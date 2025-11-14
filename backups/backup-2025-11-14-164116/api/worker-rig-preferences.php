<?php
/**
 * Worker Rig Preferences API
 * CRUD operations for worker rig preferences
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();
$auth->requireRole([ROLE_ADMIN, ROLE_MANAGER]);

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo, $action);
            break;
        case 'PUT':
        case 'PATCH':
            handlePut($pdo, $action);
            break;
        case 'DELETE':
            handleDelete($pdo, $action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($pdo, $action) {
    switch ($action) {
        case 'get_worker_rigs':
            $workerId = intval($_GET['worker_id'] ?? 0);
            if (!$workerId) {
                throw new Exception('Worker ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    wrp.*,
                    r.rig_name,
                    r.rig_code
                FROM worker_rig_preferences wrp
                LEFT JOIN rigs r ON wrp.rig_id = r.id
                WHERE wrp.worker_id = ?
                ORDER BY 
                    CASE wrp.preference_level 
                        WHEN 'primary' THEN 1 
                        WHEN 'secondary' THEN 2 
                        WHEN 'occasional' THEN 3 
                    END,
                    r.rig_name ASC
            ");
            $stmt->execute([$workerId]);
            $rigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $rigs
            ]);
            break;
            
        case 'get_rig_workers':
            $rigId = intval($_GET['rig_id'] ?? 0);
            if (!$rigId) {
                throw new Exception('Rig ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    wrp.*,
                    w.worker_name,
                    w.contact_number,
                    w.status as worker_status
                FROM worker_rig_preferences wrp
                LEFT JOIN workers w ON wrp.worker_id = w.id
                WHERE wrp.rig_id = ? AND w.status = 'active'
                ORDER BY 
                    CASE wrp.preference_level 
                        WHEN 'primary' THEN 1 
                        WHEN 'secondary' THEN 2 
                        WHEN 'occasional' THEN 3 
                    END,
                    w.worker_name ASC
            ");
            $stmt->execute([$rigId]);
            $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $workers
            ]);
            break;
            
        case 'get_available_rigs':
            $workerId = intval($_GET['worker_id'] ?? 0);
            
            // Get all active rigs
            $stmt = $pdo->query("
                SELECT id, rig_name, rig_code 
                FROM rigs 
                WHERE status = 'active' 
                ORDER BY rig_name
            ");
            $allRigs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get worker's current rig preferences
            $assignedRigs = [];
            if ($workerId) {
                $stmt = $pdo->prepare("
                    SELECT rig_id 
                    FROM worker_rig_preferences 
                    WHERE worker_id = ?
                ");
                $stmt->execute([$workerId]);
                $assignedRigs = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Mark which rigs are assigned
            $rigs = array_map(function($rig) use ($assignedRigs) {
                $rig['is_assigned'] = in_array($rig['id'], $assignedRigs);
                return $rig;
            }, $allRigs);
            
            echo json_encode([
                'success' => true,
                'data' => $rigs
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePost($pdo, $action) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }
    
    switch ($action) {
        case 'add_preference':
            $workerId = intval($_POST['worker_id'] ?? 0);
            $rigId = intval($_POST['rig_id'] ?? 0);
            $preferenceLevel = $_POST['preference_level'] ?? 'primary';
            $notes = trim($_POST['notes'] ?? '');
            
            if (!$workerId || !$rigId) {
                throw new Exception('Worker ID and Rig ID are required');
            }
            
            if (!in_array($preferenceLevel, ['primary', 'secondary', 'occasional'])) {
                $preferenceLevel = 'primary';
            }
            
            // Check if already exists
            $checkStmt = $pdo->prepare("SELECT id FROM worker_rig_preferences WHERE worker_id = ? AND rig_id = ?");
            $checkStmt->execute([$workerId, $rigId]);
            if ($checkStmt->fetch()) {
                throw new Exception('Preference already exists for this worker and rig');
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO worker_rig_preferences (worker_id, rig_id, preference_level, notes)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$workerId, $rigId, $preferenceLevel, $notes]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Rig preference added successfully',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_preference':
            $id = intval($_POST['id'] ?? 0);
            $preferenceLevel = $_POST['preference_level'] ?? 'primary';
            $notes = trim($_POST['notes'] ?? '');
            
            if (!$id) {
                throw new Exception('Preference ID is required');
            }
            
            if (!in_array($preferenceLevel, ['primary', 'secondary', 'occasional'])) {
                $preferenceLevel = 'primary';
            }
            
            $stmt = $pdo->prepare("
                UPDATE worker_rig_preferences 
                SET preference_level = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$preferenceLevel, $notes, $id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Rig preference updated successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePut($pdo, $action) {
    // Same as POST for updates
    handlePost($pdo, $action);
}

function handleDelete($pdo, $action) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }
    
    switch ($action) {
        case 'remove_preference':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('Preference ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM worker_rig_preferences WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Rig preference removed successfully'
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

