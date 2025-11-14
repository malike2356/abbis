<?php
$page_title = 'Maintenance Digital Twin';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
$auth->requireAuth();
$auth->requirePermission('resources.access');
$pdo = getDBConnection();
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🧩 Maintenance Digital Twin</h1>
        <p>Project part wear/use from job data; suggest just‑in‑time ordering</p>
    </div>

    <div class="dashboard-card">
        <h2>Asset State (Placeholder)</h2>
        <p style="color: var(--secondary);">Simple model to be added. Will estimate time‑to‑maintenance windows.</p>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Asset</th><th>State</th><th>TtM (days)</th><th>Action</th></tr></thead>
                <tbody>
                    <tr><td>RIG001</td><td>Normal</td><td>14</td><td><button class="btn btn-sm btn-outline">Create Schedule</button></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


