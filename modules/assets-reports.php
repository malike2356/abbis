<?php
/**
 * Asset Reports View (lightweight)
 */

$pdo = getDBConnection();

// Simple KPIs
$kpis = ['assets' => 0, 'active' => 0, 'maintenance' => 0, 'disposed' => 0];
try {
    $kpis['assets'] = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();
    $kpis['active'] = (int)$pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'active'")->fetchColumn();
    $kpis['maintenance'] = (int)$pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'maintenance'")->fetchColumn();
    $kpis['disposed'] = (int)$pdo->query("SELECT COUNT(*) FROM assets WHERE status = 'disposed'")->fetchColumn();
} catch (PDOException $e) {}
?>

<div class="dashboard-card" style="margin-bottom: 20px;">
    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap: wrap; gap: 12px;">
        <h2 style="margin:0; color: var(--text);">📄 Asset Reports</h2>
        <a href="assets.php?action=assets" class="btn btn-outline">Back to Assets</a>
    </div>
    <p style="margin: 8px 0 0 0; color: var(--secondary);">Quick overview of asset status. Export options coming soon.</p>
    <div style="margin-top: 12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 12px;">
        <div style="padding:14px; border:1px solid var(--border); border-radius:8px; background: var(--bg);">
            <div style="font-size:12px; color: var(--secondary);">Total Assets</div>
            <div style="font-size:22px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['assets']); ?></div>
        </div>
        <div style="padding:14px; border:1px solid var(--border); border-radius:8px; background: var(--bg);">
            <div style="font-size:12px; color: var(--secondary);">Active</div>
            <div style="font-size:22px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['active']); ?></div>
        </div>
        <div style="padding:14px; border:1px solid var(--border); border-radius:8px; background: var(--bg);">
            <div style="font-size:12px; color: var(--secondary);">Under Maintenance</div>
            <div style="font-size:22px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['maintenance']); ?></div>
        </div>
        <div style="padding:14px; border:1px solid var(--border); border-radius:8px; background: var(--bg);">
            <div style="font-size:12px; color: var(--secondary);">Disposed</div>
            <div style="font-size:22px; font-weight:700; color: var(--text);"><?php echo number_format($kpis['disposed']); ?></div>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <h3 style="margin-bottom: 12px; color: var(--text);">Recent Assets</h3>
    <?php
    $recent = [];
    try {
        $stmt = $pdo->query("SELECT id, asset_name, category, status, created_at FROM assets ORDER BY created_at DESC LIMIT 20");
        $recent = $stmt->fetchAll();
    } catch (PDOException $e) {}
    ?>
    <?php if (empty($recent)): ?>
        <p style="text-align:center; color: var(--secondary); padding: 30px;">No data to show.</p>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Added</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td style="color: var(--text); font-weight:600;">
                                <a href="assets.php?action=asset-detail&id=<?php echo $r['id']; ?>" style="color: var(--primary); text-decoration: none;">
                                    <?php echo e($r['asset_name']); ?>
                                </a>
                            </td>
                            <td style="color: var(--text); "><?php echo e($r['category'] ?? '—'); ?></td>
                            <td style="color: var(--text); "><?php echo ucfirst($r['status'] ?? 'active'); ?></td>
                            <td style="color: var(--secondary); font-size: 12px; "><?php echo formatDate($r['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>


