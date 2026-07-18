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
$promoCode = strtoupper(trim($input['promo_code'] ?? ''));

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
        $subtotalCents += boa_price_cents_for_product($product, $size) * $qty;
        $metadataCart[] = ['id' => $product['id'], 'color' => $color, 'size' => $size, 'qty' => $qty];
    }

    $shippingCents = boa_shipping_cents($subtotalCents);
    $totalCents = $subtotalCents + $shippingCents;

    $discountCents = 0;
    $validatedPromoCode = null;
    if (!empty($promoCode)) {
        require_once __DIR__ . '/lib/newsletter.php';
        $db = boa_db();
        $promoStmt = $db->prepare('
            SELECT id, promo_code 
            FROM newsletter_subscribers 
            WHERE promo_code = ? 
              AND used_at IS NULL 
              AND expires_at > NOW()
              AND unsubscribed_at IS NULL
        ');
        $promoStmt->execute([$promoCode]);
        $promoRow = $promoStmt->fetch();
        if ($promoRow) {
            $discountCents = (int) round($subtotalCents * 0.10);
            $totalCents = max(0, $totalCents - $discountCents);
            $validatedPromoCode = $promoRow['promo_code'];
        }
    }

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

    // Stash in session — fast path used by paypal-capture-order.php
    $_SESSION['paypal_pending_orders'][$order['id']] = [
        'cart'           => $metadataCart,
        'shipping'       => $shipping,
        'promo_code'     => $validatedPromoCode,
        'discount_cents' => $discountCents,
    ];

    // Also persist to DB immediately as safety net.
    // If the session expires before capture (mobile timeout, tab switch),
    // paypal-capture-order.php and webhook-paypal.php can recover from here.
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
        $db->prepare(
            'INSERT INTO orders
             (user_id, stripe_session_id, email, provider, provider_order_id,
              cart_json, subtotal_cents, shipping_cents, total_cents, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            'paypal_' . $order['id'],
            $shipping['email'] ?? '',
            'pending',
            $order['id'],
            json_encode([
                'cart'           => $metadataCart,
                'shipping'       => $shipping,
                'promo_code'     => $validatedPromoCode,
                'discount_cents' => $discountCents,
            ]),
            $subtotalCents,
            $shippingCents,
            $totalCents,
            'pending_payment',
        ]);
    } catch (Exception $e) {
        // Non-fatal — session is still available as primary fallback
        error_log('Could not pre-save PayPal pending order to DB: ' . $e->getMessage());
    }

    echo json_encode(['id' => $order['id']]);
} catch (Throwable $e) {
    error_log('PayPal create order error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start PayPal checkout. Please try again.']);
}
