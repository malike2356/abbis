<?php
/**
 * Migration Helper Functions
 * Provides consistent table existence checks and migration warnings across the system
 */

/**
 * Check if a table exists in the database
 * @param PDO $pdo Database connection
 * @param string $tableName Table name to check
 * @return bool True if table exists, false otherwise
 */
function tableExists($pdo, $tableName) {
    try {
        $stmt = $pdo->prepare("SELECT 1 FROM `{$tableName}` LIMIT 1");
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Check multiple tables and return missing ones
 * @param PDO $pdo Database connection
 * @param array $tableNames Array of table names to check
 * @return array Array of missing table names
 */
function checkTablesExist($pdo, $tableNames) {
    $missing = [];
    foreach ($tableNames as $table) {
        if (!tableExists($pdo, $table)) {
            $missing[] = $table;
        }
    }
    return $missing;
}

/**
 * Display migration warning message for missing tables
 * @param array $missingTables Array of missing table names
 * @param string $migrationFile Suggested migration file name
 * @param string $featureName Human-readable feature name
 * @return string HTML for the warning message
 */
function showMigrationWarning($missingTables, $migrationFile = null, $featureName = null) {
    if (empty($missingTables)) {
        return '';
    }
    
    $tablesList = implode(', ', array_map(function($t) {
        return "<code>{$t}</code>";
    }, $missingTables));
    
    $migrationInfo = '';
    if ($migrationFile) {
        $migrationInfo = "<p style='margin: 10px 0 0 0; color: #856404;'>
            Please run the <code>{$migrationFile}</code> migration to create the required tables.
        </p>";
    }
    
    $featureText = $featureName ? " ({$featureName})" : '';
    
    $html = "<div style='margin-top: 12px; padding: 15px; border: 1px solid #ff9800; border-radius: 6px; background: #fff3cd;'>
        <strong>⚠️ Required tables not found{$featureText}.</strong>
        <p style='margin: 10px 0 0 0; color: #856404;'>
            Missing tables: {$tablesList}
        </p>
        {$migrationInfo}
        <a href='database-migrations.php' class='btn btn-primary' style='margin-top: 10px;'>
            <i class='fas fa-database'></i> Go to Database Migrations →
        </a>
    </div>";
    
    return $html;
}

/**
 * Safe query execution with migration check
 * Returns empty array on error and sets tableMissing flag
 * @param PDO $pdo Database connection
 * @param string $sql SQL query to execute
 * @param array $params Query parameters
 * @param array $requiredTables Tables that must exist for this query
 * @param bool &$tableMissing Reference to flag that will be set to true if tables are missing
 * @return array Query results or empty array on error
 */
function safeQuery($pdo, $sql, $params = [], $requiredTables = [], &$tableMissing = false) {
    // Check required tables first
    if (!empty($requiredTables)) {
        $missing = checkTablesExist($pdo, $requiredTables);
        if (!empty($missing)) {
            $tableMissing = true;
            return [];
        }
    }
    
    try {
        if (empty($params)) {
            $stmt = $pdo->query($sql);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Check if it's a "table doesn't exist" error
        if (strpos($e->getMessage(), "doesn't exist") !== false || 
            strpos($e->getMessage(), "Base table") !== false ||
            $e->getCode() == '42S02') {
            $tableMissing = true;
        }
        return [];
    }
}

/**
 * Get migration file for a feature/module
 * @param string $moduleName Module name (e.g., 'assets', 'maintenance', 'inventory')
 * @return string|null Migration file name or null
 */
function getMigrationFileForModule($moduleName) {
    $migrations = [
        'assets' => 'maintenance_assets_inventory_migration.sql',
        'maintenance' => 'maintenance_assets_inventory_migration.sql',
        'inventory' => 'maintenance_assets_inventory_migration.sql',
        'materials' => 'maintenance_assets_inventory_migration.sql',
        'catalog' => 'catalog_migration.sql',
        'crm' => 'crm_migration.sql',
        'suppliers' => null, // Created inline in suppliers.php
        'purchase_orders' => null, // Created inline in purchase-order-draft.php
    ];
    
    return $migrations[$moduleName] ?? 'maintenance_assets_inventory_migration.sql';
}
