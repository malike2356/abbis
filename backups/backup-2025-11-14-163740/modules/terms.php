<?php
$page_title = 'Terms of Service';
require_once '../config/app.php';
require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Terms of Service</h1>
        <p>Last updated: <?php echo date('Y'); ?></p>
    </div>

    <div class="card" style="padding:16px;">
        <h3>1. Acceptance of Terms</h3>
        <p>By using ABBIS, you agree to these Terms and all applicable policies referenced here.</p>

        <h3>2. Use of Service</h3>
        <p>You are responsible for your account and for complying with applicable laws, including Ghana DPA (Act 843) and GDPR where applicable.</p>

        <h3>3. Data & Privacy</h3>
        <p>Use is governed by our Privacy and Cookie Policies. You must obtain appropriate consents from data subjects where required.</p>

        <h3>4. Security</h3>
        <p>You must keep credentials secure. We implement reasonable safeguards; however no system is 100% secure.</p>

        <h3>5. Availability</h3>
        <p>We aim for high availability; details in the SLA. Planned maintenance may cause temporary unavailability.</p>

        <h3>6. Integrations</h3>
        <p>Third-party integrations (Zoho, Wazuh, etc.) may have their own terms; you are responsible for compliance.</p>

        <h3>7. Liability</h3>
        <p>To the maximum extent permitted by law, ABBIS is provided “as is” and our liability is limited.</p>

        <h3>8. Changes</h3>
        <p>We may update these Terms. Continued use after updates constitutes acceptance.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


