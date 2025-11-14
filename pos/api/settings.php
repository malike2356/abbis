<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

header('Content-Type: application/json');

try {
    $auth->requireAuth();
    $auth->requirePermission('pos.access');
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 401);
}

$allowedKeys = [
    'pos_printer_mode',
    'pos_printer_width',
    'pos_printer_endpoint',
    'pos_barcode_prefix',
    'pos_receipt_footer',
];

$pdo = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $placeholders = implode(',', array_fill(0, count($allowedKeys), '?'));
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key IN ($placeholders)");
    $stmt->execute($allowedKeys);
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    jsonResponse([
        'success' => true,
        'data' => array_merge(array_fill_keys($allowedKeys, null), $rows),
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $auth->requirePermission('system.admin');
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => 'Administrator rights required to update settings.'], 403);
    }

    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        jsonResponse(['success' => false, 'message' => 'Invalid payload.'], 400);
    }

    $stmt = $pdo->prepare("
        INSERT INTO system_config (config_key, config_value)
        VALUES (:key, :value)
        ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
    ");

    foreach ($allowedKeys as $key) {
        if (array_key_exists($key, $payload)) {
            $value = $payload[$key];
            $stmt->execute([
                ':key' => $key,
                ':value' => is_array($value) ? json_encode($value) : $value,
            ]);
        }
    }

    jsonResponse(['success' => true, 'message' => 'POS hardware settings saved.']);
    exit;
}

jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);


