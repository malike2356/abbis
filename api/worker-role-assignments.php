<?php
/**
 * Worker Role Assignments API
 * CRUD operations for worker role assignments
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
        case 'get_worker_roles':
            $workerId = intval($_GET['worker_id'] ?? 0);
            if (!$workerId) {
                throw new Exception('Worker ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    wra.*,
                    wr.description as role_description
                FROM worker_role_assignments wra
                LEFT JOIN worker_roles wr ON wra.role_name = wr.role_name
                WHERE wra.worker_id = ?
                ORDER BY wra.is_primary DESC, wra.role_name ASC
            ");
            $stmt->execute([$workerId]);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $roles
            ]);
            break;
            
        case 'get_available_roles':
            $workerId = intval($_GET['worker_id'] ?? 0);
            
            // Get all active roles
            $stmt = $pdo->query("
                SELECT role_name, description 
                FROM worker_roles 
                WHERE is_active = 1 
                ORDER BY role_name
            ");
            $allRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get worker's current roles
            $assignedRoles = [];
            if ($workerId) {
                $stmt = $pdo->prepare("
                    SELECT role_name 
                    FROM worker_role_assignments 
                    WHERE worker_id = ?
                ");
                $stmt->execute([$workerId]);
                $assignedRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            // Mark which roles are assigned
            $roles = array_map(function($role) use ($assignedRoles) {
                $role['is_assigned'] = in_array($role['role_name'], $assignedRoles);
                return $role;
            }, $allRoles);
            
            echo json_encode([
                'success' => true,
                'data' => $roles
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

/**
 * Validate role compatibility
 * Business rule: Driller cannot also be Spanner Boy or Table Boy (mutually exclusive)
 */
function validateRoleCompatibility($pdo, $workerId, $newRoleName) {
    // Normalize role names for comparison (case-insensitive)
    $newRole = strtolower(trim($newRoleName));
    
    // Define incompatible role groups
    $drillerRoles = ['driller'];
    $assistantRoles = ['spanner boy', 'spanner', 'table boy', 'table'];
    
    // Check if new role is a driller role
    $isDrillerRole = false;
    foreach ($drillerRoles as $driller) {
        if (strpos($newRole, $driller) !== false) {
            $isDrillerRole = true;
            break;
        }
    }
    
    // Check if new role is an assistant role
    $isAssistantRole = false;
    foreach ($assistantRoles as $assistant) {
        if (strpos($newRole, $assistant) !== false) {
            $isAssistantRole = true;
            break;
        }
    }
    
    // Get worker's existing roles
    $stmt = $pdo->prepare("
        SELECT role_name 
        FROM worker_role_assignments 
        WHERE worker_id = ?
    ");
    $stmt->execute([$workerId]);
    $existingRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Also check workers.role column (legacy)
    $stmt = $pdo->prepare("SELECT role FROM workers WHERE id = ?");
    $stmt->execute([$workerId]);
    $primaryRole = $stmt->fetchColumn();
    if ($primaryRole) {
        $existingRoles[] = $primaryRole;
    }
    
    // Check for conflicts
    foreach ($existingRoles as $existingRole) {
        $existing = strtolower(trim($existingRole));
        
        // If worker is already a driller, cannot add assistant role
        if ($isAssistantRole) {
            foreach ($drillerRoles as $driller) {
                if (strpos($existing, $driller) !== false) {
                    throw new Exception('A Driller cannot also be a Spanner Boy or Table Boy. These roles are mutually exclusive - the Driller operates the machine while the Spanner/Table Boy is their direct assistant.');
                }
            }
        }
        
        // If worker is already an assistant, cannot add driller role
        if ($isDrillerRole) {
            foreach ($assistantRoles as $assistant) {
                if (strpos($existing, $assistant) !== false) {
                    throw new Exception('A Spanner Boy or Table Boy cannot also be a Driller. These roles are mutually exclusive - the Driller operates the machine while the Spanner/Table Boy is their direct assistant.');
                }
            }
        }
    }
    
    return true;
}

function handlePost($pdo, $action) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }
    
    switch ($action) {
        case 'add_role':
            $workerId = intval($_POST['worker_id'] ?? 0);
            $roleName = trim($_POST['role_name'] ?? '');
            $isPrimary = isset($_POST['is_primary']) ? intval($_POST['is_primary']) : 0;
            $defaultRate = isset($_POST['default_rate']) ? floatval($_POST['default_rate']) : null;
            
            if (!$workerId || !$roleName) {
                throw new Exception('Worker ID and Role Name are required');
            }
            
            // Validate role compatibility
            validateRoleCompatibility($pdo, $workerId, $roleName);
            
            // Check if already assigned
            $checkStmt = $pdo->prepare("SELECT id FROM worker_role_assignments WHERE worker_id = ? AND role_name = ?");
            $checkStmt->execute([$workerId, $roleName]);
            if ($checkStmt->fetch()) {
                throw new Exception('Role already assigned to this worker');
            }
            
            // If setting as primary, unset other primary roles
            if ($isPrimary) {
                $updateStmt = $pdo->prepare("UPDATE worker_role_assignments SET is_primary = 0 WHERE worker_id = ?");
                $updateStmt->execute([$workerId]);
                
                // Update workers table primary role
                $updateWorkerStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE id = ?");
                $updateWorkerStmt->execute([$roleName, $workerId]);
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO worker_role_assignments (worker_id, role_name, is_primary, default_rate)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$workerId, $roleName, $isPrimary, $defaultRate]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Role assigned successfully',
                'id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_role':
            $id = intval($_POST['id'] ?? 0);
            $isPrimary = isset($_POST['is_primary']) ? intval($_POST['is_primary']) : 0;
            $defaultRate = isset($_POST['default_rate']) ? floatval($_POST['default_rate']) : null;
            $newRoleName = trim($_POST['role_name'] ?? ''); // Allow role name update
            
            if (!$id) {
                throw new Exception('Assignment ID is required');
            }
            
            // Get current assignment
            $getStmt = $pdo->prepare("SELECT worker_id, role_name FROM worker_role_assignments WHERE id = ?");
            $getStmt->execute([$id]);
            $assignment = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assignment) {
                throw new Exception('Assignment not found');
            }
            
            // If role name is being changed, validate compatibility
            if ($newRoleName && $newRoleName !== $assignment['role_name']) {
                validateRoleCompatibility($pdo, $assignment['worker_id'], $newRoleName);
            }
            
            // If setting as primary, unset other primary roles
            if ($isPrimary) {
                $updateStmt = $pdo->prepare("UPDATE worker_role_assignments SET is_primary = 0 WHERE worker_id = ? AND id != ?");
                $updateStmt->execute([$assignment['worker_id'], $id]);
                
                // Update workers table primary role (use new role name if changed, otherwise current)
                $roleToSet = $newRoleName ? $newRoleName : $assignment['role_name'];
                $updateWorkerStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE id = ?");
                $updateWorkerStmt->execute([$roleToSet, $assignment['worker_id']]);
            }
            
            // Build update query based on what's being updated
            $updates = [];
            $params = [];
            
            if ($newRoleName && $newRoleName !== $assignment['role_name']) {
                $updates[] = "role_name = ?";
                $params[] = $newRoleName;
            }
            
            $updates[] = "is_primary = ?";
            $params[] = $isPrimary;
            
            $updates[] = "default_rate = ?";
            $params[] = $defaultRate;
            
            $updates[] = "updated_at = CURRENT_TIMESTAMP";
            
            $params[] = $id;
            
            $stmt = $pdo->prepare("
                UPDATE worker_role_assignments 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            echo json_encode([
                'success' => true,
                'message' => 'Role assignment updated successfully'
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
        case 'remove_role':
            $id = intval($_POST['id'] ?? 0);
            
            if (!$id) {
                throw new Exception('Assignment ID is required');
            }
            
            // Get assignment details
            $getStmt = $pdo->prepare("SELECT worker_id, role_name, is_primary FROM worker_role_assignments WHERE id = ?");
            $getStmt->execute([$id]);
            $assignment = $getStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$assignment) {
                throw new Exception('Assignment not found');
            }
            
            $pdo->beginTransaction();
            
            try {
                // Delete the assignment
                $deleteStmt = $pdo->prepare("DELETE FROM worker_role_assignments WHERE id = ?");
                $deleteStmt->execute([$id]);
                
                // If it was primary, set another role as primary
                if ($assignment['is_primary']) {
                    $newPrimaryStmt = $pdo->prepare("
                        SELECT id, role_name 
                        FROM worker_role_assignments 
                        WHERE worker_id = ? 
                        ORDER BY created_at ASC 
                        LIMIT 1
                    ");
                    $newPrimaryStmt->execute([$assignment['worker_id']]);
                    $newPrimary = $newPrimaryStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($newPrimary) {
                        $updateStmt = $pdo->prepare("UPDATE worker_role_assignments SET is_primary = 1 WHERE id = ?");
                        $updateStmt->execute([$newPrimary['id']]);
                        
                        $updateWorkerStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE id = ?");
                        $updateWorkerStmt->execute([$newPrimary['role_name'], $assignment['worker_id']]);
                    } else {
                        // No more roles, clear primary role in workers table
                        $updateWorkerStmt = $pdo->prepare("UPDATE workers SET role = '' WHERE id = ?");
                        $updateWorkerStmt->execute([$assignment['worker_id']]);
                    }
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Role assignment removed successfully'
                ]);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

