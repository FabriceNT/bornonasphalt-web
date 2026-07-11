<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
boa_send_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token    = trim($input['token'] ?? '');
$password = $input['password'] ?? '';

if (strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid token']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit;
}

try {
    $db = boa_db();
    $stmt = $db->prepare('
        SELECT id, user_id 
        FROM password_reset_tokens 
        WHERE token = ? 
          AND used_at IS NULL 
          AND expires_at > NOW()
    ');
    $stmt->execute([$token]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(400);
        echo json_encode(['error' => 'This link is invalid or has expired']);
        exit;
    }

    // Mettre à jour le mot de passe
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([$hash, $row['user_id']]);

    // Marquer le token comme utilisé
    $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
       ->execute([$row['id']]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Reset password error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again.']);
}
