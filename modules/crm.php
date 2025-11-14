<?php
/**
 * Integrated CRM System
 * Client management, follow-ups, emails, and activities
 */
$page_title = 'CRM - Client Relationship Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/email.php';
require_once '../includes/tab-navigation.php';
require_once '../includes/module-router.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

// Check for standalone mode BEFORE including header (for customer statement printing)
// If standalone, include the statement page directly and exit
if (isset($_GET['action']) && $_GET['action'] === 'customer-statement' && isset($_GET['standalone'])) {
    require_once __DIR__ . '/crm-customer-statement.php';
    exit;
}

$pdo = getDBConnection();
// Default action should be dashboard (overview first)
$action = getCurrentAction('dashboard');
$clientId = intval($_GET['client_id'] ?? 0);

if ($action === 'complaints') {
    redirect('complaints.php');
}

// Define tabs - Dashboard first for overview, then main functionality
$tabs = [
    'dashboard' => '📊 Dashboard',
    'clients' => '👥 Clients',
    'followups' => '📅 Follow-ups',
    'complaints' => '⚠️ Complaints',
    'quote-requests' => '💰 Quote Requests',
    'rig-requests' => '🚛 Rig Requests',
    'emails' => '📧 Emails',
    'templates' => '📝 Templates',
];

// Check if CRM tables exist, show notice if not
try {
    $pdo->query("SELECT 1 FROM client_followups LIMIT 1");
} catch (PDOException $e) {
    echo '<div class="alert alert-warning" style="margin: 20px;">';
    echo '<strong>⚠️ CRM Migration Required:</strong> Run <code>database/crm_migration.sql</code> to enable CRM features.';
    echo '</div>';
}

// Get current user
$currentUserId = $_SESSION['user_id'];

require_once '../includes/header.php';
?>

<script>
// Apply saved theme ASAP to avoid mismatch and ensure toggle works
(function(){
    try {
        var saved = localStorage.getItem('abbis-theme');
        if (saved) {
            document.documentElement.setAttribute('data-theme', saved);
            document.body && document.body.setAttribute('data-theme', saved);
        }
    } catch(e) {}
})();

// Fallback: ensure toggle is bound if main.js didn't bind yet
document.addEventListener('DOMContentLoaded', function(){
    try {
        if (window.abbisApp && typeof window.abbisApp.initializeTheme === 'function') {
            window.abbisApp.initializeTheme();
        } else {
            var toggle = document.querySelector('.theme-toggle');
            if (toggle && !toggle.dataset.bound) {
                toggle.addEventListener('click', function(){
                    var current = document.documentElement.getAttribute('data-theme') || 'light';
                    var next = current === 'dark' ? 'light' : 'dark';
                    document.documentElement.setAttribute('data-theme', next);
                    document.body && document.body.setAttribute('data-theme', next);
                    try { localStorage.setItem('abbis-theme', next); } catch(e) {}
                    // Persist to session for PHP if endpoint exists
                    fetch('../api/save-theme.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'theme='+encodeURIComponent(next)}).catch(function(){});
                });
                toggle.dataset.bound = '1';
            }
        }
    } catch(e) {}
});
</script>

<style>
    /* Ensure CRM respects theme variables */
    .page-header h1 { color: var(--text); }
    .page-header p { color: var(--secondary); }
    .config-tabs .tabs { background: transparent; }
    .config-tabs .tabs .tab { background: transparent; color: var(--text); border-color: var(--border); }
    .config-tabs .tabs .tab:hover { background: rgba(14,165,233,0.08); color: var(--primary); }
    .config-tabs .tabs .tab.active { border-bottom: 2px solid var(--primary) !important; color: var(--primary) !important; }
    .dashboard-card { background: var(--card); color: var(--text); border: 1px solid var(--border); }
    .data-table th { background: var(--bg); color: var(--text); }
    .data-table td { color: var(--text); }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>👥 Clients & CRM</h1>
        <p>Manage clients, relationships, follow-ups, and communications</p>
    </div>
    
    <?php
    // Use tab navigation helper
    require_once '../includes/tab-navigation.php';
    echo renderTabNavigation($tabs, $action);
    ?>
    
    <?php
    // Route to appropriate view - dashboard is the default (overview first)
    $routes = [
        'dashboard' => __DIR__ . '/crm-dashboard.php',
        'clients' => __DIR__ . '/crm-clients.php',
        'followups' => __DIR__ . '/crm-followups.php',
        'quote-requests' => __DIR__ . '/requests.php',
        'rig-requests' => __DIR__ . '/requests.php',
        'emails' => __DIR__ . '/crm-emails.php',
        'templates' => __DIR__ . '/crm-templates.php',
        'client-detail' => __DIR__ . '/crm-client-detail.php',
        'customer-statement' => __DIR__ . '/crm-customer-statement.php',
    ];
    
    // Add health route if feature enabled
    if (function_exists('isFeatureEnabled') && isFeatureEnabled('crm_health')) {
        $tabs['health'] = '💚 Health';
        $routes['health'] = __DIR__ . '/crm-health.php';
    }
    
    // Use ModuleRouter
    // Pass variables from current scope to make them available in included files
    // For requests pages, also pass type parameter
    $vars = [
        'auth' => $auth,
        'pdo' => $pdo,
        'action' => $action,
        'clientId' => $clientId,
        'currentUserId' => $currentUserId,
        'page_title' => $page_title
    ];
    
    // Set type parameter for requests pages
    if ($action === 'quote-requests') {
        $_GET['type'] = 'quote';
        $vars['type'] = 'quote';
    } elseif ($action === 'rig-requests') {
        $_GET['type'] = 'rig';
        $vars['type'] = 'rig';
    }
    
    try {
        ModuleRouter::route('crm', $routes, 'dashboard', 'action', $vars);
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error loading CRM view: ' . htmlspecialchars($e->getMessage()) . '</div>';
        error_log("CRM routing error: " . $e->getMessage());
        // Show error details in development
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<pre style="margin-top: 10px; padding: 10px; background: var(--card); border: 1px solid var(--border); border-radius: 4px; font-size: 12px;">';
            echo htmlspecialchars($e->getTraceAsString());
            echo '</pre>';
        }
    }
    ?>
</div>

<?php require_once '../includes/footer.php'; ?>

<script>
// Ensure theme is applied on CRM pages after load
window.addEventListener('DOMContentLoaded', function() {
    try {
        if (window.abbisApp && typeof window.abbisApp.initializeTheme === 'function') {
            window.abbisApp.initializeTheme();
        }
    } catch (e) { /* noop */ }
});
</script>

