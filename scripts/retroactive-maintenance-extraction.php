<?php
/**
 * Retroactive Maintenance Extraction Script
 * Processes existing field reports to extract maintenance information
 * and create maintenance records
 * 
 * Usage: php scripts/retroactive-maintenance-extraction.php
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/MaintenanceExtractor.php';

echo "===========================================\n";
echo "Retroactive Maintenance Extraction\n";
echo "===========================================\n\n";

$pdo = getDBConnection();
$extractor = new MaintenanceExtractor($pdo);

// Check if maintenance columns exist
try {
    $pdo->query("SELECT is_maintenance_work FROM field_reports LIMIT 1");
    echo "✓ Maintenance columns exist in field_reports\n";
} catch (PDOException $e) {
    echo "✗ Maintenance columns not found. Please run migration first:\n";
    echo "  database/migration-interconnect-data.sql\n";
    exit(1);
}

// Get all field reports
echo "\nFetching field reports...\n";
$stmt = $pdo->query("
    SELECT fr.*, 
           u.id as created_by_user_id
    FROM field_reports fr
    LEFT JOIN users u ON fr.created_by = u.id
    ORDER BY fr.report_date DESC, fr.id DESC
");
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($reports) . " field reports\n\n";

$processed = 0;
$created = 0;
$skipped = 0;
$errors = 0;

foreach ($reports as $report) {
    $processed++;
    
    // Check if maintenance record already exists
    $checkStmt = $pdo->prepare("SELECT id FROM maintenance_records WHERE field_report_id = ?");
    $checkStmt->execute([$report['id']]);
    if ($checkStmt->fetch()) {
        $skipped++;
        echo "⏭️  Report #{$report['report_id']} - Maintenance record already exists\n";
        continue;
    }
    
    // Prepare report data for extraction
    $reportData = [
        'rig_id' => $report['rig_id'],
        'report_date' => $report['report_date'],
        'start_time' => $report['start_time'],
        'finish_time' => $report['finish_time'],
        'total_duration' => $report['total_duration'],
        'job_type' => $report['job_type'],
        'is_maintenance_work' => $report['is_maintenance_work'] ?? 0,
        'maintenance_work_type' => $report['maintenance_work_type'] ?? null,
        'remarks' => $report['remarks'] ?? '',
        'incident_log' => $report['incident_log'] ?? '',
        'solution_log' => $report['solution_log'] ?? '',
        'recommendation_log' => $report['recommendation_log'] ?? '',
        'total_wages' => $report['total_wages'] ?? 0,
        'asset_id' => $report['asset_id'] ?? null,
    ];
    
    // Get expenses for this report
    try {
        $expenseStmt = $pdo->prepare("
            SELECT description, unit_cost, quantity, amount 
            FROM expense_entries 
            WHERE report_id = ?
        ");
        $expenseStmt->execute([$report['id']]);
        $expenses = $expenseStmt->fetchAll(PDO::FETCH_ASSOC);
        $reportData['expenses'] = $expenses;
    } catch (PDOException $e) {
        $reportData['expenses'] = [];
    }
    
    // Extract maintenance information
    try {
        $maintenanceData = $extractor->extractFromFieldReport($reportData);
        
        if ($maintenanceData && isset($maintenanceData['is_maintenance'])) {
            // Create maintenance record
            $maintenanceId = $extractor->createMaintenanceRecord(
                $maintenanceData,
                $report['id'],
                $report['created_by'] ?? 1
            );
            
            if ($maintenanceId) {
                $created++;
                echo "✓ Report #{$report['report_id']} - Created maintenance record #{$maintenanceId}\n";
                
                // Update field report to mark as maintenance
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
                        $report['id']
                    ]);
                } catch (PDOException $e) {
                    // Columns might not exist
                }
                
                // Link expenses to maintenance record
                if (!empty($expenses)) {
                    foreach ($expenses as $expense) {
                        try {
                            $linkStmt = $pdo->prepare("
                                UPDATE expense_entries 
                                SET maintenance_record_id = ? 
                                WHERE report_id = ? AND description = ?
                                LIMIT 1
                            ");
                            $linkStmt->execute([
                                $maintenanceId,
                                $report['id'],
                                $expense['description']
                            ]);
                        } catch (PDOException $e) {
                            // Column might not exist
                        }
                    }
                }
            } else {
                $errors++;
                echo "✗ Report #{$report['report_id']} - Failed to create maintenance record\n";
            }
        } else {
            $skipped++;
            echo "⏭️  Report #{$report['report_id']} - No maintenance detected\n";
        }
    } catch (Exception $e) {
        $errors++;
        echo "✗ Report #{$report['report_id']} - Error: " . $e->getMessage() . "\n";
    }
    
    // Progress indicator
    if ($processed % 10 == 0) {
        echo "\nProgress: {$processed}/" . count($reports) . " processed\n\n";
    }
}

echo "\n===========================================\n";
echo "Processing Complete!\n";
echo "===========================================\n";
echo "Total Reports: " . count($reports) . "\n";
echo "Processed: {$processed}\n";
echo "Maintenance Records Created: {$created}\n";
echo "Skipped (no maintenance): {$skipped}\n";
echo "Errors: {$errors}\n";
echo "===========================================\n";

