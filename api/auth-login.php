<?php
// POST /api/auth-login.php
// body: { "email": "...", "password": "..." }
// returns: { "user": { "id": 1, "name": "...", "email": "..." } }

require_once __DIR__ . '/config.php';

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
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required.']);
    exit;
}

$rl_key = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? '');
boa_rate_limit($rl_key, 10, 900); // 10 attempts / 15 min

try {
    $db = boa_db();
    $stmt = $db->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Same error for "no such user" and "wrong password" — don't reveal
    // which one it was, that just helps someone enumerate valid emails.
    if (!$user || !password_verify($password, $user['password_hash'])) {
        boa_rate_limit_record($rl_key);
        http_response_code(401);
        echo json_encode(['error' => 'Incorrect email or password.']);
        exit;
    }

    $_SESSION['user_id'] = (int) $user['id'];

    // Rattacher les commandes guest passées avec le même email
    $db->prepare('UPDATE orders SET user_id = ? WHERE email = ? AND user_id IS NULL')
       ->execute([(int) $user['id'], strtolower(trim($user['email']))]);

    echo json_encode(['user' => ['id' => (int) $user['id'], 'name' => $user['name'], 'email' => $user['email']]]);
} catch (Exception $e) {
    error_log('Login error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not sign in. Please try again.']);
}
