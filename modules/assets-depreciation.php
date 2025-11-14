<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

/**
 * Assets Depreciation View (lightweight)
 */

$pdo = getDBConnection();

// Load basic depreciation summary (graceful if tables missing)
$summary = ['assets' => 0, 'total_cost' => 0, 'accumulated' => 0, 'net_book' => 0];
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM assets");
    $summary['assets'] = (int)$stmt->fetchColumn();
    $stmt = $pdo->query("SELECT SUM(purchase_cost) FROM assets");
    $summary['total_cost'] = (float)($stmt->fetchColumn() ?: 0);
    // Optional tables/columns may not exist; wrap in try
    try {
        $stmt = $pdo->query("SELECT SUM(accumulated_depreciation) FROM asset_depreciation");
        $summary['accumulated'] = (float)($stmt->fetchColumn() ?: 0);
    } catch (PDOException $e) {}
    $summary['net_book'] = max(0, $summary['total_cost'] - $summary['accumulated']);
} catch (PDOException $e) {
    $tableMissing = true;
}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">💰 Depreciation</h2>
        <a href="assets.php?action=reports" class="btn btn-outline">View Reports</a>
    </div>
    <?php if (!empty($tableMissing)): ?>
        <div style="margin-top: 12px; padding: 15px; border:1px solid #ff9800; border-radius: 6px; background: #fff3cd;">
            <strong>⚠️ Assets tables not found.</strong>
            <p style="margin: 10px 0 0 0; color: #856404;">
                Please run the <code>maintenance_assets_inventory_migration.sql</code> migration to create the required tables.
            </p>
            <a href="database-migrations.php" class="btn btn-primary" style="margin-top: 10px;">
                <i class="fas fa-database"></i> Go to Database Migrations →
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Total Assets</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo number_format($summary['assets']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Total Cost</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo formatCurrency($summary['total_cost']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Accumulated Depreciation</div>
        <div style="font-size:24px; font-weight:700; color: var(--text); "><?php echo formatCurrency($summary['accumulated']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Net Book Value</div>
        <div style="font-size:24px; font-weight:700; color: var(--text); "><?php echo formatCurrency($summary['net_book']); ?></div>
    </div>
    
</div>

<div class="dashboard-card">
    <h3 style="margin-bottom: 12px; color: var(--text);">Recent Depreciation Entries</h3>
    <?php
    $entries = [];
    try {
        $stmt = $pdo->query("SELECT * FROM asset_depreciation ORDER BY depreciation_date DESC LIMIT 50");
        $entries = $stmt->fetchAll();
    } catch (PDOException $e) {}
    ?>
    <?php if (empty($entries)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 30px;">No depreciation entries found.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Asset</th>
                        <th>Method</th>
                        <th>Amount</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($entries as $row): ?>
                        <tr>
                            <td style="color: var(--text);"><?php echo formatDate($row['depreciation_date'] ?? $row['created_at'] ?? ''); ?></td>
                            <td style="color: var(--text);"><?php echo e($row['asset_name'] ?? '—'); ?></td>
                            <td style="color: var(--text);"><?php echo e($row['method'] ?? '—'); ?></td>
                            <td style="color: var(--text); font-weight: 600; "><?php echo formatCurrency($row['amount'] ?? 0); ?></td>
                            <td style="color: var(--secondary); font-size: 12px; "><?php echo e($row['notes'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


