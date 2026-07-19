<?php
// GET /api/admin-logs.php
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

if ($action === 'tail') {
    $file = $_GET['file'] ?? '';
    if ($file === 'orders') {
        $filepath = dirname(__DIR__) . '/api/logs/orders.log';
    } elseif ($file === 'errors') {
        $filepath = dirname(__DIR__) . '/api/logs/order-errors.log';
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file parameter']);
        exit;
    }

    $linesCount = min(500, max(1, (int)($_GET['lines'] ?? 100)));

    if (!file_exists($filepath)) {
        echo json_encode(['lines' => []]);
        exit;
    }

    $lines = [];
    $fp = fopen($filepath, 'r');
    if ($fp) {
        // Efficient backward reading
        fseek($fp, 0, SEEK_END);
        $totalBytes = ftell($fp);
        
        $linesReceived = 0;
        $buffer = '';
        $chunkSize = 4096;
        $offset = $totalBytes;
        
        while ($offset > 0 && $linesReceived <= $linesCount) {
            $readSize = min($chunkSize, $offset);
            $offset -= $readSize;
            fseek($fp, $offset, SEEK_SET);
            $chunk = fread($fp, $readSize);
            $buffer = $chunk . $buffer;
            
            $newlines = substr_count($chunk, "\n");
            $linesReceived += $newlines;
        }
        fclose($fp);
        
        $rawLines = explode("\n", rtrim($buffer, "\n"));
        // Slice the last N elements
        $lines = array_slice($rawLines, -$linesCount);
    }
    
    echo json_encode(['lines' => $lines]);
    exit;

} elseif ($action === 'status') {
    $filepath = dirname(__DIR__) . '/api/logs/order-errors.log';
    $recentErrorsCount = 0;
    
    if (file_exists($filepath)) {
        $fp = fopen($filepath, 'r');
        if ($fp) {
            $twentyFourHoursAgo = time() - 86400;
            while (($line = fgets($fp)) !== false) {
                $line = trim($line);
                if (empty($line)) continue;
                
                $data = json_decode($line, true);
                if ($data && isset($data['time'])) {
                    $timestamp = strtotime($data['time']);
                    if ($timestamp && $timestamp >= $twentyFourHoursAgo) {
                        $recentErrorsCount++;
                    }
                }
            }
            fclose($fp);
        }
    }
    
    echo json_encode(['recent_errors' => $recentErrorsCount]);
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
