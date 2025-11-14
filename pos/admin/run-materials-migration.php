<?php
/**
 * Run Materials-POS Integration Migration
 * Creates tables for material returns and activity logging
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

// Read migration file
$migrationFile = $rootPath . '/database/migrations/pos/009_materials_pos_integration.sql';
if (!file_exists($migrationFile)) {
    die('Migration file not found: ' . $migrationFile);
}

$sql = file_get_contents($migrationFile);

// Remove comments and split into statements
$sql = preg_replace('/--.*$/m', '', $sql); // Remove single-line comments
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql); // Remove multi-line comments

// Split by semicolons, but be careful with CREATE TABLE statements
$statements = [];
$currentStatement = '';
$inCreateTable = false;
$parenCount = 0;

foreach (explode("\n", $sql) as $line) {
    $trimmed = trim($line);
    
    // Skip empty lines
    if (empty($trimmed)) {
        continue;
    }
    
    $currentStatement .= $line . "\n";
    
    // Count parentheses to track CREATE TABLE blocks
    $parenCount += substr_count($line, '(') - substr_count($line, ')');
    
    // Check if we're starting a CREATE TABLE
    if (stripos($trimmed, 'CREATE TABLE') !== false) {
        $inCreateTable = true;
    }
    
    // End of statement
    if (strpos($trimmed, ';') !== false && $parenCount === 0) {
        $stmt = trim($currentStatement);
        if (!empty($stmt)) {
            $statements[] = $stmt;
        }
        $currentStatement = '';
        $inCreateTable = false;
    }
}

// Add any remaining statement
if (!empty(trim($currentStatement))) {
    $statements[] = trim($currentStatement);
}

// Execute statements
// Note: CREATE TABLE is DDL and doesn't need transactions (some DBs don't support it)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    foreach ($statements as $statement) {
        if (empty(trim($statement))) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $success[] = "Executed: " . substr($statement, 0, 50) . "...";
        } catch (PDOException $e) {
            // Check if it's a "table already exists" error - this is OK
            if (strpos($e->getMessage(), 'already exists') !== false || 
                strpos($e->getMessage(), 'Duplicate table') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false ||
                strpos($e->getMessage(), 'Duplicate column') !== false) {
                $success[] = "Skipped (already exists): " . substr($statement, 0, 50) . "...";
            } else {
                $errors[] = "Error: " . $e->getMessage() . " in: " . substr($statement, 0, 50) . "...";
                // Continue with other statements even if one fails
            }
        }
    }
}

// Check which tables exist
$existingTables = [];
$requiredTables = ['pos_material_returns', 'pos_material_mappings', 'pos_material_activity_log'];
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>Run Materials-POS Migration</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Materials-POS Integration Migration</h1>
        
        <?php if (!empty($success) || !empty($errors)): ?>
            <?php if (!empty($success)): ?>
                <div class="status success">
                    <strong>✅ Success:</strong>
                    <ul>
                        <?php foreach ($success as $msg): ?>
                            <li><?php echo htmlspecialchars($msg); ?></li>
                        <?php endforeach; ?>
                    </ul>
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
            <strong>📋 Migration Status:</strong>
            <ul>
                <?php foreach ($requiredTables as $table): ?>
                    <li>
                        <?php echo $table; ?>: 
                        <?php if (in_array($table, $existingTables)): ?>
                            <span style="color: green;">✅ Exists</span>
                        <?php else: ?>
                            <span style="color: red;">❌ Missing</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <?php if (count($existingTables) < count($requiredTables)): ?>
            <form method="POST">
                <p>Click the button below to create the required tables for Materials-POS integration.</p>
                <button type="submit" name="run_migration" value="1" class="btn">Run Migration</button>
            </form>
        <?php else: ?>
            <div class="status success">
                <strong>✅ All tables exist!</strong>
                <p>Migration is complete. You can now use the material return functionality.</p>
                <a href="<?php echo app_base_path(); ?>/pos/index.php?action=admin&tab=dashboard" class="btn" style="text-decoration: none; display: inline-block;">Go to POS Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

