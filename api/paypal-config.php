<?php
// GET /api/paypal-config.php
// returns: { "client_id": "..." }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();

echo json_encode(['client_id' => PAYPAL_CLIENT_ID]);
