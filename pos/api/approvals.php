<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../config/constants.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    $repo = new PosRepository();
    
    if ($method === 'GET') {
        // Get pending approvals
        $approvalType = $_GET['type'] ?? null;
        $limit = min((int)($_GET['limit'] ?? 50), 100);
        
        $approvals = $repo->getPendingApprovals($approvalType, $limit);
        echo json_encode(['success' => true, 'data' => $approvals]);
    } else if ($method === 'POST') {
        // Approve or reject
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }
        
        $action = $payload['action'] ?? '';
        $approvalId = (int)($payload['approval_id'] ?? 0);
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        if ($approvalId <= 0) {
            throw new InvalidArgumentException('Approval ID is required');
        }
        
        // Check if user has permission to approve (admin or manager)
        $userRole = $auth->getUserRole();
        $isAdmin = ($userRole === ROLE_ADMIN);
        $hasPosAccess = $auth->userHasPermission('pos.access');
        
        if (!$isAdmin && !$hasPosAccess) {
            throw new InvalidArgumentException('You do not have permission to approve/reject requests');
        }
        
        $notes = $payload['notes'] ?? null;
        
        if ($action === 'approve') {
            $result = $repo->approvePendingApproval($approvalId, $userId, $notes);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Approval approved successfully']);
            } else {
                throw new RuntimeException('Failed to approve request');
            }
        } else if ($action === 'reject') {
            $result = $repo->rejectPendingApproval($approvalId, $userId, $notes);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Approval rejected']);
            } else {
                throw new RuntimeException('Failed to reject request');
            }
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action. Must be: approve or reject']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    $message = $e->getMessage();
    error_log('[POS Approvals] Validation error: ' . $message);
    echo json_encode(['success' => false, 'message' => $message]);
} catch (Throwable $e) {
    http_response_code(500);
    $message = $e->getMessage();
    error_log('[POS Approvals] Fatal error: ' . $message);
    error_log('[POS Approvals] Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'message' => 'Approval operation failed: ' . $message]);
}

