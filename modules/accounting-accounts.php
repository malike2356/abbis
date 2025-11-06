<?php
$pdo = getDBConnection();
$message = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action']==='add') {
            $stmt = $pdo->prepare("INSERT INTO chart_of_accounts (account_code,account_name,account_type,parent_id,is_active) VALUES (?,?,?,?,1)");
            $stmt->execute([
                trim($_POST['account_code']), trim($_POST['account_name']), $_POST['account_type'], empty($_POST['parent_id'])?null:intval($_POST['parent_id'])
            ]);
            $message = 'Account added';
        }
    } catch (PDOException $e) { $message = 'Error: '.$e->getMessage(); }
}

try { $accounts = $pdo->query("SELECT * FROM chart_of_accounts ORDER BY account_type, account_code")->fetchAll(); }
catch (PDOException $e) { $accounts = []; }
?>

<div class="dashboard-card" style="margin-bottom:16px;">
    <h2>Chart of Accounts</h2>
    <?php if ($message): ?><div class="alert alert-success"><?php echo e($message); ?></div><?php endif; ?>
    <form method="post" style="display:grid; grid-template-columns: repeat(4,1fr); gap:12px; align-items:end;">
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="action" value="add">
        <div>
            <label class="form-label">Code</label>
            <input name="account_code" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Name</label>
            <input name="account_name" class="form-control" required>
        </div>
        <div>
            <label class="form-label">Type</label>
            <select name="account_type" class="form-control" required>
                <option>Asset</option><option>Liability</option><option>Equity</option><option>Revenue</option><option>Expense</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">Add Account</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><?php echo e($a['account_code']); ?></td>
                    <td><?php echo e($a['account_name']); ?></td>
                    <td><?php echo e($a['account_type']); ?></td>
                    <td><?php echo $a['is_active']? 'Active':'Inactive'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


