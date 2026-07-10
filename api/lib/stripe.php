<?php
// Minimal Stripe client using plain cURL — no Composer/SDK needed, which
// keeps this portable on shared hosting that may not support Composer well.
// Docs: https://stripe.com/docs/api
//
// This uses the embedded Payment Element (Stripe.js on the front end)
// rather than redirect-based Checkout Sessions — the card form renders
// directly in our own page, and returning customers' saved cards actually
// show up as selectable options (which hosted Checkout Sessions turned
// out not to reliably do, even with a Customer + default payment method
// set correctly — see project notes).

const STRIPE_API_BASE = 'https://api.stripe.com/v1';

// Stripe's API expects form-urlencoded bodies, including for nested
// structures like shipping[address][city]=X.
function boa_stripe_flatten(array $data, string $prefix = ''): array
{
    $result = [];
    foreach ($data as $key => $value) {
        $paramKey = $prefix === '' ? $key : "{$prefix}[{$key}]";
        if (is_array($value)) {
            $result += boa_stripe_flatten($value, $paramKey);
        } elseif (is_bool($value)) {
            // PHP's http_build_query() turns true/false into '1'/'' —
            // Stripe's API needs the literal words "true"/"false".
            $result[$paramKey] = $value ? 'true' : 'false';
        } else {
            $result[$paramKey] = $value;
        }
    }
    return $result;
}

function boa_stripe_request(string $method, string $path, array $params = []): array
{
    $ch = curl_init();
    $url = STRIPE_API_BASE . $path;

    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query(boa_stripe_flatten($params));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_SECRET_KEY . ':',
        CURLOPT_TIMEOUT => 20,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(boa_stripe_flatten($params)));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Stripe request failed: {$error}");
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode >= 400) {
        $message = $decoded['error']['message'] ?? 'Unknown Stripe error';
        throw new Exception("Stripe error ({$statusCode}): {$message}");
    }
    return $decoded;
}

// Creates a Stripe Customer object — this is what a saved card gets
// attached to. Called lazily on a signed-in user's first checkout, id
// stored in the users table from then on.
function boa_stripe_create_customer(string $email, string $name): array
{
    return boa_stripe_request('POST', '/customers', [
        'email' => $email,
        'name' => $name,
    ]);
}

// A Customer Session is what tells the embedded Payment Element "this
// customer's saved payment methods are OK to show and let them reuse,
// save new ones, and remove old ones." Without this, Payment Element
// never surfaces saved cards, no matter how the Customer object itself is
// configured. Create one fresh per checkout attempt (short-lived, ~30 min).
function boa_stripe_create_customer_session(string $customerId): array
{
    return boa_stripe_request('POST', '/customer_sessions', [
        'customer' => $customerId,
        'components' => [
            'payment_element' => [
                'enabled' => true,
                'features' => [
                    'payment_method_redisplay' => 'enabled',
                    'payment_method_save' => 'enabled',
                    'payment_method_save_usage' => 'on_session',
                    'payment_method_remove' => 'enabled',
                ],
            ],
        ],
    ]);
}

// amountCents: total to charge (product + shipping), already computed
// server-side. customerId is optional — omit for guest checkout.
function boa_stripe_create_payment_intent(int $amountCents, string $metadataCartJson, ?string $customerId, ?string $email, array $extraMetadata = []): array
{
    $metadata = ['cart' => $metadataCartJson];
    if (!empty($extraMetadata)) {
        $metadata = array_merge($metadata, $extraMetadata);
    }
    $params = [
        'amount' => $amountCents,
        'currency' => 'usd',
        'automatic_payment_methods' => ['enabled' => true],
        'metadata' => $metadata,
    ];
    if ($customerId) {
        $params['customer'] = $customerId;
        $params['setup_future_usage'] = 'on_session';
    }
    if (!empty($email)) {
        $params['receipt_email'] = $email;
    }
    return boa_stripe_request('POST', '/payment_intents', $params);
}

function boa_stripe_retrieve_payment_intent(string $id): array
{
    return boa_stripe_request('GET', "/payment_intents/{$id}");
}

// Attaching a card via setup_future_usage doesn't automatically make it
// the customer's default — calling this after a successful payment means
// Payment Element's "redisplay" feature has a sensible one to preselect.
function boa_stripe_set_default_payment_method(string $customerId, string $paymentMethodId): void
{
    boa_stripe_request('POST', "/customers/{$customerId}", [
        'invoice_settings' => [
            'default_payment_method' => $paymentMethodId,
        ],
    ]);
}

// Verifies the Stripe-Signature header manually (no SDK). Throws on failure.
// Docs: https://stripe.com/docs/webhooks/signatures
function boa_stripe_verify_webhook(string $payload, string $sigHeader, string $secret, int $toleranceSeconds = 300): array
{
    $parts = [];
    foreach (explode(',', $sigHeader) as $pair) {
        [$k, $v] = array_pad(explode('=', $pair, 2), 2, null);
        $parts[$k][] = $v;
    }

    if (empty($parts['t']) || empty($parts['v1'])) {
        throw new Exception('Malformed Stripe-Signature header.');
    }

    $timestamp = (int) $parts['t'][0];
    $signedPayload = "{$timestamp}.{$payload}";
    $expectedSig = hash_hmac('sha256', $signedPayload, $secret);

    $matched = false;
    foreach ($parts['v1'] as $candidate) {
        if (hash_equals($expectedSig, $candidate)) {
            $matched = true;
            break;
        }
    }
    if (!$matched) {
        throw new Exception('Signature mismatch — request did not come from Stripe.');
    }

    if (abs(time() - $timestamp) > $toleranceSeconds) {
        throw new Exception('Timestamp outside tolerance — possible replay attack.');
    }

    $event = json_decode($payload, true);
    if ($event === null) {
        throw new Exception('Invalid JSON payload.');
    }
    return $event;
}

function boa_stripe_create_promo_code(string $code): array {
    // Crée d'abord le coupon (10%, usage unique, expire dans 60 jours)
    // puis le promotion code associé avec le code lisible choisi.

    // Vérifie si le promo code existe déjà (idempotence)
    $existing = boa_stripe_request('GET', '/v1/promotion_codes?code=' . urlencode($code) . '&limit=1');
    if (!empty($existing['data'])) {
        return $existing['data'][0];
    }

    // Crée le coupon
    $coupon = boa_stripe_request('POST', '/v1/coupons', [
        'percent_off'       => '10',
        'duration'          => 'once',
        'redeem_by'         => (string)(time() + 60 * 86400), // 60 jours
        'max_redemptions'   => '1',
    ]);

    // Crée le promotion code lisible
    $promo = boa_stripe_request('POST', '/v1/promotion_codes', [
        'coupon'            => $coupon['id'],
        'code'              => $code,
        'max_redemptions'   => '1',
    ]);

    return $promo;
}
