<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosReportingService.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$storeId = !empty($_GET['store_id']) ? (int)$_GET['store_id'] : null;
$days = !empty($_GET['days']) ? (int)$_GET['days'] : 30;

if (empty($type)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Chart type required']);
    exit;
}

try {
    $reporting = new PosReportingService();
    $data = $reporting->getChartData($type, $storeId, $days);
    
    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Charts] Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch chart data'
    ]);
}

