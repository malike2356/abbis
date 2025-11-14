<?php
/**
 * Feature Toggle Management
 * Enable/Disable system modules based on business needs
 */
$page_title = 'Feature Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
$message = null;
$messageType = null;

// Ensure feature_toggles table exists and seed defaults if empty
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feature_toggles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        feature_key VARCHAR(100) NOT NULL UNIQUE,
        feature_name VARCHAR(150) NOT NULL,
        category VARCHAR(50) NOT NULL DEFAULT 'operations',
        description VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(8) DEFAULT '📦',
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        is_core TINYINT(1) NOT NULL DEFAULT 0,
        menu_position INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $count = (int)$pdo->query("SELECT COUNT(*) FROM feature_toggles")->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position)
            VALUES 
            ('field_reports','Field Reports','core','Core field operations and reporting','📝',1,1,1),
            ('financial','Financial Management','core','Finance, Payroll, Loans management','💰',1,1,2),
            ('clients_crm','Clients & CRM','core','Client and relationship management','🤝',1,1,3),
            ('materials','Materials Management','core','Core materials and inventory management','📦',1,1,4),
            ('hr','Human Resources','core','HR Management System - Staff, Workers, and Stakeholders','👥',1,1,5),
            ('accounting','Accounting System','financial','Double-entry accounting, journal, ledger, balance sheet','📘',0,0,10),
            ('inventory_advanced','Advanced Inventory','operations','Transactions, stock, reorder, analytics','📋',0,0,20),
            ('assets','Asset Management','operations','Company assets and depreciation tracking','🏭',0,0,21),
            ('maintenance','Maintenance Management','operations','Equipment maintenance tracking and scheduling','🔧',0,0,22),
            ('api_keys','API Keys Management','business','Manage API access and authentication','🔑',1,0,50),
            ('monitoring_api','Monitoring API','business','Health checks and system metrics','📡',1,0,51),
            ('analytics_hub','Analytics & Insights','business','Advanced dashboards and trend analysis','📊',1,0,40),
            ('cms','CMS Website','business','Public website with ecommerce, blog, and quote requests','🌐',1,0,60)
        ");
        $seed->execute();
    }

    $chk = $pdo->prepare("SELECT id FROM feature_toggles WHERE feature_key = ? LIMIT 1");
    $chk->execute(['accounting']);
    if (!$chk->fetch()) {
        $ins = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position)
            VALUES ('accounting','Accounting','financial','General accounting (double-entry), reports, integrations','📘',0,0,4)");
        $ins->execute();
    }

    $featuresToEnsure = [
        // Core Features (already seeded, but ensure they exist)
        ['field_reports','Field Reports','core','Core field operations and reporting','📝',1,1,1],
        ['financial','Financial Management','core','Finance, Payroll, Loans management','💰',1,1,2],
        ['clients_crm','Clients & CRM','core','Client and relationship management','🤝',1,1,3],
        ['materials','Materials Management','core','Core materials and inventory management','📦',1,1,4],
        ['hr','Human Resources','core','HR Management System - Staff, Workers, and Stakeholders','👥',1,1,5],
        
        // Financial Features
        ['accounting','Accounting System','financial','Double-entry accounting, journal, ledger, balance sheet','📘',0,0,10],
        ['payroll','Payroll & Wages','financial','Process payroll runs and payslips','💼',0,0,11],
        ['loans_management','Loans Management','financial','Loan issuance, schedules, and repayments','🏦',0,0,12],
        ['collections','Collections Assistant','financial','Predict late payers and dunning letters','📅',0,0,13],
        ['debt_recovery','Debt Recovery','financial','Monitor outstanding debts and follow-ups','⚖️',0,0,14],
        
        // Operations Features
        ['inventory_advanced','Advanced Inventory','operations','Transactions, stock, reorder, analytics','📋',0,0,20],
        ['assets','Asset Management','operations','Company assets and depreciation tracking','🏭',0,0,21],
        ['maintenance','Maintenance Management','operations','Equipment maintenance tracking and scheduling','🔧',0,0,22],
        ['maint_digital_twin','Maintenance Digital Twin','operations','Asset state and time-to-maintenance predictions','🧩',0,0,23],
        ['job_planner','Smart Job Planner','operations','Auto-build drilling schedule and job planning','🗓️',0,0,24],
        ['suppliers','Supplier Intelligence','operations','Rank suppliers and draft purchase orders','🏭',0,0,25],
        ['rig_tracking','Rig Tracking','operations','Live rig allocation and telemetry tracking','🚜',0,0,26],
        ['map_integration','Map Integration','operations','Geospatial view of projects and assets','🗺️',0,0,27],
        ['pos','POS & Store Management','operations','Point of sale, catalog sync, store operations','🛍️',0,0,28],
        ['data_tools','Data Management Toolkit','operations','Backups, migrations, and data utilities','🗄️',0,0,29],
        ['database_migrations','Database Migrations','operations','Run and track schema migration scripts','🧱',0,0,30],
        
        // Business Intelligence Features
        ['analytics_hub','Analytics & Insights','business','Advanced dashboards and trend analysis','📊',1,0,40],
        ['crm_health','Client Health & NPS','business','Satisfaction scores and follow-ups','💚',0,0,41],
        ['board_pack','Executive Export Pack','business','One-click monthly board pack generation','📦',0,0,42],
        ['pos_analytics','POS Analytics','business','Store performance dashboards and margin analysis','📈',0,0,43],
        ['ai_assistant','AI Assistant','business','AI copilots and insight generation','🧠',0,0,44],
        ['ai_governance','AI Governance','business','Provider governance, limits, and audits','🛡️',0,0,45],
        
        // Integration Features
        ['api_keys','API Keys Management','business','Manage API access and authentication','🔑',1,0,50],
        ['monitoring_api','Monitoring API','business','Health checks and system metrics','📡',1,0,51],
        ['zoho','Zoho Integration','business','Zoho CRM/Inventory/Books/Payroll/HR integration','🧩',0,0,52],
        ['looker_studio','Looker Studio','business','External analytics data sources integration','📈',0,0,53],
        ['elk_stack','ELK Stack','business','Elasticsearch/Kibana integration for logging','🦌',0,0,54],
        ['social_auth','Social Login','business','OAuth sign-in for CMS and client portal','🔐',0,0,55],
        
        // Portal & Communication Features
        ['cms','CMS Website','business','Public website with ecommerce, blog, and quote requests','🌐',1,0,60],
        ['complaints_portal','Complaints Portal','business','Track and resolve client complaints','📣',0,0,61],
        ['recruitment','Recruitment Pipeline','business','Manage job postings and candidates','🧑‍💼',0,0,62],
        
        // Security & Compliance Features
        ['access_logs','Access Logs & Auditing','business','Monitor logins and privileged activity','📝',0,0,70]
    ];
    foreach ($featuresToEnsure as $f) {
        $chk = $pdo->prepare("SELECT id FROM feature_toggles WHERE feature_key = ? LIMIT 1");
        $chk->execute([$f[0]]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position) VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([$f[0], $f[1], $f[2], $f[3], $f[4], $f[5], $f[6], $f[7]]);
        }
    }
} catch (PDOException $e) {
    // Defer error to display section
}

// Handle feature toggle requests (supports AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feature_id'])) {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $featureId = (int)$_POST['feature_id'];
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("SELECT is_core FROM feature_toggles WHERE id = ?");
            $stmt->execute([$featureId]);
            $feature = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($feature && (int)$feature['is_core'] === 1 && !$isEnabled) {
                $message = 'Core features cannot be disabled';
                $messageType = 'error';
            } else {
                $update = $pdo->prepare("UPDATE feature_toggles SET is_enabled = ? WHERE id = ?");
                $update->execute([$isEnabled, $featureId]);
                $message = 'Feature status updated successfully';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }

    if ($isAjax) {
        $summaryStmt = $pdo->query("SELECT 
                SUM(is_enabled) AS enabled_total,
                SUM(NOT is_enabled) AS disabled_total,
                SUM(is_core) AS core_total,
                SUM(NOT is_core) AS optional_total
            FROM feature_toggles");
        $summary = $summaryStmt ? $summaryStmt->fetch(PDO::FETCH_ASSOC) : [
            'enabled_total' => 0,
            'disabled_total' => 0,
            'core_total' => 0,
            'optional_total' => 0,
        ];

        header('Content-Type: application/json');
        echo json_encode([
            'success' => $messageType === 'success',
            'message' => $message,
            'feature_id' => (int)($_POST['feature_id'] ?? 0),
            'is_enabled' => $messageType === 'success' ? (bool)$isEnabled : null,
            'summary' => [
                'enabled' => (int)($summary['enabled_total'] ?? 0),
                'disabled' => (int)($summary['disabled_total'] ?? 0),
                'core' => (int)($summary['core_total'] ?? 0),
                'optional' => (int)($summary['optional_total'] ?? 0),
            ],
        ]);
        exit;
    }
}

// Fetch features grouped by category
try {
    $stmt = $pdo->query("SELECT * FROM feature_toggles ORDER BY is_core DESC, category, menu_position, feature_name");
    $features = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $featuresByCategory = [];
    foreach ($features as $feature) {
        $cat = $feature['category'];
        $featuresByCategory[$cat] ??= [];
        $featuresByCategory[$cat][] = $feature;
    }
} catch (PDOException $e) {
    $featuresByCategory = [];
    $message = 'Feature toggle system not initialized. Run database migration.';
    $messageType = 'warning';
}

$totalCount = 0;
$enabledCount = 0;
$coreCount = 0;
foreach ($featuresByCategory as $catFeatures) {
    $totalCount += count($catFeatures);
    foreach ($catFeatures as $feature) {
        if (!empty($feature['is_enabled'])) {
            $enabledCount++;
        }
        if (!empty($feature['is_core'])) {
            $coreCount++;
        }
    }
}
$disabledCount = max(0, $totalCount - $enabledCount);
$optionalCount = max(0, $totalCount - $coreCount);

require_once '../includes/header.php';
$csrfToken = CSRF::getToken();
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>⚙️ Feature Management</h1>
        <p>Enable or disable system modules based on your business needs</p>
    </div>

    <?php if ($message && empty($_SERVER['HTTP_X_REQUESTED_WITH'])): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'warning' ? 'warning' : 'success'); ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>

    <div class="feature-splash-card">
        <div class="feature-splash-icon">💡</div>
        <div class="feature-splash-body">
            <h3>Tailor ABBIS to your workflow</h3>
            <p>
                Toggle modules on or off at any time. Core features remain active to keep operations running, while optional
                capabilities can be enabled when your team is ready for them.
            </p>
            <div class="feature-splash-summary">
                <div class="summary-item">
                    <span class="summary-label">Enabled</span>
                    <span class="summary-value" id="summaryEnabled"><?php echo number_format($enabledCount); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Disabled</span>
                    <span class="summary-value" id="summaryDisabled"><?php echo number_format($disabledCount); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Core</span>
                    <span class="summary-value" id="summaryCore"><?php echo number_format($coreCount); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Optional</span>
                    <span class="summary-value" id="summaryOptional"><?php echo number_format($optionalCount); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="feature-toolbar">
        <div class="feature-search">
            <label for="featureSearch" class="form-label">Search Features</label>
            <div class="feature-search-input">
                <span aria-hidden="true">🔍</span>
                <input type="search" id="featureSearch" placeholder="Search by name or description">
            </div>
        </div>
        <div class="feature-filter">
            <label for="featureCategory" class="form-label">Category</label>
            <select id="featureCategory">
                <option value="all">All Categories</option>
                <option value="core">Core</option>
                <option value="operations">Operations</option>
                <option value="financial">Financial</option>
                <option value="business">Business</option>
            </select>
        </div>
        <div class="feature-filter">
            <label for="featureStatus" class="form-label">Status</label>
            <select id="featureStatus">
                <option value="all">All</option>
                <option value="enabled">Enabled</option>
                <option value="disabled">Disabled</option>
            </select>
        </div>
    </div>

    <?php if (empty($featuresByCategory)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Feature Toggle System Not Initialized</strong><br>
            Run <code>database/maintenance_assets_inventory_migration.sql</code> to set up the feature management system.
        </div>
    <?php else: ?>
        <?php foreach ($featuresByCategory as $category => $categoryFeatures): ?>
            <div class="feature-category-card" data-category="<?php echo htmlspecialchars($category); ?>">
                <div class="feature-category-header">
                    <h2>
                    <?php
                    $categoryNames = [
                        'core' => '🔐 Core Features (Always Active)',
                        'operations' => '⚙️ Operations',
                        'financial' => '💰 Financial',
                        'business' => '📊 Business Intelligence & Integrations'
                    ];
                    echo $categoryNames[$category] ?? ucfirst($category);
                    ?>
                    </h2>
                </div>
                <div class="feature-grid">
                    <?php foreach ($categoryFeatures as $feature): ?>
                        <div class="feature-card"
                             data-feature-card
                             data-category="<?php echo htmlspecialchars($category); ?>"
                             data-status="<?php echo $feature['is_enabled'] ? 'enabled' : 'disabled'; ?>"
                             data-name="<?php echo htmlspecialchars($feature['feature_name']); ?>"
                             data-description="<?php echo htmlspecialchars($feature['description'] ?? ''); ?>">
                            <div class="feature-card-header">
                                <div class="feature-card-meta">
                                    <span class="feature-card-icon"><?php echo e($feature['icon'] ?? '📦'); ?></span>
                                    <div class="feature-card-title">
                                        <h3><?php echo e($feature['feature_name']); ?></h3>
                                        <?php if ($feature['is_core']): ?>
                                            <span class="feature-badge feature-badge-core">Core</span>
                                        <?php endif; ?>
                                        <?php if (!$feature['is_core'] && !$feature['is_enabled']): ?>
                                            <span class="feature-badge feature-badge-off">Off</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="feature-toggle">
                                    <input type="checkbox"
                                           id="feature-toggle-<?php echo $feature['id']; ?>"
                                           class="feature-switch"
                                           data-feature-id="<?php echo $feature['id']; ?>"
                                           data-csrf="<?php echo htmlspecialchars($csrfToken); ?>"
                                           <?php echo $feature['is_enabled'] ? 'checked' : ''; ?>
                                           <?php echo $feature['is_core'] ? 'disabled' : ''; ?>>
                                    <label for="feature-toggle-<?php echo $feature['id']; ?>" class="feature-switch-label"></label>
                                </div>
                            </div>
                            <?php if ($feature['description']): ?>
                                <p class="feature-card-description"><?php echo e($feature['description']); ?></p>
                            <?php endif; ?>
                            <div class="feature-card-footer">
                                <div class="feature-status-pill <?php echo $feature['is_enabled'] ? 'on' : 'off'; ?>" data-status-pill>
                                    <?php echo $feature['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                </div>
                                <?php if ($feature['is_core']): ?>
                                    <span class="feature-note">Always active</span>
                                <?php else: ?>
                                    <button type="button"
                                            class="feature-quick-toggle"
                                            data-feature-id="<?php echo $feature['id']; ?>"
                                            data-csrf="<?php echo htmlspecialchars($csrfToken); ?>"
                                            data-new-state="<?php echo $feature['is_enabled'] ? '0' : '1'; ?>">
                                        <?php echo $feature['is_enabled'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="feature-notes-card">
            <h3>⚠️ Important Notes</h3>
            <ul>
                <li>Disabling a feature hides its menus but never deletes data.</li>
                <li>Core modules keep ABBIS operational and cannot be turned off.</li>
                <li>Consider downstream reports and automations before disabling features.</li>
                <li>Changes are applied immediately for all users. Plan during low-usage windows.</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<div id="featureToast" class="feature-toast" role="status" aria-live="polite"></div>

<script>
(function() {
    const featureCards = Array.from(document.querySelectorAll('[data-feature-card]'));
    const searchInput = document.getElementById('featureSearch');
    const categorySelect = document.getElementById('featureCategory');
    const statusSelect = document.getElementById('featureStatus');
    const toastEl = document.getElementById('featureToast');

    function showToast(message, variant = 'success') {
        if (!toastEl) return;
        toastEl.textContent = message;
        toastEl.dataset.variant = variant;
        toastEl.classList.add('visible');
        setTimeout(() => toastEl.classList.remove('visible'), 3200);
    }

    function updateSummary(summary) {
        if (!summary) return;
        const { enabled, disabled, core, optional } = summary;
        const enabledEl = document.getElementById('summaryEnabled');
        const disabledEl = document.getElementById('summaryDisabled');
        const coreEl = document.getElementById('summaryCore');
        const optionalEl = document.getElementById('summaryOptional');
        if (enabledEl) enabledEl.textContent = enabled.toLocaleString();
        if (disabledEl) disabledEl.textContent = disabled.toLocaleString();
        if (coreEl) coreEl.textContent = core.toLocaleString();
        if (optionalEl) optionalEl.textContent = optional.toLocaleString();
    }

    function filterCards() {
        const query = (searchInput?.value || '').trim().toLowerCase();
        const categoryFilter = categorySelect?.value || 'all';
        const statusFilter = statusSelect?.value || 'all';

        featureCards.forEach(card => {
            const name = card.dataset.name.toLowerCase();
            const description = card.dataset.description.toLowerCase();
            const category = card.dataset.category;
            const status = card.dataset.status;

            const matchesSearch = query === '' || name.includes(query) || description.includes(query);
            const matchesCategory = categoryFilter === 'all' || category === categoryFilter;
            const matchesStatus = statusFilter === 'all' || status === statusFilter;

            card.style.display = (matchesSearch && matchesCategory && matchesStatus) ? '' : 'none';
        });
    }

    function toggleFeature(featureId, newState, csrfToken, switchEl, quickButton) {
        if (!featureId || !csrfToken) return;

        const formData = new FormData();
        formData.set('feature_id', featureId);
        formData.set('csrf_token', csrfToken);
        if (newState === '1') {
            formData.set('is_enabled', '1');
        }

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(resp => resp.json())
        .then(data => {
            if (!data || !data.success) {
                throw new Error(data?.message || 'Unable to update feature.');
            }

            const card = switchEl?.closest('[data-feature-card]') || quickButton?.closest('[data-feature-card]');
            if (card) {
                const newStatus = data.is_enabled ? 'enabled' : 'disabled';
                card.dataset.status = newStatus;

                const pill = card.querySelector('[data-status-pill]');
                if (pill) {
                    pill.textContent = data.is_enabled ? 'Enabled' : 'Disabled';
                    pill.classList.toggle('on', data.is_enabled === true);
                    pill.classList.toggle('off', data.is_enabled === false);
                }

                const quickToggle = card.querySelector('.feature-quick-toggle');
                if (quickToggle) {
                    quickToggle.dataset.newState = data.is_enabled ? '0' : '1';
                    quickToggle.textContent = data.is_enabled ? 'Disable' : 'Enable';
                }

                if (switchEl) {
                    switchEl.checked = !!data.is_enabled;
                }
            }

            updateSummary(data.summary);
            filterCards();
            showToast(data.message || 'Feature updated.');
        })
        .catch(err => {
            console.error(err);
            showToast(err.message || 'Failed to update feature.', 'error');
            if (switchEl) {
                switchEl.checked = !switchEl.checked;
            }
        });
    }

    if (searchInput) searchInput.addEventListener('input', filterCards);
    if (categorySelect) categorySelect.addEventListener('change', filterCards);
    if (statusSelect) statusSelect.addEventListener('change', filterCards);

    document.addEventListener('change', event => {
        const target = event.target;
        if (target && target.classList.contains('feature-switch')) {
            const featureId = target.dataset.featureId;
            const csrf = target.dataset.csrf;
            const newState = target.checked ? '1' : '0';
            toggleFeature(featureId, newState, csrf, target, null);
        }
    });

    document.addEventListener('click', event => {
        const button = event.target.closest('.feature-quick-toggle');
        if (!button) {
            return;
        }
        const featureId = button.dataset.featureId;
        const csrf = button.dataset.csrf;
        const newState = button.dataset.newState;
        const relatedSwitch = document.querySelector(`.feature-switch[data-feature-id="${featureId}"]`);

        if (relatedSwitch) {
            relatedSwitch.checked = newState === '1';
        }

        toggleFeature(featureId, newState, csrf, relatedSwitch, button);
    });

    filterCards();
})();
</script>

<style>
    .feature-splash-card {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 20px;
        align-items: center;
        margin-bottom: 20px;
        padding: 20px 24px;
        border-radius: 12px;
        border: 1px solid rgba(59, 130, 246, 0.2);
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.08) 0%, rgba(59, 130, 246, 0.02) 100%);
        box-shadow: var(--shadow-sm);
    }
    .feature-splash-icon {
        font-size: 42px;
    }
    .feature-splash-body h3 {
        margin: 0 0 10px;
        font-size: 18px;
        color: var(--primary);
    }
    .feature-splash-body p {
        margin: 0;
        color: var(--secondary);
        font-size: 13px;
        line-height: 1.6;
    }
    .feature-splash-summary {
        display: flex;
        gap: 16px;
        margin-top: 16px;
        flex-wrap: wrap;
    }
    .summary-item {
        display: flex;
        flex-direction: column;
        background: #ffffff;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 10px;
        padding: 10px 14px;
        min-width: 120px;
    }
    .summary-label {
        font-size: 11px;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .summary-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
    }
    .feature-toolbar {
        display: flex;
        gap: 16px;
        align-items: flex-end;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .feature-search,
    .feature-filter {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 200px;
    }
    .feature-search-input {
        display: flex;
        align-items: center;
        gap: 8px;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 6px 10px;
        background: var(--card);
    }
    .feature-search-input input {
        border: none;
        outline: none;
        background: transparent;
        width: 100%;
        font-size: 13px;
    }
    .feature-toolbar select {
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 13px;
        background: var(--card);
    }
    .feature-toolbar .form-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: var(--secondary);
    }
    .feature-category-card {
        margin-bottom: 28px;
        padding: 20px 22px;
        border: 1px solid rgba(148, 163, 184, 0.3);
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.9);
        box-shadow: var(--shadow-sm);
    }
    .feature-category-header {
        margin-bottom: 18px;
        color: var(--primary);
        font-size: 16px;
        font-weight: 600;
    }
    .feature-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
    }
    .feature-card {
        border: 1px solid rgba(148,163,184,0.35);
        border-radius: 12px;
        padding: 18px;
        background: var(--card);
        transition: border 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .feature-card[data-status="enabled"] {
        border-color: rgba(59, 130, 246, 0.45);
        box-shadow: var(--shadow-sm);
    }
    .feature-card-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
    }
    .feature-card-meta {
        display: flex;
        gap: 10px;
        align-items: center;
        flex: 1;
    }
    .feature-card-icon {
        font-size: 26px;
        line-height: 1;
    }
    .feature-card-title {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }
    .feature-card-title h3 {
        margin: 0;
        font-size: 16px;
        color: var(--text);
    }
    .feature-badge {
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .feature-badge-core {
        background: rgba(59, 130, 246, 0.1);
        color: #1d4ed8;
    }
    .feature-badge-off {
        background: rgba(248, 113, 113, 0.12);
        color: #b91c1c;
    }
    .feature-card-description {
        margin: 0;
        font-size: 13px;
        color: var(--secondary);
        line-height: 1.5;
    }
    .feature-card-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }
    .feature-status-pill {
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
    }
    .feature-status-pill.on {
        background: rgba(34, 197, 94, 0.15);
        color: #15803d;
    }
    .feature-status-pill.off {
        background: rgba(248, 113, 113, 0.15);
        color: #b91c1c;
    }
    .feature-note {
        font-size: 11px;
        color: var(--secondary);
    }
    .feature-quick-toggle {
        border: 1px solid var(--border);
        background: var(--card);
        color: var(--text);
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 12px;
        cursor: pointer;
        transition: border 0.2s ease, color 0.2s ease;
    }
    .feature-quick-toggle:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    .feature-switch {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }
    .feature-switch-label {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 24px;
        background: rgba(148, 163, 184, 0.4);
        border-radius: 999px;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    .feature-switch-label::after {
        content: '';
        position: absolute;
        left: 4px;
        top: 4px;
        width: 16px;
        height: 16px;
        background: #ffffff;
        border-radius: 50%;
        transition: transform 0.2s ease;
        box-shadow: 0 2px 6px rgba(15, 23, 42, 0.15);
    }
    .feature-switch:checked + .feature-switch-label {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.8) 0%, rgba(59, 130, 246, 1) 100%);
    }
    .feature-switch:checked + .feature-switch-label::after {
        transform: translateX(22px);
    }
    .feature-notes-card {
        margin-top: 24px;
        padding: 18px 20px;
        border-left: 3px solid #f59e0b;
        background: rgba(254, 243, 199, 0.6);
        border-radius: 12px;
        font-size: 13px;
        color: #92400e;
    }
    .feature-notes-card h3 {
        margin: 0 0 8px;
        font-size: 15px;
    }
    .feature-notes-card ul {
        margin: 0;
        padding-left: 18px;
        list-style: disc;
        line-height: 1.6;
    }
    .feature-toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        padding: 12px 18px;
        border-radius: 10px;
        background: rgba(34, 197, 94, 0.95);
        color: #ffffff;
        box-shadow: 0 10px 24px rgba(15,23,42,0.22);
        opacity: 0;
        pointer-events: none;
        transform: translateY(20px);
        transition: opacity 0.2s ease, transform 0.2s ease;
        z-index: 1300;
        font-size: 13px;
    }
    .feature-toast[data-variant="error"] {
        background: rgba(239, 68, 68, 0.95);
    }
    .feature-toast.visible {
        opacity: 1;
        transform: translateY(0);
    }
    @media (max-width: 1024px) {
        .feature-splash-card {
            grid-template-columns: 1fr;
            text-align: center;
        }
        .feature-splash-summary {
            justify-content: center;
        }
        .feature-toolbar {
            flex-direction: column;
            align-items: stretch;
        }
        .feature-category-card {
            padding: 18px;
        }
        .feature-grid {
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        }
    }
    @media (max-width: 768px) {
        .feature-toast {
            left: 16px;
            right: 16px;
            bottom: 16px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>

