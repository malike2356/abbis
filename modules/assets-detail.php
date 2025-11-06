<?php
/**
 * Asset Detail View (lightweight)
 */

$pdo = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$asset = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
    $stmt->execute([$id]);
    $asset = $stmt->fetch();
} catch (PDOException $e) {}
?>

<div class="dashboard-card">
    <?php if (!$asset): ?>
        <p style="text-align:center; color: var(--secondary); padding: 30px;">Asset not found.</p>
    <?php else: ?>
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 style="margin:0; color: var(--text);">🏭 <?php echo e($asset['asset_name']); ?></h2>
            <div style="display:flex; gap:10px;">
                <a href="assets.php?action=edit&id=<?php echo $id; ?>" class="btn btn-outline">Edit</a>
                <a href="assets.php?action=assets" class="btn btn-outline">Back</a>
            </div>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <div style="font-size:12px; color: var(--secondary);">Category</div>
                <div style="color: var(--text); font-weight:600; "><?php echo e($asset['category'] ?? '—'); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Serial / Identifier</div>
                <div style="color: var(--text); font-weight:600; "><?php echo e($asset['serial_number'] ?? '—'); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Status</div>
                <div style="color: var(--text); font-weight:600; "><?php echo ucfirst($asset['status'] ?? 'active'); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Purchase</div>
                <div style="color: var(--text); font-weight:600; ">
                    <?php echo !empty($asset['purchase_date']) ? formatDate($asset['purchase_date']) : '—'; ?>
                    ·
                    <?php echo isset($asset['purchase_cost']) ? formatCurrency($asset['purchase_cost']) : '—'; ?>
                </div>
            </div>
            <div style="grid-column: 1 / -1;">
                <div style="font-size:12px; color: var(--secondary);">Description</div>
                <div style="color: var(--text); "><?php echo nl2br(e($asset['description'] ?? '')); ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>


