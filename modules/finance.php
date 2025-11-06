<?php
/**
 * Financial Reports
 */
$page_title = 'Financial Reports';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();

// Get date range from filters or default to current month
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$rigId = intval($_GET['rig_id'] ?? 0);

// Build query for financial reports
$query = "
    SELECT 
        fr.*,
        r.rig_name,
        c.client_name
    FROM field_reports fr
    LEFT JOIN rigs r ON fr.rig_id = r.id
    LEFT JOIN clients c ON fr.client_id = c.id
    WHERE fr.report_date BETWEEN ? AND ?
";

$params = [$startDate, $endDate];

if (!empty($rigId)) {
    $query .= " AND fr.rig_id = ?";
    $params[] = $rigId;
}

$query .= " ORDER BY fr.report_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Calculate totals
$totalIncome = array_sum(array_column($reports, 'total_income'));
$totalExpenses = array_sum(array_column($reports, 'total_expenses'));
$totalProfit = array_sum(array_column($reports, 'net_profit'));
$totalBanked = array_sum(array_column($reports, 'total_money_banked'));

// Get rigs for filter
$rigs = $pdo->query("SELECT * FROM rigs WHERE status = 'active'")->fetchAll();

require_once '../includes/header.php';
?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Financial Reports</h1>
                    <p>Track income, expenses, and profitability</p>
                </div>
            </div>

            <!-- Financial Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalIncome); ?></h3>
                        <p>Total Income</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalExpenses); ?></h3>
                        <p>Total Expenses</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📈</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalProfit); ?></h3>
                        <p>Net Profit</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">🏦</div>
                    <div class="stat-info">
                        <h3><?php echo formatCurrency($totalBanked); ?></h3>
                        <p>Money Banked</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="dashboard-card">
                <h2>Filters</h2>
                <form method="GET" class="finance-filters-form">
                    <div class="form-group">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo e($startDate); ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo e($endDate); ?>">
                    </div>
                    <div class="form-group">
                        <label for="rig_id" class="form-label">Rig</label>
                        <select id="rig_id" name="rig_id" class="form-control">
                            <option value="">All Rigs</option>
                            <?php foreach ($rigs as $rig): ?>
                                <option value="<?php echo $rig['id']; ?>" <?php echo $rigId == $rig['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($rig['rig_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group finance-filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="finance.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
                <style>
                    .finance-filters-form {
                        display: grid !important;
                        grid-template-columns: repeat(3, 1fr) !important;
                        gap: 20px !important;
                        align-items: end !important;
                    }
                    .finance-filters-form .form-group {
                        margin-bottom: 0;
                    }
                    .finance-filter-actions {
                        grid-column: span 3;
                        display: flex !important;
                        gap: 10px;
                        margin-top: 8px;
                    }
                    @media (max-width: 768px) {
                        .finance-filters-form {
                            grid-template-columns: 1fr !important;
                        }
                        .finance-filter-actions {
                            grid-column: span 1;
                        }
                    }
                </style>
            </div>

            <!-- Financial Reports Table -->
            <div class="dashboard-card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="margin: 0;">Financial Reports (<?php echo count($reports); ?>)</h2>
                    <a href="../api/export.php?module=reports&format=csv&date_from=<?php echo urlencode($startDate); ?>&date_to=<?php echo urlencode($endDate); ?>" 
                       class="btn btn-primary">Export CSV</a>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Report ID</th>
                                <th>Site</th>
                                <th>Rig</th>
                                <th>Income</th>
                                <th>Expenses</th>
                                <th>Profit</th>
                                <th>Banked</th>
                                <th>Balance</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="10" class="text-center">No financial reports found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><?php echo formatDate($report['report_date']); ?></td>
                                    <td><code><?php echo e($report['report_id']); ?></code></td>
                                    <td><?php echo e($report['site_name']); ?></td>
                                    <td><?php echo e($report['rig_name']); ?></td>
                                    <td class="text-success"><?php echo formatCurrency($report['total_income']); ?></td>
                                    <td class="text-danger"><?php echo formatCurrency($report['total_expenses']); ?></td>
                                    <td class="<?php echo $report['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <strong><?php echo formatCurrency($report['net_profit']); ?></strong>
                                    </td>
                                    <td><?php echo formatCurrency($report['total_money_banked']); ?></td>
                                    <td><?php echo formatCurrency($report['days_balance']); ?></td>
                                    <td>
                                        <a href="field-reports.php?id=<?php echo $report['id']; ?>" 
                                           class="btn btn-sm btn-outline">View</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Summary Details -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h2>Income Breakdown</h2>
                    <div class="kpi-grid">
                        <?php
                        $contractSum = array_sum(array_column($reports, 'contract_sum'));
                        $rigFeeCollected = array_sum(array_column($reports, 'rig_fee_collected'));
                        $cashReceived = array_sum(array_column($reports, 'cash_received'));
                        $materialsIncome = array_sum(array_column($reports, 'materials_income'));
                        ?>
                        <div class="kpi-item">
                            <span class="kpi-label">Contract Sum</span>
                            <span class="kpi-value"><?php echo formatCurrency($contractSum); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Rig Fees</span>
                            <span class="kpi-value"><?php echo formatCurrency($rigFeeCollected); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Cash Received</span>
                            <span class="kpi-value"><?php echo formatCurrency($cashReceived); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Materials Income</span>
                            <span class="kpi-value"><?php echo formatCurrency($materialsIncome); ?></span>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <h2>Expense Breakdown</h2>
                    <div class="kpi-grid">
                        <?php
                        $materialsCost = array_sum(array_column($reports, 'materials_cost'));
                        $totalWages = array_sum(array_column($reports, 'total_wages'));
                        $outstandingRigFee = array_sum(array_column($reports, 'outstanding_rig_fee'));
                        ?>
                        <div class="kpi-item">
                            <span class="kpi-label">Materials Cost</span>
                            <span class="kpi-value debt"><?php echo formatCurrency($materialsCost); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Total Wages</span>
                            <span class="kpi-value debt"><?php echo formatCurrency($totalWages); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Outstanding Rig Fees</span>
                            <span class="kpi-value debt"><?php echo formatCurrency($outstandingRigFee); ?></span>
                        </div>
                        <div class="kpi-item">
                            <span class="kpi-label">Other Expenses</span>
                            <span class="kpi-value debt"><?php echo formatCurrency($totalExpenses - $materialsCost - $totalWages); ?></span>
                        </div>
                    </div>
                </div>
            </div>

<?php require_once '../includes/footer.php'; ?>
