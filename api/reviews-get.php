<?php
// GET /api/reviews-get.php?product_id=A1
// GET /api/reviews-get.php?featured=1&limit=4
// GET /api/reviews-get.php?summary=1

require_once __DIR__ . '/config.php';
boa_start_session();

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

header('Content-Type: application/json');

$productId = trim($_GET['product_id'] ?? '');
$featured  = !empty($_GET['featured']);
$summary   = !empty($_GET['summary']);
$limit     = min((int)($_GET['limit'] ?? 20), 50);

function decode_photos(array $row): array {
    $row['photos'] = isset($row['photos']) ? json_decode($row['photos'], true) ?? [] : [];
    return $row;
}

try {
    $db = boa_db();

    if ($summary) {
        $stmt = $db->query(
            'SELECT COUNT(*) as total, ROUND(AVG(rating),1) as average FROM reviews WHERE approved=1'
        );
        $row = $stmt->fetch();
        echo json_encode(['total' => (int)$row['total'], 'average' => (float)$row['average']]);
        exit;
    }

    if ($featured) {
        $stmt = $db->prepare(
            'SELECT product_id, rating, title, body, display_name, color, size, photos, verified, created_at
             FROM reviews
             WHERE approved=1 AND rating>=4 AND LENGTH(body)>=30
             ORDER BY verified DESC, rating DESC, created_at DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        echo json_encode(['reviews' => array_map('decode_photos', $stmt->fetchAll())]);
        exit;
    }

    if (!$productId) {
        http_response_code(400); echo json_encode(['error' => 'product_id requis.']); exit;
    }

    $summaryStmt = $db->prepare(
        'SELECT COUNT(*) as total, ROUND(AVG(rating),1) as average,
                SUM(rating=5) as r5, SUM(rating=4) as r4,
                SUM(rating=3) as r3, SUM(rating=2) as r2, SUM(rating=1) as r1
         FROM reviews WHERE product_id=? AND approved=1'
    );
    $summaryStmt->execute([$productId]);
    $s = $summaryStmt->fetch();

    $reviewsStmt = $db->prepare(
        'SELECT rating, title, body, display_name, color, size, photos, verified, created_at
         FROM reviews WHERE product_id=? AND approved=1
         ORDER BY verified DESC, rating DESC, created_at DESC LIMIT ?'
    );
    $reviewsStmt->bindValue(1, $productId, PDO::PARAM_STR);
    $reviewsStmt->bindValue(2, $limit,     PDO::PARAM_INT);
    $reviewsStmt->execute();

    $myReview = null;
    if (!empty($_SESSION['user_id'])) {
      $db2 = boa_db();
      $uStmt = $db2->prepare('SELECT email FROM users WHERE id = ?');
      $uStmt->execute([$_SESSION['user_id']]);
      $uRow = $uStmt->fetch();
      if ($uRow) {
        $mStmt = $db2->prepare(
          'SELECT id, rating, title, body, color, size, photos, created_at
           FROM reviews WHERE product_id = ? AND email = ? LIMIT 1'
        );
        $mStmt->execute([$productId, $uRow['email']]);
        $mRow = $mStmt->fetch();
        if ($mRow) $myReview = decode_photos($mRow);
      }
    }

    echo json_encode([
        'summary' => [
            'total'     => (int)$s['total'],
            'average'   => (float)$s['average'],
            'breakdown' => [5=>(int)$s['r5'],4=>(int)$s['r4'],3=>(int)$s['r3'],2=>(int)$s['r2'],1=>(int)$s['r1']],
        ],
        'reviews' => array_map('decode_photos', $reviewsStmt->fetchAll()),
        'my_review' => $myReview,
    ]);

} catch (Exception $e) {
    error_log('reviews-get error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not load reviews.']);
}
