<?php
/**
 * Fix RPM Data Entry Errors
 * Corrects misplaced decimal points in RPM values
 * Recalculates current_rpm for rigs
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/plain');

$pdo = getDBConnection();

echo "===========================================\n";
echo "RPM Data Correction Script\n";
echo "===========================================\n\n";

// Find reports with unrealistic RPM values (> 1000)
echo "Finding reports with unrealistic RPM values...\n";
$stmt = $pdo->query("
    SELECT id, report_id, rig_id, report_date, start_rpm, finish_rpm, total_rpm
    FROM field_reports
    WHERE (start_rpm > 1000 OR finish_rpm > 1000)
    ORDER BY report_date DESC, id DESC
");
$problematicReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($problematicReports) . " reports with unrealistic RPM values\n\n";

if (empty($problematicReports)) {
    echo "No issues found. Exiting.\n";
    exit(0);
}

echo "===========================================\n";
echo "PROBLEMATIC REPORTS\n";
echo "===========================================\n\n";

foreach ($problematicReports as $report) {
    echo "Report ID: {$report['report_id']}\n";
    echo "  Date: {$report['report_date']}\n";
    echo "  Start RPM: " . ($report['start_rpm'] ?? 'NULL') . "\n";
    echo "  Finish RPM: " . ($report['finish_rpm'] ?? 'NULL') . "\n";
    echo "  Total RPM: " . ($report['total_rpm'] ?? 'NULL') . "\n";
    
    // Suggest corrections
    $suggestions = [];
    if (!empty($report['start_rpm']) && $report['start_rpm'] > 1000) {
        $suggested = $report['start_rpm'] / 100;
        $suggestions[] = "Start RPM: {$report['start_rpm']} → {$suggested}";
    }
    if (!empty($report['finish_rpm']) && $report['finish_rpm'] > 1000) {
        $suggested = $report['finish_rpm'] / 100;
        $suggestions[] = "Finish RPM: {$report['finish_rpm']} → {$suggested}";
    }
    
    if (!empty($suggestions)) {
        echo "  Suggested corrections:\n";
        foreach ($suggestions as $suggestion) {
            echo "    - {$suggestion}\n";
        }
    }
    echo "\n";
}

echo "===========================================\n";
echo "AUTO-CORRECTION\n";
echo "===========================================\n\n";
echo "This script will automatically correct RPM values > 1000 by dividing by 100.\n";
echo "This assumes the error is a misplaced decimal point (e.g., 28783 → 287.83)\n\n";

// Check for command-line argument for auto-confirmation
$autoConfirm = isset($argv[1]) && ($argv[1] === '--yes' || $argv[1] === '-y');

if (!$autoConfirm) {
    // Try readline if available, otherwise prompt with fgets
    if (function_exists('readline')) {
        $confirm = readline("Continue with auto-correction? (yes/no): ");
    } else {
        echo "Continue with auto-correction? (yes/no): ";
        $confirm = trim(fgets(STDIN));
    }
    
    if (strtolower($confirm) !== 'yes') {
        echo "Cancelled. No changes made.\n";
        exit(0);
    }
} else {
    echo "Auto-confirmation enabled. Proceeding with corrections...\n\n";
}

$pdo->beginTransaction();

try {
    $corrected = 0;
    $recalculated = [];
    
    foreach ($problematicReports as $report) {
        $updates = [];
        $params = [];
        
        // Fix start_rpm if > 1000
        if (!empty($report['start_rpm']) && $report['start_rpm'] > 1000) {
            $correctedStart = $report['start_rpm'] / 100;
            $updates[] = "start_rpm = ?";
            $params[] = $correctedStart;
            echo "✓ Report {$report['report_id']}: start_rpm {$report['start_rpm']} → {$correctedStart}\n";
        }
        
        // Fix finish_rpm if > 1000
        if (!empty($report['finish_rpm']) && $report['finish_rpm'] > 1000) {
            $correctedFinish = $report['finish_rpm'] / 100;
            $updates[] = "finish_rpm = ?";
            $params[] = $correctedFinish;
            echo "✓ Report {$report['report_id']}: finish_rpm {$report['finish_rpm']} → {$correctedFinish}\n";
            
            // Recalculate total_rpm
            $newStart = isset($correctedStart) ? $correctedStart : ($report['start_rpm'] ?? 0);
            if ($newStart > 0) {
                $newTotal = max(0, $correctedFinish - $newStart);
                $updates[] = "total_rpm = ?";
                $params[] = $newTotal;
                echo "  → Recalculated total_rpm: {$newTotal}\n";
            }
        } elseif (!empty($updates) && !empty($report['start_rpm']) && $report['start_rpm'] <= 1000) {
            // If start was corrected but finish wasn't > 1000, recalculate total_rpm
            $newStart = isset($correctedStart) ? $correctedStart : $report['start_rpm'];
            $finishRpm = $report['finish_rpm'] ?? 0;
            if ($finishRpm > 0 && $newStart > 0) {
                $newTotal = max(0, $finishRpm - $newStart);
                $updates[] = "total_rpm = ?";
                $params[] = $newTotal;
                echo "  → Recalculated total_rpm: {$newTotal}\n";
            }
        }
        
        if (!empty($updates)) {
            $params[] = $report['id'];
            $updateStmt = $pdo->prepare("
                UPDATE field_reports 
                SET " . implode(', ', $updates) . "
                WHERE id = ?
            ");
            $updateStmt->execute($params);
            $corrected++;
            
            // Track rig for recalculation
            if (!empty($report['rig_id'])) {
                $recalculated[$report['rig_id']] = true;
            }
        }
    }
    
    echo "\n===========================================\n";
    echo "Recalculating Current RPM for Affected Rigs\n";
    echo "===========================================\n\n";
    
    // Recalculate current_rpm for affected rigs
    foreach (array_keys($recalculated) as $rigId) {
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
            // Calculate from sum of total_rpm if no finish_rpm available
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(total_rpm), 0) as total_rpm_sum
                FROM field_reports
                WHERE rig_id = ? 
                AND total_rpm IS NOT NULL 
                AND total_rpm > 0
                AND total_rpm < 1000
            ");
            $stmt->execute([$rigId]);
            $result = $stmt->fetch();
            
            if ($result && !empty($result['total_rpm_sum'])) {
                $newCurrentRpm = floatval($result['total_rpm_sum']);
                $updateStmt = $pdo->prepare("UPDATE rigs SET current_rpm = ? WHERE id = ?");
                $updateStmt->execute([$newCurrentRpm, $rigId]);
                
                echo "✓ Updated rig {$rigId}: current_rpm = {$newCurrentRpm} (calculated from sum)\n";
            } else {
                echo "⚠ Could not recalculate RPM for rig {$rigId} - no valid data found\n";
            }
        }
    }
    
    $pdo->commit();
    
    echo "\n===========================================\n";
    echo "Correction Complete!\n";
    echo "===========================================\n";
    echo "Reports corrected: {$corrected}\n";
    echo "Rigs recalculated: " . count($recalculated) . "\n";
    echo "===========================================\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "All changes rolled back.\n";
    exit(1);
}

