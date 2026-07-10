<?php
// POST /api/paypal-capture-order.php
// body: { "orderID": "..." }
// returns: { "ok": true } or { "error": "..." }
//
// Unlike Stripe (where a webhook confirms payment asynchronously), PayPal
// orders are captured synchronously via a server-to-server call we make
// ourselves right here — so this endpoint IS the trusted confirmation,
// not something the browser could fake.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/paypal.php';
require_once __DIR__ . '/lib/printful.php';
require_once __DIR__ . '/lib/printify.php';
require_once __DIR__ . '/lib/fulfillment.php';
require_once __DIR__ . '/lib/mailer.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$orderId = $input['orderID'] ?? null;

if (!$orderId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing orderID.']);
    exit;
}

$pending = $_SESSION['paypal_pending_orders'][$orderId] ?? null;
if (!$pending) {
    http_response_code(400);
    echo json_encode(['error' => 'No matching pending order for this session.']);
    exit;
}

try {
    $capture = boa_paypal_capture_order($orderId);
    $status = $capture['status'] ?? '';
    if ($status !== 'COMPLETED') {
        throw new Exception("Unexpected capture status: {$status}");
    }

    unset($_SESSION['paypal_pending_orders'][$orderId]);
    $cart = $pending['cart'];
    $shipping = $pending['shipping'];

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

    $result = boa_fulfill_cheapest($cartItems, $shipping);

    $logLine = json_encode([
        'time' => date('c'),
        'paypal_order_id' => $orderId,
        'chosen_provider' => $result['chosen_provider'],
        'chosen_order_id' => $result['chosen_order_id'],
        'printful_total_cents' => $result['printful_total_cents'],
        'printify_total_cents' => $result['printify_total_cents'],
        'note' => $result['note'] ?? null,
        'cart' => $cart,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/orders.log', $logLine, FILE_APPEND | LOCK_EX);

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

        $discountCents = $pending['discount_cents'] ?? 0;
        $subtotalCents = array_sum(array_map(function ($c) {
            return boa_price_cents_for_size($c['size']) * $c['qty'];
        }, $cart));
        $shippingCents = boa_shipping_cents($subtotalCents);
        $totalCents = max(0, $subtotalCents + $shippingCents - $discountCents);

        $insertOrder = $db->prepare('INSERT INTO orders (user_id, stripe_session_id, email, provider, provider_order_id, cart_json, subtotal_cents, shipping_cents, total_cents, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insertOrder->execute([
            $userId,
            'paypal_' . $orderId, // reusing the same column across both payment providers
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
        error_log('Could not save PayPal order to database: ' . $e->getMessage());
    }

    // Marquer le code promo comme utilisé
    $usedPromoCode = $pending['promo_code'] ?? null;
    if ($usedPromoCode) {
        try {
            $dbPromo = boa_db();
            $dbPromo->prepare('
                UPDATE newsletter_subscribers 
                SET used_at = NOW() 
                WHERE promo_code = ? AND used_at IS NULL
            ')->execute([$usedPromoCode]);
        } catch (Exception $e) {
            error_log('Could not mark promo code as used (PayPal): ' . $e->getMessage());
        }
    }

    // Confirmation email
    try {
        if (!empty($shipping['email'])) {
            boa_send_order_confirmation(
                $shipping['email'],
                $shipping['name'],
                substr($orderId, 0, 12),
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

    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('PayPal capture/fulfillment error: ' . $e->getMessage());
    $logLine = json_encode([
        'time' => date('c'),
        'error' => $e->getMessage(),
        'paypal_order_id' => $orderId,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/order-errors.log', $logLine, FILE_APPEND | LOCK_EX);

    http_response_code(500);
    echo json_encode(['error' => 'Payment captured but order creation failed server-side — contact support.']);
}
