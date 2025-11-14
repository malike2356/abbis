<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

/**
 * Assets List View (lightweight)
 */

$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Load assets (graceful if table missing)
$tableMissing = false;
$assets = [];
try {
    $stmt = $pdo->query("SELECT id, asset_name, category, serial_number, status, purchase_date, purchase_cost, created_at FROM assets ORDER BY created_at DESC LIMIT 200");
    $assets = $stmt->fetchAll();
} catch (PDOException $e) {
    $assets = [];
    $tableMissing = true;
}

// Explicitly check if table exists
if (!tableExists($pdo, 'assets')) {
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">🏭 Assets</h2>
        <a href="assets.php?action=add" class="btn btn-primary">➕ Add Asset</a>
    </div>
    <p style="margin: 8px 0 0 0; color: var(--secondary);">Track company assets with status and key details.</p>
    <?php if ($tableMissing): ?>
        <?php 
        $missingTables = checkTablesExist($pdo, ['assets']);
        echo showMigrationWarning($missingTables, getMigrationFileForModule('assets'), 'Asset Management');
        ?>
    <?php endif; ?>
</div>

<div class="dashboard-card">
    <?php if (empty($assets)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 40px;">No assets yet. Add your first one.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Serial</th>
                        <th>Status</th>
                        <th>Purchase Date</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assets as $a): ?>
                        <tr>
                            <td style="color: var(--text); font-weight: 600;">
                                <a href="assets.php?action=asset-detail&id=<?php echo $a['id']; ?>" style="color: var(--primary); text-decoration: none;">
                                    <?php echo e($a['asset_name']); ?>
                                </a>
                            </td>
                            <td style="color: var(--text);"><?php echo e($a['category'] ?? '—'); ?></td>
                            <td style="color: var(--secondary); font-size: 12px;"><?php echo e($a['serial_number'] ?? '—'); ?></td>
                            <td>
                                <?php 
                                $statusColors = [
                                    'active' => ['bg' => 'rgba(16,185,129,0.15)', 'fg' => '#10b981'],
                                    'maintenance' => ['bg' => 'rgba(245,158,11,0.15)', 'fg' => '#f59e0b'],
                                    'inactive' => ['bg' => 'rgba(100,116,139,0.15)', 'fg' => '#64748b'],
                                    'disposed' => ['bg' => 'rgba(239,68,68,0.15)', 'fg' => '#ef4444'],
                                ];
                                $st = strtolower($a['status'] ?? 'active');
                                $style = $statusColors[$st] ?? $statusColors['active'];
                                ?>
                                <span style="padding:4px 8px; border-radius:4px; font-size:12px; background: <?php echo $style['bg']; ?>; color: <?php echo $style['fg']; ?>;">
                                    <?php echo ucfirst($st); ?>
                                </span>
                            </td>
                            <td style="color: var(--text);">
                                <?php echo !empty($a['purchase_date']) ? formatDate($a['purchase_date']) : '—'; ?>
                            </td>
                            <td style="color: var(--text); font-weight: 600;">
                                <?php echo isset($a['purchase_cost']) ? formatCurrency($a['purchase_cost']) : '—'; ?>
                            </td>
                            <td>
                                <a href="assets.php?action=edit&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-outline">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
</div>


