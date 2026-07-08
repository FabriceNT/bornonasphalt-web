<?php
// Minimal PayPal client using plain cURL — no SDK/Composer needed.
// Docs: https://developer.paypal.com/api/rest/

const PAYPAL_API_BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';
const PAYPAL_API_BASE_LIVE = 'https://api-m.paypal.com';

function boa_paypal_api_base(): string
{
    return (defined('PAYPAL_MODE') && PAYPAL_MODE === 'live') ? PAYPAL_API_BASE_LIVE : PAYPAL_API_BASE_SANDBOX;
}

// PayPal uses OAuth2 client-credentials — a short-lived access token
// fetched fresh per request. Not cached across requests (each PHP request
// is short-lived anyway on shared hosting), so this is a small extra
// round-trip per call, acceptable at this scale.
function boa_paypal_get_access_token(): string
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => boa_paypal_api_base() . '/v1/oauth2/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("PayPal auth request failed: {$error}");
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode >= 400) {
        throw new Exception('PayPal auth error: ' . json_encode($decoded));
    }
    return $decoded['access_token'];
}

function boa_paypal_request(string $method, string $path, ?array $body = null): array
{
    $token = boa_paypal_get_access_token();
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => boa_paypal_api_base() . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("PayPal request failed: {$error}");
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode >= 400) {
        throw new Exception("PayPal error ({$statusCode}): " . json_encode($decoded));
    }
    return $decoded ?? [];
}

// $shipping: { name, address1, city, state_code, zip, country_code }
function boa_paypal_create_order(int $amountCents, array $shipping): array
{
    $amount = number_format($amountCents / 100, 2, '.', '');

    $body = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'amount' => ['currency_code' => 'USD', 'value' => $amount],
            'shipping' => [
                'name' => ['full_name' => $shipping['name'] ?: 'Customer'],
                'address' => [
                    'address_line_1' => $shipping['address1'],
                    'admin_area_2' => $shipping['city'],
                    'admin_area_1' => $shipping['state_code'],
                    'postal_code' => $shipping['zip'],
                    'country_code' => $shipping['country_code'] ?: 'US',
                ],
            ],
        ]],
        // We already collect the address ourselves (same form as Stripe) —
        // this tells PayPal to use it as-is instead of asking again.
        'application_context' => [
            'shipping_preference' => 'SET_PROVIDED_ADDRESS',
        ],
    ];

    return boa_paypal_request('POST', '/v2/checkout/orders', $body);
}

function boa_paypal_capture_order(string $orderId): array
{
    return boa_paypal_request('POST', "/v2/checkout/orders/{$orderId}/capture");
}
