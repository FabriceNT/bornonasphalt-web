<?php
// POST /api/auth-signup.php
// body: { "name": "...", "email": "...", "password": "..." }
// returns: { "user": { "id": 1, "name": "...", "email": "..." } }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$name = trim($input['name'] ?? '');
$email = strtolower(trim($input['email'] ?? ''));
$password = $input['password'] ?? '';

if ($name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name, email and password are all required.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'That email address doesn\'t look valid.']);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters.']);
    exit;
}

try {
    $db = boa_db();

    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'An account with that email already exists.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $insert->execute([$name, $email, $hash]);
    $userId = (int) $db->lastInsertId();

    $_SESSION['user_id'] = $userId;

    // Rattacher les commandes guest passées avec le même email
    $db->prepare('UPDATE orders SET user_id = ? WHERE email = ? AND user_id IS NULL')
       ->execute([$userId, strtolower(trim($email))]);

    // Newsletter opt-in
    $promo_code = null;
    $nl = null;
    $newsletter = isset($input['newsletter']) && $input['newsletter'] === true;
    if ($newsletter) {
        require_once __DIR__ . '/lib/newsletter.php';
        $nl = boa_newsletter_subscribe($email);
        $promo_code = $nl['code'] ?? null;
    }

    $welcome_subject = 'Welcome to the Born on Asphalt family 🏁';

    $welcome_body  = "Welcome to the Born on Asphalt community — damn glad to have you here.\r\n\r\n";
    $welcome_body .= "We're a crew of obsessives who share one religion: asphalt, engines,\r\n";
    $welcome_body .= "and machines with a soul. Every piece we make carries the history of\r\n";
    $welcome_body .= "that culture — raw, authentic, timeless.\r\n\r\n";
    $welcome_body .= "───────────────────────\r\n";
    $welcome_body .= "🔧 WHAT'S COMING YOUR WAY\r\n";
    $welcome_body .= "───────────────────────\r\n";
    $welcome_body .= "→ Exclusive vintage designs inspired by the golden age of American muscle\r\n";
    $welcome_body .= "→ New releases before anyone else sees them\r\n";
    $welcome_body .= "→ Deals and offers reserved for the community\r\n";
    $welcome_body .= "→ Stories, history, and everything that makes the asphalt shake\r\n\r\n";

    if ($promo_code) {
        $welcome_body .= "───────────────────────\r\n";
        $welcome_body .= "🎁 A GIFT FOR YOU\r\n";
        $welcome_body .= "───────────────────────\r\n";
        $welcome_body .= "To celebrate your arrival, here's 10% off your first order:\r\n\r\n";
        $welcome_body .= "      [ {$promo_code} ]\r\n\r\n";
        $welcome_body .= "Valid on bornonasphalt.com for 30 days. One use. No minimum.\r\n\r\n";
    }

    $welcome_body .= "───────────────────────\r\n\r\n";
    $welcome_body .= "Got a question, a suggestion, or just want to talk engines?\r\n";
    $welcome_body .= "Hit us at support@bornonasphalt.com\r\n\r\n";
    $welcome_body .= "See you on the road.\r\n";
    $welcome_body .= "— Jake from Born on Asphalt\r\n";
    $welcome_body .= "bornonasphalt.com\r\n\r\n";
    $welcome_body .= "─\r\n";
    $welcome_body .= "You're receiving this email because you created an account on bornonasphalt.com.\r\n";

    $unsubscribe_token = $nl['unsubscribe_token'] ?? null;
    if ($unsubscribe_token) {
        $welcome_body .= "To unsubscribe: https://bornonasphalt.com/unsubscribe.php?token={$unsubscribe_token}";
    } else {
        $welcome_body .= "To unsubscribe from future emails, reply with \"unsubscribe\".";
    }

    $welcome_headers  = 'From: Born on Asphalt <noreply@bornonasphalt.com>' . "\r\n";
    $welcome_headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    mail($email, $welcome_subject, $welcome_body, $welcome_headers);

    echo json_encode(['user' => ['id' => $userId, 'name' => $name, 'email' => $email]]);
} catch (Exception $e) {
    error_log('Signup error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not create account. Please try again.']);
}
