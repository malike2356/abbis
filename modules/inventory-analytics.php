<?php
/**
 * Inventory Analytics View
 * Show inventory analytics and trends
 */
$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Get analytics data
$analytics = [
    'total_value' => 0,
    'total_items' => 0,
    'transactions_count' => 0,
    'low_stock_count' => 0,
    'transaction_trends' => [],
    'material_usage' => []
];

try {
    // Total inventory value
    $stmt = $pdo->query("SELECT SUM(total_value) FROM materials_inventory");
    $analytics['total_value'] = $stmt->fetchColumn() ?: 0;
    
    // Total trackable items
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE is_trackable = 1");
    $analytics['total_items'] = $stmt->fetchColumn() ?: 0;
    
    // Transactions this month
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_transactions 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
    ");
    $analytics['transactions_count'] = $stmt->fetchColumn() ?: 0;
    
    // Low stock count
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM materials m
        LEFT JOIN materials_inventory mi ON m.material_type = mi.material_type
        WHERE m.is_trackable = 1 
          AND mi.quantity_remaining <= m.reorder_level 
          AND m.reorder_level > 0
    ");
    $analytics['low_stock_count'] = $stmt->fetchColumn() ?: 0;
    
    // Transaction trends (last 7 days)
    $stmt = $pdo->query("
        SELECT DATE(created_at) as date, 
               transaction_type,
               COUNT(*) as count,
               SUM(total_cost) as total_cost
        FROM inventory_transactions
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at), transaction_type
        ORDER BY date ASC
    ");
    $analytics['transaction_trends'] = $stmt->fetchAll();
    
    // Material usage (top 10)
    $stmt = $pdo->query("
        SELECT m.material_name, m.material_type,
               SUM(ABS(it.quantity)) as total_used,
               SUM(it.total_cost) as total_cost
        FROM inventory_transactions it
        LEFT JOIN materials m ON it.material_id = m.id
        WHERE it.transaction_type IN ('usage', 'sale')
        AND it.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY it.material_id
        ORDER BY total_used DESC
        LIMIT 10
    ");
    $analytics['material_usage'] = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tables might not exist yet
}
?>

<!-- Analytics Overview -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">💰</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Inventory Value</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo formatCurrency($analytics['total_value']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">📦</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Trackable Items</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($analytics['total_items']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">💳</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Transactions (This Month)</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($analytics['transactions_count']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">⚠️</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Low Stock Alerts</h3>
                <div style="font-size: 28px; font-weight: 700; color: <?php echo $analytics['low_stock_count'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                    <?php echo number_format($analytics['low_stock_count']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Trends & Material Usage -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
    <!-- Recent Transaction Trends -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">📊 Recent Transaction Trends (7 Days)</h2>
        <?php if (empty($analytics['transaction_trends'])): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No transaction data for the last 7 days
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php 
                $grouped = [];
                foreach ($analytics['transaction_trends'] as $trend) {
                    $date = $trend['date'];
                    if (!isset($grouped[$date])) {
                        $grouped[$date] = ['count' => 0, 'cost' => 0];
                    }
                    $grouped[$date]['count'] += $trend['count'];
                    $grouped[$date]['cost'] += $trend['total_cost'];
                }
                foreach ($grouped as $date => $data): ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="color: var(--text);"><?php echo formatDate($date, 'M j, Y'); ?></strong>
                                <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                    <?php echo number_format($data['count']); ?> transactions
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 16px; font-weight: 700; color: var(--text);">
                                    <?php echo formatCurrency($data['cost']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Top Material Usage -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">📈 Top Material Usage (30 Days)</h2>
        <?php if (empty($analytics['material_usage'])): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No usage data available
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($analytics['material_usage'] as $index => $usage): ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <span style="
                                    display: inline-block;
                                    width: 24px;
                                    height: 24px;
                                    line-height: 24px;
                                    text-align: center;
                                    background: var(--primary);
                                    color: white;
                                    border-radius: 50%;
                                    font-size: 12px;
                                    font-weight: 700;
                                    margin-right: 8px;
                                ">
                                    <?php echo $index + 1; ?>
                                </span>
                                <strong style="color: var(--text);">
                                    <?php echo e($usage['material_name'] ?? ucfirst(str_replace('_', ' ', $usage['material_type'] ?? 'Unknown'))); ?>
                                </strong>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 8px;">
                            <div>
                                <div style="font-size: 11px; color: var(--secondary);">Quantity Used</div>
                                <div style="font-size: 16px; font-weight: 700; color: var(--text);">
                                    <?php echo number_format($usage['total_used'], 2); ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: var(--secondary);">Total Cost</div>
                                <div style="font-size: 16px; font-weight: 700; color: var(--text);">
                                    <?php echo formatCurrency($usage['total_cost'] ?? 0); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Additional Analytics Cards -->
<div class="dashboard-card">
    <h2 style="margin-bottom: 20px; color: var(--text);">📊 Inventory Insights</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div style="padding: 20px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
            <div style="font-size: 14px; color: var(--secondary); margin-bottom: 8px;">Average Transaction Value</div>
            <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                <?php
                $avgValue = $analytics['transactions_count'] > 0 
                    ? $analytics['total_value'] / $analytics['transactions_count'] 
                    : 0;
                echo formatCurrency($avgValue);
                ?>
            </div>
        </div>
        
        <div style="padding: 20px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px;">
            <div style="font-size: 14px; color: var(--secondary); margin-bottom: 8px;">Stock Health</div>
            <div style="font-size: 24px; font-weight: 700; color: <?php echo $analytics['low_stock_count'] > 0 ? 'var(--warning)' : 'var(--success)'; ?>;">
                <?php 
                $healthPercent = $analytics['total_items'] > 0 
                    ? (($analytics['total_items'] - $analytics['low_stock_count']) / $analytics['total_items']) * 100 
                    : 100;
                echo number_format($healthPercent, 1); 
                ?>%
            </div>
            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                <?php echo number_format($analytics['total_items'] - $analytics['low_stock_count']); ?> / <?php echo number_format($analytics['total_items']); ?> items well stocked
            </div>
        </div>
    </div>
</div>

