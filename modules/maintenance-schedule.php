<?php
/**
 * Maintenance Schedule View (lightweight)
 */

$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Load upcoming schedules
$tableMissing = false;
$schedules = [];
try {
    // Join with assets and maintenance_types to get readable names
    $stmt = $pdo->query("
        SELECT 
            ms.id,
            ms.next_maintenance_date as scheduled_date,
            ms.notes,
            a.asset_name as equipment_name,
            mt.type_name as maintenance_type,
            ms.is_active
        FROM maintenance_schedules ms
        LEFT JOIN assets a ON ms.asset_id = a.id
        LEFT JOIN maintenance_types mt ON ms.maintenance_type_id = mt.id
        WHERE ms.is_active = 1
        ORDER BY ms.next_maintenance_date ASC 
        LIMIT 200
    ");
    $schedules = $stmt->fetchAll();
} catch (PDOException $e) {
    $schedules = [];
    $tableMissing = true;
}

// Explicitly check if table exists
if (!tableExists($pdo, 'maintenance_schedules')) {
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">📅 Maintenance Schedule</h2>
        <a href="maintenance.php?action=add" class="btn btn-primary">➕ Add Schedule</a>
    </div>
    <?php if ($tableMissing): ?>
        <div style="margin-top: 12px; padding: 15px; border:1px solid #ff9800; border-radius: 6px; background: #fff3cd;">
            <strong>⚠️ Maintenance schedule table not found.</strong>
            <p style="margin: 10px 0 0 0; color: #856404;">
                Please run the <code>maintenance_assets_inventory_migration.sql</code> migration to create the required tables.
            </p>
            <a href="database-migrations.php" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-database"></i> Go to Database Migrations →
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="dashboard-card">
    <?php if (empty($schedules)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 40px;">No scheduled maintenance.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Equipment</th>
                        <th>Type</th>
                        <th>Assigned To</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $row): ?>
                        <tr>
                            <td style="color: var(--text); "><?php echo formatDate($row['scheduled_date']); ?></td>
                            <td style="color: var(--text); font-weight: 600; "><?php echo e($row['equipment_name'] ?? 'N/A'); ?></td>
                            <td style="color: var(--text); "><?php echo e($row['maintenance_type'] ?? 'N/A'); ?></td>
                            <td style="color: var(--text); ">—</td>
                            <td style="color: var(--secondary); font-size: 12px; "><?php echo e($row['notes'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


