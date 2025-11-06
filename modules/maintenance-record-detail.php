<?php
/**
 * Maintenance Record Detail (lightweight)
 */

$pdo = getDBConnection();
$id = intval($_GET['id'] ?? 0);
$rec = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM maintenance_records WHERE id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch();
} catch (PDOException $e) {}
?>

<div class="dashboard-card">
    <?php if (!$rec): ?>
        <p style="text-align:center; color: var(--secondary); padding: 30px;">Record not found.</p>
    <?php else: ?>
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h2 style="margin:0; color: var(--text);">🔧 <?php echo e($rec['equipment_name']); ?></h2>
            <a href="maintenance.php?action=records" class="btn btn-outline">Back</a>
        </div>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
                <div style="font-size:12px; color: var(--secondary);">Type</div>
                <div style="color: var(--text); font-weight:600; "><?php echo ucfirst($rec['maintenance_type']); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Status</div>
                <div style="color: var(--text); font-weight:600; "><?php echo ucfirst($rec['status']); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Performed By</div>
                <div style="color: var(--text); font-weight:600; "><?php echo e($rec['performed_by'] ?? '—'); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Date</div>
                <div style="color: var(--text); font-weight:600; "><?php echo formatDate($rec['performed_at']); ?></div>
            </div>
            <div>
                <div style="font-size:12px; color: var(--secondary);">Cost</div>
                <div style="color: var(--text); font-weight:600; "><?php echo formatCurrency($rec['cost'] ?? 0); ?></div>
            </div>
            <div style="grid-column: 1 / -1;">
                <div style="font-size:12px; color: var(--secondary);">Details</div>
                <div style="color: var(--text); "><?php echo nl2br(e($rec['details'] ?? '')); ?></div>
            </div>
        </div>
    <?php endif; ?>
</div>


