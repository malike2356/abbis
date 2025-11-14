<?php
$page_title = 'Client Health & NPS';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
$auth->requireAuth();
$auth->requirePermission('crm.access');
$pdo = getDBConnection();
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>💚 Client Health & NPS</h1>
        <p>Lightweight satisfaction, health scoring, and follow-up triggers</p>
    </div>

    <div class="dashboard-card">
        <h2>Recent Jobs – Collect Feedback</h2>
        <p style="color: var(--secondary);">Placeholder. After each job, send a 2‑question survey and compute health.</p>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Client</th><th>Report</th><th>Health</th><th>Actions</th></tr></thead>
                <tbody>
                    <tr><td>Sample Client</td><td>RIG001-2025-001</td><td>—</td><td><button class="btn btn-sm btn-outline">Request Feedback</button></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


