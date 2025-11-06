<?php
/**
 * Accounting Hub - General Accounting System
 */
$page_title = 'Accounting';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
$action = $_GET['action'] ?? 'dashboard';

// Feature toggle gate
if (function_exists('isFeatureEnabled')) {
    try {
        if (!isFeatureEnabled('accounting')) {
            require_once '../includes/header.php';
            echo '<div class="container-fluid"><div class="alert alert-warning" style="margin:20px;">Accounting feature is disabled. Enable it in System → Feature Management.</div></div>';
            require_once '../includes/footer.php';
            exit;
        }
    } catch (Throwable $e) { /* ignore */ }
}

// Ensure core tables exist; auto-initialize by running SQL migration if missing
$needsMigration = false;
try {
    $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
} catch (PDOException $e) {
    $needsMigration = true;
    // Attempt auto-initialize from migration file
    $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
    if (file_exists($migrationFile) && is_readable($migrationFile)) {
        $sql = file_get_contents($migrationFile);
        if ($sql) {
            try {
                // Split on semicolons that end statements
                $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
                foreach ($stmts as $stmt) {
                    if ($stmt !== '') { $pdo->exec($stmt); }
                }
                // Recheck
                $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
                $needsMigration = false;
            } catch (PDOException $e2) {
                $needsMigration = true;
            }
        }
    }
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <nav aria-label="Breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <a href="financial.php" style="color: var(--primary); text-decoration: none;">Finance</a> <span style="opacity:0.6;">→</span> <span>Accounting</span>
        </div>
    </nav>
    <div class="page-header">
        <h1>📘 General Accounting</h1>
        <p>Standard double-entry accounting integrated with ABBIS</p>
    </div>

    <?php if (!empty($needsMigration)): ?>
        <div class="alert alert-warning">⚠️ Accounting not initialized. Run <code>database/accounting_migration.sql</code>.</div>
    <?php endif; ?>

    <div class="config-tabs" style="margin-bottom: 24px;">
        <div class="tabs">
            <button class="tab <?php echo $action==='dashboard'?'active':''; ?>" onclick="location.href='?action=dashboard'">📊 Dashboard</button>
            <button class="tab <?php echo $action==='accounts'?'active':''; ?>" onclick="location.href='?action=accounts'">📚 Chart of Accounts</button>
            <button class="tab <?php echo $action==='journal'?'active':''; ?>" onclick="location.href='?action=journal'">🧾 Journal</button>
            <button class="tab <?php echo $action==='ledger'?'active':''; ?>" onclick="location.href='?action=ledger'">📑 Ledgers</button>
            <button class="tab <?php echo $action==='trial'?'active':''; ?>" onclick="location.href='?action=trial'">🧮 Trial Balance</button>
            <button class="tab <?php echo $action==='pl'?'active':''; ?>" onclick="location.href='?action=pl'">📈 P&amp;L</button>
            <button class="tab <?php echo $action==='bs'?'active':''; ?>" onclick="location.href='?action=bs'">🏦 Balance Sheet</button>
            <button class="tab <?php echo $action==='integrations'?'active':''; ?>" onclick="location.href='?action=integrations'">🔌 Integrations</button>
            <button class="tab <?php echo $action==='settings'?'active':''; ?>" onclick="location.href='?action=settings'">⚙️ Settings</button>
        </div>
    </div>

    <?php
    try {
        switch ($action) {
            case 'accounts': include 'accounting-accounts.php'; break;
            case 'journal': include 'accounting-journal.php'; break;
            case 'ledger': include 'accounting-ledger.php'; break;
            case 'trial': include 'accounting-trial-balance.php'; break;
            case 'pl': include 'accounting-pl.php'; break;
            case 'bs': include 'accounting-balance-sheet.php'; break;
            case 'integrations': include 'accounting-integrations.php'; break;
            case 'settings': include 'accounting-settings.php'; break;
            case 'dashboard':
            default: include 'accounting-dashboard.php';
        }
    } catch (Exception $e) {
        echo '<div class="alert alert-danger">Error: '. e($e->getMessage()) .'</div>';
    }
    ?>
</div>

<?php require_once '../includes/footer.php'; ?>


