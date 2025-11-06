<?php
/**
 * Assign Workers to Rigs
 * Based on corrected list:
 * Red Rig: Atta, Isaac, Tawiah, Godwin, Asare
 * Green Rig: Earnest, Owusua, Rasta, Chief, Godwin, Kwesi
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting rig assignment for workers...\n\n";

try {
    // First, check if worker_rig_preferences table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'worker_rig_preferences'");
    if ($checkTable->rowCount() == 0) {
        echo "Creating worker_rig_preferences table...\n";
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `worker_rig_preferences` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `worker_id` int(11) NOT NULL,
              `rig_id` int(11) NOT NULL,
              `preference_level` enum('primary','secondary','available') DEFAULT 'primary',
              `notes` text DEFAULT NULL,
              `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `worker_rig_unique` (`worker_id`, `rig_id`),
              KEY `worker_id` (`worker_id`),
              KEY `rig_id` (`rig_id`),
              FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
              FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "✓ Table created\n\n";
    }
    
    // Get rigs - find Red Rig and Green Rig
    $rigsStmt = $pdo->query("SELECT id, rig_name, rig_code FROM rigs WHERE status = 'active' ORDER BY id");
    $rigs = $rigsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Available rigs:\n";
    foreach ($rigs as $rig) {
        echo "  - ID: {$rig['id']}, Name: {$rig['rig_name']}, Code: {$rig['rig_code']}\n";
    }
    echo "\n";
    
    // Find or create Red Rig and Green Rig
    $redRigId = null;
    $greenRigId = null;
    
    foreach ($rigs as $rig) {
        $rigNameLower = strtolower($rig['rig_name']);
        if (strpos($rigNameLower, 'red') !== false) {
            $redRigId = $rig['id'];
            echo "Found Red Rig: {$rig['rig_name']} (ID: {$redRigId})\n";
        } elseif (strpos($rigNameLower, 'green') !== false) {
            $greenRigId = $rig['id'];
            echo "Found Green Rig: {$rig['rig_name']} (ID: {$greenRigId})\n";
        }
    }
    
    // If not found, create them or use first two rigs
    if (!$redRigId && !$greenRigId && count($rigs) >= 2) {
        // Use first rig as Red, second as Green
        $redRigId = $rigs[0]['id'];
        $greenRigId = $rigs[1]['id'];
        echo "Using Rig 1 as Red Rig (ID: {$redRigId})\n";
        echo "Using Rig 2 as Green Rig (ID: {$greenRigId})\n";
    } elseif (!$redRigId && count($rigs) >= 1) {
        // Create Red Rig
        $stmt = $pdo->prepare("INSERT INTO rigs (rig_name, rig_code, status) VALUES (?, ?, 'active')");
        $stmt->execute(['Red Rig', 'RED-01']);
        $redRigId = $pdo->lastInsertId();
        echo "Created Red Rig (ID: {$redRigId})\n";
    }
    
    if (!$greenRigId) {
        // Create Green Rig
        $stmt = $pdo->prepare("INSERT INTO rigs (rig_name, rig_code, status) VALUES (?, ?, 'active')");
        $stmt->execute(['Green Rig', 'GRN-01']);
        $greenRigId = $pdo->lastInsertId();
        echo "Created Green Rig (ID: {$greenRigId})\n";
    }
    
    if (!$redRigId || !$greenRigId) {
        throw new Exception("Could not determine Red Rig and Green Rig IDs");
    }
    
    echo "\n";
    
    // Get workers
    $workersStmt = $pdo->query("SELECT id, worker_name FROM workers ORDER BY worker_name");
    $workers = $workersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Define assignments
    $redRigWorkers = ['Atta', 'Isaac', 'Tawiah', 'Godwin', 'Asare'];
    $greenRigWorkers = ['Earnest', 'Owusua', 'Rasta', 'Chief', 'Godwin', 'Kwesi'];
    
    $pdo->beginTransaction();
    
    // Clear existing assignments for these workers
    $workerIds = [];
    foreach ($workers as $worker) {
        $workerIds[] = $worker['id'];
    }
    if (!empty($workerIds)) {
        $placeholders = str_repeat('?,', count($workerIds) - 1) . '?';
        $clearStmt = $pdo->prepare("DELETE FROM worker_rig_preferences WHERE worker_id IN ($placeholders)");
        $clearStmt->execute($workerIds);
        echo "Cleared existing rig assignments\n";
    }
    
    // Assign workers to Red Rig
    echo "\nAssigning workers to Red Rig:\n";
    $assignStmt = $pdo->prepare("
        INSERT INTO worker_rig_preferences (worker_id, rig_id, preference_level, notes) 
        VALUES (?, ?, 'primary', ?)
        ON DUPLICATE KEY UPDATE preference_level = 'primary', notes = ?
    ");
    
    foreach ($redRigWorkers as $workerName) {
        $workerId = null;
        foreach ($workers as $worker) {
            if (strcasecmp($worker['worker_name'], $workerName) === 0) {
                $workerId = $worker['id'];
                break;
            }
        }
        
        if ($workerId) {
            $assignStmt->execute([$workerId, $redRigId, "Assigned to Red Rig", "Assigned to Red Rig"]);
            echo "  ✓ {$workerName} (ID: {$workerId}) assigned to Red Rig\n";
        } else {
            echo "  ⚠ Worker '{$workerName}' not found\n";
        }
    }
    
    // Assign workers to Green Rig
    echo "\nAssigning workers to Green Rig:\n";
    foreach ($greenRigWorkers as $workerName) {
        $workerId = null;
        foreach ($workers as $worker) {
            if (strcasecmp($worker['worker_name'], $workerName) === 0) {
                $workerId = $worker['id'];
                break;
            }
        }
        
        if ($workerId) {
            $assignStmt->execute([$workerId, $greenRigId, "Assigned to Green Rig", "Assigned to Green Rig"]);
            echo "  ✓ {$workerName} (ID: {$workerId}) assigned to Green Rig\n";
        } else {
            echo "  ⚠ Worker '{$workerName}' not found\n";
        }
    }
    
    // Verify assignments
    echo "\n=== Verification ===\n";
    $verifyStmt = $pdo->query("
        SELECT w.worker_name, r.rig_name, r.rig_code, wrp.preference_level
        FROM worker_rig_preferences wrp
        JOIN workers w ON wrp.worker_id = w.id
        JOIN rigs r ON wrp.rig_id = r.id
        ORDER BY r.rig_name, w.worker_name
    ");
    
    $assignments = $verifyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentRig = '';
    foreach ($assignments as $assignment) {
        if ($currentRig !== $assignment['rig_name']) {
            $currentRig = $assignment['rig_name'];
            echo "\n{$currentRig} ({$assignment['rig_code']}):\n";
        }
        echo "  - {$assignment['worker_name']} ({$assignment['preference_level']})\n";
    }
    
    $pdo->commit();
    
    echo "\n✅ Rig assignments completed successfully!\n";
    echo "Total assignments: " . count($assignments) . "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

