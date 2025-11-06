<?php
$pdo = getDBConnection();
$msg = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['close_period'])) {
    try {
        $stmt = $pdo->prepare("UPDATE fiscal_periods SET is_closed=1 WHERE id=?");
        $stmt->execute([intval($_POST['period_id'])]);
        $msg = 'Period closed';
    } catch (PDOException $e) { $msg = 'Error: '.$e->getMessage(); }
}

try { $periods = $pdo->query("SELECT * FROM fiscal_periods ORDER BY start_date DESC")->fetchAll(); }
catch (PDOException $e) { $periods = []; }
?>

<div class="dashboard-card">
    <h2>Settings & Fiscal Periods</h2>
    <?php if ($msg): ?><div class="alert alert-success"><?php echo e($msg); ?></div><?php endif; ?>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead><tr><th>Name</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($periods as $p): ?>
                <tr>
                    <td><?php echo e($p['name']); ?></td>
                    <td><?php echo e($p['start_date']); ?></td>
                    <td><?php echo e($p['end_date']); ?></td>
                    <td><?php echo $p['is_closed']?'Closed':'Open'; ?></td>
                    <td>
                        <?php if(!$p['is_closed']): ?>
                        <form method="post" style="display:inline;" onsubmit="return confirm('Close this period?');">
                            <?php echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="close_period" value="1">
                            <input type="hidden" name="period_id" value="<?php echo $p['id']; ?>">
                            <button class="btn btn-sm btn-outline">Close Period</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>


