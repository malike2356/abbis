<?php
/**
 * CRUD for regulatory form templates
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

if (!$auth->userHasPermission('resources.access') && $auth->getUserRole() !== ROLE_ADMIN) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$pdo = getDBConnection();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

try {
    switch ($action) {
        case 'create_template':
            createTemplate($pdo);
            break;
        case 'update_template':
            updateTemplate($pdo);
            break;
        case 'delete_template':
            deleteTemplate($pdo);
            break;
        case 'duplicate_template':
            duplicateTemplate($pdo);
            break;
        default:
            jsonResponse(['success' => false, 'message' => 'Unsupported action'], 422);
    }
} catch (Throwable $e) {
    error_log('Regulatory forms API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}

function createTemplate(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        INSERT INTO regulatory_form_templates
        (form_name, jurisdiction, description, reference_type, html_template, instructions, is_active, created_by)
        VALUES (:form_name, :jurisdiction, :description, :reference_type, :html_template, :instructions, :is_active, :created_by)
    ");
    $stmt->execute([
        ':form_name' => trim((string)($_POST['form_name'] ?? '')),
        ':jurisdiction' => trim((string)($_POST['jurisdiction'] ?? '')),
        ':description' => trim((string)($_POST['description'] ?? '')),
        ':reference_type' => $_POST['reference_type'] ?? 'field_report',
        ':html_template' => $_POST['html_template'] ?? '',
        ':instructions' => trim((string)($_POST['instructions'] ?? '')),
        ':is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
        ':created_by' => $_SESSION['user_id'] ?? null,
    ]);

    jsonResponse(['success' => true, 'template_id' => $pdo->lastInsertId()]);
}

function updateTemplate(PDO $pdo): void
{
    $templateId = (int)($_POST['template_id'] ?? 0);
    if ($templateId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Template ID required'], 422);
    }

    $stmt = $pdo->prepare("
        UPDATE regulatory_form_templates
        SET form_name = :form_name,
            jurisdiction = :jurisdiction,
            description = :description,
            reference_type = :reference_type,
            html_template = :html_template,
            instructions = :instructions,
            is_active = :is_active
        WHERE id = :id
    ");
    $stmt->execute([
        ':form_name' => trim((string)($_POST['form_name'] ?? '')),
        ':jurisdiction' => trim((string)($_POST['jurisdiction'] ?? '')),
        ':description' => trim((string)($_POST['description'] ?? '')),
        ':reference_type' => $_POST['reference_type'] ?? 'field_report',
        ':html_template' => $_POST['html_template'] ?? '',
        ':instructions' => trim((string)($_POST['instructions'] ?? '')),
        ':is_active' => isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1,
        ':id' => $templateId,
    ]);

    jsonResponse(['success' => true]);
}

function deleteTemplate(PDO $pdo): void
{
    $templateId = (int)($_POST['template_id'] ?? 0);
    if ($templateId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Template ID required'], 422);
    }

    $stmt = $pdo->prepare("DELETE FROM regulatory_form_templates WHERE id = ?");
    $stmt->execute([$templateId]);

    jsonResponse(['success' => true]);
}

function duplicateTemplate(PDO $pdo): void
{
    $templateId = (int)($_POST['template_id'] ?? 0);
    if ($templateId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Template ID required'], 422);
    }

    $stmt = $pdo->prepare("SELECT * FROM regulatory_form_templates WHERE id = ?");
    $stmt->execute([$templateId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        jsonResponse(['success' => false, 'message' => 'Template not found'], 404);
    }

    $copyStmt = $pdo->prepare("
        INSERT INTO regulatory_form_templates
        (form_name, jurisdiction, description, reference_type, html_template, instructions, is_active, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $copyStmt->execute([
        $template['form_name'] . ' (Copy)',
        $template['jurisdiction'],
        $template['description'],
        $template['reference_type'],
        $template['html_template'],
        $template['instructions'],
        $template['is_active'],
        $_SESSION['user_id'] ?? null,
    ]);

    jsonResponse(['success' => true, 'template_id' => $pdo->lastInsertId()]);
}

