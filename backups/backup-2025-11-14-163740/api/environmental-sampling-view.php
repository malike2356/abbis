<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once __DIR__ . '/../includes/Environmental/EnvironmentalSamplingService.php';

$auth->requireAuth();
if (!$auth->userHasPermission('resources.access')) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$projectId) {
    jsonResponse(['success' => false, 'message' => 'Project ID required'], 422);
}

$service = new EnvironmentalSamplingService();
$project = $service->getProject($projectId);

if (!$project) {
    jsonResponse(['success' => false, 'message' => 'Project not found'], 404);
}

// Augment samples with counts for convenience
foreach ($project['samples'] as &$sample) {
    $sample['chain_count'] = count($sample['chain'] ?? []);
    $sample['result_count'] = count($sample['results'] ?? []);
}

jsonResponse(['success' => true, 'project' => $project]);


