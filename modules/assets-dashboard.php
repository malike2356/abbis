<?php
/**
 * Assets Dashboard
 * Overview of assets and their status
 */
$pdo = getDBConnection();

// Get asset statistics
$stats = [
    'total_assets' => 0,
    'active' => 0,
    'in_maintenance' => 0,
    'total_value' => 0,
    'depreciation_this_month' => 0,
    'assets_by_type' => []
];

try {
    // Total assets
    $stmt = $pdo->query("SELECT COUNT(*) FROM assets");
    $stats['total_assets'] = $stmt->fetchColumn() ?: 0;
    
    // Active assets
    $stmt = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'active'");
    $stats['active'] = $stmt->fetchColumn() ?: 0;
    
    // In maintenance
    $stmt = $pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'maintenance'");
    $stats['in_maintenance'] = $stmt->fetchColumn() ?: 0;
    
    // Total value
    $stmt = $pdo->query("SELECT SUM(current_value) FROM assets WHERE status = 'active'");
    $stats['total_value'] = $stmt->fetchColumn() ?: 0;
    
    // Depreciation this month
    $stmt = $pdo->query("
        SELECT SUM(depreciation_amount) 
        FROM asset_depreciation 
        WHERE MONTH(depreciation_date) = MONTH(CURDATE()) 
        AND YEAR(depreciation_date) = YEAR(CURDATE())
    ");
    $stats['depreciation_this_month'] = $stmt->fetchColumn() ?: 0;
    
    // Assets by type
    $stmt = $pdo->query("
        SELECT asset_type, COUNT(*) as count 
        FROM assets 
        GROUP BY asset_type
    ");
    $stats['assets_by_type'] = $stmt->fetchAll();
} catch (PDOException $e) {
    // Tables might not exist yet
}

// Get recent assets
try {
    $stmt = $pdo->query("
        SELECT a.*, u.full_name as assigned_to_name
        FROM assets a
        LEFT JOIN users u ON a.assigned_to = u.id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recentAssets = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentAssets = [];
}

// Get assets needing attention
try {
    $stmt = $pdo->query("
        SELECT a.*
        FROM assets a
        WHERE a.status IN ('maintenance', 'poor', 'critical')
           OR (a.warranty_expiry IS NOT NULL AND a.warranty_expiry < DATE_ADD(CURDATE(), INTERVAL 30 DAY))
           OR (a.insurance_expiry IS NOT NULL AND a.insurance_expiry < DATE_ADD(CURDATE(), INTERVAL 30 DAY))
        ORDER BY 
            CASE a.status
                WHEN 'critical' THEN 1
                WHEN 'poor' THEN 2
                WHEN 'maintenance' THEN 3
                ELSE 4
            END,
            a.warranty_expiry ASC,
            a.insurance_expiry ASC
        LIMIT 5
    ");
    $needsAttention = $stmt->fetchAll();
} catch (PDOException $e) {
    $needsAttention = [];
}
?>

<!-- Asset Statistics -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">🏭</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Assets</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($stats['total_assets']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">✅</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Active</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--success);">
                    <?php echo number_format($stats['active']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">🔧</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">In Maintenance</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--warning);">
                    <?php echo number_format($stats['in_maintenance']); ?>
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
                    <?php echo formatCurrency($stats['total_value']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">📉</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Depreciation (This Month)</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo formatCurrency($stats['depreciation_this_month']); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Assets by Type Chart -->
<?php if (!empty($stats['assets_by_type'])): ?>
<div class="dashboard-card" style="margin-bottom: 30px;">
    <h2 style="margin-bottom: 20px; color: var(--text);">Assets by Type</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
        <?php foreach ($stats['assets_by_type'] as $type): ?>
            <div style="
                padding: 16px;
                border: 1px solid var(--border);
                border-radius: 8px;
                background: var(--bg);
                text-align: center;
            ">
                <div style="font-size: 28px; font-weight: 700; color: var(--primary); margin-bottom: 8px;">
                    <?php echo number_format($type['count']); ?>
                </div>
                <div style="font-size: 13px; color: var(--secondary); text-transform: capitalize;">
                    <?php echo str_replace('_', ' ', $type['asset_type']); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Recent Assets & Needs Attention -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
    <!-- Recent Assets -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">Recent Assets</h2>
        <?php if (empty($recentAssets)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No assets registered yet. <a href="?action=add" style="color: var(--primary);">Register your first asset</a>
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($recentAssets as $asset): ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($asset['asset_name']); ?></strong>
                                <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                    <?php echo str_replace('_', ' ', ucfirst($asset['asset_type'])); ?>
                                    <?php if ($asset['location']): ?>
                                        • 📍 <?php echo e($asset['location']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <span style="
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 11px;
                                font-weight: 600;
                                background: rgba(14, 165, 233, 0.1);
                                color: var(--primary);
                            ">
                                <?php echo ucfirst($asset['status']); ?>
                            </span>
                        </div>
                        <div style="font-size: 12px; color: var(--secondary);">
                            Value: <?php echo formatCurrency($asset['current_value']); ?>
                        </div>
                        <a href="?action=asset-detail&asset_id=<?php echo $asset['id']; ?>" 
                           class="btn btn-sm btn-outline" 
                           style="margin-top: 8px; display: inline-block;">
                            View Details →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Needs Attention -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">⚠️ Needs Attention</h2>
        <?php if (empty($needsAttention)): ?>
            <p style="text-align: center; padding: 40px; color: var(--success);">
                ✅ All assets are in good condition!
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($needsAttention as $asset): ?>
                    <?php
                    $alertReasons = [];
                    if (in_array($asset['status'], ['maintenance', 'poor', 'critical'])) {
                        $alertReasons[] = 'Status: ' . ucfirst($asset['status']);
                    }
                    if ($asset['warranty_expiry'] && strtotime($asset['warranty_expiry']) < strtotime('+30 days')) {
                        $days = floor((strtotime($asset['warranty_expiry']) - time()) / 86400);
                        $alertReasons[] = 'Warranty expires ' . ($days < 0 ? 'expired' : "in $days days");
                    }
                    if ($asset['insurance_expiry'] && strtotime($asset['insurance_expiry']) < strtotime('+30 days')) {
                        $days = floor((strtotime($asset['insurance_expiry']) - time()) / 86400);
                        $alertReasons[] = 'Insurance expires ' . ($days < 0 ? 'expired' : "in $days days");
                    }
                    $alertColor = ($asset['status'] === 'critical' || strpos(implode(' ', $alertReasons), 'expired') !== false) ? 'var(--danger)' : 'var(--warning)';
                    ?>
                    <div style="
                        padding: 12px;
                        border: 2px solid <?php echo $alertColor; ?>;
                        border-radius: 8px;
                        background: rgba(239, 68, 68, 0.05);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($asset['asset_name']); ?></strong>
                                <div style="font-size: 11px; color: <?php echo $alertColor; ?>; margin-top: 4px; font-weight: 600;">
                                    ⚠️ <?php echo implode(', ', $alertReasons); ?>
                                </div>
                            </div>
                        </div>
                        <a href="?action=asset-detail&asset_id=<?php echo $asset['id']; ?>" 
                           class="btn btn-sm btn-outline" 
                           style="margin-top: 8px; display: inline-block;">
                            Review →
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
        <a href="?action=add" class="btn btn-primary" style="text-align: center;">
            ➕ Register New Asset
        </a>
        <a href="?action=assets" class="btn btn-outline" style="text-align: center;">
            🏭 View All Assets
        </a>
        <a href="?action=depreciation" class="btn btn-outline" style="text-align: center;">
            💰 Depreciation Report
        </a>
        <a href="?action=reports" class="btn btn-outline" style="text-align: center;">
            📄 Generate Reports
        </a>
    </div>
</div>

