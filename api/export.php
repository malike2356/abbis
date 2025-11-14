<?php
/**
 * Unified Export API
 * Handles all export operations using ExportManager
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/ExportManager.php';

$auth->requireAuth();

// Get parameters
$module = $_GET['module'] ?? $_GET['type'] ?? 'reports'; // Support both 'module' and 'type' for backward compatibility
$format = $_GET['format'] ?? 'csv';

// Collect filters from GET parameters
$filters = [];
$filterKeys = ['date_from', 'date_to', 'rig_id', 'client_id', 'worker', 'role', 'payment_status', 'report_id', 'material_type', 'group_by', 'job_type'];
foreach ($filterKeys as $key) {
    if (isset($_GET[$key])) {
        $filters[$key] = $_GET[$key];
    }
}

try {
    $exportManager = new ExportManager();
    $exportManager->export($module, $format, $filters);
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
