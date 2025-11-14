<?php
/**
 * Geology estimator API
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/Geology/GeologyEstimator.php';

$auth->requireAuth();

$hasAccess = $auth->userHasPermission('resources.access')
    || $auth->userHasPermission('field_reports.manage')
    || $auth->userHasPermission('crm.access')
    || $auth->getUserRole() === ROLE_ADMIN;

if (!$hasAccess) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrfToken = $payload['csrf_token'] ?? null;
if (!CSRF::validateToken($csrfToken)) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

$lat = isset($payload['latitude']) ? (float)$payload['latitude'] : null;
$lng = isset($payload['longitude']) ? (float)$payload['longitude'] : null;

if ($lat === null || $lng === null) {
    jsonResponse(['success' => false, 'message' => 'Latitude and longitude are required'], 422);
}

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    jsonResponse(['success' => false, 'message' => 'Latitude/longitude values are out of bounds'], 422);
}

try {
    $estimator = new GeologyEstimator();
    $result = $estimator->predict($lat, $lng, [
        'radius_km' => isset($payload['radius_km']) ? (float)$payload['radius_km'] : null,
        'limit' => isset($payload['limit']) ? (int)$payload['limit'] : null,
    ]);

    if (!$result['success']) {
        jsonResponse($result, 404);
    }

    $neighbors = $result['neighbors'] ?? [];
    $firstNeighbor = $neighbors[0] ?? [];

    $logData = [
        'client_id' => $payload['client_id'] ?? null,
        'user_id' => $_SESSION['user_id'] ?? null,
        'latitude' => $lat,
        'longitude' => $lng,
        'region' => $payload['region'] ?? ($firstNeighbor['region'] ?? null),
        'district' => $payload['district'] ?? ($firstNeighbor['district'] ?? null),
        'depth_min_m' => $result['prediction']['depth_min_m'] ?? null,
        'depth_avg_m' => $result['prediction']['depth_avg_m'] ?? null,
        'depth_max_m' => $result['prediction']['depth_max_m'] ?? null,
        'confidence_score' => $result['prediction']['confidence_score'] ?? null,
        'neighbor_count' => count($neighbors),
        'estimation_method' => 'inverse_distance_weighting',
        'notes' => $payload['notes'] ?? null,
    ];

    $estimator->logPrediction($logData);

    jsonResponse([
        'success' => true,
        'data' => $result,
    ]);
} catch (Throwable $e) {
    error_log('Geology estimator failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to generate prediction'], 500);
}

