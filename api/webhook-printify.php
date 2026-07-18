<?php
// POST /api/webhook-printify.php?key=PRINTIFY_WEBHOOK_SECRET
// Enregistrer via : POST /v1/shops/{shop_id}/webhooks.json
// Topic : order:shipment:sent
//
// Printify ne signe pas les payloads HMAC — auth par token query string.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/lib/mailer.php';

// Printify envoie un GET pour valider l'URL — répondre 200 immédiatement
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(200);
    echo json_encode(['ok' => true]);
    exit;
}

// Vérification du token secret sur les POST uniquement
$receivedKey = $_GET['key'] ?? '';
if (!defined('PRINTIFY_WEBHOOK_SECRET') || !hash_equals(PRINTIFY_WEBHOOK_SECRET, $receivedKey)) {
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

// On ne traite que order:shipment:sent — ignorer silencieusement tout le reste
if ($event['type'] !== 'order:shipment:sent') {
    http_response_code(200);
    echo json_encode(['received' => true]);
    exit;
}

try {
    $resource = $event['resource'] ?? [];
    $data     = $resource['data'] ?? [];
    $shipment = $data['shipment'] ?? [];

    $trackingNumber  = $shipment['number'] ?? '';
    $trackingUrl     = $shipment['url']    ?? '';
    $carrier         = $shipment['carrier'] ?? 'Carrier';

    // L'ID Printify de la commande
    $providerOrderId = (string)($resource['id'] ?? '');

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
       ->execute(['shipped', 'printify', $providerOrderId]);

    // Récupérer les infos pour l'email
    $stmt = $db->prepare('SELECT email, cart_json FROM orders WHERE provider = ? AND provider_order_id = ? LIMIT 1');
    $stmt->execute(['printify', $providerOrderId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['email'])) {
        http_response_code(200);
        echo json_encode(['received' => true, 'note' => 'Order not found in DB.']);
        exit;
    }

    $email   = $row['email'];
    $cart    = json_decode($row['cart_json'], true) ?: [];
    $orderId = substr($providerOrderId, 0, 12);
    $toName  = explode('@', $email)[0];

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
    error_log('Printify webhook error: ' . $e->getMessage());
    $logLine = json_encode([
        'time'  => date('c'),
        'error' => $e->getMessage(),
        'event' => $event['type'] ?? 'unknown',
    ]) . "\n";
    file_put_contents(__DIR__ . '/logs/order-errors.log', $logLine, FILE_APPEND | LOCK_EX);

    // Toujours 200 — Printify ne doit pas retenter indéfiniment
    http_response_code(200);
    echo json_encode(['received' => true, 'note' => 'Error logged server-side.']);
}
