<?php
// GET /api/admin-newsletter.php
require_once __DIR__ . '/config.php';
boa_require_admin();

// Do not send CORS for export since it's a direct file download redirect
$action = $_GET['action'] ?? '';

if ($action === 'export') {
    try {
        $db = boa_db();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="boa-subscribers.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Output header row
        fputcsv($output, ['Email', 'Subscribed At', 'Promo Code', 'Used At']);
        
        // Query active subscribers
        $stmt = $db->query("SELECT email, created_at, promo_code, used_at FROM newsletter_subscribers WHERE unsubscribed_at IS NULL ORDER BY created_at DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, [
                $row['email'],
                $row['created_at'],
                $row['promo_code'],
                $row['used_at'] ?: 'N/A'
            ]);
        }
        fclose($output);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Error generating export: " . $e->getMessage();
    }
    exit;
}

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

if ($action === 'list') {
    try {
        $db = boa_db();
        
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $countStmt = $db->query("SELECT COUNT(*) FROM newsletter_subscribers");
        $totalCount = (int)$countStmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT email, created_at, unsubscribed_at FROM newsletter_subscribers ORDER BY created_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(2, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $subscribers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'subscribers' => $subscribers,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;

} elseif ($action === 'stats') {
    try {
        $db = boa_db();
        
        $totalSubscribers = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers")->fetchColumn();
        $activeSubscribers = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE unsubscribed_at IS NULL")->fetchColumn();
        $totalPromos = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE promo_code IS NOT NULL")->fetchColumn();
        $usedPromos = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE used_at IS NOT NULL")->fetchColumn();
        $expiredPromos = (int)$db->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE expires_at < NOW() AND used_at IS NULL")->fetchColumn();
        
        echo json_encode([
            'total_subscribers' => $totalSubscribers,
            'active_subscribers' => $activeSubscribers,
            'total_promos_generated' => $totalPromos,
            'promos_used' => $usedPromos,
            'promos_expired' => $expiredPromos
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
