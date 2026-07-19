<?php
// /api/admin-reviews.php
require_once __DIR__ . '/config.php';
boa_require_admin();

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'list') {
        $filter = $_GET['filter'] ?? 'pending';
        try {
            $db = boa_db();
            if ($filter === 'pending') {
                $stmt = $db->prepare("SELECT * FROM reviews WHERE approved = 0 ORDER BY id DESC");
                $stmt->execute();
            } elseif ($filter === 'approved') {
                $stmt = $db->prepare("SELECT * FROM reviews WHERE approved = 1 ORDER BY id DESC");
                $stmt->execute();
            } else {
                $stmt = $db->prepare("SELECT * FROM reviews ORDER BY id DESC");
                $stmt->execute();
            }
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($reviews as &$r) {
                $r['photos'] = json_decode($r['photos'] ?? '[]', true) ?: [];
            }
            
            echo json_encode(['reviews' => $reviews]);
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
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;
    
    $action = $input['action'] ?? '';
    $id = (int)($input['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid review ID']);
        exit;
    }
    
    try {
        $db = boa_db();
        
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("UPDATE reviews SET approved = 0 WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        } elseif ($action === 'delete') {
            $stmt = $db->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
