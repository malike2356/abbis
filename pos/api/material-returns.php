<?php
/**
 * Material Returns API
 * Handle material return requests and acceptance
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/pos/MaterialsService.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = getDBConnection();
$materialsService = new MaterialsService($pdo);
$userId = $_SESSION['user_id'] ?? 0;

try {
    if ($method === 'GET') {
        $action = $_GET['action'] ?? 'list';
        
        if ($action === 'pending') {
            $returns = $materialsService->getPendingReturns();
            echo json_encode(['success' => true, 'data' => $returns]);
            exit;
        }
        
        if ($action === 'get' && isset($_GET['id'])) {
            $stmt = $pdo->prepare("
                SELECT mr.*, 
                       u1.full_name as requested_by_name,
                       u2.full_name as accepted_by_name
                FROM pos_material_returns mr
                LEFT JOIN users u1 ON mr.requested_by = u1.id
                LEFT JOIN users u2 ON mr.accepted_by = u2.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $return = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($return) {
                echo json_encode(['success' => true, 'data' => $return]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Return request not found']);
            }
            exit;
        }
        
        // List all returns
        $status = $_GET['status'] ?? '';
        $query = "
            SELECT mr.*, 
                   u1.full_name as requested_by_name,
                   u2.full_name as accepted_by_name
            FROM pos_material_returns mr
            LEFT JOIN users u1 ON mr.requested_by = u1.id
            LEFT JOIN users u2 ON mr.accepted_by = u2.id
        ";
        $params = [];
        
        if ($status) {
            $query .= " WHERE mr.status = ?";
            $params[] = $status;
        }
        
        $query .= " ORDER BY mr.requested_at DESC LIMIT 100";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $returns]);
        exit;
    }
    
    if ($method === 'POST') {
        $action = $_POST['action'] ?? $_GET['action'] ?? '';
        
        if ($action === 'accept') {
            if (!isset($_POST['return_id'])) {
                throw new InvalidArgumentException('return_id is required');
            }
            
            $result = $materialsService->acceptReturnRequest(
                (int)$_POST['return_id'],
                $userId,
                [
                    'actual_quantity' => $_POST['actual_quantity'] ?? null,
                    'quality_check' => $_POST['quality_check'] ?? null
                ]
            );
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Return accepted successfully',
                    'data' => $result
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to accept return'
                ]);
            }
            exit;
        }
        
        if ($action === 'reject') {
            if (!isset($_POST['return_id'])) {
                throw new InvalidArgumentException('return_id is required');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Get return request before updating
                $getStmt = $pdo->prepare("
                    SELECT * FROM pos_material_returns 
                    WHERE id = ? AND status = 'pending'
                ");
                $getStmt->execute([$_POST['return_id']]);
                $returnRequest = $getStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$returnRequest) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Return request not found or already processed']);
                    exit;
                }
                
                // Update return request status
                $stmt = $pdo->prepare("
                    UPDATE pos_material_returns 
                    SET status = 'rejected',
                        rejected_by = ?,
                        rejected_at = NOW(),
                        remarks = CONCAT(COALESCE(remarks, ''), ' | Rejected: ', ?)
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([
                    $userId,
                    $_POST['rejection_reason'] ?? 'No reason provided',
                    $_POST['return_id']
                ]);
                
                // IMPORTANT: When return is rejected, materials_inventory should remain unchanged
                // (No deduction was made yet, so no restoration needed)
                // However, if materials were already deducted (shouldn't happen), restore them
                // For now, we just update the status - materials stay in operations inventory
                
                // Update field_report_materials_remaining status if exists
                try {
                    $updateRemainingStmt = $pdo->prepare("
                        UPDATE field_report_materials_remaining 
                        SET status = 'rejected'
                        WHERE return_request_id = ?
                    ");
                    $updateRemainingStmt->execute([$_POST['return_id']]);
                } catch (PDOException $e) {
                    // Table might not exist, ignore
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Return rejected. Materials remain in operations inventory.'
                ]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }
            exit;
        }
        
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('[Material Returns API] Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

