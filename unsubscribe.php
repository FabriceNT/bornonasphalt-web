<?php
require_once dirname(__DIR__) . '/secrets.php';

$token = trim($_GET['token'] ?? '');

$success = false;
$already = false;

if (strlen($token) === 64 && ctype_xdigit($token)) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $check = $pdo->prepare('SELECT id, unsubscribed_at FROM newsletter_subscribers WHERE unsubscribe_token = ?');
        $check->execute([$token]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            if ($row['unsubscribed_at'] !== null) {
                $already = true;
            } else {
                $upd = $pdo->prepare('UPDATE newsletter_subscribers SET unsubscribed_at = NOW() WHERE id = ?');
                $upd->execute([$row['id']]);
                $success = true;
            }
        }
    } catch (Exception $e) {
        // silencieux
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Unsubscribe — Born on Asphalt</title>
<style>
  body { background:#111; color:#e8e0d0; font-family:'Courier New',monospace; 
         display:flex; align-items:center; justify-content:center; 
         min-height:100vh; margin:0; }
  .box { max-width:480px; text-align:center; padding:40px 20px; }
  .logo { font-size:1.1rem; letter-spacing:0.2em; color:#8B0000; margin-bottom:32px; }
  h1 { font-size:1.4rem; letter-spacing:0.1em; margin-bottom:16px; }
  p { color:#aaa; line-height:1.7; }
  a { color:#8B0000; text-decoration:none; }
  a:hover { text-decoration:underline; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">BORN ON ASPHALT</div>
  <?php if ($success): ?>
    <h1>YOU'RE UNSUBSCRIBED.</h1>
    <p>You won't receive any more emails from us.<br>
    We're sorry to see you go.</p>
    <p style="margin-top:32px;"><a href="https://bornonasphalt.com">← Back to the shop</a></p>
  <?php elseif ($already): ?>
    <h1>ALREADY UNSUBSCRIBED.</h1>
    <p>You've already been removed from our list.</p>
    <p style="margin-top:32px;"><a href="https://bornonasphalt.com">← Back to the shop</a></p>
  <?php else: ?>
    <h1>INVALID LINK.</h1>
    <p>This unsubscribe link is invalid or has expired.<br>
    Contact us at <a href="mailto:support@bornonasphalt.com">support@bornonasphalt.com</a></p>
    <p style="margin-top:32px;"><a href="https://bornonasphalt.com">← Back to the shop</a></p>
  <?php endif; ?>
</div>
</body>
</html>
