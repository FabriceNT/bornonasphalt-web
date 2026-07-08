<?php
// POST /api/addresses-save.php
// body: { id?, label, full_name, address1, city, state_code, zip, country_code?, is_default? }
// If `id` is present and belongs to the signed-in user, updates that address.
// Otherwise creates a new one.
// returns: { "address": { id, ... } }

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
boa_send_cors_headers();
boa_start_session();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not signed in.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int) $input['id'] : null;
$label = trim($input['label'] ?? 'Home');
$fullName = trim($input['full_name'] ?? '');
$address1 = trim($input['address1'] ?? '');
$city = trim($input['city'] ?? '');
$stateCode = trim($input['state_code'] ?? '');
$zip = trim($input['zip'] ?? '');
$countryCode = trim($input['country_code'] ?? 'US');
$isDefault = !empty($input['is_default']) ? 1 : 0;

if ($fullName === '' || $address1 === '' || $city === '' || $stateCode === '' || $zip === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Name, address, city, state and ZIP are all required.']);
    exit;
}

try {
    $db = boa_db();
    $userId = $_SESSION['user_id'];

    if ($isDefault) {
        $db->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?')->execute([$userId]);
    }

    if ($id) {
        // Update — the user_id check in WHERE prevents editing someone
        // else's address even if they guess a valid id.
        $update = $db->prepare('UPDATE addresses SET label = ?, full_name = ?, address1 = ?, city = ?, state_code = ?, zip = ?, country_code = ?, is_default = ? WHERE id = ? AND user_id = ?');
        $update->execute([$label ?: 'Home', $fullName, $address1, $city, $stateCode, $zip, $countryCode ?: 'US', $isDefault, $id, $userId]);
    } else {
        $insert = $db->prepare('INSERT INTO addresses (user_id, label, full_name, address1, city, state_code, zip, country_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$userId, $label ?: 'Home', $fullName, $address1, $city, $stateCode, $zip, $countryCode ?: 'US', $isDefault]);
        $id = (int) $db->lastInsertId();
    }

    echo json_encode(['address' => [
        'id' => $id, 'label' => $label ?: 'Home', 'full_name' => $fullName, 'address1' => $address1,
        'city' => $city, 'state_code' => $stateCode, 'zip' => $zip, 'country_code' => $countryCode ?: 'US',
        'is_default' => $isDefault,
    ]]);
} catch (Exception $e) {
    error_log('addresses-save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Could not save address.']);
}
