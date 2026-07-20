<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/newsletter.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://bornonasphalt.com');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$nl_rl_key = 'newsletter:' . ($_SERVER['REMOTE_ADDR'] ?? '');
boa_rate_limit($nl_rl_key, 5, 3600); // 5 attempts / 60 min
boa_rate_limit_record($nl_rl_key);

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

$result = boa_newsletter_subscribe($email);
if (!$result['success']) {
    http_response_code(isset($result['error']) && $result['error'] === 'Invalid email' ? 400 : 500);
}
echo json_encode($result);
