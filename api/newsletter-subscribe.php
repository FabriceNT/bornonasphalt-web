<?php
require_once __DIR__ . '/lib/newsletter.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://bornonasphalt.com');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

$result = boa_newsletter_subscribe($email);
if (!$result['success']) {
    http_response_code(isset($result['error']) && $result['error'] === 'Invalid email' ? 400 : 500);
}
echo json_encode($result);
