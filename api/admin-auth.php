<?php
// POST /api/admin-auth.php
require_once __DIR__ . '/config.php';

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

if (!defined('ADMIN_PASSWORD_HASH')) {
    http_response_code(503);
    echo json_encode(['error' => 'Admin not configured']);
    exit;
}

// Auto-create rate limiting table if not exists
try {
    $db = boa_db();
    $db->exec("CREATE TABLE IF NOT EXISTS `admin_login_attempts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `ip` VARCHAR(45) NOT NULL,
        `attempted_at` DATETIME NOT NULL,
        KEY `idx_ip_attempted` (`ip`, `attempted_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    error_log("Failed to ensure admin_login_attempts table: " . $e->getMessage());
}

$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true) ?? $_POST;
} else {
    $input = $_GET;
}

$action = $input['action'] ?? '';

if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    $password = $input['password'] ?? '';
    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Password required']);
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Rate Limiting Check: count failed attempts for IP in last 15 mins
    try {
        $db = boa_db();
        $fifteenMinsAgo = date('Y-m-d H:i:s', time() - 15 * 60);
        $stmt = $db->prepare("SELECT COUNT(*) FROM admin_login_attempts WHERE ip = ? AND attempted_at >= ?");
        $stmt->execute([$ip, $fifteenMinsAgo]);
        $failedAttempts = (int)$stmt->fetchColumn();

        if ($failedAttempts >= 5) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many attempts. Try again later.']);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }

    // Verify password
    if (password_verify($password, ADMIN_PASSWORD_HASH)) {
        boa_start_session();
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        echo json_encode(['success' => true]);
    } else {
        // Record failed attempt
        try {
            $stmt = $db->prepare("INSERT INTO admin_login_attempts (ip, attempted_at) VALUES (?, NOW())");
            $stmt->execute([$ip]);
        } catch (Exception $e) {
            error_log("Failed to log failed login attempt: " . $e->getMessage());
        }

        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
    exit;

} elseif ($action === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
        exit;
    }

    boa_start_session();
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    echo json_encode(['success' => true]);
    exit;

} elseif ($action === 'status') {
    boa_start_session();
    $authenticated = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    echo json_encode(['authenticated' => $authenticated]);
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
