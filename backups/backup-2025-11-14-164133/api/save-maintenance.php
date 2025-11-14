<?php
/**
 * Save Maintenance Record
 * Handles RPM-based maintenance with components and expenses
 */
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$auth->requireAuth();

try {
    $pdo = getDBConnection();
    $currentUserId = $_SESSION['user_id'];
    
    // Get input
    $maintenanceId = intval($_POST['maintenance_id'] ?? 0);
    $action = $_POST['action'] ?? 'save';
    
    $pdo->beginTransaction();
    
    if ($action === 'save' || $action === 'complete') {
        // Get maintenance data
        $maintenanceCode = sanitizeInput($_POST['maintenance_code'] ?? '');
        $maintenanceTypeId = intval($_POST['maintenance_type_id'] ?? 0);
        $maintenanceCategory = sanitizeInput($_POST['maintenance_category'] ?? 'proactive');
        $assetId = intval($_POST['asset_id'] ?? 0);
        $rigId = intval($_POST['rig_id'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? 'logged');
        $priority = sanitizeInput($_POST['priority'] ?? 'medium');
        
        // RPM fields
        $rpmAtMaintenance = !empty($_POST['rpm_at_maintenance']) ? floatval($_POST['rpm_at_maintenance']) : null;
        $rpmThreshold = !empty($_POST['rpm_threshold']) ? floatval($_POST['rpm_threshold']) : null;
        
        // Other fields
        $description = sanitizeInput($_POST['description'] ?? '');
        $workPerformed = sanitizeInput($_POST['work_performed'] ?? '');
        $partsRequired = sanitizeInput($_POST['parts_required'] ?? '');
        $partsCost = floatval($_POST['parts_cost'] ?? 0);
        $laborCost = floatval($_POST['labor_cost'] ?? 0);
        $totalCost = floatval($_POST['total_cost'] ?? $partsCost + $laborCost);
        $downtimeHours = floatval($_POST['downtime_hours'] ?? 0);
        $effect = sanitizeInput($_POST['effect'] ?? '');
        $effectivenessRating = intval($_POST['effectiveness_rating'] ?? 0);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Dates
        $scheduledDate = !empty($_POST['scheduled_date']) ? $_POST['scheduled_date'] : null;
        $startedDate = !empty($_POST['started_date']) ? $_POST['started_date'] : null;
        $completedDate = ($action === 'complete' && empty($_POST['completed_date'])) ? date('Y-m-d H:i:s') : ($_POST['completed_date'] ?? null);
        $dueDate = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
        
        $performedBy = intval($_POST['performed_by'] ?? 0);
        $supervisedBy = intval($_POST['supervised_by'] ?? 0);
        
        if (!$maintenanceTypeId || !$assetId) {
            throw new Exception('Maintenance type and asset are required');
        }
        
        // Calculate RPM intervals if RPM is provided
        $rpmIntervalUsed = null;
        $nextMaintenanceRpm = null;
        
        if ($rpmAtMaintenance !== null && $rigId) {
            // Get rig data
            $rigStmt = $pdo->prepare("SELECT last_maintenance_rpm, maintenance_rpm_interval, current_rpm FROM rigs WHERE id = ?");
            $rigStmt->execute([$rigId]);
            $rig = $rigStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rig) {
                if ($rig['last_maintenance_rpm'] !== null) {
                    $rpmIntervalUsed = $rpmAtMaintenance - floatval($rig['last_maintenance_rpm']);
                }
                $nextMaintenanceRpm = $rpmAtMaintenance + floatval($rig['maintenance_rpm_interval']);
            }
        }
        
        if ($maintenanceId > 0) {
            // Update existing record
            $updateStmt = $pdo->prepare("
                UPDATE maintenance_records SET
                    maintenance_type_id = ?,
                    maintenance_category = ?,
                    asset_id = ?,
                    rig_id = ?,
                    status = ?,
                    priority = ?,
                    scheduled_date = ?,
                    started_date = ?,
                    completed_date = ?,
                    due_date = ?,
                    performed_by = ?,
                    supervised_by = ?,
                    description = ?,
                    work_performed = ?,
                    parts_required = ?,
                    parts_cost = ?,
                    labor_cost = ?,
                    total_cost = ?,
                    downtime_hours = ?,
                    effect = ?,
                    effectiveness_rating = ?,
                    notes = ?,
                    rpm_at_maintenance = ?,
                    rpm_threshold = ?,
                    rpm_interval_used = ?,
                    next_maintenance_rpm = ?
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $maintenanceTypeId,
                $maintenanceCategory,
                $assetId,
                $rigId ?: null,
                $status,
                $priority,
                $scheduledDate,
                $startedDate,
                $completedDate,
                $dueDate,
                $performedBy ?: null,
                $supervisedBy ?: null,
                $description,
                $workPerformed,
                $partsRequired,
                $partsCost,
                $laborCost,
                $totalCost,
                $downtimeHours,
                $effect,
                $effectivenessRating ?: null,
                $notes,
                $rpmAtMaintenance,
                $rpmThreshold,
                $rpmIntervalUsed,
                $nextMaintenanceRpm,
                $maintenanceId
            ]);
        } else {
            // Create new record
            if (empty($maintenanceCode)) {
                $maintenanceCode = 'MNT-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
            }
            
            $insertStmt = $pdo->prepare("
                INSERT INTO maintenance_records (
                    maintenance_code, maintenance_type_id, maintenance_category,
                    asset_id, rig_id, status, priority,
                    scheduled_date, started_date, completed_date, due_date,
                    performed_by, supervised_by,
                    description, work_performed, parts_required,
                    parts_cost, labor_cost, total_cost,
                    downtime_hours, effect, effectiveness_rating, notes,
                    rpm_at_maintenance, rpm_threshold, rpm_interval_used, next_maintenance_rpm,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $maintenanceCode,
                $maintenanceTypeId,
                $maintenanceCategory,
                $assetId,
                $rigId ?: null,
                $status,
                $priority,
                $scheduledDate,
                $startedDate,
                $completedDate,
                $dueDate,
                $performedBy ?: null,
                $supervisedBy ?: null,
                $description,
                $workPerformed,
                $partsRequired,
                $partsCost,
                $laborCost,
                $totalCost,
                $downtimeHours,
                $effect,
                $effectivenessRating ?: null,
                $notes,
                $rpmAtMaintenance,
                $rpmThreshold,
                $rpmIntervalUsed,
                $nextMaintenanceRpm,
                $currentUserId
            ]);
            
            $maintenanceId = $pdo->lastInsertId();
        }
        
        // Handle completion - update rig RPM tracking
        if ($action === 'complete' && $rpmAtMaintenance !== null && $rigId) {
            $rigStmt = $pdo->prepare("SELECT maintenance_rpm_interval FROM rigs WHERE id = ?");
            $rigStmt->execute([$rigId]);
            $rig = $rigStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($rig) {
                // Update rig maintenance tracking
                $updateRigStmt = $pdo->prepare("
                    UPDATE rigs SET
                        last_maintenance_rpm = ?,
                        maintenance_due_at_rpm = ? + maintenance_rpm_interval
                    WHERE id = ?
                ");
                $updateRigStmt->execute([
                    $rpmAtMaintenance,
                    $rpmAtMaintenance,
                    $rigId
                ]);
                
                // Record in RPM history
                try {
                    $historyStmt = $pdo->prepare("
                        INSERT INTO rig_rpm_history (rig_id, rpm_value, rpm_type, recorded_by, notes)
                        VALUES (?, ?, 'maintenance', ?, ?)
                    ");
                    $historyStmt->execute([
                        $rigId,
                        $rpmAtMaintenance,
                        $currentUserId,
                        "Maintenance completed - Maintenance ID: {$maintenanceId}"
                    ]);
                } catch (PDOException $e) {
                    error_log("RPM history insert failed: " . $e->getMessage());
                }
            }
        }
        
        // Save components
        if (isset($_POST['components']) && is_array($_POST['components'])) {
            // Delete existing components
            $deleteComponents = $pdo->prepare("DELETE FROM maintenance_components WHERE maintenance_id = ?");
            $deleteComponents->execute([$maintenanceId]);
            
            // Insert new components
            $componentStmt = $pdo->prepare("
                INSERT INTO maintenance_components (
                    maintenance_id, component_name, component_type,
                    action_taken, condition_before, condition_after, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['components'] as $component) {
                $componentStmt->execute([
                    $maintenanceId,
                    sanitizeInput($component['component_name'] ?? ''),
                    sanitizeInput($component['component_type'] ?? 'other'),
                    sanitizeInput($component['action_taken'] ?? 'serviced'),
                    !empty($component['condition_before']) ? sanitizeInput($component['condition_before']) : null,
                    !empty($component['condition_after']) ? sanitizeInput($component['condition_after']) : null,
                    !empty($component['notes']) ? sanitizeInput($component['notes']) : null
                ]);
            }
        }
        
        // Save expenses
        if (isset($_POST['expenses']) && is_array($_POST['expenses'])) {
            // Delete existing expenses
            $deleteExpenses = $pdo->prepare("DELETE FROM maintenance_expenses WHERE maintenance_id = ?");
            $deleteExpenses->execute([$maintenanceId]);
            
            // Insert new expenses
            $expenseStmt = $pdo->prepare("
                INSERT INTO maintenance_expenses (
                    maintenance_id, expense_type, description,
                    material_id, quantity, unit_cost, total_cost,
                    supplier, invoice_number, purchase_date, recorded_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($_POST['expenses'] as $expense) {
                $quantity = floatval($expense['quantity'] ?? 1);
                $unitCost = floatval($expense['unit_cost'] ?? 0);
                $totalCost = floatval($expense['total_cost'] ?? ($quantity * $unitCost));
                
                $expenseStmt->execute([
                    $maintenanceId,
                    sanitizeInput($expense['expense_type'] ?? 'parts'),
                    sanitizeInput($expense['description'] ?? ''),
                    !empty($expense['material_id']) ? intval($expense['material_id']) : null,
                    $quantity,
                    $unitCost,
                    $totalCost,
                    !empty($expense['supplier']) ? sanitizeInput($expense['supplier']) : null,
                    !empty($expense['invoice_number']) ? sanitizeInput($expense['invoice_number']) : null,
                    !empty($expense['purchase_date']) ? $expense['purchase_date'] : null,
                    $currentUserId
                ]);
            }
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => $maintenanceId > 0 ? 'Maintenance record updated' : 'Maintenance record created',
            'maintenance_id' => $maintenanceId,
            'maintenance_code' => $maintenanceCode
        ]);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
