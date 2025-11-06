<?php
/**
 * CRM API Endpoints
 * Handles AJAX requests for CRM operations
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/email.php';

header('Content-Type: application/json');

$auth->requireAuth();

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currentUserId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'add_followup':
            handleAddFollowup();
            break;
            
        case 'update_followup':
            handleUpdateFollowup();
            break;
            
        case 'complete_followup':
            handleCompleteFollowup();
            break;
            
        case 'send_email':
            handleSendEmail();
            break;
            
        case 'add_contact':
            handleAddContact();
            break;
            
        case 'add_activity':
            handleAddActivity();
            break;
            
        case 'get_client_data':
            handleGetClientData();
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("CRM API error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}

function handleAddFollowup() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $clientId = intval($_POST['client_id'] ?? 0);
    $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
    $type = $_POST['type'] ?? 'call';
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $scheduledDate = $_POST['scheduled_date'] ?? '';
    $priority = $_POST['priority'] ?? 'medium';
    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : $currentUserId;
    
    if (empty($clientId) || empty($subject) || empty($scheduledDate)) {
        jsonResponse(['success' => false, 'message' => 'Required fields missing'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO client_followups (
            client_id, contact_id, type, subject, description, scheduled_date, 
            priority, assigned_to, created_by, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
    ");
    
    $stmt->execute([
        $clientId, $contactId, $type, $subject, $description,
        $scheduledDate, $priority, $assignedTo, $currentUserId
    ]);
    
    $followupId = $pdo->lastInsertId();
    
    // Record activity
    $stmt = $pdo->prepare("
        INSERT INTO client_activities (client_id, type, title, description, related_id, related_type, created_by)
        VALUES (?, 'note', 'Follow-up Scheduled', ?, ?, 'followup', ?)
    ");
    $stmt->execute([$clientId, $subject, $followupId, $currentUserId]);
    
    // Update client last contact date
    $stmt = $pdo->prepare("UPDATE clients SET next_followup_date = ? WHERE id = ?");
    $stmt->execute([$scheduledDate, $clientId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Follow-up scheduled successfully',
        'followup_id' => $followupId
    ]);
}

function handleUpdateFollowup() {
    global $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $followupId = intval($_POST['followup_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    
    if (empty($followupId)) {
        jsonResponse(['success' => false, 'message' => 'Follow-up ID required'], 400);
    }
    
    $stmt = $pdo->prepare("
        UPDATE client_followups 
        SET status = ?, completed_date = ?,
            outcome = ?, updated_at = NOW()
        WHERE id = ?
    ");
    
    $completedDate = ($status === 'completed') ? date('Y-m-d H:i:s') : null;
    $outcome = sanitizeInput($_POST['outcome'] ?? '');
    
    $stmt->execute([$status, $completedDate, $outcome, $followupId]);
    
    jsonResponse(['success' => true, 'message' => 'Follow-up updated']);
}

function handleCompleteFollowup() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $followupId = intval($_POST['followup_id'] ?? 0);
    $outcome = sanitizeInput($_POST['outcome'] ?? '');
    
    if (empty($followupId)) {
        jsonResponse(['success' => false, 'message' => 'Follow-up ID required'], 400);
    }
    
    // Get follow-up details
    $stmt = $pdo->prepare("SELECT * FROM client_followups WHERE id = ?");
    $stmt->execute([$followupId]);
    $followup = $stmt->fetch();
    
    if (!$followup) {
        jsonResponse(['success' => false, 'message' => 'Follow-up not found'], 404);
    }
    
    // Update follow-up
    $stmt = $pdo->prepare("
        UPDATE client_followups 
        SET status = 'completed', completed_date = NOW(), outcome = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$outcome, $followupId]);
    
    // Update client last contact date
    $stmt = $pdo->prepare("UPDATE clients SET last_contact_date = CURDATE() WHERE id = ?");
    $stmt->execute([$followup['client_id']]);
    
    // Record activity
    $stmt = $pdo->prepare("
        INSERT INTO client_activities (client_id, type, title, description, related_id, related_type, created_by)
        VALUES (?, 'note', 'Follow-up Completed', ?, ?, 'followup', ?)
    ");
    $stmt->execute([$followup['client_id'], $followup['subject'], $followupId, $currentUserId]);
    
    jsonResponse(['success' => true, 'message' => 'Follow-up marked as completed']);
}

function handleSendEmail() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $clientId = intval($_POST['client_id'] ?? 0);
    $contactId = !empty($_POST['contact_id']) ? intval($_POST['contact_id']) : null;
    $to = sanitizeInput($_POST['to'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $body = $_POST['body'] ?? '';
    $templateId = !empty($_POST['template_id']) ? intval($_POST['template_id']) : null;
    
    if (empty($clientId) || empty($to) || empty($subject) || empty($body)) {
        jsonResponse(['success' => false, 'message' => 'Required fields missing'], 400);
    }
    
    // Get client info
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
    }
    
    // Send email
    $emailer = new Email();
    $result = $emailer->send($to, $subject, $body);
    
    if (!$result) {
        jsonResponse(['success' => false, 'message' => 'Failed to send email'], 500);
    }
    
    // Record email
    $stmt = $pdo->prepare("
        INSERT INTO client_emails (
            client_id, contact_id, direction, subject, body,
            from_email, to_email, status, sent_at, created_by
        ) VALUES (?, ?, 'outbound', ?, ?, ?, ?, 'sent', NOW(), ?)
    ");
    
    $fromEmail = $_SESSION['email'] ?? 'noreply@abbis.africa';
    $stmt->execute([
        $clientId, $contactId, $subject, $body,
        $fromEmail, $to, $currentUserId
    ]);
    
    $emailId = $pdo->lastInsertId();
    
    // Record activity
    $stmt = $pdo->prepare("
        INSERT INTO client_activities (client_id, type, title, description, related_id, related_type, created_by)
        VALUES (?, 'email', 'Email Sent', ?, ?, 'email', ?)
    ");
    $stmt->execute([$clientId, $subject, $emailId, $currentUserId]);
    
    // Update client last contact date
    $stmt = $pdo->prepare("UPDATE clients SET last_contact_date = CURDATE() WHERE id = ?");
    $stmt->execute([$clientId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Email sent successfully',
        'email_id' => $emailId
    ]);
}

function handleAddContact() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $clientId = intval($_POST['client_id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $title = sanitizeInput($_POST['title'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $mobile = sanitizeInput($_POST['mobile'] ?? '');
    $isPrimary = isset($_POST['is_primary']) ? 1 : 0;
    $department = sanitizeInput($_POST['department'] ?? '');
    
    if (empty($clientId) || empty($name)) {
        jsonResponse(['success' => false, 'message' => 'Client ID and name required'], 400);
    }
    
    // If this is primary, unset other primary contacts
    if ($isPrimary) {
        $stmt = $pdo->prepare("UPDATE client_contacts SET is_primary = 0 WHERE client_id = ?");
        $stmt->execute([$clientId]);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO client_contacts (
            client_id, name, title, email, phone, mobile, is_primary, department
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $clientId, $name, $title, $email, $phone, $mobile, $isPrimary, $department
    ]);
    
    $contactId = $pdo->lastInsertId();
    
    // Record activity
    $stmt = $pdo->prepare("
        INSERT INTO client_activities (client_id, type, title, description, related_id, related_type, created_by)
        VALUES (?, 'update', 'Contact Added', 'New contact: ' . ?, ?, 'contact', ?)
    ");
    $stmt->execute([$clientId, $name, $contactId, $currentUserId]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Contact added successfully',
        'contact_id' => $contactId
    ]);
}

function handleAddActivity() {
    global $pdo, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $clientId = intval($_POST['client_id'] ?? 0);
    $type = $_POST['type'] ?? 'note';
    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    
    if (empty($clientId) || empty($title)) {
        jsonResponse(['success' => false, 'message' => 'Required fields missing'], 400);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO client_activities (client_id, type, title, description, created_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$clientId, $type, $title, $description, $currentUserId]);
    
    $activityId = $pdo->lastInsertId();
    
    jsonResponse([
        'success' => true,
        'message' => 'Activity recorded',
        'activity_id' => $activityId
    ]);
}

function handleGetClientData() {
    global $pdo;
    
    $clientId = intval($_GET['client_id'] ?? 0);
    
    if (empty($clientId)) {
        jsonResponse(['success' => false, 'message' => 'Client ID required'], 400);
    }
    
    // Get client
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
    }
    
    // Get contacts
    $stmt = $pdo->prepare("SELECT * FROM client_contacts WHERE client_id = ? ORDER BY is_primary DESC, name");
    $stmt->execute([$clientId]);
    $contacts = $stmt->fetchAll();
    
    // Get follow-ups
    $stmt = $pdo->prepare("
        SELECT cf.*, u.full_name as assigned_name
        FROM client_followups cf
        LEFT JOIN users u ON cf.assigned_to = u.id
        WHERE cf.client_id = ?
        ORDER BY cf.scheduled_date DESC
        LIMIT 20
    ");
    $stmt->execute([$clientId]);
    $followups = $stmt->fetchAll();
    
    // Get emails
    $stmt = $pdo->prepare("
        SELECT * FROM client_emails
        WHERE client_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$clientId]);
    $emails = $stmt->fetchAll();
    
    // Get activities
    $stmt = $pdo->prepare("
        SELECT ca.*, u.full_name as creator_name
        FROM client_activities ca
        LEFT JOIN users u ON ca.created_by = u.id
        WHERE ca.client_id = ?
        ORDER BY ca.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$clientId]);
    $activities = $stmt->fetchAll();
    
    jsonResponse([
        'success' => true,
        'client' => $client,
        'contacts' => $contacts,
        'followups' => $followups,
        'emails' => $emails,
        'activities' => $activities
    ]);
}

