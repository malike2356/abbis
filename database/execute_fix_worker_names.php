<?php
/**
 * Execute Worker Name Fix - Direct execution
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting worker name fix...\n\n";

try {
    $pdo->beginTransaction();
    
    // Step 1: Remove workers not in the corrected list
    $deleteStmt = $pdo->prepare("DELETE FROM workers WHERE worker_name IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $deleteStmt->execute(['Peter', 'Jawich', 'Razak', 'Anthony Emma', 'Mtw', 'BOSS', 'new', 'Finest', 'Giet', 'Linest', 'linef', 'Internal', 'Castro']);
    $deletedCount = $deleteStmt->rowCount();
    echo "✓ Deleted $deletedCount workers not in corrected list\n";
    
    // Step 2: Update roles to match corrected list
    $updates = [
        ['Atta', 'Driller'],
        ['Isaac', 'Rig Driver / Spanner'],
        ['Tawiah', 'Rodboy'],
        ['Godwin', 'Rodboy'],
        ['Asare', 'Rodboy'],
        ['Earnest', 'Driller'],
        ['Owusua', 'Rig Driver'],
        ['Rasta', 'Spanner boy / Table boy'],
        ['Chief', 'Rodboy'],
        ['Kwesi', 'Rodboy']
    ];
    
    $updateStmt = $pdo->prepare("UPDATE workers SET role = ? WHERE worker_name = ?");
    $updatedCount = 0;
    foreach ($updates as $update) {
        $updateStmt->execute([$update[1], $update[0]]);
        $count = $updateStmt->rowCount();
        $updatedCount += $count;
        if ($count > 0) {
            echo "✓ Updated {$update[0]} to role: {$update[1]} ($count rows)\n";
        }
    }
    
    // Step 3: Remove workers with incorrect roles
    $cleanupStmt = $pdo->prepare("
        DELETE FROM workers WHERE 
        (worker_name = 'Atta' AND role NOT LIKE '%Driller%') OR
        (worker_name = 'Isaac' AND role NOT LIKE '%Rig Driver%' AND role NOT LIKE '%Spanner%') OR
        (worker_name = 'Tawiah' AND role NOT LIKE '%Rodboy%') OR
        (worker_name = 'Godwin' AND role NOT LIKE '%Rodboy%') OR
        (worker_name = 'Asare' AND role NOT LIKE '%Rodboy%') OR
        (worker_name = 'Earnest' AND role NOT LIKE '%Driller%') OR
        (worker_name = 'Owusua' AND role NOT LIKE '%Rig Driver%') OR
        (worker_name = 'Rasta' AND (role NOT LIKE '%Spanner%' AND role NOT LIKE '%Table%')) OR
        (worker_name = 'Chief' AND role NOT LIKE '%Rodboy%') OR
        (worker_name = 'Kwesi' AND role NOT LIKE '%Rodboy%')
    ");
    $cleanupStmt->execute();
    $cleanedCount = $cleanupStmt->rowCount();
    echo "✓ Cleaned $cleanedCount workers with incorrect roles\n";
    
    // Get final results
    $verifyStmt = $pdo->query("
        SELECT worker_name, role, status, COUNT(*) as count 
        FROM workers 
        GROUP BY worker_name, role, status 
        ORDER BY worker_name
    ");
    
    $results = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pdo->commit();
    
    echo "\n=== Final Results ===\n";
    echo "Current workers in database:\n";
    foreach ($results as $row) {
        echo sprintf("  - %s (%s) [%s] - Count: %d\n", 
            $row['worker_name'], 
            $row['role'], 
            $row['status'],
            $row['count']
        );
    }
    
    echo "\n✅ Worker name fix completed successfully!\n";
    echo "Summary: Deleted: $deletedCount, Updated: $updatedCount, Cleaned: $cleanedCount\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

