<?php
/**
 * Rig Requests API Endpoints
 * Handles AJAX requests for rig request operations
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$auth->requireAuth();

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currentUserId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_request':
            handleGetRequest();
            break;
            
        case 'update_status':
            handleUpdateStatus();
            break;
            
        case 'assign_rig':
            handleAssignRig();
            break;
            
        case 'assign_user':
            handleAssignUser();
            break;
            
        case 'add_followup':
            handleAddFollowup();
            break;
            
        case 'link_to_client':
            handleLinkToClient();
            break;
            
        case 'link_to_report':
            handleLinkToReport();
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("Rig Requests API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

function handleGetRequest() {
    global $pdo;
    
    $requestId = intval($_GET['request_id'] ?? 0);
    
    if (empty($requestId)) {
        jsonResponse(['success' => false, 'message' => 'Request ID required'], 400);
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            rr.*,
            r.rig_name, r.rig_code,
            c.client_name, c.id as client_id,
            u.full_name as assigned_name,
            fr.report_id as field_report_id
        FROM rig_requests rr
        LEFT JOIN rigs r ON rr.assigned_rig_id = r.id
        LEFT JOIN clients c ON rr.client_id = c.id
        LEFT JOIN users u ON rr.assigned_to = u.id
        LEFT JOIN field_reports fr ON rr.field_report_id = fr.id
        WHERE rr.id = ?
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        jsonResponse(['success' => false, 'message' => 'Request not found'], 404);
    }
    
    // Get follow-ups
    $followupsStmt = $pdo->prepare("
        SELECT rrf.*, u.full_name as assigned_name
        FROM rig_request_followups rrf
        LEFT JOIN users u ON rrf.assigned_to = u.id
        WHERE rrf.rig_request_id = ?
        ORDER BY rrf.scheduled_date DESC
    ");
    $followupsStmt->execute([$requestId]);
    $followups = $followupsStmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'request' => $request,
        'followups' => $followups
    ]);
}

function handleUpdateStatus() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $assignedRigId = !empty($_POST['assigned_rig_id']) ? intval($_POST['assigned_rig_id']) : null;
    $internalNotes = sanitizeInput($_POST['internal_notes'] ?? '');
    
    if (empty($requestId) || empty($status)) {
        jsonResponse(['success' => false, 'message' => 'Request ID and status required'], 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE rig_requests 
        SET status = ?, assigned_rig_id = ?, internal_notes = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$status, $assignedRigId, $internalNotes, $requestId]);
    
    // Set dispatched_at or completed_at based on status
    if ($status === 'dispatched') {
        $pdo->prepare("UPDATE rig_requests SET dispatched_at = NOW() WHERE id = ?")->execute([$requestId]);
    } elseif ($status === 'completed') {
        $pdo->prepare("UPDATE rig_requests SET completed_at = NOW() WHERE id = ?")->execute([$requestId]);
    }
    
    // Record activity if client is linked
    try {
        $clientStmt = $pdo->prepare("SELECT client_id FROM rig_requests WHERE id = ?");
        $clientStmt->execute([$requestId]);
        $clientId = $clientStmt->fetchColumn();
        
        if ($clientId) {
            $activityStmt = $pdo->prepare("
                INSERT INTO client_activities (client_id, type, title, description, related_id, related_type, created_by)
                VALUES (?, 'update', 'Rig Request Status Updated', ?, ?, 'rig_request', ?)
            ");
            $activityStmt->execute([
                $clientId,
                "Status changed to: " . ucfirst(str_replace('_', ' ', $status)),
                $requestId,
                $currentUserId
            ]);
        }
    } catch (PDOException $e) {
        // Ignore if activities table doesn't exist
    }
    
    jsonResponse([
        'success' => true,
        'message' => 'Status updated successfully'
    ]);
}

function handleAssignRig() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $rigId = !empty($_POST['rig_id']) ? intval($_POST['rig_id']) : null;
    
    if (empty($requestId)) {
        jsonResponse(['success' => false, 'message' => 'Request ID required'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE rig_requests SET assigned_rig_id = ? WHERE id = ?");
    $stmt->execute([$rigId, $requestId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Rig assigned successfully'
    ]);
}

function handleAssignUser() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $userId = !empty($_POST['user_id']) ? intval($_POST['user_id']) : null;
    
    if (empty($requestId)) {
        jsonResponse(['success' => false, 'message' => 'Request ID required'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE rig_requests SET assigned_to = ? WHERE id = ?");
    $stmt->execute([$userId, $requestId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'User assigned successfully'
    ]);
}

function handleAddFollowup() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $type = $_POST['type'] ?? 'call';
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $scheduledDate = $_POST['scheduled_date'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $currentUserId;
    
    if (empty($requestId) || empty($subject) || empty($scheduledDate)) {
        jsonResponse(['success' => false, 'message' => 'Required fields missing'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO rig_request_followups (
            rig_request_id, type, subject, description, scheduled_date, 
            priority, assigned_to, created_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $stmt->execute([
        $requestId, $type, $subject, $description,
        $scheduledDate, $priority, $assignedTo, $currentUserId
    ]);
    
    $followupId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Follow-up scheduled successfully',
        'followup_id' => $followupId
    ]);
}

function handleLinkToClient() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $clientId = intval($_POST['client_id'] ?? 0);
    
    if (empty($requestId) || empty($clientId)) {
        jsonResponse(['success' => false, 'message' => 'Request ID and Client ID required'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE rig_requests SET client_id = ? WHERE id = ?");
    $stmt->execute([$clientId, $requestId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Request linked to client successfully'
    ]);
}

function handleLinkToReport() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestId = intval($_POST['request_id'] ?? 0);
    $reportId = intval($_POST['report_id'] ?? 0);
    
    if (empty($requestId) || empty($reportId)) {
        jsonResponse(['success' => false, 'message' => 'Request ID and Report ID required'], 400);
    }
    
    $stmt = $pdo->prepare("UPDATE rig_requests SET field_report_id = ?, status = 'completed', completed_at = NOW() WHERE id = ?");
    $stmt->execute([$reportId, $requestId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Request linked to field report successfully'
    ]);
}

