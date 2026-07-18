<?php
// POST /api/webhook-printful.php?key=PRINTFUL_WEBHOOK_SECRET
// Configure this URL in Printful Dashboard > Stores > Webhooks
// Events : shipment.shipped
//
// Printful does not sign webhook payloads with HMAC — authentication
// is done via a secret token in the query string.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/mailer.php';

// Vérification du token secret
$receivedKey = $_GET['key'] ?? '';
if (!defined('PRINTFUL_WEBHOOK_SECRET') || !hash_equals(PRINTFUL_WEBHOOK_SECRET, $receivedKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

header('Content-Type: application/json');

$payload = file_get_contents('php://input');
$event   = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload.']);
    exit;
}

// On ne traite que shipment_sent — ignorer silencieusement tout le reste
if ($event['type'] !== 'shipment_sent') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

try {
    $shipment       = $event['data']['shipment'] ?? [];
    $order          = $event['data']['order']    ?? [];

    $trackingNumber = $shipment['tracking_number'] ?? '';
    $trackingUrl    = $shipment['tracking_url']    ?? '';
    $carrier        = $shipment['carrier']         ?? 'Carrier';

    // external_id = printful order id stocké dans orders.provider_order_id
    $providerOrderId = (string)($order['id'] ?? '');

    // Log brut pour diagnostic
    $logLine = json_encode([
        'time'             => date('c'),
        'type'             => $event['type'],
        'provider_order_id'=> $providerOrderId,
        'tracking_number'  => $trackingNumber,
        'carrier'          => $carrier,
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/orders.log', $logLine, FILE_APPEND | LOCK_EX);

    if (!$providerOrderId) {
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'No order id in payload.']);
        exit;
    }

    $db = boa_db();

    // Mettre à jour le statut de la commande
    $db->prepare('UPDATE orders SET status = ? WHERE provider = ? AND provider_order_id = ?')
       ->execute(['shipped', 'printful', $providerOrderId]);

    // Récupérer les infos nécessaires pour l'email
    $stmt = $db->prepare('SELECT email, cart_json FROM orders WHERE provider = ? AND provider_order_id = ? LIMIT 1');
    $stmt->execute(['printful', $providerOrderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['email'])) {
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Order not found in DB.']);
        exit;
    }

    $email   = $row['email'];
    $cart    = json_decode($row['cart_json'], true) ?: [];
    $orderId = substr($providerOrderId, 0, 12);

    // Extraire le prénom depuis le cart ou laisser vide
    $toName = explode('@', $email)[0];

    if ($trackingNumber && $trackingUrl) {
        boa_send_shipping_notification(
            $email,
            $toName,
            $orderId,
            $carrier,
            $trackingNumber,
            $trackingUrl,
            $cart
        );
    }

    http_response_code(200);
    echo json_encode(['received' => true]);

} catch (Throwable $e) {
    error_log('Printful webhook error: ' . $e->getMessage());
    $logLine = json_encode([
        'time'  => date('c'),
        'error' => $e->getMessage(),
        'event' => $event['type'] ?? 'unknown',
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/order-errors.log', $logLine, FILE_APPEND | LOCK_EX);

    // Toujours 200 — Printful ne doit pas retenter indéfiniment
    http_response_code(200);
    echo json_encode(['received' => true, 'note' => 'Error logged server-side.']);
}
