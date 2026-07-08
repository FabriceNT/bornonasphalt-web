<?php
// GET /api/stripe-config.php
// returns: { "publishable_key": "pk_test_..." }
//
// The publishable key is, by design, safe to expose in the browser — it
// can only create tokens/payment methods, never charge anything or read
// account data. Keeping it in secrets.php anyway just means every Stripe
// key lives in one place.

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();

echo json_encode(['publishable_key' => STRIPE_PUBLISHABLE_KEY]);
