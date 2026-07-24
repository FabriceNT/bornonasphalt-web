<?php
// api/cart-abandon-save.php — Save cart abandonment email + cart JSON

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/cart-abandon.php';

header('Content-Type: application/json');
boa_send_cors_headers();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
boa_rate_limit('cart_abandon_' . $remoteIp, 10, 3600);
boa_rate_limit_record('cart_abandon_' . $remoteIp);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$email = trim($input['email'] ?? '');
$cartRaw = $input['cart'] ?? '';

if (is_array($cartRaw)) {
    $cartJson = json_encode($cartRaw);
} else {
    $cartJson = (string)$cartRaw;
}

$cartDecoded = json_decode($cartJson, true);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($cartJson) || !is_array($cartDecoded) || empty($cartDecoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request data']);
    exit;
}

boa_save_cart_abandon($email, $cartJson);

echo json_encode(['ok' => true]);
