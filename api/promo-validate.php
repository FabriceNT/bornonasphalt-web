<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
boa_send_cors_headers();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code  = strtoupper(trim($input['code'] ?? ''));

if (empty($code)) {
    http_response_code(400);
    echo json_encode(['valid' => false, 'error' => 'No code provided']);
    exit;
}

try {
    $db = boa_db();
    $stmt = $db->prepare('
        SELECT id, promo_code 
        FROM newsletter_subscribers 
        WHERE promo_code = ? 
          AND used_at IS NULL 
          AND expires_at > NOW()
          AND unsubscribed_at IS NULL
    ');
    $stmt->execute([$code]);
    $row = $stmt->fetch();

    if ($row) {
        echo json_encode(['valid' => true, 'discount_percent' => 10, 'code' => $row['promo_code']]);
    } else {
        echo json_encode(['valid' => false, 'error' => 'Invalid or expired code']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['valid' => false, 'error' => 'Server error']);
}
