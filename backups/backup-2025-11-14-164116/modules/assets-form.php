<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

/**
 * Asset Add/Edit Form (placeholder implementation)
 * Provides a theme-aware form to create or edit an asset.
 */

$isEdit = isset($_GET['id']) && intval($_GET['id']) > 0;
$assetId = intval($_GET['id'] ?? 0);

$pdo = getDBConnection();
$asset = null;

if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$assetId]);
        $asset = $stmt->fetch();
    } catch (PDOException $e) {
        $asset = null;
    }
}
?>

<div class="dashboard-card" style="max-width: 900px; margin: 0 auto;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin:0; color: var(--text);">
            <?php echo $isEdit ? '✏️ Edit Asset' : '➕ Add New Asset'; ?>
        </h2>
        <a href="assets.php?action=assets" class="btn btn-outline">← Back to Assets</a>
    </div>

    <form method="post" action="assets.php?action=<?php echo $isEdit ? 'assets&save=1&id=' . $assetId : 'assets&save=1'; ?>" enctype="application/x-www-form-urlencoded"
          style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label class="form-label">Asset Name</label>
            <input type="text" name="asset_name" class="form-control" required
                   value="<?php echo e($asset['asset_name'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Category</label>
            <input type="text" name="category" class="form-control"
                   value="<?php echo e($asset['category'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Serial / Identifier</label>
            <input type="text" name="serial_number" class="form-control"
                   value="<?php echo e($asset['serial_number'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php $status = $asset['status'] ?? 'active'; ?>
                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="maintenance" <?php echo $status === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="disposed" <?php echo $status === 'disposed' ? 'selected' : ''; ?>>Disposed</option>
            </select>
        </div>
        <div>
            <label class="form-label">Purchase Date</label>
            <input type="date" name="purchase_date" class="form-control"
                   value="<?php echo e($asset['purchase_date'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Purchase Cost</label>
            <input type="number" step="0.01" name="purchase_cost" class="form-control"
                   value="<?php echo e($asset['purchase_cost'] ?? ''); ?>">
        </div>
        <div style="grid-column: 1 / -1;">
            <label class="form-label">Description / Notes</label>
            <textarea name="description" rows="3" class="form-control"><?php echo e($asset['description'] ?? ''); ?></textarea>
        </div>

        <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content: flex-end; margin-top: 8px;">
            <a href="assets.php?action=assets" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Asset</button>
        </div>
    </form>
</div>


