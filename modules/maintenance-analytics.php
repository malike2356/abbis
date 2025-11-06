<?php
/**
 * Maintenance Analytics (lightweight)
 */

$pdo = getDBConnection();

$kpis = ['records' => 0, 'scheduled' => 0, 'completed' => 0, 'cost_month' => 0];
try {
    $kpis['records'] = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_records")->fetchColumn();
    $kpis['scheduled'] = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status = 'scheduled'")->fetchColumn();
    $kpis['completed'] = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status = 'completed'")->fetchColumn();
    $stmt = $pdo->query("SELECT SUM(cost) FROM maintenance_records WHERE MONTH(performed_at)=MONTH(CURDATE()) AND YEAR(performed_at)=YEAR(CURDATE())");
    $kpis['cost_month'] = (float)($stmt->fetchColumn() ?: 0);
} catch (PDOException $e) {}
?>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;">
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Total Records</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['records']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Scheduled</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['scheduled']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">Completed</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['completed']); ?></div>
    </div>
    <div class="dashboard-card">
        <div style="font-size:12px; color: var(--secondary);">This Month's Cost</div>
        <div style="font-size:24px; font-weight:700; color: var(--text);"><?php echo formatCurrency($kpis['cost_month']); ?></div>
    </div>
</div>

<div class="dashboard-card">
    <h3 style="margin-bottom: 12px; color: var(--text);">Recent Activity</h3>
    <?php
    $recent = [];
    try {
        $stmt = $pdo->query("SELECT equipment_name, status, performed_at FROM maintenance_records ORDER BY performed_at DESC LIMIT 10");
        $recent = $stmt->fetchAll();
    } catch (PDOException $e) {}
    ?>
    <?php if (empty($recent)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 30px;">No recent activity.</p>
    <?php else: ?>
        <ul style="list-style:none; margin:0; padding:0; display:grid; gap: 10px;">
            <?php foreach ($recent as $r): ?>
                <li style="padding:12px; border:1px solid var(--border); border-radius:8px; background: var(--bg); display:flex; justify-content: space-between;">
                    <span style="color: var(--text); font-weight:600; "><?php echo e($r['equipment_name']); ?></span>
                    <span style="color: var(--secondary); font-size:12px; "><?php echo ucfirst($r['status']); ?> · <?php echo formatDate($r['performed_at']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>


