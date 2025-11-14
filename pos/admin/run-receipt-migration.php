<?php
/**
 * Run Receipt Numbers Migration
 * Access via: http://localhost:8080/abbis3.2/pos/admin/run-receipt-migration.php
 */
$rootPath = dirname(__DIR__, 2);
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/auth.php';
require_once $rootPath . '/includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('pos.access');

$pdo = getDBConnection();

$message = '';
$error = '';
$columnsCreated = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_migration'])) {
    try {
        $pdo->beginTransaction();
        
        $executed = 0;
        $failed = 0;
        
        // Check and add receipt_number column
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'receipt_number'");
            if ($checkStmt->rowCount() === 0) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE pos_sales ADD COLUMN `receipt_number` VARCHAR(50) DEFAULT NULL");
                $executed++;
                
                // Add unique index
                try {
                    $pdo->exec("ALTER TABLE pos_sales ADD UNIQUE KEY `sales_receipt_number_unique` (`receipt_number`)");
                    $executed++;
                } catch (PDOException $e) {
                    // Index might already exist or fail, that's okay
                }
            } else {
                $executed++; // Column already exists
            }
        } catch (PDOException $e) {
            $failed++;
            $error .= "receipt_number: " . $e->getMessage() . "\n";
        }
        
        // Check and add paper_receipt_number column
        try {
            $checkStmt = $pdo->query("SHOW COLUMNS FROM pos_sales LIKE 'paper_receipt_number'");
            if ($checkStmt->rowCount() === 0) {
                // Column doesn't exist, add it
                $pdo->exec("ALTER TABLE pos_sales ADD COLUMN `paper_receipt_number` VARCHAR(50) DEFAULT NULL");
                $executed++;
                
                // Add index
                try {
                    $pdo->exec("ALTER TABLE pos_sales ADD KEY `sales_paper_receipt_idx` (`paper_receipt_number`)");
                    $executed++;
                } catch (PDOException $e) {
                    // Index might already exist or fail, that's okay
                }
            } else {
                $executed++; // Column already exists
            }
        } catch (PDOException $e) {
            $failed++;
            $error .= "paper_receipt_number: " . $e->getMessage() . "\n";
        }
        
        $pdo->commit();
        
        // Verify columns
        $stmt = $pdo->query("SHOW COLUMNS FROM pos_sales WHERE Field IN ('receipt_number', 'paper_receipt_number')");
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnsCreated = count($cols) >= 1;
        
        if ($columnsCreated && $failed === 0) {
            $message = "Migration completed successfully! Executed: $executed operations. Both columns created.";
        } elseif ($columnsCreated) {
            $message = "Migration partially completed. Executed: $executed, Failed: $failed. Some columns may already exist.";
        } else {
            $message = "Migration executed but columns not found. Executed: $executed, Failed: $failed";
        }
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Check current status
$stmt = $pdo->query("SHOW COLUMNS FROM pos_sales WHERE Field IN ('receipt_number', 'paper_receipt_number')");
$existingCols = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hasReceiptNumber = false;
$hasPaperReceiptNumber = false;

foreach ($existingCols as $col) {
    if ($col['Field'] === 'receipt_number') $hasReceiptNumber = true;
    if ($col['Field'] === 'paper_receipt_number') $hasPaperReceiptNumber = true;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Run Receipt Numbers Migration</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .status { padding: 12px; border-radius: 4px; margin: 16px 0; }
        .status.success { background: #d1fae5; border: 1px solid #10b981; color: #065f46; }
        .status.error { background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; }
        .status.info { background: #dbeafe; border: 1px solid #3b82f6; color: #1e40af; }
        .btn { padding: 10px 20px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #135e96; }
        .column-status { margin: 8px 0; padding: 8px; background: #f3f4f6; border-radius: 4px; }
        .column-status.exists { background: #d1fae5; }
        .column-status.missing { background: #fee2e2; }
    </style>
</head>
<body>
    <h1>Receipt Numbers Migration</h1>
    
    <div class="status info">
        <strong>Current Status:</strong>
        <div class="column-status <?php echo $hasReceiptNumber ? 'exists' : 'missing'; ?>">
            receipt_number: <?php echo $hasReceiptNumber ? '✓ EXISTS' : '✗ MISSING'; ?>
        </div>
        <div class="column-status <?php echo $hasPaperReceiptNumber ? 'exists' : 'missing'; ?>">
            paper_receipt_number: <?php echo $hasPaperReceiptNumber ? '✓ EXISTS' : '✗ MISSING'; ?>
        </div>
    </div>
    
    <?php if ($message): ?>
        <div class="status success"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="status error"><?php echo nl2br(htmlspecialchars($error)); ?></div>
    <?php endif; ?>
    
    <?php if (!$hasReceiptNumber || !$hasPaperReceiptNumber): ?>
        <form method="POST">
            <p>Click the button below to run the migration and add the receipt number columns.</p>
            <button type="submit" name="run_migration" class="btn">Run Migration</button>
        </form>
    <?php else: ?>
        <div class="status success">
            <strong>✓ All columns exist!</strong> The migration has already been run.
        </div>
    <?php endif; ?>
    
    <p style="margin-top: 30px;">
        <a href="index.php?action=admin">← Back to POS Admin</a>
    </p>
</body>
</html>

