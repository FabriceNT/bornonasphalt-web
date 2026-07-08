<?php
// POST /api/addresses-delete.php
// body: { "id": 3 }
// returns: { "ok": true }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing address id.']);
    exit;
}

try {
    $db = boa_db();
    // The user_id check in WHERE is what prevents deleting someone else's address.
    $stmt = $db->prepare('DELETE FROM addresses WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $_SESSION['user_id']]);
    echo json_encode(['ok' => true]);
} catch (Exception $e) {
    error_log('addresses-delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not delete address.']);
}
