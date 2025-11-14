<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
// Note: AI permission check removed - AI assistant is now available to all authenticated users
// Permission is still checked at the API level for security

$page_title = 'ABBIS Assistant';

$entityType = $_GET['entity_type'] ?? '';
$entityId = isset($_GET['entity_id']) ? (int) $_GET['entity_id'] : null;
$entityLabel = $_GET['entity_label'] ?? '';

// Set AI Assistant context before header is included
$aiContext = [
    'entity_type' => $entityType,
    'entity_id' => $entityId,
    'entity_label' => $entityLabel ?: 'Service Delivery Workspace',
];
$aiQuickPrompts = [
    'Give me today\'s top three priorities',
    'Explain anomalies in the latest field reports',
    'What should I brief leadership about tomorrow?',
];

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🧠 ABBIS Assistant Hub</h1>
        <p class="lead">
            Converse with ABBIS to distil insights, surface risks, and plan next steps across Service Delivery.
        </p>
    </div>

    <div class="card" style="padding: 24px; margin-bottom: 24px;">
        <h2 style="margin-top: 0;">How to get the most from ABBIS Assistant</h2>
        <ul style="line-height: 1.7;">
            <li>Ask for summaries (e.g. <em>"Summarise today’s rig performance"</em>).</li>
            <li>Request diagnostics (e.g. <em>"What caused the drop in net profit this month?"</em>).</li>
            <li>Plan next steps (e.g. <em>"Suggest actions to recover overdue collections"</em>).</li>
            <li>Provide context by selecting a specific client, rig request, or field report before asking.</li>
        </ul>
    </div>
</div>

<?php
// Note: AI assistant panel is already included in header.php for all pages
// The context variables were set before the header was included, so the panel
// will use the correct context for this page.

require_once '../includes/footer.php';
?>

