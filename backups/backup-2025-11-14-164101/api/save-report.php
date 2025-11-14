<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';
require_once '../includes/helpers.php';
require_once '../includes/MaintenanceExtractor.php';
require_once '../includes/AccountingAutoTracker.php';
require_once '../includes/pos/FieldReportPosIntegrator.php';
require_once '../includes/pos/FieldReportMaterialsService.php';
require_once '../includes/pos/MaterialStoreService.php';

$auth->requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Validate CSRF token
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

try {
    $data = sanitizeArray($_POST);
    $errors = Validation::validateFieldReport($data);
    
    if (!empty($errors)) {
        jsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $errors], 400);
    }
    
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    // Extract and save client first
    $clientId = null;
    if (!empty($data['client_data'])) {
        $clientData = json_decode($data['client_data'], true);
        $clientId = $abbis->extractAndSaveClient($clientData);
    } elseif (!empty($data['client_name'])) {
        // Fallback: extract from form fields
        $clientId = $abbis->extractAndSaveClient([
            'client_name' => $data['client_name'] ?? '',
            'contact_person' => $data['client_contact_person'] ?? '',
            'client_contact' => $data['client_contact'] ?? '',
            'email' => $data['client_email'] ?? ''
        ]);
    }
    
    // Generate report ID with rig code
    $rigCode = null;
    if (!empty($data['rig_id'])) {
        $rigStmt = $pdo->prepare("SELECT rig_code FROM rigs WHERE id = ?");
        $rigStmt->execute([$data['rig_id']]);
        $rig = $rigStmt->fetch();
        $rigCode = $rig ? $rig['rig_code'] : null;
    }
    $reportId = $abbis->generateReportId($rigCode);
    
    // Calculate construction depth server-side for consistency
    // Formula: (screen pipes + plain pipes) * 3 meters per pipe
    $screenPipesUsed = intval($data['screen_pipes_used'] ?? 0);
    $plainPipesUsed = intval($data['plain_pipes_used'] ?? 0);
    $constructionDepth = $abbis->calculateConstructionDepth($screenPipesUsed, $plainPipesUsed);
    // Override client-side calculation with server-side calculation
    $data['construction_depth'] = $constructionDepth;
    
    // Calculate financial totals
    $totals = $abbis->calculateFinancialTotals($data);
    
    // Check if this is maintenance work
    $isMaintenanceWork = isset($data['is_maintenance_work']) && $data['is_maintenance_work'] == 1;
    if (!$isMaintenanceWork && isset($data['job_type']) && $data['job_type'] === 'maintenance') {
        $isMaintenanceWork = true;
    }
    
    // Prepare maintenance fields
    $maintenanceWorkType = $data['maintenance_work_type'] ?? null;
    $maintenanceDescription = null;
    $assetId = $data['asset_id'] ?? null;
    
    // Handle supervisor - support both supervisor_id and supervisor (text)
    $supervisorId = null;
    $supervisorText = $data['supervisor'] ?? '';
    
    // If supervisor_id is provided, use it and get supervisor name
    if (!empty($data['supervisor_id'])) {
        $supervisorId = intval($data['supervisor_id']);
        if ($supervisorId > 0) {
            $supervisorStmt = $pdo->prepare("SELECT worker_name FROM workers WHERE id = ?");
            $supervisorStmt->execute([$supervisorId]);
            $supervisorResult = $supervisorStmt->fetch();
            if ($supervisorResult) {
                $supervisorText = $supervisorResult['worker_name'];
            }
        }
    }
    
    // Insert main report (with maintenance fields)
    // Check if maintenance columns exist, build query dynamically
    $materialsStoreId = !empty($data['materials_store_id']) ? intval($data['materials_store_id']) : null;

    // Calculate materials value (assets) - value of remaining materials
    $materialsValue = 0;
    $materialsProvidedBy = $data['materials_provided_by'] ?? 'client';
    if (in_array($materialsProvidedBy, ['company_shop', 'company_store', 'company'])) {
        // Get unit costs from materials_inventory
        $materialsStmt = $pdo->query("SELECT material_type, unit_cost FROM materials_inventory");
        $materials = $materialsStmt->fetchAll(PDO::FETCH_ASSOC);
        $unitCosts = [];
        foreach ($materials as $mat) {
            $unitCosts[$mat['material_type']] = floatval($mat['unit_cost'] ?? 0);
        }
        
        // Calculate remaining materials
        $screenPipesReceived = floatval($data['screen_pipes_received'] ?? 0);
        $screenPipesUsed = floatval($data['screen_pipes_used'] ?? 0);
        $plainPipesReceived = floatval($data['plain_pipes_received'] ?? 0);
        $plainPipesUsed = floatval($data['plain_pipes_used'] ?? 0);
        $gravelReceived = floatval($data['gravel_received'] ?? 0);
        $gravelUsed = floatval($data['gravel_used'] ?? 0);
        
        $screenPipesRemaining = max(0, $screenPipesReceived - $screenPipesUsed);
        $plainPipesRemaining = max(0, $plainPipesReceived - $plainPipesUsed);
        $gravelRemaining = max(0, $gravelReceived - $gravelUsed);
        
        // Calculate total value of remaining materials (assets)
        $materialsValue = 
            ($screenPipesRemaining * ($unitCosts['screen_pipe'] ?? 0)) +
            ($plainPipesRemaining * ($unitCosts['plain_pipe'] ?? 0)) +
            ($gravelRemaining * ($unitCosts['gravel'] ?? 0));
    }
    
    $columns = [
        'report_id', 'report_date', 'rig_id', 'job_type', 'site_name', 'plus_code', 'latitude', 'longitude',
        'location_description', 'region', 'client_id', 'client_contact', 'start_time', 'finish_time',
        'total_duration', 'start_rpm', 'finish_rpm', 'total_rpm', 'rod_length', 'rods_used', 'total_depth',
        'screen_pipes_used', 'plain_pipes_used', 'gravel_used', 'construction_depth', 'materials_provided_by', 'materials_store_id',
        'supervisor', 'total_workers', 'remarks', 'incident_log', 'solution_log', 'recommendation_log',
        'balance_bf', 'contract_sum', 'rig_fee_charged', 'rig_fee_collected', 'cash_received', 'materials_income',
        'materials_cost', 'materials_value', 'momo_transfer', 'cash_given', 'bank_deposit', 'total_income', 'total_expenses',
        'total_wages', 'net_profit', 'total_money_banked', 'days_balance', 'outstanding_rig_fee', 'created_by'
    ];
    $values = [
        $reportId, $data['report_date'], $data['rig_id'], $data['job_type'], $data['site_name'],
        null, $data['latitude'], $data['longitude'], $data['location_description'],
        $data['region'], $clientId, $data['client_contact'] ?? '', $data['start_time'],
        $data['finish_time'], $data['total_duration'], $data['start_rpm'], $data['finish_rpm'],
        $data['total_rpm'], $data['rod_length'], $data['rods_used'], $data['total_depth'],
        $data['screen_pipes_used'] ?? 0, $data['plain_pipes_used'] ?? 0, $data['gravel_used'] ?? 0,
        $constructionDepth, $data['materials_provided_by'] ?? 'client', $materialsStoreId,
        $supervisorText,
        $data['total_workers'] ?? 0, $data['remarks'] ?? '', $data['incident_log'] ?? '', $data['solution_log'] ?? '',
        $data['recommendation_log'] ?? '', $data['balance_bf'] ?? 0, $data['contract_sum'] ?? 0,
        $data['rig_fee_charged'] ?? 0, $data['rig_fee_collected'] ?? 0, $data['cash_received'] ?? 0,
        $data['materials_income'] ?? 0, $data['materials_cost'] ?? 0, $materialsValue, $data['momo_transfer'] ?? 0,
        $data['cash_given'] ?? 0, $data['bank_deposit'] ?? 0, $totals['total_income'], $totals['total_expenses'],
        $totals['total_wages'], $totals['net_profit'], $totals['total_money_banked'], $totals['days_balance'],
        $totals['outstanding_rig_fee'], $_SESSION['user_id']
    ];
    
    // Add supervisor_id if column exists
    try {
        $pdo->query("SELECT supervisor_id FROM field_reports LIMIT 1");
        $columns[] = 'supervisor_id';
        $values[] = $supervisorId;
    } catch (PDOException $e) {
        // Column doesn't exist yet, will be added by migration
    }
    
    // Check if materials_value column exists, handle dynamically
    $hasMaterialsValueColumn = false;
    try {
        $pdo->query("SELECT materials_value FROM field_reports LIMIT 1");
        $hasMaterialsValueColumn = true;
    } catch (PDOException $e) {
        // Column doesn't exist, remove from columns/values
        $materialsValueIndex = array_search('materials_value', $columns);
        if ($materialsValueIndex !== false) {
            // Find materials_cost index to locate materials_value value
            $materialsCostIndex = array_search('materials_cost', $columns);
            if ($materialsCostIndex !== false) {
                // materials_value comes right after materials_cost
                $valueIndex = $materialsCostIndex + 1;
                if (isset($values[$valueIndex])) {
                    unset($values[$valueIndex]);
                }
            }
            unset($columns[$materialsValueIndex]);
            $columns = array_values($columns); // Re-index
            $values = array_values($values); // Re-index
        }
    }
    
    // Add maintenance columns if they exist
    try {
        $pdo->query("SELECT is_maintenance_work FROM field_reports LIMIT 1");
        $columns[] = 'is_maintenance_work';
        $columns[] = 'maintenance_work_type';
        $columns[] = 'maintenance_description';
        $columns[] = 'asset_id';
        $values[] = $isMaintenanceWork ? 1 : 0;
        $values[] = $maintenanceWorkType;
        $values[] = $maintenanceDescription;
        $values[] = $assetId;
    } catch (PDOException $e) {
        // Columns don't exist yet, will be added by migration
    }
    
    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $columnsList = implode(', ', $columns);
    
    $stmt = $pdo->prepare("INSERT INTO field_reports ($columnsList) VALUES ($placeholders)");
    $stmt->execute($values);
    
    $reportInsertId = $pdo->lastInsertId();
    
    // Extract and create maintenance record if maintenance work detected
    $maintenanceRecordId = null;
    if ($isMaintenanceWork || $data['job_type'] === 'maintenance') {
        try {
            $extractor = new MaintenanceExtractor($pdo);
            $maintenanceData = $extractor->extractFromFieldReport($data);
            
            if ($maintenanceData && isset($maintenanceData['is_maintenance'])) {
                // Add expense data for parts extraction
                if (!empty($data['expenses'])) {
                    $maintenanceData['expenses'] = json_decode($data['expenses'], true);
                }
                
                $maintenanceRecordId = $extractor->createMaintenanceRecord(
                    $maintenanceData,
                    $reportInsertId,
                    $_SESSION['user_id']
                );
                
                if ($maintenanceRecordId) {
                    // Link expense entries to maintenance record
                    if (!empty($data['expenses'])) {
                        $expensesData = json_decode($data['expenses'], true);
                        foreach ($expensesData as $expense) {
                            try {
                                $linkStmt = $pdo->prepare("
                                    UPDATE expense_entries 
                                    SET maintenance_record_id = ? 
                                    WHERE report_id = ? AND description = ?
                                    LIMIT 1
                                ");
                                $linkStmt->execute([
                                    $maintenanceRecordId,
                                    $reportInsertId,
                                    $expense['description'] ?? ''
                                ]);
                            } catch (PDOException $e) {
                                // Column might not exist yet
                                error_log("Error linking expense to maintenance: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            // Log error but don't fail the report save
            error_log("Error extracting maintenance from field report: " . $e->getMessage());
        }
    } else {
        // Auto-detect maintenance from text fields even if not explicitly marked
        try {
            $extractor = new MaintenanceExtractor($pdo);
            $maintenanceData = $extractor->extractFromFieldReport($data);
            
            if ($maintenanceData && isset($maintenanceData['is_maintenance'])) {
                // Ask user to confirm or auto-create based on confidence
                // For now, we'll create it but mark as needing review
                if (!empty($data['expenses'])) {
                    $maintenanceData['expenses'] = json_decode($data['expenses'], true);
                }
                
                $maintenanceRecordId = $extractor->createMaintenanceRecord(
                    $maintenanceData,
                    $reportInsertId,
                    $_SESSION['user_id']
                );
                
                // Update field report to mark as maintenance
                if ($maintenanceRecordId) {
                    try {
                        $updateStmt = $pdo->prepare("
                            UPDATE field_reports 
                            SET is_maintenance_work = 1, 
                                maintenance_work_type = ?,
                                maintenance_description = ?
                            WHERE id = ?
                        ");
                        $updateStmt->execute([
                            $maintenanceData['maintenance_type'] ?? 'General Maintenance',
                            $maintenanceData['description'] ?? '',
                            $reportInsertId
                        ]);
                    } catch (PDOException $e) {
                        // Columns might not exist yet
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error auto-detecting maintenance: " . $e->getMessage());
        }
    }
    
    // Handle payroll entries
    if (!empty($data['payroll'])) {
        $payrollData = json_decode($data['payroll'], true);
        
        // Note: 10 workers per rig is a guideline, not a strict limit
        // Log a warning if over 10 but don't block
        if (count($payrollData) > 10) {
            error_log("Warning: Field report has " . count($payrollData) . " workers (recommended max: 10)");
        }
        
        foreach ($payrollData as $payroll) {
            $workerId = null;
            $workerName = $payroll['worker_name'] ?? '';
            
            // Try to get worker_id from worker_name
            if (!empty($workerName)) {
                $workerLookup = $pdo->prepare("SELECT id FROM workers WHERE worker_name = ? LIMIT 1");
                $workerLookup->execute([$workerName]);
                $workerResult = $workerLookup->fetch();
                if ($workerResult) {
                    $workerId = $workerResult['id'];
                }
            }
            
            // Also check if worker_id is directly provided
            if (empty($workerId) && !empty($payroll['worker_id'])) {
                $workerId = intval($payroll['worker_id']);
            }
            
            // Check if worker_id column exists
            $hasWorkerId = false;
            try {
                $pdo->query("SELECT worker_id FROM payroll_entries LIMIT 1");
                $hasWorkerId = true;
            } catch (PDOException $e) {
                // Column doesn't exist yet
            }
            
            if ($hasWorkerId) {
                $payrollStmt = $pdo->prepare("INSERT INTO payroll_entries (report_id, worker_id, worker_name, role, wage_type, units, pay_per_unit, benefits, loan_reclaim, amount, paid_today, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $payrollStmt->execute([
                    $reportInsertId, $workerId, $workerName, $payroll['role'], $payroll['wage_type'],
                    $payroll['units'], $payroll['pay_per_unit'], $payroll['benefits'],
                    $payroll['loan_reclaim'], $payroll['amount'], $payroll['paid_today'] ? 1 : 0,
                    $payroll['notes']
                ]);
            } else {
                // Fallback to old format if worker_id column doesn't exist
                $payrollStmt = $pdo->prepare("INSERT INTO payroll_entries (report_id, worker_name, role, wage_type, units, pay_per_unit, benefits, loan_reclaim, amount, paid_today, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $payrollStmt->execute([
                    $reportInsertId, $workerName, $payroll['role'], $payroll['wage_type'],
                    $payroll['units'], $payroll['pay_per_unit'], $payroll['benefits'],
                    $payroll['loan_reclaim'], $payroll['amount'], $payroll['paid_today'] ? 1 : 0,
                    $payroll['notes']
                ]);
            }
        }
    }
    
    // Ensure field_report_items table exists (self-init)
    try { $pdo->query("SELECT 1 FROM field_report_items LIMIT 1"); }
    catch (Throwable $e) {
        @include_once __DIR__ . '/../database/run-sql.php';
        $path = __DIR__ . '/../database/catalog_links_migration.sql';
        if (function_exists('run_sql_file')) { @run_sql_file($path); }
        else {
            $sql = @file_get_contents($path);
            if ($sql) { foreach (preg_split('/;\s*\n/', $sql) as $stmt) { $stmt = trim($stmt); if ($stmt) { try { $pdo->exec($stmt); } catch (Throwable $ignored) {} } } }
        }
    }

    // Handle expense entries and catalog-linked items
    if (!empty($data['expenses'])) {
        $expensesData = json_decode($data['expenses'], true);
        foreach ($expensesData as $expense) {
            // Legacy expense table (if present)
            try {
                $expenseStmt = $pdo->prepare("INSERT INTO expense_entries (report_id, description, unit_cost, quantity, amount) VALUES (?, ?, ?, ?, ?)");
                $expenseStmt->execute([
                    $reportInsertId, $expense['description'], $expense['unit_cost'],
                    $expense['quantity'], $expense['amount']
                ]);
            } catch (Throwable $ignored) { /* table may not exist; ignore */ }

            // Catalog-linked line item
            try {
                $fri = $pdo->prepare("INSERT INTO field_report_items (report_id, catalog_item_id, description, quantity, unit, unit_price, total_amount, item_type) VALUES (?,?,?,?,?,?,?, 'expense')");
                $catalogId = !empty($expense['catalog_item_id']) ? intval($expense['catalog_item_id']) : null;
                $desc = $expense['description'] ?? '';
                $qty = floatval($expense['quantity'] ?? 1);
                $unit = $expense['unit'] ?? null;
                $unitPrice = floatval($expense['unit_cost'] ?? 0);
                $total = floatval($expense['amount'] ?? ($qty * $unitPrice));
                $fri->execute([$reportInsertId, $catalogId, $desc, $qty, $unit, $unitPrice, $total]);
            } catch (Throwable $ignored) {}
        }
    }
    
    // Update materials inventory if company provided materials (legacy 'company' value)
    // Note: New values are 'company_shop' and 'company_store'
    if (($data['materials_provided_by'] ?? '') === 'company') {
        $screenPipesUsed = intval($data['screen_pipes_used'] ?? 0);
        $plainPipesUsed = intval($data['plain_pipes_used'] ?? 0);
        $gravelUsed = intval($data['gravel_used'] ?? 0);
        
        // OLD CODE - Replaced by FieldReportMaterialsService below
        // Keeping for backward compatibility but new service handles everything
    }

    // Process materials with system-wide sync (NEW COMPREHENSIVE SYSTEM)
    try {
        $materialsService = new FieldReportMaterialsService($pdo);
        $materialsResult = $materialsService->processFieldReportMaterials($reportInsertId, $data);
        
        if (!$materialsResult['success']) {
            error_log("Field report materials processing failed: " . ($materialsResult['error'] ?? 'Unknown error'));
        } else {
            // Update materials_cost based on processing result
            // Only include cost if materials are for company (not contractor's own materials)
            $jobType = $data['job_type'] ?? 'direct';
            $materialsProvidedBy = $data['materials_provided_by'] ?? 'client';
            $includeInCost = true;
            
            // Rule: If contractor job AND materials provided by client → NOT in cost
            // Company materials (shop or store) are always included in cost
            if ($jobType === 'subcontract' && $materialsProvidedBy === 'client') {
                $includeInCost = false;
            }
            
            // Both company_shop and company_store are company materials
            if (in_array($materialsProvidedBy, ['company_shop', 'company_store'])) {
                $includeInCost = true;
            }
            
            if ($includeInCost && isset($materialsResult['results']['used'])) {
                $totalMaterialsCost = 0;
                foreach ($materialsResult['results']['used'] as $material => $result) {
                    if ($result['include_in_cost'] ?? false) {
                        $totalMaterialsCost += $result['cost'] ?? 0;
                    }
                }
                
                // Update field report with calculated materials cost
                if ($totalMaterialsCost > 0) {
                    try {
                        $updateCostStmt = $pdo->prepare("
                            UPDATE field_reports 
                            SET materials_cost = ?
                            WHERE id = ?
                        ");
                        $updateCostStmt->execute([$totalMaterialsCost, $reportInsertId]);
                        
                        // Recalculate totals with updated materials cost
                        $data['materials_cost'] = $totalMaterialsCost;
                        $totals = $abbis->calculateFinancialTotals($data);
                        
                        // Update financial totals
                        $updateTotalsStmt = $pdo->prepare("
                            UPDATE field_reports 
                            SET total_expenses = ?,
                                net_profit = ?
                            WHERE id = ?
                        ");
                        $updateTotalsStmt->execute([
                            $totals['total_expenses'],
                            $totals['net_profit'],
                            $reportInsertId
                        ]);
                    } catch (PDOException $e) {
                        error_log("Error updating materials cost: " . $e->getMessage());
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error processing field report materials: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Don't fail the report save if materials processing fails
    }
    
    // Material Store Integration - Process materials from Material Store (Company Store/Warehouse)
    // Handle both 'store' (legacy) and 'company_store' (new) values
    $materialsProvidedBy = $data['materials_provided_by'] ?? '';
    if ($materialsProvidedBy === 'store' || $materialsProvidedBy === 'company_store') {
        try {
            $materialStoreService = new MaterialStoreService($pdo);
            $materialsUsed = [
                'screen_pipes_used' => intval($data['screen_pipes_used'] ?? 0),
                'plain_pipes_used' => intval($data['plain_pipes_used'] ?? 0),
                'gravel_used' => intval($data['gravel_used'] ?? 0)
            ];
            
            $storeResult = $materialStoreService->useInFieldWork(
                $reportInsertId,
                $materialsUsed,
                $_SESSION['user_id'] ?? 0
            );
            
            if ($storeResult['success']) {
                // Update field report with remaining quantities and value
                $updateRemainingStmt = $pdo->prepare("
                    UPDATE field_reports 
                    SET screen_pipes_remaining = ?,
                        plain_pipes_remaining = ?,
                        gravel_remaining = ?,
                        materials_value_used = ?
                    WHERE id = ?
                ");
                $updateRemainingStmt->execute([
                    $storeResult['materials']['screen_pipe']['remaining'] ?? null,
                    $storeResult['materials']['plain_pipe']['remaining'] ?? null,
                    $storeResult['materials']['gravel']['remaining'] ?? null,
                    $storeResult['total_value'] ?? 0,
                    $reportInsertId
                ]);
                
                error_log("[Field Report] Material Store inventory updated for report {$reportId}");
            } else {
                error_log("[Field Report] Material Store processing failed: " . ($storeResult['error'] ?? 'Unknown'));
            }
        } catch (Exception $e) {
            error_log("[Field Report] Material Store error: " . $e->getMessage());
            // Don't fail the report save if Material Store processing fails
        }
    }
    
    // POS integration for Company (Shop/POS) - materials directly from POS
    // Handle both 'material_shop' (legacy), 'company_shop' (new), and 'store' with store_id
    if (($materialsProvidedBy === 'material_shop' || $materialsProvidedBy === 'company_shop' || 
         ($materialsProvidedBy === 'store' && $materialsStoreId)) && $materialsStoreId) {
        try {
            FieldReportPosIntegrator::syncInventory(
                $pdo,
                [
                    'report_db_id' => $reportInsertId,
                    'report_code' => $reportId,
                    'store_id' => $materialsStoreId,
                    'screen_pipes_used' => (int) ($data['screen_pipes_used'] ?? 0),
                    'plain_pipes_used' => (int) ($data['plain_pipes_used'] ?? 0),
                    'gravel_used' => (int) ($data['gravel_used'] ?? 0),
                    'materials_provided_by' => 'store',
                    'performed_by' => $_SESSION['user_id'] ?? null,
                ]
            );
        } catch (Exception $e) {
            error_log("Legacy POS sync failed (non-fatal): " . $e->getMessage());
        }
    }
    
    // Track rig fee debt if applicable (legacy table)
    if (!empty($data['rig_id']) && ($data['rig_fee_charged'] ?? 0) > ($data['rig_fee_collected'] ?? 0)) {
        $outstandingAmount = floatval($data['rig_fee_charged']) - floatval($data['rig_fee_collected']);
        $status = $outstandingAmount > 0 ? 'pending' : 'paid';
        
        try {
            $debtStmt = $pdo->prepare("
                INSERT INTO rig_fee_debts 
                (rig_id, report_id, client_id, amount_charged, amount_collected, outstanding_balance, status, issue_date, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ");
            $debtStmt->execute([
                $data['rig_id'],
                $reportInsertId,
                $clientId,
                $data['rig_fee_charged'] ?? 0,
                $data['rig_fee_collected'] ?? 0,
                $outstandingAmount,
                $status,
                $_SESSION['user_id']
            ]);
        } catch (PDOException $e) {
            // Legacy table might not exist, ignore
            error_log("Rig fee debt tracking error: " . $e->getMessage());
        }
    }
    
    // Automatically create debt recovery records for outstanding amounts
    // Ensure debt_recoveries table exists
    try {
        $pdo->query("SELECT 1 FROM debt_recoveries LIMIT 1");
    } catch (PDOException $e) {
        // Table doesn't exist, create it
        $pdo->exec("CREATE TABLE IF NOT EXISTS `debt_recoveries` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `debt_code` VARCHAR(50) NOT NULL UNIQUE,
            `field_report_id` INT(11) DEFAULT NULL,
            `client_id` INT(11) DEFAULT NULL,
            `debt_type` ENUM('contract_shortfall', 'rig_fee_unpaid', 'partial_payment', 'other') NOT NULL DEFAULT 'contract_shortfall',
            `agreed_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Original agreed/contract amount',
            `collected_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount actually collected',
            `debt_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Outstanding debt (agreed - collected)',
            `amount_recovered` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount recovered so far',
            `remaining_debt` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Current remaining debt',
            `due_date` DATE DEFAULT NULL COMMENT 'Original payment due date',
            `status` ENUM('outstanding', 'partially_paid', 'in_collection', 'recovered', 'written_off', 'bad_debt') NOT NULL DEFAULT 'outstanding',
            `priority` ENUM('low', 'medium', 'high', 'urgent', 'critical') NOT NULL DEFAULT 'medium',
            `age_days` INT(11) DEFAULT 0 COMMENT 'Days since debt was created',
            `last_followup_date` DATE DEFAULT NULL,
            `next_followup_date` DATE DEFAULT NULL,
            `followup_count` INT(11) DEFAULT 0,
            `payment_terms` VARCHAR(255) DEFAULT NULL,
            `contact_person` VARCHAR(100) DEFAULT NULL,
            `contact_phone` VARCHAR(50) DEFAULT NULL,
            `contact_email` VARCHAR(100) DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            `recovery_notes` TEXT DEFAULT NULL,
            `written_off_reason` TEXT DEFAULT NULL,
            `written_off_date` DATE DEFAULT NULL,
            `created_by` INT(11) NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `debt_code` (`debt_code`),
            KEY `field_report_id` (`field_report_id`),
            KEY `client_id` (`client_id`),
            KEY `status` (`status`),
            KEY `due_date` (`due_date`),
            KEY `next_followup_date` (`next_followup_date`),
            FOREIGN KEY (`field_report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL,
            FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    // Check for rig fee shortfall and create debt record
    $rigFeeCharged = floatval($data['rig_fee_charged'] ?? 0);
    $rigFeeCollected = floatval($data['rig_fee_collected'] ?? 0);
    if ($rigFeeCharged > $rigFeeCollected && $rigFeeCharged > 0) {
        $rigFeeShortfall = $rigFeeCharged - $rigFeeCollected;
        
        // Check if debt already exists for this report and rig fee
        $checkStmt = $pdo->prepare("
            SELECT id FROM debt_recoveries 
            WHERE field_report_id = ? AND debt_type = 'rig_fee_unpaid' AND remaining_debt > 0
        ");
        $checkStmt->execute([$reportInsertId]);
        $existingDebt = $checkStmt->fetch();
        
        if (!$existingDebt) {
            // Generate unique debt code
            $debtCode = 'DEBT-RIG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Get client contact info if available
            $contactPerson = $data['client_contact_person'] ?? '';
            $contactPhone = $data['client_contact'] ?? '';
            $contactEmail = $data['client_email'] ?? '';
            
            // Determine priority based on amount
            $priority = 'medium';
            if ($rigFeeShortfall > 10000) $priority = 'critical';
            elseif ($rigFeeShortfall > 5000) $priority = 'urgent';
            elseif ($rigFeeShortfall > 2000) $priority = 'high';
            
            $debtStmt = $pdo->prepare("
                INSERT INTO debt_recoveries 
                (debt_code, field_report_id, client_id, debt_type, agreed_amount, collected_amount, 
                 debt_amount, remaining_debt, status, priority, contact_person, contact_phone, 
                 contact_email, notes, created_by)
                VALUES (?, ?, ?, 'rig_fee_unpaid', ?, ?, ?, ?, 'outstanding', ?, ?, ?, ?, ?, ?)
            ");
            
            $notes = "Auto-generated from field report: {$reportId}. Rig fee charged: GHS " . number_format($rigFeeCharged, 2) . 
                     ", collected: GHS " . number_format($rigFeeCollected, 2) . 
                     ", shortfall: GHS " . number_format($rigFeeShortfall, 2);
            
            $debtStmt->execute([
                $debtCode,
                $reportInsertId,
                $clientId,
                $rigFeeCharged,
                $rigFeeCollected,
                $rigFeeShortfall,
                $rigFeeShortfall,
                $priority,
                $contactPerson,
                $contactPhone,
                $contactEmail,
                $notes,
                $_SESSION['user_id']
            ]);
        }
    }
    
    // Check for contract shortfall and create debt record
    $contractSum = floatval($data['contract_sum'] ?? 0);
    $totalIncome = floatval($totals['total_income'] ?? 0);
    if ($contractSum > $totalIncome && $contractSum > 0) {
        $contractShortfall = $contractSum - $totalIncome;
        
        // Check if debt already exists for this report and contract shortfall
        $checkStmt = $pdo->prepare("
            SELECT id FROM debt_recoveries 
            WHERE field_report_id = ? AND debt_type = 'contract_shortfall' AND remaining_debt > 0
        ");
        $checkStmt->execute([$reportInsertId]);
        $existingDebt = $checkStmt->fetch();
        
        if (!$existingDebt) {
            // Generate unique debt code
            $debtCode = 'DEBT-CONTRACT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Get client contact info if available
            $contactPerson = $data['client_contact_person'] ?? '';
            $contactPhone = $data['client_contact'] ?? '';
            $contactEmail = $data['client_email'] ?? '';
            
            // Determine priority based on amount
            $priority = 'medium';
            if ($contractShortfall > 10000) $priority = 'critical';
            elseif ($contractShortfall > 5000) $priority = 'urgent';
            elseif ($contractShortfall > 2000) $priority = 'high';
            
            $debtStmt = $pdo->prepare("
                INSERT INTO debt_recoveries 
                (debt_code, field_report_id, client_id, debt_type, agreed_amount, collected_amount, 
                 debt_amount, remaining_debt, status, priority, contact_person, contact_phone, 
                 contact_email, notes, created_by)
                VALUES (?, ?, ?, 'contract_shortfall', ?, ?, ?, ?, 'outstanding', ?, ?, ?, ?, ?, ?)
            ");
            
            $notes = "Auto-generated from field report: {$reportId}. Contract sum: GHS " . number_format($contractSum, 2) . 
                     ", total collected: GHS " . number_format($totalIncome, 2) . 
                     ", shortfall: GHS " . number_format($contractShortfall, 2);
            
            $debtStmt->execute([
                $debtCode,
                $reportInsertId,
                $clientId,
                $contractSum,
                $totalIncome,
                $contractShortfall,
                $contractShortfall,
                $priority,
                $contactPerson,
                $contactPhone,
                $contactEmail,
                $notes,
                $_SESSION['user_id']
            ]);
        }
    }
    
    $pdo->commit();
    
    // Automatically track financial transactions in accounting system
    // This runs automatically for EVERY new field report - no manual intervention needed
    try {
        // Ensure accounting tables exist before tracking
        try {
            $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
        } catch (PDOException $e) {
            // Tables don't exist, initialize them
            $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                if ($sql) {
                    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
                    foreach ($stmts as $stmt) {
                        if ($stmt !== '') {
                            try {
                                $pdo->exec($stmt);
                            } catch (PDOException $e2) {
                                // Ignore if already exists
                            }
                        }
                    }
                }
            }
        }
        
        $accountingTracker = new AccountingAutoTracker($pdo);
        
        // Get client name for description
        $clientName = '';
        if ($clientId) {
            $clientStmt = $pdo->prepare("SELECT client_name FROM clients WHERE id = ?");
            $clientStmt->execute([$clientId]);
            $client = $clientStmt->fetch();
            $clientName = $client ? $client['client_name'] : '';
        }
        
        // Prepare report data for accounting tracking
        $reportDataForAccounting = array_merge($data, [
            'report_id' => $reportId,
            'report_date' => $data['report_date'],
            'site_name' => $data['site_name'] ?? '',
            'client_name' => $clientName,
            'created_by' => $_SESSION['user_id'],
            'contract_sum' => $data['contract_sum'] ?? 0,
            'rig_fee_charged' => $data['rig_fee_charged'] ?? 0,
            'rig_fee_collected' => $data['rig_fee_collected'] ?? 0,
            'cash_received' => $data['cash_received'] ?? 0,
            'materials_income' => $data['materials_income'] ?? 0,
            'materials_cost' => $data['materials_cost'] ?? 0,
            'momo_transfer' => $data['momo_transfer'] ?? 0,
            'cash_given' => $data['cash_given'] ?? 0,
            'bank_deposit' => $data['bank_deposit'] ?? 0,
            'total_wages' => $totals['total_wages'],
            'total_expenses' => $totals['total_expenses'],
            'outstanding_rig_fee' => $totals['outstanding_rig_fee']
        ]);
        
        // Automatically create journal entry - this happens for EVERY new report
        $trackingResult = $accountingTracker->trackFieldReport($reportInsertId, $reportDataForAccounting);
        
        if ($trackingResult) {
            error_log("Accounting: Auto-tracked field report {$reportId} (ID: {$reportInsertId})");
        } else {
            error_log("Accounting: Failed to auto-track field report {$reportId} (ID: {$reportInsertId}) - check for errors above");
        }
    } catch (Exception $e) {
        // Log but don't fail the report save if accounting tracking fails
        // This ensures reports are always saved even if accounting has issues
        error_log("Accounting auto-tracking error for report {$reportId}: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
    }
    
    // Queue email notification
    try {
        require_once __DIR__ . '/../includes/email.php';
        $emailSystem = new EmailNotification();
        $emailSystem->notifyNewReport($reportId, array_merge($data, [
            'report_id' => $reportId,
            'client_name' => $data['client_name'] ?? ''
        ]));
    } catch (Exception $e) {
        error_log("Email notification error: " . $e->getMessage());
        // Don't fail the save if email fails
    }
    
    // Update rig RPM if total_rpm is provided
    if (!empty($data['rig_id']) && !empty($data['total_rpm']) && floatval($data['total_rpm']) > 0) {
        try {
            // Directly update rig RPM (internal call - no HTTP needed)
            $rigId = intval($data['rig_id']);
            $totalRpm = floatval($data['total_rpm']);
            
            // Validate RPM values before updating
            $startRpm = isset($data['start_rpm']) ? floatval($data['start_rpm']) : null;
            $finishRpm = isset($data['finish_rpm']) ? floatval($data['finish_rpm']) : null;
            
            // Check for unrealistic RPM values (likely data entry errors)
            if (($startRpm !== null && $startRpm > 1000) || ($finishRpm !== null && $finishRpm > 1000)) {
                // Log warning but don't block save
                error_log("WARNING: Unrealistic RPM values detected for rig {$rigId}. Start: {$startRpm}, Finish: {$finishRpm}, Total: {$totalRpm}. Possible decimal point error.");
                
                // Don't update rig RPM if values are unrealistic
                // This prevents incorrect accumulation
                // User should fix the data entry first
            } else {
                // Validate total_rpm is reasonable (should be 0.5-10 for typical jobs)
                if ($totalRpm > 100) {
                    error_log("WARNING: Total RPM ({$totalRpm}) seems unrealistic for rig {$rigId}. Not updating rig current_rpm.");
                } else {
                    // Get current rig data
                    $rigStmt = $pdo->prepare("SELECT current_rpm, maintenance_due_at_rpm, maintenance_rpm_interval, rig_code FROM rigs WHERE id = ?");
                    $rigStmt->execute([$rigId]);
                    $rig = $rigStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($rig) {
                        // Calculate new RPM
                        $oldRpm = floatval($rig['current_rpm'] ?? 0);
                        $newRpm = $oldRpm + $totalRpm;
                        
                        // Update rig RPM
                        $updateStmt = $pdo->prepare("UPDATE rigs SET current_rpm = ? WHERE id = ?");
                        $updateStmt->execute([$newRpm, $rigId]);
                
                // Record in RPM history if table exists
                try {
                    $historyStmt = $pdo->prepare("
                        INSERT INTO rig_rpm_history (rig_id, report_id, rpm_value, rpm_type, recorded_by, notes)
                        VALUES (?, ?, ?, 'total', ?, ?)
                    ");
                    $historyStmt->execute([
                        $rigId,
                        $reportInsertId,
                        $totalRpm,
                        $_SESSION['user_id'],
                        "Auto-updated from field report"
                    ]);
                } catch (PDOException $e) {
                    // Table might not exist yet, continue
                    error_log("RPM history insert failed: " . $e->getMessage());
                }
                
                        // Check for maintenance threshold and auto-create maintenance record if needed
                        if ($rig['maintenance_due_at_rpm'] && $newRpm >= floatval($rig['maintenance_due_at_rpm'])) {
                            try {
                                // Get default proactive maintenance type
                                $typeStmt = $pdo->query("SELECT id FROM maintenance_types WHERE is_proactive = 1 AND is_active = 1 LIMIT 1");
                                $maintenanceType = $typeStmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($maintenanceType) {
                                    // Get asset ID for this rig
                                    $assetStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_type = 'rig' AND (asset_code = ? OR asset_name LIKE ?) LIMIT 1");
                                    $assetStmt->execute([$rig['rig_code'], '%' . $rig['rig_code'] . '%']);
                                    $asset = $assetStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($asset) {
                                        // Generate maintenance code
                                        $maintenanceCode = 'MNT-' . date('Ymd') . '-' . strtoupper(substr($rig['rig_code'], -3)) . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
                                        
                                        // Create maintenance record
                                        $maintStmt = $pdo->prepare("
                                            INSERT INTO maintenance_records (
                                                maintenance_code, maintenance_type_id, maintenance_category,
                                                asset_id, rig_id, status, priority,
                                                description, created_by
                                            ) VALUES (?, ?, 'proactive', ?, ?, 'scheduled', 'high', ?, ?)
                                        ");
                                        
                                        $nextMaintenanceRpm = $newRpm + floatval($rig['maintenance_rpm_interval']);
                                        $description = "Auto-scheduled maintenance due at RPM threshold. Current RPM: {$newRpm}, Threshold: {$rig['maintenance_due_at_rpm']}";
                                        
                                        $maintStmt->execute([
                                            $maintenanceCode,
                                            $maintenanceType['id'],
                                            $asset['id'],
                                            $rigId,
                                            $description,
                                            $_SESSION['user_id']
                                        ]);
                                
                                error_log("Maintenance threshold reached for rig {$rigId} (RIG: {$rig['rig_code']}). New RPM: {$newRpm}. Maintenance record created: {$maintenanceCode}");
                            }
                        }
                    } catch (PDOException $e) {
                        // Maintenance tables might not exist yet, log and continue
                        error_log("Auto-maintenance creation failed: " . $e->getMessage());
                    }
                }
                    }
                }
            }
        } catch (Exception $e) {
            // Log but don't fail the report save if RPM update fails
            error_log("RPM update failed: " . $e->getMessage());
        }
    }
    
    // Clear cache
    $abbis->clearCache('dashboard_stats');
    
    jsonResponse([
        'success' => true, 
        'message' => 'Field report saved successfully!', 
        'report_id' => $reportId,
        'redirect' => '../modules/field-reports-list.php?success=1'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save report error: " . $e->getMessage());
    if (DEBUG) {
        jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
    } else {
        jsonResponse(['success' => false, 'message' => 'An error occurred while saving the report'], 500);
    }
}
?>