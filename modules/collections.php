<?php
$page_title = 'Collections Assistant';
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
        <h1>📅 Collections Assistant</h1>
        <p>Predict late payers and propose optimal collection dates</p>
    </div>

    <div class="dashboard-card">
        <h2>Predicted Risk (Placeholder)</h2>
        <p style="color: var(--secondary);">Baseline heuristics and AI integration to be added.</p>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Client</th><th>Risk</th><th>Suggested Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <tr><td>Sample Client</td><td>Medium</td><td><?php echo e(date('Y-m-d', strtotime('+7 days'))); ?></td><td><button class="btn btn-sm btn-outline">Schedule Reminder</button></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


