<?php
/**
 * Safe System Data Purge/Wipe
 * Requires password confirmation for security
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

// Get password and confirmation
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$confirmText = $_POST['confirm_text'] ?? '';

// Validate password confirmation
if (empty($password) || empty($passwordConfirm)) {
    jsonResponse(['success' => false, 'message' => 'Password and password confirmation are required'], 400);
}

if ($password !== $passwordConfirm) {
    jsonResponse(['success' => false, 'message' => 'Passwords do not match'], 400);
}

// Verify user's password
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    jsonResponse(['success' => false, 'message' => 'Invalid password'], 403);
}

// Get purge mode and tables
$purgeMode = $_POST['purge_mode'] ?? 'selective';
$tablesToPurge = [];

if ($purgeMode === 'all') {
    // Purge everything (except users table for security)
    $tablesToPurge = [
        'field_reports', 'payroll_entries', 'expense_entries', 'rig_fee_debts',
        'worker_loans', 'clients', 'workers', 'rigs', 'materials_inventory',
        'catalog_items', 'catalog_categories', 'catalog_price_history', 'field_report_items',
        'login_attempts', 'cache_stats'
    ];
    // Note: system_config is preserved to keep system settings
    $requiredConfirm = 'DELETE ALL DATA';
} else {
    // Selective purge - get selected tables
    $tablesToPurge = $_POST['purge_tables'] ?? [];
    if (empty($tablesToPurge)) {
        jsonResponse(['success' => false, 'message' => 'No tables selected for purging'], 400);
    }
    $requiredConfirm = 'DELETE SELECTED DATA';
}

// Require confirmation text
if ($confirmText !== $requiredConfirm) {
    jsonResponse(['success' => false, 'message' => "Please type '{$requiredConfirm}' to confirm"], 400);
}

// Perform purge
try {
    $pdo->beginTransaction();
    
    // Get table counts before deletion
    $allPossibleTables = [
        'field_reports', 'payroll_entries', 'expense_entries', 'rig_fee_debts',
        'worker_loans', 'clients', 'workers', 'rigs', 'materials_inventory',
        'catalog_items', 'catalog_categories', 'catalog_price_history', 'field_report_items',
        'login_attempts', 'cache_stats'
    ];
    
    $counts = [];
    foreach ($allPossibleTables as $table) {
        if (in_array($table, $tablesToPurge)) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM `$table`");
                $counts[$table] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $counts[$table] = 0;
            }
        } else {
            $counts[$table] = 'preserved';
        }
    }
    
    // Define deletion order for tables with foreign key dependencies
    // Catalog tables must be deleted in specific order due to foreign keys
    $deletionOrder = [
        'field_report_items',      // References catalog_items
        'catalog_price_history',   // References catalog_items (CASCADE, but delete explicitly for safety)
        'catalog_items',           // References catalog_categories (SET NULL, safe)
        'catalog_categories'       // Can be deleted last
    ];
    
    // Separate catalog tables from other tables
    $catalogTables = [];
    $otherTables = [];
    
    foreach ($tablesToPurge as $table) {
        if (in_array($table, $deletionOrder)) {
            $catalogTables[] = $table;
        } else {
            $otherTables[] = $table;
        }
    }
    
    // Sort catalog tables according to deletion order
    $sortedCatalogTables = [];
    foreach ($deletionOrder as $orderedTable) {
        if (in_array($orderedTable, $catalogTables)) {
            $sortedCatalogTables[] = $orderedTable;
        }
    }
    
    // Delete other tables first, then catalog tables in order
    $orderedTablesToPurge = array_merge($otherTables, $sortedCatalogTables);
    
    // Delete data from selected tables only (keep table structure)
    foreach ($orderedTablesToPurge as $table) {
        try {
            $pdo->exec("DELETE FROM `$table`");
        } catch (PDOException $e) {
            // Table might not exist, skip it
            error_log("Purge: Table $table does not exist or error: " . $e->getMessage());
        }
    }
    
    // Reset auto-increment counters for purged tables
    foreach ($orderedTablesToPurge as $table) {
        try {
            $pdo->exec("ALTER TABLE `$table` AUTO_INCREMENT = 1");
        } catch (PDOException $e) {
            // Ignore errors
        }
    }
    
    // Log the purge action
    try {
        $purgeSummary = $purgeMode === 'all' ? 'ALL DATA' : implode(', ', $tablesToPurge);
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value, description) 
            VALUES ('last_purge', ?, 'Last system purge timestamp')
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value, description) 
            VALUES ('last_purge_user', ?, 'User who performed last purge')
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->execute([$_SESSION['username'], $_SESSION['username']]);
        
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value, description) 
            VALUES ('last_purge_tables', ?, 'Tables purged in last purge')
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->execute([$purgeSummary, $purgeSummary]);
    } catch (PDOException $e) {
        // Ignore if config table doesn't exist
    }
    
    $pdo->commit();
    
    $message = $purgeMode === 'all' 
        ? 'All system data has been purged successfully'
        : 'Selected data has been purged successfully';
    
    jsonResponse([
        'success' => true,
        'message' => $message,
        'deleted_counts' => $counts,
        'purged_tables' => $tablesToPurge,
        'mode' => $purgeMode
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Purge error: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Purge failed: ' . $e->getMessage()], 500);
}

