<?php
/**
 * Generate regulatory form output
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once __DIR__ . '/../includes/Forms/RegulatoryFormRenderer.php';

$auth->requireAuth();

if (!$auth->userHasPermission('resources.access') && $auth->getUserRole() !== ROLE_ADMIN) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);

if (!is_array($payload)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request body'], 400);
}

if (!CSRF::validateToken($payload['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

$templateId = (int)($payload['template_id'] ?? 0);
$referenceId = isset($payload['reference_id']) ? (int)$payload['reference_id'] : null;
$context = is_array($payload['context'] ?? null) ? $payload['context'] : [];

try {
    $pdo = getDBConnection();
    $renderer = new RegulatoryFormRenderer($pdo);

    $stmt = $pdo->prepare("SELECT * FROM regulatory_form_templates WHERE id = ? LIMIT 1");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        jsonResponse(['success' => false, 'message' => 'Template not found'], 404);
    }

    if (!$template['is_active']) {
        jsonResponse(['success' => false, 'message' => 'Template is inactive'], 422);
    }

    $result = $renderer->render($template, $template['reference_type'], $referenceId, $context);

    $fileName = 'REG-' . $templateId . '-' . date('YmdHis') . '.html';
    $storageDir = ROOT_PATH . '/storage/regulatory';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0775, true);
    }
    $filePath = $storageDir . '/' . $fileName;
    file_put_contents($filePath, $result['html']);

    $relativePath = 'storage/regulatory/' . $fileName;

    $logStmt = $pdo->prepare("
        INSERT INTO regulatory_form_exports (template_id, reference_type, reference_id, generated_by, output_path)
        VALUES (?, ?, ?, ?, ?)
    ");
    $logStmt->execute([
        $templateId,
        $template['reference_type'],
        $referenceId,
        $_SESSION['user_id'] ?? null,
        $relativePath
    ]);

    jsonResponse([
        'success' => true,
        'html' => $result['html'],
        'download_url' => app_url($relativePath),
        'datasets' => $result['datasets']
    ]);
} catch (Throwable $e) {
    error_log('Regulatory form generation failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Failed to generate form'], 500);
}

