<?php
/**
 * Loan Management
 */
$page_title = 'Loan Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/AccountingAutoTracker.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Handle loan actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token');
        redirect('loans.php');
    }
    
    $action = sanitizeArray($_POST['action'] ?? '');
    
    try {
        switch ($action) {
            case 'add_loan':
                $workerId = intval($_POST['worker_id'] ?? 0);
                $loanAmount = floatval($_POST['loan_amount'] ?? 0);
                $purpose = sanitizeArray($_POST['purpose'] ?? '');
                
                if ($workerId <= 0 || $loanAmount <= 0) {
                    throw new Exception('Worker and valid loan amount are required');
                }
                
                // Get worker name for backward compatibility
                $workerStmt = $pdo->prepare("SELECT worker_name FROM workers WHERE id = ?");
                $workerStmt->execute([$workerId]);
                $worker = $workerStmt->fetch();
                $workerName = $worker ? $worker['worker_name'] : '';
                
                $stmt = $pdo->prepare("
                    INSERT INTO loans (worker_id, worker_name, loan_amount, outstanding_balance, purpose, issue_date, created_by) 
                    VALUES (?, ?, ?, ?, ?, CURDATE(), ?)
                ");
                $stmt->execute([$workerId, $workerName, $loanAmount, $loanAmount, $purpose, $_SESSION['user_id']]);
                $loanId = $pdo->lastInsertId();

                // Automatically track loan disbursement in accounting - runs for EVERY new loan
                try {
                    // Ensure accounting tables exist
                    try {
                        $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
                    } catch (PDOException $e) {
                        // Initialize if needed
                        $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
                        if (file_exists($migrationFile)) {
                            $sql = file_get_contents($migrationFile);
                            if ($sql) {
                                foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                                    $stmt = trim($stmt);
                                    if ($stmt) {
                                        try {
                                            $pdo->exec($stmt);
                                        } catch (PDOException $e2) {}
                                    }
                                }
                            }
                        }
                    }
                    
                    $accountingTracker = new AccountingAutoTracker($pdo);
                    $result = $accountingTracker->trackLoanDisbursement($loanId, [
                        'worker_name' => $workerName,
                        'loan_amount' => $loanAmount,
                        'issue_date' => date('Y-m-d'),
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        error_log("Accounting: Auto-tracked loan disbursement ID {$loanId}");
                    }
                } catch (Exception $e) {
                    error_log("Accounting auto-tracking error for loan ID {$loanId}: " . $e->getMessage());
                }
                
                flash('success', "Loan added successfully for $workerName");
                break;
                
            case 'record_repayment':
                $loanId = intval($_POST['loan_id'] ?? 0);
                $repaymentAmount = floatval($_POST['repayment_amount'] ?? 0);
                $notes = sanitizeArray($_POST['notes'] ?? '');
                
                if ($loanId <= 0 || $repaymentAmount <= 0) {
                    throw new Exception('Valid loan and repayment amount are required');
                }
                
                // Get current loan details
                $loanStmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
                $loanStmt->execute([$loanId]);
                $loan = $loanStmt->fetch();
                
                if (!$loan) {
                    throw new Exception('Loan not found');
                }
                
                if ($repaymentAmount > $loan['outstanding_balance']) {
                    throw new Exception('Repayment amount cannot exceed outstanding balance');
                }
                
                // Start transaction
                $pdo->beginTransaction();
                
                // Update loan
                $updateStmt = $pdo->prepare("
                    UPDATE loans 
                    SET amount_repaid = amount_repaid + ?, 
                        outstanding_balance = outstanding_balance - ?,
                        status = CASE WHEN (outstanding_balance - ?) <= 0 THEN 'repaid' ELSE status END,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$repaymentAmount, $repaymentAmount, $repaymentAmount, $loanId]);
                
                // Record repayment
                $repaymentStmt = $pdo->prepare("
                    INSERT INTO loan_repayments (loan_id, repayment_amount, repayment_date, notes, created_by) 
                    VALUES (?, ?, CURDATE(), ?, ?)
                ");
                $repaymentStmt->execute([$loanId, $repaymentAmount, $notes, $_SESSION['user_id']]);
                $repaymentId = $pdo->lastInsertId();

                $pdo->commit();

                // Automatically track loan repayment in accounting - runs for EVERY repayment
                try {
                    // Ensure accounting tables exist
                    try {
                        $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
                    } catch (PDOException $e) {
                        // Initialize if needed
                        $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
                        if (file_exists($migrationFile)) {
                            $sql = file_get_contents($migrationFile);
                            if ($sql) {
                                foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                                    $stmt = trim($stmt);
                                    if ($stmt) {
                                        try {
                                            $pdo->exec($stmt);
                                        } catch (PDOException $e2) {}
                                    }
                                }
                            }
                        }
                    }
                    
                    $accountingTracker = new AccountingAutoTracker($pdo);
                    $result = $accountingTracker->trackLoanRepayment($repaymentId, [
                        'worker_name' => $loan['worker_name'] ?? '',
                        'repayment_amount' => $repaymentAmount,
                        'repayment_date' => date('Y-m-d'),
                        'created_by' => $_SESSION['user_id']
                    ]);
                    
                    if ($result) {
                        error_log("Accounting: Auto-tracked loan repayment ID {$repaymentId}");
                    }
                } catch (Exception $e) {
                    error_log("Accounting auto-tracking error for repayment ID {$repaymentId}: " . $e->getMessage());
                }
                
                flash('success', "Repayment of " . formatCurrency($repaymentAmount) . " recorded successfully");
                break;
                
            case 'update_status':
                $loanId = intval($_POST['loan_id'] ?? 0);
                $status = sanitizeArray($_POST['status'] ?? '');
                
                if ($loanId <= 0 || !in_array($status, ['active', 'repaid', 'written_off'])) {
                    throw new Exception('Invalid loan or status');
                }
                
                $stmt = $pdo->prepare("UPDATE loans SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$status, $loanId]);
                
                flash('success', "Loan status updated successfully");
                break;
        }
        
        redirect('loans.php');
        
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect('loans.php');
    }
}

// Get all loans (join with workers if worker_id exists)
$loans = $pdo->query("
    SELECT l.*, 
           w.worker_name as worker_name_from_table,
           (SELECT SUM(repayment_amount) FROM loan_repayments WHERE loan_id = l.id) as total_repayments
    FROM loans l 
    LEFT JOIN workers w ON l.worker_id = w.id
    ORDER BY l.status, l.outstanding_balance DESC
")->fetchAll();

// Get active workers for dropdown (use workers table with IDs)
$workers = $pdo->query("SELECT id, worker_name, employee_code FROM workers WHERE status = 'active' ORDER BY worker_name")->fetchAll();

// Calculate loan statistics
$totalActiveLoans = 0;
$totalLoanAmount = 0;
$totalRepaid = 0;
$totalOutstanding = 0;

foreach ($loans as $loan) {
    if ($loan['status'] === 'active') {
        $totalActiveLoans++;
    }
    $totalLoanAmount += $loan['loan_amount'];
    $totalRepaid += $loan['amount_repaid'];
    $totalOutstanding += $loan['outstanding_balance'];
}

require_once '../includes/header.php';
?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Loan Management</h1>
                    <p>Track worker loans and repayments</p>
                </div>
            </div>

            <!-- Loan Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏦</div>
                    <div class="stat-info">
                        <h3><?php echo number_format($totalActiveLoans); ?></h3>
                        <p>Active Loans</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalLoanAmount); ?></h3>
                        <p>Total Loan Amount</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalRepaid); ?></h3>
                        <p>Total Repaid</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalOutstanding); ?></h3>
                        <p>Total Outstanding</p>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Add New Loan -->
                <div class="dashboard-card">
                    <h2>Add New Loan</h2>
                    <form method="POST" class="form-grid">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="add_loan">
                        
                        <div class="form-group">
                            <label for="worker_id" class="form-label">Worker</label>
                            <select id="worker_id" name="worker_id" class="form-control" required>
                                <option value="">Select Worker</option>
                                <?php foreach ($workers as $worker): ?>
                                    <option value="<?php echo $worker['id']; ?>">
                                        <?php echo e($worker['employee_code'] ?? 'N/A'); ?> - <?php echo e($worker['worker_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="loan_amount" class="form-label">Loan Amount (GHS)</label>
                            <input type="number" id="loan_amount" name="loan_amount" class="form-control" 
                                   step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="purpose" class="form-label">Purpose</label>
                            <input type="text" id="purpose" name="purpose" class="form-control" 
                                   placeholder="Loan purpose">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-primary">Add Loan</button>
                        </div>
                    </form>
                </div>

                <!-- Record Repayment -->
                <div class="dashboard-card">
                    <h2>Record Repayment</h2>
                    <form method="POST" class="form-grid">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="record_repayment">
                        
                        <div class="form-group">
                            <label for="loan_id" class="form-label">Select Loan</label>
                            <select id="loan_id" name="loan_id" class="form-control" required>
                                <option value="">Select Loan</option>
                                <?php foreach ($loans as $loan): ?>
                                    <?php if ($loan['status'] === 'active'): ?>
                                        <option value="<?php echo $loan['id']; ?>">
                                            <?php echo e($loan['worker_name']); ?> - 
                                            <?php echo formatCurrency($loan['outstanding_balance']); ?> outstanding
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="repayment_amount" class="form-label">Repayment Amount (GHS)</label>
                            <input type="number" id="repayment_amount" name="repayment_amount" 
                                   class="form-control" step="0.01" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="notes" class="form-label">Notes</label>
                            <input type="text" id="notes" name="notes" class="form-control" 
                                   placeholder="Repayment notes">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="submit" class="btn btn-success">Record Repayment</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Loans List -->
            <div class="dashboard-card">
                <h2>All Loans</h2>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Worker</th>
                                <th>Loan Amount</th>
                                <th>Amount Repaid</th>
                                <th>Outstanding</th>
                                <th>Issue Date</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($loans)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No loans found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($loans as $loan): ?>
                                <tr>
                                    <td><?php echo e($loan['worker_name']); ?></td>
                                    <td><?php echo formatCurrency($loan['loan_amount']); ?></td>
                                    <td><?php echo formatCurrency($loan['amount_repaid']); ?></td>
                                    <td class="<?php echo $loan['outstanding_balance'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <strong><?php echo formatCurrency($loan['outstanding_balance']); ?></strong>
                                    </td>
                                    <td><?php echo formatDate($loan['issue_date']); ?></td>
                                    <td><?php echo e($loan['purpose']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo e($loan['status']); ?>">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($loan['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <?php echo CSRF::getTokenField(); ?>
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                <input type="hidden" name="status" value="repaid">
                                                <button type="submit" class="btn btn-sm btn-success" 
                                                        onclick="return confirm('Mark this loan as repaid?')">Mark Repaid</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

<?php require_once '../includes/footer.php'; ?>
