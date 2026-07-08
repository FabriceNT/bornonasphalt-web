<?php
// Thin wrapper around the Printify v1 REST API.
// Docs: https://developers.printify.com/

const PRINTIFY_API_BASE = 'https://api.printify.com/v1';

function boa_printify_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init();
    $headers = [
        'Authorization: Bearer ' . PRINTIFY_API_TOKEN,
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL            => PRINTIFY_API_BASE . $path,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Printify request failed: {$error}");
    }
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($statusCode >= 400) {
        throw new Exception("Printify error ({$statusCode}): " . json_encode($decoded));
    }
    return $decoded ?? [];
}

// cartItems: [{ id, title, qty, printify_product_id, printify_variant_id }]
// shipping: { name, address1, city, state_code, country_code, zip, email }
//
// Creates the order WITHOUT sending it to production (send_to_production: false)
// so we can read the calculated cost first. Printify calculates costs
// asynchronously — the POST response often returns total_price=0. We therefore
// poll the order with a GET after creation until the cost is non-zero,
// with a short backoff (max 3 attempts, 1s apart).
function boa_printify_create_draft_order(array $cartItems, array $shipping): array
{
    foreach ($cartItems as $item) {
        if (empty($item['printify_product_id']) || empty($item['printify_variant_id'])) {
            throw new Exception(
                "Product \"{$item['title']}\" ({$item['id']}) has no printify_product_id / " .
                "printify_variant_id set in products.php. Fill it in from your Printify " .
                "dashboard before this order can be created."
            );
        }
    }

    [$firstName, $lastName] = array_pad(explode(' ', $shipping['name'], 2), 2, '');

    $body = [
        'external_id' => 'boa-' . uniqid(),
        'line_items'  => array_map(function ($item) {
            return [
                'product_id' => $item['printify_product_id'],
                'variant_id' => (int) $item['printify_variant_id'],
                'quantity'   => $item['qty'],
            ];
        }, $cartItems),
        'address_to' => [
            'first_name' => $firstName ?: 'Customer',
            'last_name'  => $lastName  ?: '',
            'email'      => $shipping['email'],
            'address1'   => $shipping['address1'],
            'city'       => $shipping['city'],
            'region'     => $shipping['state_code'],
            'country'    => $shipping['country_code'] ?: 'US',
            'zip'        => $shipping['zip'],
        ],
        'send_to_production' => false,
    ];

    $order   = boa_printify_request('POST', '/shops/' . PRINTIFY_SHOP_ID . '/orders.json', $body);
    $orderId = $order['id'] ?? null;

    if (!$orderId) {
        throw new Exception('Printify order created but no id returned: ' . json_encode($order));
    }

    // Poll until cost is non-zero (Printify calculates asynchronously).
    // 3 attempts with 1-second sleep between each — well within Stripe webhook
    // timeout budget and enough time for Printify's price engine to respond.
    $totalCents = (int)($order['total_price'] ?? 0) + (int)($order['total_shipping'] ?? 0);
    $attempts   = 0;

    while ($totalCents === 0 && $attempts < 3) {
        sleep(1);
        $fetched    = boa_printify_request('GET', '/shops/' . PRINTIFY_SHOP_ID . '/orders/' . $orderId . '.json');
        $totalCents = (int)($fetched['total_price'] ?? 0) + (int)($fetched['total_shipping'] ?? 0);
        $order      = $fetched;
        $attempts++;
    }

    if ($totalCents === 0) {
        // Still 0 after polling — log a warning but don't abort. fulfillment.php
        // handles a 0-cost gracefully by preferring the other provider.
        error_log("Printify order {$orderId}: cost still 0 after {$attempts} polling attempts.");
    }

    return [
        'id'          => $orderId,
        'total_cents' => $totalCents,
        'raw'         => $order,
    ];
}

function boa_printify_send_to_production(string $orderId): array
{
    return boa_printify_request('POST', '/shops/' . PRINTIFY_SHOP_ID . "/orders/{$orderId}/send_to_production.json");
}

function boa_printify_cancel_order(string $orderId): void
{
    try {
        boa_printify_request('POST', '/shops/' . PRINTIFY_SHOP_ID . "/orders/{$orderId}/cancel.json");
    } catch (Exception $e) {
        // Non-fatal — log it, don't block the checkout flow over cleanup.
        error_log("Could not cancel unused Printify draft order {$orderId}: " . $e->getMessage());
    }
}
