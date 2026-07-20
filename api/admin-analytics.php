<?php
// GET /api/admin-analytics.php
require_once __DIR__ . '/config.php';
boa_require_admin();

boa_send_cors_headers();
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Helper to base64url encode (standard JWT encoding)
function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Helper to get Google OAuth 2.0 access token via RS256 JWT assertion
function boa_ga4_access_token(): string
{
    $keyFile = dirname(__DIR__, 2) . '/secrets/ga4-service-account.json';
    if (!file_exists($keyFile)) {
        http_response_code(503);
        echo json_encode(['error' => 'GA4 credentials not configured']);
        exit;
    }

    $keyData = json_decode(file_get_contents($keyFile), true);
    if (!$keyData || empty($keyData['private_key']) || empty($keyData['client_email'])) {
        http_response_code(503);
        echo json_encode(['error' => 'GA4 credentials invalid']);
        exit;
    }

    $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
    $now = time();
    $claims = json_encode([
        'iss' => $keyData['client_email'],
        'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]);

    $payload = base64url_encode($header) . '.' . base64url_encode($claims);
    $signature = '';

    if (!openssl_sign($payload, $signature, $keyData['private_key'], 'SHA256')) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to sign JWT assertion']);
        exit;
    }

    $jwt = $payload . '.' . base64url_encode($signature);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            'error' => 'OAuth token request network error',
            'detail' => curl_error($ch)
        ]);
        exit;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($httpCode !== 200) {
        http_response_code(502);
        echo json_encode([
            'error' => 'OAuth token request rejected',
            'detail' => $decoded ?: $response
        ]);
        exit;
    }

    return $decoded['access_token'] ?? '';
}

// Helper to run GA4 report
function boa_ga4_report(string $token, array $body): array
{
    $propertyId = '546148502';
    $ch = curl_init("https://analyticsdata.googleapis.com/v1beta/properties/{$propertyId}:runReport");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$token}",
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    if ($response === false) {
        http_response_code(502);
        echo json_encode([
            'error' => 'GA4 API network error',
            'detail' => curl_error($ch)
        ]);
        exit;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);
    if ($httpCode !== 200) {
        http_response_code(502);
        echo json_encode([
            'error' => 'GA4 API error',
            'detail' => $decoded ?: $response
        ]);
        exit;
    }

    return $decoded ?: [];
}

$action = $_GET['action'] ?? '';

if ($action === 'overview') {
    $body1 = [
        'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
        'metrics' => [
            ['name' => 'sessions'],
            ['name' => 'activeUsers'],
            ['name' => 'screenPageViews'],
            ['name' => 'averageSessionDuration']
        ]
    ];

    $body2 = [
        'dateRanges' => [['startDate' => '30daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'date']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [[
            'dimension' => ['dimensionName' => 'date'],
            'desc' => false
        ]]
    ];

    $token = boa_ga4_access_token();
    $rep1 = boa_ga4_report($token, $body1);
    $rep2 = boa_ga4_report($token, $body2);

    $rows1 = $rep1['rows'] ?? [];
    $sessions = (int)($rows1[0]['metricValues'][0]['value'] ?? 0);
    $users = (int)($rows1[0]['metricValues'][1]['value'] ?? 0);
    $pageviews = (int)($rows1[0]['metricValues'][2]['value'] ?? 0);
    $avg_duration = (float)($rows1[0]['metricValues'][3]['value'] ?? 0);

    $daily = [];
    $rows2 = $rep2['rows'] ?? [];
    foreach ($rows2 as $row) {
        $daily[] = [
            'date' => $row['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($row['metricValues'][0]['value'] ?? 0)
        ];
    }

    echo json_encode([
        'sessions' => $sessions,
        'users' => $users,
        'pageviews' => $pageviews,
        'avg_duration_seconds' => $avg_duration,
        'daily' => $daily
    ]);
    exit;

} elseif ($action === 'pages') {
    $body = [
        'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'pagePath']],
        'metrics' => [['name' => 'screenPageViews'], ['name' => 'sessions']],
        'orderBys' => [[
            'metric' => ['metricName' => 'screenPageViews'],
            'desc' => true
        ]],
        'limit' => 10
    ];

    $token = boa_ga4_access_token();
    $rep = boa_ga4_report($token, $body);

    $pages = [];
    $rows = $rep['rows'] ?? [];
    foreach ($rows as $row) {
        $pages[] = [
            'path' => $row['dimensionValues'][0]['value'] ?? '',
            'pageviews' => (int)($row['metricValues'][0]['value'] ?? 0),
            'sessions' => (int)($row['metricValues'][1]['value'] ?? 0)
        ];
    }

    echo json_encode(['pages' => $pages]);
    exit;

} elseif ($action === 'sources') {
    $body = [
        'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'sessionDefaultChannelGroup']],
        'metrics' => [['name' => 'sessions']],
        'orderBys' => [[
            'metric' => ['metricName' => 'sessions'],
            'desc' => true
        ]],
        'limit' => 8
    ];

    $token = boa_ga4_access_token();
    $rep = boa_ga4_report($token, $body);

    $sources = [];
    $rows = $rep['rows'] ?? [];
    foreach ($rows as $row) {
        $sources[] = [
            'channel' => $row['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($row['metricValues'][0]['value'] ?? 0)
        ];
    }

    echo json_encode(['sources' => $sources]);
    exit;

} elseif ($action === 'geo') {
    $body = [
        'dateRanges' => [['startDate' => '7daysAgo', 'endDate' => 'today']],
        'dimensions' => [['name' => 'region']],
        'metrics' => [['name' => 'sessions']],
        'dimensionFilter' => [
            'filter' => [
                'fieldName' => 'region',
                'stringFilter' => [
                    'matchType' => 'FULL_REGEXP',
                    'value' => '^(?!\\(not set\\)|$|not set).+$'
                ]
            ]
        ],
        'orderBys' => [[
            'metric' => ['metricName' => 'sessions'],
            'desc' => true
        ]],
        'limit' => 10
    ];

    $token = boa_ga4_access_token();
    $rep = boa_ga4_report($token, $body);

    $regions = [];
    $rows = $rep['rows'] ?? [];
    foreach ($rows as $row) {
        $regions[] = [
            'region' => $row['dimensionValues'][0]['value'] ?? '',
            'sessions' => (int)($row['metricValues'][0]['value'] ?? 0)
        ];
    }

    echo json_encode(['regions' => $regions]);
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
