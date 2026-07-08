<?php
// GET /api/orders-list.php
// returns: { "orders": [ { id, provider, provider_order_id, cart, total_cents, status, created_at }, ... ] }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in.']);
    exit;
}

try {
    $db = boa_db();
    $stmt = $db->prepare('SELECT id, provider, provider_order_id, cart_json, subtotal_cents, shipping_cents, total_cents, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    $orders = array_map(function ($row) {
        return [
            'id' => (int) $row['id'],
            'provider' => $row['provider'],
            'provider_order_id' => $row['provider_order_id'],
            'cart' => json_decode($row['cart_json'], true),
            'subtotal_cents' => (int) $row['subtotal_cents'],
            'shipping_cents' => (int) $row['shipping_cents'],
            'total_cents' => (int) $row['total_cents'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
        ];
    }, $rows);

    echo json_encode(['orders' => $orders]);
} catch (Exception $e) {
    error_log('orders-list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load orders.']);
}
