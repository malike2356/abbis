<?php
$pdo = getDBConnection();
// Sum revenue and expenses from journal against account types
function sumByType($pdo, $type) {
    try {
        $stmt = $pdo->prepare("SELECT 
            SUM(CASE WHEN c.account_type='Revenue' THEN (jl.credit - jl.debit) ELSE (jl.debit - jl.credit) END) as amount
            FROM chart_of_accounts c
            LEFT JOIN journal_entry_lines jl ON jl.account_id = c.id
            WHERE c.account_type = ?");
        $stmt->execute([$type]);
        return (float)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) { return 0; }
}
$revenue = sumByType($pdo,'Revenue');
$expenses = sumByType($pdo,'Expense');
$net = $revenue - $expenses;
?>

<div class="dashboard-card">
    <h2>Profit &amp; Loss</h2>
    <div class="kpi-grid">
        <div class="kpi-item"><span class="kpi-label">Revenue</span><span class="kpi-value">GHS <?php echo number_format($revenue,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Expenses</span><span class="kpi-value debt">GHS <?php echo number_format($expenses,2); ?></span></div>
        <div class="kpi-item"><span class="kpi-label">Net Profit</span><span class="kpi-value <?php echo $net>=0?'':'debt'; ?>">GHS <?php echo number_format($net,2); ?></span></div>
    </div>
</div>


