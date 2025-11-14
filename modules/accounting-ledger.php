<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Load accounts
try { $accounts = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts ORDER BY account_code")->fetchAll(); }
catch (PDOException $e) { $accounts = []; }

$accountId = intval($_GET['account_id'] ?? 0);
$lines = [];
if ($accountId) {
    try {
        $stmt = $pdo->prepare("SELECT je.entry_date, je.entry_number, jl.debit, jl.credit, jl.memo
                                FROM journal_entry_lines jl
                                JOIN journal_entries je ON je.id = jl.journal_entry_id
                                WHERE jl.account_id = ?
                                ORDER BY je.entry_date, jl.id");
        $stmt->execute([$accountId]);
        $lines = $stmt->fetchAll();
    } catch (PDOException $e) { $lines = []; }
}
?>

<div class="dashboard-card">
    <h2>Account Ledger</h2>
    <form method="get" style="display:flex; gap:10px; align-items:end; margin-bottom:12px;">
        <input type="hidden" name="action" value="ledger">
        <div style="flex:1;">
            <label class="form-label">Account</label>
            <select name="account_id" class="form-control" required>
                <option value="">-- Select --</option>
                <?php foreach ($accounts as $acc): ?>
                    <option value="<?php echo $acc['id']; ?>" <?php echo $accountId===$acc['id']?'selected':''; ?>>
                        <?php echo e($acc['account_code'].' - '.$acc['account_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button class="btn btn-primary">View</button>
        </div>
    </form>

    <?php if ($accountId && empty($lines)): ?>
        <p class="no-data">No ledger entries found for selected account.</p>
    <?php elseif ($accountId): ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Entry No.</th><th>Debit</th><th>Credit</th><th>Memo</th></tr></thead>
                <tbody>
                    <?php $balance = 0; foreach ($lines as $ln): $balance += ($ln['debit'] - $ln['credit']); ?>
                    <tr>
                        <td><?php echo e($ln['entry_date']); ?></td>
                        <td><?php echo e($ln['entry_number']); ?></td>
                        <td><?php echo number_format($ln['debit'],2); ?></td>
                        <td><?php echo number_format($ln['credit'],2); ?></td>
                        <td><?php echo e($ln['memo']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top:10px; font-size:13px; color: var(--secondary);">Running Balance: <strong style="color: var(--text);">GHS <?php echo number_format($balance,2); ?></strong></div>
        </div>
    <?php endif; ?>
</div>


