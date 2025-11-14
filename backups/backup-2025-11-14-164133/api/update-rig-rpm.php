<?php
/**
 * Update Rig RPM from Field Report
 * Automatically updates rig RPM and checks for maintenance thresholds
 */
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
$auth->requireAuth();

try {
    $pdo = getDBConnection();
    
    // Get input
    $rigId = intval($_POST['rig_id'] ?? $_GET['rig_id'] ?? 0);
    $totalRpm = floatval($_POST['total_rpm'] ?? $_GET['total_rpm'] ?? 0);
    $reportId = intval($_POST['report_id'] ?? $_GET['report_id'] ?? 0);
    
    if (!$rigId || $totalRpm <= 0) {
        throw new Exception('Invalid rig ID or RPM value');
    }
    
    // Get current rig data
    $rigStmt = $pdo->prepare("SELECT current_rpm, maintenance_due_at_rpm, maintenance_rpm_interval, rig_code FROM rigs WHERE id = ?");
    $rigStmt->execute([$rigId]);
    $rig = $rigStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$rig) {
        throw new Exception('Rig not found');
    }
    
    $pdo->beginTransaction();
    
    // Calculate new RPM
    $oldRpm = floatval($rig['current_rpm']);
    $newRpm = $oldRpm + $totalRpm;
    
    // Update rig RPM
    $updateStmt = $pdo->prepare("
        UPDATE rigs 
        SET current_rpm = ? 
        WHERE id = ?
    ");
    $updateStmt->execute([$newRpm, $rigId]);
    
    // Record in RPM history
    try {
        $historyStmt = $pdo->prepare("
            INSERT INTO rig_rpm_history (rig_id, report_id, rpm_value, rpm_type, recorded_by, notes)
            VALUES (?, ?, ?, 'total', ?, ?)
        ");
        $historyStmt->execute([
            $rigId,
            $reportId ?: null,
            $totalRpm,
            $_SESSION['user_id'] ?? 1,
            "Auto-updated from field report"
        ]);
    } catch (PDOException $e) {
        // Table might not exist yet, continue
        error_log("RPM history insert failed: " . $e->getMessage());
    }
    
    // Check for maintenance threshold
    $maintenanceDue = false;
    $maintenanceRecordId = null;
    
    if ($rig['maintenance_due_at_rpm'] && $newRpm >= floatval($rig['maintenance_due_at_rpm'])) {
        $maintenanceDue = true;
        
        // Auto-create maintenance record
        try {
            // Get default proactive maintenance type
            $typeStmt = $pdo->query("SELECT id FROM maintenance_types WHERE is_proactive = 1 AND is_active = 1 LIMIT 1");
            $maintenanceType = $typeStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($maintenanceType) {
                // Get asset ID for this rig
                $assetStmt = $pdo->prepare("SELECT id FROM assets WHERE asset_type = 'rig' AND asset_code = ? OR asset_name LIKE ? LIMIT 1");
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
                            rpm_at_maintenance, rpm_threshold, next_maintenance_rpm,
                            description, created_by
                        ) VALUES (?, ?, 'proactive', ?, ?, 'scheduled', 'high', ?, ?, ?, ?, ?)
                    ");
                    
                    $nextMaintenanceRpm = $newRpm + floatval($rig['maintenance_rpm_interval']);
                    $description = "Auto-scheduled maintenance due at RPM threshold. Current RPM: {$newRpm}, Threshold: {$rig['maintenance_due_at_rpm']}";
                    
                    $maintStmt->execute([
                        $maintenanceCode,
                        $maintenanceType['id'],
                        $asset['id'],
                        $rigId,
                        $newRpm,
                        $rig['maintenance_due_at_rpm'],
                        $nextMaintenanceRpm,
                        $description,
                        $_SESSION['user_id'] ?? 1
                    ]);
                    
                    $maintenanceRecordId = $pdo->lastInsertId();
                }
            }
        } catch (PDOException $e) {
            // Maintenance table might not exist yet, log and continue
            error_log("Auto-maintenance creation failed: " . $e->getMessage());
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'RPM updated successfully',
        'data' => [
            'rig_id' => $rigId,
            'old_rpm' => $oldRpm,
            'new_rpm' => $newRpm,
            'rpm_added' => $totalRpm,
            'maintenance_due' => $maintenanceDue,
            'maintenance_due_at_rpm' => $rig['maintenance_due_at_rpm'],
            'maintenance_record_id' => $maintenanceRecordId,
            'rpm_remaining' => $maintenanceDue ? 0 : ($rig['maintenance_due_at_rpm'] ? $rig['maintenance_due_at_rpm'] - $newRpm : null)
        ]
    ]);
    
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
