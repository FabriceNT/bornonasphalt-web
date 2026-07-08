<?php
// GET /api/auth-me.php
// returns: { "user": { "id": 1, "name": "...", "email": "..." } } or { "user": null }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if (empty($_SESSION['user_id'])) {
    echo json_encode(['user' => null]);
    exit;
}

try {
    $db = boa_db();
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        // User was deleted but the session cookie is still around.
        unset($_SESSION['user_id']);
        echo json_encode(['user' => null]);
        exit;
    }

    echo json_encode(['user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
} catch (Exception $e) {
    error_log('auth-me error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not check session.']);
}
