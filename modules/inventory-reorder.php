<?php
/**
 * Reorder Alerts View
 * Show items that need to be reordered
 */
$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Get low stock items
try {
    $stmt = $pdo->query("
        SELECT m.*, 
               mi.quantity_received,
               mi.quantity_used,
               mi.quantity_remaining,
               mi.unit_cost,
               mi.total_value,
               m.reorder_level,
               m.reorder_quantity,
               m.supplier,
               m.supplier_contact,
               (m.reorder_level - mi.quantity_remaining) as units_needed
        FROM materials m
        LEFT JOIN materials_inventory mi ON m.material_type = mi.material_type
        WHERE m.is_trackable = 1 
          AND mi.quantity_remaining <= m.reorder_level 
          AND m.reorder_level > 0
        ORDER BY (mi.quantity_remaining / NULLIF(m.reorder_level, 0)) ASC, m.material_name ASC
    ");
    $lowStockItems = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback to basic check
    try {
        $stmt = $pdo->query("
            SELECT *, 
                   quantity_remaining,
                   (SELECT reorder_level FROM materials WHERE material_type = materials_inventory.material_type LIMIT 1) as reorder_level
            FROM materials_inventory
            WHERE quantity_remaining <= (SELECT reorder_level FROM materials WHERE material_type = materials_inventory.material_type LIMIT 1)
        ");
        $lowStockItems = $stmt->fetchAll();
    } catch (PDOException $e2) {
        $lowStockItems = [];
    }
}

// Calculate summary
$totalAlerts = count($lowStockItems);
$criticalCount = 0; // Items at 0 or below reorder level
$estimatedCost = 0;
foreach ($lowStockItems as $item) {
    if (($item['quantity_remaining'] ?? 0) <= 0) {
        $criticalCount++;
    }
    $unitsNeeded = max(0, ($item['reorder_level'] ?? 0) - ($item['quantity_remaining'] ?? 0));
    $estimatedCost += $unitsNeeded * floatval($item['unit_cost'] ?? 0);
}
?>

<div class="dashboard-card" style="margin-bottom: 30px; background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); border-left: 4px solid var(--danger);">
    <div style="display: flex; align-items: start; gap: 15px;">
        <div style="font-size: 48px;">⚠️</div>
        <div style="flex: 1;">
            <h2 style="margin: 0 0 10px 0; color: var(--danger);">Reorder Alerts</h2>
            <p style="margin: 0; color: var(--text); line-height: 1.6;">
                <?php if ($totalAlerts > 0): ?>
                    <strong><?php echo number_format($totalAlerts); ?></strong> item(s) need to be reordered.
                    <?php if ($criticalCount > 0): ?>
                        <strong style="color: var(--danger);"><?php echo number_format($criticalCount); ?></strong> are critically low (out of stock).
                    <?php endif; ?>
                    Estimated reorder cost: <strong><?php echo formatCurrency($estimatedCost); ?></strong>
                <?php else: ?>
                    ✅ All items are well stocked! No reorders needed at this time.
                <?php endif; ?>
            </p>
        </div>
    </div>
</div>

<?php if (empty($lowStockItems)): ?>
    <div class="dashboard-card">
        <div style="text-align: center; padding: 60px; color: var(--success);">
            <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
            <h3 style="margin: 0 0 10px 0; color: var(--success);">All Stock Levels Healthy</h3>
            <p style="color: var(--secondary);">No items require reordering at this time.</p>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">Items Requiring Reorder</h2>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Current Stock</th>
                        <th>Reorder Level</th>
                        <th>Units Needed</th>
                        <th>Recommended Order</th>
                        <th>Unit Cost</th>
                        <th>Estimated Cost</th>
                        <th>Supplier</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStockItems as $item): ?>
                        <?php
                        $currentStock = $item['quantity_remaining'] ?? 0;
                        $reorderLevel = $item['reorder_level'] ?? 0;
                        $unitsNeeded = max(0, $reorderLevel - $currentStock);
                        $recommendedOrder = $item['reorder_quantity'] ?? max($unitsNeeded, $reorderLevel);
                        $estimatedItemCost = $recommendedOrder * floatval($item['unit_cost'] ?? 0);
                        $isCritical = $currentStock <= 0;
                        ?>
                        <tr style="<?php echo $isCritical ? 'background: rgba(239, 68, 68, 0.1);' : ''; ?>">
                            <td>
                                <strong style="color: var(--text);">
                                    <?php echo e($item['material_name'] ?? ucfirst(str_replace('_', ' ', $item['material_type'] ?? 'Unknown'))); ?>
                                </strong>
                                <?php if ($isCritical): ?>
                                    <span style="
                                        padding: 2px 6px;
                                        background: var(--danger);
                                        color: white;
                                        border-radius: 3px;
                                        font-size: 10px;
                                        font-weight: 600;
                                        margin-left: 8px;
                                    ">
                                        CRITICAL
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: <?php echo $isCritical ? 'var(--danger)' : 'var(--warning)'; ?>;">
                                    <?php echo number_format($currentStock); ?>
                                </strong>
                            </td>
                            <td style="color: var(--text);"><?php echo number_format($reorderLevel); ?></td>
                            <td>
                                <strong style="color: var(--danger);">
                                    <?php echo number_format($unitsNeeded); ?>
                                </strong>
                            </td>
                            <td style="color: var(--text); font-weight: 600;">
                                <?php echo number_format($recommendedOrder); ?>
                            </td>
                            <td style="color: var(--text);">
                                <?php echo formatCurrency($item['unit_cost'] ?? 0); ?>
                            </td>
                            <td>
                                <strong style="color: var(--text);">
                                    <?php echo formatCurrency($estimatedItemCost); ?>
                                </strong>
                            </td>
                            <td style="color: var(--secondary); font-size: 13px;">
                                <?php if (!empty($item['supplier'])): ?>
                                    <?php echo e($item['supplier']); ?>
                                    <?php if (!empty($item['supplier_contact'])): ?>
                                        <br><small><?php echo e($item['supplier_contact']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--secondary);">Not set</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?action=transactions&add=1&material_id=<?php echo $item['id'] ?? 0; ?>&type=purchase" 
                                   class="btn btn-sm btn-primary">
                                    Order
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Summary -->
    <div class="dashboard-card">
        <h3 style="margin-bottom: 15px; color: var(--text);">Reorder Summary</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 12px; color: var(--secondary); margin-bottom: 4px;">Total Items to Reorder</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo number_format($totalAlerts); ?>
                </div>
            </div>
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 12px; color: var(--secondary); margin-bottom: 4px;">Critical (Out of Stock)</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--danger);">
                    <?php echo number_format($criticalCount); ?>
                </div>
            </div>
            <div style="padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 12px; color: var(--secondary); margin-bottom: 4px;">Estimated Total Cost</div>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo formatCurrency($estimatedCost); ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

