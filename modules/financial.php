<?php
/**
 * Financial Management Hub
 * Central location for all financial operations
 */
$page_title = 'Financial Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>💰 Financial Management</h1>
        <p>Central hub for all financial operations and transactions</p>
    </div>
    
    <!-- Financial Management Grid -->
    <div class="dashboard-grid">
        <!-- Finance Overview -->
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📊</span>
                <div>
                    <h2 style="margin: 0;">Finance Overview</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Financial reports, transactions, and analytics
                    </p>
                </div>
            </div>
            <a href="finance.php" class="btn btn-primary" style="width: 100%;">
                Open Finance →
            </a>
        </div>
        
        <!-- Payroll -->
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">💵</span>
                <div>
                    <h2 style="margin: 0;">Payroll</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Worker payments and payroll management
                    </p>
                </div>
            </div>
            <a href="payroll.php" class="btn btn-primary" style="width: 100%;">
                Open Payroll →
            </a>
        </div>
        
        <!-- Loans -->
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">💳</span>
                <div>
                    <h2 style="margin: 0;">Loans</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Worker loans and debt management
                    </p>
                </div>
            </div>
            <a href="loans.php" class="btn btn-primary" style="width: 100%;">
                Open Loans →
            </a>
        </div>

        <!-- Accounting -->
        <?php if (function_exists('isFeatureEnabled') ? isFeatureEnabled('accounting') : true): ?>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📘</span>
                <div>
                    <h2 style="margin: 0;">Accounting</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        General accounting (double-entry), reports, integrations
                    </p>
                </div>
            </div>
            <a href="accounting.php" class="btn btn-primary" style="width: 100%;">
                Open Accounting →
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Collections Assistant -->
        <?php if (function_exists('isFeatureEnabled') ? isFeatureEnabled('collections') : true): ?>
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📅</span>
                <div>
                    <h2 style="margin: 0;">Collections Assistant</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Predict late payers; schedule smart reminders</p>
                </div>
            </div>
            <a href="collections.php" class="btn btn-primary" style="width: 100%;">Open Collections →</a>
        </div>
        <?php endif; ?>
        
        <!-- Debt Recovery -->
        <div class="dashboard-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🔍</span>
                <div>
                    <h2 style="margin: 0;">Debt Recovery</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Track and recover unpaid contract amounts</p>
                </div>
            </div>
            <?php
            $pdo = getDBConnection();
            try {
                $debtCount = $pdo->query("SELECT COUNT(*) FROM debt_recoveries WHERE status IN ('outstanding', 'partially_paid', 'in_collection')")->fetchColumn() ?: 0;
                $debtAmount = $pdo->query("SELECT COALESCE(SUM(remaining_debt), 0) FROM debt_recoveries WHERE status IN ('outstanding', 'partially_paid', 'in_collection')")->fetchColumn() ?: 0;
            } catch (PDOException $e) {
                $debtCount = 0;
                $debtAmount = 0;
            }
            ?>
            <?php if ($debtCount > 0): ?>
                <div style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                    <div style="font-size: 14px; color: var(--danger); font-weight: 600; margin-bottom: 4px;">
                        ⚠️ <?php echo number_format($debtCount); ?> Outstanding Debt<?php echo $debtCount > 1 ? 's' : ''; ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text);">
                        Total: GHS <?php echo number_format($debtAmount, 2); ?>
                    </div>
                </div>
            <?php endif; ?>
            <a href="debt-recovery.php" class="btn btn-primary" style="width: 100%;">Open Debt Recovery →</a>
        </div>
    </div>
    
    <!-- Quick Financial Stats -->
    <div class="dashboard-card" id="quick-financial-overview" style="margin-top: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
            <h2 style="margin: 0;">📈 Quick Financial Overview</h2>
            <div class="export-buttons" style="display: flex; gap: 6px;">
                <a href="export.php?module=reports&format=csv" class="btn btn-sm btn-outline" title="Export Financial Data as CSV">
                    📥 CSV
                </a>
                <a href="export.php?module=reports&format=excel" class="btn btn-sm btn-outline" title="Export Financial Data as Excel">
                    📊 Excel
                </a>
                <a href="export.php?module=reports&format=pdf" class="btn btn-sm btn-outline" title="Export Financial Data as PDF" target="_blank">
                    📄 PDF
                </a>
            </div>
        </div>
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <?php
            $pdo = getDBConnection();
            
            try {
                // Total Revenue
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_income), 0) as total FROM field_reports");
                $totalRevenue = $stmt->fetch()['total'];
                
                // Total Expenses
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_expenses), 0) as total FROM field_reports");
                $totalExpenses = $stmt->fetch()['total'];
                
                // Total Profit
                $totalProfit = $totalRevenue - $totalExpenses;
                
                // Total Payroll
                $stmt = $pdo->query("SELECT COALESCE(SUM(total_wages), 0) as total FROM field_reports");
                $totalPayroll = $stmt->fetch()['total'];
                
                // Total Loans
                $stmt = $pdo->query("SELECT COALESCE(SUM(loan_amount), 0) as total FROM rig_fee_debts WHERE status = 'outstanding'");
                $totalLoans = $stmt->fetch()['total'] ?? 0;
                
                // Outstanding Loans Count
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM rig_fee_debts WHERE status = 'outstanding'");
                $loansCount = $stmt->fetch()['count'] ?? 0;
                
                // Outstanding Debt Recovery
                $stmt = $pdo->query("SELECT COALESCE(SUM(remaining_debt), 0) as total, COUNT(*) as count FROM debt_recoveries WHERE status IN ('outstanding', 'partially_paid', 'in_collection')");
                $debtRecovery = $stmt->fetch(PDO::FETCH_ASSOC);
                $totalDebtRecovery = floatval($debtRecovery['total'] ?? 0);
                $debtRecoveryCount = intval($debtRecovery['count'] ?? 0);
            } catch (PDOException $e) {
                $totalRevenue = $totalExpenses = $totalProfit = $totalPayroll = $totalLoans = $loansCount = 0;
                $totalDebtRecovery = $debtRecoveryCount = 0;
            }
            ?>
            
            <div class="stat-card">
                <div class="stat-icon">💰</div>
                <div class="stat-info">
                    <h3><?php echo formatCurrency($totalRevenue); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💸</div>
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
                <div class="stat-icon">💵</div>
                <div class="stat-info">
                    <h3><?php echo formatCurrency($totalPayroll); ?></h3>
                    <p>Total Payroll</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💳</div>
                <div class="stat-info">
                    <h3><?php echo formatCurrency($totalLoans); ?></h3>
                    <p>Outstanding Loans</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📋</div>
                <div class="stat-info">
                    <h3><?php echo $loansCount; ?></h3>
                    <p>Active Loans</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔍</div>
                <div class="stat-info">
                    <h3 style="color: <?php echo $totalDebtRecovery > 0 ? 'var(--danger)' : 'var(--text)'; ?>;">
                        GHS <?php echo number_format($totalDebtRecovery, 2); ?>
                    </h3>
                    <p>Outstanding Debts (<?php echo $debtRecoveryCount; ?>)</p>
                </div>
            </div>
        </div>
        
        <!-- Rig Breakdown -->
        <div style="margin-top: 30px; padding-top: 30px; border-top: 2px solid var(--border);">
            <h3 style="margin-bottom: 20px;">📊 Financial Summary by Rig</h3>
            <?php
            try {
                $rigBreakdown = $pdo->query("
                    SELECT 
                        r.id,
                        r.rig_name,
                        r.rig_code,
                        COUNT(fr.id) as total_jobs,
                        COALESCE(SUM(fr.total_income), 0) as total_revenue,
                        COALESCE(SUM(fr.total_expenses), 0) as total_expenses,
                        COALESCE(SUM(fr.net_profit), 0) as total_profit,
                        COALESCE(SUM(fr.total_rpm), 0) as total_rpm,
                        COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job
                    FROM rigs r
                    LEFT JOIN field_reports fr ON fr.rig_id = r.id
                    WHERE r.status = 'active'
                    GROUP BY r.id, r.rig_name, r.rig_code
                    ORDER BY total_revenue DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rigBreakdown)):
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($rigBreakdown as $rig): 
                    $profitMargin = $rig['total_revenue'] > 0 ? (($rig['total_profit'] / $rig['total_revenue']) * 100) : 0;
                ?>
                <div style="background: var(--card); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 16px;">
                        <div>
                            <h4 style="margin: 0 0 4px 0; color: var(--text);"><?php echo htmlspecialchars($rig['rig_name']); ?></h4>
                            <p style="margin: 0; font-size: 12px; color: var(--secondary);"><?php echo htmlspecialchars($rig['rig_code']); ?></p>
                        </div>
                        <span style="font-size: 24px;">🚛</span>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                        <div>
                            <div style="font-size: 11px; color: var(--secondary);">Jobs</div>
                            <div style="font-size: 18px; font-weight: 600; color: var(--text);"><?php echo number_format($rig['total_jobs']); ?></div>
                        </div>
                        <div>
                            <div style="font-size: 11px; color: var(--secondary);">Total RPM</div>
                            <div style="font-size: 18px; font-weight: 600; color: var(--text);"><?php echo number_format($rig['total_rpm'], 2); ?></div>
                        </div>
                    </div>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; color: var(--secondary);">Revenue:</span>
                            <strong style="color: var(--text);"><?php echo formatCurrency($rig['total_revenue']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; color: var(--secondary);">Expenses:</span>
                            <strong style="color: var(--text);"><?php echo formatCurrency($rig['total_expenses']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                            <span style="font-size: 12px; color: var(--secondary);">Profit:</span>
                            <strong style="color: <?php echo $rig['total_profit'] >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo formatCurrency($rig['total_profit']); ?>
                            </strong>
                        </div>
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-size: 12px; color: var(--secondary);">Margin:</span>
                            <strong style="color: <?php echo $profitMargin >= 0 ? 'var(--success)' : 'var(--danger)'; ?>;">
                                <?php echo number_format($profitMargin, 1); ?>%
                            </strong>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color: var(--secondary); text-align: center; padding: 20px;">No rig data available</p>
            <?php 
                endif;
            } catch (PDOException $e) {
                echo '<p style="color: var(--danger);">Error loading rig breakdown: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
            ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

