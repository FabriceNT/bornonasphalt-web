<?php
// POST /api/auth-signup.php
// body: { "name": "...", "email": "...", "password": "..." }
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
$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name, email and password are all required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'That email address doesn\'t look valid.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    $db = boa_db();

    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'An account with that email already exists.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $insert->execute([$name, $email, $hash]);
    $userId = (int) $db->lastInsertId();

    $_SESSION['user_id'] = $userId;

    echo json_encode(['user' => ['id' => $userId, 'name' => $name, 'email' => $email]]);
} catch (Exception $e) {
    error_log('Signup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not create account. Please try again.']);
}
