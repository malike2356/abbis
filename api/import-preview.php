<?php
/**
 * CSV import preview endpoint.
 *
 * Accepts a CSV upload, stores it temporarily, and returns detected headers +
 * sample rows for mapping via the onboarding wizard.
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/Import/ImportManager.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $csrfToken = $_POST['csrf_token'] ?? null;
    if (!CSRF::validateToken($csrfToken)) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }

    $dataset = trim((string)($_POST['dataset'] ?? ''));
    if ($dataset === '') {
        jsonResponse(['success' => false, 'message' => 'Dataset is required'], 422);
    }

    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(['success' => false, 'message' => 'CSV file upload failed'], 422);
    }

    $file = $_FILES['csv_file'];
    $extension = strtolower((string)pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['csv', 'txt'], true)) {
        jsonResponse(['success' => false, 'message' => 'Please upload a CSV file'], 422);
    }

    $delimiter = $_POST['delimiter'] ?? ',';
    $importManager = new ImportManager();
    if (!$importManager->getDefinition($dataset)) {
        jsonResponse(['success' => false, 'message' => 'Unsupported dataset'], 422);
    }

    $tempDir = ROOT_PATH . '/storage/temp';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    $token = bin2hex(random_bytes(16));
    $tempFile = $tempDir . '/import_' . $token . '.csv';
    if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
        jsonResponse(['success' => false, 'message' => 'Unable to store uploaded file'], 500);
    }

    $preview = $importManager->buildPreview($dataset, $tempFile, $delimiter);

    $metadata = [
        'token' => $token,
        'dataset' => $dataset,
        'delimiter' => $delimiter,
        'uploaded_by' => $_SESSION['user_id'] ?? null,
        'uploaded_at' => time(),
        'original_name' => $file['name'],
    ];

    file_put_contents($tempDir . '/import_' . $token . '.json', json_encode($metadata));

    jsonResponse([
        'success' => true,
        'token' => $token,
        'preview' => $preview,
        'metadata' => $metadata,
    ]);
} catch (Throwable $e) {
    error_log('Import preview failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}


