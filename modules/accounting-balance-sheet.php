<?php
$pdo = getDBConnection();
function sumType($pdo, $type) {
    try {
        $stmt = $pdo->prepare("SELECT 
            SUM(CASE WHEN c.account_type IN ('Asset','Expense') THEN (jl.debit - jl.credit) ELSE (jl.credit - jl.debit) END) as amount
            FROM chart_of_accounts c
            LEFT JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = ?");
        $stmt->execute([$type]);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) { return 0; }
}
$assets = sumType($pdo,'Asset');
$liabilities = sumType($pdo,'Liability');
$equity = sumType($pdo,'Equity');
?>

<div class="dashboard-card">
    <h2>Balance Sheet</h2>
    <div class="kpi-grid">
        <div class="kpi-item"><span class="kpi-label">Assets</span><span class="kpi-value">GHS <?php echo number_format($assets,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Liabilities</span><span class="kpi-value debt">GHS <?php echo number_format($liabilities,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Equity</span><span class="kpi-value">GHS <?php echo number_format($equity,2); ?></span></div>
    </div>
    <div style="margin-top:10px; font-size: 13px; color: var(--secondary);">
        Equation: Assets (<?php echo number_format($assets,2); ?>) = Liabilities (<?php echo number_format($liabilities,2); ?>) + Equity (<?php echo number_format($equity,2); ?>)
    </div>
    <?php if (round($assets,2) !== round($liabilities + $equity,2)): ?>
        <div class="alert alert-warning" style="margin-top:8px;">⚠️ Balance sheet not balanced. Review entries.</div>
    <?php endif; ?>
</div>


