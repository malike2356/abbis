<?php
/**
 * Maintenance Dashboard
 * Overview of maintenance activities and statistics
 */
$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];

// Get maintenance statistics
$stats = [
    'total_records' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed_today' => 0,
    'total_cost' => 0,
    'downtime_hours' => 0
];

try {
    // Total maintenance records
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records");
    $stats['total_records'] = $stmt->fetchColumn() ?: 0;
    
    // Pending (logged + scheduled)
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status IN ('logged', 'scheduled')");
    $stats['pending'] = $stmt->fetchColumn() ?: 0;
    
    // In progress
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status = 'in_progress'");
    $stats['in_progress'] = $stmt->fetchColumn() ?: 0;
    
    // Completed today
    $stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status = 'completed' AND DATE(completed_date) = CURDATE()");
    $stats['completed_today'] = $stmt->fetchColumn() ?: 0;
    
    // Total cost (completed only)
    $stmt = $pdo->query("SELECT SUM(total_cost) FROM maintenance_records WHERE status = 'completed'");
    $stats['total_cost'] = $stmt->fetchColumn() ?: 0;
    
    // Total downtime
    $stmt = $pdo->query("SELECT SUM(downtime_hours) FROM maintenance_records WHERE status = 'completed'");
    $stats['downtime_hours'] = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    // Tables might not exist yet
}

// Get recent maintenance records
try {
    $stmt = $pdo->query("
        SELECT mr.*, a.asset_name, mt.type_name, u.full_name as performed_by_name
        FROM maintenance_records mr
        LEFT JOIN assets a ON mr.asset_id = a.id
        LEFT JOIN maintenance_types mt ON mr.maintenance_type_id = mt.id
        LEFT JOIN users u ON mr.performed_by = u.id
        ORDER BY mr.created_at DESC
        LIMIT 10
    ");
    $recentMaintenance = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentMaintenance = [];
}

// Get upcoming scheduled maintenance
try {
    $stmt = $pdo->query("
        SELECT mr.*, a.asset_name, mt.type_name
        FROM maintenance_records mr
        LEFT JOIN assets a ON mr.asset_id = a.id
        LEFT JOIN maintenance_types mt ON mr.maintenance_type_id = mt.id
        WHERE mr.status IN ('scheduled', 'logged') AND mr.scheduled_date >= NOW()
        ORDER BY mr.scheduled_date ASC
        LIMIT 5
    ");
    $upcoming = $stmt->fetchAll();
} catch (PDOException $e) {
    $upcoming = [];
}
?>

<!-- Maintenance Statistics -->
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">📋</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Records</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($stats['total_records']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">⏳</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Pending</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--warning);">
                    <?php echo number_format($stats['pending']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">🔧</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">In Progress</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--primary);">
                    <?php echo number_format($stats['in_progress']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">✅</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Completed Today</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--success);">
                    <?php echo number_format($stats['completed_today']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">💰</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Total Cost</h3>
                <div style="font-size: 24px; font-weight: 700; color: var(--text);">
                    <?php echo formatCurrency($stats['total_cost']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
            <span style="font-size: 32px;">⏱️</span>
            <div>
                <h3 style="margin: 0; font-size: 14px; color: var(--secondary);">Downtime</h3>
                <div style="font-size: 28px; font-weight: 700; color: var(--text);">
                    <?php echo number_format($stats['downtime_hours'], 1); ?> hrs
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Maintenance & Upcoming -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
    <!-- Recent Maintenance -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">Recent Maintenance</h2>
        <?php if (empty($recentMaintenance)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No maintenance records yet. <a href="?action=add" style="color: var(--primary);">Create your first maintenance record</a>
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($recentMaintenance as $maintenance): ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($maintenance['maintenance_code']); ?></strong>
                                <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                    <?php echo e($maintenance['asset_name'] ?? 'N/A'); ?> - <?php echo e($maintenance['type_name'] ?? 'N/A'); ?>
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
                                <?php echo ucfirst($maintenance['status']); ?>
                            </span>
                        </div>
                        <?php if ($maintenance['scheduled_date']): ?>
                            <div style="font-size: 12px; color: var(--secondary);">
                                📅 <?php echo date('M j, Y g:i A', strtotime($maintenance['scheduled_date'])); ?>
                            </div>
                        <?php endif; ?>
                        <a href="?action=record-detail&id=<?php echo $maintenance['id']; ?>" 
                           class="btn btn-sm btn-outline" 
                           style="margin-top: 8px; display: inline-block;">
                            View Details →
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Upcoming Maintenance -->
    <div class="dashboard-card">
        <h2 style="margin-bottom: 20px; color: var(--text);">Upcoming Maintenance</h2>
        <?php if (empty($upcoming)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No upcoming maintenance scheduled
            </p>
        <?php else: ?>
            <div style="display: grid; gap: 12px;">
                <?php foreach ($upcoming as $maintenance): ?>
                    <div style="
                        padding: 12px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: var(--bg);
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div>
                                <strong style="color: var(--text);"><?php echo e($maintenance['asset_name'] ?? 'N/A'); ?></strong>
                                <div style="font-size: 12px; color: var(--secondary); margin-top: 4px;">
                                    <?php echo e($maintenance['type_name'] ?? 'N/A'); ?>
                                </div>
                            </div>
                            <?php
                            $daysUntil = floor((strtotime($maintenance['scheduled_date']) - time()) / 86400);
                            $priorityColor = $daysUntil <= 1 ? 'var(--danger)' : ($daysUntil <= 7 ? 'var(--warning)' : 'var(--success)');
                            ?>
                            <span style="
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 11px;
                                font-weight: 600;
                                background: rgba(239, 68, 68, 0.1);
                                color: <?php echo $priorityColor; ?>;
                            ">
                                <?php echo $daysUntil < 0 ? 'Overdue' : ($daysUntil == 0 ? 'Today' : $daysUntil . ' days'); ?>
                            </span>
                        </div>
                        <div style="font-size: 12px; color: var(--secondary);">
                            📅 <?php echo date('M j, Y g:i A', strtotime($maintenance['scheduled_date'])); ?>
                        </div>
                        <a href="?action=record-detail&id=<?php echo $maintenance['id']; ?>" 
                           class="btn btn-sm btn-outline" 
                           style="margin-top: 8px; display: inline-block;">
                            View →
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
            ➕ Log New Maintenance
        </a>
        <a href="?action=records" class="btn btn-outline" style="text-align: center;">
            📋 View All Records
        </a>
        <a href="?action=schedule" class="btn btn-outline" style="text-align: center;">
            📅 Manage Schedule
        </a>
        <a href="?action=analytics" class="btn btn-outline" style="text-align: center;">
            📈 View Analytics
        </a>
    </div>
</div>

