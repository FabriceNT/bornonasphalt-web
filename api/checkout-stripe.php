<?php
// POST /api/checkout-stripe.php
// body: { "cart": [{ "id", "color", "size", "qty" }, ...], "email": "optional@x.com" }
// returns: {
//   "client_secret": "pi_..._secret_...",
//   "customer_session_client_secret": "cuss_..._secret_..." | null,
//   "amount_cents": 3974
// }
//
// The front end uses these to mount Stripe's embedded Payment Element
// (see js/main.js) — no redirect to a separate Stripe-hosted page.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/stripe.php';

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
$email = $input['email'] ?? null;

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
        $unitCents = boa_price_cents_for_size($size);
        $subtotalCents += $unitCents * $qty;

        $metadataCart[] = ['id' => $product['id'], 'color' => $color, 'size' => $size, 'qty' => $qty];
    }

    $shippingCents = boa_shipping_cents($subtotalCents);
    $totalCents = $subtotalCents + $shippingCents;

    // If signed in, resolve (or create) their Stripe Customer so their card
    // can be saved and reoffered. Guests just pay, no account needed.
    $stripeCustomerId = null;
    if (!empty($_SESSION['user_id'])) {
        $db = boa_db();
        $stmt = $db->prepare('SELECT email, name, stripe_customer_id FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            $stripeCustomerId = $user['stripe_customer_id'];
            if (empty($stripeCustomerId)) {
                $customer = boa_stripe_create_customer($user['email'], $user['name']);
                $stripeCustomerId = $customer['id'];
                $db->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?')
                   ->execute([$stripeCustomerId, $_SESSION['user_id']]);
            }
            $email = $email ?: $user['email'];
        }
    }

    $customerSessionClientSecret = null;
    if ($stripeCustomerId) {
        try {
            $customerSession = boa_stripe_create_customer_session($stripeCustomerId);
            $customerSessionClientSecret = $customerSession['client_secret'];
        } catch (Exception $e) {
            // Not fatal — Payment Element just won't offer saved cards
            // this time, guest-style checkout still works fine.
            error_log('Could not create Stripe customer session: ' . $e->getMessage());
        }
    }

    $paymentIntent = null;
    try {
        $paymentIntent = boa_stripe_create_payment_intent($totalCents, json_encode($metadataCart), $stripeCustomerId, $email);
    } catch (Exception $e) {
        // Same "customer was deleted on Stripe's side" recovery as before.
        if ($stripeCustomerId && str_contains($e->getMessage(), 'No such customer')) {
            $customer = boa_stripe_create_customer($email ?? '', $user['name'] ?? '');
            $stripeCustomerId = $customer['id'];
            boa_db()->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?')
                     ->execute([$stripeCustomerId, $_SESSION['user_id']]);
            $paymentIntent = boa_stripe_create_payment_intent($totalCents, json_encode($metadataCart), $stripeCustomerId, $email);
        } else {
            throw $e;
        }
    }

    echo json_encode([
        'client_secret' => $paymentIntent['client_secret'],
        'customer_session_client_secret' => $customerSessionClientSecret,
        'amount_cents' => $totalCents,
    ]);
} catch (Throwable $e) {
    error_log('Stripe checkout error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not start checkout. Please try again.']);
}
