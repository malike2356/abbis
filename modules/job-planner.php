<?php
$page_title = 'Smart Job Planner';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
$auth->requireAuth();
$pdo = getDBConnection();
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🗓️ Smart Job Planner</h1>
        <p>Auto-build a 2–4 week schedule balancing rigs, crew, distance, and cash flow</p>
    </div>

    <div class="dashboard-card">
        <form method="get" style="display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap: 12px; align-items:end;">
            <div>
                <label class="form-label">Horizon</label>
                <select name="horizon" class="form-control">
                    <option value="2w">2 weeks</option>
                    <option value="4w">4 weeks</option>
                </select>
            </div>
            <div>
                <label class="form-label">Start Date</label>
                <input type="date" name="start" class="form-control" value="<?php echo e(date('Y-m-d')); ?>">
            </div>
            <div>
                <label class="form-label">Objective</label>
                <select name="objective" class="form-control">
                    <option value="balance">Balanced</option>
                    <option value="profit">Maximize Profit</option>
                    <option value="distance">Minimize Travel</option>
                </select>
            </div>
            <div>
                <button class="btn btn-primary">Build Schedule</button>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <h2>Proposed Schedule (Preview)</h2>
        <p style="color: var(--secondary);">This is a placeholder. Optimization logic will be added.</p>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Rig</th><th>Client / Site</th><th>Distance</th><th>Estimated Profit</th><th>Actions</th></tr></thead>
                <tbody>
                    <tr><td><?php echo e(date('Y-m-d')); ?></td><td>RIG001</td><td>Sample Client – Sample Site</td><td>—</td><td>—</td><td><button class="btn btn-sm btn-outline">Adjust</button></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


