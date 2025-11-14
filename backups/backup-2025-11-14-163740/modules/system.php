<?php
/**
 * System Management Hub
 * Central location for all system administration functions
 */
$page_title = 'System Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

require_once '../includes/header.php';
require_once '../includes/navigation-tracker.php';

NavigationTracker::recordCurrentPage((int)$_SESSION['user_id']);

// Documentation map (same as documentation.php)
$docsMap = [
    'system_overview' => [
        'title' => 'Complete System Features',
        'path' => __DIR__ . '/../docs/COMPLETE_SYSTEM_FEATURES.md',
        'category' => 'System Overview'
    ],
    'architecture' => [
        'title' => 'Architecture & Integrations',
        'path' => __DIR__ . '/../docs/reports/SYSTEM_ANALYSIS_SUMMARY.md',
        'category' => 'System Overview'
    ],
    'onboarding' => [
        'title' => 'Onboarding Wizard Guide',
        'path' => __DIR__ . '/../docs/guides/ONBOARDING_WIZARD.md',
        'category' => 'Core Workflows'
    ],
    'client_portal' => [
        'title' => 'Client Portal & Online Payments',
        'path' => __DIR__ . '/../docs/CLIENT_PORTAL_MILESTONE2.md',
        'category' => 'Core Workflows'
    ],
    'geology' => [
        'title' => 'Geology Estimator Guide',
        'path' => __DIR__ . '/../docs/guides/GEOLOGY_ESTIMATOR.md',
        'category' => 'Advanced Features'
    ],
    'regulatory' => [
        'title' => 'Regulatory Forms Automation Guide',
        'path' => __DIR__ . '/../docs/guides/REGULATORY_FORMS.md',
        'category' => 'Advanced Features'
    ],
    'telemetry' => [
        'title' => 'Rig Maintenance Telemetry Guide',
        'path' => __DIR__ . '/../docs/guides/RIG_MAINTENANCE_TELEMETRY.md',
        'category' => 'Advanced Features'
    ],
    'sampling' => [
        'title' => 'Environmental Sampling Workflow Guide',
        'path' => __DIR__ . '/../docs/guides/ENVIRONMENTAL_SAMPLING.md',
        'category' => 'Advanced Features'
    ]
];

$selectedKey = $_GET['doc'] ?? null;
$selectedDoc = null;
$renderedDoc = null;
$error = null;
$activeTab = $_GET['tab'] ?? 'management';

if ($selectedKey && isset($docsMap[$selectedKey])) {
    $selectedDoc = $docsMap[$selectedKey];
    if (is_readable($selectedDoc['path'])) {
        $raw = file_get_contents($selectedDoc['path']);
        $renderedDoc = renderMarkdownLite($raw);
    } else {
        $error = 'Unable to read documentation file.';
    }
}

function renderMarkdownLite(string $markdown): string
{
    $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
    $lines = explode("\n", $markdown);
    $html = '';
    $inList = false;
    $inCode = false;
    foreach ($lines as $line) {
      $trim = ltrim($line);
      if (str_starts_with($trim, '```')) {
          if (!$inCode) {
              if ($inList) {
                  $html .= '</ul>';
                  $inList = false;
              }
              $html .= '<pre class="doc-code">';
              $inCode = true;
          } else {
              $html .= '</pre>';
              $inCode = false;
          }
          continue;
      }
      if ($inCode) {
          $html .= htmlspecialchars($line) . "\n";
          continue;
      }
      if (preg_match('/^#{1,6} /', $trim)) {
          if ($inList) {
              $html .= '</ul>';
              $inList = false;
          }
          $level = strpos($trim, ' ');
          $text = trim(substr($trim, $level + 1));
          $level = min(max($level, 1), 6);
          $html .= sprintf('<h%d>%s</h%d>', $level, e($text), $level);
          continue;
      }
      if (preg_match('/^[-*+] /', $trim)) {
          if (!$inList) {
              $html .= '<ul>';
              $inList = true;
          }
          $html .= '<li>' . e(trim(substr($trim, 2))) . '</li>';
          continue;
      }
      if ($trim === '') {
          if ($inList) {
              $html .= '</ul>';
              $inList = false;
          }
          $html .= '<p></p>';
          continue;
      }
      if ($inList) {
          $html .= '</ul>';
          $inList = false;
      }
      $html .= '<p>' . e($trim) . '</p>';
    }
    if ($inList) {
        $html .= '</ul>';
    }
    if ($inCode) {
        $html .= '</pre>';
    }
    return $html;
}
?>

<style>
    /* 3-Column Layout for System Management */
    .system-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
        margin-bottom: 16px;
    }
    
    @media (max-width: 1400px) {
        .system-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 900px) {
        .system-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .system-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 24px;
        box-shadow: var(--shadow);
        transition: transform 0.2s, box-shadow 0.2s;
    }
    
    .system-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    
    /* Tab Navigation */
    .system-tabs {
        display: flex;
        gap: 12px;
        margin-bottom: 30px;
        border-bottom: 2px solid var(--border);
        padding-bottom: 0;
    }
    
    .system-tab {
        padding: 14px 24px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: var(--secondary);
        border-bottom: 3px solid transparent;
        transition: all 0.3s;
        font-weight: 500;
        margin-bottom: -2px;
    }
    
    .system-tab:hover {
        color: var(--primary);
        background: color-mix(in srgb, var(--text) 2%, transparent);
    }
    
    .system-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .system-tab-content {
        display: none;
    }
    
    .system-tab-content.active {
        display: block;
    }
    
    /* Documentation Styles */
    .docs-grid {
        display: grid;
        grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
        gap: 24px;
    }
    
    @media (max-width: 992px) {
        .docs-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .doc-sidebar {
        border-radius: 18px;
        border: 1px solid var(--border);
        background: var(--card);
        padding: 20px;
        display: grid;
        gap: 18px;
        height: fit-content;
        position: sticky;
        top: 110px;
        max-height: calc(100vh - 140px);
        overflow-y: auto;
    }
    
    .doc-category h3 {
        margin: 0 0 10px;
        font-size: 16px;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .doc-link {
        display: block;
        padding: 10px 12px;
        border-radius: 12px;
        text-decoration: none;
        color: var(--text);
        border: 1px solid transparent;
        transition: background 0.2s, border 0.2s;
        font-weight: 500;
        margin-bottom: 6px;
    }
    
    .doc-link:hover {
        background: color-mix(in srgb, var(--primary) 8%, transparent);
        border-color: color-mix(in srgb, var(--primary) 32%, transparent);
    }
    
    .doc-link.active {
        background: color-mix(in srgb, var(--primary) 12%, transparent);
        border-color: color-mix(in srgb, var(--primary) 45%, transparent);
        color: var(--primary);
    }
    
    .doc-viewer {
        border-radius: 18px;
        border: 1px solid var(--border);
        background: var(--card);
        padding: 26px;
        min-height: 480px;
        box-shadow: var(--shadow-sm, 0 20px 60px rgba(15,23,42,0.08));
    }
    
    .doc-viewer h1, .doc-viewer h2, .doc-viewer h3, .doc-viewer h4 {
        margin-top: 24px;
    }
    
    .doc-viewer ul {
        margin-left: 20px;
        padding-left: 18px;
    }
    
    .doc-code {
        background: rgba(15,23,42,0.08);
        padding: 14px;
        border-radius: 12px;
        overflow-x: auto;
        font-family: "Fira Code", "Courier New", monospace;
        font-size: 13px;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>⚙️ System Management</h1>
        <p>Central hub for all system configuration, administration, and complete ABBIS documentation</p>
    </div>
    
    <!-- Tab Navigation -->
    <div class="system-tabs">
        <button class="system-tab <?php echo $activeTab === 'management' ? 'active' : ''; ?>" 
                onclick="switchTab('management', this)">
            ⚙️ System Management
        </button>
        <button class="system-tab <?php echo $activeTab === 'documentation' ? 'active' : ''; ?>" 
                onclick="switchTab('documentation', this)">
            📚 Complete ABBIS Documentation
        </button>
    </div>
    
    <!-- System Management Tab -->
    <div id="tab-management" class="system-tab-content <?php echo $activeTab === 'management' ? 'active' : ''; ?>">
    <!-- System Management Grid -->
    <div class="system-grid">
        <!-- Configuration (Core) -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">⚙️</span>
                <div>
                    <h2 style="margin: 0;">Configuration</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        System settings, company info, rigs, workers, materials
                    </p>
                </div>
            </div>
            <a href="config.php" class="btn btn-primary" style="width: 100%;">
                Open Configuration →
            </a>
        </div>
        
        <!-- User Management (Core) -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">👥</span>
                <div>
                    <h2 style="margin: 0;">User Management</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Manage system users and permissions
                    </p>
                </div>
            </div>
            <a href="users.php" class="btn btn-primary" style="width: 100%;">
                Manage Users →
            </a>
        </div>

        <!-- Feature Management (Core) -->
        <div class="system-card" style="border: 2px solid var(--primary);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">⚙️</span>
                <div>
                    <h2 style="margin: 0;">Feature Management</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Enable/disable system modules based on business needs
                    </p>
                </div>
            </div>
            <a href="feature-management.php" class="btn btn-primary" style="width: 100%;">
                Manage Features →
            </a>
        </div>

        <!-- Data Management -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">💾</span>
                <div>
                    <h2 style="margin: 0;">Data Management</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Import, export, and purge system data
                    </p>
                </div>
            </div>
            <a href="data-management.php" class="btn btn-primary" style="width: 100%;">
                Open Data Management →
            </a>
        </div>

        <!-- Onboarding Wizard -->
        <div class="system-card" style="border: 2px solid rgba(59, 130, 246, 0.25);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🚀</span>
                <div>
                    <h2 style="margin: 0;">Onboarding Wizard</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Guided CSV import for clients, rigs, workers, and catalog items
                    </p>
                </div>
            </div>
            <a href="onboarding-wizard.php" class="btn btn-primary" style="width: 100%;">
                Launch Onboarding Wizard →
            </a>
        </div>

        <!-- Database Migrations -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🗄️</span>
                <div>
                    <h2 style="margin: 0;">Database Migrations</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Run SQL migrations to create or update database tables
                    </p>
                </div>
            </div>
            <a href="database-migrations.php" class="btn btn-primary" style="width: 100%;">
                Run Migrations →
            </a>
        </div>
        
        <!-- API Keys -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🔑</span>
                <div>
                    <h2 style="margin: 0;">API Keys</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Manage API keys for external integrations
                    </p>
                </div>
            </div>
            <a href="api-keys.php" class="btn btn-primary" style="width: 100%;">
                Manage API Keys →
            </a>
        </div>

        <!-- Security Audit Logs -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🛡️</span>
                <div>
                    <h2 style="margin: 0;">Security Audit Logs</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Review access decisions and permission denials across ABBIS
                    </p>
                </div>
            </div>
            <a href="access-logs.php" class="btn btn-primary" style="width: 100%;">
                View Access Logs →
            </a>
        </div>

        <!-- Policies & Legal -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📜</span>
                <div>
                    <h2 style="margin: 0;">Policies & Legal</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Terms, Privacy, Cookies, DPA, SLA, and Contracts
                    </p>
                </div>
            </div>
            <a href="policies.php" class="btn btn-primary" style="width: 100%;">
                Open Policies →
            </a>
        </div>
        
        <?php if (isFeatureEnabled('cms')): ?>
        <!-- CMS Website Admin -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🌐</span>
                <div>
                    <h2 style="margin: 0;">CMS Website</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Manage website pages, posts, themes, ecommerce, and content
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?php echo cms_url('admin/'); ?>" target="_blank" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    Open CMS Admin →
                </a>
                <a href="<?php echo cms_url('public/index.php'); ?>" target="_blank" class="btn btn-outline" style="flex: 1; min-width: 120px;">
                    View Website →
                </a>
            </div>
        </div>
        
        <!-- Legal Documents -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📜</span>
                <div>
                    <h2 style="margin: 0;">Legal Documents</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Manage drilling agreements, terms of service, privacy policies
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="legal-documents.php" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    Manage Documents →
                </a>
                <a href="<?php echo cms_url('legal/drilling-agreement'); ?>" target="_blank" class="btn btn-outline" style="flex: 1; min-width: 120px;">
                    View Agreement →
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Client Portal -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">👥</span>
                <div>
                    <h2 style="margin: 0;">Client Portal</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Customer portal for quotes, invoices, and online payments
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <a href="<?php echo client_portal_url('login.php'); ?>" target="_blank" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    View Portal →
                </a>
                <a href="<?php echo cms_url('admin/payment-methods.php'); ?>" target="_blank" class="btn btn-outline" style="flex: 1; min-width: 120px;">
                    Configure Payments →
                </a>
            </div>
        </div>
        
        <!-- Documentation Hub -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📚</span>
                <div>
                    <h2 style="margin: 0;">Documentation Hub</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Complete project manuals &amp; advanced modules documentation
                    </p>
                </div>
            </div>
            <a href="?tab=documentation" class="btn btn-primary" style="width: 100%;">
                View Complete Documentation →
            </a>
        </div>
        
        <!-- Zoho Integration -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🔗</span>
                <div>
                    <h2 style="margin: 0;">Zoho Integration</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Connect with Zoho CRM, Books, Inventory, Payroll, HR
                    </p>
                </div>
            </div>
            <a href="zoho-integration.php" class="btn btn-primary" style="width: 100%;">
                Configure Zoho →
            </a>
        </div>
        
        <!-- Looker Studio Integration -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">📊</span>
                <div>
                    <h2 style="margin: 0;">Looker Studio</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Connect data for Google Looker Studio visualization
                    </p>
                </div>
            </div>
            <a href="looker-studio-integration.php" class="btn btn-primary" style="width: 100%;">
                Configure Looker Studio →
            </a>
        </div>
        
        <!-- ELK/Kibana Integration -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🔍</span>
                <div>
                    <h2 style="margin: 0;">ELK Stack</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Elasticsearch, Logstash, Kibana integration
                    </p>
                </div>
            </div>
            <a href="elk-integration.php" class="btn btn-primary" style="width: 100%;">
                Configure ELK →
            </a>
        </div>
        
        <!-- AI Providers & Assistant -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🧠</span>
                <div>
                    <h2 style="margin: 0;">AI Providers</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Configure OpenAI, DeepSeek, Gemini, or Ollama and review assistant usage
                    </p>
                </div>
            </div>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                <?php if (isset($accessControl) && $accessControl->shouldDisplayNav(AI_PERMISSION_KEY)): ?>
                    <a href="ai-assistant.php" class="btn btn-outline" style="flex: 1; min-width: 140px;">
                        Launch AI Assistant →
                    </a>
                <?php endif; ?>
                <a href="ai-governance.php" class="btn btn-primary" style="flex: 1; min-width: 140px;">
                    Open AI Governance →
                </a>
            </div>
        </div>

        <!-- Map Providers -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🗺️</span>
                <div>
                    <h2 style="margin: 0;">Map Providers</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                        Configure Google or Leaflet (OSM) for location picker
                    </p>
                </div>
            </div>
            <a href="map-integration.php" class="btn btn-primary" style="width: 100%;">
                Configure Maps →
            </a>
        </div>

        <!-- Social Authentication -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🔐</span>
                <div>
                    <h2 style="margin: 0;">Social Authentication</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Configure Google OAuth and Facebook OAuth for social login</p>
                </div>
            </div>
            <a href="social-auth-config.php" class="btn btn-primary" style="width: 100%;">Configure Social Login →</a>
        </div>
    </div>
    
    <!-- Quick System Info -->
    <div class="dashboard-card" style="margin-top: 30px;">
        <h2>📈 System Overview</h2>
        <div class="stats-grid">
            <?php
            $pdo = getDBConnection();
            
            // Get system statistics
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
                $activeUsers = $stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM field_reports");
                $totalReports = $stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
                $totalClients = $stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM api_keys WHERE is_active = 1");
                $activeApiKeys = $stmt->fetch()['count'];
                
                // Check integrations
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM zoho_integration WHERE is_active = 1");
                $zohoConnections = $stmt->fetch()['count'];
                
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM elk_config WHERE is_active = 1");
                $elkActive = $stmt->fetch()['count'];
                
                // Active Workers
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM workers WHERE status = 'active'");
                $activeWorkers = $stmt->fetch()['count'];
                
                // Active Rigs
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM rigs WHERE status = 'active'");
                $activeRigs = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $activeUsers = $totalReports = $totalClients = $activeApiKeys = $zohoConnections = $elkActive = $activeWorkers = $activeRigs = 0;
            }
            ?>
            
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-info">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📄</div>
                <div class="stat-info">
                    <h3><?php echo $totalReports; ?></h3>
                    <p>Total Reports</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🏢</div>
                <div class="stat-info">
                    <h3><?php echo $totalClients; ?></h3>
                    <p>Clients</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔑</div>
                <div class="stat-info">
                    <h3><?php echo $activeApiKeys; ?></h3>
                    <p>Active API Keys</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔗</div>
                <div class="stat-info">
                    <h3><?php echo $zohoConnections; ?></h3>
                    <p>Zoho Connections</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">🔍</div>
                <div class="stat-info">
                    <h3><?php echo $elkActive > 0 ? 'Active' : 'Inactive'; ?></h3>
                    <p>ELK Integration</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👷</div>
                <div class="stat-info">
                    <h3><?php echo $activeWorkers; ?></h3>
                    <p>Active Workers</p>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⛏️</div>
                <div class="stat-info">
                    <h3><?php echo $activeRigs; ?></h3>
                    <p>Active Rigs</p>
                </div>
            </div>
        </div>
    </div>
    </div>
    <!-- End System Management Tab -->
    
    <!-- Documentation Tab -->
    <div id="tab-documentation" class="system-tab-content <?php echo $activeTab === 'documentation' ? 'active' : ''; ?>">
        <div class="page-heading" style="margin-bottom: 24px;">
            <h2>📚 Complete ABBIS Documentation</h2>
            <p class="page-subtitle">Authoritative references for architecture, workflows, advanced modules, and integration guides covering the entire ABBIS system.</p>
        </div>

        <div class="docs-grid">
            <aside class="doc-sidebar">
                <?php
                $categories = [];
                foreach ($docsMap as $key => $doc) {
                    $categories[$doc['category']][] = ['key' => $key, 'title' => $doc['title']];
                }
                foreach ($categories as $categoryName => $docs):
                ?>
                    <div class="doc-category">
                        <h3><?php echo e($categoryName); ?></h3>
                        <div>
                            <?php foreach ($docs as $doc): ?>
                                <a class="doc-link <?php echo $selectedKey === $doc['key'] ? 'active' : ''; ?>"
                                   href="system.php?tab=documentation&doc=<?php echo urlencode($doc['key']); ?>">
                                    <?php echo e($doc['title']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </aside>
            <section class="doc-viewer">
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo e($error); ?></div>
                <?php elseif ($selectedDoc && $renderedDoc): ?>
                    <div>
                        <?php echo $renderedDoc; ?>
                    </div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:280px; text-align:center; color:var(--secondary);">
                        <div style="font-size:48px; margin-bottom:12px;">📚</div>
                        <h2 style="margin:0 0 10px;">Select a document to begin</h2>
                        <p style="max-width:420px;">Choose a guide from the sidebar to read detailed implementation notes, integrations, and operational playbooks for the complete ABBIS system.</p>
                        <p style="max-width:420px; margin-top:12px; font-size:14px; color:var(--secondary);">
                            This documentation library covers all aspects of ABBIS including system architecture, core workflows, advanced features, and integration guides.
                        </p>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
    <!-- End Documentation Tab -->
</div>

<script>
function switchTab(tab, button) {
    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    // Remove doc param if switching to management
    if (tab === 'management') {
        url.searchParams.delete('doc');
    }
    window.history.pushState({}, '', url);
    
    // Update active tab
    document.querySelectorAll('.system-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.system-tab-content').forEach(c => c.classList.remove('active'));
    
    // Activate clicked button and corresponding content
    if (button) {
        button.classList.add('active');
    }
    document.getElementById('tab-' + tab).classList.add('active');
}
</script>

<?php require_once '../includes/footer.php'; ?>

