<?php

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once __DIR__ . '/../includes/Maintenance/RigTelemetryService.php';

$auth->requireAuth();
if (!$auth->userHasPermission('resources.access')) {
    jsonResponse(['success' => false, 'message' => 'Access denied'], 403);
}

$pdo = getDBConnection();
$service = new RigTelemetryService($pdo);

$rigId = isset($_GET['rig_id']) ? (int)$_GET['rig_id'] : null;

$summary = $service->getDashboardSummary($rigId);

$alertsStmt = $pdo->prepare("
    SELECT a.*, r.rig_name, r.rig_code
    FROM rig_maintenance_alerts a
    LEFT JOIN rigs r ON r.id = a.rig_id
    WHERE a.status IN ('open','acknowledged')
      " . ($rigId ? "AND a.rig_id = :rig_id" : '') . "
    ORDER BY FIELD(a.severity,'critical','warning','info'), a.triggered_at DESC
    LIMIT 25
");
$alertsStmt->execute($rigId ? [':rig_id' => $rigId] : []);
$alerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);

$eventsStmt = $pdo->prepare("
    SELECT e.*, r.rig_name, r.rig_code
    FROM rig_telemetry_events e
    LEFT JOIN rigs r ON r.id = e.rig_id
    WHERE 1=1
      " . ($rigId ? "AND e.rig_id = :rig_id" : '') . "
    ORDER BY e.recorded_at DESC
    LIMIT 50
");
$eventsStmt->execute($rigId ? [':rig_id' => $rigId] : []);
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

$streamsStmt = $pdo->prepare("
    SELECT s.*, r.rig_name, r.rig_code
    FROM rig_maintenance_streams s
    LEFT JOIN rigs r ON r.id = s.rig_id
    WHERE 1=1
      " . ($rigId ? "AND s.rig_id = :rig_id" : '') . "
    ORDER BY s.status DESC, s.updated_at DESC
");
$streamsStmt->execute($rigId ? [':rig_id' => $rigId] : []);
$streams = $streamsStmt->fetchAll(PDO::FETCH_ASSOC);

jsonResponse([
    'success' => true,
    'summary' => $summary,
    'alerts' => $alerts,
    'events' => $events,
    'streams' => $streams,
]);


