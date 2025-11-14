<?php
/**
 * AI Assistant Panel
 * Available to all authenticated users on all pages
 */

if (!isset($auth)) {
    require_once __DIR__ . '/auth.php';
}

// Allow all authenticated users to see the panel
// Permission is checked at the API level for security
if (!isset($_SESSION['user_id']) || !$auth->isLoggedIn()) {
    return;
}

if (!function_exists('e')) {
    require_once __DIR__ . '/helpers.php';
}

$isModuleContext = $_SESSION['is_module'] ?? false;
// Determine API path based on current page location
$currentPage = $_SERVER['PHP_SELF'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

// Check if we're in a module directory
if (strpos($currentPage, '/modules/') !== false || 
    strpos($requestUri, '/modules/') !== false || 
    $isModuleContext) {
    $apiPath = '../api/ai-insights.php';
} else {
    $apiPath = 'api/ai-insights.php';
}

$entityType = $aiContext['entity_type'] ?? '';
$entityId = $aiContext['entity_id'] ?? '';
$entityLabel = $aiContext['entity_label'] ?? '';

$quickPrompts = $aiQuickPrompts ?? [
    'Give me today\'s top three priorities',
    'Who is our biggest client?',
    'What\'s our financial health?',
    'Show me recent field reports',
];
?>

<div 
    class="ai-assistant-shell" 
    data-ai-assistant="true"
    data-api-path="<?php echo e($apiPath); ?>"
    <?php if ($entityType): ?>
        data-entity-type="<?php echo e($entityType); ?>"
    <?php endif; ?>
    <?php if ($entityId): ?>
        data-entity-id="<?php echo e($entityId); ?>"
    <?php endif; ?>
    <?php if ($entityLabel): ?>
        data-entity-label="<?php echo e($entityLabel); ?>"
    <?php endif; ?>
>
    <button class="ai-assistant-toggle" type="button" title="Toggle ABBIS Assistant">
        <span class="ai-assistant-toggle-icon">🧠</span>
        <span class="ai-assistant-toggle-label">Assistant</span>
    </button>

    <div class="ai-assistant-panel" role="dialog" aria-modal="false" aria-label="ABBIS Assistant">
        <div class="ai-assistant-header">
            <div>
                <h2>ABBIS Assistant</h2>
                <p class="ai-assistant-subtitle">
                    Get insights, summaries, and next steps in real time.
                </p>
            </div>
            <button class="ai-assistant-close" type="button" title="Close assistant">×</button>
        </div>

        <div class="ai-assistant-context">
            <div class="ai-assistant-status">
                <span class="ai-assistant-status-dot"></span>
                <span class="ai-assistant-status-label">Ready</span>
            </div>
            <div class="ai-assistant-entity" data-ai-context-label>
                <?php echo $entityLabel ? e($entityLabel) : 'No context selected'; ?>
            </div>
            <button class="ai-assistant-context-clear" type="button" title="Clear context">Clear</button>
        </div>

        <div class="ai-assistant-quick">
            <?php foreach ($quickPrompts as $prompt): ?>
                <button type="button" class="ai-assistant-quick-btn" data-prompt="<?php echo e($prompt); ?>">
                    <?php echo e($prompt); ?>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="ai-assistant-messages" data-ai-messages role="log" aria-live="polite">
            <div class="ai-assistant-message ai-assistant-message--assistant">
                <div class="ai-assistant-avatar">🤖</div>
                <div class="ai-assistant-bubble">
                    Hi! I can help summarise trends, surface risks, and recommend next actions. Ask away or pick a quick prompt.
                </div>
            </div>
        </div>

        <form class="ai-assistant-form" data-ai-form>
            <label for="aiAssistantInput" class="visually-hidden">Message ABBIS Assistant</label>
            <textarea 
                id="aiAssistantInput" 
                name="message" 
                rows="2"
                placeholder="Ask anything about today’s operations, clients, or field reports..." 
                data-ai-input
                required
            ></textarea>
            <div class="ai-assistant-form-actions">
                <button type="submit" class="btn btn-primary" data-ai-send>Send</button>
                <button type="button" class="btn btn-outline" data-ai-clear>Clear</button>
            </div>
        </form>
    </div>
</div>

