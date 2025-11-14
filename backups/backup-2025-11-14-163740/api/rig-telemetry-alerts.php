<?php

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/../includes/Maintenance/RigTelemetryService.php';

$auth->requireAuth();
if (!$auth->userHasPermission('resources.access')) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$service = new RigTelemetryService();
$action = $_POST['action'] ?? '';
$alertId = isset($_POST['alert_id']) ? (int)$_POST['alert_id'] : 0;
$maintenanceId = isset($_POST['maintenance_record_id']) ? (int)$_POST['maintenance_record_id'] : null;

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

if (!$alertId) {
    jsonResponse(['success' => false, 'message' => 'Alert ID required'], 422);
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$result = false;

switch ($action) {
    case 'acknowledge':
        $result = $service->acknowledgeAlert($alertId, $userId);
        break;
    case 'resolve':
        $result = $service->resolveAlert($alertId, $userId, $maintenanceId);
        break;
    default:
        jsonResponse(['success' => false, 'message' => 'Unsupported action'], 422);
}

if (!$result) {
    jsonResponse(['success' => false, 'message' => 'Unable to update alert'], 500);
}

jsonResponse(['success' => true, 'alert' => $service->getAlertById($alertId)]);


