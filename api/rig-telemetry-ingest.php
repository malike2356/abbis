<?php

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/helpers.php';
require_once __DIR__ . '/../includes/Maintenance/RigTelemetryService.php';

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$payload = $rawInput ? json_decode($rawInput, true) : null;

$token = $_SERVER['HTTP_X_STREAM_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? null;

if ($token && stripos($token, 'Bearer ') === 0) {
    $token = trim(substr($token, 7));
}

$service = new RigTelemetryService();

if ($token) {
    $stream = $service->findStreamByToken($token);
    if (!$stream) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid stream token']);
        exit;
    }
    $rigId = (int)$stream['rig_id'];
    $streamId = (int)$stream['id'];
    $userId = null;
} else {
    require_once '../includes/auth.php';
    $auth->requireAuth();
    if (!CSRF::validateToken($_POST['csrf_token'] ?? $payload['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    $rigId = isset($_POST['rig_id']) ? (int)$_POST['rig_id'] : (int)($payload['rig_id'] ?? 0);
    $streamId = isset($_POST['stream_id']) ? (int)$_POST['stream_id'] : (int)($payload['stream_id'] ?? 0);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if (!$rigId) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Rig ID required']);
        exit;
    }
}

if (!$payload && !empty($_POST)) {
    $payload = $_POST;
}

$events = $payload['events'] ?? [];

if (!is_array($events) || empty($events)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Telemetry events payload required']);
    exit;
}

$responses = [];

foreach ($events as $event) {
    if (!is_array($event)) {
        continue;
    }

    $metricKey = $event['metric'] ?? $event['metric_key'] ?? null;
    if (!$metricKey) {
        continue;
    }

    $value = $event['value'] ?? $event['metric_value'] ?? null;
    $options = [
        'stream_id' => $streamId ?: ($event['stream_id'] ?? null),
        'metric_label' => $event['label'] ?? $event['metric_label'] ?? null,
        'metric_unit' => $event['unit'] ?? $event['metric_unit'] ?? null,
        'source' => $event['source'] ?? ($token ? 'telemetry' : 'manual'),
        'recorded_at' => $event['recorded_at'] ?? null,
        'payload' => isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : null,
    ];

    try {
        $result = $service->recordEvent($rigId, $metricKey, $value, $options);
        $responses[] = [
            'metric' => $metricKey,
            'status' => $result['status'],
            'threshold' => $result['threshold'],
        ];
    } catch (Throwable $e) {
        error_log('Telemetry ingest error: ' . $e->getMessage());
    }
}

if (!empty($payload['heartbeat']) && isset($streamId) && $streamId) {
    $service->logHeartbeat((int)$streamId, $payload['heartbeat_payload'] ?? null);
}

echo json_encode([
    'success' => true,
    'processed' => count($responses),
    'results' => $responses,
]);


