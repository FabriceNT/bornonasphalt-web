<?php
// CAPI — Meta Conversions API
// https://developers.facebook.com/docs/marketing-api/conversions-api

function boa_meta_capi_purchase(
    string $eventId,
    int    $valueCents,
    string $currency = 'USD',
    string $email    = ''
): void {
    if (!defined('META_PIXEL_ID') || !defined('META_ACCESS_TOKEN')) return;
    if (empty(META_PIXEL_ID) || empty(META_ACCESS_TOKEN)) return;

    $url = 'https://graph.facebook.com/v21.0/' . META_PIXEL_ID . '/events';

    $userData = ['client_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''];
    if (!empty($email)) {
        // Hash requis par Meta
        $userData['em'] = hash('sha256', strtolower(trim($email)));
    }

    $payload = [
        'data' => [[
            'event_name'       => 'Purchase',
            'event_time'       => time(),
            'event_id'         => $eventId,
            'event_source_url' => (defined('FRONTEND_URL') ? FRONTEND_URL : '') . '/checkout-success.html',
            'action_source'    => 'website',
            'user_data'        => $userData,
            'custom_data'      => [
                'value'    => round($valueCents / 100, 2),
                'currency' => $currency,
            ],
        ]],
        'access_token' => META_ACCESS_TOKEN,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err) {
        error_log('Meta CAPI error: ' . $err);
    } else {
        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            error_log('Meta CAPI API error: ' . json_encode($decoded['error']));
        }
    }
}
