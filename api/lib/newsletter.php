<?php
require_once dirname(__DIR__, 3) . '/secrets.php';

function boa_newsletter_subscribe(string $email): array {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email'];
    }

    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Déjà inscrit → retourner code existant sans ré-envoyer
        $check = $pdo->prepare('
            SELECT id, promo_code, unsubscribe_token 
            FROM newsletter_subscribers 
            WHERE email = ? AND unsubscribed_at IS NULL
        ');
        $check->execute([$email]);
        if ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            return [
                'success' => true, 
                'code' => $row['promo_code'],
                'unsubscribe_token' => $row['unsubscribe_token']
            ];
        }

        // Générer code unique WELCOME-XXXXXX
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $promo_code = 'WELCOME-' . $suffix;
        $unsubscribe_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

        $stmt = $pdo->prepare('
            INSERT INTO newsletter_subscribers 
            (email, promo_code, unsubscribe_token, expires_at) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$email, $promo_code, $unsubscribe_token, $expires_at]);
        $subscriber_id = $pdo->lastInsertId();

        // Envoyer mail
        $subject = 'Your 10% off — Born on Asphalt';
        $body  = "Your code is ready.\r\n\r\n";
        $body .= "Use {$promo_code} at checkout for 10% off your first order.\r\n\r\n";
        $body .= "No expiry. No minimum. One use per customer.\r\n\r\n";
        $body .= "bornonasphalt.com\r\n\r\n— The BOA Shop\r\n\r\n";
        $body .= "─\r\n";
        $body .= "You're receiving this email because you subscribed on bornonasphalt.com.\r\n";
        $body .= "To unsubscribe: https://bornonasphalt.com/unsubscribe.php?token={$unsubscribe_token}";
        $headers  = 'From: Born on Asphalt <noreply@bornonasphalt.com>' . "\r\n";
        $headers .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

        $sent = mail($email, $subject, $body, $headers);
        if ($sent) {
            $pdo->prepare('UPDATE newsletter_subscribers SET email_sent = 1 WHERE id = ?')
                ->execute([$subscriber_id]);
        }

        return ['success' => true, 'code' => $promo_code, 'unsubscribe_token' => $unsubscribe_token];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Server error'];
    }
}
