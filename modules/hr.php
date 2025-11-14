<?php
/**
 * HR Management System - Staff, Workers, and Stakeholders
 * Comprehensive Human Resources management for ABBIS
 */
$page_title = 'Human Resources';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('hr.access');

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'dashboard';
$currentUserId = $_SESSION['user_id'];

// Check if HR tables exist (check and handle gracefully)
$hrTablesExist = true;
try {
    // Check if departments table exists
    $pdo->query("SELECT 1 FROM departments LIMIT 1");
    // Check if workers table has email column (added by migration)
    $checkStmt = $pdo->query("SHOW COLUMNS FROM workers LIKE 'email'");
    if ($checkStmt->rowCount() == 0) {
        $hrTablesExist = false;
    }
} catch (PDOException $e) {
    $hrTablesExist = false;
}

// Show warning if tables don't exist, but don't block access
if (!$hrTablesExist) {
    flash('warning', 'HR database tables not fully initialized. Some features may not work. Please run the migration: <code>database/hr_system_migration.sql</code>');
}

// Ensure every worker has a staff identifier before continuing
if ($hrTablesExist) {
    try {
        ensureAllWorkersHaveStaffIdentifiers($pdo);
    } catch (Throwable $e) {
        error_log('Failed to ensure staff identifiers for workers: ' . $e->getMessage());
    }
}

// Initialize variables to prevent undefined variable errors
$workers = [];
$departments = [];
$positions = [];
$managers = [];
$attendanceRecords = [];
$leaveRequests = [];
$leaveTypes = [];
$leaveBalances = [];
$performanceReviews = [];
$users = [];
$trainingRecords = [];
$skills = [];
$stakeholders = [];
$stakeholderCommunications = [];
$recentEmployees = [];
$totalEmployees = 0;
$totalStaff = 0;
$totalWorkers = 0;
$totalDepartments = 0;
$totalPositions = 0;
$totalStakeholders = 0;
$pendingLeave = 0;
$todayAttendance = 0;
$dateFrom = '';
$dateTo = '';
$viewStakeholderId = 0;

// Only proceed with HR operations if tables exist
if (!$hrTablesExist && in_array($action, ['employees', 'departments', 'positions', 'attendance', 'leave', 'performance', 'training', 'stakeholders'])) {
    $action = 'dashboard'; // Redirect to dashboard if tables don't exist
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token');
        redirect('hr.php');
    }
    
    $postAction = $_POST['action'] ?? '';
    
    try {
        switch ($postAction) {
            case 'run_migration':
                // Run HR database migration
                if ($auth->getUserRole() !== ROLE_ADMIN) {
                    throw new Exception('Only administrators can run migrations');
                }
                
                $migrationFile = __DIR__ . '/../database/hr_system_migration.sql';
                if (!file_exists($migrationFile)) {
                    throw new Exception('Migration file not found: ' . $migrationFile);
                }
                
                // Use MySQL command line if available, otherwise execute SQL directly
                $execPath = exec('which mysql 2>/dev/null', $output, $return);
                
                if ($execPath && $return === 0) {
                    // Use MySQL command line (more reliable for complex SQL)
                    // Use constants from database.php config
                    $dbHost = defined('DB_HOST') ? DB_HOST : 'localhost';
                    $dbUser = defined('DB_USER') ? DB_USER : 'root';
                    $dbPass = defined('DB_PASS') ? DB_PASS : '';
                    $dbName = defined('DB_NAME') ? DB_NAME : 'abbis_3_2';
                    
                    $cmd = sprintf(
                        'mysql -h %s -u %s %s %s < %s 2>&1',
                        escapeshellarg($dbHost),
                        escapeshellarg($dbUser),
                        !empty($dbPass) ? '-p' . escapeshellarg($dbPass) : '',
                        escapeshellarg($dbName),
                        escapeshellarg($migrationFile)
                    );
                    
                    exec($cmd, $output, $returnCode);
                    if ($returnCode !== 0) {
                        throw new Exception('Migration failed: ' . implode("\n", $output));
                    }
                    flash('success', 'HR database migration completed successfully!');
                } else {
                    // Fallback: Execute SQL directly via PDO
                    // Read the migration file
                    $sql = file_get_contents($migrationFile);
                    
                    // Remove USE statement (we're already connected to the database)
                    $sql = preg_replace('/USE\s+`?[\w_]+`?\s*;/i', '', $sql);
                    
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $pdo->beginTransaction();
                    try {
                        // Execute SET FOREIGN_KEY_CHECKS first
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                        
                        // Split SQL into statements, preserving PREPARE/EXECUTE blocks
                        $lines = explode("\n", $sql);
                        $statements = [];
                        $current = '';
                        $inPrepareBlock = false;
                        
                        foreach ($lines as $line) {
                            $line = trim($line);
                            
                            // Skip empty lines and pure comments
                            if (empty($line) || preg_match('/^--/', $line)) {
                                continue;
                            }
                            
                            // Detect PREPARE block start
                            if (preg_match('/SET\s+@sql\s*=/i', $line)) {
                                $inPrepareBlock = true;
                            }
                            
                            // Add line to current statement
                            $current .= ($current ? "\n" : '') . $line;
                            
                            // Detect end of PREPARE block (DEALLOCATE PREPARE)
                            if (preg_match('/DEALLOCATE\s+PREPARE/i', $line)) {
                                $inPrepareBlock = false;
                                if (!empty(trim($current))) {
                                    $statements[] = trim($current);
                                }
                                $current = '';
                            } 
                            // If not in PREPARE block and line ends with semicolon
                            elseif (!$inPrepareBlock && substr($line, -1) === ';') {
                                if (!empty(trim($current))) {
                                    $statements[] = trim($current);
                                }
                                $current = '';
                            }
                        }
                        
                        // Add any remaining statement
                        if (!empty(trim($current))) {
                            $statements[] = trim($current);
                        }
                        
                        $executed = 0;
                        $skipped = 0;
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            // Skip very short or empty statements
                            if (empty($statement) || strlen($statement) < 5) {
                                continue;
                            }
                            
                            // Skip SELECT statements (they're just checks)
                            if (preg_match('/^\s*SELECT\s+1/i', $statement)) {
                                $skipped++;
                                continue;
                            }
                            
                            try {
                                $pdo->exec($statement);
                                $executed++;
                            } catch (PDOException $e) {
                                $errorMsg = $e->getMessage();
                                // Skip if table/column already exists (common in migrations)
                                if (strpos($errorMsg, 'already exists') !== false || 
                                    strpos($errorMsg, 'Duplicate') !== false ||
                                    strpos($errorMsg, '1060') !== false ||
                                    strpos($errorMsg, '1061') !== false ||
                                    preg_match('/Duplicate column name/i', $errorMsg) ||
                                    preg_match('/Duplicate key name/i', $errorMsg)) {
                                    $skipped++;
                                    // Log but don't fail
                                    error_log("Migration skipped (already exists): " . substr($errorMsg, 0, 100));
                                } else {
                                    // Re-throw if it's a real error
                                    throw $e;
                                }
                            }
                        }
                        
                        // Re-enable foreign key checks
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        
                        $pdo->commit();
                        flash('success', "HR database migration completed! ($executed statements executed" . ($skipped > 0 ? ", $skipped skipped" : "") . ")");
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        // Re-enable foreign key checks even on error
                        try {
                            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        } catch (PDOException $e2) {}
                        throw new Exception('Migration error: ' . $e->getMessage());
                    }
                }
                
                redirect('hr.php?action=dashboard');
                break;
                
            case 'add_employee':
                // Add new employee/worker
                $employeeCodeInput = sanitizeArray($_POST['employee_code'] ?? '');
                $workerName = sanitizeArray($_POST['worker_name'] ?? '');
                $role = sanitizeArray($_POST['role'] ?? '');
                $employeeType = sanitizeArray($_POST['employee_type'] ?? 'worker');
                $defaultRate = floatval($_POST['default_rate'] ?? 0);
                $contactNumber = sanitizeArray($_POST['contact_number'] ?? '');
                $email = sanitizeArray($_POST['email'] ?? '');
                $departmentId = intval($_POST['department_id'] ?? 0) ?: null;
                $positionId = intval($_POST['position_id'] ?? 0) ?: null;
                $hireDate = sanitizeArray($_POST['hire_date'] ?? null) ?: null;
                
                if (empty($workerName)) {
                    throw new Exception('Worker name is required');
                }
                
                // Ensure a unique staff identifier exists
                if ($employeeCodeInput === '') {
                    $employeeCode = generateStaffIdentifier($pdo);
                } else {
                    $employeeCode = $employeeCodeInput;
                    $duplicateCheck = $pdo->prepare("SELECT id FROM workers WHERE employee_code = ? LIMIT 1");
                    $duplicateCheck->execute([$employeeCode]);
                    if ($duplicateCheck->fetchColumn()) {
                        throw new Exception('The staff ID ' . e($employeeCode) . ' is already assigned to another worker.');
                    }
                }
                
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO workers (employee_code, worker_name, role, default_rate, contact_number, email, 
                                            employee_type, department_id, position_id, hire_date, status) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([$employeeCode, $workerName, $role ?: '', $defaultRate, $contactNumber, $email, 
                                   $employeeType, $departmentId, $positionId, $hireDate]);
                    
                    $workerId = $pdo->lastInsertId();
                    
                    // Create initial role assignment if role is provided
                    if (!empty($role)) {
                        try {
                            $roleStmt = $pdo->prepare("
                                INSERT INTO worker_role_assignments (worker_id, role_name, is_primary, default_rate)
                                VALUES (?, ?, 1, ?)
                                ON DUPLICATE KEY UPDATE default_rate = VALUES(default_rate)
                            ");
                            $roleStmt->execute([$workerId, $role, $defaultRate > 0 ? $defaultRate : null]);
                        } catch (PDOException $e) {
                            // Table might not exist yet, log but don't fail
                            error_log("Could not create role assignment: " . $e->getMessage());
                        }
                    }
                    
                    // Double-check that a staff identifier exists (safety for legacy DBs)
                    ensureWorkerHasStaffIdentifier($pdo, (int) $workerId);
                    
                    $pdo->commit();
                    flash('success', 'Employee added successfully. Staff ID: ' . e($employeeCode));
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                
                redirect('hr.php?action=employees');
                break;
                
            case 'update_employee':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $workerName = sanitizeArray($_POST['worker_name'] ?? '');
                $role = sanitizeArray($_POST['role'] ?? '');
                $employeeType = sanitizeArray($_POST['employee_type'] ?? 'worker');
                $defaultRate = floatval($_POST['default_rate'] ?? 0);
                $contactNumber = sanitizeArray($_POST['contact_number'] ?? '');
                $email = sanitizeArray($_POST['email'] ?? '');
                $status = sanitizeArray($_POST['status'] ?? 'active');
                $departmentId = intval($_POST['department_id'] ?? 0) ?: null;
                $positionId = intval($_POST['position_id'] ?? 0) ?: null;
                $managerId = intval($_POST['manager_id'] ?? 0) ?: null;
                $employeeCodeInput = sanitizeArray($_POST['employee_code'] ?? '');
                
                if ($workerId <= 0) {
                    throw new Exception('Invalid worker ID');
                }

                if ($employeeCodeInput !== '') {
                    $duplicateCheck = $pdo->prepare("SELECT id FROM workers WHERE employee_code = ? AND id != ? LIMIT 1");
                    $duplicateCheck->execute([$employeeCodeInput, $workerId]);
                    if ($duplicateCheck->fetchColumn()) {
                        throw new Exception('The staff ID ' . e($employeeCodeInput) . ' is already assigned to another worker.');
                    }
                }
                
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("
                        UPDATE workers 
                        SET worker_name = ?, role = ?, default_rate = ?, contact_number = ?, email = ?, 
                            employee_type = ?, status = ?, department_id = ?, position_id = ?, manager_id = ?,
                            employee_code = COALESCE(NULLIF(?, ''), employee_code),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$workerName, $role, $defaultRate, $contactNumber, $email, 
                                   $employeeType, $status, $departmentId, $positionId, $managerId, $employeeCodeInput, $workerId]);
                    
                    // Guarantee a staff identifier exists after the update
                    ensureWorkerHasStaffIdentifier($pdo, $workerId);
                    
                    // Update primary role assignment if role changed
                    if (!empty($role)) {
                        try {
                            // Check if role assignment exists
                            $checkStmt = $pdo->prepare("SELECT id FROM worker_role_assignments WHERE worker_id = ? AND role_name = ?");
                            $checkStmt->execute([$workerId, $role]);
                            
                            if ($checkStmt->fetch()) {
                                // Update existing assignment to primary
                                $updateStmt = $pdo->prepare("
                                    UPDATE worker_role_assignments 
                                    SET is_primary = 1, default_rate = ?, updated_at = NOW()
                                    WHERE worker_id = ? AND role_name = ?
                                ");
                                $updateStmt->execute([$defaultRate > 0 ? $defaultRate : null, $workerId, $role]);
                                
                                // Unset other primary roles
                                $unsetStmt = $pdo->prepare("
                                    UPDATE worker_role_assignments 
                                    SET is_primary = 0 
                                    WHERE worker_id = ? AND role_name != ?
                                ");
                                $unsetStmt->execute([$workerId, $role]);
                            } else {
                                // Create new role assignment as primary
                                $createStmt = $pdo->prepare("
                                    INSERT INTO worker_role_assignments (worker_id, role_name, is_primary, default_rate)
                                    VALUES (?, ?, 1, ?)
                                ");
                                $createStmt->execute([$workerId, $role, $defaultRate > 0 ? $defaultRate : null]);
                                
                                // Unset other primary roles
                                $unsetStmt = $pdo->prepare("
                                    UPDATE worker_role_assignments 
                                    SET is_primary = 0 
                                    WHERE worker_id = ? AND role_name != ?
                                ");
                                $unsetStmt->execute([$workerId, $role]);
                            }
                        } catch (PDOException $e) {
                            // Table might not exist yet, log but don't fail
                            error_log("Could not update role assignment: " . $e->getMessage());
                        }
                    }
                    
                    $pdo->commit();
                    flash('success', 'Employee updated successfully');
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                
                redirect('hr.php?action=employees');
                break;
                
            case 'delete_employee':
                $workerId = intval($_POST['worker_id'] ?? 0);
                
                if ($workerId <= 0) {
                    throw new Exception('Invalid worker ID');
                }
                
                // Check if worker is used in other tables
                $checkStmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM payroll_entries WHERE worker_id = ?) as payroll_count,
                        (SELECT COUNT(*) FROM loans WHERE worker_id = ?) as loans_count,
                        (SELECT COUNT(*) FROM field_reports WHERE supervisor_id = ?) as reports_count
                ");
                $checkStmt->execute([$workerId, $workerId, $workerId]);
                $usage = $checkStmt->fetch();
                
                if ($usage['payroll_count'] > 0 || $usage['loans_count'] > 0 || $usage['reports_count'] > 0) {
                    // Don't delete, just deactivate
                    $stmt = $pdo->prepare("UPDATE workers SET status = 'inactive', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$workerId]);
                    flash('warning', 'Employee cannot be deleted as they have records in the system. Status changed to inactive.');
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM workers WHERE id = ?");
                    $stmt->execute([$workerId]);
                    flash('success', 'Employee deleted successfully');
                }
                
                redirect('hr.php?action=employees');
                break;
                
            case 'add_worker_role':
                // Add worker role (from HR module)
                if (!file_exists(__DIR__ . '/../includes/config-manager.php')) {
                    throw new Exception('Config manager not available');
                }
                require_once __DIR__ . '/../includes/config-manager.php';
                if (!class_exists('ConfigManager')) {
                    throw new Exception('ConfigManager class not found');
                }
                $configManager = new ConfigManager();
                
                $roleName = sanitizeArray($_POST['role_name'] ?? '');
                $description = sanitizeArray($_POST['description'] ?? '');
                if (empty($roleName)) {
                    throw new Exception('Role name is required');
                }
                $result = $configManager->addWorkerRole($roleName, $description);
                if ($result) {
                    flash('success', 'Role added successfully');
                } else {
                    flash('error', 'Role already exists or failed to add');
                }
                redirect('hr.php?action=roles');
                break;
                
            case 'update_worker_role':
                // Update worker role (from HR module)
                if (!file_exists(__DIR__ . '/../includes/config-manager.php')) {
                    throw new Exception('Config manager not available');
                }
                require_once __DIR__ . '/../includes/config-manager.php';
                if (!class_exists('ConfigManager')) {
                    throw new Exception('ConfigManager class not found');
                }
                $configManager = new ConfigManager();
                
                $id = intval($_POST['id'] ?? 0);
                $roleName = sanitizeArray($_POST['role_name'] ?? '');
                $description = sanitizeArray($_POST['description'] ?? '');
                if (empty($roleName)) {
                    throw new Exception('Role name is required');
                }
                $result = $configManager->updateWorkerRole($id, $roleName, $description);
                if (is_array($result) && isset($result['success'])) {
                    $message = 'Role updated successfully';
                    if ($result['workers_updated'] > 0) {
                        $message .= ". {$result['workers_updated']} worker(s) updated from '{$result['old_role']}' to '{$result['new_role']}'";
                    }
                    flash('success', $message);
                } else if ($result === true) {
                    flash('success', 'Role updated successfully');
                } else {
                    flash('error', 'Failed to update role');
                }
                redirect('hr.php?action=roles');
                break;
                
            case 'delete_worker_role':
                // Delete worker role (from HR module)
                if (!file_exists(__DIR__ . '/../includes/config-manager.php')) {
                    throw new Exception('Config manager not available');
                }
                require_once __DIR__ . '/../includes/config-manager.php';
                if (!class_exists('ConfigManager')) {
                    throw new Exception('ConfigManager class not found');
                }
                $configManager = new ConfigManager();
                
                $id = intval($_POST['id'] ?? 0);
                $result = $configManager->deleteWorkerRole($id);
                if (is_array($result)) {
                    if ($result['success']) {
                        flash('success', 'Role deleted successfully');
                    } else {
                        flash('error', $result['message'] ?? 'Failed to delete role');
                    }
                } else {
                    flash($result ? 'success' : 'error', $result ? 'Role deleted successfully' : 'Failed to delete role');
                }
                redirect('hr.php?action=roles');
                break;
                
            case 'toggle_worker_role':
                // Toggle worker role status (from HR module)
                if (!file_exists(__DIR__ . '/../includes/config-manager.php')) {
                    throw new Exception('Config manager not available');
                }
                require_once __DIR__ . '/../includes/config-manager.php';
                if (!class_exists('ConfigManager')) {
                    throw new Exception('ConfigManager class not found');
                }
                $configManager = new ConfigManager();
                
                $id = intval($_POST['id'] ?? 0);
                $result = $configManager->toggleWorkerRole($id);
                if ($result) {
                    flash('success', 'Role status updated');
                } else {
                    flash('error', 'Failed to update role status');
                }
                redirect('hr.php?action=roles');
                break;
                
            case 'add_department':
                $deptCode = sanitizeArray($_POST['department_code'] ?? '');
                $deptName = sanitizeArray($_POST['department_name'] ?? '');
                $description = sanitizeArray($_POST['description'] ?? '');
                $managerId = intval($_POST['manager_id'] ?? 0) ?: null;
                
                if (empty($deptCode) || empty($deptName)) {
                    throw new Exception('Department code and name are required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO departments (department_code, department_name, description, manager_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$deptCode, $deptName, $description, $managerId]);
                
                flash('success', 'Department added successfully');
                redirect('hr.php?action=departments');
                break;
                
            case 'update_department':
                $deptId = intval($_POST['id'] ?? 0);
                $deptCode = sanitizeArray($_POST['department_code'] ?? '');
                $deptName = sanitizeArray($_POST['department_name'] ?? '');
                $description = sanitizeArray($_POST['description'] ?? '');
                $managerId = intval($_POST['manager_id'] ?? 0) ?: null;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($deptId <= 0) {
                    throw new Exception('Invalid department ID');
                }
                
                if (empty($deptCode) || empty($deptName)) {
                    throw new Exception('Department code and name are required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE departments 
                    SET department_code = ?, department_name = ?, description = ?, manager_id = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$deptCode, $deptName, $description, $managerId, $isActive, $deptId]);
                
                flash('success', 'Department updated successfully');
                redirect('hr.php?action=departments');
                break;
                
            case 'delete_department':
                $deptId = intval($_POST['id'] ?? 0);
                
                if ($deptId <= 0) {
                    throw new Exception('Invalid department ID');
                }
                
                // Check if department is used by any workers
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM workers WHERE department_id = ?");
                $checkStmt->execute([$deptId]);
                $result = $checkStmt->fetch();
                
                if ($result['count'] > 0) {
                    // Can't delete, but can deactivate
                    $stmt = $pdo->prepare("UPDATE departments SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$deptId]);
                    flash('warning', 'Department cannot be deleted as it has employees. Status changed to inactive.');
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$deptId]);
                    flash('success', 'Department deleted successfully');
                }
                
                redirect('hr.php?action=departments');
                break;
                
            case 'add_position':
                $posCode = sanitizeArray($_POST['position_code'] ?? '');
                $posTitle = sanitizeArray($_POST['position_title'] ?? '');
                $departmentId = intval($_POST['department_id'] ?? 0) ?: null;
                $description = sanitizeArray($_POST['description'] ?? '');
                $minSalary = floatval($_POST['min_salary'] ?? 0);
                $maxSalary = floatval($_POST['max_salary'] ?? 0);
                
                if (empty($posCode) || empty($posTitle)) {
                    throw new Exception('Position code and title are required');
                }
                
                // Check if position code already exists
                $checkStmt = $pdo->prepare("SELECT id FROM positions WHERE position_code = ?");
                $checkStmt->execute([$posCode]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Position code already exists');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO positions (position_code, position_title, department_id, description, min_salary, max_salary) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$posCode, $posTitle, $departmentId, $description, $minSalary, $maxSalary]);
                
                flash('success', 'Position added successfully');
                redirect('hr.php?action=positions');
                break;
                
            case 'update_position':
                $positionId = intval($_POST['id'] ?? 0);
                $posCode = sanitizeArray($_POST['position_code'] ?? '');
                $posTitle = sanitizeArray($_POST['position_title'] ?? '');
                $departmentId = intval($_POST['department_id'] ?? 0) ?: null;
                $description = sanitizeArray($_POST['description'] ?? '');
                $minSalary = floatval($_POST['min_salary'] ?? 0);
                $maxSalary = floatval($_POST['max_salary'] ?? 0);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($positionId <= 0) {
                    throw new Exception('Invalid position ID');
                }
                
                if (empty($posCode) || empty($posTitle)) {
                    throw new Exception('Position code and title are required');
                }
                
                // Check if position code already exists for another position
                $checkStmt = $pdo->prepare("SELECT id FROM positions WHERE position_code = ? AND id != ?");
                $checkStmt->execute([$posCode, $positionId]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Position code already exists');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE positions 
                    SET position_code = ?, position_title = ?, department_id = ?, description = ?, 
                        min_salary = ?, max_salary = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$posCode, $posTitle, $departmentId, $description, $minSalary, $maxSalary, $isActive, $positionId]);
                
                flash('success', 'Position updated successfully');
                redirect('hr.php?action=positions');
                break;
                
            case 'delete_position':
                $positionId = intval($_POST['id'] ?? 0);
                
                if ($positionId <= 0) {
                    throw new Exception('Invalid position ID');
                }
                
                // Check if position is used by any workers
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM workers WHERE position_id = ?");
                $checkStmt->execute([$positionId]);
                $result = $checkStmt->fetch();
                
                if ($result['count'] > 0) {
                    // Can't delete, but can deactivate
                    $stmt = $pdo->prepare("UPDATE positions SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$positionId]);
                    flash('warning', 'Position cannot be deleted as it is assigned to employees. Status changed to inactive.');
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM positions WHERE id = ?");
                    $stmt->execute([$positionId]);
                    flash('success', 'Position deleted successfully');
                }
                
                redirect('hr.php?action=positions');
                break;
                
            case 'add_stakeholder':
                $stakeholderCode = sanitizeArray($_POST['stakeholder_code'] ?? '');
                $stakeholderType = sanitizeArray($_POST['stakeholder_type'] ?? '');
                $fullName = sanitizeArray($_POST['full_name'] ?? '');
                $organization = sanitizeArray($_POST['organization'] ?? '');
                $email = sanitizeArray($_POST['email'] ?? '');
                $phone = sanitizeArray($_POST['phone'] ?? '');
                
                if (empty($stakeholderCode) || empty($fullName) || empty($stakeholderType)) {
                    throw new Exception('Stakeholder code, name, and type are required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO stakeholders (stakeholder_code, stakeholder_type, full_name, organization, email, phone, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$stakeholderCode, $stakeholderType, $fullName, $organization, $email, $phone, $currentUserId]);
                
                flash('success', 'Stakeholder added successfully');
                redirect('hr.php?action=stakeholders');
                break;
                
            case 'update_stakeholder':
                $stakeholderId = intval($_POST['id'] ?? 0);
                $stakeholderCode = sanitizeArray($_POST['stakeholder_code'] ?? '');
                $stakeholderType = sanitizeArray($_POST['stakeholder_type'] ?? '');
                $fullName = sanitizeArray($_POST['full_name'] ?? '');
                $organization = sanitizeArray($_POST['organization'] ?? '');
                $email = sanitizeArray($_POST['email'] ?? '');
                $phone = sanitizeArray($_POST['phone'] ?? '');
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                
                if ($stakeholderId <= 0) {
                    throw new Exception('Invalid stakeholder ID');
                }
                
                if (empty($stakeholderCode) || empty($fullName) || empty($stakeholderType)) {
                    throw new Exception('Stakeholder code, name, and type are required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE stakeholders 
                    SET stakeholder_code = ?, stakeholder_type = ?, full_name = ?, organization = ?, 
                        email = ?, phone = ?, is_active = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$stakeholderCode, $stakeholderType, $fullName, $organization, $email, $phone, $isActive, $stakeholderId]);
                
                flash('success', 'Stakeholder updated successfully');
                redirect('hr.php?action=stakeholders');
                break;
                
            case 'delete_stakeholder':
                $stakeholderId = intval($_POST['id'] ?? 0);
                
                if ($stakeholderId <= 0) {
                    throw new Exception('Invalid stakeholder ID');
                }
                
                // Check if stakeholder has communications
                $checkStmt = $pdo->prepare("SELECT COUNT(*) as count FROM stakeholder_communications WHERE stakeholder_id = ?");
                $checkStmt->execute([$stakeholderId]);
                $result = $checkStmt->fetch();
                
                if ($result['count'] > 0) {
                    // Can't delete, but can deactivate
                    $stmt = $pdo->prepare("UPDATE stakeholders SET is_active = 0 WHERE id = ?");
                    $stmt->execute([$stakeholderId]);
                    flash('warning', 'Stakeholder cannot be deleted as it has communications. Status changed to inactive.');
                } else {
                    // Safe to delete
                    $stmt = $pdo->prepare("DELETE FROM stakeholders WHERE id = ?");
                    $stmt->execute([$stakeholderId]);
                    flash('success', 'Stakeholder deleted successfully');
                }
                
                redirect('hr.php?action=stakeholders');
                break;
                
            case 'record_attendance':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $attendanceDate = sanitizeArray($_POST['attendance_date'] ?? date('Y-m-d'));
                $timeIn = sanitizeArray($_POST['time_in'] ?? null) ?: null;
                $timeOut = sanitizeArray($_POST['time_out'] ?? null) ?: null;
                $attendanceStatus = sanitizeArray($_POST['attendance_status'] ?? 'present');
                $notes = sanitizeArray($_POST['notes'] ?? '');
                
                if ($workerId <= 0) {
                    throw new Exception('Worker is required');
                }
                
                // Calculate hours if time in/out provided
                $totalHours = 0;
                $overtimeHours = 0;
                if ($timeIn && $timeOut) {
                    $start = strtotime($timeIn);
                    $end = strtotime($timeOut);
                    $totalHours = ($end - $start) / 3600;
                    if ($totalHours > 8) {
                        $overtimeHours = $totalHours - 8;
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_records (worker_id, attendance_date, time_in, time_out, total_hours, 
                                                    overtime_hours, attendance_status, notes, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        time_in = VALUES(time_in),
                        time_out = VALUES(time_out),
                        total_hours = VALUES(total_hours),
                        overtime_hours = VALUES(overtime_hours),
                        attendance_status = VALUES(attendance_status),
                        notes = VALUES(notes),
                        updated_at = NOW()
                ");
                $stmt->execute([$workerId, $attendanceDate, $timeIn, $timeOut, $totalHours, $overtimeHours, $attendanceStatus, $notes, $currentUserId]);
                
                flash('success', 'Attendance recorded successfully');
                redirect('hr.php?action=attendance');
                break;
                
            case 'update_attendance':
                $attendanceId = intval($_POST['id'] ?? 0);
                $workerId = intval($_POST['worker_id'] ?? 0);
                $attendanceDate = sanitizeArray($_POST['attendance_date'] ?? date('Y-m-d'));
                $timeIn = sanitizeArray($_POST['time_in'] ?? null) ?: null;
                $timeOut = sanitizeArray($_POST['time_out'] ?? null) ?: null;
                $attendanceStatus = sanitizeArray($_POST['attendance_status'] ?? 'present');
                $notes = sanitizeArray($_POST['notes'] ?? '');
                
                if ($attendanceId <= 0 || $workerId <= 0) {
                    throw new Exception('Invalid attendance record');
                }
                
                // Calculate hours if time in/out provided
                $totalHours = 0;
                $overtimeHours = 0;
                if ($timeIn && $timeOut) {
                    $start = strtotime($timeIn);
                    $end = strtotime($timeOut);
                    $totalHours = ($end - $start) / 3600;
                    if ($totalHours > 8) {
                        $overtimeHours = $totalHours - 8;
                    }
                }
                
                $stmt = $pdo->prepare("
                    UPDATE attendance_records 
                    SET worker_id = ?, attendance_date = ?, time_in = ?, time_out = ?, 
                        total_hours = ?, overtime_hours = ?, attendance_status = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$workerId, $attendanceDate, $timeIn, $timeOut, $totalHours, $overtimeHours, $attendanceStatus, $notes, $attendanceId]);
                
                flash('success', 'Attendance updated successfully');
                redirect('hr.php?action=attendance');
                break;
                
            case 'delete_attendance':
                $attendanceId = intval($_POST['id'] ?? 0);
                
                if ($attendanceId <= 0) {
                    throw new Exception('Invalid attendance record');
                }
                
                $stmt = $pdo->prepare("DELETE FROM attendance_records WHERE id = ?");
                $stmt->execute([$attendanceId]);
                
                flash('success', 'Attendance record deleted successfully');
                redirect('hr.php?action=attendance');
                break;
                
            case 'add_leave_request':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $leaveTypeId = intval($_POST['leave_type_id'] ?? 0);
                $startDate = sanitizeArray($_POST['start_date'] ?? '');
                $endDate = sanitizeArray($_POST['end_date'] ?? '');
                $reason = sanitizeArray($_POST['reason'] ?? '');
                
                if ($workerId <= 0 || $leaveTypeId <= 0 || empty($startDate) || empty($endDate)) {
                    throw new Exception('Worker, leave type, start date, and end date are required');
                }
                
                // Calculate total days
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $totalDays = $start->diff($end)->days + 1;
                
                // Generate request code
                $requestCode = 'LV-' . date('Ymd') . '-' . str_pad($workerId, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                $stmt = $pdo->prepare("
                    INSERT INTO leave_requests (request_code, worker_id, leave_type_id, start_date, end_date, total_days, reason, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$requestCode, $workerId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason, $currentUserId]);
                
                flash('success', 'Leave request submitted successfully');
                redirect('hr.php?action=leave');
                break;
                
            case 'approve_leave':
                $requestId = intval($_POST['request_id'] ?? 0);
                $approved = ($_POST['approved'] ?? '') === '1';
                $rejectionReason = sanitizeArray($_POST['rejection_reason'] ?? '');
                
                if ($requestId <= 0) {
                    throw new Exception('Invalid leave request');
                }
                
                if ($approved) {
                    $stmt = $pdo->prepare("
                        UPDATE leave_requests 
                        SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$currentUserId, $requestId]);
                    
                    // Update leave balance
                    $request = $pdo->prepare("SELECT worker_id, leave_type_id, total_days FROM leave_requests WHERE id = ?");
                    $request->execute([$requestId]);
                    $req = $request->fetch();
                    
                    if ($req) {
                        $year = date('Y');
                        $balanceStmt = $pdo->prepare("
                            INSERT INTO leave_balances (worker_id, leave_type_id, year, used_days, remaining_days)
                            VALUES (?, ?, ?, ?, 0)
                            ON DUPLICATE KEY UPDATE
                                used_days = used_days + ?,
                                remaining_days = remaining_days - ?,
                                updated_at = NOW()
                        ");
                        $balanceStmt->execute([$req['worker_id'], $req['leave_type_id'], $year, $req['total_days'], $req['total_days'], $req['total_days']]);
                    }
                    
                    flash('success', 'Leave request approved');
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE leave_requests 
                        SET status = 'rejected', rejection_reason = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$rejectionReason, $requestId]);
                    flash('success', 'Leave request rejected');
                }
                
                redirect('hr.php?action=leave');
                break;
                
            case 'update_leave_request':
                $requestId = intval($_POST['id'] ?? 0);
                $workerId = intval($_POST['worker_id'] ?? 0);
                $leaveTypeId = intval($_POST['leave_type_id'] ?? 0);
                $startDate = sanitizeArray($_POST['start_date'] ?? '');
                $endDate = sanitizeArray($_POST['end_date'] ?? '');
                $reason = sanitizeArray($_POST['reason'] ?? '');
                
                if ($requestId <= 0 || $workerId <= 0 || $leaveTypeId <= 0 || empty($startDate) || empty($endDate)) {
                    throw new Exception('All fields are required');
                }
                
                // Calculate total days
                $start = new DateTime($startDate);
                $end = new DateTime($endDate);
                $totalDays = $start->diff($end)->days + 1;
                
                $stmt = $pdo->prepare("
                    UPDATE leave_requests 
                    SET worker_id = ?, leave_type_id = ?, start_date = ?, end_date = ?, 
                        total_days = ?, reason = ?, updated_at = NOW()
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$workerId, $leaveTypeId, $startDate, $endDate, $totalDays, $reason, $requestId]);
                
                if ($stmt->rowCount() > 0) {
                    flash('success', 'Leave request updated successfully');
                } else {
                    flash('warning', 'Leave request cannot be updated as it is already approved/rejected');
                }
                
                redirect('hr.php?action=leave');
                break;
                
            case 'delete_leave_request':
                $requestId = intval($_POST['id'] ?? 0);
                
                if ($requestId <= 0) {
                    throw new Exception('Invalid leave request');
                }
                
                // Only allow deletion of pending requests
                $stmt = $pdo->prepare("DELETE FROM leave_requests WHERE id = ? AND status = 'pending'");
                $stmt->execute([$requestId]);
                
                if ($stmt->rowCount() > 0) {
                    flash('success', 'Leave request deleted successfully');
                } else {
                    flash('warning', 'Only pending leave requests can be deleted');
                }
                
                redirect('hr.php?action=leave');
                break;
                
            case 'add_performance_review':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $reviewPeriodStart = sanitizeArray($_POST['review_period_start'] ?? '');
                $reviewPeriodEnd = sanitizeArray($_POST['review_period_end'] ?? '');
                $reviewType = sanitizeArray($_POST['review_type'] ?? 'annual');
                $reviewerId = intval($_POST['reviewer_id'] ?? $currentUserId);
                $overallRating = floatval($_POST['overall_rating'] ?? 0);
                $strengths = sanitizeArray($_POST['strengths'] ?? '');
                $areasForImprovement = sanitizeArray($_POST['areas_for_improvement'] ?? '');
                $goals = sanitizeArray($_POST['goals'] ?? '');
                $recommendations = sanitizeArray($_POST['recommendations'] ?? '');
                
                if ($workerId <= 0 || empty($reviewPeriodStart) || empty($reviewPeriodEnd)) {
                    throw new Exception('Worker, review period start, and end dates are required');
                }
                
                $reviewCode = 'PR-' . date('Ymd') . '-' . str_pad($workerId, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                $stmt = $pdo->prepare("
                    INSERT INTO performance_reviews (review_code, worker_id, review_period_start, review_period_end, 
                                                    review_type, reviewer_id, overall_rating, strengths, 
                                                    areas_for_improvement, goals, recommendations, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$reviewCode, $workerId, $reviewPeriodStart, $reviewPeriodEnd, $reviewType, 
                               $reviewerId, $overallRating, $strengths, $areasForImprovement, $goals, $recommendations, $currentUserId]);
                
                flash('success', 'Performance review created successfully');
                redirect('hr.php?action=performance');
                break;
                
            case 'update_performance_review':
                $reviewId = intval($_POST['id'] ?? 0);
                $workerId = intval($_POST['worker_id'] ?? 0);
                $reviewPeriodStart = sanitizeArray($_POST['review_period_start'] ?? '');
                $reviewPeriodEnd = sanitizeArray($_POST['review_period_end'] ?? '');
                $reviewType = sanitizeArray($_POST['review_type'] ?? 'annual');
                $reviewerId = intval($_POST['reviewer_id'] ?? $currentUserId);
                $overallRating = floatval($_POST['overall_rating'] ?? 0);
                $strengths = sanitizeArray($_POST['strengths'] ?? '');
                $areasForImprovement = sanitizeArray($_POST['areas_for_improvement'] ?? '');
                $goals = sanitizeArray($_POST['goals'] ?? '');
                $recommendations = sanitizeArray($_POST['recommendations'] ?? '');
                
                if ($reviewId <= 0 || $workerId <= 0 || empty($reviewPeriodStart) || empty($reviewPeriodEnd)) {
                    throw new Exception('All required fields must be provided');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE performance_reviews 
                    SET worker_id = ?, review_period_start = ?, review_period_end = ?, 
                        review_type = ?, reviewer_id = ?, overall_rating = ?, strengths = ?, 
                        areas_for_improvement = ?, goals = ?, recommendations = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$workerId, $reviewPeriodStart, $reviewPeriodEnd, $reviewType, 
                               $reviewerId, $overallRating, $strengths, $areasForImprovement, $goals, $recommendations, $reviewId]);
                
                flash('success', 'Performance review updated successfully');
                redirect('hr.php?action=performance');
                break;
                
            case 'delete_performance_review':
                $reviewId = intval($_POST['id'] ?? 0);
                
                if ($reviewId <= 0) {
                    throw new Exception('Invalid performance review');
                }
                
                $stmt = $pdo->prepare("DELETE FROM performance_reviews WHERE id = ?");
                $stmt->execute([$reviewId]);
                
                flash('success', 'Performance review deleted successfully');
                redirect('hr.php?action=performance');
                break;
                
            case 'add_training':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $trainingTitle = sanitizeArray($_POST['training_title'] ?? '');
                $trainingType = sanitizeArray($_POST['training_type'] ?? 'internal');
                $provider = sanitizeArray($_POST['provider'] ?? '');
                $startDate = sanitizeArray($_POST['start_date'] ?? '');
                $endDate = sanitizeArray($_POST['end_date'] ?? null) ?: null;
                $durationHours = floatval($_POST['duration_hours'] ?? 0);
                $cost = floatval($_POST['cost'] ?? 0);
                $certificateNumber = sanitizeArray($_POST['certificate_number'] ?? '');
                $certificateExpiry = sanitizeArray($_POST['certificate_expiry'] ?? null) ?: null;
                
                if ($workerId <= 0 || empty($trainingTitle) || empty($startDate)) {
                    throw new Exception('Worker, training title, and start date are required');
                }
                
                $trainingCode = 'TR-' . date('Ymd') . '-' . str_pad($workerId, 4, '0', STR_PAD_LEFT) . '-' . rand(100, 999);
                
                $stmt = $pdo->prepare("
                    INSERT INTO training_records (training_code, worker_id, training_title, training_type, provider,
                                                 start_date, end_date, duration_hours, cost, certificate_number,
                                                 certificate_expiry, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$trainingCode, $workerId, $trainingTitle, $trainingType, $provider, $startDate, 
                               $endDate, $durationHours, $cost, $certificateNumber, $certificateExpiry, $currentUserId]);
                
                flash('success', 'Training record added successfully');
                redirect('hr.php?action=training');
                break;
                
            case 'update_training':
                $trainingId = intval($_POST['id'] ?? 0);
                $workerId = intval($_POST['worker_id'] ?? 0);
                $trainingTitle = sanitizeArray($_POST['training_title'] ?? '');
                $trainingType = sanitizeArray($_POST['training_type'] ?? 'internal');
                $provider = sanitizeArray($_POST['provider'] ?? '');
                $startDate = sanitizeArray($_POST['start_date'] ?? '');
                $endDate = sanitizeArray($_POST['end_date'] ?? null) ?: null;
                $durationHours = floatval($_POST['duration_hours'] ?? 0);
                $cost = floatval($_POST['cost'] ?? 0);
                $certificateNumber = sanitizeArray($_POST['certificate_number'] ?? '');
                $certificateExpiry = sanitizeArray($_POST['certificate_expiry'] ?? null) ?: null;
                $status = sanitizeArray($_POST['status'] ?? 'pending');
                
                if ($trainingId <= 0 || $workerId <= 0 || empty($trainingTitle) || empty($startDate)) {
                    throw new Exception('All required fields must be provided');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE training_records 
                    SET worker_id = ?, training_title = ?, training_type = ?, provider = ?,
                        start_date = ?, end_date = ?, duration_hours = ?, cost = ?, 
                        certificate_number = ?, certificate_expiry = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$workerId, $trainingTitle, $trainingType, $provider, $startDate, 
                               $endDate, $durationHours, $cost, $certificateNumber, $certificateExpiry, $status, $trainingId]);
                
                flash('success', 'Training record updated successfully');
                redirect('hr.php?action=training');
                break;
                
            case 'delete_training':
                $trainingId = intval($_POST['id'] ?? 0);
                
                if ($trainingId <= 0) {
                    throw new Exception('Invalid training record');
                }
                
                $stmt = $pdo->prepare("DELETE FROM training_records WHERE id = ?");
                $stmt->execute([$trainingId]);
                
                flash('success', 'Training record deleted successfully');
                redirect('hr.php?action=training');
                break;
                
            case 'add_stakeholder_communication':
                $stakeholderId = intval($_POST['stakeholder_id'] ?? 0);
                $communicationType = sanitizeArray($_POST['communication_type'] ?? '');
                $subject = sanitizeArray($_POST['subject'] ?? '');
                $message = sanitizeArray($_POST['message'] ?? '');
                $communicationDate = sanitizeArray($_POST['communication_date'] ?? date('Y-m-d H:i:s'));
                
                if ($stakeholderId <= 0 || empty($communicationType)) {
                    throw new Exception('Stakeholder and communication type are required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO stakeholder_communications (stakeholder_id, communication_type, subject, message, 
                                                          communication_date, initiated_by, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$stakeholderId, $communicationType, $subject, $message, $communicationDate, $currentUserId, $currentUserId]);
                
                flash('success', 'Communication logged successfully');
                redirect('hr.php?action=stakeholders&view=' . $stakeholderId);
                break;
                
            case 'update_stakeholder_communication':
                $commId = intval($_POST['id'] ?? 0);
                $stakeholderId = intval($_POST['stakeholder_id'] ?? 0);
                $communicationType = sanitizeArray($_POST['communication_type'] ?? '');
                $subject = sanitizeArray($_POST['subject'] ?? '');
                $message = sanitizeArray($_POST['message'] ?? '');
                $communicationDate = sanitizeArray($_POST['communication_date'] ?? date('Y-m-d H:i:s'));
                
                if ($commId <= 0 || $stakeholderId <= 0 || empty($communicationType)) {
                    throw new Exception('All required fields must be provided');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE stakeholder_communications 
                    SET stakeholder_id = ?, communication_type = ?, subject = ?, message = ?, 
                        communication_date = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$stakeholderId, $communicationType, $subject, $message, $communicationDate, $commId]);
                
                flash('success', 'Communication updated successfully');
                redirect('hr.php?action=stakeholders&view=' . $stakeholderId);
                break;
                
            case 'delete_stakeholder_communication':
                $commId = intval($_POST['id'] ?? 0);
                $stakeholderId = intval($_POST['stakeholder_id'] ?? 0);
                
                if ($commId <= 0) {
                    throw new Exception('Invalid communication record');
                }
                
                $stmt = $pdo->prepare("DELETE FROM stakeholder_communications WHERE id = ?");
                $stmt->execute([$commId]);
                
                flash('success', 'Communication deleted successfully');
                redirect('hr.php?action=stakeholders&view=' . ($stakeholderId > 0 ? $stakeholderId : ''));
                break;
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect('hr.php?action=' . $action);
    }
}

// Get data based on action
switch ($action) {
    case 'employees':
        // Get all workers/employees
        try {
            $workers = $pdo->query("
                SELECT w.*, 
                       d.department_name, 
                       p.position_title,
                       m.worker_name as manager_name,
                       u.username as system_user,
                       COALESCE(ws.total_jobs, 0) as total_jobs,
                       COALESCE(ws.jobs_last_month, 0) as jobs_last_month,
                       COALESCE(ws.total_wages, 0) as total_wages,
                       ws.last_job_date
                FROM workers w
                LEFT JOIN departments d ON w.department_id = d.id
                LEFT JOIN positions p ON w.position_id = p.id
                LEFT JOIN workers m ON w.manager_id = m.id
                LEFT JOIN users u ON w.user_id = u.id
                LEFT JOIN worker_statistics ws ON w.id = ws.worker_id
                ORDER BY w.worker_name ASC
            ")->fetchAll();
        } catch (PDOException $e) {
            // Fallback: get workers without joins or statistics
            try {
                $workers = $pdo->query("
                    SELECT w.*, 
                           NULL as department_name, 
                           NULL as position_title, 
                           NULL as manager_name, 
                           NULL as system_user,
                           0 as total_jobs,
                           0 as jobs_last_month,
                           0 as total_wages,
                           NULL as last_job_date
                    FROM workers w 
                    ORDER BY w.worker_name ASC
                ")->fetchAll();
            } catch (PDOException $e2) {
                $workers = [];
            }
        }
        
        try {
            $departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
        } catch (PDOException $e) {
            $departments = [];
        }
        
        try {
            $positions = $pdo->query("SELECT * FROM positions WHERE is_active = 1 ORDER BY position_title")->fetchAll();
        } catch (PDOException $e) {
            $positions = [];
        }
        
        try {
            $managers = $pdo->query("SELECT id, worker_name FROM workers WHERE status = 'active' AND employee_type IN ('staff', 'worker') ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            try {
                $managers = $pdo->query("SELECT id, worker_name FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
            } catch (PDOException $e2) {
                $managers = [];
            }
        }
        break;
        
    case 'departments':
        try {
            $departments = $pdo->query("
                SELECT d.*, 
                       w.worker_name as manager_name,
                       (SELECT COUNT(*) FROM workers WHERE department_id = d.id AND status = 'active') as employee_count
                FROM departments d
                LEFT JOIN workers w ON d.manager_id = w.id
                ORDER BY d.department_name
            ")->fetchAll();
        } catch (PDOException $e) {
            $departments = [];
        }
        
        try {
            $managers = $pdo->query("SELECT id, worker_name FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            $managers = [];
        }
        break;
        
    case 'positions':
        try {
            $positions = $pdo->query("
                SELECT p.*, 
                       d.department_name,
                       (SELECT COUNT(*) FROM workers WHERE position_id = p.id AND status = 'active') as employee_count
                FROM positions p
                LEFT JOIN departments d ON p.department_id = d.id
                ORDER BY p.position_title
            ")->fetchAll();
        } catch (PDOException $e) {
            $positions = [];
        }
        
        try {
            $departments = $pdo->query("SELECT * FROM departments WHERE is_active = 1 ORDER BY department_name")->fetchAll();
        } catch (PDOException $e) {
            $departments = [];
        }
        break;
        
    case 'attendance':
        // Get attendance records
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-t');
        
        try {
            $attendance = $pdo->prepare("
                SELECT ar.*, w.worker_name, w.employee_code
                FROM attendance_records ar
                JOIN workers w ON ar.worker_id = w.id
                WHERE ar.attendance_date BETWEEN ? AND ?
                ORDER BY ar.attendance_date DESC, w.worker_name
            ");
            $attendance->execute([$dateFrom, $dateTo]);
            $attendanceRecords = $attendance->fetchAll();
        } catch (PDOException $e) {
            $attendanceRecords = [];
        }
        
        try {
            $workers = $pdo->query("SELECT id, worker_name, employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            try {
                $workers = $pdo->query("SELECT id, worker_name, NULL as employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
            } catch (PDOException $e2) {
                $workers = [];
            }
        }
        break;
        
    case 'leave':
        // Get leave requests
        try {
            $leaveRequests = $pdo->query("
                SELECT lr.*, w.worker_name, w.employee_code, lt.leave_name,
                       u.username as approved_by_user
                FROM leave_requests lr
                JOIN workers w ON lr.worker_id = w.id
                JOIN leave_types lt ON lr.leave_type_id = lt.id
                LEFT JOIN users u ON lr.approved_by = u.id
                ORDER BY lr.created_at DESC
                LIMIT 50
            ")->fetchAll();
        } catch (PDOException $e) {
            $leaveRequests = [];
        }
        
        try {
            $leaveTypes = $pdo->query("SELECT * FROM leave_types WHERE is_active = 1 ORDER BY leave_name")->fetchAll();
        } catch (PDOException $e) {
            $leaveTypes = [];
        }
        
        try {
            $workers = $pdo->query("SELECT id, worker_name, employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            try {
                $workers = $pdo->query("SELECT id, worker_name, NULL as employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
            } catch (PDOException $e2) {
                $workers = [];
            }
        }
        
        // Get leave balances
        try {
            $leaveBalances = $pdo->query("
                SELECT lb.*, w.worker_name, lt.leave_name
                FROM leave_balances lb
                JOIN workers w ON lb.worker_id = w.id
                JOIN leave_types lt ON lb.leave_type_id = lt.id
                WHERE lb.year = YEAR(CURDATE())
                ORDER BY w.worker_name, lt.leave_name
            ")->fetchAll();
        } catch (PDOException $e) {
            $leaveBalances = [];
        }
        break;
        
    case 'performance':
        try {
            $performanceReviews = $pdo->query("
                SELECT pr.*, w.worker_name, w.employee_code, u.username as reviewer_name
                FROM performance_reviews pr
                JOIN workers w ON pr.worker_id = w.id
                LEFT JOIN users u ON pr.reviewer_id = u.id
                ORDER BY pr.review_period_end DESC
                LIMIT 50
            ")->fetchAll();
        } catch (PDOException $e) {
            $performanceReviews = [];
        }
        
        try {
            $workers = $pdo->query("SELECT id, worker_name, employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            try {
                $workers = $pdo->query("SELECT id, worker_name, NULL as employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
            } catch (PDOException $e2) {
                $workers = [];
            }
        }
        
        try {
            $users = $pdo->query("SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
        } catch (PDOException $e) {
            $users = [];
        }
        break;
        
    case 'training':
        try {
            $trainingRecords = $pdo->query("
                SELECT tr.*, w.worker_name, w.employee_code
                FROM training_records tr
                JOIN workers w ON tr.worker_id = w.id
                ORDER BY tr.start_date DESC
                LIMIT 50
            ")->fetchAll();
        } catch (PDOException $e) {
            $trainingRecords = [];
        }
        
        try {
            $workers = $pdo->query("SELECT id, worker_name, employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
        } catch (PDOException $e) {
            try {
                $workers = $pdo->query("SELECT id, worker_name, NULL as employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();
            } catch (PDOException $e2) {
                $workers = [];
            }
        }
        
        // Get skills
        try {
            $skills = $pdo->query("
                SELECT ws.*, w.worker_name
                FROM worker_skills ws
                JOIN workers w ON ws.worker_id = w.id
                ORDER BY w.worker_name, ws.skill_name
            ")->fetchAll();
        } catch (PDOException $e) {
            $skills = [];
        }
        break;
        
    case 'stakeholders':
        try {
            $stakeholders = $pdo->query("
                SELECT s.*, u.username as created_by_user
                FROM stakeholders s
                LEFT JOIN users u ON s.created_by = u.id
                ORDER BY s.created_at DESC
            ")->fetchAll();
        } catch (PDOException $e) {
            $stakeholders = [];
        }
        
        // Get communications if viewing specific stakeholder
        $viewStakeholderId = intval($_GET['view'] ?? 0);
        $stakeholderCommunications = [];
        if ($viewStakeholderId > 0) {
            try {
                $stakeholderCommunications = $pdo->prepare("
                    SELECT sc.*, u.username as initiated_by_user
                    FROM stakeholder_communications sc
                    LEFT JOIN users u ON sc.initiated_by = u.id
                    WHERE sc.stakeholder_id = ?
                    ORDER BY sc.communication_date DESC
                ");
                $stakeholderCommunications->execute([$viewStakeholderId]);
                $stakeholderCommunications = $stakeholderCommunications->fetchAll();
            } catch (PDOException $e) {
                $stakeholderCommunications = [];
            }
        }
        break;
        
    case 'dashboard':
    default:
        // Dashboard statistics - with safe error handling
        try {
            $totalEmployees = $pdo->query("SELECT COUNT(*) as count FROM workers WHERE status = 'active'")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalEmployees = 0;
        }
        
        try {
            $totalStaff = $pdo->query("SELECT COUNT(*) as count FROM workers WHERE status = 'active' AND employee_type = 'staff'")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalStaff = 0;
        }
        
        try {
            $totalWorkers = $pdo->query("SELECT COUNT(*) as count FROM workers WHERE status = 'active' AND employee_type = 'worker'")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalWorkers = 0;
        }
        
        try {
            $totalDepartments = $pdo->query("SELECT COUNT(*) as count FROM departments WHERE is_active = 1")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalDepartments = 0;
        }
        
        try {
            $totalPositions = $pdo->query("SELECT COUNT(*) as count FROM positions WHERE is_active = 1")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalPositions = 0;
        }
        
        try {
            $totalStakeholders = $pdo->query("SELECT COUNT(*) as count FROM stakeholders WHERE is_active = 1")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $totalStakeholders = 0;
        }
        
        // Recent activity
        try {
            $recentEmployees = $pdo->query("
                SELECT w.*, d.department_name, p.position_title
                FROM workers w
                LEFT JOIN departments d ON w.department_id = d.id
                LEFT JOIN positions p ON w.position_id = p.id
                ORDER BY w.created_at DESC
                LIMIT 5
            ")->fetchAll();
        } catch (PDOException $e) {
            // Fallback: just get workers without joins
            try {
                $recentEmployees = $pdo->query("
                    SELECT w.*, NULL as department_name, NULL as position_title
                    FROM workers w
                    ORDER BY w.created_at DESC
                    LIMIT 5
                ")->fetchAll();
            } catch (PDOException $e2) {
                $recentEmployees = [];
            }
        }
        
        try {
            $pendingLeave = $pdo->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $pendingLeave = 0;
        }
        
        try {
            $todayAttendance = $pdo->query("SELECT COUNT(*) as count FROM attendance_records WHERE attendance_date = CURDATE()")->fetch()['count'] ?? 0;
        } catch (PDOException $e) {
            $todayAttendance = 0;
        }
        break;
}

require_once '../includes/header.php';
?>

<style>
/* HR Module Styles - Theme Compatible */
.hr-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
}

.hr-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: var(--secondary);
    text-decoration: none;
    transition: all 0.2s;
    position: relative;
    bottom: -2px;
}

.hr-tab:hover {
    color: var(--primary);
    background: color-mix(in srgb, var(--primary) 5%, transparent);
}

.hr-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.hr-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.hr-stat-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px color-mix(in srgb, var(--text) 8%, transparent);
    transition: all 0.3s ease;
}

.hr-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px color-mix(in srgb, var(--text) 12%, transparent);
}

.hr-stat-icon {
    font-size: 32px;
    margin-bottom: 12px;
}

.hr-stat-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 8px;
}

.hr-stat-label {
    font-size: 13px;
    color: var(--secondary);
    font-weight: 500;
}

.hr-table-wrapper {
    background: var(--card);
    border-radius: var(--radius);
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px color-mix(in srgb, var(--text) 8%, transparent);
}

.hr-table {
    width: 100%;
    border-collapse: collapse;
}

.hr-table thead {
    background: var(--bg);
}

.hr-table th {
    padding: 14px 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.hr-table td {
    padding: 14px 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text);
}

.hr-table tbody tr {
    background: var(--card);
}

.hr-table tbody tr:hover {
    background: var(--bg);
}

[data-theme="dark"] .hr-table tbody tr:hover {
    background: color-mix(in srgb, white 3%, transparent);
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.badge-active {
    background: color-mix(in srgb, var(--success) 20%, transparent);
    color: var(--success);
}

.badge-inactive {
    background: color-mix(in srgb, var(--danger) 20%, transparent);
    color: var(--danger);
}

.badge-pending {
    background: color-mix(in srgb, var(--warning) 20%, transparent);
    color: var(--warning);
}

.hr-form-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px color-mix(in srgb, var(--text) 8%, transparent);
    margin-bottom: 24px;
}

.hr-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.dashboard-card {
    background: var(--card);
    border-radius: var(--radius);
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: 0 1px 3px color-mix(in srgb, var(--text) 8%, transparent);
}

.dashboard-card h2 {
    margin: 0 0 20px 0;
    color: var(--text);
    font-size: 18px;
    font-weight: 600;
}

/* Ensure form controls use theme variables */
.hr-form-card .form-control,
.dashboard-card .form-control {
    background: var(--input);
    border: 1px solid var(--border);
    color: var(--text);
}

.hr-form-card .form-control:focus,
.dashboard-card .form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--primary) 10%, transparent);
}

/* Migration notice styling */
.hr-migration-notice {
    background: color-mix(in srgb, var(--warning) 15%, transparent);
    border: 1px solid var(--warning);
    border-radius: var(--radius);
    padding: 20px;
    margin-bottom: 24px;
}

.hr-migration-notice h3 {
    margin: 0 0 12px 0;
    color: var(--warning);
    font-size: 18px;
}

.hr-migration-notice p {
    margin: 0 0 12px 0;
    color: var(--text);
}

.hr-migration-notice code {
    background: var(--card);
    padding: 12px;
    border-radius: 6px;
    display: block;
    margin-top: 12px;
    font-family: monospace;
    font-size: 13px;
    color: var(--text);
}
</style>

<div class="page-header">
    <div>
        <h1>Human Resources Management</h1>
        <p>Manage staff, recruitment pipelines, and stakeholders</p>
    </div>
</div>

<!-- Tabs -->
<div class="hr-tabs">
    <a href="hr.php?action=dashboard" class="hr-tab <?php echo $action === 'dashboard' ? 'active' : ''; ?>">
        📊 Dashboard
    </a>
    <a href="hr.php?action=employees" class="hr-tab <?php echo $action === 'employees' ? 'active' : ''; ?>">
        👥 Employees
    </a>
    <a href="hr.php?action=departments" class="hr-tab <?php echo $action === 'departments' ? 'active' : ''; ?>">
        🏢 Departments
    </a>
    <a href="hr.php?action=positions" class="hr-tab <?php echo $action === 'positions' ? 'active' : ''; ?>">
        💼 Positions
    </a>
    <a href="hr.php?action=attendance" class="hr-tab <?php echo $action === 'attendance' ? 'active' : ''; ?>">
        ⏰ Attendance
    </a>
    <a href="hr.php?action=leave" class="hr-tab <?php echo $action === 'leave' ? 'active' : ''; ?>">
        🏖️ Leave
    </a>
    <a href="hr.php?action=performance" class="hr-tab <?php echo $action === 'performance' ? 'active' : ''; ?>">
        ⭐ Performance
    </a>
    <a href="hr.php?action=training" class="hr-tab <?php echo $action === 'training' ? 'active' : ''; ?>">
        📚 Training
    </a>
    <a href="hr.php?action=stakeholders" class="hr-tab <?php echo $action === 'stakeholders' ? 'active' : ''; ?>">
        🤝 Stakeholders
    </a>
    <a href="hr.php?action=roles" class="hr-tab <?php echo $action === 'roles' ? 'active' : ''; ?>">
        👔 Roles
    </a>
    <?php if ($auth->userHasPermission('recruitment.access')): ?>
        <a href="recruitment.php" class="hr-tab" style="display:flex; align-items:center; gap:6px;">
            🧑‍💼 Recruitment
        </a>
    <?php endif; ?>
</div>

<?php if ($action === 'dashboard'): ?>
    <!-- Dashboard -->
    <?php if (!$hrTablesExist): ?>
        <div class="hr-migration-notice">
            <h3>⚠️ HR Database Migration Required</h3>
            <p>
                The HR system database tables have not been initialized yet. Please run the migration script to set up the HR system.
            </p>
            <div>
                <strong>To run the migration:</strong>
                <code>mysql -u root -p abbis_3_2 < database/hr_system_migration.sql</code>
            </div>
            <p style="margin-top: 12px; font-size: 13px;">
                After running the migration, refresh this page to see the HR dashboard.
            </p>
            <form method="POST" style="margin-top: 16px;">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="run_migration">
                <button type="submit" class="btn btn-primary" onclick="return confirm('This will run the database migration. Are you sure?')">
                    Run Migration Now (Auto)
                </button>
            </form>
        </div>
    <?php endif; ?>
    <div class="hr-stats-grid">
        <div class="hr-stat-card">
            <div class="hr-stat-icon">👥</div>
            <div class="hr-stat-value"><?php echo number_format($totalEmployees); ?></div>
            <div class="hr-stat-label">Total Employees</div>
        </div>
        <div class="hr-stat-card">
            <div class="hr-stat-icon">👔</div>
            <div class="hr-stat-value"><?php echo number_format($totalStaff); ?></div>
            <div class="hr-stat-label">Staff</div>
        </div>
        <div class="hr-stat-card">
            <div class="hr-stat-icon">🔧</div>
            <div class="hr-stat-value"><?php echo number_format($totalWorkers); ?></div>
            <div class="hr-stat-label">Field Workers</div>
        </div>
        <div class="hr-stat-card">
            <div class="hr-stat-icon">🏢</div>
            <div class="hr-stat-value"><?php echo number_format($totalDepartments); ?></div>
            <div class="hr-stat-label">Departments</div>
        </div>
        <div class="hr-stat-card">
            <div class="hr-stat-icon">💼</div>
            <div class="hr-stat-value"><?php echo number_format($totalPositions); ?></div>
            <div class="hr-stat-label">Positions</div>
        </div>
        <div class="hr-stat-card">
            <div class="hr-stat-icon">🤝</div>
            <div class="hr-stat-value"><?php echo number_format($totalStakeholders); ?></div>
            <div class="hr-stat-label">Stakeholders</div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2>Recent Employees</h2>
            <div class="hr-table-wrapper">
                <table class="hr-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentEmployees as $emp): ?>
                            <tr>
                                <td><?php echo e($emp['employee_code'] ?? 'N/A'); ?></td>
                                <td><?php echo e($emp['worker_name']); ?></td>
                                <td><?php echo e($emp['role']); ?></td>
                                <td><?php echo e($emp['department_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $emp['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                        <?php echo ucfirst($emp['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="dashboard-card">
            <h2>Quick Actions</h2>
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <a href="hr.php?action=employees" class="btn btn-primary">Add New Employee</a>
                <a href="hr.php?action=attendance" class="btn btn-secondary">Record Attendance</a>
                <a href="hr.php?action=leave" class="btn btn-secondary">Manage Leave</a>
                <a href="hr.php?action=stakeholders" class="btn btn-secondary">Add Stakeholder</a>
                <?php if ($auth->userHasPermission('recruitment.access')): ?>
                    <a href="recruitment.php" class="btn btn-secondary">Open Recruitment Pipeline</a>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border);">
                <h3 style="font-size: 14px; margin-bottom: 12px; color: var(--secondary);">Pending Actions</h3>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div>
                        <strong><?php echo $pendingLeave; ?></strong> pending leave requests
                    </div>
                    <div>
                        <strong><?php echo $todayAttendance; ?></strong> attendance records today
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Workers by Jobs -->
        <?php if (!empty($topWorkers)): ?>
        <div class="dashboard-card">
            <h2>🏆 Top Workers by Jobs</h2>
            <div class="table-responsive">
                <table class="table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Worker</th>
                            <th>Role</th>
                            <th>Total Jobs</th>
                            <th>This Month</th>
                            <th>Total Wages</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topWorkers as $worker): ?>
                        <tr>
                            <td>
                                <a href="hr.php?action=worker-detail&worker_id=<?php echo $worker['worker_id']; ?>" 
                                   style="color: var(--primary); text-decoration: none; font-weight: 500;">
                                    <?php echo e($worker['worker_name']); ?>
                                </a>
                            </td>
                            <td><?php echo e($worker['role']); ?></td>
                            <td><strong><?php echo $worker['total_jobs']; ?></strong></td>
                            <td><?php echo $worker['jobs_last_month']; ?></td>
                            <td>GHS <?php echo number_format($worker['total_wages'] ?? 0, 2); ?></td>
                            <td>
                                <a href="hr.php?action=worker-detail&worker_id=<?php echo $worker['worker_id']; ?>" 
                                   class="btn btn-sm btn-primary">View Details</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Worker Activity -->
        <?php if (!empty($recentJobs)): ?>
        <div class="dashboard-card">
            <h2>📋 Recent Worker Activity</h2>
            <div class="table-responsive">
                <table class="table" style="margin-top: 15px;">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Worker</th>
                            <th>Role</th>
                            <th>Site</th>
                            <th>Rig</th>
                            <th>Report ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentJobs as $job): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($job['report_date'])); ?></td>
                            <td>
                                <a href="hr.php?action=worker-detail&worker_id=<?php echo $job['worker_id']; ?>" 
                                   style="color: var(--primary); text-decoration: none;">
                                    <?php echo e($job['worker_name']); ?>
                                </a>
                            </td>
                            <td><?php echo e($job['role']); ?></td>
                            <td><?php echo e($job['site_name']); ?></td>
                            <td><?php echo e($job['rig_code'] ?? 'N/A'); ?></td>
                            <td>
                                <a href="field-reports-list.php?search=<?php echo urlencode($job['report_id']); ?>" 
                                   style="color: var(--primary); text-decoration: none;">
                                    <?php echo e($job['report_id']); ?>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'employees'): ?>
    <!-- Employees Management -->
    <div class="hr-form-card">
        <h2 id="employeeFormTitle">Add New Employee</h2>
        <form method="POST" class="hr-form-grid" id="employeeForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" id="employeeAction" value="add_employee">
            <input type="hidden" name="worker_id" id="employeeWorkerId" value="">
            
            <div class="form-group">
                <label>Staff ID</label>
                <input type="text" name="employee_code" class="form-control" placeholder="Leave blank to auto-generate">
            </div>
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="worker_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Role *</label>
                <?php
                // Get worker roles if config-manager is available
                $workerRoles = [];
                $activeWorkerRoleNames = [];
                try {
                    if (file_exists(__DIR__ . '/../includes/config-manager.php')) {
                        require_once __DIR__ . '/../includes/config-manager.php';
                        if (class_exists('ConfigManager')) {
                            $configManager = new ConfigManager();
                            $workerRoles = $configManager->getAllWorkerRoles();
                            $activeWorkerRoleNames = $configManager->getAllWorkerRoleNames();
                        }
                    }
                } catch (Exception $e) {
                    // Fallback: get roles from workers table
                    try {
                        $roleStmt = $pdo->query("SELECT DISTINCT role FROM workers WHERE role IS NOT NULL AND role != '' ORDER BY role");
                        $activeWorkerRoleNames = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
                    } catch (PDOException $e2) {}
                }
                ?>
                <select name="role" class="form-control" required>
                    <option value="">Select Role...</option>
                    <?php foreach ($activeWorkerRoleNames as $roleName): ?>
                        <option value="<?php echo e($roleName); ?>"><?php echo e($roleName); ?></option>
                    <?php endforeach; ?>
                    <option value="__custom__">➕ Add Custom Role...</option>
                </select>
                <div id="customRoleContainer" style="display: none; margin-top: 10px;">
                    <input type="text" id="custom_role_input" class="form-control" placeholder="Enter custom role name">
                    <small class="form-text" style="color: var(--secondary);">Custom role will be added to the system</small>
                </div>
            </div>
            <div class="form-group">
                <label>Type</label>
                <select name="employee_type" class="form-control">
                    <option value="worker">Worker</option>
                    <option value="staff">Staff</option>
                    <option value="contractor">Contractor</option>
                </select>
            </div>
            <div class="form-group">
                <label>Default Rate</label>
                <input type="number" name="default_rate" class="form-control" step="0.01" value="0">
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number" class="form-control">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Department</label>
                <select name="department_id" class="form-control">
                    <option value="">Select Department</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['department_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Position</label>
                <select name="position_id" class="form-control">
                    <option value="">Select Position</option>
                    <?php foreach ($positions as $pos): ?>
                        <option value="<?php echo $pos['id']; ?>"><?php echo e($pos['position_title']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Hire Date</label>
                <input type="date" name="hire_date" class="form-control">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="employeeStatus" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <!-- Role Management Section -->
            <div class="form-group" style="grid-column: 1 / -1; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <label style="font-weight: 600; font-size: 16px; margin: 0;">👔 Role Assignments</label>
                    <button type="button" class="btn btn-sm btn-outline" onclick="showRoleManagementModal()" id="manageRolesBtn" style="display: none;">
                        Manage Roles
                    </button>
                </div>
                <div id="workerRolesDisplay" style="min-height: 40px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                    <small style="color: var(--secondary);">Save worker first to manage roles</small>
                </div>
                <small class="form-text" style="color: var(--secondary); margin-top: 5px;">
                    Workers can have multiple roles. Set one as primary. Each role can have its own default rate.
                </small>
            </div>
            
            <!-- Rig Preferences Section -->
            <div class="form-group" style="grid-column: 1 / -1; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <label style="font-weight: 600; font-size: 16px; margin: 0;">🚛 Rig Preferences</label>
                    <button type="button" class="btn btn-sm btn-outline" onclick="showRigPreferencesModal()" id="manageRigsBtn" style="display: none;">
                        Manage Rig Preferences
                    </button>
                </div>
                <div id="workerRigsDisplay" style="min-height: 40px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                    <small style="color: var(--secondary);">Save worker first to manage rig preferences</small>
                </div>
                <small class="form-text" style="color: var(--secondary); margin-top: 5px;">
                    Track which rigs this worker typically works on. Used for suggestions only - workers can still be assigned to any rig.
                </small>
            </div>
            
            <div class="form-group" style="grid-column: 1 / -1;">
                <button type="submit" class="btn btn-primary" id="employeeSubmitBtn">Add Employee</button>
                <button type="button" class="btn btn-outline" onclick="resetEmployeeForm()" id="employeeCancelBtn" style="display: none;">Cancel</button>
            </div>
        </form>
    </div>
    
    <!-- Role Management Modal -->
    <div id="roleManagementModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 id="roleManagementTitle">Manage Worker Roles</h2>
                <button type="button" class="modal-close" onclick="closeRoleManagementModal()">&times;</button>
            </div>
            <div style="padding: 20px;">
                <div id="roleManagementContent">
                    <div style="text-align: center; padding: 40px;">
                        <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                        <p>Loading roles...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Rig Preferences Modal -->
    <div id="rigPreferencesModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h2 id="rigPreferencesTitle">Manage Rig Preferences</h2>
                <button type="button" class="modal-close" onclick="closeRigPreferencesModal()">&times;</button>
            </div>
            <div style="padding: 20px;">
                <div id="rigPreferencesContent">
                    <div style="text-align: center; padding: 40px;">
                        <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                        <p>Loading rig preferences...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <button type="button" class="btn btn-outline" onclick="analyzeWorkerDuplicates()" title="Analyze for duplicate workers">
            🔍 Analyze Duplicates
        </button>
        <button type="button" class="btn btn-outline" onclick="exportWorkers()" title="Export workers list to CSV">
            📥 Export Workers
        </button>
    </div>

    <!-- Inline JavaScript functions - ensure they're available immediately -->
    <script>
    // Ensure functions are available before buttons are rendered
    if (typeof editEmployeeFromButton === 'undefined') {
        window.editEmployeeFromButton = function(button) {
            try {
                const workerId = button.getAttribute('data-worker-id');
                if (!workerId) {
                    console.error('Missing worker ID');
                    alert('Error: Missing worker ID. Please refresh the page and try again.');
                    return;
                }
                
                const workerData = {
                    id: workerId,
                    employee_code: button.getAttribute('data-worker-code') || '',
                    worker_name: button.getAttribute('data-worker-name') || '',
                    role: button.getAttribute('data-worker-role') || '',
                    employee_type: button.getAttribute('data-worker-type') || 'worker',
                    default_rate: button.getAttribute('data-worker-rate') || 0,
                    contact_number: button.getAttribute('data-worker-contact') || '',
                    email: button.getAttribute('data-worker-email') || '',
                    department_id: button.getAttribute('data-worker-dept') || '',
                    position_id: button.getAttribute('data-worker-position') || '',
                    hire_date: button.getAttribute('data-worker-hire-date') || '',
                    status: button.getAttribute('data-worker-status') || 'active'
                };
                
                // Call main editEmployee function if it exists, otherwise use inline logic
                if (typeof editEmployee === 'function') {
                    editEmployee(workerData);
                } else {
                    // Fallback inline edit logic
                    const formTitle = document.getElementById('employeeFormTitle');
                    const actionInput = document.getElementById('employeeAction');
                    const workerIdInput = document.getElementById('employeeWorkerId');
                    const submitBtn = document.getElementById('employeeSubmitBtn');
                    const cancelBtn = document.getElementById('employeeCancelBtn');
                    const form = document.getElementById('employeeForm');
                    
                    if (!formTitle || !actionInput || !workerIdInput || !submitBtn || !form) {
                        alert('Error: Form elements not found. Please refresh the page.');
                        return;
                    }
                    
                    formTitle.textContent = 'Edit Employee';
                    actionInput.value = 'update_employee';
                    workerIdInput.value = workerData.id;
                    submitBtn.textContent = 'Update Employee';
                    if (cancelBtn) cancelBtn.style.display = 'inline-block';
                    
                    // Fill form fields
                    const codeInput = document.querySelector('[name="employee_code"]');
                    if (codeInput) codeInput.value = workerData.employee_code || '';
                    const nameInput = document.querySelector('[name="worker_name"]');
                    if (nameInput) nameInput.value = workerData.worker_name || '';
                    const roleInput = document.querySelector('[name="role"]');
                    if (roleInput) roleInput.value = workerData.role || '';
                    const typeInput = document.querySelector('[name="employee_type"]');
                    if (typeInput) typeInput.value = workerData.employee_type || 'worker';
                    const rateInput = document.querySelector('[name="default_rate"]');
                    if (rateInput) rateInput.value = workerData.default_rate || 0;
                    const contactInput = document.querySelector('[name="contact_number"]');
                    if (contactInput) contactInput.value = workerData.contact_number || '';
                    const emailInput = document.querySelector('[name="email"]');
                    if (emailInput) emailInput.value = workerData.email || '';
                    const deptInput = document.querySelector('[name="department_id"]');
                    if (deptInput) deptInput.value = workerData.department_id || '';
                    const positionInput = document.querySelector('[name="position_id"]');
                    if (positionInput) positionInput.value = workerData.position_id || '';
                    const statusInput = document.getElementById('employeeStatus');
                    if (statusInput) statusInput.value = workerData.status || 'active';
                    const hireDateInput = document.querySelector('[name="hire_date"]');
                    if (hireDateInput && workerData.hire_date) hireDateInput.value = workerData.hire_date;
                    
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    if (nameInput) setTimeout(() => nameInput.focus(), 300);
                }
            } catch (error) {
                console.error('Error in editEmployeeFromButton:', error);
                alert('An error occurred while trying to edit the employee. Please check the console for details.');
            }
        };
    }
    
    if (typeof deleteEmployeeFromButton === 'undefined') {
        window.deleteEmployeeFromButton = function(button) {
            try {
                const workerId = button.getAttribute('data-worker-id');
                const workerName = button.getAttribute('data-worker-name') || 'this employee';
                
                if (!workerId) {
                    console.error('Missing worker ID');
                    alert('Error: Missing worker ID. Please refresh the page and try again.');
                    return;
                }
                
                if (confirm(`Are you sure you want to delete ${workerName}? This action cannot be undone and will affect all related records (payroll, loans, field reports, etc.).`)) {
                    // Call main deleteEmployee function if it exists, otherwise use inline logic
                    if (typeof deleteEmployee === 'function') {
                        deleteEmployee(workerId);
                    } else {
                        // Fallback inline delete logic
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = 'hr.php?action=employees';
                        form.style.display = 'none';
                        
                        const csrfToken = document.querySelector('[name="csrf_token"]');
                        if (csrfToken && csrfToken.value) {
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = 'csrf_token';
                            csrfInput.value = csrfToken.value;
                            form.appendChild(csrfInput);
                        }
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'delete_employee';
                        form.appendChild(actionInput);
                        
                        const workerIdInput = document.createElement('input');
                        workerIdInput.type = 'hidden';
                        workerIdInput.name = 'worker_id';
                        workerIdInput.value = workerId;
                        form.appendChild(workerIdInput);
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            } catch (error) {
                console.error('Error in deleteEmployeeFromButton:', error);
                alert('An error occurred while trying to delete the employee. Please check the console for details.');
            }
        };
    }
    </script>

    <div class="hr-table-wrapper">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Roles</th>
                    <th>Rigs</th>
                    <th>Jobs</th>
                    <th>Type</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Load worker mapping manager
                require_once __DIR__ . '/../includes/worker-mapping-manager.php';
                $mappingManager = new WorkerMappingManager();
                
                foreach ($workers as $worker): 
                    $workerRoles = $mappingManager->getWorkerRoles($worker['id']);
                    $workerRigs = $mappingManager->getWorkerRigs($worker['id']);
                ?>
                    <tr>
                        <td><?php echo e($worker['employee_code'] ?? 'N/A'); ?></td>
                        <td>
                            <a href="hr.php?action=worker-detail&worker_id=<?php echo $worker['id']; ?>" 
                               style="color: var(--primary); text-decoration: none; font-weight: 500;">
                                <?php echo e($worker['worker_name']); ?>
                            </a>
                        </td>
                        <td>
                            <?php if (count($workerRoles) > 0): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach ($workerRoles as $role): ?>
                                        <span style="background: <?php echo $role['is_primary'] ? '#28a745' : '#007bff'; ?>; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                                            <?php echo $role['is_primary'] ? '⭐' : ''; ?> <?php echo e($role['role_name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--secondary);"><?php echo e($worker['role']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (count($workerRigs) > 0): ?>
                                <div style="display: flex; flex-wrap: wrap; gap: 4px;">
                                    <?php foreach (array_slice($workerRigs, 0, 2) as $rig): ?>
                                        <span style="background: <?php echo $rig['preference_level'] === 'primary' ? '#28a745' : ($rig['preference_level'] === 'secondary' ? '#ffc107' : '#6c757d'); ?>; color: white; padding: 2px 6px; border-radius: 4px; font-size: 11px;">
                                            <?php echo e($rig['rig_code']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                    <?php if (count($workerRigs) > 2): ?>
                                        <span style="color: var(--secondary); font-size: 11px;">+<?php echo count($workerRigs) - 2; ?> more</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--secondary);">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 2px;">
                                <span style="font-weight: 600; color: var(--primary);">
                                    <?php echo $worker['total_jobs'] ?? 0; ?> total
                                </span>
                                <?php if (($worker['jobs_last_month'] ?? 0) > 0): ?>
                                    <small style="color: var(--secondary); font-size: 11px;">
                                        <?php echo $worker['jobs_last_month']; ?> this month
                                    </small>
                                <?php endif; ?>
                                <?php if ($worker['last_job_date']): ?>
                                    <small style="color: var(--secondary); font-size: 10px;">
                                        Last: <?php echo date('M d', strtotime($worker['last_job_date'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo ucfirst($worker['employee_type'] ?? 'worker'); ?></td>
                        <td><?php echo e($worker['department_name'] ?? '-'); ?></td>
                        <td><?php echo e($worker['position_title'] ?? '-'); ?></td>
                        <td><?php echo e($worker['contact_number'] ?? '-'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $worker['status'] === 'active' ? 'active' : 'inactive'; ?>">
                                <?php echo ucfirst($worker['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary edit-employee-btn"
                                    data-worker-id="<?php echo htmlspecialchars($worker['id']); ?>"
                                    data-worker-code="<?php echo htmlspecialchars($worker['employee_code'] ?? ''); ?>"
                                    data-worker-name="<?php echo htmlspecialchars($worker['worker_name'] ?? ''); ?>"
                                    data-worker-role="<?php echo htmlspecialchars($worker['role'] ?? ''); ?>"
                                    data-worker-type="<?php echo htmlspecialchars($worker['employee_type'] ?? 'worker'); ?>"
                                    data-worker-rate="<?php echo htmlspecialchars($worker['default_rate'] ?? 0); ?>"
                                    data-worker-contact="<?php echo htmlspecialchars($worker['contact_number'] ?? ''); ?>"
                                    data-worker-email="<?php echo htmlspecialchars($worker['email'] ?? ''); ?>"
                                    data-worker-dept="<?php echo htmlspecialchars($worker['department_id'] ?? ''); ?>"
                                    data-worker-position="<?php echo htmlspecialchars($worker['position_id'] ?? ''); ?>"
                                    data-worker-hire-date="<?php echo htmlspecialchars($worker['hire_date'] ?? ''); ?>"
                                    data-worker-status="<?php echo htmlspecialchars($worker['status'] ?? 'active'); ?>"
                                    onclick="editEmployeeFromButton(this)">Edit</button>
                            <button type="button" class="btn btn-sm btn-danger delete-employee-btn"
                                    data-worker-id="<?php echo htmlspecialchars($worker['id']); ?>"
                                    data-worker-name="<?php echo htmlspecialchars($worker['worker_name'] ?? 'Unknown'); ?>"
                                    onclick="deleteEmployeeFromButton(this)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'departments'): ?>
    <!-- Departments -->
    <div class="hr-form-card">
        <h2>Add Department</h2>
        <form method="POST" class="hr-form-grid">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_department">
            
            <div class="form-group">
                <label>Department Code *</label>
                <input type="text" name="department_code" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Department Name *</label>
                <input type="text" name="department_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>Manager</label>
                <select name="manager_id" class="form-control">
                    <option value="">Select Manager</option>
                    <?php foreach ($managers as $mgr): ?>
                        <option value="<?php echo $mgr['id']; ?>"><?php echo e($mgr['worker_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Add Department</button>
            </div>
        </form>
    </div>

    <div class="hr-table-wrapper">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Manager</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($departments as $dept): ?>
                    <tr>
                        <td><?php echo e($dept['department_code']); ?></td>
                        <td><?php echo e($dept['department_name']); ?></td>
                        <td><?php echo e($dept['manager_name'] ?? '-'); ?></td>
                        <td><?php echo $dept['employee_count']; ?></td>
                        <td>
                            <span class="badge badge-<?php echo $dept['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $dept['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" onclick="editDepartment(<?php echo $dept['id']; ?>)">Edit</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteDepartment(<?php echo $dept['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'positions'): ?>
    <!-- Positions -->
    <div class="hr-form-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 id="positionFormTitle">Add Position</h2>
            <button type="button" class="btn btn-primary" onclick="showPositionModal()">Add New Position</button>
        </div>
    </div>

    <div class="hr-table-wrapper">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Title</th>
                    <th>Department</th>
                    <th>Salary Range</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($positions)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--secondary);">
                            No positions defined. Click "Add New Position" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($positions as $pos): ?>
                        <tr>
                            <td><?php echo e($pos['position_code']); ?></td>
                            <td><?php echo e($pos['position_title']); ?></td>
                            <td><?php echo e($pos['department_name'] ?? '-'); ?></td>
                            <td>
                                <?php if ($pos['min_salary'] > 0 || $pos['max_salary'] > 0): ?>
                                    <?php echo formatCurrency($pos['min_salary']); ?> - <?php echo formatCurrency($pos['max_salary']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo $pos['employee_count']; ?></td>
                            <td>
                                <span class="badge badge-<?php echo $pos['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $pos['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary edit-position-btn" 
                                        data-position-id="<?php echo htmlspecialchars($pos['id']); ?>"
                                        data-position-code="<?php echo htmlspecialchars($pos['position_code']); ?>"
                                        data-position-title="<?php echo htmlspecialchars($pos['position_title']); ?>"
                                        data-position-dept="<?php echo htmlspecialchars($pos['department_id'] ?? ''); ?>"
                                        data-position-min-salary="<?php echo htmlspecialchars($pos['min_salary'] ?? 0); ?>"
                                        data-position-max-salary="<?php echo htmlspecialchars($pos['max_salary'] ?? 0); ?>"
                                        data-position-description="<?php echo htmlspecialchars($pos['description'] ?? ''); ?>"
                                        data-position-active="<?php echo $pos['is_active'] ? '1' : '0'; ?>"
                                        onclick="editPositionFromButton(this)">Edit</button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="deletePosition(<?php echo $pos['id']; ?>, '<?php echo e($pos['position_title']); ?>')">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Position Modal -->
    <div id="positionModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="positionModalTitle">Add Position</h2>
                <button type="button" class="modal-close" onclick="closePositionModal()">&times;</button>
            </div>
            <form method="POST" action="hr.php?action=positions" id="positionForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" id="positionAction" value="add_position">
                <input type="hidden" name="id" id="positionId" value="">
                
                <div class="form-group">
                    <label for="position_code" class="form-label">Position Code *</label>
                    <input type="text" id="position_code" name="position_code" class="form-control" required 
                           placeholder="e.g., POS001">
                </div>
                
                <div class="form-group">
                    <label for="position_title" class="form-label">Position Title *</label>
                    <input type="text" id="position_title" name="position_title" class="form-control" required 
                           placeholder="e.g., Senior Driller">
                </div>
                
                <div class="form-group">
                    <label for="position_department" class="form-label">Department</label>
                    <select id="position_department" name="department_id" class="form-control">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>"><?php echo e($dept['department_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="position_min_salary" class="form-label">Min Salary</label>
                    <input type="number" id="position_min_salary" name="min_salary" class="form-control" step="0.01" value="0">
                </div>
                
                <div class="form-group">
                    <label for="position_max_salary" class="form-label">Max Salary</label>
                    <input type="number" id="position_max_salary" name="max_salary" class="form-control" step="0.01" value="0">
                </div>
                
                <div class="form-group">
                    <label for="position_description" class="form-label">Description</label>
                    <textarea id="position_description" name="description" class="form-control" rows="3" 
                              placeholder="Brief description of this position"></textarea>
                </div>
                
                <div class="form-group" id="positionActiveGroup" style="display: none;">
                    <label>
                        <input type="checkbox" id="position_is_active" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-outline" onclick="closePositionModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function showPositionModal() {
        document.getElementById('positionModal').style.display = 'block';
        document.getElementById('positionModalTitle').textContent = 'Add Position';
        document.getElementById('positionAction').value = 'add_position';
        document.getElementById('positionId').value = '';
        document.getElementById('positionForm').reset();
        document.getElementById('positionActiveGroup').style.display = 'none';
        document.getElementById('position_is_active').checked = true;
    }
    
    function editPositionFromButton(button) {
        const positionId = button.getAttribute('data-position-id');
        const positionCode = button.getAttribute('data-position-code');
        const positionTitle = button.getAttribute('data-position-title');
        const positionDept = button.getAttribute('data-position-dept');
        const positionMinSalary = button.getAttribute('data-position-min-salary');
        const positionMaxSalary = button.getAttribute('data-position-max-salary');
        const positionDescription = button.getAttribute('data-position-description');
        const positionActive = button.getAttribute('data-position-active');
        
        if (!positionId) {
            alert('Error: Missing position ID');
            return;
        }
        
        document.getElementById('positionModal').style.display = 'block';
        document.getElementById('positionModalTitle').textContent = 'Edit Position';
        document.getElementById('positionAction').value = 'update_position';
        document.getElementById('positionId').value = positionId;
        document.getElementById('position_code').value = positionCode || '';
        document.getElementById('position_title').value = positionTitle || '';
        document.getElementById('position_department').value = positionDept || '';
        document.getElementById('position_min_salary').value = positionMinSalary || '0';
        document.getElementById('position_max_salary').value = positionMaxSalary || '0';
        document.getElementById('position_description').value = positionDescription || '';
        document.getElementById('position_is_active').checked = positionActive === '1';
        document.getElementById('positionActiveGroup').style.display = 'block';
    }
    
    function closePositionModal() {
        document.getElementById('positionModal').style.display = 'none';
    }
    
    function deletePosition(positionId, positionTitle) {
        if (!confirm(`Are you sure you want to delete position "${positionTitle}"?\n\nIf this position is assigned to employees, it will be deactivated instead of deleted.`)) {
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=positions';
        form.style.display = 'none';
        
        const csrfToken = document.querySelector('input[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_position';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = positionId;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
    
    // Close modal when clicking outside (only for position modal)
    document.addEventListener('click', function(event) {
        const positionModal = document.getElementById('positionModal');
        if (positionModal && event.target === positionModal) {
            closePositionModal();
        }
    });
    </script>

<?php elseif ($action === 'attendance'): ?>
    <!-- Attendance -->
    <div class="hr-form-card">
        <h2>Record Attendance</h2>
        <form method="POST" class="hr-form-grid">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="record_attendance">
            
            <div class="form-group">
                <label>Employee *</label>
                <select name="worker_id" class="form-control" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['id']; ?>">
                            <?php echo e($worker['employee_code'] ?? 'N/A'); ?> - <?php echo e($worker['worker_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="attendance_date" value="<?php echo date('Y-m-d'); ?>" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Time In</label>
                <input type="time" name="time_in" class="form-control">
            </div>
            <div class="form-group">
                <label>Time Out</label>
                <input type="time" name="time_out" class="form-control">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="attendance_status" class="form-control">
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                    <option value="half_day">Half Day</option>
                    <option value="leave">On Leave</option>
                    <option value="holiday">Holiday</option>
                </select>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Record Attendance</button>
            </div>
        </form>
    </div>

    <div style="margin-bottom: 24px;">
        <form method="GET" style="display: flex; gap: 12px; align-items: end;">
            <input type="hidden" name="action" value="attendance">
            <div class="form-group">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?php echo e($dateFrom ?? date('Y-m-01')); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?php echo e($dateTo ?? date('Y-m-t')); ?>" class="form-control">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>
    </div>

    <div class="hr-table-wrapper">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($attendanceRecords)): ?>
                    <?php foreach ($attendanceRecords as $record): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($record['attendance_date'])); ?></td>
                            <td><?php echo e($record['worker_name']); ?></td>
                            <td><?php echo $record['time_in'] ? date('H:i', strtotime($record['time_in'])) : '-'; ?></td>
                            <td><?php echo $record['time_out'] ? date('H:i', strtotime($record['time_out'])) : '-'; ?></td>
                            <td><?php echo number_format($record['total_hours'], 2); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $record['attendance_status'] === 'present' ? 'active' : 'inactive'; ?>">
                                    <?php echo ucfirst($record['attendance_status']); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editAttendance(<?php echo $record['id']; ?>)">Edit</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteAttendance(<?php echo $record['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: var(--secondary);">No attendance records found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'leave'): ?>
    <!-- Leave Management -->
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2>Submit Leave Request</h2>
            <form method="POST" class="hr-form-grid">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="add_leave_request">
                
                <div class="form-group">
                    <label>Employee *</label>
                    <select name="worker_id" class="form-control" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($workers as $worker): ?>
                            <option value="<?php echo $worker['id']; ?>">
                                <?php echo e($worker['employee_code'] ?? 'N/A'); ?> - <?php echo e($worker['worker_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Leave Type *</label>
                    <select name="leave_type_id" class="form-control" required>
                        <option value="">Select Leave Type</option>
                        <?php foreach ($leaveTypes as $type): ?>
                            <option value="<?php echo $type['id']; ?>"><?php echo e($type['leave_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="date" name="start_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="date" name="end_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
        
        <div class="dashboard-card">
            <h2>Leave Balances</h2>
            <div class="hr-table-wrapper" style="max-height: 400px; overflow-y: auto;">
                <table class="hr-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Leave Type</th>
                            <th>Allocated</th>
                            <th>Used</th>
                            <th>Remaining</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($leaveBalances)): ?>
                            <?php foreach ($leaveBalances as $balance): ?>
                                <tr>
                                    <td><?php echo e($balance['worker_name']); ?></td>
                                    <td><?php echo e($balance['leave_name']); ?></td>
                                    <td><?php echo $balance['allocated_days']; ?></td>
                                    <td><?php echo $balance['used_days']; ?></td>
                                    <td><strong><?php echo $balance['remaining_days']; ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px; color: var(--secondary);">No leave balances found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="hr-table-wrapper" style="margin-top: 24px;">
        <h2 style="margin-bottom: 16px;">Leave Requests</h2>
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Request Code</th>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($leaveRequests)): ?>
                    <?php foreach ($leaveRequests as $request): ?>
                        <tr>
                            <td><?php echo e($request['request_code']); ?></td>
                            <td><?php echo e($request['worker_name']); ?></td>
                            <td><?php echo e($request['leave_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($request['start_date'])); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($request['end_date'])); ?></td>
                            <td><?php echo $request['total_days']; ?></td>
                            <td>
                                <span class="badge badge-<?php 
                                    echo $request['status'] === 'approved' ? 'active' : 
                                        ($request['status'] === 'pending' ? 'pending' : 'inactive'); 
                                ?>">
                                    <?php echo ucfirst($request['status']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($request['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="approve_leave">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="approved" value="1">
                                        <button type="submit" class="btn btn-sm btn-primary">Approve</button>
                                    </form>
                                    <form method="POST" style="display: inline;">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="action" value="approve_leave">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        <input type="hidden" name="approved" value="0">
                                        <input type="text" name="rejection_reason" placeholder="Rejection reason" class="form-control" style="display: inline; width: 150px; margin-left: 5px;">
                                        <button type="submit" class="btn btn-sm btn-secondary">Reject</button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editLeaveRequest(<?php echo $request['id']; ?>)" style="margin-left: 5px;">Edit</button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteLeaveRequest(<?php echo $request['id']; ?>)">Delete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--secondary);">No leave requests found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'performance'): ?>
    <!-- Performance Reviews -->
    <div class="hr-form-card">
        <h2>Add Performance Review</h2>
        <form method="POST" class="hr-form-grid">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_performance_review">
            
            <div class="form-group">
                <label>Employee *</label>
                <select name="worker_id" class="form-control" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['id']; ?>">
                            <?php echo e($worker['employee_code'] ?? 'N/A'); ?> - <?php echo e($worker['worker_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Review Type</label>
                <select name="review_type" class="form-control">
                    <option value="annual">Annual</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="monthly">Monthly</option>
                    <option value="probation">Probation</option>
                    <option value="promotion">Promotion</option>
                </select>
            </div>
            <div class="form-group">
                <label>Review Period Start *</label>
                <input type="date" name="review_period_start" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Review Period End *</label>
                <input type="date" name="review_period_end" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Reviewer</label>
                <select name="reviewer_id" class="form-control">
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $user['id'] == $currentUserId ? 'selected' : ''; ?>>
                            <?php echo e($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Overall Rating (1-5)</label>
                <input type="number" name="overall_rating" min="1" max="5" step="0.1" class="form-control">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Strengths</label>
                <textarea name="strengths" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Areas for Improvement</label>
                <textarea name="areas_for_improvement" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Goals</label>
                <textarea name="goals" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label>Recommendations</label>
                <textarea name="recommendations" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Create Review</button>
            </div>
        </form>
    </div>

    <div class="hr-table-wrapper">
        <h2 style="margin-bottom: 16px;">Performance Reviews</h2>
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Review Code</th>
                    <th>Employee</th>
                    <th>Review Period</th>
                    <th>Type</th>
                    <th>Rating</th>
                    <th>Reviewer</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($performanceReviews)): ?>
                    <?php foreach ($performanceReviews as $review): ?>
                        <tr>
                            <td><?php echo e($review['review_code']); ?></td>
                            <td><?php echo e($review['worker_name']); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($review['review_period_start'])); ?> to <?php echo date('Y-m-d', strtotime($review['review_period_end'])); ?></td>
                            <td><?php echo ucfirst($review['review_type']); ?></td>
                            <td><?php echo $review['overall_rating'] ? number_format($review['overall_rating'], 2) : '-'; ?></td>
                            <td><?php echo e($review['reviewer_name'] ?? '-'); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $review['status'] === 'completed' ? 'active' : 'pending'; ?>">
                                    <?php echo ucfirst($review['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editPerformanceReview(<?php echo $review['id']; ?>)">Edit</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deletePerformanceReview(<?php echo $review['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: var(--secondary);">No performance reviews found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

<?php elseif ($action === 'training'): ?>
    <!-- Training Management -->
    <div class="hr-form-card">
        <h2>Add Training Record</h2>
        <form method="POST" class="hr-form-grid">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_training">
            
            <div class="form-group">
                <label>Employee *</label>
                <select name="worker_id" class="form-control" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($workers as $worker): ?>
                        <option value="<?php echo $worker['id']; ?>">
                            <?php echo e($worker['employee_code'] ?? 'N/A'); ?> - <?php echo e($worker['worker_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Training Title *</label>
                <input type="text" name="training_title" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Training Type</label>
                <select name="training_type" class="form-control">
                    <option value="internal">Internal</option>
                    <option value="external">External</option>
                    <option value="online">Online</option>
                    <option value="certification">Certification</option>
                </select>
            </div>
            <div class="form-group">
                <label>Provider</label>
                <input type="text" name="provider" class="form-control">
            </div>
            <div class="form-group">
                <label>Start Date *</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-control">
            </div>
            <div class="form-group">
                <label>Duration (Hours)</label>
                <input type="number" name="duration_hours" step="0.5" class="form-control">
            </div>
            <div class="form-group">
                <label>Cost</label>
                <input type="number" name="cost" step="0.01" class="form-control">
            </div>
            <div class="form-group">
                <label>Certificate Number</label>
                <input type="text" name="certificate_number" class="form-control">
            </div>
            <div class="form-group">
                <label>Certificate Expiry</label>
                <input type="date" name="certificate_expiry" class="form-control">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Add Training</button>
            </div>
        </form>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2>Training Records</h2>
            <div class="hr-table-wrapper" style="max-height: 400px; overflow-y: auto;">
                <table class="hr-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Employee</th>
                            <th>Training</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($trainingRecords)): ?>
                            <?php foreach ($trainingRecords as $training): ?>
                                <tr>
                                    <td><?php echo e($training['training_code']); ?></td>
                                    <td><?php echo e($training['worker_name']); ?></td>
                                    <td><?php echo e($training['training_title']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($training['start_date'])); ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $training['status'] === 'completed' ? 'active' : 'pending'; ?>">
                                            <?php echo ucfirst($training['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-primary" onclick="editTraining(<?php echo $training['id']; ?>)">Edit</button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteTraining(<?php echo $training['id']; ?>)">Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px; color: var(--secondary);">No training records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="dashboard-card">
            <h2>Skills Inventory</h2>
            <div class="hr-table-wrapper" style="max-height: 400px; overflow-y: auto;">
                <table class="hr-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Skill</th>
                            <th>Level</th>
                            <th>Certified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($skills)): ?>
                            <?php foreach ($skills as $skill): ?>
                                <tr>
                                    <td><?php echo e($skill['worker_name']); ?></td>
                                    <td><?php echo e($skill['skill_name']); ?></td>
                                    <td><?php echo ucfirst($skill['proficiency_level']); ?></td>
                                    <td><?php echo $skill['certified'] ? 'Yes' : 'No'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; padding: 20px; color: var(--secondary);">No skills recorded</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($action === 'worker-detail'): ?>
    <!-- Worker Detail - Jobs and Activity -->
    <?php
    $workerId = intval($_GET['worker_id'] ?? 0);
    if ($workerId <= 0) {
        flash('error', 'Invalid worker ID');
        redirect('hr.php?action=employees');
    }
    
    // Get worker details
    try {
        $workerStmt = $pdo->prepare("
            SELECT w.*, 
                   d.department_name, 
                   p.position_title,
                   (SELECT COUNT(DISTINCT fr.id) FROM payroll_entries pe 
                    INNER JOIN field_reports fr ON pe.report_id = fr.id 
                    WHERE pe.worker_id = w.id) as total_jobs,
                   (SELECT COUNT(DISTINCT fr.id) FROM payroll_entries pe 
                    INNER JOIN field_reports fr ON pe.report_id = fr.id 
                    WHERE pe.worker_id = w.id AND fr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as jobs_last_month
            FROM workers w
            LEFT JOIN departments d ON w.department_id = d.id
            LEFT JOIN positions p ON w.position_id = p.id
            WHERE w.id = ?
        ");
        $workerStmt->execute([$workerId]);
        $worker = $workerStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$worker) {
            flash('error', 'Worker not found');
            redirect('hr.php?action=employees');
        }
        
        // Get worker statistics
        $statsStmt = $pdo->prepare("SELECT * FROM worker_statistics WHERE worker_id = ?");
        $statsStmt->execute([$workerId]);
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
        
        // Get all jobs for this worker
        $jobsStmt = $pdo->prepare("
            SELECT 
                fr.id,
                fr.report_id,
                fr.report_date,
                fr.site_name,
                fr.rig_id,
                r.rig_name,
                r.rig_code,
                fr.client_id,
                c.client_name,
                fr.job_type,
                pe.amount as wage_amount,
                pe.paid_today,
                fr.total_rpm,
                fr.total_depth,
                pe.role as job_role
            FROM payroll_entries pe
            INNER JOIN field_reports fr ON pe.report_id = fr.id
            LEFT JOIN rigs r ON fr.rig_id = r.id
            LEFT JOIN clients c ON fr.client_id = c.id
            WHERE pe.worker_id = ?
            ORDER BY fr.report_date DESC
        ");
        $jobsStmt->execute([$workerId]);
        $jobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get weekly summary
        $weeklyStmt = $pdo->prepare("
            SELECT * FROM worker_weekly_jobs 
            WHERE worker_id = ?
            ORDER BY year DESC, week DESC
            LIMIT 12
        ");
        $weeklyStmt->execute([$workerId]);
        $weeklyData = $weeklyStmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        flash('error', 'Error loading worker data: ' . $e->getMessage());
        redirect('hr.php?action=employees');
    }
    ?>
    
    <div class="hr-form-card" style="margin-bottom: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
                <h2 style="margin: 0;"><?php echo e($worker['worker_name']); ?></h2>
                <p style="color: var(--secondary); margin: 5px 0 0 0;">
                    <?php echo e($worker['role']); ?> | 
                    <?php echo e($worker['department_name'] ?? 'No Department'); ?> | 
                    <?php echo e($worker['position_title'] ?? 'No Position'); ?>
                </p>
            </div>
            <a href="hr.php?action=employees" class="btn btn-outline">← Back to Employees</a>
        </div>
        
        <!-- Statistics Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
            <div style="background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                <div style="color: var(--secondary); font-size: 13px; margin-bottom: 5px;">Total Jobs</div>
                <div style="font-size: 28px; font-weight: bold; color: var(--primary);">
                    <?php echo $stats['total_jobs'] ?? count($jobs); ?>
                </div>
            </div>
            <div style="background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                <div style="color: var(--secondary); font-size: 13px; margin-bottom: 5px;">Jobs This Month</div>
                <div style="font-size: 28px; font-weight: bold; color: var(--primary);">
                    <?php echo $stats['jobs_last_month'] ?? 0; ?>
                </div>
            </div>
            <div style="background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                <div style="color: var(--secondary); font-size: 13px; margin-bottom: 5px;">Total Wages</div>
                <div style="font-size: 28px; font-weight: bold; color: var(--primary);">
                    GHS <?php echo number_format($stats['total_wages'] ?? 0, 2); ?>
                </div>
            </div>
            <div style="background: var(--card); padding: 20px; border-radius: 8px; border: 1px solid var(--border);">
                <div style="color: var(--secondary); font-size: 13px; margin-bottom: 5px;">Rigs Worked On</div>
                <div style="font-size: 28px; font-weight: bold; color: var(--primary);">
                    <?php echo $stats['rigs_worked_on'] ?? 0; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Weekly Summary -->
    <?php if (!empty($weeklyData)): ?>
    <div class="hr-form-card" style="margin-bottom: 20px;">
        <h3>📅 Weekly Job Summary (Last 12 Weeks)</h3>
        <div class="table-responsive">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th>Week</th>
                        <th>Jobs</th>
                        <th>Sites</th>
                        <th>Total Wages</th>
                        <th>RPM</th>
                        <th>Rigs</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($weeklyData as $week): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($week['week_start'])); ?> (Week <?php echo $week['week']; ?>)</td>
                        <td><strong><?php echo $week['jobs_count']; ?></strong></td>
                        <td><?php echo e($week['sites']); ?></td>
                        <td>GHS <?php echo number_format($week['total_wages'], 2); ?></td>
                        <td><?php echo number_format($week['total_rpm'], 2); ?></td>
                        <td><?php echo $week['rigs_count']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- All Jobs -->
    <div class="hr-form-card">
        <h3>📋 All Jobs (<?php echo count($jobs); ?>)</h3>
        <div class="table-responsive">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Report ID</th>
                        <th>Site</th>
                        <th>Rig</th>
                        <th>Client</th>
                        <th>Job Type</th>
                        <th>Role</th>
                        <th>Wage</th>
                        <th>RPM</th>
                        <th>Depth</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; padding: 40px; color: var(--secondary);">
                            No jobs found for this worker.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($job['report_date'])); ?></td>
                            <td>
                                <a href="field-reports-list.php?id=<?php echo $job['id']; ?>" 
                                   style="color: var(--primary); text-decoration: none;">
                                    <?php echo e($job['report_id']); ?>
                                </a>
                            </td>
                            <td><?php echo e($job['site_name']); ?></td>
                            <td><?php echo e($job['rig_name'] ?? 'N/A'); ?> (<?php echo e($job['rig_code'] ?? 'N/A'); ?>)</td>
                            <td><?php echo e($job['client_name'] ?? 'N/A'); ?></td>
                            <td><span class="badge"><?php echo ucfirst($job['job_type']); ?></span></td>
                            <td><?php echo e($job['job_role']); ?></td>
                            <td>GHS <?php echo number_format($job['wage_amount'], 2); ?></td>
                            <td><?php echo number_format($job['total_rpm'] ?? 0, 2); ?></td>
                            <td><?php echo number_format($job['total_depth'] ?? 0, 2); ?>m</td>
                            <td>
                                <span class="badge badge-<?php echo $job['paid_today'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $job['paid_today'] ? 'Paid' : 'Unpaid'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="field-reports-list.php?id=<?php echo $job['id']; ?>" 
                                   class="btn btn-sm btn-primary">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($action === 'stakeholders'): ?>
    <!-- Stakeholders -->
    <div class="hr-form-card">
        <h2>Add Stakeholder</h2>
        <form method="POST" class="hr-form-grid">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_stakeholder">
            
            <div class="form-group">
                <label>Stakeholder Code *</label>
                <input type="text" name="stakeholder_code" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Type *</label>
                <select name="stakeholder_type" class="form-control" required>
                    <option value="">Select Type</option>
                    <option value="board_member">Board Member</option>
                    <option value="investor">Investor</option>
                    <option value="partner">Partner</option>
                    <option value="advisor">Advisor</option>
                    <option value="consultant">Consultant</option>
                    <option value="vendor">Vendor</option>
                    <option value="supplier">Supplier</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Organization</label>
                <input type="text" name="organization" class="form-control">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="submit" class="btn btn-primary">Add Stakeholder</button>
            </div>
        </form>
    </div>

    <div class="hr-table-wrapper">
        <table class="hr-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Organization</th>
                    <th>Contact</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stakeholders as $stakeholder): ?>
                    <tr>
                        <td><?php echo e($stakeholder['stakeholder_code']); ?></td>
                        <td><?php echo e($stakeholder['full_name']); ?></td>
                        <td><?php echo ucfirst(str_replace('_', ' ', $stakeholder['stakeholder_type'])); ?></td>
                        <td><?php echo e($stakeholder['organization'] ?? '-'); ?></td>
                        <td>
                            <?php if ($stakeholder['email']): ?>
                                <?php echo e($stakeholder['email']); ?><br>
                            <?php endif; ?>
                            <?php if ($stakeholder['phone']): ?>
                                <?php echo e($stakeholder['phone']); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $stakeholder['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $stakeholder['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="hr.php?action=stakeholders&view=<?php echo $stakeholder['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                            <button type="button" class="btn btn-sm btn-primary" onclick="editStakeholder(<?php echo $stakeholder['id']; ?>)">Edit</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteStakeholder(<?php echo $stakeholder['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($viewStakeholderId > 0): ?>
        <?php 
        $selectedStakeholder = null;
        foreach ($stakeholders as $s) {
            if ($s['id'] == $viewStakeholderId) {
                $selectedStakeholder = $s;
                break;
            }
        }
        ?>
        <?php if ($selectedStakeholder): ?>
            <div class="hr-form-card" style="margin-top: 24px;">
                <h2>Stakeholder: <?php echo e($selectedStakeholder['full_name']); ?></h2>
                
                <div style="margin-bottom: 24px;">
                    <h3>Log Communication</h3>
                    <form method="POST" class="hr-form-grid">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="add_stakeholder_communication">
                        <input type="hidden" name="stakeholder_id" value="<?php echo $selectedStakeholder['id']; ?>">
                        
                        <div class="form-group">
                            <label>Communication Type *</label>
                            <select name="communication_type" class="form-control" required>
                                <option value="">Select Type</option>
                                <option value="meeting">Meeting</option>
                                <option value="email">Email</option>
                                <option value="phone">Phone</option>
                                <option value="letter">Letter</option>
                                <option value="report">Report</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date & Time</label>
                            <input type="datetime-local" name="communication_date" value="<?php echo date('Y-m-d\TH:i'); ?>" class="form-control">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Subject</label>
                            <input type="text" name="subject" class="form-control">
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Message/Notes</label>
                            <textarea name="message" class="form-control" rows="4"></textarea>
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Log Communication</button>
                        </div>
                    </form>
                </div>
                
                <div>
                    <h3>Communication History</h3>
                    <?php if (!empty($stakeholderCommunications)): ?>
                        <div class="hr-table-wrapper">
                            <table class="hr-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Message</th>
                                        <th>Initiated By</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stakeholderCommunications as $comm): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($comm['communication_date'])); ?></td>
                                            <td><?php echo ucfirst($comm['communication_type']); ?></td>
                                            <td><?php echo e($comm['subject'] ?? '-'); ?></td>
                                            <td><?php echo e(substr($comm['message'] ?? '', 0, 100)) . (strlen($comm['message'] ?? '') > 100 ? '...' : ''); ?></td>
                                            <td><?php echo e($comm['initiated_by_user'] ?? '-'); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editStakeholderCommunication(<?php echo $comm['id']; ?>, <?php echo $selectedStakeholder['id']; ?>)">Edit</button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteStakeholderCommunication(<?php echo $comm['id']; ?>, <?php echo $selectedStakeholder['id']; ?>)">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--secondary); padding: 20px;">No communications logged yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

<?php elseif ($action === 'roles'): ?>
    <!-- Worker Roles Management -->
    <?php
    // Get worker roles if config-manager is available
    $workerRoles = [];
    $activeWorkerRoleNames = [];
    try {
        if (file_exists(__DIR__ . '/../includes/config-manager.php')) {
            require_once __DIR__ . '/../includes/config-manager.php';
            if (class_exists('ConfigManager')) {
                $configManager = new ConfigManager();
                $workerRoles = $configManager->getAllWorkerRoles();
                $activeWorkerRoleNames = $configManager->getAllWorkerRoleNames();
            }
        }
    } catch (Exception $e) {
        // Fallback: empty array
        $workerRoles = [];
    }
    ?>
    <div class="hr-form-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Worker Roles Management</h2>
            <button type="button" class="btn btn-primary" onclick="showRoleModal()">Add New Role</button>
        </div>
        <div class="hr-table-wrapper">
            <table class="hr-table">
                <thead>
                    <tr>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Workers Using</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($workerRoles)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: var(--secondary);">
                                No roles defined. Click "Add New Role" to create one.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($workerRoles as $role): ?>
                            <tr>
                                <td><strong><?php echo e($role['role_name']); ?></strong></td>
                                <td><?php echo e($role['description'] ?? 'N/A'); ?></td>
                                <td><?php echo (int)($role['worker_count'] ?? 0); ?></td>
                                <td>
                                    <?php if ($role['is_system']): ?>
                                        <span style="color: var(--primary);">System</span>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">Custom</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($role['is_active']): ?>
                                        <span class="badge badge-active">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$role['is_system']): ?>
                                        <button type="button" class="btn btn-sm btn-primary edit-role-btn" 
                                                data-role-id="<?php echo htmlspecialchars($role['id']); ?>"
                                                data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>"
                                                data-role-description="<?php echo htmlspecialchars($role['description'] ?? ''); ?>"
                                                data-role-system="<?php echo $role['is_system'] ? '1' : '0'; ?>"
                                                onclick="editRoleFromButton(this)">Edit</button>
                                        <button type="button" class="btn btn-sm btn-outline" onclick="toggleRole(<?php echo $role['id']; ?>, <?php echo $role['is_active'] ? 'false' : 'true'; ?>)">
                                            <?php echo $role['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteRole(<?php echo $role['id']; ?>)">Delete</button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-primary" disabled style="opacity: 0.5; cursor: not-allowed;">Edit</button>
                                        <span style="color: var(--secondary); font-size: 12px; margin-left: 8px;">Protected</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Worker Role Modal -->
    <div id="roleModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="roleModalTitle">Add New Role</h2>
                <button type="button" class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <form method="POST" action="hr.php?action=roles" id="roleForm">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" id="roleAction" value="add_worker_role">
                <input type="hidden" name="id" id="roleId" value="">
                
                <div class="form-group">
                    <label for="role_name" class="form-label">Role Name *</label>
                    <input type="text" id="role_name" name="role_name" class="form-control" required 
                           placeholder="e.g., Driller, Supervisor, Rod Boy">
                </div>
                
                <div class="form-group">
                    <label for="role_description" class="form-label">Description</label>
                    <textarea id="role_description" name="description" class="form-control" rows="3" 
                              placeholder="Brief description of this role"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save</button>
                    <button type="button" class="btn btn-outline" onclick="closeRoleModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>

<!-- Include JavaScript for worker management features -->
<script src="../assets/js/config.js"></script>
<script>
// Worker duplicate analysis and export functions
function analyzeWorkerDuplicates() {
    // Open modal for duplicate analysis
    fetch('../api/config-crud.php?action=analyze_duplicates', {
        method: 'GET',
        headers: {'Content-Type': 'application/json'}
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Show results in modal
            alert('Duplicate analysis complete. Check console for details.');
            console.log('Duplicates:', data.duplicates);
        } else {
            alert('Analysis failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        alert('Error: ' + err.message);
    });
}

function exportWorkers() {
    // Export workers to CSV
    window.location.href = '../api/export.php?type=workers';
}

// Edit employee from button (using data attributes)
function editEmployeeFromButton(button) {
    try {
        const workerId = button.getAttribute('data-worker-id');
        if (!workerId) {
            console.error('Missing worker ID');
            alert('Error: Missing worker ID. Please refresh the page and try again.');
            return;
        }
        
        const workerData = {
            id: workerId,
            employee_code: button.getAttribute('data-worker-code') || '',
            worker_name: button.getAttribute('data-worker-name') || '',
            role: button.getAttribute('data-worker-role') || '',
            employee_type: button.getAttribute('data-worker-type') || 'worker',
            default_rate: button.getAttribute('data-worker-rate') || 0,
            contact_number: button.getAttribute('data-worker-contact') || '',
            email: button.getAttribute('data-worker-email') || '',
            department_id: button.getAttribute('data-worker-dept') || '',
            position_id: button.getAttribute('data-worker-position') || '',
            hire_date: button.getAttribute('data-worker-hire-date') || '',
            status: button.getAttribute('data-worker-status') || 'active'
        };
        
        editEmployee(workerData);
    } catch (error) {
        console.error('Error in editEmployeeFromButton:', error);
        alert('An error occurred while trying to edit the employee. Please check the console for details.');
    }
}

// Employee edit/delete functions
function editEmployee(worker) {
    try {
        // Handle both object and JSON string
        let workerData = worker;
        if (typeof worker === 'string') {
            try {
                workerData = JSON.parse(worker);
            } catch (e) {
                console.error('Error parsing worker data:', e);
                alert('Error loading employee data. Please refresh the page and try again.');
                return;
            }
        }
        
        // Validate required elements exist
        const formTitle = document.getElementById('employeeFormTitle');
        const actionInput = document.getElementById('employeeAction');
        const workerIdInput = document.getElementById('employeeWorkerId');
        const submitBtn = document.getElementById('employeeSubmitBtn');
        const cancelBtn = document.getElementById('employeeCancelBtn');
        const form = document.getElementById('employeeForm');
        
        if (!formTitle || !actionInput || !workerIdInput || !submitBtn || !form) {
            console.error('Required form elements not found:', {
                formTitle: !!formTitle,
                actionInput: !!actionInput,
                workerIdInput: !!workerIdInput,
                submitBtn: !!submitBtn,
                form: !!form
            });
            alert('Error: Form elements not found. Please refresh the page.');
            return;
        }
        
        // Update form title and action
        formTitle.textContent = 'Edit Employee';
        actionInput.value = 'update_employee';
        workerIdInput.value = workerData.id || workerData.worker_id || '';
        submitBtn.textContent = 'Update Employee';
        if (cancelBtn) {
            cancelBtn.style.display = 'inline-block';
        }
        
        // Fill form fields
        const codeInput = document.querySelector('[name="employee_code"]');
        if (codeInput) {
            codeInput.value = workerData.employee_code || workerData.code || '';
        }
        
        const nameInput = document.querySelector('[name="worker_name"]');
        if (nameInput) {
            nameInput.value = workerData.worker_name || workerData.name || '';
        }
        
        const roleInput = document.querySelector('[name="role"]');
        if (roleInput) {
            roleInput.value = workerData.role || '';
        }
        
        const typeInput = document.querySelector('[name="employee_type"]');
        if (typeInput) {
            typeInput.value = workerData.employee_type || workerData.type || 'worker';
        }
        
        const rateInput = document.querySelector('[name="default_rate"]');
        if (rateInput) {
            rateInput.value = workerData.default_rate || workerData.rate || 0;
        }
        
        const contactInput = document.querySelector('[name="contact_number"]');
        if (contactInput) {
            contactInput.value = workerData.contact_number || workerData.contact || '';
        }
        
        const emailInput = document.querySelector('[name="email"]');
        if (emailInput) {
            emailInput.value = workerData.email || '';
        }
        
        const deptInput = document.querySelector('[name="department_id"]');
        if (deptInput) {
            deptInput.value = workerData.department_id || workerData.department || '';
        }
        
        const positionInput = document.querySelector('[name="position_id"]');
        if (positionInput) {
            positionInput.value = workerData.position_id || workerData.position || '';
        }
        
        const statusInput = document.getElementById('employeeStatus');
        if (statusInput) {
            statusInput.value = workerData.status || 'active';
        }
        
        const hireDateInput = document.querySelector('[name="hire_date"]');
        if (hireDateInput && workerData.hire_date) {
            hireDateInput.value = workerData.hire_date;
        }
        
        // Load and display roles and rigs
        const workerId = workerData.id || workerData.worker_id;
        if (workerId) {
            try {
                loadWorkerRoles(workerId);
                loadWorkerRigs(workerId);
            } catch (e) {
                console.warn('Error loading roles/rigs:', e);
            }
            
            // Show manage buttons
            const manageRolesBtn = document.getElementById('manageRolesBtn');
            const manageRigsBtn = document.getElementById('manageRigsBtn');
            if (manageRolesBtn) {
                manageRolesBtn.style.display = 'inline-block';
            }
            if (manageRigsBtn) {
                manageRigsBtn.style.display = 'inline-block';
            }
        }
        
        // Scroll to form
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Focus on name field for better UX
        if (nameInput) {
            setTimeout(() => {
                nameInput.focus();
            }, 300);
        }
    } catch (error) {
        console.error('Error in editEmployee function:', error);
        console.error('Error stack:', error.stack);
        alert('An error occurred while opening the edit form. Please check the console for details.');
    }
}

function resetEmployeeForm() {
    document.getElementById('employeeFormTitle').textContent = 'Add New Employee';
    document.getElementById('employeeAction').value = 'add_employee';
    document.getElementById('employeeWorkerId').value = '';
    document.getElementById('employeeSubmitBtn').textContent = 'Add Employee';
    document.getElementById('employeeCancelBtn').style.display = 'none';
    document.getElementById('employeeForm').reset();
    
    // Reset role and rig displays
    document.getElementById('workerRolesDisplay').innerHTML = '<small style="color: var(--secondary);">Save worker first to manage roles</small>';
    document.getElementById('workerRigsDisplay').innerHTML = '<small style="color: var(--secondary);">Save worker first to manage rig preferences</small>';
    document.getElementById('manageRolesBtn').style.display = 'none';
    document.getElementById('manageRigsBtn').style.display = 'none';
}

// Load and display worker roles
async function loadWorkerRoles(workerId) {
    if (!workerId) return;
    
    try {
        const response = await fetch(`../api/worker-role-assignments.php?action=get_worker_roles&worker_id=${workerId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            displayWorkerRoles(result.data);
        } else {
            document.getElementById('workerRolesDisplay').innerHTML = '<small style="color: var(--secondary);">No roles assigned</small>';
        }
    } catch (error) {
        console.error('Error loading worker roles:', error);
        document.getElementById('workerRolesDisplay').innerHTML = '<small style="color: #dc3545;">Error loading roles</small>';
    }
}

function displayWorkerRoles(roles) {
    const display = document.getElementById('workerRolesDisplay');
    
    if (roles.length === 0) {
        display.innerHTML = '<small style="color: var(--secondary);">No roles assigned</small>';
        return;
    }
    
    let html = '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
    roles.forEach(role => {
        const badgeColor = role.is_primary ? '#28a745' : '#007bff';
        html += `
            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: ${badgeColor}; color: white; border-radius: 6px; font-size: 12px; font-weight: 600;">
                ${role.is_primary ? '⭐' : ''} ${escapeHtml(role.role_name)}
                ${role.default_rate ? ` (${parseFloat(role.default_rate).toFixed(2)} GHS)` : ''}
            </span>
        `;
    });
    html += '</div>';
    display.innerHTML = html;
}

// Load and display worker rigs
async function loadWorkerRigs(workerId) {
    if (!workerId) return;
    
    try {
        const response = await fetch(`../api/worker-rig-preferences.php?action=get_worker_rigs&worker_id=${workerId}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            displayWorkerRigs(result.data);
        } else {
            document.getElementById('workerRigsDisplay').innerHTML = '<small style="color: var(--secondary);">No rig preferences set</small>';
        }
    } catch (error) {
        console.error('Error loading worker rigs:', error);
        document.getElementById('workerRigsDisplay').innerHTML = '<small style="color: #dc3545;">Error loading rig preferences</small>';
    }
}

function displayWorkerRigs(rigs) {
    const display = document.getElementById('workerRigsDisplay');
    
    if (rigs.length === 0) {
        display.innerHTML = '<small style="color: var(--secondary);">No rig preferences set</small>';
        return;
    }
    
    let html = '<div style="display: flex; flex-wrap: wrap; gap: 8px;">';
    rigs.forEach(rig => {
        let badgeColor = '#6c757d';
        let levelText = '';
        if (rig.preference_level === 'primary') {
            badgeColor = '#28a745';
            levelText = '⭐ Primary';
        } else if (rig.preference_level === 'secondary') {
            badgeColor = '#ffc107';
            levelText = 'Secondary';
        } else {
            badgeColor = '#6c757d';
            levelText = 'Occasional';
        }
        
        html += `
            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; background: ${badgeColor}; color: white; border-radius: 6px; font-size: 12px; font-weight: 600;">
                ${levelText} - ${escapeHtml(rig.rig_name || rig.rig_code)}
            </span>
        `;
    });
    html += '</div>';
    display.innerHTML = html;
}

function escapeHtml(text) {
    if (text == null) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Role Management Modal Functions
let currentWorkerIdForRoles = null;

async function showRoleManagementModal() {
    const workerId = document.getElementById('employeeWorkerId').value;
    if (!workerId) {
        alert('Please save the worker first before managing roles');
        return;
    }
    
    currentWorkerIdForRoles = workerId;
    const modal = document.getElementById('roleManagementModal');
    const content = document.getElementById('roleManagementContent');
    const title = document.getElementById('roleManagementTitle');
    
    // Get worker name
    const workerName = document.querySelector('[name="worker_name"]').value;
    title.textContent = `Manage Roles - ${workerName}`;
    
    modal.style.display = 'flex';
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Loading roles...</p>
        </div>
    `;
    
    try {
        // Load current roles and available roles
        const [currentRolesRes, availableRolesRes] = await Promise.all([
            fetch(`../api/worker-role-assignments.php?action=get_worker_roles&worker_id=${workerId}`),
            fetch(`../api/worker-role-assignments.php?action=get_available_roles&worker_id=${workerId}`)
        ]);
        
        const currentRoles = await currentRolesRes.json();
        const availableRoles = await availableRolesRes.json();
        
        if (!currentRoles.success || !availableRoles.success) {
            throw new Error('Failed to load roles');
        }
        
        renderRoleManagementContent(currentRoles.data, availableRoles.data);
    } catch (error) {
        console.error('Error loading roles:', error);
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ Error</h3>
                <p>${error.message || 'Failed to load roles'}</p>
                <button type="button" class="btn btn-outline" onclick="closeRoleManagementModal()" style="margin-top: 20px;">Close</button>
            </div>
        `;
    }
}

function renderRoleManagementContent(currentRoles, availableRoles) {
    const content = document.getElementById('roleManagementContent');
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">Current Roles</h3>
            <div id="currentRolesList" style="margin-bottom: 20px;">
    `;
    
    if (currentRoles.length === 0) {
        html += '<p style="color: var(--secondary); padding: 20px; text-align: center; background: #f8f9fa; border-radius: 6px;">No roles assigned</p>';
    } else {
        currentRoles.forEach(role => {
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: white; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <strong>${escapeHtml(role.role_name)}</strong>
                            ${role.is_primary ? '<span style="background: #28a745; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">Primary</span>' : ''}
                        </div>
                        <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                            Default Rate: ${role.default_rate ? parseFloat(role.default_rate).toFixed(2) + ' GHS' : 'Not set'}
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-sm btn-primary" onclick="editRoleAssignment(${role.id}, '${escapeHtml(role.role_name)}', ${role.is_primary}, ${role.default_rate || 0})">Edit</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeRoleAssignment(${role.id})">Remove</button>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
            </div>
            
            <h3 style="margin-bottom: 15px;">Add New Role</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                <div class="form-group">
                    <label>Select Role</label>
                    <select id="newRoleSelect" class="form-control">
                        <option value="">-- Select Role --</option>
    `;
    
    availableRoles.forEach(role => {
        if (!role.is_assigned) {
            html += `<option value="${escapeHtml(role.role_name)}">${escapeHtml(role.role_name)}</option>`;
        }
    });
    
    html += `
                    </select>
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="setAsPrimary"> Set as Primary Role
                    </label>
                </div>
                <div class="form-group">
                    <label>Default Rate (GHS) - Optional</label>
                    <input type="number" id="newRoleRate" class="form-control" step="0.01" min="0" placeholder="Leave empty to use worker's default rate">
                </div>
                <button type="button" class="btn btn-primary" onclick="addRoleAssignment()">Add Role</button>
            </div>
        </div>
        <div style="margin-top: 20px; text-align: center;">
            <button type="button" class="btn btn-primary" onclick="closeRoleManagementModal()">Close</button>
        </div>
    `;
    
    content.innerHTML = html;
}

// Add role assignment
async function addRoleAssignment() {
    const workerId = currentWorkerIdForRoles;
    const roleName = document.getElementById('newRoleSelect').value;
    const isPrimary = document.getElementById('setAsPrimary').checked ? 1 : 0;
    const defaultRate = document.getElementById('newRoleRate').value || null;
    
    if (!roleName) {
        alert('Please select a role');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'add_role');
        formData.append('worker_id', workerId);
        formData.append('role_name', roleName);
        formData.append('is_primary', isPrimary);
        if (defaultRate) {
            formData.append('default_rate', defaultRate);
        }
        
        const response = await fetch('../api/worker-role-assignments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload roles
            await showRoleManagementModal();
            // Reload display in form
            loadWorkerRoles(workerId);
            // Update primary role in form if set as primary
            if (isPrimary) {
                document.querySelector('[name="role"]').value = roleName;
            }
        } else {
            alert('Error: ' + (result.message || 'Failed to add role'));
        }
    } catch (error) {
        console.error('Error adding role:', error);
        // Try to parse error response
        if (error.response) {
            error.response.json().then(result => {
                alert('Error: ' + (result.message || error.message));
            }).catch(() => {
                alert('Error adding role: ' + error.message);
            });
        } else {
            alert('Error adding role: ' + error.message);
        }
    }
}

// Edit role assignment
async function editRoleAssignment(id, roleName, isPrimary, defaultRate) {
    const newIsPrimary = prompt('Set as primary role? (1 for yes, 0 for no)', isPrimary ? '1' : '0');
    if (newIsPrimary === null) return;
    
    const newRate = prompt('Default Rate (GHS) - Leave empty to keep current:', defaultRate || '');
    if (newRate === null) return;
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'update_role');
        formData.append('id', id);
        formData.append('is_primary', parseInt(newIsPrimary));
        formData.append('default_rate', newRate || null);
        
        const response = await fetch('../api/worker-role-assignments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const workerId = currentWorkerIdForRoles;
            await showRoleManagementModal();
            loadWorkerRoles(workerId);
            
            // Update primary role in form if set as primary
            if (parseInt(newIsPrimary)) {
                document.querySelector('[name="role"]').value = roleName;
            }
        } else {
            alert('Error: ' + (result.message || 'Failed to update role'));
        }
    } catch (error) {
        console.error('Error updating role:', error);
        alert('Error updating role: ' + error.message);
    }
}

// Remove role assignment
async function removeRoleAssignment(id) {
    if (!confirm('Are you sure you want to remove this role assignment?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'remove_role');
        formData.append('id', id);
        
        const response = await fetch('../api/worker-role-assignments.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const workerId = currentWorkerIdForRoles;
            await showRoleManagementModal();
            loadWorkerRoles(workerId);
        } else {
            alert('Error: ' + (result.message || 'Failed to remove role'));
        }
    } catch (error) {
        console.error('Error removing role:', error);
        alert('Error removing role: ' + error.message);
    }
}

function closeRoleManagementModal() {
    document.getElementById('roleManagementModal').style.display = 'none';
}

// Rig Preferences Modal Functions
let currentWorkerIdForRigs = null;

async function showRigPreferencesModal() {
    const workerId = document.getElementById('employeeWorkerId').value;
    if (!workerId) {
        alert('Please save the worker first before managing rig preferences');
        return;
    }
    
    currentWorkerIdForRigs = workerId;
    const modal = document.getElementById('rigPreferencesModal');
    const content = document.getElementById('rigPreferencesContent');
    const title = document.getElementById('rigPreferencesTitle');
    
    // Get worker name
    const workerName = document.querySelector('[name="worker_name"]').value;
    title.textContent = `Manage Rig Preferences - ${workerName}`;
    
    modal.style.display = 'flex';
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>Loading rig preferences...</p>
        </div>
    `;
    
    try {
        // Load current rigs and available rigs
        const [currentRigsRes, availableRigsRes] = await Promise.all([
            fetch(`../api/worker-rig-preferences.php?action=get_worker_rigs&worker_id=${workerId}`),
            fetch(`../api/worker-rig-preferences.php?action=get_available_rigs&worker_id=${workerId}`)
        ]);
        
        const currentRigs = await currentRigsRes.json();
        const availableRigs = await availableRigsRes.json();
        
        if (!currentRigs.success || !availableRigs.success) {
            throw new Error('Failed to load rig preferences');
        }
        
        renderRigPreferencesContent(currentRigs.data, availableRigs.data);
    } catch (error) {
        console.error('Error loading rig preferences:', error);
        content.innerHTML = `
            <div style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ Error</h3>
                <p>${error.message || 'Failed to load rig preferences'}</p>
                <button type="button" class="btn btn-outline" onclick="closeRigPreferencesModal()" style="margin-top: 20px;">Close</button>
            </div>
        `;
    }
}

function renderRigPreferencesContent(currentRigs, availableRigs) {
    const content = document.getElementById('rigPreferencesContent');
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 15px;">Current Rig Preferences</h3>
            <div id="currentRigsList" style="margin-bottom: 20px;">
    `;
    
    if (currentRigs.length === 0) {
        html += '<p style="color: var(--secondary); padding: 20px; text-align: center; background: #f8f9fa; border-radius: 6px;">No rig preferences set</p>';
    } else {
        currentRigs.forEach(rig => {
            let levelColor = '#6c757d';
            let levelText = '';
            if (rig.preference_level === 'primary') {
                levelColor = '#28a745';
                levelText = '⭐ Primary';
            } else if (rig.preference_level === 'secondary') {
                levelColor = '#ffc107';
                levelText = 'Secondary';
            } else {
                levelText = 'Occasional';
            }
            
            html += `
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: white; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 10px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <strong>${escapeHtml(rig.rig_name || rig.rig_code)}</strong>
                            <span style="background: ${levelColor}; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px;">${levelText}</span>
                        </div>
                        ${rig.notes ? `<div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">${escapeHtml(rig.notes)}</div>` : ''}
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-sm btn-primary" onclick="editRigPreference(${rig.id}, '${escapeHtml(rig.preference_level)}', '${escapeHtml(rig.notes || '')}')">Edit</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="removeRigPreference(${rig.id})">Remove</button>
                    </div>
                </div>
            `;
        });
    }
    
    html += `
            </div>
            
            <h3 style="margin-bottom: 15px;">Add New Rig Preference</h3>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 6px;">
                <div class="form-group">
                    <label>Select Rig</label>
                    <select id="newRigSelect" class="form-control">
                        <option value="">-- Select Rig --</option>
    `;
    
    availableRigs.forEach(rig => {
        if (!rig.is_assigned) {
            html += `<option value="${rig.id}">${escapeHtml(rig.rig_name)} (${escapeHtml(rig.rig_code)})</option>`;
        }
    });
    
    html += `
                    </select>
                </div>
                <div class="form-group">
                    <label>Preference Level</label>
                    <select id="newRigPreferenceLevel" class="form-control">
                        <option value="primary">Primary - Usually works on this rig</option>
                        <option value="secondary">Secondary - Sometimes works on this rig</option>
                        <option value="occasional">Occasional - Rarely works on this rig</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notes (Optional)</label>
                    <textarea id="newRigNotes" class="form-control" rows="2" placeholder="Any additional notes about this worker's relationship with this rig"></textarea>
                </div>
                <button type="button" class="btn btn-primary" onclick="addRigPreference()">Add Rig Preference</button>
            </div>
        </div>
        <div style="margin-top: 20px; text-align: center;">
            <button type="button" class="btn btn-primary" onclick="closeRigPreferencesModal()">Close</button>
        </div>
    `;
    
    content.innerHTML = html;
}

// Add rig preference
async function addRigPreference() {
    const workerId = currentWorkerIdForRigs;
    const rigId = document.getElementById('newRigSelect').value;
    const preferenceLevel = document.getElementById('newRigPreferenceLevel').value;
    const notes = document.getElementById('newRigNotes').value;
    
    if (!rigId) {
        alert('Please select a rig');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'add_preference');
        formData.append('worker_id', workerId);
        formData.append('rig_id', rigId);
        formData.append('preference_level', preferenceLevel);
        formData.append('notes', notes);
        
        const response = await fetch('../api/worker-rig-preferences.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Reload rigs
            await showRigPreferencesModal();
            // Reload display in form
            loadWorkerRigs(workerId);
        } else {
            alert('Error: ' + (result.message || 'Failed to add rig preference'));
        }
    } catch (error) {
        console.error('Error adding rig preference:', error);
        alert('Error adding rig preference: ' + error.message);
    }
}

// Edit rig preference
async function editRigPreference(id, currentLevel, currentNotes) {
    const newLevel = prompt('Preference Level (primary/secondary/occasional):', currentLevel);
    if (newLevel === null) return;
    
    if (!['primary', 'secondary', 'occasional'].includes(newLevel.toLowerCase())) {
        alert('Invalid preference level. Must be: primary, secondary, or occasional');
        return;
    }
    
    const newNotes = prompt('Notes (optional):', currentNotes || '');
    if (newNotes === null) return;
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'update_preference');
        formData.append('id', id);
        formData.append('preference_level', newLevel.toLowerCase());
        formData.append('notes', newNotes);
        
        const response = await fetch('../api/worker-rig-preferences.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const workerId = currentWorkerIdForRigs;
            await showRigPreferencesModal();
            loadWorkerRigs(workerId);
        } else {
            alert('Error: ' + (result.message || 'Failed to update rig preference'));
        }
    } catch (error) {
        console.error('Error updating rig preference:', error);
        alert('Error updating rig preference: ' + error.message);
    }
}

// Remove rig preference
async function removeRigPreference(id) {
    if (!confirm('Are you sure you want to remove this rig preference?')) {
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
        formData.append('action', 'remove_preference');
        formData.append('id', id);
        
        const response = await fetch('../api/worker-rig-preferences.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const workerId = currentWorkerIdForRigs;
            await showRigPreferencesModal();
            loadWorkerRigs(workerId);
        } else {
            alert('Error: ' + (result.message || 'Failed to remove rig preference'));
        }
    } catch (error) {
        console.error('Error removing rig preference:', error);
        alert('Error removing rig preference: ' + error.message);
    }
}

function closeRigPreferencesModal() {
    document.getElementById('rigPreferencesModal').style.display = 'none';
}

// Add spinner animation
const spinnerStyle = document.createElement('style');
spinnerStyle.textContent = `
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background-color: var(--card);
        margin: auto;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--text);
    }
`;
document.head.appendChild(spinnerStyle);

// Delete employee from button
function deleteEmployeeFromButton(button) {
    try {
        const workerId = button.getAttribute('data-worker-id');
        const workerName = button.getAttribute('data-worker-name') || 'this employee';
        
        if (!workerId) {
            console.error('Missing worker ID');
            alert('Error: Missing worker ID. Please refresh the page and try again.');
            return;
        }
        
        if (confirm(`Are you sure you want to delete ${workerName}? This action cannot be undone and will affect all related records (payroll, loans, field reports, etc.).`)) {
            deleteEmployee(workerId);
        }
    } catch (error) {
        console.error('Error in deleteEmployeeFromButton:', error);
        alert('An error occurred while trying to delete the employee. Please check the console for details.');
    }
}

function deleteEmployee(id) {
    try {
        if (!id) {
            console.error('No employee ID provided');
            alert('Error: No employee ID provided.');
            return;
        }
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=employees';
        form.style.display = 'none';
        
        // Get CSRF token
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken && csrfToken.value) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        } else {
            console.warn('CSRF token not found, but continuing');
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_employee';
        form.appendChild(actionInput);
        
        const workerIdInput = document.createElement('input');
        workerIdInput.type = 'hidden';
        workerIdInput.name = 'worker_id';
        workerIdInput.value = id;
        form.appendChild(workerIdInput);
        
        document.body.appendChild(form);
        form.submit();
    } catch (error) {
        console.error('Error in deleteEmployee function:', error);
        console.error('Error stack:', error.stack);
        alert('An error occurred while deleting the employee. Please check the console for details.');
    }
}

// Role modal functions
function showRoleModal() {
    const modal = document.getElementById('roleModal');
    if (modal) {
        modal.style.display = 'flex';
        const form = document.getElementById('roleForm');
        if (form) {
            form.reset();
        }
        const actionInput = document.getElementById('roleAction');
        if (actionInput) {
            actionInput.value = 'add_worker_role';
        }
        const idInput = document.getElementById('roleId');
        if (idInput) {
            idInput.value = '';
        }
        const title = document.getElementById('roleModalTitle');
        if (title) {
            title.textContent = 'Add New Role';
        }
    }
}

// Edit role from button (using data attributes)
// Ensure function is globally accessible
if (typeof editRoleFromButton === 'undefined') {
    window.editRoleFromButton = function(button) {
        try {
            const roleId = button.getAttribute('data-role-id');
            const roleName = button.getAttribute('data-role-name');
            const roleDescription = button.getAttribute('data-role-description') || '';
            const isSystem = button.getAttribute('data-role-system') === '1' || button.getAttribute('data-role-system') === 'true';
            
            if (!roleId || !roleName) {
                console.error('Missing role data:', { roleId, roleName });
                alert('Error: Missing role data. Please refresh the page and try again.');
                return;
            }
            
            // Check if it's a system role
            if (isSystem) {
                alert('System roles cannot be edited. They are protected by the system.');
                return;
            }
            
            // Call editRole if it exists
            if (typeof editRole === 'function') {
                editRole({
                    id: roleId,
                    role_name: roleName,
                    description: roleDescription
                });
            } else {
                console.error('editRole function not found');
                alert('Error: Edit function not available. Please refresh the page.');
            }
        } catch (error) {
            console.error('Error in editRoleFromButton:', error);
            alert('An error occurred while trying to edit the role. Please check the console for details.');
        }
    };
} else {
    // Function already exists, just ensure it's on window
    window.editRoleFromButton = editRoleFromButton;
}

// Ensure editRole is globally accessible
if (typeof editRole === 'undefined') {
    window.editRole = function(role) {
        try {
            // Handle both object and JSON string
            let roleData = role;
        if (typeof role === 'string') {
            try {
                roleData = JSON.parse(role);
            } catch (e) {
                console.error('Error parsing role data:', e);
                alert('Error loading role data. Please refresh the page and try again.');
                return;
            }
        }
        
        const modal = document.getElementById('roleModal');
        if (!modal) {
            console.error('Role modal not found');
            alert('Role modal not found. Please refresh the page.');
            return;
        }
        
        // Show modal
        modal.style.display = 'flex';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        
        // Reset form first
        const form = document.getElementById('roleForm');
        if (form) {
            form.reset();
        }
        
        const actionInput = document.getElementById('roleAction');
        if (actionInput) {
            actionInput.value = 'update_worker_role';
        } else {
            console.error('roleAction input not found');
            alert('Error: Form elements not found. Please refresh the page.');
            return;
        }
        
        const idInput = document.getElementById('roleId');
        if (idInput) {
            idInput.value = roleData.id || roleData.role_id || '';
        } else {
            console.error('roleId input not found');
            alert('Error: Form elements not found. Please refresh the page.');
            return;
        }
        
        const nameInput = document.getElementById('role_name');
        if (nameInput) {
            nameInput.value = roleData.role_name || roleData.name || '';
        } else {
            console.error('role_name input not found');
            alert('Error: Form elements not found. Please refresh the page.');
            return;
        }
        
        const descInput = document.getElementById('role_description');
        if (descInput) {
            descInput.value = roleData.description || roleData.desc || '';
        } else {
            console.warn('role_description input not found, but continuing');
        }
        
        const title = document.getElementById('roleModalTitle');
        if (title) {
            title.textContent = 'Edit Role';
        }
        
        // Focus on the name input for better UX
        if (nameInput) {
            setTimeout(() => {
                nameInput.focus();
            }, 100);
        }
        } catch (error) {
            console.error('Error in editRole function:', error);
            alert('An error occurred while opening the edit form. Please check the console for details.');
        }
    };
} else {
    // Function already exists, just ensure it's on window
    window.editRole = editRole;
}

function closeRoleModal() {
    const modal = document.getElementById('roleModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function toggleRole(id, isActive) {
    if (confirm('Are you sure you want to ' + (isActive === 'true' ? 'deactivate' : 'activate') + ' this role?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=roles';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'toggle_worker_role';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function deleteRole(id) {
    if (confirm('Are you sure you want to delete this role? This action cannot be undone. If the role is assigned to workers, you must remove it from all workers first.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=roles';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_worker_role';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Handle custom role selection in employee form
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    const customRoleContainer = document.getElementById('customRoleContainer');
    const customRoleInput = document.getElementById('custom_role_input');
    
    if (roleSelect && customRoleContainer) {
        roleSelect.addEventListener('change', function() {
            if (this.value === '__custom__') {
                customRoleContainer.style.display = 'block';
                customRoleInput.required = true;
            } else {
                customRoleContainer.style.display = 'none';
                customRoleInput.required = false;
            }
        });
        
        // Handle custom role submission
        const employeeForm = document.querySelector('form[action*="add_employee"]');
        if (employeeForm) {
            employeeForm.addEventListener('submit', function(e) {
                if (roleSelect.value === '__custom__' && customRoleInput.value) {
                    // Create role first, then submit form with role name
                    const roleName = customRoleInput.value.trim();
                    if (roleName) {
                        // Add role via API, then set the role select value
                        fetch('../api/config-crud.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: new URLSearchParams({
                                action: 'add_worker_role',
                                role_name: roleName,
                                description: '',
                                csrf_token: document.querySelector('[name="csrf_token"]')?.value || ''
                            })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                roleSelect.value = roleName;
                                roleSelect.removeChild(roleSelect.querySelector('option[value="__custom__"]'));
                                const newOption = document.createElement('option');
                                newOption.value = roleName;
                                newOption.textContent = roleName;
                                roleSelect.appendChild(newOption);
                                roleSelect.value = roleName;
                                customRoleContainer.style.display = 'none';
                                // Continue with form submission
                                return true;
                            } else {
                                alert('Failed to create role: ' + (data.message || 'Unknown error'));
                                e.preventDefault();
                            }
                        })
                        .catch(err => {
                            alert('Error creating role: ' + err.message);
                            e.preventDefault();
                        });
                        e.preventDefault();
                    }
                }
            });
        }
    }
});

// Modal styling
const modalStyle = document.createElement('style');
modalStyle.textContent = `
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background-color: var(--card);
        margin: auto;
        padding: 0;
        border-radius: var(--radius);
        width: 90%;
        max-width: 600px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-header h2 {
        margin: 0;
        color: var(--text);
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: var(--text);
        line-height: 1;
    }
    .modal-close:hover {
        color: var(--primary);
    }
    .form-actions {
        padding: 20px;
        border-top: 1px solid var(--border);
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }
`;
document.head.appendChild(modalStyle);

// CRUD Functions for all HR entities
function editDepartment(id) {
    // Fetch department data and populate form
    fetch(`hr.php?action=departments&get_department=${id}`)
        .then(r => r.text())
        .then(html => {
            // For now, use a simple prompt-based edit
            // In production, you'd want a proper modal
            const deptCode = prompt('Department Code:');
            if (deptCode === null) return;
            const deptName = prompt('Department Name:');
            if (deptName === null) return;
            const description = prompt('Description (optional):', '') || '';
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'hr.php?action=departments';
            
            const csrfToken = document.querySelector('[name="csrf_token"]');
            if (csrfToken) {
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken.value;
                form.appendChild(csrfInput);
            }
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_department';
            form.appendChild(actionInput);
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = id;
            form.appendChild(idInput);
            
            const codeInput = document.createElement('input');
            codeInput.type = 'hidden';
            codeInput.name = 'department_code';
            codeInput.value = deptCode;
            form.appendChild(codeInput);
            
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'department_name';
            nameInput.value = deptName;
            form.appendChild(nameInput);
            
            const descInput = document.createElement('input');
            descInput.type = 'hidden';
            descInput.name = 'description';
            descInput.value = description;
            form.appendChild(descInput);
            
            document.body.appendChild(form);
            form.submit();
        })
        .catch(err => {
            alert('Error loading department: ' + err.message);
        });
}

function deleteDepartment(id) {
    if (confirm('Are you sure you want to delete this department? If it has employees, it will be deactivated instead.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=departments';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_department';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editStakeholder(id) {
    const code = prompt('Stakeholder Code:');
    if (code === null) return;
    const name = prompt('Full Name:');
    if (name === null) return;
    const type = prompt('Type (board_member/investor/partner/advisor/consultant/vendor/supplier/other):');
    if (type === null) return;
    const org = prompt('Organization (optional):', '') || '';
    const email = prompt('Email (optional):', '') || '';
    const phone = prompt('Phone (optional):', '') || '';
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'hr.php?action=stakeholders';
    
    const csrfToken = document.querySelector('[name="csrf_token"]');
    if (csrfToken) {
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken.value;
        form.appendChild(csrfInput);
    }
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'update_stakeholder';
    form.appendChild(actionInput);
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    form.appendChild(idInput);
    
    ['stakeholder_code', 'full_name', 'stakeholder_type', 'organization', 'email', 'phone'].forEach(field => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = field;
        input.value = {stakeholder_code: code, full_name: name, stakeholder_type: type, organization: org, email: email, phone: phone}[field] || '';
        form.appendChild(input);
    });
    
    document.body.appendChild(form);
    form.submit();
}

function deleteStakeholder(id) {
    if (confirm('Are you sure you want to delete this stakeholder? If it has communications, it will be deactivated instead.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=stakeholders';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_stakeholder';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editAttendance(id) {
    alert('Edit attendance functionality - to be implemented with proper modal');
    // Similar pattern to other edit functions
}

function deleteAttendance(id) {
    if (confirm('Are you sure you want to delete this attendance record?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=attendance';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_attendance';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editLeaveRequest(id) {
    alert('Edit leave request functionality - to be implemented with proper modal');
    // Similar pattern to other edit functions
}

function deleteLeaveRequest(id) {
    if (confirm('Are you sure you want to delete this leave request? Only pending requests can be deleted.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=leave';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_leave_request';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editPerformanceReview(id) {
    alert('Edit performance review functionality - to be implemented with proper modal');
    // Similar pattern to other edit functions
}

function deletePerformanceReview(id) {
    if (confirm('Are you sure you want to delete this performance review?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=performance';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_performance_review';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editTraining(id) {
    alert('Edit training functionality - to be implemented with proper modal');
    // Similar pattern to other edit functions
}

function deleteTraining(id) {
    if (confirm('Are you sure you want to delete this training record?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=training';
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_training';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

function editStakeholderCommunication(id, stakeholderId) {
    alert('Edit communication functionality - to be implemented with proper modal');
    // Similar pattern to other edit functions
}

function deleteStakeholderCommunication(id, stakeholderId) {
    if (confirm('Are you sure you want to delete this communication record?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'hr.php?action=stakeholders&view=' + stakeholderId;
        
        const csrfToken = document.querySelector('[name="csrf_token"]');
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken.value;
            form.appendChild(csrfInput);
        }
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_stakeholder_communication';
        form.appendChild(actionInput);
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;
        form.appendChild(idInput);
        
        const stakeholderInput = document.createElement('input');
        stakeholderInput.type = 'hidden';
        stakeholderInput.name = 'stakeholder_id';
        stakeholderInput.value = stakeholderId;
        form.appendChild(stakeholderInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

