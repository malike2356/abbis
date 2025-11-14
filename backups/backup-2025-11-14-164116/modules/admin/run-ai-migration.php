<?php
/**
 * Run AI Migration - Web Accessible
 * Creates AI tables if they don't exist
 */
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('system.admin');

$page_title = 'Run AI Migration';
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token.';
    } else {
        try {
            $pdo = getDBConnection();
            
            $migrationFile = __DIR__ . '/../../database/migrations/phase5/001_create_ai_tables.sql';
            if (!file_exists($migrationFile)) {
                $errors[] = "Migration file not found: $migrationFile";
            } else {
                $sql = file_get_contents($migrationFile);
                
                // Split SQL statements
                $statements = array_filter(
                    array_map('trim', explode(';', $sql)),
                    function($stmt) {
                        return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
                    }
                );
                
                $successCount = 0;
                $skippedCount = 0;
                
                foreach ($statements as $statement) {
                    if (empty(trim($statement))) {
                        continue;
                    }
                    
                    try {
                        $pdo->exec($statement);
                        $successCount++;
                    } catch (PDOException $e) {
                        // Ignore "table already exists" errors
                        if (strpos($e->getMessage(), 'already exists') !== false || 
                            strpos($e->getMessage(), 'Duplicate') !== false) {
                            $skippedCount++;
                        } else {
                            $errors[] = "Error executing statement: " . $e->getMessage();
                        }
                    }
                }
                
                if ($successCount > 0) {
                    $messages[] = "Successfully executed $successCount SQL statement(s).";
                }
                if ($skippedCount > 0) {
                    $messages[] = "Skipped $skippedCount statement(s) (tables already exist).";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Migration failed: " . $e->getMessage();
        }
    }
}

// Check table status
$tableStatus = [];
try {
    $pdo = getDBConnection();
    $tables = ['ai_usage_logs', 'ai_response_cache', 'ai_provider_config'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $exists = $stmt->rowCount() > 0;
            $tableStatus[$table] = [
                'exists' => $exists,
                'columns' => []
            ];
            
            if ($exists) {
                $colsStmt = $pdo->query("DESCRIBE $table");
                $tableStatus[$table]['columns'] = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $tableStatus[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }
} catch (Exception $e) {
    $errors[] = "Error checking tables: " . $e->getMessage();
}

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-fluid" style="max-width: 1000px; margin: 0 auto; padding: 24px;">
    <div class="page-header" style="margin-bottom: 32px;">
        <h1>🔄 Run AI Migration</h1>
        <p class="lead">Create AI tables for usage logging and provider configuration</p>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($messages): ?>
        <div class="alert alert-success">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo e($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <h2 style="margin-top: 0;">📊 Table Status</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                    <th>Columns</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableStatus as $tableName => $status): ?>
                    <tr>
                        <td><code><?php echo e($tableName); ?></code></td>
                        <td>
                            <?php if ($status['exists']): ?>
                                <span class="badge badge-success">✓ Exists</span>
                            <?php else: ?>
                                <span class="badge badge-danger">✗ Not Found</span>
                                <?php if (isset($status['error'])): ?>
                                    <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                        <?php echo e($status['error']); ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($status['exists'] && !empty($status['columns'])): ?>
                                <span style="font-size: 12px; color: var(--secondary);">
                                    <?php echo count($status['columns']); ?> columns
                                </span>
                            <?php else: ?>
                                <span style="color: var(--secondary);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card" style="padding: 24px;">
        <h2 style="margin-top: 0;">🚀 Run Migration</h2>
        <p>This will create the following tables if they don't already exist:</p>
        <ul>
            <li><code>ai_usage_logs</code> - Logs AI feature usage</li>
            <li><code>ai_response_cache</code> - Caches AI responses</li>
            <li><code>ai_provider_config</code> - Stores AI provider configurations</li>
        </ul>
        
        <form method="post" style="margin-top: 24px;">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="run_migration" value="1">
            <button type="submit" class="btn btn-primary">▶️ Run Migration</button>
            <a href="ai-governance.php" class="btn btn-outline">← Back to AI Governance</a>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

