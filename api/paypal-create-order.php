<?php
// POST /api/paypal-create-order.php
// body: { cart: [...], full_name, address1, city, state_code, zip, email }
// returns: { "id": "PayPal order id" }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/paypal.php';

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
$cart = $input['cart'] ?? null;

if (!is_array($cart) || count($cart) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Cart is empty.']);
    exit;
}

try {
    $metadataCart = [];
    $subtotalCents = 0;

    foreach ($cart as $item) {
        $product = boa_find_product($item['id'] ?? '');
        if ($product === null) {
            http_response_code(400);
            echo json_encode(['error' => "Unknown product id: {$item['id']}"]);
            exit;
        }

        $color = $item['color'] ?? '';
        $size = $item['size'] ?? '';
        $variant = boa_find_variant($product['id'], $color, $size);
        if ($variant === null) {
            http_response_code(400);
            echo json_encode(['error' => "\"{$product['title']}\" isn't available in {$color} / {$size}."]);
            exit;
        }

        $qty = max(1, (int) ($item['qty'] ?? 1));
        $subtotalCents += boa_price_cents_for_size($size) * $qty;
        $metadataCart[] = ['id' => $product['id'], 'color' => $color, 'size' => $size, 'qty' => $qty];
    }

    $shippingCents = boa_shipping_cents($subtotalCents);
    $totalCents = $subtotalCents + $shippingCents;

    $shipping = [
        'name' => trim($input['full_name'] ?? ''),
        'address1' => trim($input['address1'] ?? ''),
        'city' => trim($input['city'] ?? ''),
        'state_code' => trim($input['state_code'] ?? ''),
        'zip' => trim($input['zip'] ?? ''),
        'country_code' => 'US',
        'email' => trim($input['email'] ?? ''),
    ];

    $order = boa_paypal_create_order($totalCents, $shipping);

    // PayPal's own order fields (custom_id etc.) are too small to hold a
    // full cart. Stash it in the session instead, keyed by the order id,
    // and read it back in paypal-capture-order.php once payment is
    // confirmed. Relies on the same browser session completing both steps
    // shortly after each other, which is how the PayPal Buttons flow works.
    $_SESSION['paypal_pending_orders'][$order['id']] = [
        'cart' => $metadataCart,
        'shipping' => $shipping,
    ];

    echo json_encode(['id' => $order['id']]);
} catch (Throwable $e) {
    error_log('PayPal create order error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start PayPal checkout. Please try again.']);
}
