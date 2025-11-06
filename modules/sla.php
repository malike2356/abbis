<?php
$page_title = 'Service Level Agreement (SLA)';
require_once '../config/app.php';
require_once '../includes/header.php';
?>

<div class="container">
    <div class="page-header">
        <h1>Service Level Agreement (SLA)</h1>
        <p>Support scope, response times, and maintenance windows</p>
    </div>

    <div class="card" style="padding:16px;">
        <h3>Availability</h3>
        <p>Target availability is 99.5% monthly (excluding planned maintenance).</p>
        <h3>Support</h3>
        <ul>
            <li>Critical: response within 4 business hours</li>
            <li>High: response within 1 business day</li>
            <li>Normal: response within 2 business days</li>
        </ul>
        <h3>Maintenance</h3>
        <p>Planned maintenance may occur outside business hours with prior notice where applicable.</p>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>


