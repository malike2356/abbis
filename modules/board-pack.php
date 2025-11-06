<?php
$page_title = 'Executive Export Pack';
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
        <h1>📦 Executive Export Pack</h1>
        <p>One‑click monthly Board Pack (P&L, Balance Sheet, Cash Flow, KPIs)</p>
    </div>

    <div class="dashboard-card">
        <form method="get" style="display:flex; gap:12px; align-items:end;">
            <div>
                <label class="form-label">Period</label>
                <input type="month" name="period" class="form-control" value="<?php echo e(date('Y-m')); ?>">
            </div>
            <div>
                <button class="btn btn-primary">Generate Pack</button>
            </div>
        </form>
    </div>

    <div class="dashboard-card">
        <h2>Downloads (Placeholder)</h2>
        <ul>
            <li><a href="#">📄 Board Pack (PDF)</a></li>
            <li><a href="#">📊 Board Pack (XLSX)</a></li>
        </ul>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


