<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once __DIR__ . '/../includes/Environmental/EnvironmentalSamplingService.php';

$auth->requireAuth();
if (!$auth->userHasPermission('field_reports.manage') && !$auth->userHasPermission('resources.access')) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

$action = $_POST['action'] ?? '';
$service = new EnvironmentalSamplingService();

try {
    switch ($action) {
        case 'save_project':
            $projectId = $service->saveProject([
                'id' => $_POST['project_id'] ?? null,
                'project_code' => $_POST['project_code'] ?? null,
                'project_name' => $_POST['project_name'] ?? null,
                'client_id' => $_POST['client_id'] ?? null,
                'field_report_id' => $_POST['field_report_id'] ?? null,
                'site_name' => $_POST['site_name'] ?? null,
                'location_address' => $_POST['location_address'] ?? null,
                'latitude' => $_POST['latitude'] ?? null,
                'longitude' => $_POST['longitude'] ?? null,
                'sampling_type' => $_POST['sampling_type'] ?? null,
                'status' => $_POST['status'] ?? null,
                'scheduled_date' => $_POST['scheduled_date'] ?? null,
                'collected_date' => $_POST['collected_date'] ?? null,
                'submitted_to_lab_at' => $_POST['submitted_to_lab_at'] ?? null,
                'completed_at' => $_POST['completed_at'] ?? null,
                'created_by' => $_SESSION['user_id'] ?? null,
                'notes' => $_POST['notes'] ?? null,
            ]);
            $project = $service->getProject($projectId);
            jsonResponse(['success' => true, 'project' => $project]);
            break;

        case 'delete_project':
            $projectId = (int)($_POST['project_id'] ?? 0);
            if (!$projectId) {
                jsonResponse(['success' => false, 'message' => 'Project ID required'], 422);
            }
            $service->deleteProject($projectId);
            jsonResponse(['success' => true]);
            break;

        case 'save_sample':
            $projectId = (int)($_POST['project_id'] ?? 0);
            if (!$projectId) {
                jsonResponse(['success' => false, 'message' => 'Project required'], 422);
            }
            $sampleId = $service->saveSample([
                'id' => $_POST['sample_id'] ?? null,
                'project_id' => $projectId,
                'sample_code' => $_POST['sample_code'] ?? null,
                'sample_type' => $_POST['sample_type'] ?? null,
                'matrix' => $_POST['matrix'] ?? null,
                'collection_method' => $_POST['collection_method'] ?? null,
                'container_type' => $_POST['container_type'] ?? null,
                'preservative' => $_POST['preservative'] ?? null,
                'collected_by' => $_POST['collected_by'] ?? null,
                'collected_at' => $_POST['collected_at'] ?? null,
                'temperature_c' => $_POST['temperature_c'] ?? null,
                'weather_notes' => $_POST['weather_notes'] ?? null,
                'field_observations' => $_POST['field_observations'] ?? null,
                'status' => $_POST['status'] ?? null,
            ]);
            $project = $service->getProject($projectId);
            jsonResponse(['success' => true, 'project' => $project, 'sample_id' => $sampleId]);
            break;

        case 'add_chain_entry':
            $sampleId = (int)($_POST['sample_id'] ?? 0);
            if (!$sampleId) {
                jsonResponse(['success' => false, 'message' => 'Sample ID required'], 422);
            }
            $entryId = $service->addChainEntry([
                'sample_id' => $sampleId,
                'custody_step' => $_POST['custody_step'] ?? null,
                'handler_name' => $_POST['handler_name'] ?? '',
                'handler_role' => $_POST['handler_role'] ?? null,
                'handler_signature' => $_POST['handler_signature'] ?? null,
                'transfer_action' => $_POST['transfer_action'] ?? null,
                'transfer_at' => $_POST['transfer_at'] ?? null,
                'condition_notes' => $_POST['condition_notes'] ?? null,
                'temperature_c' => $_POST['temperature_c'] ?? null,
                'received_by_lab' => $_POST['received_by_lab'] ?? null,
            ]);
            jsonResponse(['success' => true, 'entry_id' => $entryId, 'chain' => $service->getChainBySample($sampleId)]);
            break;

        case 'add_lab_result':
            $sampleId = (int)($_POST['sample_id'] ?? 0);
            if (!$sampleId) {
                jsonResponse(['success' => false, 'message' => 'Sample ID required'], 422);
            }
            $resultId = $service->addLabResult([
                'sample_id' => $sampleId,
                'parameter_name' => $_POST['parameter_name'] ?? '',
                'parameter_group' => $_POST['parameter_group'] ?? null,
                'parameter_unit' => $_POST['parameter_unit'] ?? null,
                'result_value' => $_POST['result_value'] ?? null,
                'detection_limit' => $_POST['detection_limit'] ?? null,
                'method_reference' => $_POST['method_reference'] ?? null,
                'analyst_name' => $_POST['analyst_name'] ?? null,
                'analyzed_at' => $_POST['analyzed_at'] ?? null,
                'qa_qc_flag' => $_POST['qa_qc_flag'] ?? null,
                'remarks' => $_POST['remarks'] ?? null,
                'attachment_path' => $_POST['attachment_path'] ?? null,
            ]);
            jsonResponse(['success' => true, 'result_id' => $resultId, 'results' => $service->getResultsBySample($sampleId)]);
            break;

        case 'update_status':
            $projectId = (int)($_POST['project_id'] ?? 0);
            $status = $_POST['status'] ?? null;
            if (!$projectId || !$status) {
                jsonResponse(['success' => false, 'message' => 'Project and status required'], 422);
            }
            $service->updateProjectStatus($projectId, $status);
            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Unsupported action'], 422);
    }
} catch (Throwable $e) {
    error_log('Environmental sampling API error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}


