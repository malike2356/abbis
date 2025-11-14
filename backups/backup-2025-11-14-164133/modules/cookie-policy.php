<?php
$page_title = 'Cookie Policy';
require_once '../config/app.php';
require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Cookie Policy</h1>
        <p>Last updated: <?php echo date('Y'); ?></p>
    </div>

    <div class="card" style="padding:16px;">
        <p>We use cookies to provide core functionality (authentication, session security), remember preferences (theme), and for analytics where enabled.</p>
        <h3>Types of cookies</h3>
        <ul>
            <li>Strictly necessary: login session, CSRF tokens</li>
            <li>Preference: theme mode (light/dark/system)</li>
            <li>Analytics/Performance: only if you enable external analytics</li>
        </ul>
        <h3>Your choices</h3>
        <p>You can manage consent via the cookie banner and your browser settings. Blocking cookies may impact functionality.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


