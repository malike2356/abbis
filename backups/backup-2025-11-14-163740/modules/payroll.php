<?php
/**
 * Payroll Management - Complete Rebuild
 * Full-featured payroll management system
 */
$page_title = 'Payroll Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Handle payroll entry deletion
if (isset($_POST['delete_payroll']) && isset($_POST['payroll_id'])) {
    $payrollId = (int)$_POST['payroll_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM payroll_entries WHERE id = ?");
        $stmt->execute([$payrollId]);
        $_SESSION['success'] = 'Payroll entry deleted successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting payroll entry: ' . $e->getMessage();
    }
    header('Location: payroll.php');
    exit;
}

// Handle payment status update
if (isset($_POST['update_payment_status']) && isset($_POST['payroll_id'])) {
    $payrollId = (int)$_POST['payroll_id'];
    $paidToday = isset($_POST['paid_today']) ? 1 : 0;
    try {
        // Get current payroll entry
        $currentStmt = $pdo->prepare("SELECT pe.*, fr.report_date FROM payroll_entries pe LEFT JOIN field_reports fr ON pe.report_id = fr.id WHERE pe.id = ?");
        $currentStmt->execute([$payrollId]);
        $currentPayroll = $currentStmt->fetch();
        
        $oldPaidStatus = $currentPayroll ? (int)$currentPayroll['paid_today'] : 0;
        
        $stmt = $pdo->prepare("UPDATE payroll_entries SET paid_today = ? WHERE id = ?");
        $stmt->execute([$paidToday, $payrollId]);
        
        // Automatically track payroll payment in accounting if status changed to paid - runs for EVERY payment
        if ($paidToday == 1 && $oldPaidStatus == 0 && $currentPayroll) {
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
                
                require_once '../includes/AccountingAutoTracker.php';
                $accountingTracker = new AccountingAutoTracker($pdo);
                $result = $accountingTracker->trackPayrollPayment($payrollId, [
                    'worker_name' => $currentPayroll['worker_name'] ?? '',
                    'amount' => floatval($currentPayroll['amount'] ?? 0),
                    'payment_date' => $currentPayroll['report_date'] ?? date('Y-m-d'),
                    'created_by' => $_SESSION['user_id'] ?? null
                ]);
                
                if ($result) {
                    error_log("Accounting: Auto-tracked payroll payment ID {$payrollId}");
                }
            } catch (Exception $e) {
                error_log("Accounting auto-tracking error for payroll payment ID {$payrollId}: " . $e->getMessage());
            }
        }
        
        $_SESSION['success'] = 'Payment status updated successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error updating payment status: ' . $e->getMessage();
    }
    header('Location: payroll.php');
    exit;
}

// Get filters
$filterDateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$filterDateTo = $_GET['date_to'] ?? date('Y-m-t'); // Last day of current month
$filterWorker = $_GET['worker'] ?? '';
$filterRole = $_GET['role'] ?? '';
$filterPaymentStatus = $_GET['payment_status'] ?? '';
$filterReportId = $_GET['report_id'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["1=1"];
$params = [];

if ($filterDateFrom) {
    $where[] = "DATE(fr.report_date) >= ?";
    $params[] = $filterDateFrom;
}
if ($filterDateTo) {
    $where[] = "DATE(fr.report_date) <= ?";
    $params[] = $filterDateTo;
}
if ($filterWorker) {
    $where[] = "pe.worker_name LIKE ?";
    $params[] = "%$filterWorker%";
}
if ($filterRole) {
    $where[] = "pe.role = ?";
    $params[] = $filterRole;
}
if ($filterPaymentStatus !== '') {
    $where[] = "pe.paid_today = ?";
    $params[] = $filterPaymentStatus;
}
if ($filterReportId) {
    $where[] = "fr.report_id = ?";
    $params[] = $filterReportId;
}
if ($search) {
    $where[] = "(pe.worker_name LIKE ? OR pe.role LIKE ? OR fr.report_id LIKE ? OR fr.site_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = implode(' AND ', $where);

// Get total count
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM payroll_entries pe
    LEFT JOIN field_reports fr ON pe.report_id = fr.id
    WHERE $whereClause
");
$countStmt->execute($params);
$totalEntries = $countStmt->fetchColumn();
$totalPages = ceil($totalEntries / $perPage);

// Get payroll entries
$entriesStmt = $pdo->prepare("
    SELECT 
        pe.*,
        fr.report_id as field_report_id,
        fr.report_date,
        fr.site_name,
        fr.rig_id,
        r.rig_code
    FROM payroll_entries pe
    LEFT JOIN field_reports fr ON pe.report_id = fr.id
    LEFT JOIN rigs r ON fr.rig_id = r.id
    WHERE $whereClause
    ORDER BY fr.report_date DESC, pe.created_at DESC
    LIMIT ? OFFSET ?
");
$allParams = array_merge($params, [$perPage, $offset]);
$entriesStmt->execute($allParams);
$entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(pe.amount), 0) as total_payroll,
        COALESCE(SUM(CASE WHEN pe.paid_today = 1 THEN pe.amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN pe.paid_today = 0 THEN pe.amount ELSE 0 END), 0) as total_unpaid,
        COUNT(*) as total_entries,
        COUNT(DISTINCT pe.worker_name) as unique_workers,
        COUNT(DISTINCT pe.role) as unique_roles
    FROM payroll_entries pe
    LEFT JOIN field_reports fr ON pe.report_id = fr.id
    WHERE $whereClause
");
$statsStmt->execute($params);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get today's payroll
$todayStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_payroll,
           COUNT(*) as count
    FROM payroll_entries 
    WHERE DATE(created_at) = CURDATE()
");
$todayStmt->execute();
$todayPayroll = $todayStmt->fetch();

// Get this month's payroll
$monthStmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as total_payroll,
           COUNT(*) as count
    FROM payroll_entries pe
    LEFT JOIN field_reports fr ON pe.report_id = fr.id
    WHERE YEAR(fr.report_date) = YEAR(CURDATE())
      AND MONTH(fr.report_date) = MONTH(CURDATE())
");
$monthStmt->execute();
$monthPayroll = $monthStmt->fetch();

// Get active workers count
$activeWorkersStmt = $pdo->query("SELECT COUNT(*) as count FROM workers WHERE status = 'active'");
$activeWorkers = $activeWorkersStmt->fetch();

// Get unique workers for filter
$workersStmt = $pdo->query("SELECT DISTINCT worker_name FROM payroll_entries ORDER BY worker_name");
$workersList = $workersStmt->fetchAll(PDO::FETCH_COLUMN);

// Get unique roles for filter
$rolesStmt = $pdo->query("SELECT DISTINCT role FROM payroll_entries ORDER BY role");
$rolesList = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<style>
.payroll-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 32px;
}

@media (max-width: 1024px) {
    .payroll-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .payroll-stats-grid {
        grid-template-columns: 1fr;
    }
}

.stat-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 10px;
    padding: 22px;
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
    min-height: 140px;
    display: flex;
    flex-direction: column;
    word-wrap: break-word;
    overflow-wrap: break-word;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), #3b82f6);
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: #cbd5e1;
}

.stat-card-icon {
    font-size: 28px;
    margin-bottom: 12px;
    opacity: 0.9;
    display: inline-block;
}

.stat-card-value {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
    word-break: break-word;
    line-height: 1.2;
}

.stat-card-label {
    font-size: 12px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
    line-height: 1.4;
    word-wrap: break-word;
}

.stat-card-detail {
    font-size: 11px;
    color: #94a3b8;
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
    line-height: 1.5;
    word-wrap: break-word;
}

.filters-section {
    background: white;
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.filters-section h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #f1f5f9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 18px;
    margin-bottom: 20px;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-group input,
.filter-group select {
    padding: 10px 14px;
    border: 1.5px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    background: #ffffff;
    color: #1e293b;
    transition: all 0.2s;
    font-family: inherit;
}

.filter-group input:focus,
.filter-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid #f1f5f9;
}

.payroll-table-wrapper {
    background: white;
    border-radius: 10px;
    padding: 0;
    border: 1px solid #e2e8f0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    overflow: hidden;
}

.payroll-table-wrapper .table-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
}

.payroll-table-wrapper .table-header h3 {
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
    margin: 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 12px;
}

.payroll-table-wrapper .table-content {
    overflow-x: auto;
    padding: 0;
}

.payroll-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.payroll-table thead {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
}

.payroll-table th {
    padding: 14px 16px;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    white-space: nowrap;
}

.payroll-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
    color: #334155;
    vertical-align: middle;
}

.payroll-table tbody tr {
    transition: background 0.15s;
}

.payroll-table tbody tr:hover {
    background: #f8fafc;
}

.payroll-table tbody tr:last-child td {
    border-bottom: none;
}

.payment-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.payment-status.paid {
    background: #dcfce7;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.payment-status.unpaid {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

.action-dropdown {
    position: relative;
    display: inline-block;
}

.action-dropdown-toggle {
    padding: 6px 14px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    color: #475569;
    font-weight: 500;
    transition: all 0.2s;
}

.action-dropdown-toggle:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

.action-dropdown-menu {
    display: none;
    position: absolute;
    right: 0;
    top: 100%;
    margin-top: 6px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    z-index: 1000;
    min-width: 180px;
    overflow: hidden;
}

.action-dropdown-menu.show {
    display: block;
}

.action-menu-item {
    display: block;
    padding: 10px 16px;
    color: #334155;
    text-decoration: none;
    font-size: 13px;
    transition: background 0.15s;
    border: none;
    background: none;
    width: 100%;
    text-align: left;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
}

.action-menu-item:last-child {
    border-bottom: none;
}

.action-menu-item:hover {
    background: #f8fafc;
}

.action-menu-item.danger {
    color: #dc2626;
}

.action-menu-item.danger:hover {
    background: #fee2e2;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    margin: 24px 0;
    flex-wrap: wrap;
    padding: 0 24px;
}

.pagination a,
.pagination span {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    text-decoration: none;
    color: #475569;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    min-width: 36px;
    text-align: center;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
    transform: translateY(-1px);
}

.pagination .current {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .disabled {
    opacity: 0.4;
    cursor: not-allowed;
    pointer-events: none;
}

.empty-state {
    text-align: center;
    padding: 80px 20px;
    color: #94a3b8;
}

.empty-state-icon {
    font-size: 56px;
    margin-bottom: 20px;
    opacity: 0.6;
}

.empty-state-title {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
    color: #475569;
}

.empty-state-text {
    font-size: 14px;
    margin-bottom: 28px;
    color: #64748b;
}

@media (max-width: 768px) {
    .filters-grid {
        grid-template-columns: 1fr;
    }
    
    .payroll-table-wrapper {
        padding: 16px;
    }
    
    .payroll-table {
        font-size: 12px;
    }
    
    .payroll-table th,
    .payroll-table td {
        padding: 8px;
    }
}
</style>

<div class="page-header" style="margin-bottom: 32px;">
    <div>
        <h1 style="font-size: 28px; font-weight: 700; color: #1e293b; margin: 0 0 8px 0; letter-spacing: -0.5px;">Payroll Management</h1>
        <p style="font-size: 14px; color: #64748b; margin: 0;">Manage worker payments, track payroll records, and monitor payment status</p>
    </div>
    <div>
        <a href="<?php echo module_url('field-reports.php'); ?>" class="btn btn-primary" style="padding: 10px 20px; font-weight: 500; font-size: 14px;">Create Report</a>
    </div>
</div>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="margin-bottom: 24px;">
        <?php echo e($_SESSION['success']); unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="margin-bottom: 24px;">
        <?php echo e($_SESSION['error']); unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Statistics Dashboard -->
<div class="payroll-stats-grid">
    <div class="stat-card">
        <div class="stat-card-icon">💰</div>
        <div class="stat-card-value"><?php echo formatCurrency($stats['total_payroll'] ?? 0); ?></div>
        <div class="stat-card-label">Total Payroll (Filtered)</div>
        <div class="stat-card-detail"><?php echo number_format($stats['total_entries'] ?? 0); ?> entries</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon">✅</div>
        <div class="stat-card-value" style="color: #166534;"><?php echo formatCurrency($stats['total_paid'] ?? 0); ?></div>
        <div class="stat-card-label">Total Paid</div>
        <div class="stat-card-detail">Fully paid entries</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon">⏳</div>
        <div class="stat-card-value" style="color: #991b1b;"><?php echo formatCurrency($stats['total_unpaid'] ?? 0); ?></div>
        <div class="stat-card-label">Total Unpaid</div>
        <div class="stat-card-detail">Pending payments</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon">👷</div>
        <div class="stat-card-value"><?php echo number_format($stats['unique_workers'] ?? 0); ?></div>
        <div class="stat-card-label">Unique Workers</div>
        <div class="stat-card-detail"><?php echo number_format($stats['unique_roles'] ?? 0); ?> different roles</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon">📅</div>
        <div class="stat-card-value" style="color: #1e40af;"><?php echo formatCurrency($todayPayroll['total_payroll'] ?? 0); ?></div>
        <div class="stat-card-label">Today's Payroll</div>
        <div class="stat-card-detail"><?php echo number_format($todayPayroll['count'] ?? 0); ?> entries today</div>
    </div>
    
    <div class="stat-card">
        <div class="stat-card-icon">📊</div>
        <div class="stat-card-value" style="color: #6b21a8;"><?php echo formatCurrency($monthPayroll['total_payroll'] ?? 0); ?></div>
        <div class="stat-card-label">This Month's Payroll</div>
        <div class="stat-card-detail"><?php echo number_format($monthPayroll['count'] ?? 0); ?> entries</div>
    </div>
</div>

<!-- Filters Section -->
<div class="filters-section">
    <h3>Filters & Search</h3>
    <form method="GET" action="payroll.php" id="filterForm">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Search</label>
                <input type="text" name="search" placeholder="Worker, Role, Report ID, Site..." value="<?php echo e($search); ?>">
            </div>
            
            <div class="filter-group">
                <label>Date From</label>
                <input type="date" name="date_from" value="<?php echo e($filterDateFrom); ?>">
            </div>
            
            <div class="filter-group">
                <label>Date To</label>
                <input type="date" name="date_to" value="<?php echo e($filterDateTo); ?>">
            </div>
            
            <div class="filter-group">
                <label>Worker</label>
                <select name="worker">
                    <option value="">All Workers</option>
                    <?php foreach ($workersList as $worker): ?>
                        <option value="<?php echo e($worker); ?>" <?php echo $filterWorker === $worker ? 'selected' : ''; ?>>
                            <?php echo e($worker); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Role</label>
                <select name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($rolesList as $role): ?>
                        <option value="<?php echo e($role); ?>" <?php echo $filterRole === $role ? 'selected' : ''; ?>>
                            <?php echo e($role); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Payment Status</label>
                <select name="payment_status">
                    <option value="">All Statuses</option>
                    <option value="1" <?php echo $filterPaymentStatus === '1' ? 'selected' : ''; ?>>Paid</option>
                    <option value="0" <?php echo $filterPaymentStatus === '0' ? 'selected' : ''; ?>>Unpaid</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Report ID</label>
                <input type="text" name="report_id" placeholder="Report ID..." value="<?php echo e($filterReportId); ?>">
            </div>
        </div>
        
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary" style="padding: 10px 20px; font-weight: 500; font-size: 13px;">Apply Filters</button>
            <a href="payroll.php" class="btn btn-outline" style="padding: 10px 20px; font-weight: 500; font-size: 13px;">Clear Filters</a>
            <button type="button" class="btn btn-outline" onclick="showPayslipModal()" style="padding: 10px 20px; font-weight: 500; font-size: 13px;">Generate Payslip</button>
            <button type="button" class="btn btn-primary" onclick="showBulkPayslipModal()" style="padding: 10px 20px; font-weight: 500; font-size: 13px;">📧 Bulk Generate & Email</button>
            <button type="button" class="btn btn-outline" onclick="exportPayroll()" style="padding: 10px 20px; font-weight: 500; font-size: 13px;">Export CSV</button>
        </div>
    </form>
</div>

<!-- Payroll Entries Table -->
<div class="payroll-table-wrapper">
    <div class="table-header">
        <h3>Payroll Entries</h3>
    </div>
    <div class="table-content">
    <?php if (empty($entries)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">No Payroll Entries Found</div>
            <div class="empty-state-text">No payroll entries match your current filters.</div>
            <a href="../modules/field-reports.php" class="btn btn-primary">Create Field Report</a>
        </div>
    <?php else: ?>
        <table class="payroll-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Worker</th>
                    <th>Role</th>
                    <th>Wage Type</th>
                    <th>Units</th>
                    <th>Rate</th>
                    <th>Benefits</th>
                    <th>Loan</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Report</th>
                    <th>Site</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td><?php echo $entry['report_date'] ? date('M d, Y', strtotime($entry['report_date'])) : 'N/A'; ?></td>
                        <td><strong><?php echo e($entry['worker_name']); ?></strong></td>
                        <td><?php echo e($entry['role']); ?></td>
                                                 <td>
                             <span style="font-size: 11px; padding: 4px 10px; background: #f1f5f9; border-radius: 4px; color: #475569; font-weight: 500;">
                                 <?php echo ucfirst(str_replace('_', ' ', $entry['wage_type'])); ?>
                             </span>
                         </td>
                        <td><?php echo number_format($entry['units'], 2); ?></td>
                        <td><?php echo formatCurrency($entry['pay_per_unit']); ?></td>
                        <td><?php echo formatCurrency($entry['benefits'] ?? 0); ?></td>
                        <td><?php echo formatCurrency($entry['loan_reclaim'] ?? 0); ?></td>
                                                 <td><strong style="color: #1e293b; font-size: 14px;"><?php echo formatCurrency($entry['amount']); ?></strong></td>
                        <td>
                            <?php if ($entry['paid_today']): ?>
                                <span class="payment-status paid">✅ Paid</span>
                            <?php else: ?>
                                <span class="payment-status unpaid">⏳ Unpaid</span>
                            <?php endif; ?>
                        </td>
                                                 <td>
                             <?php if ($entry['field_report_id']): ?>
                                 <a href="../modules/field-reports-list.php?search=<?php echo urlencode($entry['field_report_id']); ?>" 
                                    class="btn btn-sm btn-outline" title="View Report" style="padding: 4px 10px; font-size: 12px; border-color: #cbd5e1; color: #475569;">
                                     <?php echo e($entry['field_report_id']); ?>
                                 </a>
                             <?php else: ?>
                                 <span style="color: #94a3b8; font-size: 12px;">—</span>
                             <?php endif; ?>
                         </td>
                                                 <td>
                             <?php if ($entry['site_name']): ?>
                                 <span title="<?php echo e($entry['site_name']); ?>" style="color: #475569;">
                                     <?php echo e(strlen($entry['site_name']) > 25 ? substr($entry['site_name'], 0, 25) . '...' : $entry['site_name']); ?>
                                 </span>
                             <?php else: ?>
                                 <span style="color: #94a3b8; font-size: 12px;">—</span>
                             <?php endif; ?>
                         </td>
                        <td>
                            <div class="action-dropdown">
                                <button class="action-dropdown-toggle" onclick="togglePayrollActions(<?php echo $entry['id']; ?>)">
                                    Actions <span>▼</span>
                                </button>
                                <div id="actions-<?php echo $entry['id']; ?>" class="action-dropdown-menu">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="payroll_id" value="<?php echo $entry['id']; ?>">
                                        <input type="hidden" name="paid_today" value="<?php echo $entry['paid_today'] ? 0 : 1; ?>">
                                        <button type="submit" name="update_payment_status" class="action-menu-item">
                                            <?php echo $entry['paid_today'] ? '⏳ Mark as Unpaid' : '✅ Mark as Paid'; ?>
                                        </button>
                                    </form>
                                    <?php if ($entry['notes']): ?>
                                        <button type="button" class="action-menu-item" onclick="showNotes(<?php echo $entry['id']; ?>, <?php echo htmlspecialchars(json_encode($entry['notes']), ENT_QUOTES, 'UTF-8'); ?>)">
                                            📝 View Notes
                                        </button>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this payroll entry? This action cannot be undone.');">
                                        <?php echo CSRF::getTokenField(); ?>
                                        <input type="hidden" name="payroll_id" value="<?php echo $entry['id']; ?>">
                                        <button type="submit" name="delete_payroll" class="action-menu-item danger">
                                            🗑️ Delete Entry
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">« Previous</a>
                <?php else: ?>
                    <span class="disabled">« Previous</span>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next »</a>
                <?php else: ?>
                    <span class="disabled">Next »</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div style="padding: 16px 24px; border-top: 1px solid #e2e8f0; background: #f8fafc; text-align: center; color: #64748b; font-size: 12px;">
            Showing <?php echo number_format($offset + 1); ?> to <?php echo number_format(min($offset + $perPage, $totalEntries)); ?> of <?php echo number_format($totalEntries); ?> entries
        </div>
    <?php endif; ?>
    </div>
</div>

<!-- Notes Modal -->
<div id="notesModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0; font-size: 18px; font-weight: 600; color: #1e293b;">Notes</h3>
            <button onclick="closeNotesModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">&times;</button>
        </div>
        <div id="notesContent" style="color: #334155; line-height: 1.6; white-space: pre-wrap; font-size: 14px;"></div>
        <div style="margin-top: 20px; text-align: right;">
            <button onclick="closeNotesModal()" class="btn btn-primary" style="padding: 10px 20px; font-weight: 500; font-size: 14px;">Close</button>
        </div>
    </div>
</div>

<!-- Payslip Generation Modal -->
<div id="payslipModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 28px; max-width: 600px; width: 90%; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f1f5f9;">
            <h3 style="margin: 0; font-size: 20px; font-weight: 600; color: #1e293b;">Generate Payslip</h3>
            <button onclick="closePayslipModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">&times;</button>
        </div>
        
        <form method="GET" action="../modules/payslip.php" target="_blank" id="payslipForm" style="display: flex; flex-direction: column; gap: 20px;">
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Worker</label>
                <select name="worker" id="payslip_worker" required style="padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #1e293b; transition: all 0.2s;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'">
                    <option value="">Select Worker...</option>
                    <?php foreach ($workersList as $worker): ?>
                        <option value="<?php echo e($worker); ?>"><?php echo e($worker); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Period From</label>
                    <input type="date" name="date_from" id="payslip_date_from" required style="padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #1e293b; transition: all 0.2s;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'" value="<?php echo e($filterDateFrom); ?>">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Period To</label>
                    <input type="date" name="date_to" id="payslip_date_to" required style="padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #1e293b; transition: all 0.2s;" onfocus="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(59, 130, 246, 0.1)'" onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'" value="<?php echo e($filterDateTo); ?>">
                </div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 8px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <button type="submit" class="btn btn-primary" style="flex: 1; padding: 12px 20px; font-weight: 500; font-size: 14px;">Generate Payslip</button>
                <button type="button" onclick="closePayslipModal()" class="btn btn-outline" style="padding: 12px 20px; font-weight: 500; font-size: 14px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Payslip Generation Modal -->
<div id="bulkPayslipModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 12px; padding: 28px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #f1f5f9;">
            <h3 style="margin: 0; font-size: 20px; font-weight: 600; color: #1e293b;">📧 Bulk Generate Payslips & Email</h3>
            <button onclick="closeBulkPayslipModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='none'">&times;</button>
        </div>
        
        <form id="bulkPayslipForm" style="display: flex; flex-direction: column; gap: 20px;">
            <?php echo CSRF::getTokenField(); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Period From</label>
                    <input type="date" name="date_from" id="bulk_date_from" required style="padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #1e293b;" value="<?php echo e($filterDateFrom); ?>">
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Period To</label>
                    <input type="date" name="date_to" id="bulk_date_to" required style="padding: 12px 14px; border: 1.5px solid #e2e8f0; border-radius: 6px; font-size: 14px; background: #ffffff; color: #1e293b;" value="<?php echo e($filterDateTo); ?>">
                </div>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px;">Select Workers (Select multiple)</label>
                <div style="border: 1.5px solid #e2e8f0; border-radius: 6px; padding: 12px; max-height: 250px; overflow-y: auto; background: #f8fafc;">
                    <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
                        <button type="button" onclick="selectAllWorkers()" style="padding: 6px 12px; background: #7c3aed; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer; margin-right: 8px;">Select All</button>
                        <button type="button" onclick="deselectAllWorkers()" style="padding: 6px 12px; background: #64748b; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">Deselect All</button>
                    </div>
                    <?php foreach ($workersList as $worker): ?>
                        <label style="display: flex; align-items: center; padding: 8px; cursor: pointer; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='transparent'">
                            <input type="checkbox" name="workers[]" value="<?php echo e($worker); ?>" style="margin-right: 10px; width: 18px; height: 18px; cursor: pointer;">
                            <span style="font-size: 14px; color: #1e293b;"><?php echo e($worker); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <small style="color: #64748b; font-size: 12px;">💡 Tip: Select multiple workers to generate payslips for all of them at once. Workers with email addresses will receive their payslips automatically.</small>
            </div>
            
            <div style="display: flex; flex-direction: column; gap: 12px; padding: 16px; background: #f0f9ff; border-radius: 8px; border: 1px solid #bae6fd;">
                <label style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.3px; margin-bottom: 8px;">Select Distribution Method</label>
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: white; border-radius: 6px; border: 2px solid #e2e8f0; transition: all 0.2s; cursor: pointer;" id="option_download" onclick="selectOption('download')">
                        <input type="radio" id="option_download_radio" name="distribution_method" value="download" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="option_download_radio" style="cursor: pointer; font-size: 14px; color: #1e293b; font-weight: 500; flex: 1; margin: 0;">
                            📥 Download Only - Save payslips locally and create ZIP file
                        </label>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: white; border-radius: 6px; border: 2px solid #e2e8f0; transition: all 0.2s; cursor: pointer;" id="option_email" onclick="selectOption('email')">
                        <input type="radio" id="option_email_radio" name="distribution_method" value="email" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="option_email_radio" style="cursor: pointer; font-size: 14px; color: #1e293b; font-weight: 500; flex: 1; margin: 0;">
                            📧 Email Only - Send payslips via email (only for workers with email addresses)
                        </label>
                    </div>
                    
                    <div style="display: flex; align-items: center; gap: 12px; padding: 10px; background: white; border-radius: 6px; border: 2px solid #e2e8f0; transition: all 0.2s; cursor: pointer;" id="option_both" onclick="selectOption('both')">
                        <input type="radio" id="option_both_radio" name="distribution_method" value="both" style="width: 18px; height: 18px; cursor: pointer;">
                        <label for="option_both_radio" style="cursor: pointer; font-size: 14px; color: #1e293b; font-weight: 500; flex: 1; margin: 0;">
                            📥📧 Both - Download locally AND send via email
                        </label>
                    </div>
                </div>
            </div>
            
            <div id="bulkPayslipProgress" style="display: none; padding: 20px; background: #f8fafc; border-radius: 8px; margin-top: 10px;">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <div style="width: 20px; height: 20px; border: 3px solid #e2e8f0; border-top-color: #7c3aed; border-radius: 50%; animation: spin 1s linear infinite;"></div>
                    <span style="font-size: 14px; color: #1e293b; font-weight: 500;">Processing payslips...</span>
                </div>
                <div id="bulkPayslipResults" style="max-height: 300px; overflow-y: auto; font-size: 13px;"></div>
            </div>
            
            <div style="display: flex; gap: 12px; margin-top: 8px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <button type="submit" id="bulkGenerateBtn" class="btn btn-primary" style="flex: 1; padding: 12px 20px; font-weight: 500; font-size: 14px;">🚀 Generate</button>
                <button type="button" onclick="closeBulkPayslipModal()" class="btn btn-outline" style="padding: 12px 20px; font-weight: 500; font-size: 14px;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<style>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<script>
function togglePayrollActions(id) {
    // Close all other dropdowns
    document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
        if (menu.id !== 'actions-' + id) {
            menu.classList.remove('show');
        }
    });
    
    // Toggle current dropdown
    const menu = document.getElementById('actions-' + id);
    if (menu) {
        menu.classList.toggle('show');
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

function showNotes(id, notes) {
    document.getElementById('notesContent').textContent = notes;
    document.getElementById('notesModal').style.display = 'flex';
}

function closeNotesModal() {
    document.getElementById('notesModal').style.display = 'none';
}

function exportPayroll() {
    // Get current filter parameters
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    // Convert export-payroll params to unified export API format
    const exportParams = new URLSearchParams();
    exportParams.set('module', 'payroll');
    exportParams.set('format', 'csv');
    // Map existing params
    if (params.has('date_from')) exportParams.set('date_from', params.get('date_from'));
    if (params.has('date_to')) exportParams.set('date_to', params.get('date_to'));
    if (params.has('worker')) exportParams.set('worker', params.get('worker'));
    if (params.has('role')) exportParams.set('role', params.get('role'));
    if (params.has('payment_status')) exportParams.set('payment_status', params.get('payment_status'));
    if (params.has('report_id')) exportParams.set('report_id', params.get('report_id'));
    window.location.href = '../api/export.php?' + exportParams.toString();
}

function showPayslipModal() {
    document.getElementById('payslipModal').style.display = 'flex';
}

function closePayslipModal() {
    document.getElementById('payslipModal').style.display = 'none';
}

function showBulkPayslipModal() {
    document.getElementById('bulkPayslipModal').style.display = 'flex';
}

function closeBulkPayslipModal() {
    document.getElementById('bulkPayslipModal').style.display = 'none';
    document.getElementById('bulkPayslipProgress').style.display = 'none';
    document.getElementById('bulkPayslipResults').innerHTML = '';
}

// Update button text based on selected distribution method
function updateBulkGenerateButtonText() {
    const submitBtn = document.getElementById('bulkGenerateBtn');
    if (!submitBtn) return;
    
    const selectedMethod = document.querySelector('input[name="distribution_method"]:checked')?.value;
    
    let actionText = 'Generate';
    switch(selectedMethod) {
        case 'download':
            actionText = 'Generate & Download';
            break;
        case 'email':
            actionText = 'Generate & Email';
            break;
        case 'both':
            actionText = 'Generate, Download & Email';
            break;
    }
    
    submitBtn.innerHTML = '🚀 ' + actionText;
}

// Add event listeners to update button text when radio buttons change
document.addEventListener('DOMContentLoaded', function() {
    // Add change listeners to radio buttons
    document.querySelectorAll('input[name="distribution_method"]').forEach(radio => {
        radio.addEventListener('change', function() {
            selectOption(this.value);
        });
    });
    
    // Initialize styling for default selected option
    const defaultOption = document.querySelector('input[name="distribution_method"]:checked')?.value || 'download';
    selectOption(defaultOption);
});

function selectAllWorkers() {
    document.querySelectorAll('#bulkPayslipForm input[name="workers[]"]').forEach(cb => cb.checked = true);
}

function deselectAllWorkers() {
    document.querySelectorAll('#bulkPayslipForm input[name="workers[]"]').forEach(cb => cb.checked = false);
}

// Handle option selection styling
function selectOption(option) {
    // Update radio button
    document.getElementById(`option_${option}_radio`).checked = true;
    
    // Update visual styling
    ['download', 'email', 'both'].forEach(opt => {
        const el = document.getElementById(`option_${opt}`);
        if (opt === option) {
            el.style.borderColor = 'var(--primary)';
            el.style.backgroundColor = '#eff6ff';
        } else {
            el.style.borderColor = '#e2e8f0';
            el.style.backgroundColor = 'white';
        }
    });
    
    // Update button text
    updateBulkGenerateButtonText();
}

// Handle bulk payslip form submission
document.getElementById('bulkPayslipForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    
    // Get selected distribution method
    const distributionMethod = form.querySelector('input[name="distribution_method"]:checked')?.value;
    
    if (!distributionMethod) {
        alert('Please select a distribution method');
        return;
    }
    
    // Convert radio selection to checkbox format for API
    const downloadLocally = distributionMethod === 'download' || distributionMethod === 'both';
    const sendEmails = distributionMethod === 'email' || distributionMethod === 'both';
    
    // Add as hidden fields or append to formData
    formData.append('download_locally', downloadLocally ? '1' : '0');
    formData.append('send_emails', sendEmails ? '1' : '0');
    
    // Get selected workers
    const selectedWorkers = [];
    form.querySelectorAll('input[name="workers[]"]:checked').forEach(cb => {
        selectedWorkers.push(cb.value);
    });
    
    if (selectedWorkers.length === 0) {
        alert('Please select at least one worker');
        return;
    }
    
    // Add workers to form data
    formData.delete('workers[]');
    selectedWorkers.forEach(worker => {
        formData.append('workers[]', worker);
    });
    
    // Get submit button
    const submitBtn = document.getElementById('bulkGenerateBtn');
    if (!submitBtn) {
        alert('Error: Submit button not found');
        return;
    }
    
    // Update button text
    updateBulkGenerateButtonText();
    
    // Show progress
    document.getElementById('bulkPayslipProgress').style.display = 'block';
    document.getElementById('bulkPayslipResults').innerHTML = '<p style="color: #64748b;">Generating payslips...</p>';
    
    // Disable submit button
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Processing...';
    
    // Set timeout to prevent infinite loading
    const timeoutId = setTimeout(() => {
        document.getElementById('bulkPayslipResults').innerHTML = `<div style="padding: 12px; background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 4px;">
            <strong style="color: #991b1b;">✗ Request timeout - The operation is taking longer than expected. Please check the server logs or try again.</strong>
        </div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }, 120000); // 2 minute timeout
    
    // Send request
    fetch('../api/bulk-payslips.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        clearTimeout(timeoutId);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        clearTimeout(timeoutId);
        let resultsHtml = '<div style="margin-bottom: 12px;"><strong style="color: #1e293b;">Results:</strong></div>';
        
        if (data.success) {
            resultsHtml += `<div style="padding: 12px; background: #dcfce7; border-left: 4px solid #16a34a; border-radius: 4px; margin-bottom: 16px;">
                <strong style="color: #166534;">✓ ${data.message}</strong>
                ${data.summary && data.summary.zip_url && data.summary.download_locally ? `<div style="margin-top: 10px;"><a href="${data.summary.zip_url}" target="_blank" style="display: inline-block; padding: 8px 16px; background: #16a34a; color: white; text-decoration: none; border-radius: 4px; font-size: 13px; font-weight: 500;">📥 Download All Payslips (ZIP)</a></div>` : ''}
                ${data.summary && data.summary.download_locally && !data.summary.zip_url ? `<div style="margin-top: 10px; color: #64748b; font-size: 13px;">ℹ️ Payslips saved locally on server</div>` : ''}
                ${data.summary && data.summary.send_emails ? `<div style="margin-top: 10px; color: #64748b; font-size: 13px;">📧 Email sending ${data.summary.send_emails ? 'enabled' : 'disabled'}</div>` : ''}
            </div>`;
            
            if (data.results && data.results.length > 0) {
                resultsHtml += '<div style="display: flex; flex-direction: column; gap: 8px;">';
                data.results.forEach(result => {
                    const bgColor = result.success ? '#dcfce7' : '#fee2e2';
                    const borderColor = result.success ? '#16a34a' : '#dc2626';
                    const icon = result.success ? '✓' : '✗';
                    
                    let errorDetails = '';
                    if (!result.success && result.message) {
                        errorDetails = `<div style="font-size: 11px; color: #991b1b; margin-top: 4px; padding: 6px; background: rgba(220, 38, 38, 0.1); border-radius: 4px;">
                            <strong>Error:</strong> ${result.message}
                            ${result.entries_count !== undefined ? `<br><strong>Entries found:</strong> ${result.entries_count}` : ''}
                            ${result.save_error ? `<br><strong>Save error:</strong> ${result.save_error}` : ''}
                            ${result.email_error ? `<br><strong>Email error:</strong> ${result.email_error}` : ''}
                        </div>`;
                    }
                    
                    resultsHtml += `<div style="padding: 10px; background: ${bgColor}; border-left: 3px solid ${borderColor}; border-radius: 4px;">
                        <div style="font-weight: 600; color: #1e293b; margin-bottom: 4px;">${icon} ${result.worker}</div>
                        <div style="font-size: 12px; color: #64748b;">${result.message || 'No details available'}</div>
                        ${errorDetails}
                        ${result.payslip_url ? `<div style="margin-top: 6px;"><a href="${result.payslip_url}" target="_blank" style="color: #7c3aed; text-decoration: none; font-size: 12px;">View Payslip →</a></div>` : ''}
                    </div>`;
                });
                resultsHtml += '</div>';
            }
            
            // Add debug info if available
            if (data.debug) {
                resultsHtml += `<div style="margin-top: 12px; padding: 10px; background: #f1f5f9; border-radius: 4px; font-size: 11px; color: #64748b;">
                    <strong>Debug Info:</strong><br>
                    ${JSON.stringify(data.debug, null, 2)}
                </div>`;
            }
        } else {
            resultsHtml += `<div style="padding: 12px; background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 4px;">
                <strong style="color: #991b1b;">✗ ${data.message || 'Error generating payslips'}</strong>
            </div>`;
        }
        
        document.getElementById('bulkPayslipResults').innerHTML = resultsHtml;
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    })
    .catch(error => {
        clearTimeout(timeoutId);
        console.error('Bulk payslip error:', error);
        document.getElementById('bulkPayslipResults').innerHTML = `<div style="padding: 12px; background: #fee2e2; border-left: 4px solid #dc2626; border-radius: 4px;">
            <strong style="color: #991b1b;">✗ Error: ${error.message || 'Failed to process request. Please check your connection and try again.'}</strong>
            <div style="margin-top: 8px; font-size: 12px; color: #64748b;">Check the browser console (F12) for more details.</div>
        </div>`;
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
});

// Close modals when clicking outside
document.addEventListener('click', function(e) {
    const notesModal = document.getElementById('notesModal');
    const payslipModal = document.getElementById('payslipModal');
    
    if (e.target === notesModal) {
        closeNotesModal();
    }
    if (e.target === payslipModal) {
        closePayslipModal();
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
