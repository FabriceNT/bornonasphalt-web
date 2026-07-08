<?php
// GET /api/addresses-list.php
// returns: { "addresses": [ { id, label, full_name, address1, city, state_code, zip, country_code, is_default }, ... ] }

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
    $stmt = $db->prepare('SELECT id, label, full_name, address1, city, state_code, zip, country_code, is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['addresses' => $stmt->fetchAll()]);
} catch (Exception $e) {
    error_log('addresses-list error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load addresses.']);
}
