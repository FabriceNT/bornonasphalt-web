<?php
// POST /api/reviews-submit.php
require_once __DIR__ . '/config.php';

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON.']); exit; }

$productId   = trim($input['product_id']   ?? '');
$rating      = (int)($input['rating']      ?? 0);
$title       = trim($input['title']        ?? '');
$body        = trim($input['body']         ?? '');
$email       = strtolower(trim($input['email'] ?? ''));
$displayName = trim($input['display_name'] ?? 'Anonymous');
$color       = trim($input['color']        ?? '');
$size        = trim($input['size']         ?? '');
$photosRaw   = $input['photos']            ?? [];

if (!$productId || $rating < 1 || $rating > 5 || !$email || strlen($body) < 10) {
    http_response_code(422);
    echo json_encode(['error' => 'product_id, rating (1-5), email et body (min 10 chars) sont requis.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422); echo json_encode(['error' => 'Email invalide.']); exit;
}

$title       = mb_substr(strip_tags($title),       0, 120);
$body        = mb_substr(strip_tags($body),        0, 2000);
$displayName = mb_substr(strip_tags($displayName), 0, 80);

// Valider les photos : uniquement fichiers déjà uploadés dans /uploads/reviews/
$photos = [];
if (is_array($photosRaw)) {
    foreach (array_slice($photosRaw, 0, 3) as $p) {
        $p = trim($p);
        if (preg_match('#^/uploads/reviews/[a-f0-9]{24}\.(jpg|png|webp)$#', $p)) {
            if (file_exists(dirname(__DIR__) . $p)) $photos[] = $p;
        }
    }
}
$photosJson = empty($photos) ? null : json_encode($photos);

try {
    $db = boa_db();

    $dupStmt = $db->prepare('SELECT id FROM reviews WHERE email = ? AND product_id = ? LIMIT 1');
    $dupStmt->execute([$email, $productId]);
    if ($dupStmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'You have already submitted a review for this product.']);
        exit;
    }

    $orderStmt = $db->prepare(
        "SELECT id FROM orders WHERE email = ? AND cart_json LIKE ? AND status != 'failed' LIMIT 1"
    );
    $orderStmt->execute([$email, '%"id":"' . $productId . '"%']);
    $verified = $orderStmt->fetch() ? 1 : 0;

    $stmt = $db->prepare(
        'INSERT INTO reviews
            (product_id, rating, title, body, email, display_name, color, size, photos, verified, approved)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
    );
    $stmt->execute([$productId, $rating, $title, $body, $email, $displayName, $color, $size, $photosJson, $verified]);

    echo json_encode([
        'success'  => true,
        'verified' => (bool)$verified,
        'message'  => $verified
            ? 'Thank you! Your verified review is pending approval and will appear shortly.'
            : 'Thank you! Your review is pending approval.',
    ]);
} catch (Exception $e) {
    error_log('reviews-submit error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save review. Please try again.']);
}
