<?php
/**
 * Configuration Manager - CRUD Operations
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class ConfigManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    // Rigs CRUD
    public function getRigs($status = null) {
        // Check if RPM columns exist
        $hasRpmColumns = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM rigs LIKE 'current_rpm'");
            $hasRpmColumns = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            $hasRpmColumns = false;
        }
        
        if ($hasRpmColumns) {
            $query = "SELECT 
                r.*,
                COALESCE(r.current_rpm, 0) as current_rpm,
                COALESCE(r.last_maintenance_rpm, 0) as last_maintenance_rpm,
                r.maintenance_due_at_rpm,
                COALESCE(r.maintenance_rpm_interval, 30.00) as maintenance_rpm_interval,
                CASE 
                    WHEN r.maintenance_due_at_rpm IS NULL THEN NULL
                    WHEN r.current_rpm >= r.maintenance_due_at_rpm THEN 'due'
                    WHEN (r.maintenance_due_at_rpm - r.current_rpm) <= (r.maintenance_rpm_interval * 0.1) THEN 'soon'
                    ELSE 'ok'
                END as maintenance_status,
                CASE 
                    WHEN r.maintenance_due_at_rpm IS NULL THEN NULL
                    ELSE GREATEST(0, r.maintenance_due_at_rpm - COALESCE(r.current_rpm, 0))
                END as rpm_remaining
            FROM rigs r";
        } else {
            // Fallback: basic query without RPM columns
            $query = "SELECT 
                r.*,
                0 as current_rpm,
                0 as last_maintenance_rpm,
                NULL as maintenance_due_at_rpm,
                30.00 as maintenance_rpm_interval,
                NULL as maintenance_status,
                NULL as rpm_remaining
            FROM rigs r";
        }
        
        if ($status) {
            $query .= " WHERE r.status = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$status]);
        } else {
            $stmt = $this->pdo->query($query);
        }
        return $stmt->fetchAll();
    }
    
    public function addRig($data) {
        // Check if RPM columns exist
        $hasRpmColumns = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM rigs LIKE 'current_rpm'");
            $hasRpmColumns = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            $hasRpmColumns = false;
        }
        
        if ($hasRpmColumns) {
            $maintenanceRpmInterval = !empty($data['maintenance_rpm_interval']) ? floatval($data['maintenance_rpm_interval']) : 30.00;
            $currentRpm = !empty($data['current_rpm']) ? floatval($data['current_rpm']) : 0.00;
            $maintenanceDueAtRpm = $currentRpm + $maintenanceRpmInterval;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO rigs (
                    rig_name, rig_code, truck_model, registration_number, status,
                    current_rpm, maintenance_rpm_interval, maintenance_due_at_rpm
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['rig_name'],
                $data['rig_code'],
                $data['truck_model'] ?? null,
                $data['registration_number'] ?? null,
                $data['status'] ?? 'active',
                $currentRpm,
                $maintenanceRpmInterval,
                $maintenanceDueAtRpm
            ]);
        } else {
            // Fallback: insert without RPM columns
            $stmt = $this->pdo->prepare("
                INSERT INTO rigs (rig_name, rig_code, truck_model, registration_number, status) 
                VALUES (?, ?, ?, ?, ?)
            ");
            return $stmt->execute([
                $data['rig_name'],
                $data['rig_code'],
                $data['truck_model'] ?? null,
                $data['registration_number'] ?? null,
                $data['status'] ?? 'active'
            ]);
        }
    }
    
    public function updateRig($id, $data) {
        // Check if RPM columns exist
        $hasRpmColumns = false;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM rigs LIKE 'current_rpm'");
            $hasRpmColumns = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            $hasRpmColumns = false;
        }
        
        if ($hasRpmColumns) {
            // Get current rig data to preserve RPM tracking
            $currentStmt = $this->pdo->prepare("SELECT current_rpm, last_maintenance_rpm FROM rigs WHERE id = ?");
            $currentStmt->execute([$id]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            // Handle RPM fields
            $currentRpm = isset($data['current_rpm']) ? floatval($data['current_rpm']) : floatval($current['current_rpm'] ?? 0);
            $maintenanceRpmInterval = isset($data['maintenance_rpm_interval']) ? floatval($data['maintenance_rpm_interval']) : 30.00;
            
            // Recalculate maintenance due if interval changed or if not set
            $maintenanceDueAtRpm = null;
            if (isset($data['maintenance_due_at_rpm'])) {
                $maintenanceDueAtRpm = floatval($data['maintenance_due_at_rpm']);
            } elseif (isset($data['maintenance_rpm_interval']) || !$current) {
                // If interval changed, recalculate from current RPM
                $lastMaintenanceRpm = floatval($current['last_maintenance_rpm'] ?? 0);
                $baseRpm = $lastMaintenanceRpm > 0 ? $lastMaintenanceRpm : $currentRpm;
                $maintenanceDueAtRpm = $baseRpm + $maintenanceRpmInterval;
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE rigs 
                SET rig_name = ?, rig_code = ?, truck_model = ?, registration_number = ?, status = ?,
                    current_rpm = ?, maintenance_rpm_interval = ?,
                    maintenance_due_at_rpm = COALESCE(?, maintenance_due_at_rpm, current_rpm + ?)
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['rig_name'],
                $data['rig_code'],
                $data['truck_model'] ?? null,
                $data['registration_number'] ?? null,
                $data['status'] ?? 'active',
                $currentRpm,
                $maintenanceRpmInterval,
                $maintenanceDueAtRpm,
                $maintenanceRpmInterval,
                $id
            ]);
        } else {
            // Fallback: update without RPM columns
            $stmt = $this->pdo->prepare("
                UPDATE rigs 
                SET rig_name = ?, rig_code = ?, truck_model = ?, registration_number = ?, status = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                $data['rig_name'],
                $data['rig_code'],
                $data['truck_model'] ?? null,
                $data['registration_number'] ?? null,
                $data['status'] ?? 'active',
                $id
            ]);
        }
    }
    
    public function deleteRig($id) {
        // Check if rig is used in reports
        $checkStmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM field_reports WHERE rig_id = ?");
        $checkStmt->execute([$id]);
        $result = $checkStmt->fetch();
        
        if ($result['count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete rig that has been used in reports'];
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM rigs WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }
    
    // Workers CRUD
    public function getWorkers($status = null) {
        $query = "SELECT * FROM workers";
        if ($status) {
            $query .= " WHERE status = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$status]);
        } else {
            $stmt = $this->pdo->query($query);
        }
        return $stmt->fetchAll();
    }
    
    public function getWorkerRoles() {
        // First, ensure worker_roles table exists
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS worker_roles (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    role_name VARCHAR(100) NOT NULL UNIQUE,
                    description TEXT,
                    is_system TINYINT(1) DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Insert default roles if table is empty
            $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM worker_roles");
            if ($stmt->fetch()['count'] == 0) {
                $defaultRoles = [
                    ['Manager', 'Management role'],
                    ['Supervisor', 'Field supervisor role'],
                    ['Driller (Operator)', 'Main drilling operator'],
                    ['Rig Driver', 'Rig truck driver'],
                    ['Rod Boy (General Labourer)', 'General laborer handling rods']
                ];
                
                $insertStmt = $this->pdo->prepare("INSERT INTO worker_roles (role_name, description, is_system) VALUES (?, ?, 1)");
                foreach ($defaultRoles as $role) {
                    $insertStmt->execute([$role[0], $role[1]]);
                }
            }
        } catch (PDOException $e) {
            error_log("Worker roles table creation error: " . $e->getMessage());
        }
        
        // Get all active roles
        $stmt = $this->pdo->query("SELECT role_name, description FROM worker_roles WHERE is_active = 1 ORDER BY role_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllWorkerRoleNames() {
        $this->getWorkerRoles(); // Ensure table exists
        $stmt = $this->pdo->query("SELECT role_name FROM worker_roles WHERE is_active = 1 ORDER BY role_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    public function addWorkerRole($roleName, $description = '') {
        try {
            $this->getWorkerRoles(); // Ensure table exists
            $stmt = $this->pdo->prepare("
                INSERT INTO worker_roles (role_name, description, is_active) 
                VALUES (?, ?, 1)
            ");
            return $stmt->execute([trim($roleName), $description]);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Duplicate entry
                return false;
            }
            error_log("Add worker role error: " . $e->getMessage());
            return false;
        }
    }
    
    public function updateWorkerRole($id, $roleName, $description = '') {
        try {
            // First, get the old role name before updating
            $stmt = $this->pdo->prepare("SELECT role_name FROM worker_roles WHERE id = ?");
            $stmt->execute([$id]);
            $oldRole = $stmt->fetch();
            
            if (!$oldRole) {
                return false;
            }
            
            $oldRoleName = $oldRole['role_name'];
            $newRoleName = trim($roleName);
            
            // Start transaction to ensure data consistency
            $this->pdo->beginTransaction();
            
            try {
                // Update the role name in worker_roles table (allow editing system roles too)
                $stmt = $this->pdo->prepare("
                    UPDATE worker_roles 
                    SET role_name = ?, description = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$newRoleName, $description, $id]);
                
                // If the role name changed, update all references
                if ($oldRoleName !== $newRoleName) {
                    // Update workers table
                    $updateWorkers = $this->pdo->prepare("
                        UPDATE workers 
                        SET role = ? 
                        WHERE role = ?
                    ");
                    $updateWorkers->execute([$newRoleName, $oldRoleName]);
                    $workersUpdated = $updateWorkers->rowCount();
                    
                    // Update payroll_entries table (if it exists)
                    try {
                        $updatePayroll = $this->pdo->prepare("
                            UPDATE payroll_entries 
                            SET role = ? 
                            WHERE role = ?
                        ");
                        $updatePayroll->execute([$newRoleName, $oldRoleName]);
                    } catch (PDOException $e) {
                        // Table might not exist or have different structure, ignore
                        error_log("Could not update payroll_entries: " . $e->getMessage());
                    }
                    
                    // Commit transaction
                    $this->pdo->commit();
                    
                    // Return info about the update
                    return [
                        'success' => true,
                        'workers_updated' => $workersUpdated,
                        'old_role' => $oldRoleName,
                        'new_role' => $newRoleName
                    ];
                } else {
                    // Role name didn't change, just commit the description update
                    $this->pdo->commit();
                    return true;
                }
            } catch (PDOException $e) {
                $this->pdo->rollBack();
                throw $e;
            }
        } catch (PDOException $e) {
            error_log("Update worker role error: " . $e->getMessage());
            return false;
        }
    }
    
    public function deleteWorkerRole($id) {
        // Check if role is used by workers
        $stmt = $this->pdo->prepare("
            SELECT wr.role_name, COUNT(w.id) as worker_count
            FROM worker_roles wr
            LEFT JOIN workers w ON w.role = wr.role_name
            WHERE wr.id = ?
            GROUP BY wr.id, wr.role_name
        ");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result && $result['worker_count'] > 0) {
            return ['success' => false, 'message' => 'Cannot delete role that is assigned to workers'];
        }
        
        $stmt = $this->pdo->prepare("DELETE FROM worker_roles WHERE id = ? AND is_system = 0");
        $stmt->execute([$id]);
        return ['success' => true];
    }
    
    public function toggleWorkerRole($id) {
        $stmt = $this->pdo->prepare("
            UPDATE worker_roles 
            SET is_active = NOT is_active, updated_at = NOW()
            WHERE id = ? AND is_system = 0
        ");
        return $stmt->execute([$id]);
    }
    
    public function getAllWorkerRoles() {
        $this->getWorkerRoles(); // Ensure table exists
        $stmt = $this->pdo->query("
            SELECT 
                wr.*,
                COUNT(w.id) as worker_count
            FROM worker_roles wr
            LEFT JOIN workers w ON w.role = wr.role_name
            GROUP BY wr.id
            ORDER BY wr.is_system DESC, wr.role_name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addWorker($data) {
        // Check if email column exists
        $hasEmail = false;
        $emailValue = null;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
            if ($checkStmt->rowCount() > 0) {
                $hasEmail = true;
                $emailValue = $data['email'] ?? null;
            }
        } catch (PDOException $e) {
            // Email column doesn't exist, ignore
        }
        
        // Build SQL query based on whether email column exists
        if ($hasEmail) {
            $sql = "INSERT INTO workers (worker_name, role, default_rate, contact_number, email, status) VALUES (?, ?, ?, ?, ?, ?)";
            $params = [
                $data['worker_name'],
                $data['role'],
                $data['default_rate'] ?? 0,
                $data['contact_number'] ?? null,
                $emailValue,
                $data['status'] ?? 'active'
            ];
        } else {
            $sql = "INSERT INTO workers (worker_name, role, default_rate, contact_number, status) VALUES (?, ?, ?, ?, ?)";
            $params = [
                $data['worker_name'],
                $data['role'],
                $data['default_rate'] ?? 0,
                $data['contact_number'] ?? null,
                $data['status'] ?? 'active'
            ];
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function updateWorker($id, $data) {
        // Check if email column exists
        $hasEmail = false;
        $emailValue = null;
        try {
            $checkStmt = $this->pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
            if ($checkStmt->rowCount() > 0) {
                $hasEmail = true;
                $emailValue = $data['email'] ?? null;
            }
        } catch (PDOException $e) {
            // Email column doesn't exist, ignore
        }
        
        // Build SQL query based on whether email column exists
        if ($hasEmail) {
            $sql = "UPDATE workers SET worker_name = ?, role = ?, default_rate = ?, contact_number = ?, email = ?, status = ? WHERE id = ?";
            $params = [
                $data['worker_name'],
                $data['role'],
                $data['default_rate'] ?? 0,
                $data['contact_number'] ?? null,
                $emailValue,
                $data['status'] ?? 'active',
                $id
            ];
        } else {
            $sql = "UPDATE workers SET worker_name = ?, role = ?, default_rate = ?, contact_number = ?, status = ? WHERE id = ?";
            $params = [
                $data['worker_name'],
                $data['role'],
                $data['default_rate'] ?? 0,
                $data['contact_number'] ?? null,
                $data['status'] ?? 'active',
                $id
            ];
        }
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteWorker($id) {
        $stmt = $this->pdo->prepare("DELETE FROM workers WHERE id = ?");
        $stmt->execute([$id]);
        return ['success' => true];
    }
    
    // Materials CRUD
    public function getMaterials() {
        return $this->pdo->query("SELECT * FROM materials_inventory")->fetchAll();
    }
    
    public function updateMaterial($materialType, $data) {
        try {
            // Get current material data
            $currentStmt = $this->pdo->prepare("SELECT quantity_received, quantity_used FROM materials_inventory WHERE material_type = ?");
            $currentStmt->execute([$materialType]);
            $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$current) {
                error_log("Material not found: $materialType");
                return false;
            }
            
            // Calculate new values
            $quantityReceived = isset($data['quantity_received']) ? floatval($data['quantity_received']) : floatval($current['quantity_received']);
            $unitCost = isset($data['unit_cost']) ? floatval($data['unit_cost']) : 0;
            $quantityUsed = floatval($current['quantity_used'] ?? 0);
            $quantityRemaining = max(0, $quantityReceived - $quantityUsed);
            $totalValue = $quantityRemaining * $unitCost;
            
            $stmt = $this->pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_received = ?, 
                    unit_cost = ?, 
                    quantity_remaining = ?,
                    total_value = ?,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            $result = $stmt->execute([
                $quantityReceived,
                $unitCost,
                $quantityRemaining,
                $totalValue,
                $materialType
            ]);
            
            if (!$result) {
                error_log("Failed to update material: " . print_r($stmt->errorInfo(), true));
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating material: " . $e->getMessage());
            return false;
        }
    }
    
    // System Config CRUD
    public function getConfig($key = null) {
        if ($key) {
            $stmt = $this->pdo->prepare("SELECT * FROM system_config WHERE config_key = ?");
            $stmt->execute([$key]);
            return $stmt->fetch();
        }
        $stmt = $this->pdo->query("SELECT * FROM system_config ORDER BY config_category, config_key");
        $results = $stmt->fetchAll();
        $config = [];
        foreach ($results as $row) {
            $config[$row['config_key']] = $row['config_value'];
        }
        return $config;
    }
    
    public function setConfig($key, $value, $type = 'string', $category = 'general', $description = '') {
        $stmt = $this->pdo->prepare("
            INSERT INTO system_config (config_key, config_value, config_type, config_category, description) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                config_value = ?,
                config_type = ?,
                config_category = ?,
                description = ?,
                updated_at = NOW()
        ");
        return $stmt->execute([$key, $value, $type, $category, $description, $value, $type, $category, $description]);
    }
    
    public function getRodLengths() {
        $config = $this->getConfig('default_rod_lengths');
        
        // If getConfig returns false or empty, use defaults
        if (!$config || !is_array($config)) {
            return ['3.0', '3.5', '4.0', '4.2', '4.5', '5.0', '5.2', '5.5'];
        }
        
        // getConfig returns the full database row, so extract config_value
        $configValue = $config['config_value'] ?? '';
        
        if (empty($configValue)) {
            return ['3.0', '3.5', '4.0', '4.2', '4.5', '5.0', '5.2', '5.5'];
        }
        
        // If config_value is already an array (shouldn't happen, but be safe)
        if (is_array($configValue)) {
            return $configValue;
        }
        
        // Try to decode as JSON first (if stored as JSON)
        if (is_string($configValue)) {
            $lengths = json_decode($configValue, true);
            // If JSON decode succeeds and returns array, use it
            if (json_last_error() === JSON_ERROR_NONE && is_array($lengths)) {
                return $lengths;
            }
            // Otherwise, treat as comma-separated string
            return array_map('trim', explode(',', $configValue));
        }
        
        // Fallback to defaults
        return ['3.0', '3.5', '4.0', '4.2', '4.5', '5.0', '5.2', '5.5'];
    }
    
    public function setRodLengths($lengths) {
        return $this->setConfig('default_rod_lengths', json_encode($lengths), 'json', 'drilling', 'Available rod lengths');
    }
    
    public function addRodLength($length) {
        try {
            $currentLengths = $this->getRodLengths();
            $length = number_format(floatval($length), 1);
            
            // Check if length already exists
            if (in_array($length, $currentLengths)) {
                return ['success' => false, 'message' => 'Rod length ' . $length . 'm already exists'];
            }
            
            // Add new length
            $currentLengths[] = $length;
            
            // Sort numerically
            usort($currentLengths, function($a, $b) {
                return floatval($a) <=> floatval($b);
            });
            
            // Remove duplicates
            $currentLengths = array_unique($currentLengths);
            
            // Save
            $result = $this->setRodLengths($currentLengths);
            return ['success' => $result, 'message' => $result ? 'Rod length added successfully' : 'Failed to add rod length'];
        } catch (Exception $e) {
            error_log("Add rod length error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error adding rod length: ' . $e->getMessage()];
        }
    }
    
    public function updateRodLength($oldLength, $newLength) {
        try {
            $currentLengths = $this->getRodLengths();
            $oldLength = number_format(floatval($oldLength), 1);
            $newLength = number_format(floatval($newLength), 1);
            
            // Check if old length exists
            $key = array_search($oldLength, $currentLengths);
            if ($key === false) {
                return ['success' => false, 'message' => 'Rod length ' . $oldLength . 'm not found'];
            }
            
            // Check if new length already exists (and it's not the same as old)
            if ($oldLength !== $newLength && in_array($newLength, $currentLengths)) {
                return ['success' => false, 'message' => 'Rod length ' . $newLength . 'm already exists'];
            }
            
            // Update the length
            $currentLengths[$key] = $newLength;
            
            // Sort numerically
            usort($currentLengths, function($a, $b) {
                return floatval($a) <=> floatval($b);
            });
            
            // Remove duplicates
            $currentLengths = array_unique($currentLengths);
            
            // Save
            $result = $this->setRodLengths($currentLengths);
            return ['success' => $result, 'message' => $result ? 'Rod length updated successfully' : 'Failed to update rod length'];
        } catch (Exception $e) {
            error_log("Update rod length error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error updating rod length: ' . $e->getMessage()];
        }
    }
    
    public function deleteRodLength($length) {
        try {
            $currentLengths = $this->getRodLengths();
            $length = number_format(floatval($length), 1);
            
            // Check if length exists
            $key = array_search($length, $currentLengths);
            if ($key === false) {
                return ['success' => false, 'message' => 'Rod length ' . $length . 'm not found'];
            }
            
            // Remove the length
            unset($currentLengths[$key]);
            
            // Re-index array
            $currentLengths = array_values($currentLengths);
            
            // Save
            $result = $this->setRodLengths($currentLengths);
            return ['success' => $result, 'message' => $result ? 'Rod length deleted successfully' : 'Failed to delete rod length'];
        } catch (Exception $e) {
            error_log("Delete rod length error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error deleting rod length: ' . $e->getMessage()];
        }
    }
}

$configManager = new ConfigManager();
?>

