<?php
$pdo = getDBConnection();
$msg = null; $err = null;

// Load accounts for lines
try { $accounts = $pdo->query("SELECT id, account_code, account_name FROM chart_of_accounts WHERE is_active=1 ORDER BY account_code")->fetchAll(); }
catch (PDOException $e) { $accounts = []; }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_entry'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO journal_entries (entry_number, entry_date, reference, description, created_by) VALUES (?,?,?,?,?)");
        $entryNo = 'JE-' . date('Ymd-His');
        $stmt->execute([$entryNo, $_POST['entry_date'], trim($_POST['reference']??''), trim($_POST['description']??''), $_SESSION['user_id']]);
        $entryId = (int)$pdo->lastInsertId();
        $totalDebit = 0; $totalCredit = 0;
        foreach ($_POST['lines'] as $line) {
            $acc = intval($line['account_id'] ?? 0);
            $debit = (float)($line['debit'] ?? 0);
            $credit = (float)($line['credit'] ?? 0);
            if ($acc && ($debit>0 || $credit>0)) {
                $ins = $pdo->prepare("INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (?,?,?,?,?)");
                $ins->execute([$entryId, $acc, $debit, $credit, trim($line['memo'] ?? '')]);
                $totalDebit += $debit; $totalCredit += $credit;
            }
        }
        if (round($totalDebit,2) !== round($totalCredit,2)) { throw new Exception('Debits and Credits must balance'); }
        $pdo->commit();
        $msg = 'Journal entry created ('. e($entryNo) .')';
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = $e->getMessage();
    }
}

try { $recent = $pdo->query("SELECT * FROM journal_entries ORDER BY entry_date DESC, id DESC LIMIT 50")->fetchAll(); }
catch (PDOException $e) { $recent = []; }
?>

<div class="dashboard-card" style="margin-bottom:16px;">
    <h2>New Journal Entry</h2>
    <?php if ($err): ?><div class="alert alert-danger"><?php echo e($err); ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?php echo e($msg); ?></div><?php endif; ?>
    <form method="post">
        <?php echo CSRF::getTokenField(); ?>
        <input type="hidden" name="create_entry" value="1">
        <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:12px;">
            <div>
                <label class="form-label">Date</label>
                <input type="date" name="entry_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div>
                <label class="form-label">Reference</label>
                <input type="text" name="reference" class="form-control">
            </div>
            <div>
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control">
            </div>
        </div>
        <div style="margin-top:12px; overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Account</th><th>Debit</th><th>Credit</th><th>Memo</th></tr></thead>
                <tbody>
                    <?php for($i=0;$i<4;$i++): ?>
                    <tr>
                        <td>
                            <select name="lines[<?php echo $i; ?>][account_id]" class="form-control">
                                <option value="">-- Select Account --</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?php echo $acc['id']; ?>"><?php echo e($acc['account_code'].' - '.$acc['account_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" step="0.01" name="lines[<?php echo $i; ?>][debit]" class="form-control"></td>
                        <td><input type="number" step="0.01" name="lines[<?php echo $i; ?>][credit]" class="form-control"></td>
                        <td><input type="text" name="lines[<?php echo $i; ?>][memo]" class="form-control"></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
        </div>
        <div style="margin-top:10px; display:flex; justify-content:flex-end;">
            <button type="submit" class="btn btn-primary">Post Entry</button>
        </div>
    </form>
</div>

<div class="dashboard-card">
    <h2>Recent Journal Entries</h2>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>No.</th><th>Date</th><th>Reference</th><th>Description</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?php echo e($r['entry_number']); ?></td>
                    <td><?php echo e($r['entry_date']); ?></td>
                    <td><?php echo e($r['reference']); ?></td>
                    <td><?php echo e($r['description']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


