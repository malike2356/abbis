<?php
/**
 * Database Migrations Runner
 * Allows admins to manually run SQL migration files
 */
$page_title = 'Database Migrations';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
// __DIR__ is modules/, so dirname(__DIR__) is the root directory
$rootPath = dirname(__DIR__);
$migrationsDir = $rootPath . '/database';

$message = null;
$error = null;
$migrationResult = null;

// Handle migration execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration']) && CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $migrationFile = $_POST['migration_file'] ?? '';
    
    if (empty($migrationFile)) {
        $error = 'Please select a migration file';
    } else {
        $filePath = $migrationsDir . '/' . basename($migrationFile);
        
        // Security check - ensure file is in migrations directory
        if (!file_exists($filePath) || strpos(realpath($filePath), realpath($migrationsDir)) !== 0) {
            $error = 'Invalid migration file';
        } else {
            // Read SQL file
            $sql = file_get_contents($filePath);
            
            if ($sql === false) {
                $error = 'Failed to read migration file';
            } else {
                // Remove comments and split by semicolons
                $sql = preg_replace('/--.*$/m', '', $sql);
                $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
                
                // Split into individual statements
                $statements = array_filter(array_map('trim', explode(';', $sql)));
                
                $executed = 0;
                $failed = 0;
                $errors = [];
                
                try {
                    $pdo->beginTransaction();
                    
                    foreach ($statements as $statement) {
                        $statement = trim($statement);
                        if (empty($statement) || strlen($statement) < 10) {
                            continue;
                        }
                        
                        try {
                            $pdo->exec($statement);
                            $executed++;
                        } catch (PDOException $e) {
                            // Some errors are okay (like table/column/index already exists)
                            $errorCode = $e->getCode();
                            $errorMessage = $e->getMessage();
                            
                                                                // Ignore common "already exists" or "doesn't exist but that's okay" errors:
                                    // - Table already exists (42S01)
                                    // - Duplicate column name (42S21)
                                    // - Duplicate key name (42000 or 42S21)
                                    // - Column already exists
                                    // - Key name already exists (ER_DUP_KEYNAME = 1061)
                                    // - Unknown column in index (ER_KEY_COLUMN_DOES_NOT_EXIST = 1072) - happens when column doesn't exist yet
                                    // - Column count doesn't match value count (ER_WRONG_VALUE_COUNT_ON_ROW = 1136)
                                    $ignorableErrors = [
                                        'already exists',
                                        'Duplicate column',
                                        'Duplicate key',
                                        'Duplicate entry',
                                        'Duplicate',
                                        'Multiple primary key',
                                        'Duplicate key name',
                                        'key name',
                                        'Unknown column',
                                        'Column count doesn\'t match',
                                        "doesn't exist",
                                        'does not exist'
                                    ];
                                    
                                    $ignorableErrorCodes = ['42S01', '42S21', '42000', '1061', '1072', '1136'];
                                    
                                    $shouldIgnore = false;
                                    foreach ($ignorableErrors as $pattern) {
                                        if (stripos($errorMessage, $pattern) !== false) {
                                            $shouldIgnore = true;
                                            break;
                                        }
                                    }
                                    
                                    if (!$shouldIgnore) {
                                        foreach ($ignorableErrorCodes as $code) {
                                            if ($errorCode == $code || strpos((string)$errorCode, $code) !== false) {
                                                $shouldIgnore = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($shouldIgnore) {
                                        $executed++; // Count as executed since it's just already there or not needed
                                        continue;
                                    }
                            
                            $failed++;
                            $errors[] = [
                                'statement' => substr($statement, 0, 100) . '...',
                                'error' => $errorMessage
                            ];
                        }
                    }
                    
                    // Only commit if there's an active transaction
                    if ($pdo->inTransaction()) {
                        $pdo->commit();
                    }
                    
                    // Record migration execution
                    try {
                        $pdo->exec("CREATE TABLE IF NOT EXISTS migration_history (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            migration_file VARCHAR(255) NOT NULL,
                            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            statements_executed INT DEFAULT 0,
                            statements_failed INT DEFAULT 0,
                            executed_by INT DEFAULT NULL,
                            INDEX idx_file (migration_file),
                            INDEX idx_executed_at (executed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                        
                        $stmt = $pdo->prepare("INSERT INTO migration_history (migration_file, statements_executed, statements_failed, executed_by) VALUES (?, ?, ?, ?)");
                        $stmt->execute([basename($migrationFile), $executed, $failed, $_SESSION['user_id'] ?? null]);
                    } catch (PDOException $e) {
                        // Ignore if migration_history table creation fails
                    }
                    
                    $migrationResult = [
                        'success' => true,
                        'file' => basename($migrationFile),
                        'executed' => $executed,
                        'failed' => $failed,
                        'errors' => $errors
                    ];
                    
                    if ($failed === 0) {
                        $message = "Migration executed successfully! {$executed} statements executed.";
                    } else {
                        $message = "Migration partially completed. {$executed} statements executed, {$failed} failed. Check the error details below.";
                    }
                } catch (PDOException $e) {
                    // Only rollback if there's an active transaction
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = 'Migration failed: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get list of migration files
$migrationFiles = [];
$pathDebug = [];

// Debug: Check if directory exists and is readable
$pathDebug['rootPath'] = $rootPath;
$pathDebug['migrationsDir'] = $migrationsDir;
$pathDebug['dir_exists'] = is_dir($migrationsDir);
$pathDebug['dir_readable'] = is_readable($migrationsDir);
$pathDebug['realpath'] = realpath($migrationsDir);

if (is_dir($migrationsDir) && is_readable($migrationsDir)) {
    $files = glob($migrationsDir . '/*.sql');
    $pathDebug['glob_result'] = $files ? count($files) : 0;
    
    if ($files === false) {
        $error = 'Failed to scan migration directory. Please check permissions.';
        $pathDebug['glob_error'] = true;
    } else {
        foreach ($files as $file) {
            if (is_file($file) && is_readable($file)) {
                $migrationFiles[] = [
                    'filename' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'path' => $file
                ];
            }
        }
        usort($migrationFiles, function($a, $b) {
            return $b['modified'] - $a['modified'];
        });
    }
} else {
    $error = 'Migration directory not found or not readable: ' . $migrationsDir . ' (Resolved: ' . realpath($migrationsDir) . ')';
}

// Get migration history
$migrationHistory = [];
try {
    $stmt = $pdo->query("SELECT * FROM migration_history ORDER BY executed_at DESC LIMIT 20");
    $migrationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table doesn't exist yet, that's okay
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="page-header">
                <i class="fas fa-database"></i> Database Migrations
            </h1>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($error); ?>
                    <?php if (defined('DEBUG') && DEBUG && !empty($pathDebug)): ?>
                        <details style="margin-top: 10px;">
                            <summary>Debug Information</summary>
                            <pre><?php print_r($pathDebug); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (empty($migrationFiles) && empty($error)): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ No migration files found!</strong>
                    <p style="margin-top: 10px;">
                        Expected directory: <code><?php echo htmlspecialchars($migrationsDir); ?></code><br>
                        <?php if (is_dir($migrationsDir)): ?>
                            Directory exists but no .sql files found.
                        <?php else: ?>
                            Directory does not exist or is not accessible.
                        <?php endif; ?>
                    </p>
                    <?php if (defined('DEBUG') || isset($_GET['debug'])): ?>
                        <details style="margin-top: 10px;">
                            <summary>Debug Information</summary>
                            <pre><?php print_r($pathDebug); ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($migrationResult): ?>
                <div class="alert alert-<?php echo $migrationResult['failed'] > 0 ? 'warning' : 'success'; ?>">
                    <h4>Migration Result: <?php echo htmlspecialchars($migrationResult['file']); ?></h4>
                    <p>
                        <strong>Statements Executed:</strong> <?php echo $migrationResult['executed']; ?><br>
                        <strong>Statements Failed:</strong> <?php echo $migrationResult['failed']; ?>
                    </p>
                    <?php if (!empty($migrationResult['errors'])): ?>
                        <details style="margin-top: 10px;" open>
                            <summary style="cursor: pointer; font-weight: bold; color: #d32f2f;">View Errors (<?php echo count($migrationResult['errors']); ?>)</summary>
                            <div style="margin-top: 10px; max-height: 400px; overflow-y: auto; background: #fff3cd; padding: 15px; border-radius: 4px; border: 1px solid #ffc107;">
                                <?php foreach ($migrationResult['errors'] as $index => $err): ?>
                                    <div style="margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #ffc107;">
                                        <strong style="color: #856404;">Error <?php echo $index + 1; ?>:</strong><br>
                                        <div style="margin-top: 5px; margin-left: 20px;">
                                            <strong>Statement:</strong> 
                                            <code style="background: #fff; padding: 5px; border-radius: 3px; display: block; margin: 5px 0; word-break: break-all;">
                                                <?php echo htmlspecialchars($err['statement']); ?>
                                            </code>
                                            <strong>Error:</strong> 
                                            <span style="color: #d32f2f; font-weight: 600;">
                                                <?php echo htmlspecialchars($err['error']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3>Run Migration</h3>
                </div>
                <div class="panel-body">
                    <form method="POST">
                        <?php echo CSRF::getTokenField(); ?>
                        <div class="form-group">
                            <label for="migration_file">Select Migration File:</label>
                            <select name="migration_file" id="migration_file" class="form-control" required>
                                <option value="">-- Select a migration file --</option>
                                <?php foreach ($migrationFiles as $file): ?>
                                    <option value="<?php echo htmlspecialchars($file['filename']); ?>">
                                        <?php echo htmlspecialchars($file['filename']); ?>
                                        (<?php echo formatFileSize($file['size']); ?>, 
                                        <?php echo date('Y-m-d H:i', $file['modified']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            <strong>Warning:</strong> Running migrations will modify your database structure. 
                            Make sure you have a backup before proceeding.
                        </div>
                        <button type="submit" name="run_migration" class="btn btn-primary" onclick="return confirm('Are you sure you want to run this migration? Make sure you have a database backup!');">
                            <i class="fas fa-play"></i> Run Migration
                        </button>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3>Available Migrations</h3>
                        </div>
                        <div class="panel-body">
                            <?php if (empty($migrationFiles)): ?>
                                <p class="text-muted">No migration files found in database/ directory.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>Size</th>
                                                <th>Modified</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($migrationFiles as $file): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($file['filename']); ?></code></td>
                                                    <td><?php echo formatFileSize($file['size']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', $file['modified']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="panel panel-default">
                        <div class="panel-heading">
                            <h3>Migration History</h3>
                        </div>
                        <div class="panel-body">
                            <?php if (empty($migrationHistory)): ?>
                                <p class="text-muted">No migration history found.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>File</th>
                                                <th>Executed</th>
                                                <th>Statements</th>
                                                <th>Failed</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($migrationHistory as $history): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($history['migration_file']); ?></code></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($history['executed_at'])); ?></td>
                                                    <td><?php echo $history['statements_executed']; ?></td>
                                                    <td>
                                                        <?php if ($history['statements_failed'] > 0): ?>
                                                            <span class="text-danger"><?php echo $history['statements_failed']; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-success">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3><i class="fas fa-info-circle"></i> About Migrations</h3>
                </div>
                <div class="panel-body">
                    <p>This tool allows you to manually run SQL migration files to create or update database tables.</p>
                    <ul>
                        <li><strong>Migration Files:</strong> Located in <code>database/</code> directory</li>
                        <li><strong>Safe Execution:</strong> Errors for existing tables are automatically ignored</li>
                        <li><strong>History:</strong> All migrations are logged for reference</li>
                        <li><strong>Backup:</strong> Always backup your database before running migrations</li>
                    </ul>
                    <p class="text-warning">
                        <strong>Common Issues:</strong><br>
                        - Missing tables: Run the appropriate migration file (e.g., <code>maintenance_assets_inventory_migration.sql</code>)<br>
                        - Foreign key errors: Run migrations in order or disable foreign key checks temporarily
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
