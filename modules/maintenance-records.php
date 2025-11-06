<?php
/**
 * Maintenance Records View (lightweight)
 */

$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Load records (graceful if table missing)
$tableMissing = false;
try {
    $stmt = $pdo->query("SELECT id, equipment_name, maintenance_type, status, performed_by, performed_at, cost FROM maintenance_records ORDER BY performed_at DESC LIMIT 200");
    $records = $stmt->fetchAll();
} catch (PDOException $e) {
    $records = [];
    $tableMissing = true;
}

// Explicitly check if table exists
if (!tableExists($pdo, 'maintenance_records')) {
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">📋 Maintenance Records</h2>
        <a href="maintenance.php?action=add" class="btn btn-primary">➕ Log Maintenance</a>
    </div>
    <?php if ($tableMissing): ?>
        <?php 
        $missingTables = checkTablesExist($pdo, ['maintenance_records', 'maintenance_types']);
        echo showMigrationWarning($missingTables, getMigrationFileForModule('maintenance'), 'Maintenance Management');
        ?>
    <?php endif; ?>
</div>

<div class="dashboard-card">
    <?php if (empty($records)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 40px;">No maintenance records yet.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Equipment</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Performed By</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td style="color: var(--text); "><?php echo formatDate($r['performed_at']); ?></td>
                            <td style="color: var(--text); font-weight: 600; "><?php echo e($r['equipment_name']); ?></td>
                            <td style="color: var(--text); "><?php echo ucfirst($r['maintenance_type']); ?></td>
                            <td style="color: var(--text); "><?php echo ucfirst($r['status']); ?></td>
                            <td style="color: var(--text); "><?php echo e($r['performed_by'] ?? '—'); ?></td>
                            <td style="color: var(--text); font-weight:600; "><?php echo formatCurrency($r['cost'] ?? 0); ?></td>
                            <td>
                                <a href="maintenance.php?action=record-detail&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


