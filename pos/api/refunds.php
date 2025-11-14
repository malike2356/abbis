<?php

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/PosRepository.php';
require_once __DIR__ . '/../../includes/pos/PosValidator.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$repo = new PosRepository();

try {
    if ($method === 'GET') {
        // Get return reasons list
        if (isset($_GET['action']) && $_GET['action'] === 'return_reasons') {
            $reasons = getReturnReasons();
            echo json_encode(['success' => true, 'data' => $reasons]);
            exit;
        }
        
        // List refunds or get specific refund
        $refundId = $_GET['id'] ?? null;
        $saleId = $_GET['sale_id'] ?? null;
        
        if ($refundId) {
            $refund = $repo->getRefund((int)$refundId);
            if (!$refund) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Refund not found']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $refund]);
        } else if ($saleId) {
            $refunds = $repo->getRefundsBySale((int)$saleId);
            echo json_encode(['success' => true, 'data' => $refunds]);
        } else {
            // List all refunds with pagination
            $limit = (int)($_GET['limit'] ?? 50);
            $offset = (int)($_GET['offset'] ?? 0);
            $status = $_GET['status'] ?? null; // Filter by status (pending, completed, cancelled)
            $refunds = $repo->listRefunds($limit, $offset, $status);
            echo json_encode(['success' => true, 'data' => $refunds]);
        }
    } else if ($method === 'POST') {
        $payload = json_decode(file_get_contents('php://input'), true);
        
        if (!is_array($payload)) {
            throw new InvalidArgumentException('Invalid JSON payload');
        }
        
        $action = $payload['action'] ?? 'create';
        
        if ($action === 'approve') {
            // Approve refund
            $refundId = (int)($payload['refund_id'] ?? 0);
            $approverId = (int)($_SESSION['user_id'] ?? 0);
            $approvalNotes = trim($payload['approval_notes'] ?? '');
            
            if ($refundId <= 0) {
                throw new InvalidArgumentException('Refund ID is required');
            }
            
            if ($approverId <= 0) {
                throw new InvalidArgumentException('Unable to determine approver account');
            }
            
            // Check if user has permission to approve refunds
            // Admins and users with pos.access can approve refunds
            require_once __DIR__ . '/../../config/constants.php';
            $userRole = $auth->getUserRole();
            $isAdmin = ($userRole === ROLE_ADMIN);
            $hasPosAccess = $auth->userHasPermission('pos.access');
            
            if (!$isAdmin && !$hasPosAccess) {
                throw new InvalidArgumentException('You do not have permission to approve refunds');
            }
            
            $result = $repo->approveRefund($refundId, $approverId, $approvalNotes);
            echo json_encode(['success' => true, 'message' => 'Refund approved successfully', 'data' => $result]);
        } else if ($action === 'reject') {
            // Reject refund
            $refundId = (int)($payload['refund_id'] ?? 0);
            $rejectorId = (int)($_SESSION['user_id'] ?? 0);
            $rejectionNotes = trim($payload['rejection_notes'] ?? '');
            
            if ($refundId <= 0) {
                throw new InvalidArgumentException('Refund ID is required');
            }
            
            if ($rejectorId <= 0) {
                throw new InvalidArgumentException('Unable to determine rejector account');
            }
            
            if (empty($rejectionNotes)) {
                throw new InvalidArgumentException('Rejection notes are required');
            }
            
            // Check if user has permission to reject refunds
            // Admins and users with pos.access can reject refunds
            require_once __DIR__ . '/../../config/constants.php';
            $userRole = $auth->getUserRole();
            $isAdmin = ($userRole === ROLE_ADMIN);
            $hasPosAccess = $auth->userHasPermission('pos.access');
            
            if (!$isAdmin && !$hasPosAccess) {
                throw new InvalidArgumentException('You do not have permission to reject refunds');
            }
            
            $result = $repo->rejectRefund($refundId, $rejectorId, $rejectionNotes);
            echo json_encode(['success' => true, 'message' => 'Refund rejected', 'data' => $result]);
        } else {
            // Create refund
            $validated = PosValidator::validateRefundPayload($payload);
            $validated['cashier_id'] = (int)($_SESSION['user_id'] ?? 0);
            
            if ($validated['cashier_id'] <= 0) {
                throw new InvalidArgumentException('Unable to determine cashier account');
            }
            
            $result = $repo->createRefund($validated);
            
            if ($result['requires_approval'] && $result['status'] === 'pending') {
                echo json_encode([
                    'success' => true,
                    'message' => 'Refund created and pending manager approval',
                    'data' => $result
                ]);
            } else {
                echo json_encode(['success' => true, 'data' => $result]);
            }
        }
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[POS Refund] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Refund processing failed']);
}

/**
 * Get return reasons list
 * Returns predefined return reasons with option to add custom ones from config
 */
function getReturnReasons(): array
{
    $defaultReasons = [
        'defective' => 'Defective/Damaged Item',
        'wrong_item' => 'Wrong Item Received',
        'not_as_described' => 'Not as Described',
        'customer_change_mind' => 'Customer Change of Mind',
        'duplicate_order' => 'Duplicate Order',
        'late_delivery' => 'Late Delivery',
        'quality_issue' => 'Quality Issue',
        'size_fit' => 'Wrong Size/Fit',
        'expired' => 'Expired Product',
        'other' => 'Other (Specify in Notes)',
    ];
    
    // Check for custom reasons in system config
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'pos_return_reasons' LIMIT 1");
        $stmt->execute();
        $customReasonsJson = $stmt->fetchColumn();
        
        if ($customReasonsJson) {
            $customReasons = json_decode($customReasonsJson, true);
            if (is_array($customReasons)) {
                // Merge custom reasons with defaults (custom ones take precedence)
                $defaultReasons = array_merge($defaultReasons, $customReasons);
            }
        }
    } catch (Throwable $e) {
        // If config doesn't exist or error, use defaults
        error_log('Error loading custom return reasons: ' . $e->getMessage());
    }
    
    return $defaultReasons;
}

