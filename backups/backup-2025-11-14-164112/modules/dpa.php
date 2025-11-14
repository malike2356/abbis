<?php
$page_title = 'Data Processing Agreement (DPA)';
require_once '../config/app.php';
require_once '../includes/functions.php';
require_once '../includes/header.php';

// Get company config from database
$pdo = getDBConnection();
$companyName = 'Your Company';
$companyAddress = '';

$stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
$stmt->execute(['company_name']);
$result = $stmt->fetch();
if ($result) {
    $companyName = $result['config_value'] ?: 'Your Company';
}

$stmt->execute(['company_address']);
$result = $stmt->fetch();
if ($result) {
    $companyAddress = $result['config_value'] ?: '';
}
?>

<div class="container">
    <div class="page-header">
        <h1>Data Processing Agreement (DPA)</h1>
        <p>Controller–Processor agreement compliant with Ghana DPA (Act 843) and GDPR</p>
    </div>

    <div class="card" style="padding:16px;">
        <p>This DPA forms part of the Terms between <strong><?php echo htmlspecialchars($companyName); ?></strong> (Controller) at <?php echo htmlspecialchars($companyAddress); ?> and ABBIS (Processor).</p>
        <h3>1. Subject Matter</h3>
        <p>Processing of personal data in the course of using ABBIS modules (CRM, Field Reports, HR, etc.).</p>
        <h3>2. Obligations</h3>
        <ul>
            <li>Processor follows documented instructions from Controller.</li>
            <li>Implements appropriate technical/organizational measures (see Security Assessment).</li>
            <li>Assists with data subject rights and incident notifications.</li>
        </ul>
        <h3>3. Sub-processors</h3>
        <p>Integrations (Zoho, email/SMS) may act as sub-processors subject to Controller approval.</p>
        <h3>4. Data Transfers</h3>
        <p>Cross-border transfers follow applicable safeguards where required.</p>
        <h3>5. Termination/Deletion</h3>
        <p>Upon termination, Processor deletes or returns personal data per Controller instruction.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


