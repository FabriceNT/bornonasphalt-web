<?php
// POST /api/webhook-stripe.php
// Configure this URL in Stripe Dashboard > Developers > Webhooks, listening
// for the "payment_intent.succeeded" event (NOT checkout.session.completed
// — that was the old redirect-based flow, no longer used).

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/stripe.php';
require_once __DIR__ . '/lib/printful.php';
require_once __DIR__ . '/lib/printify.php';
require_once __DIR__ . '/lib/fulfillment.php';
require_once __DIR__ . '/lib/mailer.php';
require_once __DIR__ . '/lib/meta.php';

// Catches PHP fatal errors (TypeError, Error, etc.) that catch(Exception)
// can't — writes file/line/message to order-errors.log so you can see it
// via File Manager instead of hunting hPanel's error log UI.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $logLine = json_encode([
            'time' => date('c'),
            'fatal_error' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]) . "\n";
        @file_put_contents(__DIR__ . '/logs/order-errors.log', $logLine, FILE_APPEND | LOCK_EX);
    }
});

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = boa_stripe_verify_webhook($payload, $sigHeader, STRIPE_WEBHOOK_SECRET);
} catch (Throwable $e) {
    error_log('Stripe webhook signature check failed: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['error' => 'Webhook Error: ' . $e->getMessage()]);
    exit;
}

if ($event['type'] !== 'payment_intent.succeeded') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

$paymentIntentId = null;

try {
    $pi = $event['data']['object']; // the full PaymentIntent — no extra API call needed
    $paymentIntentId = $pi['id'];

    // A card saved via setup_future_usage isn't automatically the
    // customer's default — set it now so Payment Element's "redisplay"
    // feature has an obvious one to preselect next checkout.
    if (!empty($pi['customer']) && !empty($pi['payment_method'])) {
        try {
            boa_stripe_set_default_payment_method($pi['customer'], $pi['payment_method']);
        } catch (Exception $e) {
            error_log('Could not set default payment method: ' . $e->getMessage());
        }
    }

    $cart = json_decode($pi['metadata']['cart'] ?? '[]', true);

    // Marquer le code promo comme utilisé
    $usedPromoCode = $pi['metadata']['promo_code'] ?? null;
    if ($usedPromoCode) {
        try {
            $dbPromo = boa_db();
            $dbPromo->prepare('
                UPDATE newsletter_subscribers 
                SET used_at = NOW() 
                WHERE promo_code = ? AND used_at IS NULL
            ')->execute([$usedPromoCode]);
        } catch (Exception $e) {
            error_log('Could not mark promo code as used: ' . $e->getMessage());
        }
    }

    if (empty($cart)) {
        error_log("PaymentIntent {$paymentIntentId} succeeded but had no cart metadata.");
        http_response_code(200);
        echo json_encode(['received' => true]);
        exit;
    }

    $cartItems = array_map(function ($c) {
        $product = boa_find_product($c['id']);
        $variant = boa_find_variant($c['id'], $c['color'] ?? '', $c['size'] ?? '');
        return [
            'id' => $c['id'],
            'title' => ($product['title'] ?? $c['id']) . " ({$c['color']}, {$c['size']})",
            'qty' => $c['qty'],
            'printful_sync_variant_id' => $variant['printful_sync_variant_id'] ?? null,
            'printify_product_id' => $product['printify_product_id'] ?? null,
            'printify_variant_id' => $variant['printify_variant_id'] ?? null,
        ];
    }, $cart);

    // The client sends the shipping address at confirmPayment() time (see
    // js/main.js), so Stripe attaches it directly to the PaymentIntent —
    // no dependency on Checkout's own address-collection UI at all now.
    $shippingAddress = $pi['shipping']['address'] ?? [];
    $shipping = [
        'name' => $pi['shipping']['name'] ?? 'Customer',
        'address1' => $shippingAddress['line1'] ?? '',
        'city' => $shippingAddress['city'] ?? '',
        'state_code' => $shippingAddress['state'] ?? '',
        'country_code' => $shippingAddress['country'] ?? 'US',
        'zip' => $shippingAddress['postal_code'] ?? '',
        'email' => $pi['receipt_email'] ?? '',
    ];

    $result = boa_fulfill_cheapest($cartItems, $shipping);

    $logLine = json_encode([
        'time' => date('c'),
        'payment_intent_id' => $paymentIntentId,
        'chosen_provider' => $result['chosen_provider'],
        'chosen_order_id' => $result['chosen_order_id'],
        'printful_total_cents' => $result['printful_total_cents'],
        'printify_total_cents' => $result['printify_total_cents'],
        'note' => $result['note'] ?? null,
        'cart' => $cart,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/orders.log', $logLine, FILE_APPEND | LOCK_EX);

    // Save to the database so a signed-in customer can see their order
    // history. Linked to an account only if the email matches one — guest
    // checkouts still get a row (user_id NULL) so nothing is lost.
    try {
        $db = boa_db();
        $userId = null;
        if (!empty($shipping['email'])) {
            $userStmt = $db->prepare('SELECT id FROM users WHERE email = ?');
            $userStmt->execute([strtolower($shipping['email'])]);
            $matchedUser = $userStmt->fetch();
            if ($matchedUser) {
                $userId = (int) $matchedUser['id'];
            }
        }

        $totalCents = (int) $pi['amount'];
        $subtotalCents = array_sum(array_map(function ($c) {
            return boa_price_cents_for_size($c['size']) * $c['qty'];
        }, $cart));
        $discountCents = (int) ($pi['metadata']['discount_cents'] ?? 0);
        $shippingCents = max(0, $totalCents - $subtotalCents + $discountCents);

        $insertOrder = $db->prepare('INSERT INTO orders (user_id, stripe_session_id, email, provider, provider_order_id, cart_json, subtotal_cents, shipping_cents, total_cents, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertOrder->execute([
            $userId,
            $paymentIntentId,
            $shipping['email'] ?? '',
            $result['chosen_provider'],
            (string) $result['chosen_order_id'],
            json_encode($cart),
            $subtotalCents,
            $shippingCents,
            $totalCents,
            'created',
        ]);
    } catch (Exception $e) {
        error_log('Could not save order to database: ' . $e->getMessage());
    }

    // Confirmation email
    try {
        if (!empty($shipping['email'])) {
            boa_send_order_confirmation(
                $shipping['email'],
                $shipping['name'],
                substr($paymentIntentId, 0, 12),
                $cart,
                $shipping,
                $subtotalCents,
                $shippingCents,
                $totalCents
            );
        }
    } catch (Exception $e) {
        error_log('Order confirmation email failed: ' . $e->getMessage());
    }

    // Meta CAPI — Purchase
    try {
        $capiEmail   = $shipping['email'] ?? '';
        $capiEventId = 'purchase_' . $paymentIntentId;
        $capiValue   = (int) $pi['amount']; // montant après discount
        boa_meta_capi_purchase($capiEventId, $capiValue, 'USD', $capiEmail);
    } catch (Exception $e) {
        error_log('Meta CAPI call failed: ' . $e->getMessage());
    }

    http_response_code(200);
    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    error_log('Failed to create a fulfillment order after successful payment: ' . $e->getMessage());
    $logLine = json_encode([
        'time' => date('c'),
        'error' => $e->getMessage(),
        'payment_intent_id' => $paymentIntentId,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/order-errors.log', $logLine, FILE_APPEND | LOCK_EX);

    // Still 200 — this needs a human fix, not a Stripe retry of the same event.
    http_response_code(200);
    echo json_encode(['received' => true, 'note' => 'Order creation failed server-side, check logs.']);
}
