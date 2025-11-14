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
require_once '../includes/request-response-manager.php';

header('Content-Type: application/json');

$auth->requireAuth();

$pdo = getDBConnection();
$responseManager = new RequestResponseManager($pdo);
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
            
        case 'generate_request_response':
            handleGenerateRequestResponse();
            break;
            
        case 'add_response_item':
            handleAddResponseItem();
            break;
            
        case 'update_response_item':
            handleUpdateResponseItem();
            break;
            
        case 'delete_response_item':
            handleDeleteResponseItem();
            break;
            
        case 'submit_response_for_approval':
            handleSubmitResponseForApproval();
            break;
            
        case 'approve_response':
            handleApproveResponse();
            break;
            
        case 'send_response':
            handleSendResponse();
            break;
            
        case 'get_request_responses':
            handleGetRequestResponses();
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

// Helper function to build template variables
function buildTemplateVariables($client, $report = null, $userId = null) {
    global $pdo;
    
    $vars = [];
    
    // Client variables
    $vars['client_name'] = $client['client_name'] ?? '';
    $vars['contact_name'] = $client['contact_person'] ?? '';
    $vars['contact_number'] = $client['contact_number'] ?? '';
    $vars['client_email'] = $client['email'] ?? '';
    $vars['company_type'] = $client['company_type'] ?? '';
    $vars['client_address'] = $client['address'] ?? '';
    $vars['client_status'] = ucfirst($client['status'] ?? '');
    
    // Company variables
    try {
        $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
        $vars['company_name'] = $stmt->fetchColumn() ?: 'ABBIS';
    } catch (PDOException $e) {
        $vars['company_name'] = 'ABBIS';
    }
    
    $vars['sender_name'] = $_SESSION['full_name'] ?? 'System Admin';
    $vars['sender_email'] = $_SESSION['email'] ?? 'admin@abbis.africa';
    $vars['current_date'] = date('M d, Y');
    $vars['current_time'] = date('g:i A');
    $vars['currency'] = 'GHS';
    
    // Field report variables
    if ($report) {
        $vars['report_id'] = $report['report_id'] ?? '';
        $vars['report_date'] = $report['report_date'] ? date('M d, Y', strtotime($report['report_date'])) : '';
        $vars['site_name'] = $report['site_name'] ?? '';
        $vars['job_type'] = ucfirst($report['job_type'] ?? '');
        $vars['total_depth'] = number_format($report['total_depth'] ?? 0, 2);
        $vars['total_rpm'] = number_format($report['total_rpm'] ?? 0, 2);
        $vars['total_duration'] = $report['total_duration'] ?? 0;
        $vars['rig_name'] = $report['rig_name'] ?? '';
        $vars['rig_code'] = $report['rig_code'] ?? '';
        $vars['location_description'] = $report['location_description'] ?? '';
        $vars['contract_sum'] = number_format($report['contract_sum'] ?? 0, 2);
        $vars['rig_fee_charged'] = number_format($report['rig_fee_charged'] ?? 0, 2);
        $vars['rig_fee_collected'] = number_format($report['rig_fee_collected'] ?? 0, 2);
        $vars['total_income'] = number_format($report['total_income'] ?? 0, 2);
        $vars['total_expenses'] = number_format($report['total_expenses'] ?? 0, 2);
        $vars['net_profit'] = number_format($report['net_profit'] ?? 0, 2);
        $vars['outstanding_balance'] = number_format(($report['rig_fee_charged'] ?? 0) - ($report['rig_fee_collected'] ?? 0), 2);
    }
    
    return $vars;
}

// Helper function to replace template variables
function replaceTemplateVariables($text, $variables) {
    foreach ($variables as $key => $value) {
        $text = str_replace('{{' . $key . '}}', $value, $text);
        $text = str_replace('{$' . $key . '}', $value, $text);
    }
    return $text;
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
    $reportId = !empty($_POST['report_id']) ? intval($_POST['report_id']) : null;
    
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
    
    // Get field report data if report_id is provided
    $report = null;
    if ($reportId) {
        try {
            $stmt = $pdo->prepare("
                SELECT fr.*, r.rig_name, r.rig_code 
                FROM field_reports fr 
                LEFT JOIN rigs r ON fr.rig_id = r.id 
                WHERE fr.id = ? AND fr.client_id = ?
            ");
            $stmt->execute([$reportId, $clientId]);
            $report = $stmt->fetch();
        } catch (PDOException $e) {
            // Report not found or error, continue without report data
        }
    }
    
    // Replace template variables
    $variables = buildTemplateVariables($client, $report, $currentUserId);
    $subject = replaceTemplateVariables($subject, $variables);
    $body = replaceTemplateVariables($body, $variables);
    
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

function handleGenerateRequestResponse() {
    global $responseManager, $currentUserId, $pdo;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $requestType = $_POST['request_type'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    
    if (!in_array($requestType, ['quote', 'rig'], true) || $requestId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);
    }
    
    try {
        if ($requestType === 'quote') {
            $response = $responseManager->generateQuoteResponse($requestId, $currentUserId);
        } else {
            $response = $responseManager->generateRigResponse($requestId, $currentUserId);
        }
        
        $responses = $responseManager->getResponsesForRequest($requestType, $requestId);
        $status = getRequestStatusValue($requestType, $requestId);
        $history = $responseManager->getStatusHistoryForRequest($requestType, $requestId);
        
        jsonResponse([
            'success' => true,
            'response' => $response,
            'responses' => $responses,
            'request_status' => $status,
            'history' => $history,
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleAddResponseItem() {
    global $responseManager, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $responseId = (int)($_POST['response_id'] ?? 0);
    $itemName = trim($_POST['item_name'] ?? '');
    
    if ($responseId <= 0 || $itemName === '') {
        jsonResponse(['success' => false, 'message' => 'Response ID and item name are required'], 400);
    }
    
    $data = [
        'item_name' => $itemName,
        'description' => $_POST['description'] ?? '',
        'quantity' => $_POST['quantity'] ?? 1,
        'unit_price' => $_POST['unit_price'] ?? 0,
        'discount_amount' => $_POST['discount_amount'] ?? 0,
        'tax_rate' => $_POST['tax_rate'] ?? 0,
    ];
    
    try {
        $responseManager->addCustomItem($responseId, $data, $currentUserId);
        $response = $responseManager->getResponse($responseId);
        $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
        $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
        jsonResponse(['success' => true, 'response' => $response, 'responses' => $responses, 'history' => $history]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleUpdateResponseItem() {
    global $responseManager;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Item ID is required'], 400);
    }
    
    $data = [
        'item_name' => $_POST['item_name'] ?? null,
        'description' => $_POST['description'] ?? null,
        'quantity' => $_POST['quantity'] ?? null,
        'unit_price' => $_POST['unit_price'] ?? null,
        'discount_amount' => $_POST['discount_amount'] ?? null,
        'tax_rate' => $_POST['tax_rate'] ?? null,
        'sort_order' => $_POST['sort_order'] ?? null,
    ];
    
    try {
        $responseManager->updateItem($itemId, $data);
        $item = $responseManager->getItem($itemId); // we need updated response id
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
        return;
    }

    if (!$item) {
        jsonResponse(['success' => false, 'message' => 'Item not found after update'], 404);
        return;
    }

    $response = $responseManager->getResponse($item['response_id']);
    $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
    $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
    jsonResponse(['success' => true, 'response' => $response, 'responses' => $responses, 'history' => $history]);
}

function handleDeleteResponseItem() {
    global $responseManager;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Item ID is required'], 400);
    }
    
    try {
        $item = $responseManager->getItem($itemId);
        if (!$item) {
            jsonResponse(['success' => false, 'message' => 'Item not found'], 404);
            return;
        }
        $responseManager->deleteItem($itemId);
        $response = $responseManager->getResponse($item['response_id']);
        $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
        $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
        jsonResponse(['success' => true, 'response' => $response, 'responses' => $responses, 'history' => $history]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleSubmitResponseForApproval() {
    global $responseManager, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $responseId = (int)($_POST['response_id'] ?? 0);
    if ($responseId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Response ID is required'], 400);
    }
    
    try {
        $responseManager->submitForApproval($responseId, $currentUserId);
        $response = $responseManager->getResponse($responseId);
        $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
        $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
        jsonResponse(['success' => true, 'response' => $response, 'responses' => $responses, 'history' => $history]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleApproveResponse() {
    global $responseManager, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $responseId = (int)($_POST['response_id'] ?? 0);
    if ($responseId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Response ID is required'], 400);
    }
    
    try {
        $responseManager->approveResponse($responseId, $currentUserId);
        $response = $responseManager->getResponse($responseId);
        $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
        $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
        jsonResponse(['success' => true, 'response' => $response, 'responses' => $responses, 'history' => $history]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleSendResponse() {
    global $responseManager, $currentUserId;
    
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
    
    $responseId = (int)($_POST['response_id'] ?? 0);
    $toEmail = trim($_POST['to_email'] ?? '');
    
    if ($responseId <= 0 || $toEmail === '') {
        jsonResponse(['success' => false, 'message' => 'Response ID and recipient email are required'], 400);
    }
    
    $options = [
        'note' => $_POST['note'] ?? null,
    ];
    
    if (!empty($_POST['cc'])) {
        $options['cc'] = normalizeEmailList($_POST['cc']);
    }
    if (!empty($_POST['bcc'])) {
        $options['bcc'] = normalizeEmailList($_POST['bcc']);
    }
    
    try {
        $responseManager->sendResponseEmail($responseId, $toEmail, $currentUserId, $options);
        $response = $responseManager->getResponse($responseId);
        $responses = $responseManager->getResponsesForRequest($response['request_type'], $response['request_id']);
        $history = $responseManager->getStatusHistoryForRequest($response['request_type'], $response['request_id']);
        $status = getRequestStatusValue($response['request_type'], $response['request_id']);
        jsonResponse([
            'success' => true,
            'response' => $response,
            'responses' => $responses,
            'history' => $history,
            'request_status' => $status,
        ]);
    } catch (Exception $e) {
        jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

function handleGetRequestResponses() {
    global $responseManager;
    
    $requestType = $_GET['request_type'] ?? '';
    $requestId = (int)($_GET['request_id'] ?? 0);
    
    if (!in_array($requestType, ['quote', 'rig'], true) || $requestId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Invalid request data'], 400);
    }
    
    $responses = $responseManager->getResponsesForRequest($requestType, $requestId);
    $history = $responseManager->getStatusHistoryForRequest($requestType, $requestId);
    jsonResponse(['success' => true, 'responses' => $responses, 'history' => $history]);
}

function getRequestStatusValue(string $requestType, int $requestId): ?string
{
    global $pdo;
    if ($requestType === 'quote') {
        $stmt = $pdo->prepare("SELECT status FROM cms_quote_requests WHERE id = ? LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT status FROM rig_requests WHERE id = ? LIMIT 1");
    }
    $stmt->execute([$requestId]);
    return $stmt->fetchColumn() ?: null;
}

function normalizeEmailList($value): array
{
    $emails = is_array($value)
        ? array_map('trim', $value)
        : array_map('trim', preg_split('/[,;]/', (string)$value));

    $valid = array_filter($emails, function ($email) {
        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
    });

    return array_values($valid);
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

