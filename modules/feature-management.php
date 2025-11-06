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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;")
    ;
    // Seed defaults if empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM feature_toggles")->fetchColumn();
    if ($count === 0) {
        $seed = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position)
            VALUES 
            ('field_reports','Field Reports','core','Core field operations','📝',1,1,1),
            ('financial','Financial','core','Finance, Payroll, Loans','💰',1,1,2),
            ('clients_crm','Clients & CRM','core','Clients and relationships','🤝',1,1,3),
            ('materials','Materials','core','Core materials management','📦',1,1,4),
            ('accounting','Accounting','financial','General accounting (double-entry), reports, integrations','📘',0,0,4),
            ('inventory_advanced','Advanced Inventory','operations','Transactions, stock, reorder, analytics','📋',0,0,5),
            ('assets','Assets','operations','Company assets and depreciation','🏭',0,0,6),
            ('maintenance','Maintenance','operations','Equipment maintenance management','🔧',0,0,7),
            ('api_keys','API Keys','business','Manage API access','🔑',1,0,8),
            ('monitoring_api','Monitoring API','business','Health checks and metrics','📡',1,0,9),
            ('zoho','Zoho Integration','business','Zoho CRM/Inventory/Books/Payroll/HR','🧩',0,0,10),
            ('looker_studio','Looker Studio','business','External analytics data sources','📈',0,0,11),
            ('elk_stack','ELK Stack','business','Elasticsearch/Kibana integration','🦌',0,0,12)
        ");
        $seed->execute();
    }
    // Ensure 'accounting' feature exists even if seeding previously happened
    $chk = $pdo->prepare("SELECT id FROM feature_toggles WHERE feature_key = ? LIMIT 1");
    $chk->execute(['accounting']);
    if (!$chk->fetch()) {
        $ins = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position)
            VALUES ('accounting','Accounting','financial','General accounting (double-entry), reports, integrations','📘',0,0,4)");
        $ins->execute();
    }
    // Ensure new experimental features exist
    $featuresToEnsure = [
        ['job_planner','Smart Job Planner','operations','Auto-build drilling schedule','🗓️',0,0,8],
        ['suppliers','Supplier Intelligence','operations','Rank suppliers and draft POs','🏭',0,0,9],
        ['crm_health','Client Health & NPS','business','Satisfaction and follow-ups','💚',0,0,10],
        ['collections','Collections Assistant','financial','Predict late payers and dunning','📅',0,0,11],
        ['maint_digital_twin','Maintenance Digital Twin','operations','Asset state and TtM','🧩',0,0,12],
        ['board_pack','Executive Export Pack','business','One-click monthly board pack','📦',0,0,13],
        ['cms','CMS Website','business','Public website with ecommerce, blog, and quote requests','🌐',0,0,14]
    ];
    foreach ($featuresToEnsure as $f) {
        $chk = $pdo->prepare("SELECT id FROM feature_toggles WHERE feature_key = ? LIMIT 1");
        $chk->execute([$f[0]]);
        if (!$chk->fetch()) {
            $ins = $pdo->prepare("INSERT INTO feature_toggles (feature_key, feature_name, category, description, icon, is_enabled, is_core, menu_position) VALUES (?,?,?,?,?,?,?,?)");
            $ins->execute([$f[0],$f[1],$f[2],$f[3],$f[4],$f[5],$f[6],$f[7]]);
        }
    }
} catch (PDOException $e) {
    // Defer error to display section
}

// Handle feature toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feature_id'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $featureId = intval($_POST['feature_id']);
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        
        try {
            // Check if feature is core
            $stmt = $pdo->prepare("SELECT is_core FROM feature_toggles WHERE id = ?");
            $stmt->execute([$featureId]);
            $feature = $stmt->fetch();
            
            if ($feature && $feature['is_core'] && !$isEnabled) {
                $message = 'Core features cannot be disabled';
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE feature_toggles SET is_enabled = ? WHERE id = ?");
                $stmt->execute([$isEnabled, $featureId]);
                $message = 'Feature status updated successfully';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get all features grouped by category
try {
    $stmt = $pdo->query("
        SELECT * FROM feature_toggles 
        ORDER BY is_core DESC, category, menu_position, feature_name
    ");
    $features = $stmt->fetchAll();
    
    // Group by category
    $featuresByCategory = [];
    foreach ($features as $feature) {
        $cat = $feature['category'];
        if (!isset($featuresByCategory[$cat])) {
            $featuresByCategory[$cat] = [];
        }
        $featuresByCategory[$cat][] = $feature;
    }
} catch (PDOException $e) {
    $featuresByCategory = [];
    $message = 'Feature toggle system not initialized. Run database migration.';
    $messageType = 'warning';
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>⚙️ Feature Management</h1>
        <p>Enable or disable system modules based on your business needs</p>
    </div>
    
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'warning' ? 'warning' : 'success'); ?>">
            <?php echo e($message); ?>
        </div>
    <?php endif; ?>
    
    <div class="dashboard-card" style="margin-bottom: 30px; background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid var(--primary);">
        <div style="display: flex; align-items: start; gap: 15px;">
            <div style="font-size: 32px;">💡</div>
            <div>
                <h3 style="margin: 0 0 10px 0; color: var(--primary);">About Feature Management</h3>
                <p style="margin: 0; color: var(--text); line-height: 1.6;">
                    ABBIS is a system of systems. Enable only the features you need. Core features (Field Reports, Financial, Clients & CRM, Materials) 
                    are always active and cannot be disabled. Optional features can be toggled on/off to customize the system for your business.
                </p>
            </div>
        </div>
    </div>
    
    <?php if (empty($featuresByCategory)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Feature Toggle System Not Initialized</strong><br>
            Run <code>database/maintenance_assets_inventory_migration.sql</code> to set up the feature management system.
        </div>
    <?php else: ?>
        <?php foreach ($featuresByCategory as $category => $categoryFeatures): ?>
            <div class="dashboard-card" style="margin-bottom: 30px;">
                <h2 style="margin-bottom: 20px; color: var(--primary); text-transform: capitalize;">
                    <?php 
                    $categoryNames = [
                        'core' => '🔐 Core Features (Always Active)',
                        'operations' => '⚙️ Operations',
                        'financial' => '💰 Financial',
                        'business' => '📊 Business Intelligence'
                    ];
                    echo $categoryNames[$category] ?? ucfirst($category);
                    ?>
                </h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
                    <?php foreach ($categoryFeatures as $feature): ?>
                        <div style="
                            border: 2px solid <?php echo $feature['is_enabled'] ? 'var(--success)' : 'var(--border)'; ?>;
                            border-radius: 12px;
                            padding: 20px;
                            background: <?php echo $feature['is_enabled'] ? 'rgba(16, 185, 129, 0.05)' : 'var(--bg)'; ?>;
                            transition: all 0.3s ease;
                        ">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <span style="font-size: 24px;"><?php echo e($feature['icon'] ?? '📦'); ?></span>
                                        <h3 style="margin: 0; font-size: 18px; color: var(--text);">
                                            <?php echo e($feature['feature_name']); ?>
                                        </h3>
                                        <?php if ($feature['is_core']): ?>
                                            <span style="
                                                padding: 2px 8px;
                                                background: var(--primary);
                                                color: white;
                                                border-radius: 4px;
                                                font-size: 10px;
                                                font-weight: 600;
                                                text-transform: uppercase;
                                            ">Core</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($feature['description']): ?>
                                        <p style="margin: 0; color: var(--secondary); font-size: 13px; line-height: 1.5;">
                                            <?php echo e($feature['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <form method="POST" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="feature_id" value="<?php echo $feature['id']; ?>">
                                <input type="hidden" name="toggle_feature" value="1">
                                
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <label style="
                                        display: flex;
                                        align-items: center;
                                        gap: 10px;
                                        cursor: pointer;
                                        font-weight: 600;
                                        color: var(--text);
                                    ">
                                        <input 
                                            type="checkbox" 
                                            name="is_enabled" 
                                            value="1"
                                            <?php echo $feature['is_enabled'] ? 'checked' : ''; ?>
                                            <?php echo $feature['is_core'] ? 'disabled' : ''; ?>
                                            onchange="this.form.submit()"
                                            style="width: 20px; height: 20px; cursor: pointer;"
                                        >
                                        <span>
                                            <?php if ($feature['is_enabled']): ?>
                                                <span style="color: var(--success);">✓ Enabled</span>
                                            <?php else: ?>
                                                <span style="color: var(--secondary);">✗ Disabled</span>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                    
                                    <?php if (!$feature['is_core']): ?>
                                        <button type="submit" name="toggle_feature" class="btn btn-sm <?php echo $feature['is_enabled'] ? 'btn-outline' : 'btn-primary'; ?>" style="display: none;">
                                            <?php echo $feature['is_enabled'] ? 'Disable' : 'Enable'; ?>
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--secondary);">Always Active</span>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        
        <div class="dashboard-card" style="margin-top: 30px; background: #fef3c7; border-left: 4px solid #f59e0b;">
            <h3 style="margin: 0 0 10px 0; color: #92400e;">⚠️ Important Notes</h3>
            <ul style="margin: 0; padding-left: 20px; color: #78350f; line-height: 1.8;">
                <li>Disabling a feature will hide its menu items but will not delete data.</li>
                <li>You can re-enable features at any time.</li>
                <li>Core features (Field Reports, Financial, Clients & CRM, Materials) are essential and cannot be disabled.</li>
                <li>Disabling features may affect related reports and analytics.</li>
                <li>Always ensure data is backed up before major changes.</li>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

