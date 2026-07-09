<?php
// lib/mailer.php — Order confirmation email (PHP mail(), no external lib)

function boa_send_order_confirmation(
    string $toEmail,
    string $toName,
    string $orderId,
    array  $cart,
    array  $shipping,
    int    $subtotalCents,
    int    $shippingCents,
    int    $totalCents
): void {

    $fromName    = 'Born on Asphalt';
    $fromEmail   = 'orders@bornonasphalt.com';
    $subject     = "Order confirmed — Born on Asphalt #{$orderId}";

    // ---- Lignes du panier ----
    $itemRows = '';
    foreach ($cart as $item) {
        $qty    = (int)($item['qty']   ?? 1);
        $color  = $item['color']       ?? '';
        $size   = $item['size']        ?? '';
        $price  = boa_price_cents_for_size($size) * $qty;

        // Récupère le titre et l'image depuis le catalogue PHP
        $product = function_exists('boa_find_product') ? boa_find_product($item['id'] ?? '') : null;
        $title   = $product
            ? htmlspecialchars($product['title'] . " ({$color}, {$size})")
            : htmlspecialchars(($item['title'] ?? $item['id']) . " ({$color}, {$size})");

        $imgUrl  = '';
        if ($product) {
            $images = $product['images'] ?? [];
            $imgUrl = $images[$color] ?? ($product['image'] ?? '');
        }
        $imgTag = $imgUrl
            ? "<img src=\"{$imgUrl}\" width=\"56\" height=\"70\"
               style=\"object-fit:cover;border:1px solid #B8AF95;display:block;\" alt=\"\" />"
            : '';

        $itemRows .= "
    <tr>
      <td style='padding:10px 0; border-bottom:1px dotted #B8AF95; vertical-align:middle;'>
        <table cellpadding='0' cellspacing='0' border='0'><tr>
          <td style='padding-right:14px; vertical-align:middle;'>{$imgTag}</td>
          <td style='font-family:monospace; font-size:13px; color:#1B1812; vertical-align:middle;'>
            {$title} × {$qty}
          </td>
        </tr></table>
      </td>
      <td style='padding:10px 0; border-bottom:1px dotted #B8AF95; font-family:monospace;
                 font-size:13px; color:#1B1812; text-align:right; vertical-align:middle;'>
        \$" . number_format($price / 100, 2) . "
      </td>
    </tr>";
    }

    $shippingLabel = $shippingCents === 0 ? 'Free' : '$' . number_format($shippingCents / 100, 2);
    $addrLine = implode(', ', array_filter([
        $shipping['address1'] ?? '',
        $shipping['city']     ?? '',
        $shipping['state_code'] ?? '',
        $shipping['zip']      ?? '',
    ]));

    // ---- HTML ----
    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#121210;font-family:'IBM Plex Mono',monospace;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#121210;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#EDE6D3;border:1px solid #B8AF95;max-width:600px;width:100%;">

      <!-- Header -->
      <tr>
        <td style="background:#9C3B2E;padding:14px 28px;">
          <span style="font-family:Oswald,Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#EDE6D3;">
            Born on Asphalt — Order Confirmation
          </span>
        </td>
      </tr>

      <!-- Title -->
      <tr>
        <td style="padding:28px 28px 0;">
          <p style="font-family:monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#55503f;margin:0 0 6px;">
            DOC NO. <strong style="color:#1B1812;">BOA-{$orderId}</strong>
          </p>
          <h1 style="font-family:Oswald,Arial,sans-serif;font-size:28px;text-transform:uppercase;color:#1B1812;margin:0 0 6px;">
            Your order is confirmed
          </h1>
          <p style="font-family:monospace;font-size:13px;color:#55503f;margin:0 0 24px;font-style:italic;">
            "Your build sheet has been filed. It's going to production."
          </p>
          <hr style="border:none;border-top:2px solid #1B1812;margin:0 0 20px;">
        </td>
      </tr>

      <!-- Items -->
      <tr>
        <td style="padding:0 28px;">
          <p style="font-family:Oswald,Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#9C3B2E;margin:0 0 10px;">
            // Items
          </p>
          <table width="100%" cellpadding="0" cellspacing="0">
            {$itemRows}
            <tr>
              <td style="padding:8px 0;font-family:monospace;font-size:12px;color:#55503f;">Subtotal</td>
              <td style="padding:8px 0;font-family:monospace;font-size:12px;color:#55503f;text-align:right;">SUBTOTAL_PLACEHOLDER</td>
            </tr>
            <tr>
              <td style="padding:8px 0;font-family:monospace;font-size:12px;color:#55503f;">Shipping (US)</td>
              <td style="padding:8px 0;font-family:monospace;font-size:12px;color:#55503f;text-align:right;">{$shippingLabel}</td>
            </tr>
            <tr>
              <td style="padding:10px 0;font-family:Oswald,Arial,sans-serif;font-size:15px;text-transform:uppercase;color:#1B1812;border-top:1px solid #B8AF95;">Total</td>
              <td style="padding:10px 0;font-family:Oswald,Arial,sans-serif;font-size:15px;color:#1B1812;text-align:right;border-top:1px solid #B8AF95;">TOTAL_PLACEHOLDER</td>
            </tr>
          </table>
        </td>
      </tr>

      <!-- Shipping address -->
      <tr>
        <td style="padding:24px 28px 0;">
          <p style="font-family:Oswald,Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#9C3B2E;margin:0 0 8px;">
            // Ships to
          </p>
          <p style="font-family:monospace;font-size:13px;color:#1B1812;margin:0;">
            {$shipping['name']}<br>{$addrLine}
          </p>
        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="padding:28px;border-top:1px solid #B8AF95;margin-top:24px;">
          <p style="font-family:monospace;font-size:11px;color:#55503f;margin:0;line-height:1.7;">
            Printed on demand · Comfort Colors 1717 · Ships from the US<br>
            You'll receive a tracking number by email once your order ships.<br><br>
            Questions? Reply to this email or contact us at
            <a href="mailto:support@bornonasphalt.com" style="color:#9C3B2E;">support@bornonasphalt.com</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    // Remplace les placeholders montants (évite les interpolations complexes dans heredoc)
    $html = str_replace(
        ['SUBTOTAL_PLACEHOLDER', 'TOTAL_PLACEHOLDER'],
        [
            '$' . number_format($subtotalCents / 100, 2),
            '$' . number_format($totalCents    / 100, 2),
        ],
        $html
    );

    // Texte alternatif
    $text  = "Born on Asphalt — Order #{$orderId}\n\n";
    $text .= "Hi {$toName},\n\nYour order is confirmed.\n\n";
    foreach ($cart as $item) {
        $text .= "- {$item['title']} x{$item['qty']}\n";
    }
    $text .= "\nSubtotal : $" . number_format($subtotalCents / 100, 2) . "\n";
    $text .= "Shipping  : {$shippingLabel}\n";
    $text .= "Total     : $" . number_format($totalCents    / 100, 2) . "\n";
    $text .= "\nShips to: {$shipping['name']}, {$addrLine}\n\n";
    $text .= "Questions? support@bornonasphalt.com\n";

    $boundary = '----=_BOA_' . md5(uniqid('', true));
    $headers  = implode("\r\n", [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: support@bornonasphalt.com",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: PHP/" . phpversion(),
    ]);

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    @mail($toEmail, $subject, $body, $headers);
}

function boa_send_followup_email(
    string $toEmail,
    string $firstName,
    string $promoCode,
    array  $cart,
    string $reviewLink
): void {
    $fromName  = 'Born on Asphalt';
    $fromEmail = 'orders@bornonasphalt.com';
    $subject   = 'How\'s your Born on Asphalt shirt? + 10% off';

    // Ligne articles
    $itemLines = '';
    foreach ($cart as $item) {
        $product = function_exists('boa_find_product') ? boa_find_product($item['id'] ?? '') : null;
        $title   = $product ? $product['title'] : ($item['id'] ?? '');
        $color   = $item['color'] ?? '';
        $size    = $item['size']  ?? '';
        $qty     = (int)($item['qty'] ?? 1);
        $itemLines .= "<li style='margin-bottom:4px;font-family:monospace;font-size:13px;color:#1B1812;'>"
            . htmlspecialchars("{$title} — {$color}, {$size} × {$qty}") . "</li>";
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#121210;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#121210;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#EDE6D3;border:1px solid #B8AF95;max-width:600px;width:100%;">

      <tr>
        <td style="background:#9C3B2E;padding:14px 28px;">
          <span style="font-family:Oswald,Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#EDE6D3;">
            Born on Asphalt — Field Report
          </span>
        </td>
      </tr>

      <tr>
        <td style="padding:28px 28px 0;">
          <p style="font-family:monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase;color:#55503f;margin:0 0 6px;">
            // Telemetry request
          </p>
          <h1 style="font-family:Oswald,Arial,sans-serif;font-size:26px;text-transform:uppercase;color:#1B1812;margin:0 0 12px;">
            How's the shirt, {$firstName}?
          </h1>
          <p style="font-family:monospace;font-size:13px;color:#55503f;line-height:1.7;margin:0 0 20px;">
            Your order just hit the 10-day mark. If the shirt has made it to your garage,
            we'd love to hear what you think — quality, fit, print, all of it.
          </p>
          <ul style="margin:0 0 20px;padding-left:18px;">{$itemLines}</ul>
          <a href="{$reviewLink}"
             style="display:inline-block;background:#1B1812;color:#EDE6D3;font-family:Oswald,Arial,sans-serif;
                    font-size:13px;text-transform:uppercase;letter-spacing:.08em;padding:12px 24px;
                    text-decoration:none;">
            Leave a Review →
          </a>
          <hr style="border:none;border-top:1px solid #B8AF95;margin:28px 0;">
        </td>
      </tr>

      <tr>
        <td style="padding:0 28px 28px;">
          <p style="font-family:Oswald,Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:#9C3B2E;margin:0 0 10px;">
            // Your reward
          </p>
          <p style="font-family:monospace;font-size:13px;color:#55503f;line-height:1.7;margin:0 0 16px;">
            As a thank-you for taking the time, here's <strong style="color:#1B1812;">10% off your next order</strong>.
            Valid for 60 days, one use only.
          </p>
          <div style="background:#1B1812;color:#EDE6D3;font-family:Oswald,Arial,sans-serif;
                      font-size:22px;letter-spacing:.12em;text-align:center;padding:16px 28px;">
            {$promoCode}
          </div>
          <p style="font-family:monospace;font-size:11px;color:#55503f;margin:10px 0 0;">
            Apply at checkout · Expires in 60 days · Single use
          </p>
        </td>
      </tr>

      <tr>
        <td style="padding:20px 28px;border-top:1px solid #B8AF95;">
          <p style="font-family:monospace;font-size:11px;color:#55503f;margin:0;line-height:1.7;">
            Questions? Reply to this email or contact
            <a href="mailto:support@bornonasphalt.com" style="color:#9C3B2E;">support@bornonasphalt.com</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    $text  = "How's the shirt, {$firstName}?\n\n";
    $text .= "Leave a review here: {$reviewLink}\n\n";
    $text .= "As a thank-you: 10% off your next order with code {$promoCode}\n";
    $text .= "Valid 60 days, single use. Apply at checkout on bornonasphalt.com\n\n";
    $text .= "Questions? support@bornonasphalt.com\n";

    $boundary = '----=_BOA_' . md5(uniqid('', true));
    $headers  = implode("\r\n", [
        "From: {$fromName} <{$fromEmail}>",
        "Reply-To: support@bornonasphalt.com",
        "MIME-Version: 1.0",
        "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
        "X-Mailer: PHP/" . phpversion(),
    ]);

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $text . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $html . "\r\n";
    $body .= "--{$boundary}--";

    @mail($toEmail, $subject, $body, $headers);
}
