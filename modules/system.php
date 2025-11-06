<?php
/**
 * System Management Hub
 * Central location for all system administration functions
 */
$page_title = 'System Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

require_once '../includes/header.php';
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
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>⚙️ System Management</h1>
        <p>Central hub for all system configuration and administration</p>
    </div>
    
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
                <a href="../cms/admin/" target="_blank" class="btn btn-primary" style="flex: 1; min-width: 120px;">
                    Open CMS Admin →
                </a>
                <a href="../cms/" target="_blank" class="btn btn-outline" style="flex: 1; min-width: 120px;">
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
                <a href="../cms/legal/drilling-agreement" target="_blank" class="btn btn-outline" style="flex: 1; min-width: 120px;">
                    View Agreement →
                </a>
            </div>
        </div>
        <?php endif; ?>
        
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

        <!-- APIs & Integrations: Map Providers -->
        <div class="system-card">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                <span style="font-size: 32px;">🗺️</span>
                <div>
                    <h2 style="margin: 0;">APIs: Map Providers</h2>
                    <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Configure Google or Leaflet (OSM) for location picker</p>
                </div>
            </div>
            <a href="map-integration.php" class="btn btn-primary" style="width: 100%;">Configure Maps →</a>
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
            } catch (PDOException $e) {
                $activeUsers = $totalReports = $totalClients = $activeApiKeys = $zohoConnections = $elkActive = 0;
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
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

