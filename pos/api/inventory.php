<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../includes/pos/PosValidator.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

try {
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid JSON payload.');
    }

    $validated = PosValidator::validateInventoryAdjustment($payload);
    if (abs($validated['quantity_delta']) <= 0) {
        throw new InvalidArgumentException('Quantity delta cannot be zero.');
    }

    $repo = new PosRepository();
    $result = $repo->adjustStock($validated);

    echo json_encode(['success' => true, 'data' => $result]);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    if (APP_ENV === 'development') {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Unable to adjust inventory.']);
    }
}


