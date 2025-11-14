<?php
/**
 * Run Materials Value Migration
 * Adds materials_value column to field_reports table
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('field_reports.manage');

$pdo = getDBConnection();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$errors = [];
$success = [];
$migrationRun = false;

$migrationFile = $rootPath . '/database/migrations/011_add_materials_value_column.sql';

// Check if column already exists
$columnExists = false;
try {
    $checkStmt = $pdo->query("SHOW COLUMNS FROM field_reports LIKE 'materials_value'");
    $columnExists = $checkStmt->rowCount() > 0;
} catch (PDOException $e) {
    $columnExists = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    if (!file_exists($migrationFile)) {
        $errors[] = "Migration file not found: 011_add_materials_value_column.sql";
    } else {
        $sql = file_get_contents($migrationFile);
        
        // Remove comments
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
        
        // Split into statements
        $statements = [];
        $currentStatement = '';
        
        foreach (explode("\n", $sql) as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) {
                continue;
            }
            
            $currentStatement .= $line . "\n";
            
            if (strpos($trimmed, ';') !== false) {
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
                // Handle IF NOT EXISTS for MySQL
                if (strpos($statement, 'ADD COLUMN IF NOT EXISTS') !== false) {
                    // MySQL doesn't support IF NOT EXISTS for ADD COLUMN, so check first
                    if (!$columnExists) {
                        $statement = str_replace('ADD COLUMN IF NOT EXISTS', 'ADD COLUMN', $statement);
                        $pdo->exec($statement);
                        $migrationRun = true;
                        $success[] = "Added materials_value column to field_reports table";
                    } else {
                        $success[] = "Column materials_value already exists, skipped";
                    }
                } else {
                    $pdo->exec($statement);
                    $migrationRun = true;
                    $success[] = "Executed: " . substr($statement, 0, 80) . "...";
                }
            } catch (PDOException $e) {
                // Check if it's a "column already exists" error
                if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                    strpos($e->getMessage(), 'already exists') !== false) {
                    $success[] = "Column materials_value already exists, skipped";
                } else {
                    $errors[] = "Error: " . $e->getMessage();
                }
            }
        }
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Run Materials Value Migration</title>
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
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Materials Value Column Migration</h1>
        
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
            <strong>📋 Column Status:</strong>
            <p>
                <?php if ($columnExists): ?>
                    <span style="color: green; font-weight: bold;">✅ Column <code>materials_value</code> already exists in <code>field_reports</code> table.</span>
                <?php else: ?>
                    <span style="color: orange; font-weight: bold;">⚠️ Column <code>materials_value</code> does not exist in <code>field_reports</code> table.</span>
                <?php endif; ?>
            </p>
            <p style="margin-top: 10px; font-size: 14px;">
                This column stores the total value of remaining materials (assets) calculated as:<br>
                <code>(Screen Pipes Remaining × Unit Cost) + (Plain Pipes Remaining × Unit Cost) + (Gravel Remaining × Unit Cost)</code>
            </p>
        </div>
        
        <?php if (!$columnExists): ?>
            <form method="POST">
                <p>Click the button below to add the <code>materials_value</code> column to the <code>field_reports</code> table.</p>
                <button type="submit" name="run_migration" value="1" class="btn">Run Migration</button>
            </form>
        <?php else: ?>
            <div class="status success">
                <strong>✅ Migration Complete!</strong>
                <p>The <code>materials_value</code> column is ready to use.</p>
                <a href="<?php echo app_base_path(); ?>/modules/field-reports.php" class="btn" style="text-decoration: none; display: inline-block;">Go to Field Reports</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>

