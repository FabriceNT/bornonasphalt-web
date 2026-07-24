<?php
// api/lib/cart-abandon.php — Cart abandonment tracking and emails

function boa_save_cart_abandon(string $email, string $cartJson): void
{
    $email = trim(strtolower($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $cart = json_decode($cartJson, true);
    if (!is_array($cart) || empty($cart)) {
        return;
    }

    try {
        $db = boa_db();
        $stmt = $db->prepare(
            'SELECT id FROM cart_abandonments
             WHERE email = ? AND converted_at IS NULL AND email_sent_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            $upd = $db->prepare(
                'UPDATE cart_abandonments
                 SET cart_json = ?, created_at = NOW()
                 WHERE id = ?'
            );
            $upd->execute([$cartJson, $existing['id']]);
        } else {
            $ins = $db->prepare(
                'INSERT INTO cart_abandonments (email, cart_json)
                 VALUES (?, ?)'
            );
            $ins->execute([$email, $cartJson]);
        }
    } catch (Exception $e) {
        error_log('boa_save_cart_abandon error: ' . $e->getMessage());
    }
}

// TODO: créer le code promo COMEBACK10 dans newsletter_subscribers ou système dédié
function boa_send_cart_abandon_email(string $email, string $cartJson): void
{
    $cart = json_decode($cartJson, true);
    if (!is_array($cart) || empty($cart)) {
        return;
    }

    $itemsList = '';
    foreach ($cart as $item) {
        $qty       = (int)($item['qty'] ?? 1);
        $color     = $item['color'] ?? '';
        $size      = $item['size'] ?? '';
        $productId = $item['id'] ?? '';
        $product   = function_exists('boa_find_product') ? boa_find_product($productId) : null;
        $name      = $product ? $product['title'] : ($item['title'] ?? $productId);
        $itemsList .= "{$qty}x {$name} ({$color}, {$size})\r\n";
    }

    $subject   = 'You left something in your cart — Born on Asphalt';
    $fromName  = 'Born on Asphalt';
    $fromEmail = 'orders@bornonasphalt.com';

    $body = "Hey — you left something behind.\r\n\r\n"
          . $itemsList
          . "\r\nYour cart is still waiting:\r\n"
          . "https://bornonasphalt.com\r\n\r\n"
          . "bornonasphalt.com\r\n"
          . "— The BOA Shop\r\n";

    $headers = implode("\r\n", [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: support@bornonasphalt.com",
        "Content-Type: text/plain; charset=UTF-8",
        "X-Mailer: PHP/" . phpversion(),
    ]);

    @mail($email, $subject, $body, $headers);
}

function boa_check_cart_abandonments(): void
{
    try {
        $db = boa_db();
        $stmt = $db->prepare(
            'SELECT id, email, cart_json
             FROM cart_abandonments
             WHERE email_sent_at IS NULL
               AND converted_at IS NULL
               AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 50'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            try {
                boa_send_cart_abandon_email($row['email'], $row['cart_json']);
                $upd = $db->prepare('UPDATE cart_abandonments SET email_sent_at = NOW() WHERE id = ?');
                $upd->execute([$row['id']]);
            } catch (Exception $e) {
                error_log('boa_check_cart_abandonments row error: ' . $e->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log('boa_check_cart_abandonments fatal: ' . $e->getMessage());
    }
}
