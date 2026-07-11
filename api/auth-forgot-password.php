<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = strtolower(trim($input['email'] ?? ''));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    // Réponse identique même si invalide — anti-énumération
    echo json_encode(['success' => true]);
    exit;
}

try {
    $db = boa_db();
    $stmt = $db->prepare('SELECT id, name FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // Invalider les tokens précédents non utilisés pour cet user
        $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
           ->execute([$user['id']]);

        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db->prepare('INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)')
           ->execute([$user['id'], $token, $expires_at]);

        $reset_link = 'https://bornonasphalt.com/reset-password.html?token=' . $token;
        $subject = 'Reset your Born on Asphalt password';
        $body  = "Hey {$user['name']},\r\n\r\n";
        $body .= "We received a request to reset your password.\r\n\r\n";
        $body .= "Click the link below to choose a new one:\r\n";
        $body .= "{$reset_link}\r\n\r\n";
        $body .= "This link expires in 1 hour.\r\n\r\n";
        $body .= "If you didn't request this, ignore this email — your account is safe.\r\n\r\n";
        $body .= "— Jake from Born on Asphalt\r\n";
        $body .= "bornonasphalt.com";

        $headers  = 'From: Born on Asphalt <noreply@bornonasphalt.com>' . "\r\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

        mail($email, $subject, $body, $headers);
    }

    // Toujours retourner succès — anti-énumération
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('Forgot password error: ' . $e->getMessage());
    echo json_encode(['success' => true]);
}
