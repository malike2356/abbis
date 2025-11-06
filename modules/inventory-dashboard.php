<?php
/**
 * Advanced Inventory Dashboard
 * Overview of inventory transactions and stock levels
 */
$pdo = getDBConnection();
require_once '../includes/migration-helpers.php';

// Get inventory statistics
$stats = [
    'total_materials' => 0,
    'low_stock_items' => 0,
    'transactions_today' => 0,
    'total_inventory_value' => 0,
    'reorder_alerts' => 0
];

try {
    // Total materials
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE is_trackable = 1");
    $stats['total_materials'] = $stmt->fetchColumn() ?: 0;
    
    // Low stock items (checking reorder_level if available)
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM materials 
        WHERE is_trackable = 1 
        AND quantity_remaining <= reorder_level 
        AND reorder_level > 0
    ");
    $stats['low_stock_items'] = $stmt->fetchColumn() ?: 0;
    
    // Transactions today
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM inventory_transactions 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stats['transactions_today'] = $stmt->fetchColumn() ?: 0;
    
    // Total inventory value (from materials_inventory)
    $stmt = $pdo->query("SELECT SUM(total_value) FROM materials_inventory");
    $stats['total_inventory_value'] = $stmt->fetchColumn() ?: 0;
    
    // Reorder alerts
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM materials 
        WHERE is_trackable = 1 
        AND quantity_remaining <= reorder_level 
        AND reorder_level > 0
    ");
    $stats['reorder_alerts'] = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Tables might not exist yet or columns might not exist
}

// Get recent transactions
try {
    $stmt = $pdo->query("
        SELECT it.*, m.material_name, u.full_name as created_by_name
        FROM inventory_transactions it
        LEFT JOIN materials m ON it.material_id = m.id
        LEFT JOIN users u ON it.created_by = u.id
        ORDER BY it.created_at DESC
        LIMIT 10
    ");
    $recentTransactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentTransactions = [];
}

// Get low stock items
try {
    $stmt = $pdo->query("
        SELECT m.*, 
               (SELECT quantity_remaining FROM materials_inventory WHERE material_type = m.material_type LIMIT 1) as stock_level
        FROM materials m
        WHERE m.is_trackable = 1 
        AND (SELECT quantity_remaining FROM materials_inventory WHERE material_type = m.material_type LIMIT 1) <= m.reorder_level
        AND m.reorder_level > 0
        ORDER BY stock_level ASC
        LIMIT 5
    ");
    $lowStockItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $lowStockItems = [];
}
?>

<!-- Inventory Statistics -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">📦</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Materials</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($stats['total_materials']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">⚠️</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Low Stock Items</h3>
                <div style="font-size: 28px; font-weight: 700; color: <?php echo $stats['low_stock_items'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                    <?php echo number_format($stats['low_stock_items']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">💳</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Transactions Today</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($stats['transactions_today']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">💰</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Value</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo formatCurrency($stats['total_inventory_value']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">🔔</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Reorder Alerts</h3>
                <div style="font-size: 28px; font-weight: 700; color: <?php echo $stats['reorder_alerts'] > 0 ? 'var(--danger)' : 'var(--success)'; ?>;">
                    <?php echo number_format($stats['reorder_alerts']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Transactions & Low Stock -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
    <!-- Recent Transactions -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">Recent Transactions</h2>
        <?php if (empty($recentTransactions)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No transactions yet. <a href="?action=transactions" style="color: var(--primary);">Create your first transaction</a>
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($recentTransactions as $transaction): ?>
                    <?php
                    $typeColors = [
                        'purchase' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'fg' => '#10b981'],
                        'sale' => ['bg' => 'rgba(59, 130, 246, 0.1)', 'fg' => '#3b82f6'],
                        'usage' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'fg' => '#f59e0b'],
                        'adjustment' => ['bg' => 'rgba(139, 92, 246, 0.1)', 'fg' => '#8b5cf6'],
                        'transfer' => ['bg' => 'rgba(236, 72, 153, 0.1)', 'fg' => '#ec4899'],
                    ];
                    $typeStyle = $typeColors[$transaction['transaction_type']] ?? ['bg' => 'rgba(100, 116, 139, 0.1)', 'fg' => '#64748b'];
                    ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($transaction['material_name'] ?? 'N/A'); ?></strong>
                                <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                    <?php echo e($transaction['created_by_name'] ?? 'System'); ?>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span style="
                                    padding: 4px 8px;
                                    border-radius: 4px;
                                    font-size: 11px;
                                    font-weight: 600;
                                    background: <?php echo $typeStyle['bg']; ?>;
                                    color: <?php echo $typeStyle['fg']; ?>;
                                ">
                                    <?php echo ucfirst($transaction['transaction_type']); ?>
                                </span>
                                <div style="font-size: 13px; font-weight: 600; color: var(--text); margin-top: 4px;">
                                    <?php echo $transaction['quantity'] > 0 ? '+' : ''; ?><?php echo number_format($transaction['quantity'], 2); ?>
                                </div>
                            </div>
                        </div>
                        <div style="font-size: 12px; color: var(--secondary);">
                            📅 <?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?>
                            <?php if ($transaction['total_cost']): ?>
                                • <?php echo formatCurrency($transaction['total_cost']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Low Stock Alerts -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">⚠️ Low Stock Alerts</h2>
        <?php if (empty($lowStockItems)): ?>
            <p style="text-align: center; padding: 40px; color: var(--success);">
                ✅ All items are well stocked!
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($lowStockItems as $item): ?>
                    <div style="
                        padding: 12px;
                        border: 2px solid var(--danger);
                        border-radius: 8px;
                        background: rgba(239, 68, 68, 0.05);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($item['material_name'] ?? $item['material_type']); ?></strong>
                                <div style="font-size: 11px; color: var(--danger); margin-top: 4px; font-weight: 600;">
                                    ⚠️ Stock: <?php echo number_format($item['stock_level'] ?? 0); ?> 
                                    (Reorder: <?php echo number_format($item['reorder_level']); ?>)
                                </div>
                            </div>
                        </div>
                        <a href="?action=stock" class="btn btn-sm btn-outline" style="margin-top: 8px; display: inline-block;">
                            Reorder →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="dashboard-card">
    <h2 style="margin-bottom: 20px; color: var(--text);">Quick Actions</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="?action=transactions&add=1" class="btn btn-primary" style="text-align: center;">
            ➕ New Transaction
        </a>
        <a href="?action=stock" class="btn btn-outline" style="text-align: center;">
            📦 View Stock Levels
        </a>
        <a href="?action=reorder" class="btn btn-outline" style="text-align: center;">
            ⚠️ Reorder Alerts
        </a>
        <a href="?action=analytics" class="btn btn-outline" style="text-align: center;">
            📈 View Analytics
        </a>
    </div>
</div>

