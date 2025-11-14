<?php
$page_title = 'Policies & Legal';
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);
require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>📜 Policies & Legal</h1>
        <p>Terms, policies, and legal documents</p>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h2>Terms of Service</h2>
            <p>Rules for using the ABBIS system.</p>
            <a href="terms.php" class="btn btn-primary">View Terms →</a>
        </div>
        <div class="dashboard-card">
            <h2>Privacy Policy</h2>
            <p>How we collect and use data.</p>
            <a href="privacy-policy.php" class="btn btn-primary">View Privacy Policy →</a>
        </div>
        <div class="dashboard-card">
            <h2>Cookie Policy</h2>
            <p>Cookies used and your choices.</p>
            <a href="cookie-policy.php" class="btn btn-primary">View Cookie Policy →</a>
        </div>
        <div class="dashboard-card">
            <h2>Data Processing Agreement</h2>
            <p>Controller–processor terms (GDPR/Act 843).</p>
            <a href="dpa.php" class="btn btn-primary">View DPA →</a>
        </div>
        <div class="dashboard-card">
            <h2>Service Level Agreement</h2>
            <p>Support and uptime targets.</p>
            <a href="sla.php" class="btn btn-primary">View SLA →</a>
        </div>
        <div class="dashboard-card">
            <h2>Contracts</h2>
            <p>Store and manage signed agreements.</p>
            <a href="contracts.php" class="btn btn-primary">Open Contracts →</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


