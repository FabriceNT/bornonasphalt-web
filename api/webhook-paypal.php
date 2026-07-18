<?php
// POST /api/webhook-paypal.php
// Configure in PayPal Developer Dashboard > Webhooks
// Event : PAYMENT.CAPTURE.COMPLETED
//
// Safety net only — fires if paypal-capture-order.php failed
// (session expired, server error, etc.) but PayPal captured the payment.
// Verifies the event is genuine via PayPal's webhook verification API,
// then checks if the order was already processed. If not, logs for
// manual intervention.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/paypal.php';
require_once __DIR__ . '/lib/fulfillment.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/meta.php';

header('Content-Type: application/json');

$payload    = file_get_contents('php://input');
$headers    = getallheaders();
$event      = json_decode($payload, true);

if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload.']);
    exit;
}

// Only handle PAYMENT.CAPTURE.COMPLETED
if ($event['event_type'] !== 'PAYMENT.CAPTURE.COMPLETED') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

try {
    // Verify webhook signature via PayPal API
    $token = boa_paypal_get_access_token();
    $verifyBody = [
        'auth_algo'         => $headers['PAYPAL-AUTH-ALGO']         ?? '',
        'cert_url'          => $headers['PAYPAL-CERT-URL']          ?? '',
        'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID']   ?? '',
        'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG']  ?? '',
        'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
        'webhook_id'        => defined('PAYPAL_WEBHOOK_ID') ? PAYPAL_WEBHOOK_ID : '',
        'webhook_event'     => $event,
    ];

    $ch = curl_init(boa_paypal_api_base() . '/v1/notifications/verify-webhook-signature');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS     => json_encode($verifyBody),
        CURLOPT_TIMEOUT        => 10,
    ]);
    $verifyRaw = curl_exec($ch);
    curl_close($ch);

    $verifyResult = json_decode($verifyRaw, true);
    if (($verifyResult['verification_status'] ?? '') !== 'SUCCESS') {
        error_log('PayPal webhook signature verification failed: ' . $verifyRaw);
        http_response_code(401);
        echo json_encode(['error' => 'Signature verification failed.']);
        exit;
    }

    // Extract PayPal order ID from the capture resource
    $resource     = $event['resource'] ?? [];
    $links        = $resource['links'] ?? [];
    $paypalOrderId = null;
    foreach ($links as $link) {
        if (($link['rel'] ?? '') === 'up') {
            // URL format: .../v2/checkout/orders/{order_id}
            $parts = explode('/', rtrim($link['href'], '/'));
            $paypalOrderId = end($parts);
            break;
        }
    }

    if (!$paypalOrderId) {
        error_log('PayPal webhook: could not extract order ID from payload');
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Could not extract order ID.']);
        exit;
    }

    $logLine = json_encode([
        'time'           => date('c'),
        'event'          => $event['event_type'],
        'paypal_order_id'=> $paypalOrderId,
        'capture_id'     => $resource['id'] ?? null,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/orders.log', $logLine, FILE_APPEND | LOCK_EX);

    $db = boa_db();

    // Check if order was already processed by paypal-capture-order.php
    $stmt = $db->prepare(
        "SELECT id, status, cart_json FROM orders
         WHERE stripe_session_id = ? LIMIT 1"
    );
    $stmt->execute(['paypal_' . $paypalOrderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && $row['status'] !== 'pending_payment') {
        // Already handled by paypal-capture-order.php — nothing to do
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Already processed.']);
        exit;
    }

    if (!$row) {
        // Payment captured but no record at all — log + alert email
        $errLine = json_encode([
            'time'            => date('c'),
            'alert'           => 'ORPHANED_PAYPAL_PAYMENT',
            'paypal_order_id' => $paypalOrderId,
            'capture_id'      => $resource['id'] ?? null,
            'amount'          => $resource['amount'] ?? null,
        ]) . "\n";
        file_put_contents(__DIR__ . '/logs/order-errors.log', $errLine, FILE_APPEND | LOCK_EX);
        error_log("ORPHANED PayPal payment — order ID: {$paypalOrderId}");

        // Alert email — paiement reçu mais aucune commande en base
        $alertSubject = '[BOA URGENT] Paiement PayPal orphelin — intervention requise';
        $alertBody    = "Un paiement PayPal a été capturé mais aucune commande correspondante n'existe en base.\n\n"
            . "PayPal Order ID : {$paypalOrderId}\n"
            . "Capture ID      : " . ($resource['id'] ?? 'N/A') . "\n"
            . "Montant         : " . json_encode($resource['amount'] ?? []) . "\n"
            . "Heure           : " . date('c') . "\n\n"
            . "Action requise : vérifier le dashboard PayPal et créer la commande manuellement si nécessaire.\n"
            . "Log complet    : public_html/api/logs/order-errors.log\n";
        @mail(
            'support@bornonasphalt.com',
            $alertSubject,
            $alertBody,
            implode("\r\n", [
                'From: Born on Asphalt <orders@bornonasphalt.com>',
                'Content-Type: text/plain; charset=UTF-8',
            ])
        );

        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Orphaned payment logged.']);
        exit;
    }

    // Row exists with status = pending_payment — session expired before capture
    // Recover cart and trigger fulfillment
    $pending = json_decode($row['cart_json'], true);
    if (!$pending || empty($pending['cart'])) {
        error_log("PayPal webhook: could not recover cart for order {$paypalOrderId}");
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Cart recovery failed.']);
        exit;
    }

    $cart     = $pending['cart'];
    $shipping = $pending['shipping'];
    $discountCents = $pending['discount_cents'] ?? 0;

    $cartItems = array_map(function ($c) {
        $product = boa_find_product($c['id']);
        $variant = boa_find_variant($c['id'], $c['color'] ?? '', $c['size'] ?? '');
        return [
            'id'                       => $c['id'],
            'title'                    => ($product['title'] ?? $c['id']) . " ({$c['color']}, {$c['size']})",
            'qty'                      => $c['qty'],
            'printful_sync_variant_id' => $variant['printful_sync_variant_id'] ?? null,
            'printify_product_id'      => $product['printify_product_id'] ?? null,
            'printify_variant_id'      => $variant['printify_variant_id'] ?? null,
        ];
    }, $cart);

    $result = boa_fulfill_cheapest($cartItems, $shipping);

    $subtotalCents = array_sum(array_map(function ($c) {
        $p = boa_find_product($c['id']);
        return boa_price_cents_for_product($p ?? ['id' => $c['id']], $c['size']) * $c['qty'];
    }, $cart));
    $shippingCents = boa_shipping_cents($subtotalCents);
    $totalCents    = max(0, $subtotalCents + $shippingCents - $discountCents);

    // Update the pending row to created
    $db->prepare(
        "UPDATE orders
         SET provider = ?, provider_order_id = ?, subtotal_cents = ?,
             shipping_cents = ?, total_cents = ?, status = 'created'
         WHERE id = ?"
    )->execute([
        $result['chosen_provider'],
        (string) $result['chosen_order_id'],
        $subtotalCents,
        $shippingCents,
        $totalCents,
        $row['id'],
    ]);

    // Marquer code promo utilisé
    $usedPromoCode = $pending['promo_code'] ?? null;
    if ($usedPromoCode) {
        try {
            $db->prepare(
                'UPDATE newsletter_subscribers SET used_at = NOW()
                 WHERE promo_code = ? AND used_at IS NULL'
            )->execute([$usedPromoCode]);
        } catch (Exception $e) {
            error_log('Could not mark promo code as used (PayPal webhook): ' . $e->getMessage());
        }
    }

    // Email de confirmation
    try {
        if (!empty($shipping['email'])) {
            boa_send_order_confirmation(
                $shipping['email'],
                $shipping['name'],
                substr($paypalOrderId, 0, 12),
                $cart,
                $shipping,
                $subtotalCents,
                $shippingCents,
                $totalCents
            );
        }
    } catch (Exception $e) {
        error_log('Order confirmation email failed (PayPal webhook): ' . $e->getMessage());
    }

    // Meta CAPI
    try {
        boa_meta_capi_purchase(
            'purchase_paypal_webhook_' . $paypalOrderId,
            $totalCents,
            'USD',
            $shipping['email'] ?? ''
        );
    } catch (Exception $e) {
        error_log('Meta CAPI failed (PayPal webhook): ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['received' => true, 'recovered' => true]);

} catch (Throwable $e) {
    error_log('PayPal webhook error: ' . $e->getMessage());
    $errLine = json_encode([
        'time'  => date('c'),
        'error' => $e->getMessage(),
        'event' => $event['event_type'] ?? 'unknown',
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/order-errors.log', $errLine, FILE_APPEND | LOCK_EX);
    http_response_code(200);
    echo json_encode(['received' => true, 'note' => 'Error logged.']);
}
