<?php
/**
 * Run All POS Migrations
 * Creates all required tables for POS system
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.inventory.manage');

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
$success = [];
$migrationsRun = 0;

// List of migration files in order
$migrationFiles = [
    '005_phase3_features.sql',  // Employee shifts, product variants
    '006_phase4_features.sql',   // Loyalty, gift cards, tax, promotions
    '007_receipt_numbers.sql',   // Receipt numbers
    '009_materials_pos_integration.sql' // Materials integration
];

// Check which tables exist
$requiredTables = [
    'pos_employee_shifts',
    'pos_product_variants',
    'pos_loyalty_programs',
    'pos_gift_cards',
    'pos_tax_rules',
    'pos_promotions',
    'pos_material_returns',
    'pos_material_mappings',
    'pos_material_activity_log'
];

$existingTables = [];
foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
        }
    } catch (PDOException $e) {
        // Table doesn't exist
    }
}

// Execute migrations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migrations'])) {
    foreach ($migrationFiles as $migrationFile) {
        $migrationPath = $rootPath . '/database/migrations/pos/' . $migrationFile;
        
        if (!file_exists($migrationPath)) {
            $errors[] = "Migration file not found: {$migrationFile}";
            continue;
        }
        
        $sql = file_get_contents($migrationPath);
        
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split into statements
        $statements = [];
        $currentStatement = '';
        $parenCount = 0;
        
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }
            
            $currentStatement .= $line . "\n";
            $parenCount += substr_count($line, '(') - substr_count($line, ')');
            
            if (strpos($trimmed, ';') !== false && $parenCount === 0) {
                $stmt = trim($currentStatement);
                if (!empty($stmt)) {
                    $statements[] = $stmt;
                }
                $currentStatement = '';
            }
        }
        
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }
        
        // Execute statements
        foreach ($statements as $statement) {
            if (empty(trim($statement))) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $migrationsRun++;
            } catch (PDOException $e) {
                // Check if it's a "table already exists" error - this is OK
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'Duplicate table') !== false ||
                    strpos($e->getMessage(), 'Duplicate key') !== false ||
                    strpos($e->getMessage(), 'Duplicate column') !== false) {
                    $success[] = "Skipped (already exists) in {$migrationFile}";
                } else {
                    $errors[] = "Error in {$migrationFile}: " . $e->getMessage();
                }
            }
        }
    }
}

// Re-check tables after migration
$existingTablesAfter = [];
foreach ($requiredTables as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $existingTablesAfter[] = $table;
        }
    } catch (PDOException $e) {
        // Table doesn't exist
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Run POS Migrations</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1e3a5f;
            margin-bottom: 20px;
        }
        .status {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .btn {
            background: #0ea5e9;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #0284c7;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .check {
            color: green;
            font-weight: bold;
        }
        .missing {
            color: red;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 POS System Migrations</h1>
        
        <?php if (!empty($success) || !empty($errors)): ?>
            <?php if (!empty($success)): ?>
                <div class="status success">
                    <strong>✅ Success:</strong>
                    <ul>
                        <?php foreach (array_slice($success, 0, 10) as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                        <?php if (count($success) > 10): ?>
                            <li>... and <?php echo count($success) - 10; ?> more</li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($migrationsRun > 0): ?>
                        <p><strong><?php echo $migrationsRun; ?> statements executed successfully.</strong></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($errors)): ?>
                <div class="status error">
                    <strong>❌ Errors:</strong>
                    <ul>
                        <?php foreach ($errors as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div class="status info">
            <strong>📋 Required Tables Status:</strong>
            <table>
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requiredTables as $table): ?>
                    <tr>
                        <td><code><?php echo $table; ?></code></td>
                        <td>
                            <?php if (in_array($table, $existingTablesAfter)): ?>
                                <span class="check">✅ Exists</span>
                            <?php else: ?>
                                <span class="missing">❌ Missing</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($existingTablesAfter) < count($requiredTables)): ?>
            <form method="POST">
                <p>Click the button below to run all POS migrations and create the required tables.</p>
                <button type="submit" name="run_migrations" value="1" class="btn">Run All Migrations</button>
            </form>
        <?php else: ?>
            <div class="status success">
                <strong>✅ All tables exist!</strong>
                <p>All POS migrations are complete. The system is ready to use.</p>
                <a href="<?php echo app_base_path(); ?>/pos/index.php?action=admin&tab=dashboard" class="btn" style="text-decoration: none; display: inline-block;">Go to POS Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

