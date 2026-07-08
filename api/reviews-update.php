<?php
// POST /api/reviews-update.php
// body: { "review_id": 1, "rating": 5, "title": "...", "body": "..." }
require_once __DIR__ . '/config.php';

boa_send_cors_headers();
boa_start_session();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); exit; }

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['error' => 'Not logged in.']);
  exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$reviewId = (int)($input['review_id'] ?? 0);
$rating   = (int)($input['rating']    ?? 0);
$title    = mb_substr(strip_tags(trim($input['title'] ?? '')), 0, 120);
$body     = mb_substr(strip_tags(trim($input['body']  ?? '')), 0, 2000);

$photosRaw = $input['photos'] ?? [];
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

if (!$reviewId || $rating < 1 || $rating > 5 || strlen($body) < 10) {
  http_response_code(422);
  echo json_encode(['error' => 'review_id, rating (1-5) et body (min 10 chars) requis.']);
  exit;
}

try {
  $db = boa_db();

  // Vérifier que la review appartient bien à cet utilisateur
  $uStmt = $db->prepare('SELECT email FROM users WHERE id = ?');
  $uStmt->execute([$_SESSION['user_id']]);
  $user = $uStmt->fetch();
  if (!$user) { http_response_code(403); echo json_encode(['error' => 'Unauthorized.']); exit; }

  $chk = $db->prepare('SELECT id FROM reviews WHERE id = ? AND email = ?');
  $chk->execute([$reviewId, $user['email']]);
  if (!$chk->fetch()) {
    http_response_code(403);
    echo json_encode(['error' => 'Review not found or not yours.']);
    exit;
  }

  // Update — repasse approved à 0 pour re-modération
  $upd = $db->prepare(
    'UPDATE reviews SET rating = ?, title = ?, body = ?, photos = ?, approved = 0 WHERE id = ?'
  );
  $upd->execute([$rating, $title, $body, $photosJson, $reviewId]);

  echo json_encode(['success' => true, 'message' => 'Review updated and pending re-approval.']);

} catch (Exception $e) {
  error_log('reviews-update error: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'Could not update review.']);
}
