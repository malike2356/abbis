<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

/**
 * Stock Levels View
 * Display current inventory levels and stock status
 */
$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Get materials with stock levels
try {
    $stmt = $pdo->query("
        SELECT m.*, 
               mi.quantity_received,
               mi.quantity_used,
               mi.quantity_remaining,
               mi.unit_cost,
               mi.total_value,
               mi.last_updated,
               CASE 
                   WHEN mi.quantity_remaining <= m.reorder_level AND m.reorder_level > 0 THEN 'low'
                   WHEN mi.quantity_remaining <= (m.reorder_level * 1.5) AND m.reorder_level > 0 THEN 'warning'
                   ELSE 'good'
               END as stock_status
        FROM materials m
        LEFT JOIN materials_inventory mi ON m.material_type = mi.material_type
        WHERE m.is_trackable = 1 OR mi.material_type IS NOT NULL
        ORDER BY stock_status DESC, m.material_name ASC
    ");
    $stockItems = $stmt->fetchAll();
} catch (PDOException $e) {
    // If advanced fields don't exist, fall back to basic materials_inventory
    try {
        $stmt = $pdo->query("SELECT * FROM materials_inventory ORDER BY material_type");
        $stockItems = $stmt->fetchAll();
    } catch (PDOException $e2) {
        $stockItems = [];
    }
}

// Calculate summary
$totalItems = count($stockItems);
$lowStockCount = 0;
$totalValue = 0;
foreach ($stockItems as $item) {
    if (isset($item['stock_status']) && $item['stock_status'] === 'low') {
        $lowStockCount++;
    }
    $totalValue += floatval($item['total_value'] ?? 0);
}
?>

<div class="dashboard-card" style="margin-bottom: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
        <h2 style="margin: 0; color: var(--text);">📦 Stock Levels</h2>
        <div style="display: flex; gap: 10px;">
            <span style="
                padding: 6px 12px;
                background: var(--bg);
                border: 1px solid var(--border);
                border-radius: 6px;
                font-size: 13px;
                color: var(--text);
            ">
                Total Items: <strong><?php echo number_format($totalItems); ?></strong>
            </span>
            <span style="
                padding: 6px 12px;
                background: rgba(239, 68, 68, 0.1);
                border: 1px solid var(--danger);
                border-radius: 6px;
                font-size: 13px;
                color: var(--danger);
            ">
                Low Stock: <strong><?php echo number_format($lowStockCount); ?></strong>
            </span>
            <span style="
                padding: 6px 12px;
                background: rgba(14, 165, 233, 0.1);
                border: 1px solid var(--primary);
                border-radius: 6px;
                font-size: 13px;
                color: var(--primary);
            ">
                Total Value: <strong><?php echo formatCurrency($totalValue); ?></strong>
            </span>
        </div>
    </div>
    
    <?php if (empty($stockItems)): ?>
        <div style="text-align: center; padding: 60px; color: var(--secondary);">
            <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
            <p>No stock items found.</p>
            <p style="font-size: 13px; margin-top: 8px;">Start by adding materials to your inventory.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Received</th>
                        <th>Used</th>
                        <th>Remaining</th>
                        <th>Reorder Level</th>
                        <th>Unit Cost</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stockItems as $item): ?>
                        <?php
                        $stockRemaining = $item['quantity_remaining'] ?? $item['quantity_remaining'] ?? 0;
                        $reorderLevel = $item['reorder_level'] ?? 0;
                        $stockStatus = $item['stock_status'] ?? 'good';
                        if ($stockStatus === 'good' && $reorderLevel > 0 && $stockRemaining <= $reorderLevel) {
                            $stockStatus = 'low';
                        }
                        
                        $statusColors = [
                            'low' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'fg' => '#ef4444', 'text' => '⚠️ Low Stock'],
                            'warning' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'fg' => '#f59e0b', 'text' => '⚡ Warning'],
                            'good' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'fg' => '#10b981', 'text' => '✅ In Stock']
                        ];
                        $status = $statusColors[$stockStatus] ?? $statusColors['good'];
                        ?>
                        <tr style="<?php echo $stockStatus === 'low' ? 'background: rgba(239, 68, 68, 0.05);' : ''; ?>">
                            <td>
                                <strong style="color: var(--text);">
                                    <?php echo e($item['material_name'] ?? ucfirst(str_replace('_', ' ', $item['material_type'] ?? 'Unknown'))); ?>
                                </strong>
                                <?php if (!empty($item['category'])): ?>
                                    <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                        <?php echo e($item['category']); ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--text);"><?php echo number_format($item['quantity_received'] ?? 0); ?></td>
                            <td style="color: var(--text);"><?php echo number_format($item['quantity_used'] ?? 0); ?></td>
                            <td>
                                <strong style="color: <?php echo $stockStatus === 'low' ? 'var(--danger)' : 'var(--text)'; ?>;">
                                    <?php echo number_format($stockRemaining); ?>
                                </strong>
                            </td>
                            <td style="color: var(--secondary);">
                                <?php echo $reorderLevel > 0 ? number_format($reorderLevel) : 'N/A'; ?>
                            </td>
                            <td style="color: var(--text);">
                                <?php echo formatCurrency($item['unit_cost'] ?? 0); ?>
                            </td>
                            <td>
                                <strong style="color: var(--text);">
                                    <?php echo formatCurrency($item['total_value'] ?? 0); ?>
                                </strong>
                            </td>
                            <td>
                                <span style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 11px;
                                    font-weight: 600;
                                    background: <?php echo $status['bg']; ?>;
                                    color: <?php echo $status['fg']; ?>;
                                ">
                                    <?php echo $status['text']; ?>
                                </span>
                            </td>
                            <td style="color: var(--secondary); font-size: 12px;">
                                <?php echo $item['last_updated'] ? formatDate($item['last_updated']) : 'N/A'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="dashboard-card">
    <h3 style="margin-bottom: 15px; color: var(--text);">Quick Actions</h3>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?action=transactions&add=1" class="btn btn-primary">➕ Record Transaction</a>
        <a href="?action=reorder" class="btn btn-outline">⚠️ View Reorder Alerts</a>
        <a href="?action=analytics" class="btn btn-outline">📈 View Analytics</a>
    </div>
</div>

