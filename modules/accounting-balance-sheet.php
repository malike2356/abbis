<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Calculate Assets (Debit - Credit for Asset accounts)
function sumAssets($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(jl.debit - jl.credit), 0) as amount
            FROM chart_of_accounts c
            INNER JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = 'Asset'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    } catch (PDOException $e) { return 0; }
}

// Calculate Liabilities (Credit - Debit for Liability accounts)
function sumLiabilities($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(jl.credit - jl.debit), 0) as amount
            FROM chart_of_accounts c
            INNER JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = 'Liability'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    } catch (PDOException $e) { return 0; }
}

// Calculate explicit Equity accounts (Credit - Debit)
function sumExplicitEquity($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(jl.credit - jl.debit), 0) as amount
            FROM chart_of_accounts c
            INNER JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = 'Equity'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    } catch (PDOException $e) { return 0; }
}

// Calculate Revenue (Credit - Debit for Revenue accounts)
function sumRevenue($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(jl.credit - jl.debit), 0) as amount
            FROM chart_of_accounts c
            INNER JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = 'Revenue'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    } catch (PDOException $e) { return 0; }
}

// Calculate Expenses (Debit - Credit for Expense accounts)
function sumExpenses($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            COALESCE(SUM(jl.debit - jl.credit), 0) as amount
            FROM chart_of_accounts c
            INNER JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = 'Expense'");
        $stmt->execute();
        $result = $stmt->fetchColumn();
        return (float)($result ?? 0);
    } catch (PDOException $e) { return 0; }
}

$assets = sumAssets($pdo);
$liabilities = sumLiabilities($pdo);
$explicitEquity = sumExplicitEquity($pdo);
$revenue = sumRevenue($pdo);
$expenses = sumExpenses($pdo);
$retainedEarnings = $revenue - $expenses; // Net Income
$totalEquity = $explicitEquity + $retainedEarnings; // Equity = Capital + Retained Earnings
?>

<div class="dashboard-card">
    <h2>Balance Sheet</h2>
    <div class="kpi-grid">
        <div class="kpi-item"><span class="kpi-label">Assets</span><span class="kpi-value">GHS <?php echo number_format($assets,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Liabilities</span><span class="kpi-value debt">GHS <?php echo number_format($liabilities,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Equity</span><span class="kpi-value">GHS <?php echo number_format($totalEquity,2); ?></span></div>
    </div>
    
    <div style="margin-top:16px; padding:12px; background:#f8fafc; border-radius:8px; border-left:4px solid #3b82f6;">
        <h4 style="margin:0 0 8px 0; font-size:14px; color:#1e293b;">Equity Breakdown</h4>
        <div style="font-size:13px; line-height:1.8; color:#475569;">
            <div>Capital/Equity Accounts: <strong>GHS <?php echo number_format($explicitEquity,2); ?></strong></div>
            <div>Revenue: <strong>GHS <?php echo number_format($revenue,2); ?></strong></div>
            <div>Expenses: <strong>GHS <?php echo number_format($expenses,2); ?></strong></div>
            <div>Retained Earnings (Net Income): <strong style="color:<?php echo $retainedEarnings >= 0 ? '#10b981' : '#ef4444'; ?>">GHS <?php echo number_format($retainedEarnings,2); ?></strong></div>
            <div style="margin-top:8px; padding-top:8px; border-top:1px solid #e2e8f0;"><strong>Total Equity: GHS <?php echo number_format($totalEquity,2); ?></strong></div>
        </div>
    </div>
    
    <div style="margin-top:12px; font-size: 13px; color: var(--secondary);">
        Equation: Assets (<?php echo number_format($assets,2); ?>) = Liabilities (<?php echo number_format($liabilities,2); ?>) + Equity (<?php echo number_format($totalEquity,2); ?>)
    </div>
    
    <?php 
    $isBalanced = abs($assets - ($liabilities + $totalEquity)) < 0.01; // Allow small rounding differences
    ?>
    <?php if (!$isBalanced): ?>
        <div class="alert alert-warning" style="margin-top:8px;">
            ⚠️ Balance sheet not balanced. 
            Difference: GHS <?php echo number_format(abs($assets - ($liabilities + $totalEquity)), 2); ?>
            <br><small>This may indicate missing entries or calculation errors. Review journal entries.</small>
        </div>
    <?php else: ?>
        <div class="alert alert-success" style="margin-top:8px; background:#f0fdf4; border-color:#86efac; color:#166534;">
            ✅ Balance sheet is balanced correctly!
        </div>
    <?php endif; ?>
</div>


