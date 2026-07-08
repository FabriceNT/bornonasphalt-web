<?php
// Thin wrapper around the Printful v1 REST API. Docs: https://developers.printful.com/docs/

const PRINTFUL_API_BASE = 'https://api.printful.com';

function boa_printful_headers(): array
{
    $headers = [
        'Authorization: Bearer ' . PRINTFUL_API_TOKEN,
        'Content-Type: application/json',
    ];
    if (!empty(PRINTFUL_STORE_ID)) {
        $headers[] = 'X-PF-Store-Id: ' . PRINTFUL_STORE_ID;
    }
    return $headers;
}

function boa_printful_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => PRINTFUL_API_BASE . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => boa_printful_headers(),
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Printful request failed: {$error}");
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode >= 400) {
        throw new Exception("Printful order creation failed: " . json_encode($decoded));
    }
    return $decoded['result'] ?? [];
}

// cartItems: [{ id, title, qty, printful_sync_variant_id }]
// shipping: { name, address1, city, state_code, country_code, zip, email }
//
// Creates the order as a DRAFT (no confirm param) so we can read the
// calculated cost first and only commit to whichever provider (Printful or
// Printify) is cheaper. Cancel with boa_printful_cancel_order() if it turns
// out not to be the cheaper one.
function boa_printful_create_draft_order(array $cartItems, array $shipping): array
{
    foreach ($cartItems as $item) {
        if (empty($item['printful_sync_variant_id'])) {
            throw new Exception(
                "Product \"{$item['title']}\" ({$item['id']}) has no printful_sync_variant_id set in products.php. " .
                "Fill it in from your Printful dashboard before this order can be created."
            );
        }
    }

    $body = [
        'recipient' => [
            'name' => $shipping['name'],
            'address1' => $shipping['address1'],
            'city' => $shipping['city'],
            'state_code' => $shipping['state_code'],
            'country_code' => $shipping['country_code'] ?: 'US',
            'zip' => $shipping['zip'],
            'email' => $shipping['email'],
        ],
        'items' => array_map(function ($item) {
            return [
                'sync_variant_id' => $item['printful_sync_variant_id'],
                'quantity' => $item['qty'],
            ];
        }, $cartItems),
    ];

    $order = boa_printful_request('POST', '/orders', $body);

    // "costs.total" is a string like "34.99" in Printful's API — convert to
    // cents for a fair comparison against Printify's cent-based totals.
    // If costs aren't ready yet (order put "on hold" for calculation),
    // this will be 0 — see README.md "Verify before going live".
    $totalCents = isset($order['costs']['total']) ? (int) round(((float) $order['costs']['total']) * 100) : 0;

    return [
        'id' => $order['id'] ?? null,
        'total_cents' => $totalCents,
        'raw' => $order,
    ];
}

function boa_printful_confirm_order(int $orderId): array
{
    return boa_printful_request('POST', "/orders/{$orderId}/confirm");
}

function boa_printful_cancel_order(int $orderId): void
{
    try {
        boa_printful_request('DELETE', "/orders/{$orderId}");
    } catch (Exception $e) {
        // Non-fatal — log it, don't block the checkout flow over cleanup.
        error_log("Could not cancel unused Printful draft order {$orderId}: " . $e->getMessage());
    }
}
