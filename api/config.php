<?php
// Loads secrets from a file OUTSIDE public_html so they can never be
// downloaded directly, even by mistake. See secrets.example.php for what
// to put in it, and README.md for where exactly to place it.
//
// Path from here (public_html/api/config.php):
//   dirname(__DIR__)     -> public_html
//   dirname(__DIR__, 2)  -> the folder ABOVE public_html (your home dir)
$secretsFile = dirname(__DIR__, 2) . '/secrets.php';

if (!file_exists($secretsFile)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server misconfigured: secrets.php not found above public_html. See README.md.'
    ]);
    exit;
}

require_once $secretsFile;

// secrets.php must define these constants:
$requiredConstants = [
    'STRIPE_SECRET_KEY',
    'STRIPE_PUBLISHABLE_KEY',
    'STRIPE_WEBHOOK_SECRET',
    'PRINTFUL_API_TOKEN',
    'PRINTIFY_API_TOKEN',
    'PRINTIFY_SHOP_ID',
    'FRONTEND_URL',
    'CHECKOUT_SUCCESS_URL',
    'CHECKOUT_CANCEL_URL',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
    'PAYPAL_CLIENT_ID',
    'PAYPAL_CLIENT_SECRET',
    'PAYPAL_MODE',
];
foreach ($requiredConstants as $name) {
    if (!defined($name)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => "Server misconfigured: {$name} is not defined in secrets.php."]);
        exit;
    }
}

if (!defined('PRINTFUL_STORE_ID')) {
    define('PRINTFUL_STORE_ID', '');
}

// Send CORS headers for same-origin (and localhost during development).
function boa_send_cors_headers(): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin === FRONTEND_URL || $origin === '') {
        header('Access-Control-Allow-Origin: ' . (FRONTEND_URL ?: '*'));
    }
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Stripe-Signature');
    // Sessions rely on the browser sending the PHP session cookie back —
    // required for the account endpoints (signup/login/me/logout) to work
    // when the front end calls them with fetch().
    header('Access-Control-Allow-Credentials: true');
}

// Single shared PDO connection, created on first use.
function boa_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// Starts a PHP session with cookie settings suitable for an API called via
// fetch() from the same domain. Call this at the top of any endpoint that
// needs to read or write who's logged in.
function boa_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30, // 30 days
        'path' => '/',
        'secure' => true,     // only sent over HTTPS
        'httponly' => true,   // not readable from JS — mitigates XSS token theft
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Restricts endpoint access to authenticated administrators only
function boa_require_admin(): void
{
    boa_start_session();
    if (empty($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

