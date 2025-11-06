<?php
/**
 * Maintenance Add/Edit Form (lightweight)
 */

$pdo = getDBConnection();
$isEdit = isset($_GET['id']) && intval($_GET['id']) > 0;
$rec = null;
if ($isEdit) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM maintenance_records WHERE id = ?");
        $stmt->execute([intval($_GET['id'])]);
        $rec = $stmt->fetch();
    } catch (PDOException $e) {}
}
?>

<div class="dashboard-card" style="max-width: 900px; margin: 0 auto;">
    <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin:0; color: var(--text);"><?php echo $isEdit ? '✏️ Edit Maintenance' : '➕ Log Maintenance'; ?></h2>
        <a href="maintenance.php?action=records" class="btn btn-outline">← Back</a>
    </div>
    <form method="post" action="maintenance.php?action=records&save=1<?php echo $isEdit ? '&id=' . intval($_GET['id']) : ''; ?>"
          style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
        <div>
            <label class="form-label">Equipment</label>
            <input type="text" name="equipment_name" class="form-control" required value="<?php echo e($rec['equipment_name'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Type</label>
            <select name="maintenance_type" class="form-control">
                <?php $type = $rec['maintenance_type'] ?? 'proactive'; ?>
                <option value="proactive" <?php echo $type==='proactive'?'selected':''; ?>>Proactive</option>
                <option value="reactive" <?php echo $type==='reactive'?'selected':''; ?>>Reactive</option>
            </select>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <?php $status = $rec['status'] ?? 'logged'; ?>
                <option value="logged" <?php echo $status==='logged'?'selected':''; ?>>Logged</option>
                <option value="actioned" <?php echo $status==='actioned'?'selected':''; ?>>Actioned</option>
                <option value="progress" <?php echo $status==='progress'?'selected':''; ?>>In Progress</option>
                <option value="completed" <?php echo $status==='completed'?'selected':''; ?>>Completed</option>
            </select>
        </div>
        <div>
            <label class="form-label">Performed By</label>
            <input type="text" name="performed_by" class="form-control" value="<?php echo e($rec['performed_by'] ?? ''); ?>">
        </div>
        <div>
            <label class="form-label">Date</label>
            <input type="datetime-local" name="performed_at" class="form-control" value="<?php echo !empty($rec['performed_at']) ? date('Y-m-d\TH:i', strtotime($rec['performed_at'])) : ''; ?>">
        </div>
        <div>
            <label class="form-label">Cost</label>
            <input type="number" step="0.01" name="cost" class="form-control" value="<?php echo e($rec['cost'] ?? ''); ?>">
        </div>
        <div style="grid-column: 1 / -1;">
            <label class="form-label">Details</label>
            <textarea name="details" rows="3" class="form-control"><?php echo e($rec['details'] ?? ''); ?></textarea>
        </div>
        <div style="grid-column: 1 / -1; display:flex; gap: 10px; justify-content: flex-end;">
            <a href="maintenance.php?action=records" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</div>


