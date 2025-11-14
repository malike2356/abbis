<?php
/**
 * Refine RPM Data Corrections
 * Fixes reports where finish_rpm was incorrectly adjusted
 * Recalculates total_rpm for all corrected reports
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "===========================================\n";
echo "RPM Data Refinement Script\n";
echo "===========================================\n\n";

// Find reports where finish_rpm < start_rpm (logical error)
echo "Finding reports with finish_rpm < start_rpm...\n";
$stmt = $pdo->query("
    SELECT id, report_id, rig_id, report_date, start_rpm, finish_rpm, total_rpm
    FROM field_reports
    WHERE start_rpm IS NOT NULL 
    AND finish_rpm IS NOT NULL
    AND finish_rpm < start_rpm
    ORDER BY report_date DESC
");
$problematicReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($problematicReports) . " reports with finish_rpm < start_rpm\n\n";

if (empty($problematicReports)) {
    echo "No issues found. All data looks correct.\n";
    exit(0);
}

$pdo->beginTransaction();

try {
    $fixed = 0;
    
    foreach ($problematicReports as $report) {
        echo "Report {$report['report_id']}:\n";
        echo "  Current: start_rpm = {$report['start_rpm']}, finish_rpm = {$report['finish_rpm']}\n";
        
        // If start_rpm is around 100x larger than finish_rpm, divide start_rpm by 100
        // If finish_rpm is around 100x smaller than start_rpm, multiply finish_rpm by 100
        $ratio = $report['start_rpm'] / ($report['finish_rpm'] > 0 ? $report['finish_rpm'] : 1);
        
        if ($ratio > 50 && $ratio < 150) {
            // start_rpm is about 100x too large
            $correctedStart = $report['start_rpm'] / 100;
            $correctedFinish = $report['finish_rpm'];
            echo "  → Fixing: start_rpm divided by 100\n";
        } elseif ($ratio < 0.02 && $ratio > 0.006) {
            // finish_rpm is about 100x too small
            $correctedStart = $report['start_rpm'];
            $correctedFinish = $report['finish_rpm'] * 100;
            echo "  → Fixing: finish_rpm multiplied by 100\n";
        } else {
            // Use the most recent finish_rpm as start_rpm for this report
            // This is likely a continuation from previous report
            $prevStmt = $pdo->prepare("
                SELECT finish_rpm 
                FROM field_reports 
                WHERE rig_id = ? 
                AND report_date < ? 
                AND finish_rpm IS NOT NULL
                ORDER BY report_date DESC, id DESC 
                LIMIT 1
            ");
            $prevStmt->execute([$report['rig_id'], $report['report_date']]);
            $prevReport = $prevStmt->fetch();
            
            if ($prevReport && !empty($prevReport['finish_rpm'])) {
                $correctedStart = floatval($prevReport['finish_rpm']);
                $correctedFinish = $report['finish_rpm'];
                echo "  → Fixing: Using previous report's finish_rpm as start_rpm\n";
            } else {
                // Default: use finish_rpm as both start and finish
                $correctedStart = $report['finish_rpm'];
                $correctedFinish = $report['finish_rpm'];
                echo "  → Warning: Could not determine correct start_rpm. Using finish_rpm for both.\n";
            }
        }
        
        $correctedTotal = max(0, $correctedFinish - $correctedStart);
        
        echo "  Corrected: start_rpm = {$correctedStart}, finish_rpm = {$correctedFinish}, total_rpm = {$correctedTotal}\n\n";
        
        $updateStmt = $pdo->prepare("
            UPDATE field_reports 
            SET start_rpm = ?, finish_rpm = ?, total_rpm = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$correctedStart, $correctedFinish, $correctedTotal, $report['id']]);
        $fixed++;
    }
    
    // Now recalculate total_rpm for all reports with start_rpm and finish_rpm
    echo "\n===========================================\n";
    echo "Recalculating total_rpm for all reports\n";
    echo "===========================================\n\n";
    
    $recalcStmt = $pdo->query("
        UPDATE field_reports
        SET total_rpm = GREATEST(0, finish_rpm - start_rpm)
        WHERE start_rpm IS NOT NULL 
        AND finish_rpm IS NOT NULL
        AND finish_rpm >= start_rpm
    ");
    $recalculated = $recalcStmt->rowCount();
    echo "✓ Recalculated total_rpm for {$recalculated} reports\n";
    
    // Recalculate current_rpm for affected rigs
    echo "\n===========================================\n";
    echo "Recalculating Current RPM for Affected Rigs\n";
    echo "===========================================\n\n";
    
    $rigIds = array_unique(array_column($problematicReports, 'rig_id'));
    foreach ($rigIds as $rigId) {
        echo "Recalculating RPM for rig ID {$rigId}...\n";
        
        // Get the most recent finish_rpm from reports
        $stmt = $pdo->prepare("
            SELECT finish_rpm 
            FROM field_reports 
            WHERE rig_id = ? 
            AND finish_rpm IS NOT NULL 
            AND finish_rpm > 0
            AND finish_rpm < 1000
            ORDER BY report_date DESC, id DESC 
            LIMIT 1
        ");
        $stmt->execute([$rigId]);
        $result = $stmt->fetch();
        
        if ($result && !empty($result['finish_rpm'])) {
            $newCurrentRpm = floatval($result['finish_rpm']);
            
            // Update rig current_rpm
            $updateStmt = $pdo->prepare("UPDATE rigs SET current_rpm = ? WHERE id = ?");
            $updateStmt->execute([$newCurrentRpm, $rigId]);
            
            echo "✓ Updated rig {$rigId}: current_rpm = {$newCurrentRpm}\n";
        } else {
            echo "⚠ Could not recalculate RPM for rig {$rigId} - no valid data found\n";
        }
    }
    
    $pdo->commit();
    
    echo "\n===========================================\n";
    echo "Refinement Complete!\n";
    echo "===========================================\n";
    echo "Reports fixed: {$fixed}\n";
    echo "Total RPM recalculated: {$recalculated}\n";
    echo "Rigs updated: " . count($rigIds) . "\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "All changes rolled back.\n";
    exit(1);
}

