<?php
// GET /api/admin-orders.php
require_once __DIR__ . '/config.php';
boa_require_admin();

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    $db = boa_db();
    
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = 25;
    $offset = ($page - 1) * $limit;
    
    $status = isset($_GET['status']) && $_GET['status'] !== '' ? trim($_GET['status']) : null;
    
    $where = [];
    $params = [];
    if ($status !== null) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    
    $whereSql = '';
    if (!empty($where)) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }
    
    // Get total count
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM orders $whereSql");
        $countStmt->execute($params);
        $totalCount = (int)$countStmt->fetchColumn();
        
        // Get orders list (columns must map exactly to DB and request rules)
        $query = "SELECT id, created_at, email, total_cents, status, provider, provider_order_id 
                  FROM orders 
                  $whereSql 
                  ORDER BY id DESC 
                  LIMIT ? OFFSET ?";
        
        $stmt = $db->prepare($query);
        
        $paramIndex = 1;
        foreach ($params as $p) {
            $stmt->bindValue($paramIndex++, $p, PDO::PARAM_STR);
        }
        
        // LIMIT and OFFSET must be bound as PARAM_INT
        $stmt->bindValue($paramIndex++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $now = time();
        foreach ($orders as &$order) {
            // stale_pending flag: pending_payment and created_at older than 30 mins
            $order['stale_pending'] = (
                $order['status'] === 'pending_payment' && 
                (strtotime($order['created_at']) < ($now - 30 * 60))
            );
        }
        
        echo json_encode([
            'orders' => $orders,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;

} elseif ($action === 'detail') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid order ID']);
        exit;
    }
    
    try {
        $db = boa_db();
        $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['error' => 'Order not found']);
            exit;
        }
        
        // Parse cart_json
        $order['cart_json'] = json_decode($order['cart_json'], true) ?: [];
        
        echo json_encode(['order' => $order]);
        
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
