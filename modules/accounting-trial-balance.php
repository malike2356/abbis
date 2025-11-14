<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();
$rows = [];
try {
    $rows = $pdo->query("SELECT c.account_code, c.account_name,
        COALESCE(SUM(jl.debit), 0) as debit, COALESCE(SUM(jl.credit), 0) as credit
        FROM chart_of_accounts c
        LEFT JOIN journal_entry_lines jl ON jl.account_id = c.id
        GROUP BY c.id
        ORDER BY c.account_type, c.account_code")->fetchAll();
} catch (PDOException $e) { $rows = []; }

$totalDebit = 0; $totalCredit = 0;
?>

<div class="dashboard-card">
    <h2>Trial Balance</h2>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Account</th><th>Debit</th><th>Credit</th></tr></thead>
            <tbody>
                <?php foreach ($rows as $r): 
                    $debit = (float)$r['debit'];
                    $credit = (float)$r['credit'];
                    $totalDebit += $debit; 
                    $totalCredit += $credit; 
                ?>
                <tr>
                    <td><?php echo e($r['account_code']); ?></td>
                    <td><?php echo e($r['account_name']); ?></td>
                    <td><?php echo number_format($debit, 2); ?></td>
                    <td><?php echo number_format($credit, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align:right;">Totals</th>
                    <th><?php echo number_format($totalDebit,2); ?></th>
                    <th><?php echo number_format($totalCredit,2); ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php if (round($totalDebit,2) !== round($totalCredit,2)): ?>
        <div class="alert alert-warning" style="margin-top:10px;">⚠️ Trial balance does not balance. Please review entries.</div>
    <?php endif; ?>
</div>


