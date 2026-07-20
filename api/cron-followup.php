<?php
// cron-followup.php — Follow-up email avec code promo 10%
// Exécuté 1x/jour par le cron Hostinger.
// Cible : commandes créées il y a 10+ jours, follow_up_sent = 0.
//
// Appel CLI : php /home/<user>/public_html/api/cron-followup.php
// Appel HTTP sécurisé : /api/cron-followup.php?key=CRON_SECRET

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/stripe.php';
require_once __DIR__ . '/lib/mailer.php';

function boa_check_order_errors_log(): void
{
    $logPath = __DIR__ . '/logs/order-errors.log';

    // File absent or empty — nothing to report
    if (!file_exists($logPath) || filesize($logPath) === 0) {
        return;
    }

    $twentyFourHoursAgo = time() - 86400;
    $recentErrors = [];

    $fp = fopen($logPath, 'r');
    if (!$fp) return;

    while (($line = fgets($fp)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $data = json_decode($line, true);
        if (!$data || !isset($data['time'])) continue;
        $ts = strtotime($data['time']);
        if ($ts && $ts >= $twentyFourHoursAgo) {
            $recentErrors[] = $line;
        }
    }
    fclose($fp);

    if (empty($recentErrors)) return;

    // Build alert email
    $count   = count($recentErrors);
    $subject = "[BOA ALERT] {$count} order error(s) in the last 24h — " . date('Y-m-d');
    $body    = "Born on Asphalt — Order Error Alert\n";
    $body   .= "Generated: " . date('c') . "\n";
    $body   .= "Errors in the last 24h: {$count}\n\n";
    $body   .= str_repeat('-', 60) . "\n\n";
    foreach ($recentErrors as $i => $entry) {
        $body .= "Error " . ($i + 1) . ":\n" . $entry . "\n\n";
    }
    $body .= str_repeat('-', 60) . "\n";
    $body .= "Check /boa-panel/ Logs tab for full context.\n";
    $body .= "bornonasphalt.com\n";

    // Use the same admin email as the PayPal orphan alert
    // (retrieved from webhook-paypal.php in Step 0)
    $adminEmail = 'support@bornonasphalt.com';
    $headers    = 'From: Born on Asphalt <orders@bornonasphalt.com>' . "\r\n"
                . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";

    @mail($adminEmail, $subject, $body, $headers);
    error_log("boa_check_order_errors_log: alert sent — {$count} recent error(s)");
}

// Sécurité : accès HTTP uniquement avec la clé secrète
if (php_sapi_name() !== 'cli') {
    $key = $_GET['key'] ?? '';
    if (!defined('CRON_SECRET') || $key !== CRON_SECRET) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden.']);
        exit;
    }
}

$delay_days = 10; // jours après la commande avant d'envoyer le follow-up
$sent = 0;
$errors = 0;

try {
    $db = boa_db();

    // Commandes éligibles : créées il y a 10+ jours, pas encore contactées
    $stmt = $db->prepare(
        "SELECT id, email, cart_json, total_cents, shipping_cents, subtotal_cents
         FROM orders
         WHERE follow_up_sent = 0
           AND status != 'failed'
           AND email != ''
           AND created_at <= NOW() - INTERVAL ? DAY"
    );
    $stmt->bindValue(1, $delay_days, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll();

    foreach ($orders as $order) {
        try {
            $cart = json_decode($order['cart_json'], true) ?? [];
            if (empty($cart)) continue;

            // Récupère le nom du client depuis la table users si compte existant
            $nameStmt = $db->prepare('SELECT name FROM users WHERE email = ? LIMIT 1');
            $nameStmt->execute([strtolower($order['email'])]);
            $userRow = $nameStmt->fetch();
            $firstName = $userRow
                ? explode(' ', trim($userRow['name']))[0]
                : 'there';

            // Crée un coupon Stripe unique 10% pour cette commande
            $couponCode = 'REVIEW10-' . strtoupper(substr(md5($order['id'] . $order['email']), 0, 8));
            boa_stripe_create_promo_code($couponCode);

            // Construit le lien produit (premier article du panier)
            $firstProductId = $cart[0]['id'] ?? '';
            $reviewLink = 'https://bornonasphalt.com/product.html?id=' . urlencode($firstProductId);

            // Envoie l'email
            boa_send_followup_email(
                $order['email'],
                $firstName,
                $couponCode,
                $cart,
                $reviewLink
            );

            // Marque comme envoyé
            $upd = $db->prepare(
                'UPDATE orders SET follow_up_sent = 1, follow_up_sent_at = NOW() WHERE id = ?'
            );
            $upd->execute([$order['id']]);

            $sent++;
            error_log("Follow-up sent to {$order['email']} (order #{$order['id']}) — code {$couponCode}");

        } catch (Exception $e) {
            $errors++;
            error_log("Follow-up failed for order #{$order['id']}: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    error_log('cron-followup fatal: ' . $e->getMessage());
    exit(1);
}

echo json_encode([
    'processed' => count($orders ?? []),
    'sent'      => $sent,
    'errors'    => $errors,
    'timestamp' => date('c'),
]);

// Check order-errors.log and alert admin if recent errors
boa_check_order_errors_log();

