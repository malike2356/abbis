<?php
/**
 * Comprehensive Help & User Guide
 */
$page_title = 'Help & User Guide';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

require_once '../includes/header.php';
?>

<style>
    .help-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    .help-search {
        margin-bottom: 30px;
    }
    .help-search input {
        width: 100%;
        padding: 15px 20px;
        font-size: 16px;
        border: 2px solid var(--border);
        border-radius: 8px;
    }
    .help-categories {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 40px;
    }
    @media (max-width: 1200px) {
        .help-categories {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    @media (max-width: 900px) {
        .help-categories {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    @media (max-width: 600px) {
        .help-categories {
            grid-template-columns: 1fr;
        }
    }
    .help-category-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        color: inherit;
        display: block;
    }
    .help-category-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: var(--primary);
    }
    .help-category-card .icon {
        font-size: 48px;
        margin-bottom: 15px;
    }
    .help-category-card h3 {
        color: var(--primary);
        margin-bottom: 10px;
    }
    .help-section {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .help-section h2 {
        color: var(--primary);
        border-bottom: 3px solid var(--primary);
        padding-bottom: 15px;
        margin-bottom: 25px;
    }
    .help-item {
        margin-bottom: 16px;
        padding-bottom: 16px;
        border-bottom: 1px solid var(--border);
    }
    .help-item:last-child {
        border-bottom: none;
    }
    .help-item h3 {
        color: var(--text);
        margin-bottom: 10px;
        font-size: 18px;
    }
    .help-item p {
        color: var(--secondary);
        line-height: 1.8;
        margin-bottom: 10px;
    }
    .help-item ul {
        margin-left: 20px;
        color: var(--secondary);
        line-height: 1.8;
    }
    .help-item code {
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
        color: #1e40af;
    }

    /* Grid layout for sections to avoid long scrolling */
    .help-sections-grid {
        display: none;
    }
    .help-sections-grid.visible {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 1100px) {
        .help-sections-grid.visible {
            grid-template-columns: 1fr;
        }
    }

    /* Collapsible sections */
    .help-section.collapsed .help-section-content { display: block; }
    .help-section .section-header { display: flex; justify-content: space-between; align-items: center; cursor: default; }
    .help-section .toggle-icon { font-size: 18px; color: var(--secondary); display: none; }
    .help-controls { display: flex; gap: 10px; margin: 10px 0 20px 0; }
    .help-controls .btn-sm { padding: 6px 10px; font-size: 12px; }
    .help-item .screenshot-placeholder {
        background: #f8fafc;
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        color: var(--secondary);
        margin: 15px 0;
    }
    .help-quick-links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 20px;
    }
    .help-quick-links a {
        display: inline-block;
        padding: 8px 16px;
        background: var(--primary);
        color: white;
        border-radius: 6px;
        text-decoration: none;
        font-size: 14px;
    }
    /* Sections hidden by default; shown in modal or full view */
    .help-section { display: none; }
    .help-sections-grid.visible .help-section { display: block; }
    .help-modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: none; z-index: 1000; }
    .help-modal { position: fixed; left: 50%; top: 8vh; transform: translateX(-50%); width: min(1000px, 92vw); max-height: 84vh; overflow: auto; background: var(--card); border: 1px solid var(--border); border-radius: 12px; z-index: 1001; display: none; box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
    .help-modal-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 18px; border-bottom: 1px solid var(--border); }
    .help-modal-title { font-weight: 700; color: var(--text); }
    .help-modal-close { background: none; border: none; color: var(--secondary); font-size: 18px; cursor: pointer; }
    .help-modal-body { padding: 18px; }
</style>

<div class="help-container">
    <div class="page-header">
        <div>
            <h1>📚 Help & User Guide</h1>
            <p>Complete guide to using the ABBIS system</p>
        </div>
    </div>

    <!-- Quick Navigation Categories -->
    <div style="margin-bottom: 30px;">
        <h2 style="margin-bottom: 20px; color: var(--text);">Browse Help Topics</h2>
        <div class="help-categories">
            <a href="#" class="help-category-card" onclick="openHelpTopic('dashboard'); return false;">
                <div class="icon">📊</div>
                <h3>Dashboard</h3>
                <p>Overview & metrics</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('field-reports'); return false;">
                <div class="icon">📋</div>
                <h3>Field Reports</h3>
                <p>Job documentation</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('hr'); return false;">
                <div class="icon">👥</div>
                <h3>Human Resources</h3>
                <p>Workforce management</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('crm'); return false;">
                <div class="icon">🤝</div>
                <h3>Clients & CRM</h3>
                <p>Relationship management</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('resources'); return false;">
                <div class="icon">📦</div>
                <h3>Resources</h3>
                <p>Materials, assets, maintenance</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('finance'); return false;">
                <div class="icon">💰</div>
                <h3>Finance</h3>
                <p>Payroll, loans, collections</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('pos'); return false;">
                <div class="icon">🛒</div>
                <h3>Point of Sale</h3>
                <p>Run retail counters & online orders</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('analytics'); return false;">
                <div class="icon">📈</div>
                <h3>Analytics</h3>
                <p>Business intelligence</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('search'); return false;">
                <div class="icon">🔍</div>
                <h3>Global Search</h3>
                <p>Find anything quickly</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('client-portal'); return false;">
                <div class="icon">🌐</div>
                <h3>Client Portal</h3>
                <p>Self-service portal & SSO</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('system-config'); return false;">
                <div class="icon">⚙️</div>
                <h3>System Config</h3>
                <p>Settings & configuration</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('deployment'); return false;">
                <div class="icon">🚀</div>
                <h3>Deployment & Backups</h3>
                <p>Migrate & safeguard ABBIS</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('accounting'); return false;">
                <div class="icon">📘</div>
                <h3>Accounting</h3>
                <p>Double-entry accounting</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('rig-tracking'); return false;">
                <div class="icon">🛰️</div>
                <h3>Rig Tracking</h3>
                <p>Live GPS & telemetry</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('ai-automation'); return false;">
                <div class="icon">🤖</div>
                <h3>AI & Automation</h3>
                <p>Assistant, insights, guardrails</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('customer-care'); return false;">
                <div class="icon">🛠️</div>
                <h3>Customer Care</h3>
                <p>Complaints & service recovery</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('executive-reports'); return false;">
                <div class="icon">🏛️</div>
                <h3>Executive Reports</h3>
                <p>Board packs & exports</p>
            </a>
            <a href="#" class="help-category-card" onclick="openHelpTopic('integrations'); return false;">
                <div class="icon">🔌</div>
                <h3>Integrations</h3>
                <p>Connect ABBIS to partners</p>
            </a>
        </div>
        <div style="margin-top: 16px;">
            <button class="btn btn-sm btn-outline" onclick="showFullGuide()">View Full Guide</button>
        </div>
    </div>

    <!-- Admin Tools & Maintenance -->
    <div id="admin-tools" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🧰 Admin Tools & Maintenance</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Data Integrity Utilities</h3>
                <ul>
                    <li><strong>RPM Validation:</strong> Client- and server‑side validation prevents unrealistic RPM values from corrupting rig totals.</li>
                    <li><strong>RPM Correction:</strong> Tools to correct historical entry errors and recalculations:
                        <ul>
                            <li><code>scripts/fix-rpm-data.php</code> — Corrects 100× decimal mistakes; supports <code>--yes</code> non‑interactive mode.</li>
                            <li><code>scripts/fix-rpm-data-refine.php</code> — Fixes logical cases where finish_rpm &lt; start_rpm and recomputes totals.</li>
                            <li><code>api/validate-rpm.php</code> — Validates problematic reports.</li>
                        </ul>
                    </li>
                    <li><strong>Maintenance Extraction:</strong> Automatically detects maintenance work from field reports and links to records.
                        <ul>
                            <li><code>includes/MaintenanceExtractor.php</code> — Extraction engine.</li>
                            <li><code>scripts/retroactive-maintenance-extraction.php</code> — Retroactively links historical reports.</li>
                        </ul>
                    </li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Assets & Catalog Hygiene</h3>
                <ul>
                    <li><strong>Duplicate Rig Assets:</strong> Prevents config rigs from duplicating existing assets in Resources → Assets.</li>
                    <li><code>scripts/fix-duplicate-rig-assets.php</code> — Identifies and removes duplicate rig rows safely.</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Exports & Scheduling</h3>
                <ul>
                    <li><code>api/dashboard-export.php</code> — Export dashboard datasets (CSV/JSON) with filters.</li>
                    <li><code>api/scheduled-reports.php</code> — Infrastructure endpoint for scheduled email reports (cron).</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Alerts & Notifications -->
    <div id="alerts" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🔔 Alerts & Notifications</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Dashboard Alerts</h3>
                <ul>
                    <li>Financial health (margins/ratios), outstanding debts, negative cash trends, maintenance due.</li>
                    <li>Animated, dismissible notices with links to drill‑downs.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Security & Commercialization Guidance -->
    <div id="commercialization" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💼 Security, Licensing & Commercialization</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Protect Your Deployment</h3>
                <ul>
                    <li><strong>Server Hardening:</strong> Disable directory listing; deny access to <code>docs/</code>, <code>scripts/</code>, <code>database/</code> over the web via web server rules.</li>
                    <li><strong>Secrets:</strong> Keep <code>config/database.php</code> out of VCS (already ignored); use environment‑specific configs.</li>
                    <li><strong>Code Access:</strong> Host source privately (GitHub private repo). Deploy using CI/CD artifacts, not raw git from production.</li>
                    <li><strong>Minify/Obfuscate front‑end:</strong> Serve minified JS/CSS; optional obfuscation for proprietary UI logic.</li>
                    <li><strong>License Header:</strong> Add proprietary license headers to PHP/JS files and a <code>LICENSE</code> in root.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Commercialization Options</h3>
                <ul>
                    <li><strong>Proprietary License:</strong> Ship binaries/assets, restrict redistribution, grant customer usage rights only.</li>
                    <li><strong>SaaS:</strong> Host ABBIS; charge per seat/rig/job. Keep code private; expose only the app.</li>
                    <li><strong>On‑Prem Subscription:</strong> Annual license + support/updates. Use license keys checked server‑side.</li>
                    <li><strong>Module Add‑Ons:</strong> Offer premium modules (Accounting, AI Forecasts, Analytics Export) as paid upgrades.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>License & Anti‑Piracy Measures</h3>
                <ul>
                    <li><strong>License File + Key:</strong> Store license in DB; verify on login and cron with signed tokens.</li>
                    <li><strong>Instance Fingerprinting:</strong> Bind license to domain + hardware/DB signature to prevent cloning.</li>
                    <li><strong>Call‑Home (optional):</strong> Periodic signed heartbeat to your licensing server with grace windows.</li>
                    <li><strong>Watermark Reports:</strong> Embed company name/license id subtly in PDFs/receipts.</li>
                    <li><strong>Audit Trail:</strong> Log admin exports; throttle bulk exports; per‑user API keys with rate limits.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Deployment & Backups -->
    <div id="deployment" class="help-section">
        <h2>🚀 Deployment & Backups</h2>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>ABBIS ships with command-line tooling to create release bundles, migrate to new servers, and protect your data with verified backups. All scripts live under <code>tools/deploy/</code> and use a shared configuration file <code>tools/deploy/config.php</code>.</p>
                <p>For printable step-by-step guides see <code>docs/deployment.md</code> and <code>docs/backup-restore.md</code>.</p>
            </div>

            <div class="help-item">
                <h3>Prerequisites</h3>
                <ul>
                    <li>Run commands via the project root on the application server (<code>php tools/deploy/…</code>).</li>
                    <li>Ensure PHP CLI has the <code>zip</code> extension and the MySQL client tools (<code>mysqldump</code>, <code>mysql</code>) are installed. Paths can be customised in <code>config.php</code>.</li>
                    <li>Keep at least 500&nbsp;MB of free disk space for temporary build artefacts.</li>
                    <li>Review and adjust <code>release_includes</code>, <code>global_excludes</code> and retention settings before first use.</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Create a Release Package</h3>
                <ol>
                    <li>Open a terminal in the ABBIS project directory.</li>
                    <li>Run:<br><code>php tools/deploy/package_release.php --env=staging --tag="v3.2.1"</code></li>
                    <li>The script gathers application files, exports the database (schema + data) and writes <code>build/releases/abbis-&lt;env&gt;-&lt;timestamp&gt;.zip</code>.</li>
                    <li>The archive includes <code>deployment-info.json</code> metadata and <code>scripts/post-deploy.sh</code> for target servers.</li>
                    <li>Optional flags:
                        <ul>
                            <li><code>--skip-db</code> – package files only.</li>
                            <li><code>--skip-files</code> – database dump only.</li>
                            <li><code>--env</code>, <code>--tag</code> – label the build.</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Deploy to a New Server</h3>
                <ol>
                    <li>Upload the generated ZIP to the new host and extract it in the web root.</li>
                    <li>Run the helper script:<br><code>bash scripts/post-deploy.sh</code></li>
                    <li>Provide the new public URL when prompted. The script updates <code>config/app.php</code> (<code>APP_URL</code>), clears caches and normalises permissions.</li>
                    <li>Optionally import <code>db/schema.sql</code> and <code>db/data.sql</code> directly from the script; supply database credentials when prompted.</li>
                    <li>After completion verify login, dashboards and critical workflows manually.</li>
                </ol>
                <p><strong>Tip:</strong> Keep the release archive alongside a pre-deployment backup for easy rollback.</p>
            </div>

            <div class="help-item">
                <h3>Create Backups</h3>
                <ol>
                    <li>Run:<br><code>php tools/deploy/backup.php backup --label=nightly --include-uploads</code></li>
                    <li>The utility stores <code>backups/abbis-backup-*.zip</code> containing:
                        <ul>
                            <li>Application files (same include list as releases).</li>
                            <li>Optional <code>uploads/</code> directory (toggle with <code>--include-uploads</code>).</li>
                            <li>Database dump (<code>db/backup.sql</code>).</li>
                            <li><code>backup-info.json</code> with metadata.</li>
                        </ul>
                    </li>
                    <li>Retention keeps the newest seven archives by default; adjust <code>retention.backups</code> as needed.</li>
                    <li>Schedule a cron job for automated nightly backups (see <code>docs/backup-restore.md</code> for examples).</li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Restore from Backup</h3>
                <ol>
                    <li>List available archives:<br><code>php tools/deploy/backup.php list</code></li>
                    <li>Restore a specific backup:<br><code>php tools/deploy/backup.php restore --file=backups/abbis-backup-20240101-020000-nightly.zip</code></li>
                    <li>The script extracts the archive, copies files back into the project (without overwriting environment-specific configs) and replays the MySQL dump using the credentials in <code>config/database.php</code>.</li>
                    <li>Post-restore, clear caches and test the system before reopening access to users.</li>
                </ol>
                <p>Always verify restored data on a staging environment before applying to production.</p>
            </div>

            <div class="help-item">
                <h3>Best Practices & Troubleshooting</h3>
                <ul>
                    <li><strong>Off-site storage:</strong> Copy <code>backups/</code> archives to secure cloud or NAS storage regularly.</li>
                    <li><strong>Disk usage:</strong> If packaging fails with “Insufficient disk space”, free space or update the temporary directory path.</li>
                    <li><strong>Command not found:</strong> Update MySQL binary paths in <code>tools/deploy/config.php</code> when running outside XAMPP.</li>
                    <li><strong>Release verification:</strong> Before handing over to clients, spin up the ZIP in a sandbox and run smoke tests.</li>
                    <li><strong>Documentation:</strong> Keep <code>docs/deployment.md</code> &amp; <code>docs/backup-restore.md</code> in sync with any process changes.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Resources Hub -->
    <div id="resources" class="help-section">
        <h2>📦 Resources Hub</h2>
        <div class="help-item">
            <h3>Catalog</h3>
            <ul>
                <li>Centralized products & services list with cost and selling prices</li>
                <li>Import sample list (58 items: pumps, services, materials, fees) via "Import Sample List" button</li>
                <li>Edit/delete any item, including imported ones</li>
                <li>Track price history automatically when prices change</li>
                <li>Used throughout system for expenses, receipts, and materials pricing</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Materials</h3>
            <ul>
                <li>Update Inventory: Choose "Use predefined material type" or "Select from Catalog"</li>
                <li>Catalog selection auto-fills unit cost; you can override</li>
                <li>Tracks quantities, unit costs, and usage from field reports</li>
                <li>Inventory transactions linked to catalog items for reporting</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Advanced Inventory</h3>
            <ul>
                <li>Views: Dashboard, Stock Levels, Transactions, Reorder Alerts, Analytics</li>
                <li>Low stock detection based on reorder levels and item status</li>
                <li>Theme-aware tables and quick actions</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Assets</h3>
            <ul>
                <li>Assets list, Categories, Depreciation, Reports, Asset Detail, Add/Edit forms</li>
                <li>Status tracking: Active, Maintenance, Inactive, Disposed</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Maintenance</h3>
            <ul>
                <li>Records, Schedule, Types, Analytics, Record Detail, Add/Edit forms</li>
                <li>Proactive and Reactive maintenance with costs linking to financials</li>
            </ul>
        </div>
    </div>

    <!-- Catalog -->
    <div id="catalog" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🗂️ Catalog - Products & Services</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Overview</h3>
            <p>The Catalog is a centralized list of all products and services your company buys or sells, with both cost (buying) and selling prices.</p>
            <p><strong>Access:</strong> Resources → Catalog tab</p>
        </div>

        <div class="help-item">
            <h3>Import Sample List</h3>
            <p>Quick start with 58 pre-configured items:</p>
            <ol>
                <li>Go to <strong>Resources → Catalog</strong></li>
                <li>Click <strong>Import Sample List</strong> button</li>
                <li>System seeds:
                    <ul>
                        <li><strong>Pumps:</strong> 15 different pump types (1HP, 1.5HP, 2HP, 3HP Superdub, MartPro, Donjin, Pedrolo, Stainless)</li>
                        <li><strong>Services & Construction:</strong> 24 services (Drilling, Blowing, Hand Pump, Hydrofracture, Logging, etc.)</li>
                        <li><strong>Materials & Parts:</strong> 19 products (Conduit pipes, PE Hose, PVC Pipes, Poly Tank, Reverse Osmosis, etc.)</li>
                        <li><strong>Fees & Taxes:</strong> With-holding tax</li>
                    </ul>
                </li>
            </ol>
            <p><strong>Note:</strong> You can re-run the import to update prices; existing items are updated, not duplicated.</p>
        </div>

        <div class="help-item">
            <h3>Adding Items</h3>
            <p>To add a new catalog item:</p>
            <ol>
                <li>Fill in the "New / Edit Item" form</li>
                <li>Set name, type (Product/Service), category, unit, cost price, selling price</li>
                <li>Check flags: Purchasable, Sellable, Active, Taxable</li>
                <li>Click <strong>Save Item</strong></li>
            </ol>
            <p><strong>Price History:</strong> When you update cost or selling price, changes are automatically logged for audit trails.</p>
        </div>

        <div class="help-item">
            <h3>Managing Categories</h3>
            <p>Organize items by category:</p>
            <ul>
                <li>Add categories using the form in the "Categories" card</li>
                <li>Assign items to categories for better organization</li>
                <li>Filter items by category in reports</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Where Catalog is Used</h3>
            <p>The catalog integrates seamlessly across the system:</p>
            <ul>
                <li><strong>Field Reports → Expenses:</strong> Select from catalog or enter custom items</li>
                <li><strong>Receipts:</strong> Shows itemized charges table when catalog items are used</li>
                <li><strong>Materials → Update Inventory:</strong> Select catalog products to auto-fill unit cost</li>
                <li><strong>Inventory Transactions:</strong> Linked to catalog for reporting and analysis</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Editing & Deleting</h3>
            <p>You can edit or delete any catalog item, including imported ones:</p>
            <ul>
                <li>Click <strong>Edit</strong> in the Items table to modify an item</li>
                <li>Click <strong>Delete</strong> to remove an item (confirmation required)</li>
                <li>Price changes are automatically tracked in price history</li>
            </ul>
        </div>
        </div>
    </div>

    <!-- Requests System -->
    <div id="requests" class="help-section">
        <h2>📋 Requests System - Quote & Rig Requests</h2>
        <div class="help-item">
            <h3>Overview</h3>
            <p>The Requests System handles two types of client requests:</p>
            <ul>
                <li><strong>📋 Request a Quote:</strong> For direct clients/homeowners who need complete borehole services (drilling, construction, mechanization, testing)</li>
                <li><strong>🚛 Request Rig:</strong> For agents and contractors who want to rent drilling rigs for their own projects</li>
            </ul>
            <p><strong>Access:</strong> Go to <strong>Requests Management</strong> from the main menu or <strong>CRM Dashboard</strong> → View requests</p>
        </div>
        
        <div class="help-item">
            <h3>📋 Quote Requests (Request a Quote)</h3>
            <p><strong>Who uses this:</strong> Direct clients and homeowners who need a complete borehole service.</p>
            <p><strong>What's included:</strong></p>
            <ul>
                <li><strong>Drilling:</strong> Borehole drilling with drilling machine</li>
                <li><strong>Construction:</strong> Installation of screen pipe, plain pipe, and gravels</li>
                <li><strong>Mechanization:</strong> Pump installation and accessories (can select preferred pumps from catalog)</li>
                <li><strong>Yield Test:</strong> Water yield testing with all details</li>
                <li><strong>Chemical Test:</strong> Laboratory water quality testing</li>
                <li><strong>Polytank Stand:</strong> Optional construction of polytank stand</li>
            </ul>
            <p><strong>Form Location:</strong> <code>/cms/quote</code> (public-facing form)</p>
            <p><strong>Management:</strong> <code>?module=requests&type=quote</code> (internal management)</p>
            <p><strong>Workflow:</strong></p>
            <ol>
                <li>Client fills out quote request form on CMS website</li>
                <li>Request is saved to <code>cms_quote_requests</code> table</li>
                <li>Client record is created/updated in CRM (if not existing)</li>
                <li>CRM follow-up is automatically created</li>
                <li>Request appears in CRM Dashboard and Requests Management</li>
                <li>Staff can update status: New → Contacted → Quoted → Converted/Rejected</li>
            </ol>
        </div>
        
        <div class="help-item">
            <h3>🚛 Rig Requests (Request Rig)</h3>
            <p><strong>Who uses this:</strong> Agents and contractors who don't have their own drilling rigs and want to rent one.</p>
            <p><strong>What's collected:</strong></p>
            <ul>
                <li>Requester details (name, email, phone, company)</li>
                <li>Location with Google Maps integration (click map or search)</li>
                <li>Number of boreholes to drill</li>
                <li>Estimated budget (optional)</li>
                <li>Preferred start date</li>
                <li>Urgency level (Low, Medium, High, Urgent)</li>
                <li>Additional notes</li>
            </ul>
            <p><strong>Form Location:</strong> <code>/cms/rig-request</code> (public-facing form)</p>
            <p><strong>Management:</strong> <code>?module=requests&type=rig</code> (internal management)</p>
            <p><strong>Workflow:</strong></p>
            <ol>
                <li>Contractor/agent fills out rig request form</li>
                <li>Request is saved to <code>rig_requests</code> table with auto-generated request number (RR-YYYYMMDD-####)</li>
                <li>Client record is created/updated if requester is existing client</li>
                <li>CRM follow-up is automatically created</li>
                <li>Request appears in CRM Dashboard and Requests Management</li>
                <li>Staff can update status: New → Under Review → Negotiating → Dispatched → Completed/Declined</li>
                <li>Staff can assign rig and assign user to handle the request</li>
                <li>When completed, request can be linked to field report</li>
            </ol>
        </div>
        
        <div class="help-item">
            <h3>Managing Requests</h3>
            <p><strong>Unified Requests Page:</strong> <code>?module=requests</code></p>
            <p>This page shows both quote and rig requests with filtering options:</p>
            <ul>
                <li><strong>Filter by Type:</strong> All, Quote Only, Rig Only</li>
                <li><strong>Filter by Status:</strong> All statuses or specific status</li>
                <li><strong>View Details:</strong> Click "View" button to see full request details in a modal</li>
                <li><strong>Edit Status:</strong> Click "Edit" button to update request status and assignments</li>
            </ul>
            <p><strong>CRUD Operations:</strong></p>
            <ul>
                <li><strong>Create:</strong> Done via CMS forms (public-facing)</li>
                <li><strong>Read:</strong> View all requests in table format with filters</li>
                <li><strong>Update:</strong> Edit status, assign rig, assign user, add internal notes</li>
                <li><strong>Delete:</strong> Delete requests (use with caution)</li>
            </ul>
        </div>
        
        <div class="help-item">
            <h3>CRM Integration</h3>
            <p>Both request types are fully integrated with the CRM system:</p>
            <ul>
                <li><strong>Auto Client Creation:</strong> Requests automatically create/update client records</li>
                <li><strong>Auto Follow-ups:</strong> CRM follow-ups are automatically scheduled for new requests</li>
                <li><strong>Dashboard Display:</strong> Both types appear on CRM Dashboard with clear distinction:
                    <ul>
                        <li><strong>📋 Request a Quote:</strong> Blue border and color (#0ea5e9)</li>
                        <li><strong>🚛 Request Rig:</strong> Green border and color (#059669)</li>
                    </ul>
                </li>
                <li><strong>Status Tracking:</strong> All status changes are tracked in client activities</li>
                <li><strong>Link to Finances:</strong> When requests are completed, they link to field reports and finances</li>
            </ul>
        </div>
        
        <div class="help-item">
            <h3>Request Statuses</h3>
            <p><strong>Quote Request Statuses:</strong></p>
            <ul>
                <li><strong>New:</strong> Just received, not yet contacted</li>
                <li><strong>Contacted:</strong> Initial contact made</li>
                <li><strong>Quoted:</strong> Quote has been sent to client</li>
                <li><strong>Converted:</strong> Quote accepted, job in progress</li>
                <li><strong>Rejected:</strong> Quote declined or request cancelled</li>
            </ul>
            <p><strong>Rig Request Statuses:</strong></p>
            <ul>
                <li><strong>New:</strong> Just received</li>
                <li><strong>Under Review:</strong> Being evaluated</li>
                <li><strong>Negotiating:</strong> Discussing terms and pricing</li>
                <li><strong>Dispatched:</strong> Rig has been assigned and dispatched</li>
                <li><strong>Completed:</strong> Job finished, can link to field report</li>
                <li><strong>Declined:</strong> Request rejected</li>
                <li><strong>Cancelled:</strong> Request cancelled by requester</li>
            </ul>
        </div>
        
        <div class="help-item">
            <h3>Google Maps Integration</h3>
            <p>The rig request form includes Google Maps integration for location selection:</p>
            <ul>
                <li><strong>Search Location:</strong> Type address in search box for autocomplete</li>
                <li><strong>Click on Map:</strong> Click anywhere on the map to set location</li>
                <li><strong>Drag Marker:</strong> Drag the marker to adjust location</li>
                <li><strong>Coordinates:</strong> Latitude and longitude are automatically saved</li>
                <li><strong>Manual Entry:</strong> Can also type address manually if map is unavailable</li>
            </ul>
            <p><strong>Note:</strong> Requires Google Maps API key configured in System → Map Providers</p>
        </div>
        
        <div class="help-item">
            <h3>Geology Estimator</h3>
            <p>Estimate expected drilling depth, water level, and lithology using historical wells.</p>
            <ul>
                <li><strong>Access:</strong> Open <strong>Resources → Geology Estimator</strong> (requires resources permission).</li>
                <li><strong>Dataset:</strong> Import geology wells via the onboarding wizard to feed the estimator with depth, lithology, aquifer, and yield data.</li>
                <li><strong>Usage:</strong> Drop a pin on the map or enter coordinates, adjust radius, and run the estimator. Nearby wells appear with distance, depth, and yield.</li>
                <li><strong>Outputs:</strong> Predicted depth range, recommended casing depth, average static water level, yield, lithology summary, and confidence score.</li>
                <li><strong>Logging:</strong> Predictions are saved to <code>geology_prediction_logs</code> for auditing and reporting.</li>
                <li><strong>API:</strong> Integrate with external tools via <code>POST /api/geology-estimate.php</code> (requires CSRF token).</li>
            </ul>
            <p><strong>Tip:</strong> Import more wells around your service area to increase confidence. The estimator automatically adjusts the search radius when data is sparse.</p>
        </div>
        
        <div class="help-item">
            <h3>Best Practices</h3>
            <ul>
                <li><strong>Quick Response:</strong> Review new requests within 24 hours</li>
                <li><strong>Update Status:</strong> Keep request status updated as you progress</li>
                <li><strong>Use Internal Notes:</strong> Add internal notes for team communication (not visible to requester)</li>
                <li><strong>Assign Resources:</strong> Assign rigs and users early in the process</li>
                <li><strong>Link to Reports:</strong> When rig requests are completed, link them to field reports</li>
                <li><strong>Follow CRM Workflow:</strong> Use CRM follow-ups to track communication</li>
            </ul>
        </div>
    </div>

    <!-- Dashboard -->
    <div id="dashboard" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📊 Dashboard</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>The Dashboard is your central command center, providing real-time insights into your business operations.</p>
                <p><strong>Access:</strong> Click "Dashboard" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>Key Features</h3>
                <ul>
                    <li><strong>Financial Overview:</strong> Revenue, expenses, profit, and cash flow metrics</li>
                    <li><strong>Performance Charts:</strong> Visual representation of revenue trends, profit margins, and operational metrics</li>
                    <li><strong>Recent Activity:</strong> Latest field reports, client interactions, and system updates</li>
                    <li><strong>Quick Actions:</strong> Fast access to create reports, add clients, and view analytics</li>
                    <li><strong>Alerts & Notifications:</strong> Important reminders for overdue payments, maintenance, and low stock</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Financial Metrics</h3>
                <ul>
                    <li><strong>Total Revenue:</strong> Sum of all income from field reports</li>
                    <li><strong>Total Expenses:</strong> Wages, materials, and operational costs</li>
                    <li><strong>Net Profit:</strong> Revenue minus expenses</li>
                    <li><strong>Profit Margin:</strong> Percentage of profit relative to revenue</li>
                    <li><strong>Cash Flow:</strong> Money in vs money out trends</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Charts & Visualizations</h3>
                <ul>
                    <li><strong>Revenue Chart:</strong> Monthly revenue trends with comparison to previous periods</li>
                    <li><strong>Profit Chart:</strong> Profit trends and margin analysis</li>
                    <li><strong>Export Options:</strong> Download charts as images or export data as CSV/Excel</li>
                    <li><strong>Date Range Filters:</strong> View data for specific time periods</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Field Reports -->
    <div id="field-reports" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📋 Field Reports</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Field Reports are the core of ABBIS - they capture all job details, financials, and operational data for each drilling job.</p>
                <p><strong>Access:</strong> Click "Field Reports" → "New Report" or "View Reports"</p>
            </div>
            <div class="help-item">
                <h3>Report Form Structure</h3>
                <p>The form is organized into 5 tabs for easy navigation:</p>
                <ol>
                    <li><strong>Job Information:</strong> Report ID, date, rig, site details, location, client</li>
                    <li><strong>Operational Details:</strong> Time tracking, RPM, depth, rods, materials used</li>
                    <li><strong>Workers & Wages:</strong> Worker assignments, roles, hours, wages, benefits</li>
                    <li><strong>Financials:</strong> Income, expenses, deposits, balances</li>
                    <li><strong>Additional Info:</strong> Remarks, incidents, solutions, recommendations</li>
                </ol>
            </div>
            <div class="help-item">
                <h3>Key Features</h3>
                <ul>
                    <li><strong>Auto-calculations:</strong> Duration, total RPM, depth, and financial totals calculate automatically</li>
                    <li><strong>Client Auto-extraction:</strong> New clients are automatically created from field reports</li>
                    <li><strong>Location Picker:</strong> Google Maps integration for precise location selection</li>
                    <li><strong>Material Tracking:</strong> Automatically updates inventory when materials are used</li>
                    <li><strong>Rig Fee Debt:</strong> Automatically tracks outstanding rig fees</li>
                    <li><strong>RPM Tracking:</strong> Updates rig's cumulative RPM for maintenance scheduling</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Viewing Reports</h3>
                <ul>
                    <li><strong>Report List:</strong> View all reports with filters (date, rig, client, job type)</li>
                    <li><strong>Search:</strong> Search by report ID, site name, or client</li>
                    <li><strong>Export:</strong> Export reports to Excel or generate PDF receipts</li>
                    <li><strong>Edit/Delete:</strong> Modify existing reports or remove incorrect entries</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Best Practices</h3>
                <ul>
                    <li>Enter reports daily to maintain accurate records</li>
                    <li>Double-check RPM values before saving (affects maintenance schedules)</li>
                    <li>Link reports to clients for better tracking</li>
                    <li>Use location picker for accurate site coordinates</li>
                    <li>Record all workers and wages for payroll integration</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- HR System -->
    <div id="hr" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">👥 Human Resources (HR)</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>The HR module provides comprehensive workforce management, tracking employees, attendance, performance, and training.</p>
                <p><strong>Access:</strong> Click "HR" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>Dashboard</h3>
                <ul>
                    <li><strong>Key Statistics:</strong> Total employees, active workers, departments, positions</li>
                    <li><strong>Top Workers by Jobs:</strong> Most active workers with job counts and earnings</li>
                    <li><strong>Recent Worker Activity:</strong> Latest job assignments and field report participation</li>
                    <li><strong>Quick Links:</strong> Fast access to all HR features</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Employees Management</h3>
                <ul>
                    <li><strong>Employee List:</strong> View all workers with roles, departments, positions, and job statistics</li>
                    <li><strong>Add Employee:</strong> Register new workers with full details (name, code, role, department, position, contact)</li>
                    <li><strong>Edit Employee:</strong> Update worker information, roles, assignments, and status</li>
                    <li><strong>Delete Employee:</strong> Remove workers (safely handles existing records)</li>
                    <li><strong>Worker Detail Page:</strong> Comprehensive view of individual worker:
                        <ul>
                            <li>Personal information and assignments</li>
                            <li>Job statistics (total jobs, monthly jobs, total wages)</li>
                            <li>Weekly job summary (last 12 weeks)</li>
                            <li>Complete job history with links to field reports</li>
                        </ul>
                    </li>
                    <li><strong>Job Tracking:</strong> System-wide integration shows all jobs each worker has completed</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Departments</h3>
                <ul>
                    <li><strong>Department Management:</strong> Create and manage organizational departments</li>
                    <li><strong>Features:</strong> Department code, name, description, manager assignment</li>
                    <li><strong>Employee Count:</strong> See how many employees belong to each department</li>
                    <li><strong>Status Control:</strong> Activate/deactivate departments</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Positions</h3>
                <ul>
                    <li><strong>Position Management:</strong> Define job positions and roles</li>
                    <li><strong>Features:</strong> Position code, title, department assignment, salary range, description</li>
                    <li><strong>Employee Linking:</strong> Link workers to positions for organizational structure</li>
                    <li><strong>CRUD Operations:</strong> Full create, read, update, delete functionality</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Roles Management</h3>
                <ul>
                    <li><strong>Worker Roles:</strong> Define roles like Driller, Rig Driver, Rodboy, etc.</li>
                    <li><strong>System vs Custom Roles:</strong> System roles are protected, custom roles can be modified</li>
                    <li><strong>Role Usage:</strong> See how many workers are assigned to each role</li>
                    <li><strong>Role Updates:</strong> When updating role names, system automatically updates all workers using that role</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Attendance</h3>
                <ul>
                    <li><strong>Record Attendance:</strong> Track daily attendance for employees</li>
                    <li><strong>Features:</strong> Date, time in/out, total hours, overtime, attendance status (present/absent/late)</li>
                    <li><strong>Auto-calculation:</strong> Hours and overtime calculated automatically from time in/out</li>
                    <li><strong>Attendance History:</strong> View and edit past attendance records</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Leave Management</h3>
                <ul>
                    <li><strong>Leave Types:</strong> Annual, sick, casual, unpaid, and custom leave types</li>
                    <li><strong>Request Leave:</strong> Employees can request leave with dates and reason</li>
                    <li><strong>Approval Workflow:</strong> Approve or reject leave requests</li>
                    <li><strong>Leave Balance:</strong> Track remaining leave days per employee</li>
                    <li><strong>Leave History:</strong> Complete record of all leave requests and approvals</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Performance Reviews</h3>
                <ul>
                    <li><strong>Review Types:</strong> Annual, quarterly, probationary, project-based</li>
                    <li><strong>Review Components:</strong> Overall rating, strengths, areas for improvement, goals, recommendations</li>
                    <li><strong>Reviewer Assignment:</strong> Assign managers or supervisors to conduct reviews</li>
                    <li><strong>Review History:</strong> Track all performance reviews for each employee</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Training Records</h3>
                <ul>
                    <li><strong>Training Types:</strong> Internal, external, certification, on-the-job</li>
                    <li><strong>Training Details:</strong> Title, provider, dates, duration, cost, certificate information</li>
                    <li><strong>Status Tracking:</strong> Pending, in progress, completed, cancelled</li>
                    <li><strong>Certificate Management:</strong> Track certificate numbers and expiry dates</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Stakeholders</h3>
                <ul>
                    <li><strong>Stakeholder Types:</strong> Supplier, partner, regulator, community leader, government official</li>
                    <li><strong>Stakeholder Management:</strong> Track all external stakeholders and their relationships</li>
                    <li><strong>Communications:</strong> Log all interactions with stakeholders</li>
                    <li><strong>Contact Information:</strong> Full contact details and organization information</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>System-wide Integration</h3>
                <ul>
                    <li><strong>Job Tracking:</strong> All field reports are linked to workers, showing complete job history</li>
                    <li><strong>Weekly Job Summary:</strong> View jobs per week for each worker</li>
                    <li><strong>Wage Tracking:</strong> Total wages earned from all jobs</li>
                    <li><strong>Performance Metrics:</strong> Jobs per month, total jobs, rigs worked on, clients served</li>
                    <li><strong>Search Integration:</strong> Search for workers and their job history from global search</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- CRM -->
    <div id="crm" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🤝 Clients & CRM</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>The CRM (Customer Relationship Management) system helps you manage client relationships, communications, and sales pipeline.</p>
                <p><strong>Access:</strong> Click "Clients" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>CRM Dashboard</h3>
                <ul>
                    <li><strong>Key Metrics:</strong>
                        <ul>
                            <li>Total clients and active clients</li>
                            <li>Upcoming follow-ups (next 7 days)</li>
                            <li>Overdue follow-ups</li>
                            <li>Recent emails sent/received</li>
                            <li>New leads this month</li>
                        </ul>
                    </li>
                    <li><strong>Upcoming Follow-ups:</strong> List of scheduled follow-ups with priority indicators</li>
                    <li><strong>Quick Actions:</strong> Fast access to add clients, schedule follow-ups, and send emails</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Client Management</h3>
                <ul>
                    <li><strong>Client List:</strong> View all clients with status, job count, revenue, and last contact date</li>
                    <li><strong>Client Status:</strong> Lead, Prospect, Customer, Inactive</li>
                    <li><strong>Add Client:</strong> Create new clients with full details:
                        <ul>
                            <li>Company information (name, type, website, tax ID)</li>
                            <li>Contact details (person, phone, email, address)</li>
                            <li>Business details (industry, source, rating)</li>
                            <li>Assignment to team members</li>
                        </ul>
                    </li>
                    <li><strong>Edit Client:</strong> Update client information and status</li>
                    <li><strong>Client Detail View:</strong> Comprehensive client profile with:
                        <ul>
                            <li>Full client information and statistics</li>
                            <li>Multiple contacts per client</li>
                            <li>Follow-ups timeline</li>
                            <li>Email communication history</li>
                            <li>Activity log</li>
                            <li>Job history and financial summary</li>
                        </ul>
                    </li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Follow-ups Management</h3>
                <ul>
                    <li><strong>Follow-up Types:</strong> Call, Email, Meeting, Visit, Quote, Proposal, Other</li>
                    <li><strong>Priority Levels:</strong> Low, Medium, High, Urgent</li>
                    <li><strong>Status Tracking:</strong> Scheduled, Completed, Cancelled, Postponed</li>
                    <li><strong>Features:</strong>
                        <ul>
                            <li>Schedule follow-ups with date and time</li>
                            <li>Assign to team members</li>
                            <li>Set reminders and priorities</li>
                            <li>Record outcomes and notes</li>
                            <li>Filter by status, client, assigned user, or date range</li>
                            <li>View overdue follow-ups</li>
                        </ul>
                    </li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Email Communications</h3>
                <ul>
                    <li><strong>Email Tracking:</strong> Log all inbound and outbound emails</li>
                    <li><strong>Email Status:</strong> Sent, Delivered, Opened, Replied</li>
                    <li><strong>Send Email:</strong> Direct email to clients with:
                        <ul>
                            <li>Template support</li>
                            <li>Variable substitution ({{client_name}}, etc.)</li>
                            <li>Attachment support</li>
                            <li>HTML formatting</li>
                        </ul>
                    </li>
                    <li><strong>Email History:</strong> Complete log of all communications per client</li>
                    <li><strong>Filtering:</strong> Filter by client, direction (inbound/outbound), status, date</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Email Templates</h3>
                <ul>
                    <li><strong>Template Categories:</strong> Welcome, Follow-up, Quote, Proposal, Invoice, General, <strong>Rig Request</strong></li>
                    <li><strong>Template Variables:</strong> Use {{client_name}}, {{company_name}}, etc. for personalization</li>
                    <li><strong>Template Management:</strong> Create, edit, activate/deactivate templates</li>
                    <li><strong>Template Preview:</strong> Preview templates before sending</li>
                    <li><strong>Rig Request Toolkit:</strong> The new category ships with a "Rig Request Confirmation" template and variables like <code>{{request_number}}</code>, <code>{{assigned_rig}}</code>, <code>{{rig_code}}</code>, and <code>{{preferred_start_date}}</code> for instant communication with contractors.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Client Health & NPS</h3>
                <ul>
                    <li><strong>Health Score:</strong> Automated health scoring based on engagement and activity</li>
                    <li><strong>NPS Tracking:</strong> Net Promoter Score surveys and tracking</li>
                    <li><strong>Risk Indicators:</strong> Identify clients at risk of churning</li>
                    <li><strong>Engagement Metrics:</strong> Track client interaction frequency and quality</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Quote & Rig Requests</h3>
                <ul>
                    <li><strong>Quote Requests:</strong> Manage quote requests from potential clients</li>
                    <li><strong>Rig Requests:</strong> Handle rig dispatch requests with location tracking</li>
                    <li><strong>Request Status:</strong> Track requests from new to completed</li>
                    <li><strong>Google Maps Integration:</strong> Location selection for rig requests</li>
                    <li><strong>CRM Integration:</strong> Requests automatically create CRM entries and follow-ups</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Best Practices</h3>
                <ul>
                    <li>Schedule follow-ups immediately after client contact</li>
                    <li>Use email templates for consistent communication</li>
                    <li>Update client status as relationships progress</li>
                    <li>Record all client interactions in activities</li>
                    <li>Assign clients to team members for accountability</li>
                    <li>Review overdue follow-ups daily</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Finance -->
    <div id="finance" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💰 Finance</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>The Finance module manages all financial aspects including payroll, loans, collections, and debt recovery.</p>
                <p><strong>Access:</strong> Click "Finance" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>Payroll Management</h3>
                <ul>
                    <li><strong>Payroll Entries:</strong> Record wages for workers from field reports</li>
                    <li><strong>Worker Integration:</strong> Payroll automatically linked to HR workers</li>
                    <li><strong>Wage Calculation:</strong> Based on hours worked and rates from field reports</li>
                    <li><strong>Payroll History:</strong> Complete record of all wage payments</li>
                    <li><strong>Payslip Generation:</strong> Generate payslips for workers</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Loans Management</h3>
                <ul>
                    <li><strong>Loan Types:</strong> Employee loans, client loans, equipment loans</li>
                    <li><strong>Loan Tracking:</strong> Principal, interest, repayment schedule, status</li>
                    <li><strong>Repayment Records:</strong> Track all loan repayments</li>
                    <li><strong>Loan Status:</strong> Active, paid off, defaulted, written off</li>
                    <li><strong>Loan Reports:</strong> Outstanding loans, repayment schedules, loan history</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Collections</h3>
                <ul>
                    <li><strong>Outstanding Debts:</strong> Track all money owed to the company</li>
                    <li><strong>Collection Tracking:</strong> Record collection attempts and payments</li>
                    <li><strong>Payment Plans:</strong> Set up installment payment plans for clients</li>
                    <li><strong>Collection Reports:</strong> Aging reports, collection efficiency, outstanding balances</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Debt Recovery</h3>
                <ul>
                    <li><strong>Debt Tracking:</strong> Monitor all outstanding debts</li>
                    <li><strong>Recovery Actions:</strong> Log recovery attempts and outcomes</li>
                    <li><strong>Legal Actions:</strong> Track legal proceedings for debt recovery</li>
                    <li><strong>Recovery Reports:</strong> Success rates, recovery timelines, outstanding amounts</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Analytics -->
    <div id="analytics" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📊 Advanced Analytics</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Advanced Analytics provides comprehensive business intelligence with detailed visualizations and insights.</p>
                <p><strong>Access:</strong> Click "Analytics" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>Analytics Tabs</h3>
                <ul>
                    <li><strong>Overview:</strong> High-level metrics and KPIs</li>
                    <li><strong>Financial Analysis:</strong> Revenue, expenses, profit analysis</li>
                    <li><strong>Operational Metrics:</strong> Jobs, rigs, workers, efficiency metrics</li>
                    <li><strong>Performance Analysis:</strong> Client performance, worker performance, rig utilization</li>
                    <li><strong>Forecast & Trends:</strong> Predictive analytics and trend analysis</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Financial Analysis</h3>
                <ul>
                    <li><strong>Income vs Expenses:</strong> Comparative charts showing revenue and costs</li>
                    <li><strong>Client Revenue Analysis:</strong> Revenue breakdown by client</li>
                    <li><strong>Job Type Profitability:</strong> Profit analysis by job type (direct vs subcontract)</li>
                    <li><strong>Financial Breakdown:</strong> Detailed income and expense categories</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Filters & Date Ranges</h3>
                <ul>
                    <li><strong>Date Range:</strong> Select start and end dates for analysis</li>
                    <li><strong>Quick Filters:</strong> Today, This Week, This Month, This Quarter, This Year, Last Month, Last Quarter, Last Year</li>
                    <li><strong>Group By:</strong> Month, Week, Day, Rig, Client, Job Type</li>
                    <li><strong>Rig Filter:</strong> Analyze specific rigs or all rigs</li>
                    <li><strong>Client Filter:</strong> Analyze specific clients or all clients</li>
                    <li><strong>Job Type Filter:</strong> Filter by direct jobs, subcontract jobs, or all</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Export Options</h3>
                <ul>
                    <li><strong>Export PDF:</strong> Generate PDF reports with all charts and data</li>
                    <li><strong>Export Excel:</strong> Export data to Excel for further analysis</li>
                    <li><strong>Chart Export:</strong> Export individual charts as images</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Global Search -->
    <div id="search" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🔍 Global Search</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Global Search allows you to quickly find any information across the entire ABBIS system.</p>
                <p><strong>Access:</strong> Use the search box in the header or click "Search" in the main menu</p>
            </div>
            <div class="help-item">
                <h3>Search Types</h3>
                <ul>
                    <li><strong>Field Reports:</strong> Search by report ID, site name, client, or location</li>
                    <li><strong>Clients:</strong> Search by client name, contact person, or company</li>
                    <li><strong>Workers:</strong> Search by worker name, role, or code</li>
                    <li><strong>Worker Jobs:</strong> Search for specific jobs by worker</li>
                    <li><strong>Assets:</strong> Search by asset name, code, or type</li>
                    <li><strong>Materials:</strong> Search by material name or type</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Search Features</h3>
                <ul>
                    <li><strong>Real-time Results:</strong> Results appear as you type</li>
                    <li><strong>Quick Links:</strong> Direct links to relevant pages from search results</li>
                    <li><strong>Context Information:</strong> See relevant details in search results</li>
                    <li><strong>Worker Detail Links:</strong> Search results link to comprehensive worker detail pages</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- System Configuration -->
    <div id="system-config" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">⚙️ System Configuration</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>System Configuration allows you to manage all system settings, rigs, materials, and company information.</p>
                <p><strong>Access:</strong> Click "System" → "Configuration" in the main navigation menu</p>
            </div>
            <div class="help-item">
                <h3>Rigs Management</h3>
                <ul>
                    <li><strong>Add/Edit Rigs:</strong> Manage all drilling rigs with full details</li>
                    <li><strong>Rig Information:</strong> Rig name, code, truck model, registration number, status</li>
                    <li><strong>RPM Maintenance Settings:</strong>
                        <ul>
                            <li>Current RPM (cumulative from field reports)</li>
                            <li>Maintenance RPM Interval (e.g., service every 30 RPM)</li>
                            <li>Next Maintenance Due At RPM</li>
                            <li>Automatic status calculation (OK, Due Soon, Due Now)</li>
                        </ul>
                    </li>
                    <li><strong>Rig Status:</strong> Active, Inactive, Maintenance</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Materials & Pricing</h3>
                <ul>
                    <li><strong>Material Types:</strong> View all materials with quantities and values</li>
                    <li><strong>Material Details:</strong> Quantity received, used, remaining, unit cost, total value</li>
                    <li><strong>Edit Materials:</strong> Update quantities, costs, and material information</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Rod Lengths</h3>
                <ul>
                    <li><strong>CRUD Operations:</strong> Create, read, update, and delete rod lengths</li>
                    <li><strong>Rod Length Management:</strong> Define available rod lengths for field reports</li>
                    <li><strong>Usage Tracking:</strong> See which rod lengths are used in field reports</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Company Information</h3>
                <ul>
                    <li><strong>Company Details:</strong> Name, tagline, contact information</li>
                    <li><strong>Company Logo:</strong> Upload and manage company logo</li>
                    <li><strong>Address & Contact:</strong> Physical address, phone, email, website</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Client Portal &amp; Online Payments</h3>
                <p>The Client Portal allows customers to review quotes, invoices, projects, and make secure online payments.</p>
                <h4>Full System Integration</h4>
                <ul>
                    <li><strong>ABBIS Clients:</strong> Fully integrated - uses same database table, direct user-client linking, full SSO support</li>
                    <li><strong>POS Customers:</strong> Fully integrated - POS sales linked to ABBIS clients, unified customer data</li>
                    <li><strong>CMS Customers:</strong> Fully integrated - automatic client creation from CMS orders and quote requests, portal access enabled automatically</li>
                </ul>
                <h4>Automatic Account Creation</h4>
                <ul>
                    <li>When a customer places an order on the CMS website, an ABBIS client account is automatically created</li>
                    <li>When a customer submits a quote request, an ABBIS client account is automatically created</li>
                    <li>A user account with ROLE_CLIENT is automatically created and linked to the client</li>
                    <li>Welcome email sent with portal access credentials (username and temporary password)</li>
                    <li>Clients can immediately access the portal to view quotes, invoices, payments, and order history</li>
                </ul>
                <h4>Unified Dashboard</h4>
                <ul>
                    <li>View ABBIS quotes, invoices, and payments</li>
                    <li>View CMS order history and status</li>
                    <li>View POS purchase history</li>
                    <li>All customer data in one unified view</li>
                </ul>
                <ul>
                    <li><strong>Access:</strong> Clients sign in at <code>/client-portal/login.php</code> using accounts with role <code>client</code>.</li>
                    <li><strong>Dashboard:</strong> Shows quote counts, outstanding balances, project summaries, and quick links.</li>
                    <li><strong>Invoices &amp; Payments:</strong> Clients can pay outstanding invoices with Paystack or Flutterwave. Offline options (mobile money, bank transfer, cash) log pending payments for staff follow-up.</li>
                    <li><strong>Detail Views:</strong> Dedicated quote and invoice pages list line items, payment history, and outstanding balances.</li>
                    <li><strong>Payment Flow:</strong> Clients choose an invoice, amount, and method. Gateway payments redirect to a secure checkout and return automatically.</li>
                    <li><strong>Accounting Sync:</strong> Completed payments automatically credit Accounts Receivable and debit the appropriate cash/bank account via journal entries.</li>
                    <li><strong>Email Alerts:</strong> Clients and the finance admin receive notifications when a payment is initiated and when it is confirmed.</li>
                    <li><strong>Document Downloads:</strong> Quote/invoice detail pages include download links for printable records.</li>
                    <li><strong>Configuration:</strong> Activate payment providers in <strong>CMS → Payment Methods</strong> and ensure API keys are stored in the method configuration (Paystack/Flutterwave).</li>
                    <li><strong>Audit Trail:</strong> All portal activity is stored in <code>client_portal_activities</code> and payment records live in <code>client_payments</code> for finance reconciliation.</li>
                </ul>
                <p><strong>Tip:</strong> Invite clients by creating users in ABBIS <em>Users</em> module, setting role to <code>client</code>, and linking them to the correct <code>clients</code> record (auto-linked by email).</p>
            </div>
        </div>
    </div>

    <!-- Accounting -->
    <div id="accounting" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📘 Accounting</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>General accounting (double-entry), integrated under Financial. Enable via System → Feature Management.</p>
                <ul>
                    <li>Chart of Accounts (Assets, Liabilities, Equity, Revenue, Expense)</li>
                    <li>Journal Entries with balanced debits/credits</li>
                    <li>Ledgers by account</li>
                    <li>Trial Balance</li>
                    <li>Profit &amp; Loss</li>
                    <li>Balance Sheet</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Automated Tracking</h3>
                <p>The Accounting Auto‑Tracker listens for operational events and posts balanced journal entries automatically.</p>
                <ul>
                    <li><strong>Field Reports & CMS Payments:</strong> Tracks income, expenses, and deposits captured through ABBIS or the CMS checkout.</li>
                    <li><strong>Asset Purchases:</strong> Resource acquisitions post to Fixed Assets with matching cash or payable entries.</li>
                    <li><strong>Payroll Runs:</strong> Payroll approval generates salary expense, liabilities, and cash payouts.</li>
                    <li><strong>Default Accounts:</strong> Use <strong>Accounting → Chart of Accounts</strong> to review and confirm the system-created chart of accounts before go-live.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Fiscal Periods Management</h3>
                <p><strong>Access:</strong> Finance → Accounting → Settings</p>
                <p>Fiscal periods define the time ranges for your accounting activities. They help organize financial data and prevent accidental backdating of transactions.</p>
                
                <h4>Creating Fiscal Periods</h4>
                <ol>
                    <li>Go to <strong>Accounting → Settings</strong></li>
                    <li>In the "Create New Fiscal Period" form, enter:
                        <ul>
                            <li><strong>Period Name:</strong> A descriptive name (e.g., "Q1 2025", "January 2025", "FY 2024")</li>
                            <li><strong>Start Date:</strong> The first day of the period</li>
                            <li><strong>End Date:</strong> The last day of the period</li>
                        </ul>
                    </li>
                    <li>Click <strong>Create Period</strong></li>
                </ol>
                
                <h4>Closing Fiscal Periods</h4>
                <p>When a period is complete and all transactions are finalized:</p>
                <ol>
                    <li>Find the period in the table (it will show as "Open")</li>
                    <li>Click the <strong>🔒 Close Period</strong> button</li>
                    <li>Confirm the action</li>
                </ol>
                <p><strong>Important:</strong> Once closed, no new transactions can be added to that period. This ensures data integrity for financial reporting and audits.</p>
                
                <h4>Period Types</h4>
                <ul>
                    <li><strong>Monthly:</strong> e.g., "January 2025" (Jan 1-31, 2025)</li>
                    <li><strong>Quarterly:</strong> e.g., "Q1 2025" (Jan 1 - Mar 31, 2025)</li>
                    <li><strong>Annual:</strong> e.g., "FY 2024" (Jan 1 - Dec 31, 2024)</li>
                </ul>
                
                <h4>Best Practices</h4>
                <ul>
                    <li>Create periods in advance (e.g., create all months for the year)</li>
                    <li>Close periods only after all transactions for that period are complete</li>
                    <li>Ensure periods don't overlap (the system will prevent this)</li>
                    <li>Use descriptive names that clearly identify the time period</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Accounting Tabs & Navigation</h3>
                <p>The Accounting module has several tabs for different functions:</p>
                <ul>
                    <li><strong>📊 Dashboard:</strong> Overview of accounting status, balance sheet summary, and key metrics</li>
                    <li><strong>📚 Chart of Accounts:</strong> Manage your account structure (Assets, Liabilities, Equity, Revenue, Expenses)</li>
                    <li><strong>🧾 Journal:</strong> View and create journal entries (double-entry bookkeeping)</li>
                    <li><strong>📑 Ledgers:</strong> Account-level transaction history</li>
                    <li><strong>🧮 Trial Balance:</strong> Verify that debits equal credits</li>
                    <li><strong>📈 P&L:</strong> Profit & Loss statement</li>
                    <li><strong>🏦 Balance Sheet:</strong> Assets, liabilities, and equity snapshot</li>
                    <li><strong>🔌 Integrations:</strong> Connect to external accounting software (QuickBooks, Zoho Books)</li>
                    <li><strong>⚙️ Settings:</strong> Manage fiscal periods and accounting configuration</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Initialize Historical Data</h3>
                <p>On the Accounting Dashboard, click <strong>Initialize Accounting System</strong> to backfill historic journal entries from existing field reports, resource purchases, loans, and payroll records.</p>
                <ul>
                    <li>Runs once per environment; safe to re-run if new historic data is imported.</li>
                    <li>Progress and any skipped records are shown in the modal while the job executes.</li>
                    <li>After initialization, review the Trial Balance to confirm opening balances.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>QuickBooks & Zoho Books OAuth</h3>
                <p>Use <strong>Accounting → Integrations</strong> to securely connect to cloud ledgers.</p>
                <ol>
                    <li>Enter the <strong>Client ID</strong> and <strong>Client Secret</strong> issued by Intuit or Zoho.</li>
                    <li>Click <strong>Connect</strong>. A new window opens for OAuth consent; sign in and approve the ABBIS app.</li>
                    <li>After approval, ABBIS stores encrypted access and refresh tokens and shows the connection status, company, and token expiry.</li>
                    <li>Use <strong>Disconnect</strong> to revoke a tenant or <strong>Refresh Token</strong> to renew credentials manually.</li>
                </ol>
                <p>Secrets are stored with AES‑256 encryption; you will see masked values once saved.</p>
            </div>
            <div class="help-item">
                <h3>Sync & Export</h3>
                <p>From the Integrations page choose <strong>Sync Journals</strong> to export batched journal entries.</p>
                <ul>
                    <li><strong>QuickBooks Online:</strong> ABBIS creates matching journal entries via the Accounting API.</li>
                    <li><strong>Zoho Books:</strong> Exports line items, tax codes, and memo fields according to your chart mapping.</li>
                    <li><strong>Manual Download:</strong> Use <strong>Export CSV</strong> if API credentials are unavailable; import the file inside your accounting suite.</li>
                    <li>Review the sync log on the same page for success, warnings, or items that need manual mapping.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Map Providers -->
    <div id="maps" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🗺️ Map Providers</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Configure Provider</h3>
                <p>System → APIs: Map Providers lets you choose Google or Leaflet (OpenStreetMap) for the field reports map picker.</p>
                <ul>
                    <li>Google Maps requires a valid API key (Maps JavaScript &amp; Places).</li>
                    <li>Leaflet (OSM) works without a key and uses Nominatim for search/reverse geocoding.</li>
                </ul>
                <p>When you select a location (search, click, or drag), the form auto-fills <strong>Latitude</strong>, <strong>Longitude</strong>, and the <strong>Plus Code</strong>.</p>
            </div>
        </div>
    </div>

    <!-- AI & ML -->
    <div id="ai" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🤖 AI & ML</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>What it does</h3>
                <ul>
                    <li><strong>Dashboard → AI Insights:</strong> 30/60/90‑day cash flow forecast (Net, Inflow, Outflow)</li>
                    <li><strong>Resources → AI Insights:</strong> 30‑day materials demand (screen/plain pipes, gravel)</li>
                </ul>
                <p>Practical baseline estimates using your recent reports; they update as you add data.</p>
            </div>
            <div class="help-item">
                <h3>How to use</h3>
                <ul>
                    <li><strong>Cash flow:</strong> plan deposits/payments and collections</li>
                    <li><strong>Materials:</strong> draft reorders and pre‑position stock</li>
                    <li>Use as advisory; validate before committing actions</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Improve accuracy</h3>
                <ul>
                    <li>Enter recent field reports and financials</li>
                    <li>Optionally schedule nightly persistence to <code>forecasts_*</code></li>
                </ul>
            </div>
            <div class="help-item">
                <h3>APIs</h3>
                <ul>
                    <li><code>api/ai-service.php?action=forecast_cashflow</code></li>
                    <li><code>api/ai-service.php?action=forecast_materials</code></li>
                    <li><code>api/ai-service.php?action=lead_score&client_id={id}</code></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Smart Job Planner -->
    <div id="planner" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🗓️ Smart Job Planner</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Auto-build a 2–4 week rig schedule balancing crew capacity, travel distance, job urgency, and estimated profit. Open via <strong>Resources → Smart Job Planner</strong>.</p>
            </div>
            <div class="help-item">
                <h3>How it works</h3>
                <ol>
                    <li>Select planning horizon (2 or 4 weeks), start date, and optimization objective.</li>
                    <li>The planner groups pending rig requests, calculates travel distances, and proposes a daily sequence for each active rig.</li>
                    <li>Jobs with coordinates are plotted on the route map; missing locations are flagged so you can update them.</li>
                </ol>
            </div>
            <div class="help-item">
                <h3>Dispatch & assignments</h3>
                <ul>
                    <li>Use <strong>Dispatch Rig</strong> to lock in the recommendation. The system records the plan in <code>rig_schedule_plan</code> and updates the request status to <em>Dispatched</em>.</li>
                    <li>Each dispatch captures rig, scheduled date, travel distance, and sequence. Re-dispatching updates the existing plan.</li>
                    <li>Status changes respect existing completion/cancellation states.</li>
                </ul>
            </div>
            <div class="help-item">
                <h3>Tips</h3>
                <ul>
                    <li>Add latitude/longitude to rig requests (CMS quote/rig forms support auto-capture) for accurate routing.</li>
                    <li>Update rig locations from field reports or the rig tracking module to improve starting points.</li>
                    <li>Switch objectives to tune planning behaviour: <em>Balance</em>, <em>Maximize profit</em>, or <em>Minimize distance</em>.</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Supplier Intelligence -->
    <div id="suppliers" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🏭 Supplier Intelligence</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Rank suppliers by timeliness, price stability, and defects; generate draft POs. Open via Resources → Suppliers.</p>
            </div>
        </div>
    </div>

    <!-- Client Health & NPS -->
    <div id="crm-health" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💚 Client Health & NPS</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Send quick post‑job feedback, compute health, and trigger CRM follow‑ups. Access from CRM → Health (if enabled).</p>
            </div>
        </div>
    </div>

    <!-- Field Offline Mode -->
    <div id="offline" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📶 Offline Field Reports</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>What is Offline Field Reports?</h3>
                <p>
                    The Offline Field Reports feature allows you to capture complete field reports <strong>completely offline</strong> - no internet connection required at all! 
                    All data is stored locally on your device (computer, tablet, or phone) and automatically syncs to the server when you reconnect to the internet.
                </p>
                <p><strong>Key Benefits:</strong></p>
                <ul>
                    <li>✅ Works 100% offline - no internet needed to save reports</li>
                    <li>✅ Save the page locally on any computer and use it anytime</li>
                    <li>✅ All data stored securely on your device</li>
                    <li>✅ Automatic sync when internet connection is restored</li>
                    <li>✅ Manual sync button for immediate synchronization</li>
                    <li>✅ No data loss - reports are saved even if browser closes</li>
                    <li>✅ <strong>Excel backup protection</strong> - automatic Excel backups created on every save (secondary data layer)</li>
                    <li>✅ <strong>Excel restore capability</strong> - import Excel backups to recover or transfer data</li>
                </ul>
                <p><strong>Access:</strong> Navigate to <code>http://your-domain/abbis3.2/offline</code> in your browser. You can save this page locally and use it even without internet!</p>
            </div>

            <div class="help-item">
                <h3>How It Works</h3>
                <ol>
                    <li><strong>Offline Capture:</strong> Fill out the complete field report form (all 5 tabs) while offline</li>
                    <li><strong>Local Storage:</strong> Data is saved to your device's browser storage (localStorage)</li>
                    <li><strong>Automatic Sync:</strong> When you reconnect to the internet, reports automatically sync to the server</li>
                    <li><strong>Conflict Resolution:</strong> If duplicates are detected, you'll be prompted to resolve conflicts</li>
                    <li><strong>Full Integration:</strong> Synced reports appear in your main ABBIS system just like online reports</li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Getting Started</h3>
                <h4>Step 1: Access the Offline Form</h4>
                <ul>
                    <li><strong>Online:</strong> Navigate to <code>http://your-domain/abbis3.2/offline</code></li>
                    <li><strong>Offline:</strong> Save the page to your computer (File → Save As) and open it anytime, even without internet!</li>
                    <li>For best experience, add to home screen (mobile) or bookmark (desktop)</li>
                </ul>

                <h4>Step 2: First-Time Setup</h4>
                <ul>
                    <li>The page will automatically register a Service Worker for offline caching (when online)</li>
                    <li>Allow browser notifications if prompted (for sync status updates)</li>
                    <li>The form works immediately - no installation required</li>
                    <li><strong>Important:</strong> You must be logged into ABBIS when you first access the page (for authentication), but after that, you can use it offline</li>
                </ul>

                <h4>Step 3: Using Offline</h4>
                <ul>
                    <li>Fill out reports completely offline - no internet needed</li>
                    <li>Click "💾 Save Offline" to store reports locally</li>
                    <li>Reports are saved to your browser's local storage</li>
                    <li>You can close the browser and reopen later - all saved reports remain</li>
                </ul>

                <h4>Step 4: Syncing When Online</h4>
                <ul>
                    <li>When you have internet connection, the status will show "🟢 Online"</li>
                    <li>Click the "🔄 Sync Now" button to manually sync all pending reports</li>
                    <li>Or wait for automatic sync (happens every 30 seconds when online)</li>
                    <li>Check the "Last sync" timestamp to confirm successful sync</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Using the Offline Form</h3>
                <h4>Form Structure (5 Tabs)</h4>
                <ol>
                    <li><strong>Management Tab:</strong>
                        <ul>
                            <li>Report date, rig name/code, job type, site name</li>
                            <li>Client information (name, contact, email)</li>
                            <li>Location (latitude, longitude, plus code, description)</li>
                            <li>Supervisor, total workers</li>
                            <li>Maintenance work checkbox and details (if applicable)</li>
                        </ul>
                    </li>
                    <li><strong>Drilling Tab:</strong>
                        <ul>
                            <li>Start/finish time (auto-calculates duration)</li>
                            <li>Start/finish RPM (auto-calculates total RPM)</li>
                            <li>Rod length and rods used (auto-calculates total depth)</li>
                            <li>Materials provided by (client/company/material shop)</li>
                            <li>Materials received (screen pipes, plain pipes, gravel)</li>
                            <li>Materials used (auto-calculates construction depth)</li>
                            <li>Compliance checkbox</li>
                        </ul>
                    </li>
                    <li><strong>Workers Tab:</strong>
                        <ul>
                            <li>Click "+ Add Worker" to add payroll entries</li>
                            <li>Worker name, role, wage type (daily/per borehole/hourly/custom)</li>
                            <li>Units, rate, benefits, loan reclaim</li>
                            <li>Amount auto-calculates: (units × rate) + benefits - loan reclaim</li>
                            <li>Paid today checkbox, notes</li>
                        </ul>
                    </li>
                    <li><strong>Financial Tab:</strong>
                        <ul>
                            <li><strong>Money Inflow (+):</strong> Balance B/F, Contract Sum, Rig Fee Expected, Rig Fee Collected, Cash Received, Materials Income</li>
                            <li><strong>Money Outflow (-):</strong> Materials Cost, MoMo to Company, Cash to Company, Bank Deposit</li>
                            <li><strong>Daily Expenses:</strong> Click "+ Add Expense" for detailed expense entries
                                <ul>
                                    <li>Description, unit cost, quantity (amount auto-calculates)</li>
                                    <li>Category (optional)</li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li><strong>Incidents Tab:</strong>
                        <ul>
                            <li>Incident Log (problems encountered)</li>
                            <li>Solution Log (how problems were resolved)</li>
                            <li>Recommendation Log (suggestions for future)</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Auto-Calculations</h3>
                <p>The offline form automatically calculates:</p>
                <ul>
                    <li><strong>Total Duration:</strong> From start time and finish time (in minutes)</li>
                    <li><strong>Total RPM:</strong> Finish RPM - Start RPM</li>
                    <li><strong>Total Depth:</strong> Rod Length × Rods Used</li>
                    <li><strong>Construction Depth:</strong> (Screen Pipes + Plain Pipes) × 3 meters</li>
                    <li><strong>Worker Amount:</strong> (Units × Rate) + Benefits - Loan Reclaim</li>
                    <li><strong>Expense Amount:</strong> Unit Cost × Quantity</li>
                </ul>
                <p><strong>Note:</strong> All calculations happen in real-time as you type.</p>
            </div>

            <div class="help-item">
                <h3>Saving Reports Offline</h3>
                <ol>
                    <li>Fill out all required fields (marked with *)</li>
                    <li>Click <strong>"💾 Save Offline"</strong> button</li>
                    <li>An <strong>Offline Report Saved</strong> summary modal opens showing drilling, operations, and financial highlights (depth, rods, duration, RPM change, workers, wages, income, expenses, profit, balance).</li>
                    <li>Review the snapshot, then click <strong>Close</strong> or press <strong>Esc</strong> to continue.</li>
                    <li>You'll also see a toast confirmation: "Report saved offline! Excel backup created automatically."</li>
                    <li>The form clears automatically (ready for next report)</li>
                    <li>Report appears in "Pending Reports" section</li>
                    <li><strong>Excel Backup:</strong> An Excel file is automatically created and downloaded when you save a report offline (secondary data protection layer)</li>
                </ol>
                <p><strong>Tip:</strong> The form auto-saves a draft every 30 seconds, so you won't lose your work if the page closes unexpectedly.</p>
            </div>

            <div class="help-item">
                <h3>📊 Excel Backup & Restore - Secondary Data Protection</h3>
                <p>
                    The offline mode includes a <strong>secondary data protection layer</strong> using Excel backups. This ensures you never lose data, even if browser storage is cleared or the device is lost.
                </p>
                
                <h4>Automatic Excel Backup</h4>
                <ul>
                    <li><strong>When:</strong> Every time you save a report offline, an Excel backup file is automatically created and downloaded</li>
                    <li><strong>Filename:</strong> <code>ABBIS_Offline_Backup_YYYY-MM-DD.xlsx</code> (includes date)</li>
                    <li><strong>Location:</strong> Downloads to your device's default download folder</li>
                    <li><strong>Content:</strong> Contains all pending reports, including workers and expenses data</li>
                    <li><strong>Purpose:</strong> Provides a backup copy that can be restored even if browser storage is lost</li>
                </ul>
                
                <h4>Manual Excel Export</h4>
                <p>You can manually export all pending reports at any time:</p>
                <ol>
                    <li>Click the <strong>"📥 Export All Reports to Excel"</strong> button in the Excel Backup section</li>
                    <li>An Excel file will be downloaded containing all your pending reports</li>
                    <li>The file includes multiple sheets:
                        <ul>
                            <li><strong>Field Reports:</strong> All report data in a flat table format</li>
                            <li><strong>Workers:</strong> All worker/payroll entries linked to reports</li>
                            <li><strong>Expenses:</strong> All expense entries linked to reports</li>
                            <li><strong>Metadata:</strong> Export date, report counts, and system version</li>
                        </ul>
                    </li>
                </ol>
                
                <h4>Importing from Excel Backup</h4>
                <p>You can restore reports from an Excel backup file in two ways:</p>
                
                <h5>Option 1: Client-Side Import (Works Offline)</h5>
                <ol>
                    <li>Click <strong>"📤 Import from Excel Backup"</strong> button</li>
                    <li>Select your Excel backup file (<code>.xlsx</code> or <code>.xls</code>)</li>
                    <li>The system will read the file and restore all reports to local storage</li>
                    <li>Reports are marked as "pending" and will sync when you go online</li>
                    <li><strong>Works completely offline</strong> - no internet required!</li>
                </ol>
                
                <h5>Option 2: Server-Side Import (When Online)</h5>
                <ol>
                    <li>When you're online, click <strong>"☁️ Import to Server (when online)"</strong> button</li>
                    <li>Select your Excel backup file</li>
                    <li>The file is uploaded to the server and reports are imported directly into ABBIS</li>
                    <li>Reports are immediately available in the system (no sync needed)</li>
                    <li>Accounting entries are automatically created for imported reports</li>
                </ol>
                
                <h4>Excel File Structure</h4>
                <p>The Excel backup file contains comprehensive data:</p>
                <ul>
                    <li><strong>Field Reports Sheet:</strong> All report fields including dates, sites, clients, rigs, financial data, drilling details, and logs</li>
                    <li><strong>Workers Sheet:</strong> All worker entries with names, roles, wages, benefits, and payment status</li>
                    <li><strong>Expenses Sheet:</strong> All expense entries with descriptions, costs, quantities, and categories</li>
                    <li><strong>Metadata Sheet:</strong> Export information including date, report counts, and system version</li>
                </ul>
                
                <h4>When to Use Excel Backups</h4>
                <ul>
                    <li><strong>Regular Backups:</strong> Export periodically (weekly/monthly) for long-term data protection</li>
                    <li><strong>Device Transfer:</strong> Move reports from one device to another by exporting on old device and importing on new device</li>
                    <li><strong>Data Recovery:</strong> Restore reports if browser storage is accidentally cleared</li>
                    <li><strong>Offline to Online Transfer:</strong> Export on offline device, transfer file to online device, then import</li>
                    <li><strong>Backup Before Clearing:</strong> Export before clearing browser cache or storage</li>
                </ul>
                
                <h4>Best Practices</h4>
                <ul>
                    <li><strong>Keep Multiple Backups:</strong> Save Excel files with different dates (e.g., weekly backups)</li>
                    <li><strong>Store Safely:</strong> Keep Excel backups on external drives, cloud storage, or email them to yourself</li>
                    <li><strong>Name Files Clearly:</strong> Use descriptive filenames like <code>ABBIS_Backup_2025-01-15.xlsx</code></li>
                    <li><strong>Regular Exports:</strong> Export all reports weekly or monthly for comprehensive backups</li>
                    <li><strong>Test Restores:</strong> Periodically test importing a backup to ensure the process works</li>
                </ul>
                
                <h4>Duplicate Prevention</h4>
                <p>The import system is smart about preventing duplicates:</p>
                <ul>
                    <li><strong>Client-Side Import:</strong> Skips reports with same date and site name (if already in local storage)</li>
                    <li><strong>Server-Side Import:</strong> Skips reports that already exist in the database</li>
                    <li><strong>Safe to Re-import:</strong> You can import the same file multiple times without creating duplicates</li>
                    <li><strong>Status Preservation:</strong> Already-synced reports are not overwritten by imports</li>
                </ul>
                
                <p><strong>💡 Pro Tip:</strong> The Excel backup feature provides a complete secondary data protection layer. Even if you lose access to your device or browser storage is cleared, you can always restore your reports from the Excel backup files!</p>
            </div>

            <div class="help-item">
                <h3>Sync Status Indicator</h3>
                <p>In the top-right corner, you'll see a sync status indicator with a manual sync button:</p>
                <ul>
                    <li><strong>🟢 Online:</strong> Connected to internet, ready to sync</li>
                    <li><strong>🔴 Offline:</strong> No internet connection, reports saved locally</li>
                    <li><strong>🟡 Syncing:</strong> Currently uploading reports to server</li>
                    <li><strong>Pending Count Badge:</strong> Shows number of reports waiting to sync</li>
                    <li><strong>Sync Now Button:</strong> Always visible when online - click to manually force sync</li>
                </ul>
                <p><strong>Manual Sync:</strong> The "🔄 Sync Now" button is always available when you're online. Click it to:</p>
                <ul>
                    <li>Force immediate sync of all pending reports</li>
                    <li>Check for any reports that need syncing</li>
                    <li>Verify your connection is working properly</li>
                </ul>
                <p><strong>When to use Manual Sync:</strong></p>
                <ul>
                    <li>After restoring internet connection and you want to sync immediately</li>
                    <li>If automatic sync didn't trigger (check connection first)</li>
                    <li>To verify all reports are up to date</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Managing Pending Reports</h3>
                <p>The "Pending Reports" section shows:</p>
                <ul>
                    <li><strong>Pending Sync:</strong> Reports waiting to be uploaded</li>
                    <li><strong>Synced Reports:</strong> Successfully uploaded reports (last 10 shown)</li>
                    <li><strong>Failed Reports:</strong> Reports that couldn't sync (with error messages)</li>
                </ul>
                <h4>Actions Available:</h4>
                <ul>
                    <li><strong>Edit:</strong> Click "Edit" to modify a pending report before syncing</li>
                    <li><strong>Delete:</strong> Click "Delete" to remove a report (confirmation required)</li>
                    <li><strong>View Status:</strong> Each report shows its sync status and any error messages</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Automatic Sync</h3>
                <p>When you reconnect to the internet:</p>
                <ol>
                    <li>System automatically detects online status (you'll see status change from 🔴 Offline to 🟢 Online)</li>
                    <li>Starts syncing pending reports in the background automatically</li>
                    <li>Shows progress in sync status indicator</li>
                    <li>Displays success/error notifications</li>
                    <li>Updates report status (pending → synced)</li>
                </ol>
                <p><strong>Sync Frequency:</strong> System checks for pending reports every 30 seconds when online and syncs automatically.</p>
                <p><strong>Manual Sync:</strong> You can also click the "🔄 Sync Now" button in the top-right corner at any time when online to force immediate synchronization. This is useful when:</p>
                <ul>
                    <li>You just restored internet connection and want to sync immediately</li>
                    <li>Automatic sync hasn't triggered yet</li>
                    <li>You want to verify all reports are synced</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Conflict Resolution</h3>
                <p>If a duplicate report is detected (same date, site, and rig):</p>
                <ol>
                    <li>A conflict modal appears automatically</li>
                    <li>You'll see both your local version and the server version</li>
                    <li>Choose an action:
                        <ul>
                            <li><strong>Use My Version:</strong> Overwrite server with your local data</li>
                            <li><strong>Use Server Version:</strong> Keep server data, discard local</li>
                            <li><strong>Merge:</strong> Combine both versions (coming soon)</li>
                            <li><strong>Skip:</strong> Skip this report, keep both versions</li>
                        </ul>
                    </li>
                    <li>Click "Resolve" to apply your choice</li>
                </ol>
            </div>

            <div class="help-item">
                <h3>What Data is Captured?</h3>
                <p>The offline form captures <strong>ALL</strong> data points from the full field report form:</p>
                <ul>
                    <li>✅ All management information (client, location, supervisor)</li>
                    <li>✅ Complete drilling details (times, RPM, depth, materials)</li>
                    <li>✅ Full payroll information (workers, wages, benefits)</li>
                    <li>✅ Complete financial data (income, expenses, deposits)</li>
                    <li>✅ All incident logs (incidents, solutions, recommendations)</li>
                    <li>✅ Maintenance work details (if applicable)</li>
                </ul>
                <p><strong>Result:</strong> Synced reports are identical to reports created online - no data loss!</p>
            </div>

            <div class="help-item">
                <h3>Best Practices</h3>
                <ul>
                    <li><strong>Save Frequently:</strong> Save reports as you complete them, don't wait until the end of the day</li>
                    <li><strong>Save Page Locally:</strong> You can save the offline page to your computer and use it without internet</li>
                    <li><strong>Excel Backups:</strong> Excel backups are automatically created on every save - keep these files safe as a secondary backup</li>
                    <li><strong>Regular Exports:</strong> Manually export all reports to Excel weekly or monthly for comprehensive backups</li>
                    <li><strong>Check Sync Status:</strong> Verify reports have synced before leaving the site (look for "Last sync" timestamp)</li>
                    <li><strong>Use Manual Sync:</strong> When you have internet, click "Sync Now" button to ensure all reports are uploaded</li>
                    <li><strong>Review Pending Reports:</strong> Check the pending list regularly to catch any sync issues</li>
                    <li><strong>Edit Before Sync:</strong> Review and edit reports while offline, before they sync</li>
                    <li><strong>Keep Browser Open:</strong> Keep the offline page open to allow automatic syncing when connection is restored</li>
                    <li><strong>Clear Old Reports:</strong> Delete successfully synced reports to free up storage space (optional) - <strong>but keep Excel backups!</strong></li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Troubleshooting</h3>
                <h4>Reports Not Syncing?</h4>
                <ul>
                    <li>Check internet connection (sync status should show "Online")</li>
                    <li>Verify you're logged into ABBIS (session required for sync)</li>
                    <li>Check browser console for error messages (F12 → Console)</li>
                    <li>Try manual sync: Click "Sync Now" button</li>
                    <li>Check if reports show error messages in pending list</li>
                </ul>

                <h4>Lost Data?</h4>
                <ul>
                    <li>Check "Pending Reports" section - reports are saved locally</li>
                    <li>Browser storage persists even after closing the page</li>
                    <li><strong>Restore from Excel Backup:</strong> If you have Excel backup files, use "Import from Excel Backup" to restore all reports</li>
                    <li>Check your Downloads folder for automatically created Excel backup files</li>
                    <li>Clear browser cache only if necessary (this will delete pending reports!) - <strong>Always export Excel backup first!</strong></li>
                </ul>

                <h4>Form Not Loading?</h4>
                <ul>
                    <li>Ensure you're accessing the correct URL</li>
                    <li>Check browser compatibility (modern browsers required)</li>
                    <li>Try clearing browser cache and reloading</li>
                    <li>Check if Service Worker is registered (F12 → Application → Service Workers)</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Technical Details</h3>
                <ul>
                    <li><strong>Storage:</strong> Uses browser localStorage (typically 5-10MB limit)</li>
                    <li><strong>Service Worker:</strong> Caches the form for offline access</li>
                    <li><strong>Sync API:</strong> <code>api/sync-offline-reports.php</code></li>
                    <li><strong>Authentication:</strong> Uses session cookies (must be logged into ABBIS)</li>
                    <li><strong>Data Format:</strong> JSON stored locally, sent to server as JSON</li>
                    <li><strong>Conflict Detection:</strong> Based on report date, site name, and rig</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Limitations</h3>
                <ul>
                    <li>File uploads (survey/contract documents) are not supported offline</li>
                    <li>Location picker map requires internet connection</li>
                    <li>Client/rig autocomplete requires internet connection</li>
                    <li>Storage limit depends on browser (typically 5-10MB)</li>
                    <li>Must be logged into ABBIS for sync to work</li>
                    <li><strong>Excel Export:</strong> Requires SheetJS library to be loaded (automatically included in offline page)</li>
                    <li><strong>Excel Import:</strong> Client-side import works offline; server-side import requires internet connection</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Collections Assistant -->
    <div id="collections" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📅 Collections Assistant</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Predict late payers and propose collection dates; schedule reminders. Open via Finance → Collections.</p>
            </div>
        </div>
    </div>

    <!-- Maintenance Digital Twin -->
    <div id="maint-digital-twin" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🧩 Maintenance Digital Twin</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Model asset state, estimate time‑to‑maintenance, and suggest JIT parts ordering. Open the module directly.</p>
            </div>
            <div class="help-item">
                <h3>Rig Telemetry & Alerts</h3>
                <p>Stream live RPM, temperature, vibration, and custom sensor data into ABBIS. Configure via <strong>Resources → Rig Telemetry</strong>:</p>
                <ol>
                    <li><strong>Create stream</strong>: generate a secure API token for each device and assign it to the relevant rig.</li>
                    <li><strong>Define thresholds</strong>: set warning/critical values that auto-create maintenance alerts when breached.</li>
                    <li><strong>Monitor dashboard</strong>: the live alert feed highlights critical readings, while the events table shows recent telemetry samples.</li>
                    <li><strong>Act</strong>: acknowledge or resolve alerts, optionally tying them to maintenance records for audit trails.</li>
                </ol>
                <p>Ingest data using <code>POST /api/rig-telemetry-ingest.php</code> with the provided stream token. See <strong>Documentation → Rig Maintenance Telemetry Guide</strong> for payload examples.</p>
            </div>
            <div class="help-item">
                <h3>Environmental Sampling Workflow</h3>
                <p>Capture water/soil sampling jobs with full chain-of-custody and lab results management:</p>
                <ol>
                    <li>Create a project in <strong>Resources → Environmental Sampling</strong> with client, site, schedule, and coordinates.</li>
                    <li>Add samples, noting matrix, collection method, preservative, and field observations.</li>
                    <li>Log every custody transfer—collection, storage, courier handoff, lab receipt—including temperature and condition.</li>
                    <li>Enter lab results with detection limits, methods, QA/QC flags, and analyst details. Attach certificates via file path if needed.</li>
                </ol>
                <p>Review project status (Draft → Scheduled → In Progress → Submitted → Completed) and export structured data via <code>/api/environmental-sampling-view.php?id=...</code>. See <strong>Documentation → Environmental Sampling Workflow Guide</strong> for migration steps and API usage.</p>
            </div>
        </div>
    </div>

    <!-- Executive Export Pack -->
    <div id="board-pack" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📦 Executive Export Pack</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>Export P&L, Balance Sheet, Cash Flow, KPIs, and top clients/risks as a monthly board pack (PDF/CSV). Open the module directly.</p>
            </div>
        </div>
    </div>

    <div class="help-search">
        <input type="text" id="helpSearch" placeholder="Search help topics..." 
               onkeyup="searchHelp(this.value)">
    </div>

    <div class="help-categories">
        <a href="#" class="help-category-card" onclick="openHelpTopic('getting-started'); return false;">
            <div class="icon">🚀</div>
            <h3>Getting Started</h3>
            <p>Quick start guide and basic navigation</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('field-reports'); return false;">
            <div class="icon">📝</div>
            <h3>Field Reports</h3>
            <p>Creating and managing field operation reports</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('financial'); return false;">
            <div class="icon">💰</div>
            <h3>Financial Management</h3>
            <p>Payroll, expenses, and financial tracking</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('accounting'); return false;">
            <div class="icon">📘</div>
            <h3>Accounting</h3>
            <p>COA, Journal, Ledger, Trial, P&L, Balance Sheet</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('clients'); return false;">
            <div class="icon">👥</div>
            <h3>Client Management</h3>
            <p>Managing clients and viewing transaction history</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('reports'); return false;">
            <div class="icon">📄</div>
            <h3>Reports & Receipts</h3>
            <p>Generating receipts and technical reports</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('payroll'); return false;">
            <div class="icon">💼</div>
            <h3>Payroll Management</h3>
            <p>Worker payments, payroll tracking, and payslip generation</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('hr'); return false;">
            <div class="icon">👥</div>
            <h3>Human Resources</h3>
            <p>Employee management, role assignments, and worker organization</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('dashboard'); return false;">
            <div class="icon">📊</div>
            <h3>Dashboard & Analytics</h3>
            <p>Understanding KPIs and data visualization</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('configuration'); return false;">
            <div class="icon">⚙️</div>
            <h3>Configuration</h3>
            <p>Setting up rigs, workers, materials, and company info</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('data-management'); return false;">
            <div class="icon">💾</div>
            <h3>Data Management</h3>
            <p>Import, export, and backup system data</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('resources'); return false;">
            <div class="icon">📦</div>
            <h3>Resources Hub</h3>
            <p>Materials, Advanced Inventory, Assets, Maintenance</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('documentation-hub'); return false;">
            <div class="icon">📚</div>
            <h3>Documentation Hub</h3>
            <p>Link to full project manuals & advanced modules</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('catalog'); return false;">
            <div class="icon">🗂️</div>
            <h3>Catalog</h3>
            <p>Products & services with pricing</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('planner'); return false;">
            <div class="icon">🗓️</div>
            <h3>Smart Job Planner</h3>
            <p>Auto-build 2–4 week drilling schedule</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('suppliers'); return false;">
            <div class="icon">🏭</div>
            <h3>Supplier Intelligence</h3>
            <p>Rank suppliers and draft POs</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('requests'); return false;">
            <div class="icon">📋</div>
            <h3>Requests System</h3>
            <p>Quote & rig requests</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('crm'); return false;">
            <div class="icon">🤝</div>
            <h3>CRM</h3>
            <p>Follow-ups, emails, templates</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('crm-health'); return false;">
            <div class="icon">💚</div>
            <h3>Client Health & NPS</h3>
            <p>Satisfaction checks and triggers</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('system-admin'); return false;">
            <div class="icon">🛠️</div>
            <h3>System & Admin</h3>
            <p>Users, API keys, Feature toggles</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('maps'); return false;">
            <div class="icon">🗺️</div>
            <h3>Map Providers</h3>
            <p>Google or Leaflet for location picker</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('ai'); return false;">
            <div class="icon">🤖</div>
            <h3>AI & ML</h3>
            <p>AI Insights, forecasts, and APIs</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('offline'); return false;">
            <div class="icon">📶</div>
            <h3>Field Offline Mode</h3>
            <p>Capture reports with no network</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('collections'); return false;">
            <div class="icon">📅</div>
            <h3>Collections Assistant</h3>
            <p>Predict late payers and dunning</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('maint-digital-twin'); return false;">
            <div class="icon">🧩</div>
            <h3>Maintenance Digital Twin</h3>
            <p>Asset state and windows</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('board-pack'); return false;">
            <div class="icon">📦</div>
            <h3>Executive Export Pack</h3>
            <p>One‑click monthly board pack</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('integrations'); return false;">
            <div class="icon">🔌</div>
            <h3>Integrations</h3>
            <p>Zoho, Looker Studio, ELK/Kibana, Wazuh</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('security'); return false;">
            <div class="icon">🔐</div>
            <h3>Security & Compliance</h3>
            <p>GDPR/Act 843, consent, sessions</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('accounts'); return false;">
            <div class="icon">👤</div>
            <h3>User Accounts</h3>
            <p>Profiles, social/phone login</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('branding'); return false;">
            <div class="icon">🎨</div>
            <h3>Theme & Branding</h3>
            <p>Dark/light mode, logo, favicon</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('search'); return false;">
            <div class="icon">🔍</div>
            <h3>Global Search</h3>
            <p>Quick search and advanced modal</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('cms'); return false;">
            <div class="icon">🌐</div>
            <h3>CMS & Website</h3>
            <p>Content management, e-commerce, and website features</p>
        </a>
        <a href="#" class="help-category-card" onclick="openHelpTopic('videos'); return false;">
            <div class="icon">🎥</div>
            <h3>Video Tutorials</h3>
            <p>Watch video guides and tutorials</p>
        </a>
    </div>

    <div class="help-controls">
        <button class="btn btn-outline btn-sm" onclick="expandAllSections()">Expand All</button>
        <button class="btn btn-outline btn-sm" onclick="collapseAllSections()">Collapse All</button>
    </div>

    <div class="help-sections-grid">

    <!-- Documentation Hub -->
    <div id="documentation-hub" class="help-section">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📚 Documentation Hub & Advanced Modules</h2>
            <span class="toggle-icon">▾</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Project Documentation Library</h3>
                <p>
                    For complete manuals, architecture reports, workflow guides, and advanced playbooks, open the new 
                    <strong>Documentation</strong> page in the main navigation. Each document is rendered in-app with headings, bullet lists, 
                    and code blocks, making it the authoritative source for ABBIS configuration and rollout.
                </p>
                <ul>
                    <li><strong>System Overview:</strong> Feature inventory, architecture summaries, and integration status.</li>
                    <li><strong>Core Workflows:</strong> Onboarding wizard, client portal payments, and data import guides.</li>
                    <li><strong>Advanced Modules:</strong> Geology Estimator, Regulatory Forms, Rig Telemetry, and Environmental Sampling manuals.</li>
                </ul>
                <p>
                    Navigate to <code>System → Documentation</code> or <code>modules/system.php?tab=documentation</code> → select a topic on the left to read live content and share deep links with your team.
                </p>
            </div>
            <div class="help-item">
                <h3>Resources → Advanced Features</h3>
                <p>
                    High-value modules such as Smart Job Planner, Regulatory Forms, Rig Telemetry, Environmental Sampling, and Geology Estimator
                    now live under <strong>Resources → Advanced Features</strong>. This keeps the main Resources tabs focused on daily inventory work while
                    giving power users a dedicated launchpad for spatial routing, compliance exports, and sensor analytics.
                </p>
                <ul>
                    <li><strong>Smart Job Planner:</strong> Map every pending job, optimize routes, and dispatch rigs visually.</li>
                    <li><strong>Regulatory Forms:</strong> Build HTML templates with merge tags, generate compliance packets, and track downloads.</li>
                    <li><strong>Rig Telemetry:</strong> Issue ingest tokens, monitor live sensor feeds, and trigger threshold alerts.</li>
                    <li><strong>Environmental Sampling:</strong> Capture sampling projects, chain-of-custody events, and lab results.</li>
                    <li><strong>Geology Estimator:</strong> Import historical wells and forecast drilling depth, difficulty, and aquifer likelihood.</li>
                </ul>
                <p>
                    Use this menu when referencing the DrillerDB-style interactive maps, telemetry dashboards, or regulatory exports discussed in the documentation.
                </p>
            </div>
        </div>
    </div>

    <!-- Getting Started -->
    <div id="getting-started" class="help-section">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🚀 Getting Started</h2>
            <span class="toggle-icon">▾</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>What is ABBIS?</h3>
            <p>
                <strong>ABBIS</strong> stands for <strong>Advanced Borehole Business Intelligence System</strong>. 
                It is a comprehensive management system designed specifically for borehole drilling operations.
            </p>
            <p>
                The system provides complete tracking and management of:
            </p>
            <ul>
                <li><strong>Field Reports:</strong> Detailed job records with technical specifications, locations, and timelines</li>
                <li><strong>Financial Management:</strong> Income, expenses, profit tracking, and comprehensive financial reporting</li>
                <li><strong>Payroll Management:</strong> Comprehensive worker payment processing, wage calculations, payroll tracking, and payslip generation</li>
                <li><strong>Materials Management:</strong> Inventory tracking, material costs, and pricing control</li>
                <li><strong>Client Management:</strong> Complete client database with transaction history and relationship tracking</li>
                <li><strong>Loans & Debts:</strong> Worker loans and rig fee debt management</li>
                <li><strong>Analytics & Dashboards:</strong> Real-time business intelligence, KPIs, and predictive insights</li>
                <li><strong>Reporting:</strong> Professional receipts/invoices and technical reports with unique job IDs</li>
            </ul>
            <p>
                ABBIS is built to help drilling companies manage their entire operation from field work to financial reporting, 
                providing the insights needed to make data-driven business decisions.
            </p>
        </div>

        <div class="help-item">
            <h3>System Navigation</h3>
            <p>The main navigation menu at the top provides access to all system features:</p>
            <ul>
                <li><strong>Dashboard</strong> - Overview of KPIs, analytics, and business intelligence</li>
                <li><strong>Field Reports</strong> - Create and manage field operation reports (online and offline)</li>
                <li><strong>Clients</strong> - Manage clients, CRM, follow-ups, and view transaction history</li>
                <li><strong>HR</strong> - Human resources management (staff, workers, stakeholders)</li>
                <li><strong>Resources</strong> - Materials, inventory, assets, maintenance, catalog</li>
                <li><strong>Finance</strong> - Financial management, payroll, loans, accounting, collections</li>
                <li><strong>System</strong> - Configuration, data management, users, API keys, integrations (Admin only)</li>
            </ul>
            <p><strong>Quick Actions:</strong> Use the "+ New Report" button in the header to quickly create a field report.</p>
            <p><strong>Offline Reports:</strong> Navigate to <code>/offline</code> to capture reports when offline.</p>
        </div>

        <div class="help-item">
            <h3>First Steps</h3>
            <ol>
                <li><strong>Configure Company Info:</strong> Go to Configuration → Company Info and add your company details and logo</li>
                <li><strong>Set Up Rigs:</strong> Configuration → Rigs Management → Add your drilling rigs</li>
                <li><strong>Add Workers:</strong> Configuration → Workers Management → Add your team members</li>
                <li><strong>Configure Materials:</strong> Configuration → Materials & Pricing → Set up materials inventory</li>
                <li><strong>Create Your First Report:</strong> Field Reports → Create New Report</li>
            </ol>
        </div>
        </div>
    </div>

    <!-- Field Reports -->
    <div id="field-reports" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📝 Field Reports</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Creating a Field Report</h3>
            <p>Field reports record all details of a drilling job. You can create reports online or offline:</p>
            <h4>Online (Standard Method):</h4>
            <ol>
                <li>Navigate to <strong>Field Reports</strong> → <strong>Create New Report</strong></li>
                <li>Fill in the form across 5 tabs:
                    <ul>
                        <li><strong>Management Information:</strong> Date, rig, job type, site name, client, location, supervisor, maintenance work (if applicable)</li>
                        <li><strong>Drilling / Construction:</strong> Times, RPM, depth, rod length, materials (received/used), construction depth, compliance</li>
                        <li><strong>Workers / Payroll:</strong> Worker details, wage types, units, rates, benefits, loan reclaims, payments</li>
                        <li><strong>Financial Information:</strong> Income (balance B/F, contract sum, rig fees, cash received), Expenses (materials, daily expenses), Deposits (MoMo, cash, bank)</li>
                        <li><strong>Incident / Case Log:</strong> Incident log, solution log, recommendation log</li>
                    </ul>
                </li>
                <li>Click <strong>Save Report</strong> - a unique Report ID will be generated</li>
            </ol>
            <h4>Offline (When No Internet):</h4>
            <p>Navigate to <code>http://your-domain/abbis3.2/offline</code> to capture complete field reports offline. Reports are saved locally and automatically sync when you reconnect. See the <strong>"Offline Field Reports"</strong> section for complete instructions.</p>
            <p><strong>Tip:</strong> Client information is automatically extracted and saved when you enter a client name (works in both online and offline modes).</p>
        </div>

        <div class="help-item">
            <h3>Understanding Report IDs</h3>
            <p>
                Each report gets a unique Reference ID (e.g., <code>RIG001-2024-001</code>). This Reference ID is shared with receipts and technical reports for cross-referencing. 
                This ID links all documents related to the job:
            </p>
            <ul>
                <li>Receipt/Invoice (financial document)</li>
                <li>Technical Report (technical details only)</li>
                <li>All transactions and payroll entries</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Viewing and Editing Reports</h3>
            <p>
                Go to <strong>Field Reports List</strong> to view all reports. You can:
            </p>
            <ul>
                <li>Search and filter by rig, client, date range, or job type</li>
                <li><strong>Pagination:</strong> View 20 reports per page</li>
                <li><strong>Action Dropdown:</strong> Each report has an Actions dropdown with:
                    <ul>
                        <li><strong>View Report:</strong> Opens a modal showing full report details (read-only)</li>
                        <li><strong>Generate Receipt:</strong> Creates a receipt/invoice for the job</li>
                        <li><strong>Generate Report:</strong> Creates a technical report (client-facing, no personnel section)</li>
                        <li><strong>Edit Report:</strong> Opens the same modal but with editable fields to update the report</li>
                        <li><strong>Delete Report:</strong> Removes the report from the system</li>
                    </ul>
                </li>
                <li><strong>Dashboard Actions:</strong> The same action dropdown is available on the dashboard for quick access</li>
            </ul>
            <p><strong>Note:</strong> The Edit Report feature allows you to update: Site Name, Region, Location Description, Supervisor, Remarks, Incident Log, Solution Log, and Recommendation Log. Other fields are read-only to maintain data integrity.</p>
        </div>

        <div class="help-item">
            <h3>Using Catalog in Expenses</h3>
            <p>When adding expenses in Field Reports:</p>
            <ul>
                <li><strong>Custom item (default):</strong> Check "Custom item" to enter description and amounts manually</li>
                <li><strong>From Catalog:</strong> Uncheck "Custom item" to enable catalog dropdown</li>
                <li>Selecting a catalog item auto-fills description, unit, and unit cost</li>
                <li>You can mix custom and catalog items in the same report</li>
                <li>Catalog-linked expenses appear as itemized charges on receipts</li>
            </ul>
        </div>
        </div>
    </div>

    <!-- Financial Management -->
    <div id="financial" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💰 Financial Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Understanding Financial Calculations</h3>
            <p><strong>Income includes:</strong></p>
            <ul>
                <li>Balance B/F (brought forward)</li>
                <li>Full Contract Sum (for direct jobs)</li>
                <li>Rig Fee Collected (from client)</li>
                <li>Cash Received (from company)</li>
            </ul>
            <p><strong>Expenses include:</strong></p>
            <ul>
                <li>Materials Purchased</li>
                <li>Wages</li>
                <li>Daily Expenses</li>
                <li>Loan Reclaims</li>
            </ul>
            <p><strong>Net Profit = Total Income - Total Expenses</strong></p>
        </div>

        <div class="help-item">
            <h3>Payroll Management</h3>
            <p>
                Access payroll from the main menu or within field reports. You can:
            </p>
            <ul>
                <li>Add worker payments with wage types (daily, per rod, per meter, fixed)</li>
                <li>Track benefits and loan reclaims</li>
                <li>Mark payments as "Paid Today"</li>
                <li>View payroll history and exports</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Loans Management</h3>
            <p>
                Track worker loans and rig fee debts:
            </p>
            <ul>
                <li><strong>Worker Loans:</strong> Advances given to workers</li>
                <li><strong>Rig Fee Debts:</strong> Outstanding rig fees from clients</li>
                <li>System tracks outstanding balances and payments</li>
            </ul>
        </div>
        </div>
    </div>

    <!-- Point of Sale -->
    <div id="pos" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🛒 Point of Sale (POS)</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>Overview & prerequisites</h3>
            <p>The POS module unifies in-store checkout, online shop orders, inventory, and accounting. Before your first sale:</p>
            <ul>
                <li><strong>Stores:</strong> Visit <em>System → POS &amp; Store</em> (or click <em>POS Management</em> on the POS dashboard) to create each physical location and mark one as primary.</li>
                <li><strong>Catalog:</strong> In the <em>POS Management &amp; Catalog</em> page add products with prices, tax, and whether inventory should be tracked. Toggle <strong>Expose to shop</strong> to publish items to the customer-facing online store.</li>
                <li><strong>User access:</strong> Grant <code>pos.access</code> for cashiers, <code>pos.sales.process</code> to record payments, and <code>pos.inventory.manage</code>/<code>system.admin</code> for back-office configuration and hardware settings.</li>
                <li><strong>Hardware:</strong> Connect barcode scanners (they work as keyboards) and receipt printers. Use the <em>Printer &amp; Hardware Settings</em> button on the POS dashboard to store device preferences per terminal.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Publish products to the online store</h3>
            <p>ABBIS keeps one catalog across the POS terminal and the CMS shop. When you enable <strong>Expose to shop</strong> for a product:</p>
            <ul>
                <li>The product is synchronised to the CMS catalog (category, description, price, stock level).</li>
                <li>Inventory adjustments in the POS (sales, restocks, transfers) automatically update availability online.</li>
                <li>Deactivate or archive a product once and both channels stop selling it immediately.</li>
            </ul>
            <p>If you bulk-import products or notice a mismatch, run <code>php scripts/pos/sync-catalog.php</code> on the server (or schedule it as a cron job) to force a full resync to the CMS shop.</p>
        </div>

        <div class="help-item">
            <h3>Process walk-in sales in the POS terminal</h3>
            <ol>
                <li>Open <em>Modules → Point of Sale</em>. Confirm the correct store is selected in the store picker.</li>
                <li>Search by name/SKU or scan a barcode—the highlighted product cards automatically add to the cart.</li>
                <li>Adjust quantities, remove items, or enter discounts directly in the cart. The receipt preview updates in real time.</li>
                <li>Capture customer details (optional), choose a payment method, and enter the amount received to auto-calculate change.</li>
                <li>Click <strong>Complete Sale</strong>. A sale number is generated, inventory reduces for tracked items, and the transaction appears instantly under <em>POS Management → Sales</em>.</li>
                <li>Select <strong>Print</strong> or use the <em>Test Print</em> option in hardware settings to issue receipts on a thermal printer or the standard browser print dialog.</li>
            </ol>
            <p>Use the <em>Adjust Inventory</em> modal for stock corrections, restocks, or transfers that need to hit the ledger without recording a sale.</p>
        </div>

        <div class="help-item">
            <h3>Capture and fulfil online store orders</h3>
            <p>Online purchases from the CMS shop use the same product catalog and push orders into the POS sales queue. Recommended fulfilment workflow:</p>
            <ol>
                <li>Monitor new orders from <em>POS Management → Sales</em> or from your CMS order notifications.</li>
                <li>Verify payment status, then open the sale to print the receipt or packing slip. Use the <em>Notes</em> field on the POS terminal to record courier references or click-and-collect details.</li>
                <li>Pick items from stock, optionally move them to a dedicated store/bin, and mark the order as fulfilled in the CMS if you use customer emails.</li>
                <li>If an online checkout requires manual confirmation (e.g., pay-on-delivery), recreate the basket in the POS terminal, complete the sale when money is received, and enter the web order number in the notes for traceability.</li>
            </ol>
            <p>This approach ensures every online order ends up as a POS sale so inventory, accounting sync, and analytics stay consistent.</p>
        </div>

        <div class="help-item">
            <h3>Hardware, printing, and troubleshooting tips</h3>
            <ul>
                <li><strong>Printer &amp; Hardware Settings:</strong> The button in the POS header now opens a modal where you can set ESC/POS, network, or browser printing, paper width, custom endpoints, barcode prefixes, and receipt footer text.</li>
                <li><strong>Test prints:</strong> Use <em>Test Print</em> after saving settings to confirm connection details before serving customers.</li>
                <li><strong>Offline fallback:</strong> If internet drops, keep the POS page open—scanned items remain in the cart. Once connectivity returns, complete the sale to push data to the server.</li>
                <li><strong>Accounting sync:</strong> Run <em>POS Management → Accounting → Sync Pending Sales</em> to post confirmed orders into the general ledger. Resolve errors directly from the queue table.</li>
                <li><strong>Inventory variances:</strong> Use the <em>Record Adjustment</em> tool or import stock counts from Excel/CSV to realign physical and system quantities.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Rig Tracking -->
    <div id="rig-tracking" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🛰️ Rig Tracking & Telematics</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>Overview & prerequisites</h3>
            <p>Track live rig locations, speed, and provider health directly inside ABBIS. Before enabling tracking:</p>
            <ul>
                <li><strong>Upgrade schema:</strong> Run <code>database/migrations/20251109_rig_tracking_api_config.sql</code> to add provider metadata columns.</li>
                <li><strong>Provider access:</strong> Collect API keys, device IDs, and endpoint URLs from your telematics vendor (Fleetsmart, GPSLive, Radius, etc.).</li>
                <li><strong>Map provider:</strong> Configure Google Maps (with API key) or Leaflet via <em>System → Integrations → Map Providers</em>.</li>
                <li><strong>Active rigs:</strong> Ensure rigs are marked active in <em>Resources → Rigs</em> so tracking widgets can bind to them.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Configure a third-party provider</h3>
            <ol>
                <li>Open <em>Resources → Rig Tracking</em> and select the rig you want to link.</li>
                <li>Choose <strong>Third-party API</strong> as tracking method and enter the provider name, device ID, polling frequency, and auth method.</li>
                <li>Paste the provider's base URL and populate <code>config_payload</code> fields (endpoint path, HTTP method, lat/lng JSON paths, headers, query params).</li>
                <li>Save, then click <strong>Refresh location</strong> to validate mapping. Errors surface in the provider status card for quick troubleshooting.</li>
            </ol>
            <p><strong>Tip:</strong> Template variables like <code>{{device_id}}</code> and <code>{{api_key}}</code> can be used inside payload definitions to avoid duplication.</p>
        </div>

        <div class="help-item">
            <h3>Daily use from the Rig Tracking map</h3>
            <ul>
                <li>Use <strong>Refresh</strong> to fetch a live position immediately (forces a provider sync when enabled).</li>
                <li>Toggle <strong>History</strong> to plot the last 20 positions per rig for route audits and visit verification.</li>
                <li>Open the <strong>Manual Update</strong> modal for emergency overrides or when operating offline—coordinates update instantly.</li>
                <li>Review the provider panel for last sync time, configured frequency, device ID, and any error messages.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Background sync & troubleshooting</h3>
            <ul>
                <li>Schedule <code>scripts/sync-rig-locations.php</code> (e.g. every 5 minutes) to poll each enabled rig via <code>/api/rig-tracking.php?action=sync_third_party</code>.</li>
                <li>Rigs that fail to sync flip to <code>status = error</code> with the raw message; resolve credentials or payload mapping, then run a manual refresh to clear.</li>
                <li>Monitor <code>rig_tracking_config.last_error_at</code> and dashboard alerts to catch ageing errors early.</li>
                <li>Consider adding notifications when a rig has been stationary longer than its SLA by reading the <code>rig_locations</code> table.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- AI & Automation -->
    <div id="ai-automation" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🤖 AI Assistant & Automation</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>Assistant overview</h3>
            <p>The ABBIS Assistant delivers contextual insights, anomaly detection, and next-step guidance inside the dashboard, analytics, and dedicated <em>AI Assistant Hub</em>. Access is gated by the <code>ai.assistant</code> permission.</p>
            <ul>
                <li>Open the full workspace via <em>Modules → AI Assistant</em>.</li>
                <li>Use the floating drawer on dashboards for quick questions, summaries, or risk calls.</li>
                <li>Select a context entity (client, rig request, field report) to enrich responses automatically.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Provider configuration & failover</h3>
            <ol>
                <li>Set environment defaults in <code>.env</code> (<code>AI_PROVIDERS</code>, <code>AI_OPENAI_API_KEY</code>, <code>AI_OLLAMA_BASE_URL</code>, etc.).</li>
                <li>Run <code>database/migrations/phase5/001_create_ai_tables.sql</code> to create usage, cache, and provider tables.</li>
                <li>Visit <em>System → AI Governance & Audit</em> to enable providers, store encrypted API keys, set hourly/daily quotas, and define failover order.</li>
                <li>Grant trusted staff the <code>system.admin</code> or AI governance permissions so they can manage providers without redeploying config.</li>
            </ol>
            <p>Supported adapters: OpenAI/Azure, DeepSeek, Google Gemini, and on-prem Ollama. Unknown providers can be added by extending <code>includes/AI/Providers</code>.</p>
        </div>

        <div class="help-item">
            <h3>Working with ABBIS Assistant</h3>
            <ul>
                <li>Use quick prompts (“Give me today’s top 3 priorities”, “Explain variance in field productivity”) or craft natural language questions.</li>
                <li>Attach transcripts to records by copying the session link or exporting from the assistant pane.</li>
                <li>Leverage insight actions (<em>Forecast</em>, <em>Lead Score</em>, <em>Playbook</em>) exposed through <code>api/ai-insights.php</code> for automation workflows.</li>
                <li>Encourage feedback via thumbs-up/down to refine prompt templates stored under <code>includes/AI/Prompting/Templates</code>.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Governance, auditing, and limits</h3>
            <ul>
                <li>All usage is logged to <code>ai_usage_logs</code>; review via <em>AI Governance & Audit</em> filters (user, provider, success/error).</li>
                <li>Configure hourly/daily caps per provider and per organisation to keep cost predictable.</li>
                <li>Use the failover priority list to fall back to cheaper or on-prem providers when primary services are unavailable.</li>
                <li>Rotate API keys regularly—ABBIS encrypts stored credentials with <code>ABBIS_ENCRYPTION_KEY</code>, but still ensure secrets management policies are followed.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Customer Care -->
    <div id="customer-care" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🛠️ Customer Complaints & Service Recovery</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>Complaint intake & routing</h3>
            <p>Use <em>Service Delivery → Complaints</em> to capture customer issues from phone, email, site logs, or WhatsApp. Required data points include summary, priority, due date, assigned owner, customer reference, and communication channel.</p>
            <ul>
                <li>Quickly log new cases via the inline form; defaults come from master lists (channels, priorities, statuses).</li>
                <li>Attach structured customer info so downstream CRM and finance actions can link the ticket automatically.</li>
                <li>Set SLA clock with due dates and priority levels—overdue counters and colour badges highlight risks on the overview.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Managing the complaint lifecycle</h3>
            <ol>
                <li>Assign to agents or teams (filter by “Assigned to me”, “Unassigned”, “All”).</li>
                <li>Track status transitions (New → In Progress → Waiting on Client/Partner → Resolved/Closed).</li>
                <li>Log timeline updates, add internal notes, and upload evidence from the detailed complaint view.</li>
                <li>Escalate severe, overdue, or high-priority tickets to management via the escalation field—these appear in the Severity widget.</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Integrations & follow-up</h3>
            <ul>
                <li>Link complaints to CRM follow-ups and quotes (e.g. for service credits) so the customer account reflects remediation work.</li>
                <li>Push field actions to <em>Requests</em> or maintenance teams when a site visit is required.</li>
                <li>Feed chronic payment-related issues to <em>Collections/Debt Recovery</em> with context for finance colleagues.</li>
                <li>Export complaint registers for audits or share filtered reports with leadership at month-end.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>KPIs & reporting</h3>
            <ul>
                <li>Monitor total volume, open, overdue, and resolved counts from the KPI cards at the top of the overview screen.</li>
                <li>Use list filters (status, priority, channel, owner) to drill down; results can be exported or printed for stand-up meetings.</li>
                <li>Combine complaint data with field reports and collections metrics in Analytics for root-cause trend analysis.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Executive Reports -->
    <div id="executive-reports" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🏛️ Executive Reporting & Board Packs</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>One-click board packs</h3>
            <p>Generate consolidated financial and operational packs from <em>Finance → Executive Export Pack</em>. Choose the month and ABBIS assembles P&L, balance sheet, cash flow, KPI summary, and recent highlights.</p>
            <ul>
                <li>Exports available in PDF and XLSX for sharing with leadership or investors.</li>
                <li>Use the XLSX output as a base for further modelling or commentary.</li>
                <li>Ensure core accounting modules (Journal, Ledger, Trial Balance) are up-to-date before exporting.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Preparing the data</h3>
            <ol>
                <li>Close out the month in accounting (post journals, reconcile banks, finalise inventory adjustments).</li>
                <li>Refresh analytics dashboards to confirm KPIs align with operational data.</li>
                <li>Optional: Attach commentary (CEO letter, risk register) in your chosen template before distributing.</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Complementary reports</h3>
            <ul>
                <li><strong>Accounting Dashboard:</strong> Snapshot of balance sheet, liquidity ratios, and period compares.</li>
                <li><strong>Analytics → Executive KPIs:</strong> Drill into revenue mix, margins, collections, and utilisation.</li>
                <li><strong>Board Pack archives:</strong> Store generated packs in your document management system for audit trails.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Integrations -->
    <div id="integrations" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🔌 Integrations & Data Connectors</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">

        <div class="help-item">
            <h3>Accounting software (QuickBooks & Zoho Books)</h3>
            <ol>
                <li>Open <em>Finance → Accounting Integrations</em> and enter the Client ID/Secret plus the provided redirect URI.</li>
                <li>Click <strong>Connect</strong> to launch OAuth; approve access, then confirm the status badge switches to <em>Connected</em>.</li>
                <li>Use <strong>Sync Journal Entries</strong> to export ABBIS journals to the external ledger with automatic account mapping.</li>
                <li>Tokens refresh automatically, but you can disconnect at any time to revoke access.</li>
            </ol>
            <p>All credentials are encrypted via <code>includes/crypto.php</code>; the integration page shows token expiry and progress feedback during sync.</p>
        </div>

        <div class="help-item">
            <h3>Business intelligence connectors</h3>
            <ul>
                <li><strong>Looker Studio:</strong> Visit <em>System → Integrations → Looker Studio</em> for step-by-step instructions, sample URLs, schema endpoints, and test tools. Supply <code>/api/looker-studio-api.php</code> with optional <code>api_key</code> parameters.</li>
                <li><strong>Custom dashboards:</strong> Use the <code>metrics</code> and <code>data</code> actions to feed external BI tools or scheduled scripts.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Operational observability</h3>
            <ul>
                <li><strong>ELK Stack:</strong> Configure Elasticsearch URL, credentials, index prefix, and activation status under <em>System → Integrations → ELK Stack</em>. Sync field reports, logs, and metrics or schedule cron jobs.</li>
                <li>Use the built-in <em>Test Connection</em> button to verify endpoints; toggle <em>Activate Integration</em> to start or pause exports.</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Maps, auth, and feature toggles</h3>
            <ul>
                <li><strong>Map Providers:</strong> Switch between Google Maps and Leaflet, storing keys securely for rig tracking, field site pickers, and CRM locations.</li>
                <li><strong>Social authentication:</strong> Manage OAuth settings for Google/Microsoft login under <em>System → Integrations → Social Auth</em> (ensure redirect URIs match your deployment).</li>
                <li><strong>Feature Management:</strong> Enable modules like POS, Smart Planner, Complaints, or Advanced Inventory without code changes—ideal for phased rollouts.</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Clients -->
    <div id="clients" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">👥 Client Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Viewing Clients</h3>
            <p>
                Navigate to <strong>Clients</strong> from the main menu to see all your clients:
            </p>
            <ul>
                <li>Client list shows total jobs, revenue, and last transaction date</li>
                <li>Search by client name, contact person, or email</li>
                <li>Click <strong>View Details</strong> for comprehensive client information</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Client Details Page</h3>
            <p>When viewing a client, you'll see:</p>
            <ul>
                <li><strong>Statistics:</strong> Total jobs, revenue, profit, average profit per job</li>
                <li><strong>Transaction History:</strong> Complete list of all jobs for this client</li>
                <li>Quick access to receipts and technical reports for each transaction</li>
            </ul>
            <p><strong>Note:</strong> Clients are automatically created when you enter their name in a field report.</p>
        </div>
        </div>
    </div>

    <!-- Payroll Management -->
    <div id="payroll" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💼 Payroll Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Overview</h3>
            <p>
                The Payroll Management system provides comprehensive tools for tracking worker payments, managing payroll records, 
                and generating payslips. All payroll entries are automatically created when you add workers to field reports.
            </p>
            <p><strong>Access:</strong> Main Menu → <strong>Payroll</strong></p>
        </div>

        <div class="help-item">
            <h3>Statistics Dashboard</h3>
            <p>The payroll page displays 6 key statistics cards:</p>
            <ul>
                <li><strong>Total Payroll (Filtered):</strong> Sum of all payroll amounts matching your current filters</li>
                <li><strong>Total Paid:</strong> Amount already paid to workers (paid_today = Yes)</li>
                <li><strong>Total Unpaid:</strong> Outstanding payments pending</li>
                <li><strong>Unique Workers:</strong> Number of different workers in filtered results</li>
                <li><strong>Today's Payroll:</strong> Total payroll entries created today</li>
                <li><strong>This Month's Payroll:</strong> Total payroll for the current month</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Advanced Filtering</h3>
            <p>Filter payroll entries using multiple criteria:</p>
            <ul>
                <li><strong>Search:</strong> Search by worker name, role, report ID, or site name</li>
                <li><strong>Date From/To:</strong> Filter by date range (defaults to current month)</li>
                <li><strong>Worker:</strong> Filter by specific worker (dropdown of all workers)</li>
                <li><strong>Role:</strong> Filter by worker role (Driller, Helper, etc.)</li>
                <li><strong>Payment Status:</strong> Filter by Paid, Unpaid, or All Statuses</li>
                <li><strong>Report ID:</strong> Filter by specific field report ID</li>
            </ul>
            <p><strong>Actions:</strong></p>
            <ul>
                <li><strong>Apply Filters:</strong> Apply all selected filters</li>
                <li><strong>Clear Filters:</strong> Reset all filters to default</li>
                <li><strong>Generate Payslip:</strong> Open payslip generation modal</li>
                <li><strong>Export CSV:</strong> Export filtered payroll data to CSV file</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Payroll Entries Table</h3>
            <p>The main table displays all payroll entries with the following information:</p>
            <ul>
                <li><strong>Date:</strong> Date of the field report</li>
                <li><strong>Worker:</strong> Worker name</li>
                <li><strong>Role:</strong> Worker's role (Driller, Helper, etc.)</li>
                <li><strong>Wage Type:</strong> Type of wage (daily, hourly, per_unit, etc.)</li>
                <li><strong>Units:</strong> Number of units worked</li>
                <li><strong>Rate:</strong> Pay per unit (in GHS)</li>
                <li><strong>Benefits:</strong> Additional benefits amount</li>
                <li><strong>Loan:</strong> Loan reclaim amount (if any)</li>
                <li><strong>Amount:</strong> Total payment amount</li>
                <li><strong>Status:</strong> Paid or Unpaid badge</li>
                <li><strong>Report:</strong> Link to the field report</li>
                <li><strong>Site:</strong> Site name (truncated if too long)</li>
                <li><strong>Actions:</strong> Dropdown menu for actions</li>
            </ul>
            <p><strong>Pagination:</strong> Shows 10 entries per page with navigation controls.</p>
        </div>

        <div class="help-item">
            <h3>Payroll Actions</h3>
            <p>Each payroll entry has an Actions dropdown with the following options:</p>
            <ul>
                <li><strong>Mark as Paid:</strong> Update payment status to "Paid"</li>
                <li><strong>Mark as Unpaid:</strong> Update payment status to "Unpaid"</li>
                <li><strong>View Notes:</strong> Opens a modal showing any notes for the entry</li>
                <li><strong>Delete Entry:</strong> Permanently removes the payroll entry (use with caution)</li>
            </ul>
            <p><strong>Note:</strong> Payment status updates are saved immediately. The table refreshes to show the updated status.</p>
        </div>

        <div class="help-item">
            <h3>Generating Payslips</h3>
            <p>
                Generate professional payslips for any worker covering any time period. Payslips show all payroll entries 
                for the selected worker and date range.
            </p>
            <p><strong>Steps to Generate a Payslip:</strong></p>
            <ol>
                <li>Click <strong>Generate Payslip</strong> button in the filter section</li>
                <li>Select the <strong>Worker</strong> from the dropdown</li>
                <li>Select <strong>Period From</strong> date (start of pay period)</li>
                <li>Select <strong>Period To</strong> date (end of pay period)</li>
                <li>Click <strong>Generate Payslip</strong></li>
                <li>The payslip opens in a new tab</li>
                <li>Click <strong>Print Payslip</strong> to print or save as PDF</li>
            </ol>
            <p><strong>What's Included in Payslips:</strong></p>
            <ul>
                <li><strong>Employee Information:</strong> Name, role, and contact (if available)</li>
                <li><strong>Payroll Period:</strong> Start and end dates, generation timestamp</li>
                <li><strong>Detailed Entries Table:</strong> All payroll entries with:
                    <ul>
                        <li>Date of work</li>
                        <li>Report ID and site name</li>
                        <li>Role and wage type</li>
                        <li>Units, rate, benefits, loan reclaim</li>
                        <li>Payment amount and status (Paid/Unpaid)</li>
                    </ul>
                </li>
                <li><strong>Financial Summary:</strong>
                    <ul>
                        <li>Total Earnings</li>
                        <li>Total Benefits</li>
                        <li>Total Loan Reclaim (deduction)</li>
                        <li>Net Pay (Total Earnings - Loan Reclaim)</li>
                        <li>Already Paid (amount already received)</li>
                        <li>Outstanding Balance (amount still owed)</li>
                    </ul>
                </li>
                <li><strong>Company Footer:</strong> Company name, address, contact, and email</li>
            </ul>
            <p><strong>Print-Friendly:</strong> Payslips are formatted for professional printing with clean, modern design.</p>
        </div>

        <div class="help-item">
            <h3>Exporting Payroll Data</h3>
            <p>
                Export filtered payroll entries to CSV for use in Excel or other spreadsheet applications.
            </p>
            <ol>
                <li>Apply any filters you want (date range, worker, role, etc.)</li>
                <li>Click <strong>Export CSV</strong> button</li>
                <li>The CSV file downloads automatically with all filtered entries</li>
                <li>Open in Excel, Google Sheets, or any CSV-compatible application</li>
            </ol>
            <p><strong>CSV Columns Include:</strong> Date, Report ID, Site Name, Rig Code, Worker Name, Role, Wage Type, Units, Rate, Benefits, Loan Reclaim, Amount, Payment Status, and Notes.</p>
        </div>

        <div class="help-item">
            <h3>Understanding Payroll Entries</h3>
            <p>
                Payroll entries are automatically created when you add workers to field reports. Each entry tracks:
            </p>
            <ul>
                <li><strong>Basic Info:</strong> Worker name, role, date, and linked field report</li>
                <li><strong>Work Details:</strong> Wage type, units worked, and pay rate per unit</li>
                <li><strong>Financial:</strong> Base amount, benefits, loan reclaim (if any), and total amount</li>
                <li><strong>Payment Status:</strong> Whether the worker has been paid for this entry</li>
                <li><strong>Notes:</strong> Optional notes about the payroll entry</li>
            </ul>
            <p><strong>Wage Types:</strong></p>
            <ul>
                <li><strong>Daily:</strong> Fixed daily rate</li>
                <li><strong>Hourly:</strong> Pay per hour</li>
                <li><strong>Per Unit:</strong> Pay per unit of work (meters drilled, etc.)</li>
                <li><strong>Fixed:</strong> Fixed amount regardless of work done</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Best Practices</h3>
            <ul>
                <li><strong>Regular Updates:</strong> Mark entries as "Paid" as soon as payments are made</li>
                <li><strong>Use Filters:</strong> Use date range filters to focus on specific pay periods</li>
                <li><strong>Generate Payslips Monthly:</strong> Generate payslips at the end of each month for record-keeping</li>
                <li><strong>Keep Notes:</strong> Add notes to payroll entries for important payment details</li>
                <li><strong>Export Regularly:</strong> Export payroll data monthly for backup and accounting purposes</li>
                <li><strong>Review Outstanding:</strong> Regularly check "Total Unpaid" to track outstanding payments</li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Reports & Receipts -->
    <div id="reports" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📄 Reports & Receipts</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Generating Receipts/Invoices</h3>
            <p>Receipts contain <strong>financial information only</strong> (no technical details):</p>
            <ol>
                <li>Go to <strong>Field Reports List</strong></li>
                <li>Find the report you want</li>
                <li>Click the <strong>💰 Receipt</strong> button</li>
                <li>The receipt opens in a new tab with company logo and branding</li>
                <li>Click <strong>🖨️ Print Receipt</strong> to print or save as PDF</li>
            </ol>
            <p><strong>What's included:</strong> Company info, client details, payment amounts, structural reference ID</p>
            <p><strong>Itemized Charges:</strong> If expenses were linked to catalog items, receipts show a detailed line-item table with quantity, unit price, and total for each item.</p>
        </div>

        <div class="help-item">
            <h3>Generating Technical Reports</h3>
            <p>Technical reports contain <strong>technical details only</strong> (no financial information). These reports are client-facing and suitable for sharing with clients:</p>
            <ol>
                <li>Go to <strong>Field Reports List</strong></li>
                <li>Find the report you want</li>
                <li>Click the <strong>📄 Report</strong> button (or from Actions dropdown → Generate Report)</li>
                <li>The report opens showing drilling specs, materials, and technical observations</li>
                <li>Click <strong>🖨️ Print Report</strong> to print</li>
            </ol>
            <p><strong>What's included:</strong> Site location, drilling info, materials used, technical notes, incident logs, solution logs, recommendations, and reference ID</p>
            <p><strong>Note:</strong> Technical reports are client-facing and do NOT include financial information or personnel details. The personnel section has been removed as this report is meant for clients.</p>
            <p><strong>What's NOT included:</strong> Personnel information (workers who worked on site) is excluded since this is a client-facing document. Financial details are also excluded.</p>
        </div>

        <div class="help-item">
            <h3>Reference ID & Receipt Numbers</h3>
            <p>
                Each job has a unique <strong>Reference ID</strong> (the report_id) that appears on both receipts and technical reports. 
                This allows cross-referencing between financial and technical documents.
            </p>
            <p>
                <strong>Reference ID:</strong> Shared identifier for a job, appears on all receipts and the technical report.
            </p>
            <p>
                <strong>Receipt Number:</strong> Unique identifier for each payment receipt (one job can have multiple receipts for installment payments).
                Format: <code>RCP-YYYYMMDD-HHMMSS-UNIQID-REPORTID</code>
            </p>
            <ul>
                <li>Cross-reference financial and technical documents using the Reference ID</li>
                <li>Link all related documents to a single job</li>
                <li>Track complete job history and payment installments</li>
            </ul>
            
            <h3>Receipt Financial Breakdown</h3>
            <p>
                Receipts now provide a clear financial breakdown:
            </p>
            <ul>
                <li><strong>Subtotal (Payments Received):</strong> Includes rig fee collected, materials income, and catalog item charges</li>
                <li><strong>Materials Cost:</strong> If the client purchased materials from the company, the materials cost is shown separately (in blue) before the total</li>
                <li><strong>Total Amount Due:</strong> Grand total including payments received and materials cost (if applicable)</li>
            </ul>
            <p>
                <strong>Note:</strong> The receipt only shows money received from clients and materials costs if the client purchased materials from the company. This provides transparency about what the client owes vs. what has been paid.
            </p>
        </div>
        </div>
    </div>

    <!-- System & Admin -->
    <div id="system-admin" class="help-section">
        <h2>🛠️ System & Admin</h2>
        <div class="help-item">
            <h3>System Hub</h3>
            <p>Central hub for Configuration, Data Management, API Keys, Users, Zoho, Looker Studio, ELK, Feature Management, Social Authentication, Map Providers.</p>
            <p><strong>3-Column Layout:</strong> System management page uses a modern 3-column grid layout for better organization.</p>
        </div>
        <div class="help-item">
            <h3>Feature Toggles</h3>
            <p>Enable/disable modules like Maintenance, Assets, Advanced Inventory via <strong>Feature Management</strong>.</p>
        </div>
        <div class="help-item">
            <h3>Social Authentication</h3>
            <p>Configure OAuth authentication for Google and Facebook login.</p>
            <ul>
                <li><strong>Google OAuth:</strong> Set up Google Client ID and Client Secret</li>
                <li><strong>Facebook OAuth:</strong> Set up Facebook App ID and App Secret</li>
                <li><strong>Auto-Generated Redirect URIs:</strong> System automatically generates redirect URIs if not provided</li>
                <li><strong>Access:</strong> System → Social Authentication</li>
            </ul>
            <p>Once configured, users can connect their Google or Facebook accounts from their profile page for easier login.</p>
        </div>
        <div class="help-item">
            <h3>API Keys</h3>
            <p>Generate and manage API keys for integrations; supports rate limiting and expiry.</p>
        </div>
        <div class="help-item">
            <h3>Monitoring API</h3>
            <p>Health checks, metrics, performance, alerts, with API key authentication.</p>
        </div>
        <div class="help-item">
            <h3>URL Security & Routing</h3>
            <p>Clean routing and ID obfuscation available. For compatibility, direct PHP access is currently enabled in <code>.htaccess</code>.</p>
        </div>
    </div>

    <!-- Integrations -->
    <div id="integrations" class="help-section">
        <h2>🔌 Integrations</h2>
        <div class="help-item">
            <h3>Zoho Suite</h3>
            <p>OAuth2 setup and sync for Zoho CRM, Inventory, Books, Payroll, HR via <strong>Zoho Integration</strong> module.</p>
        </div>
        <div class="help-item">
            <h3>Looker Studio</h3>
            <p>Data sources exposed via <code>api/looker-studio-api.php</code> with a management UI.</p>
        </div>
        <div class="help-item">
            <h3>ELK / Kibana</h3>
            <p>Index data to Elasticsearch via <code>api/elk-integration.php</code> and manage in the ELK module.</p>
        </div>
        <div class="help-item">
            <h3>Wazuh</h3>
            <p>Use the Monitoring API and <strong>API Integration Guide</strong> for security monitoring connections.</p>
        </div>
    </div>

    <!-- Security & Compliance -->
    <div id="security" class="help-section">
        <h2>🔐 Security & Compliance</h2>
        <div class="help-item">
            <h3>Data Protection</h3>
            <p>Compliant with Ghana Data Protection Act (Act 843) and GDPR principles. See <strong>docs/DATA_PROTECTION_COMPLIANCE.md</strong>.</p>
        </div>
        <div class="help-item">
            <h3>Consent Management</h3>
            <p>Cookie consent banner and consent recording integrated across the app.</p>
        </div>
        <div class="help-item">
            <h3>Sessions & Auth</h3>
            <p>Secure sessions (HttpOnly, SameSite=Strict), session timeout, login attempt lockout.</p>
        </div>

        <div class="help-item">
            <h3>Account Lockout Management</h3>
            <p>ABBIS implements automatic account lockout to prevent brute-force attacks:</p>
            <ul>
                <li><strong>Lockout Threshold:</strong> 5 failed login attempts within 15 minutes</li>
                <li><strong>Lockout Duration:</strong> 15 minutes (900 seconds)</li>
                <li><strong>Automatic Unlock:</strong> Account automatically unlocks after the lockout period expires</li>
                <li><strong>Lockout Status:</strong> Login page displays lockout information when an account is locked</li>
            </ul>
            <p><strong>Unlocking Accounts:</strong></p>
            <ul>
                <li><strong>Command Line (Recommended):</strong> Use <code>php scripts/unlock-account.php &lt;username&gt;</code> to immediately unlock any account</li>
                <li><strong>Web API (Admin Only):</strong> POST to <code>/api/unlock-account.php</code> with username and CSRF token</li>
                <li><strong>User Management UI:</strong> Admins can unlock accounts from Users module → View user → Click "Unlock" button</li>
            </ul>
            <p><strong>Lockout Information:</strong></p>
            <ul>
                <li>The login page automatically checks and displays lockout status</li>
                <li>Shows remaining lockout time and number of failed attempts</li>
                <li>Provides clear messaging about when the account will be unlocked</li>
            </ul>
            <p><strong>Best Practices:</strong></p>
            <ul>
                <li>Keep track of login attempts to avoid accidental lockouts</li>
                <li>Use the unlock script for immediate access during development</li>
                <li>Monitor lockout events in security audit logs</li>
                <li>Consider adjusting lockout settings for development environments</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Password Reset (Forgot Password)</h3>
            <p>Users can reset their passwords if they forget them:</p>
            <ul>
                <li><strong>Access:</strong> Click "Forgot Password?" link on the login page</li>
                <li><strong>Process:</strong>
                    <ol>
                        <li>Enter your email address on the forgot password page</li>
                        <li>System sends a password reset link to your email</li>
                        <li>Click the link in the email (valid for 1 hour)</li>
                        <li>Enter your new password (minimum 8 characters)</li>
                        <li>Confirm the new password</li>
                        <li>Password is updated and you can log in</li>
                    </ol>
                </li>
                <li><strong>Security Features:</strong>
                    <ul>
                        <li>Reset tokens expire after 1 hour</li>
                        <li>Tokens are single-use (invalidated after password reset)</li>
                        <li>Secure token generation and verification</li>
                        <li>Email validation ensures reset links are sent to registered emails only</li>
                    </ul>
                </li>
                <li><strong>Email Configuration:</strong> Ensure email service is properly configured in System → Email Settings</li>
                <li><strong>Development Mode:</strong> In development, reset links are displayed on the page if email sending fails</li>
            </ul>
            <p><strong>Troubleshooting:</strong></p>
            <ul>
                <li>If you don't receive the email, check spam/junk folder</li>
                <li>Verify your email address is correct in your user profile</li>
                <li>Check email service configuration if emails aren't being sent</li>
                <li>In development mode, check the page for the reset link if email fails</li>
                <li>Contact administrator if you continue to have issues</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Super Admin Bypass (Development Only)</h3>
            <p><strong>⚠️ WARNING: This feature is for development and maintenance only!</strong></p>
            <p>The Super Admin bypass allows full system access without authentication checks, but <strong>ONLY works when <code>APP_ENV = 'development'</code></strong>:</p>
            <ul>
                <li><strong>Automatic Disable:</strong> Automatically disabled in production environments</li>
                <li><strong>Full Access:</strong> Bypasses all authentication, authorization, and permission checks</li>
                <li><strong>Account Lockout Bypass:</strong> Not affected by login attempt lockouts</li>
                <li><strong>Access Logging:</strong> All Super Admin access attempts are logged for security</li>
            </ul>
            <p><strong>Configuration:</strong></p>
            <ul>
                <li><strong>Location:</strong> <code>config/super-admin.php</code></li>
                <li><strong>Default Username:</strong> <code>superadmin</code> (configurable via <code>SUPER_ADMIN_USERNAME</code> environment variable)</li>
                <li><strong>Default Password:</strong> <code>dev123</code> (configurable via <code>SUPER_ADMIN_PASSWORD</code> environment variable)</li>
                <li><strong>IP Whitelist:</strong> Optional IP address restrictions for additional security</li>
            </ul>
            <p><strong>Access Methods:</strong></p>
            <ul>
                <li><strong>Dedicated Login Page:</strong> <code>/super-admin-login.php</code> - Direct Super Admin login</li>
                <li><strong>Main Login Page:</strong> Use Super Admin credentials on the main login page (development only)</li>
                <li><strong>Visual Indicator:</strong> Super Admin sessions show "Super Admin (Dev)" badge in the header</li>
            </ul>
            <p><strong>Security Notes:</strong></p>
            <ul>
                <li><strong>NEVER enable in production:</strong> This feature is automatically disabled when <code>APP_ENV !== 'development'</code></li>
                <li><strong>Change Default Credentials:</strong> Always change default credentials in development environments</li>
                <li><strong>Use IP Whitelist:</strong> Consider restricting access to specific IP addresses</li>
                <li><strong>Monitor Access:</strong> Review Super Admin access logs regularly</li>
                <li><strong>Documentation:</strong> See <code>docs/SUPER_ADMIN_BYPASS.md</code> for complete documentation</li>
            </ul>
            <p><strong>Use Cases:</strong></p>
            <ul>
                <li>Development and debugging</li>
                <li>System maintenance and troubleshooting</li>
                <li>Database migrations and data fixes</li>
                <li>Testing new features without permission constraints</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Directory Services (LDAP/AD)</h3>
            <p>Integrate with corporate LDAP or Active Directory for single sign-on.</p>
            <ul>
                <li>Set environment variables such as <code>ABBIS_LDAP_ENABLED=true</code>, <code>ABBIS_LDAP_HOST</code>, <code>ABBIS_LDAP_BASE_DN</code>, and bind credentials.</li>
                <li>ABBIS authenticates against LDAP first, auto-provisions users (if enabled), then falls back to local accounts when allowed.</li>
                <li>User attributes (username, email, display name) map automatically; configure overrides in <code>config/ldap.php</code>.</li>
                <li>All LDAP login events are captured in the security audit log for traceability.</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Secret Encryption & Key Management</h3>
            <p>Sensitive credentials (OAuth secrets, API keys, accounting tokens) are stored with AES‑256‑GCM encryption.</p>
            <ul>
                <li>Generate a 32‑byte key and expose it as <code>ABBIS_ENCRYPTION_KEY</code> (Base64 or hex).</li>
                <li>Use <code>php tools/encrypt-legacy-secrets.php</code> to migrate legacy plaintext secrets into encrypted form.</li>
                <li>Run <code>php tools/smoke-test.php</code> to confirm key configuration and database connectivity during deployments.</li>
                <li>Secrets appear masked inside the UI; entering a new value overwrites the encrypted version.</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Access Control Logs</h3>
            <p>Review login activity, permission changes, and LDAP fallbacks to satisfy audit requirements.</p>
            <ul>
                <li>Navigate to <strong>System → Security Audit Logs</strong> (requires <code>access_logs.view</code> permission).</li>
                <li>Filter by user, event type, date range, or IP address.</li>
                <li>Export logs to CSV for compliance reviews.</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Uploads & Validation</h3>
            <p>Strict file type/size checks for logo and profile photos; secure directories and permissions.</p>
        </div>
    </div>

    <!-- User Accounts -->
    <div id="accounts" class="help-section">
        <h2>👤 User Accounts</h2>
        <div class="help-item">
            <h3>User Profiles</h3>
            <p>Update personal info, profile photo, and preferences in <strong>Profile</strong>.</p>
            <ul>
                <li><strong>Auto-Created Columns:</strong> Profile features automatically create required database columns on first access</li>
                <li><strong>Profile Photo:</strong> Upload profile photos (JPEG, PNG, GIF - Max 5MB)</li>
                <li><strong>Personal Information:</strong> Update name, email, phone, date of birth, bio, address, and emergency contacts</li>
                <li><strong>Password Management:</strong> Change password with current password verification</li>
                <li><strong>Social Authentication:</strong> Connect Google, Facebook, or Phone number for login</li>
            </ul>
            <p><strong>Access:</strong> Click on your name/profile icon in the top right, then "View Profile" or go to <strong>Profile</strong> from the main menu</p>
        </div>
        
        <div class="help-item">
            <h3>Media Manager</h3>
            <p>Dedicated media management system for ABBIS (separate from CMS media manager).</p>
            <ul>
                <li><strong>Upload Media:</strong> Drag and drop or click to upload multiple files</li>
                <li><strong>Media Types:</strong> Images, videos, audio, documents</li>
                <li><strong>Search & Filter:</strong> Search by name or filter by media type</li>
                <li><strong>Bulk Operations:</strong> Select multiple files and delete them at once</li>
                <li><strong>Preview Modal:</strong> Click any media to see full details and information</li>
                <li><strong>Filesystem Scanning:</strong> Scan the uploads directory to find files not yet in the database</li>
                <li><strong>Media Statistics:</strong> View counts for different media types at the top</li>
            </ul>
            <p><strong>Access:</strong> Go to <strong>Resources</strong> → <strong>Media Manager</strong></p>
            <p><strong>Note:</strong> The ABBIS and CMS media managers can share the same database/files, but maintain separate interfaces for better organization.</p>
        </div>
        <div class="help-item">
            <h3>Social & Phone Login</h3>
            <p>Google OAuth2, Facebook (framework), and phone login with verification codes.</p>
        </div>
        <div class="help-item">
            <h3>Password Recovery</h3>
            <p>Forgot Password and Reset Password flows with secure tokens.</p>
        </div>
    </div>

    <!-- Theme & Branding -->
    <div id="branding" class="help-section">
        <h2>🎨 Theme & Branding</h2>
        <div class="help-item">
            <h3>Theme</h3>
            <p>Theme toggle cycles modes: <strong>System</strong> (follows OS), <strong>Light</strong>, <strong>Dark</strong>. Default is System mode and updates automatically if your OS theme changes.</p>
        </div>
        <div class="help-item">
            <h3>Logo & Favicon</h3>
            <p>Upload company logo in Configuration → Company Info. Favicon auto-generated (💦) if not provided.</p>
        </div>
    </div>

    <!-- Global Search -->
    <div id="search" class="help-section">
        <h2>🔍 Global Search</h2>
        <div class="help-item">
            <h3>Quick Search</h3>
            <p>Use the top-right search to find records quickly. Press Enter to search.</p>
        </div>
        <div class="help-item">
            <h3>Advanced Search</h3>
            <p>Open the advanced search modal for cross-module filters (reports, clients, materials, etc.).</p>
        </div>
    </div>

    <!-- Human Resources -->
    <div id="hr" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">👥 Human Resources Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>
                    The HR module provides comprehensive employee and worker management, including role assignments, 
                    rig preferences, attendance tracking, leave management, performance reviews, and stakeholder management.
                </p>
                <p><strong>Access:</strong> Navigate to <strong>HR</strong> in the main navigation menu.</p>
            </div>

            <div class="help-item">
                <h3>Employee Management</h3>
                <h4>Adding Employees</h4>
                <ol>
                    <li>Go to <strong>HR → Employees</strong> tab</li>
                    <li>Click <strong>"Add New Employee"</strong> button</li>
                    <li>Fill in employee details:
                        <ul>
                            <li><strong>Employee Code:</strong> Auto-generated if left empty</li>
                            <li><strong>Name:</strong> Full name of the employee</li>
                            <li><strong>Role:</strong> Primary role (e.g., Driller, Rodboy, etc.)</li>
                            <li><strong>Type:</strong> Worker or Staff</li>
                            <li><strong>Default Rate:</strong> Default wage rate</li>
                            <li><strong>Contact Number:</strong> Phone number</li>
                            <li><strong>Email:</strong> Email address</li>
                            <li><strong>Department:</strong> Select department</li>
                            <li><strong>Position:</strong> Select position</li>
                            <li><strong>Hire Date:</strong> Date of employment</li>
                            <li><strong>Status:</strong> Active or Inactive</li>
                        </ul>
                    </li>
                    <li>Click <strong>"Add Employee"</strong> to save</li>
                </ol>

                <h4>Editing Employees</h4>
                <ol>
                    <li>Find the employee in the employee list</li>
                    <li>Click the <strong>"Edit"</strong> button</li>
                    <li>Update the information</li>
                    <li>Click <strong>"Update Employee"</strong> to save changes</li>
                </ol>

                <h4>Deleting Employees</h4>
                <ol>
                    <li>Find the employee in the employee list</li>
                    <li>Click the <strong>"Delete"</strong> button</li>
                    <li>Confirm deletion (this will affect all related records: payroll, loans, field reports, etc.)</li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Role Assignments</h3>
                <p>
                    Workers can have <strong>multiple roles</strong> with one set as primary. Each role can have its own default rate.
                    This allows flexibility for workers who perform different tasks.
                </p>

                <h4>Adding Roles to a Worker</h4>
                <ol>
                    <li>Edit an employee (or add a new one and save first)</li>
                    <li>Click <strong>"Manage Roles"</strong> button in the Role Assignments section</li>
                    <li>In the modal, select a role from the dropdown</li>
                    <li>Optionally set as primary role (checkbox)</li>
                    <li>Set default rate for this role (optional)</li>
                    <li>Click <strong>"Add Role"</strong></li>
                </ol>

                <h4>Editing Role Assignments</h4>
                <ol>
                    <li>Open the Role Management modal</li>
                    <li>Click <strong>"Edit"</strong> next to the role you want to modify</li>
                    <li>Update the primary status or default rate</li>
                    <li>Click <strong>"Update"</strong> to save</li>
                </ol>

                <h4>Removing Roles</h4>
                <ol>
                    <li>Open the Role Management modal</li>
                    <li>Click <strong>"Remove"</strong> next to the role you want to remove</li>
                    <li>Confirm removal</li>
                </ol>

                <h4>Role Compatibility Rules</h4>
                <p><strong>Important Business Rules:</strong></p>
                <ul>
                    <li>✅ <strong>Driller</strong> and <strong>Spanner Boy/Table Boy</strong> are <strong>mutually exclusive</strong></li>
                    <li>✅ A Driller cannot also be a Spanner Boy or Table Boy (the Driller operates the machine, while Spanner/Table Boy is their direct assistant)</li>
                    <li>✅ A Spanner Boy or Table Boy cannot also be a Driller</li>
                    <li>✅ Other workers can have multiple roles (e.g., a Rodboy can also be a Rig Driver)</li>
                </ul>
                <p>
                    The system will automatically prevent incompatible role combinations and show an error message 
                    if you try to assign conflicting roles.
                </p>
            </div>

            <div class="help-item">
                <h3>Rig Preferences</h3>
                <p>
                    Track which rigs a worker typically works on. This is used for <strong>suggestions only</strong> - 
                    workers can still be assigned to any rig in field reports.
                </p>

                <h4>Adding Rig Preferences</h4>
                <ol>
                    <li>Edit an employee (or add a new one and save first)</li>
                    <li>Click <strong>"Manage Rig Preferences"</strong> button</li>
                    <li>In the modal, select a rig from the dropdown</li>
                    <li>Set preference level:
                        <ul>
                            <li><strong>Primary:</strong> Worker's main rig</li>
                            <li><strong>Secondary:</strong> Worker occasionally works on this rig</li>
                            <li><strong>Occasional:</strong> Worker rarely works on this rig</li>
                        </ul>
                    </li>
                    <li>Add notes (optional)</li>
                    <li>Click <strong>"Add Rig Preference"</strong></li>
                </ol>

                <h4>How Rig Preferences Are Used</h4>
                <ul>
                    <li>When creating field reports, workers with rig preferences will appear at the top of the worker dropdown</li>
                    <li>This makes it faster to select the right workers for each rig</li>
                    <li>Preferences are suggestions only - you can still select any worker for any rig</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Worker Roles Management</h3>
                <p>
                    Manage the available roles in the system. Roles define what type of work a person does 
                    (e.g., Driller, Rig Driver, Rodboy, Spanner Boy, Table Boy, etc.).
                </p>

                <h4>Adding a New Role</h4>
                <ol>
                    <li>Go to <strong>HR → Roles</strong> tab</li>
                    <li>Click <strong>"Add New Role"</strong> button</li>
                    <li>Enter role name (e.g., "Driller", "Rodboy")</li>
                    <li>Enter description (optional)</li>
                    <li>Click <strong>"Save"</strong></li>
                </ol>

                <h4>Editing a Role</h4>
                <ol>
                    <li>Go to <strong>HR → Roles</strong> tab</li>
                    <li>Click <strong>"Edit"</strong> button next to the role</li>
                    <li>Update the role name or description</li>
                    <li>Click <strong>"Save"</strong></li>
                </ol>
                <p><strong>Note:</strong> System roles (marked as "System") cannot be edited or deleted.</p>

                <h4>Deleting a Role</h4>
                <ol>
                    <li>Go to <strong>HR → Roles</strong> tab</li>
                    <li>Click <strong>"Delete"</strong> button next to the role</li>
                    <li>Confirm deletion</li>
                </ol>
                <p>
                    <strong>Important:</strong> You cannot delete a role that is assigned to workers. 
                    The system will show an error message indicating how many workers have this role. 
                    You must first remove the role from all workers before deleting it.
                </p>

                <h4>Activating/Deactivating Roles</h4>
                <ol>
                    <li>Go to <strong>HR → Roles</strong> tab</li>
                    <li>Click <strong>"Activate"</strong> or <strong>"Deactivate"</strong> button</li>
                </ol>
                <p>Deactivated roles won't appear in role selection dropdowns but existing assignments remain.</p>
            </div>

            <div class="help-item">
                <h3>Duplicate Worker Analysis</h3>
                <p>
                    The system can analyze your worker database to find duplicate or potentially duplicate workers 
                    (same person entered multiple times with slight name variations).
                </p>

                <h4>Analyzing Duplicates</h4>
                <ol>
                    <li>Go to <strong>HR → Employees</strong> tab</li>
                    <li>Click <strong>"🔍 Analyze Duplicates"</strong> button</li>
                    <li>The system will scan for:
                        <ul>
                            <li>Exact name duplicates (normalized)</li>
                            <li>Duplicate contact numbers</li>
                            <li>Duplicate email addresses</li>
                            <li>Potential duplicates (70%+ name similarity)</li>
                        </ul>
                    </li>
                    <li>Review the results in the modal</li>
                </ol>

                <h4>Merging Duplicates</h4>
                <ol>
                    <li>After analyzing, review duplicate groups</li>
                    <li>For each group, select which worker record to <strong>keep</strong> (radio button)</li>
                    <li>Click <strong>"Merge Group"</strong> to merge all duplicates in that group</li>
                    <li>Or use <strong>"Merge ALL Duplicates"</strong> to automatically merge all detected duplicates</li>
                </ol>
                <p>
                    <strong>What happens when merging:</strong> All related records (payroll entries, loans, field reports, 
                    role assignments, rig preferences) are updated to point to the kept worker record, and duplicate records are removed.
                </p>
            </div>

            <div class="help-item">
                <h3>Exporting Workers</h3>
                <p>Export your complete worker list to CSV for backup or external analysis.</p>
                <ol>
                    <li>Go to <strong>HR → Employees</strong> tab</li>
                    <li>Click <strong>"📥 Export Workers"</strong> button</li>
                    <li>The CSV file will download automatically</li>
                </ol>
                <p>The export includes: Name, Code, Role, Type, Contact, Email, Department, Position, Hire Date, Status, and Default Rate.</p>
            </div>

            <div class="help-item">
                <h3>Other HR Features</h3>
                <ul>
                    <li><strong>Departments:</strong> Organize employees by department</li>
                    <li><strong>Positions:</strong> Define job positions and titles</li>
                    <li><strong>Attendance:</strong> Track daily attendance records</li>
                    <li><strong>Leave Management:</strong> Manage leave requests and balances</li>
                    <li><strong>Performance Reviews:</strong> Record and track employee performance</li>
                    <li><strong>Training:</strong> Track training records and certifications</li>
                    <li><strong>Stakeholders:</strong> Manage external stakeholders and contacts</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Tips & Best Practices</h3>
                <ul>
                    <li>✅ <strong>Standardize Names:</strong> Use consistent naming conventions (e.g., "First Last" format)</li>
                    <li>✅ <strong>Set Primary Roles:</strong> Always set a primary role for each worker</li>
                    <li>✅ <strong>Use Rig Preferences:</strong> Set rig preferences to speed up field report creation</li>
                    <li>✅ <strong>Regular Duplicate Checks:</strong> Run duplicate analysis periodically to keep data clean</li>
                    <li>✅ <strong>Role Compatibility:</strong> Remember that Driller and Spanner/Table Boy cannot be combined</li>
                    <li>✅ <strong>Keep Roles Active:</strong> Deactivate unused roles instead of deleting them (if they have historical data)</li>
                    <li>✅ <strong>Complete Profiles:</strong> Fill in contact information and other details for better tracking</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Dashboard -->
    <div id="dashboard" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">📊 Dashboard & Analytics</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Dashboard Overview</h3>
            <p>The dashboard provides comprehensive business intelligence and key performance indicators:</p>
            <ul>
                <li><strong>KPI Hero Section:</strong> Displays vital business metrics at the top:
                    <ul>
                        <li>Total Boreholes/Jobs (all time)</li>
                        <li>Boreholes done this month</li>
                        <li>Boreholes done this year</li>
                        <li>Total unique clients</li>
                        <li>Client breakdown showing jobs per client (total, this year, this month)</li>
                    </ul>
                </li>
                <li><strong>Operations Snapshot:</strong> Real-time metrics for materials, inventory, assets, maintenance, CRM follow-ups, clients, and workers</li>
                <li><strong>Balance Sheet Overview:</strong> Total assets, liabilities, net worth</li>
                <li><strong>Quick Actions:</strong> Access reports, receipts, and financial reports</li>
                <li><strong>Recent Reports & Receipts:</strong> View recent activity with action dropdowns for quick operations</li>
            </ul>
        </div>
        
        <div class="help-item">
            <h3>Understanding the Dashboard</h3>
            <p>The dashboard provides a comprehensive view of your business:</p>
            <ul>
                <li><strong>Today's Stats:</strong> Quick view of today's performance</li>
                <li><strong>Financial Health:</strong> Profit margins, ratios, averages</li>
                <li><strong>Growth Metrics:</strong> Month-over-month trends</li>
                <li><strong>Balance Sheet:</strong> Assets, liabilities, net worth</li>
                <li><strong>Operational Metrics:</strong> Rig utilization, job duration, efficiency</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Analytics Tabs</h3>
            <p>The dashboard includes multiple analytics views:</p>
            <ul>
                <li><strong>Overview:</strong> Key charts and trends</li>
                <li><strong>Financial Analysis:</strong> Detailed financial breakdowns</li>
                <li><strong>Operational Metrics:</strong> Efficiency and productivity</li>
                <li><strong>Performance Analysis:</strong> Comparative analytics</li>
                <li><strong>Forecast & Trends:</strong> Predictive analytics</li>
            </ul>
            <p>Use the filter panel to customize date ranges, rigs, clients, and job types.</p>
        </div>

        <div class="help-item">
            <h3>Quick Date Presets</h3>
            <p>Use the quick preset buttons for common date ranges:</p>
            <ul>
                <li>Today, This Week, This Month, This Quarter, This Year</li>
                <li>Last Month, Last Quarter, Last Year</li>
            </ul>
        </div>
        </div>
    </div>

    <!-- Configuration -->
    <div id="configuration" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">⚙️ Configuration</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Company Information</h3>
            <p>Configure your company details:</p>
            <ol>
                <li>Go to <strong>Configuration</strong> → <strong>Company Info</strong></li>
                <li>Upload your company logo (PNG, JPG, GIF, or SVG - max 2MB)</li>
                <li>Add company name, tagline, address, contact, and email</li>
                <li>Logo will appear in header, receipts, and reports</li>
            </ol>
            <p><strong>Logo Upload:</strong> Simply drag and drop your logo file or click to select. The logo preview appears immediately.</p>
        </div>

        <div class="help-item">
            <h3>Rigs Management</h3>
            <p>Add and manage your drilling rigs:</p>
            <ul>
                <li>Each rig needs a unique <strong>Rig Code</strong> (e.g., RIG001)</li>
                <li>Rig codes are used in Report IDs</li>
                <li>Set status (active, inactive, maintenance)</li>
                <li>Track truck model and registration</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Workers Management</h3>
            <p>Manage your workforce:</p>
            <ul>
                <li>Add worker names and roles</li>
                <li>Set default rates for different wage types</li>
                <li>Track contact information</li>
                <li>Set worker status (active/inactive)</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Materials & Pricing</h3>
            <p>Configure materials inventory:</p>
            <ul>
                <li>Track screen pipes, plain pipes, and gravel</li>
                <li>Set quantities and unit costs</li>
                <li>System automatically tracks usage from field reports</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Rod Lengths</h3>
            <p>Configure available rod lengths:</p>
            <ul>
                <li>Add standard rod lengths (e.g., 1.5m, 3m, 6m)</li>
                <li>Used when calculating total depth in field reports</li>
            </ul>
        </div>
        </div>
    </div>

    <!-- Data Management -->
    <div id="data-management" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">💾 Data Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Exporting Data</h3>
            <p>Export all system data for backup:</p>
            <ul>
                <li><strong>JSON Format:</strong> Complete data in structured JSON</li>
                <li><strong>SQL Format:</strong> SQL INSERT statements</li>
                <li><strong>CSV Format:</strong> ZIP file with CSV files for each table</li>
            </ul>
            <p><strong>Location:</strong> Configuration → Data Management → Export</p>
        </div>

        <div class="help-item">
            <h3>Importing Data</h3>
            <p>Import data from previous exports to prepopulate the system:</p>
            <ol>
                <li>Go to <strong>Data Management</strong> → <strong>Import</strong></li>
                <li>Select your export file (JSON, SQL, or ZIP)</li>
                <li>Choose import mode:
                    <ul>
                        <li><strong>Append:</strong> Add to existing data</li>
                        <li><strong>Replace:</strong> Delete existing data first (use with caution!)</li>
                    </ul>
                </li>
                <li>Click <strong>Import Data</strong></li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Onboarding Wizard</h3>
            <p>Use the guided wizard to import CSV spreadsheets for core datasets (clients, rigs, workers, catalog items) without touching SQL:</p>
            <ol>
                <li>Open <strong>System → Onboarding Wizard</strong>.</li>
                <li>Select the dataset and upload your CSV file (headers required).</li>
                <li>Map each ABBIS field to a CSV column with the drag-down selectors.</li>
                <li>Review the preview sample and adjust mappings if needed.</li>
                <li>Choose whether to update existing records and how to treat blank cells.</li>
                <li>Run the import to insert/update data and review any row-level errors.</li>
            </ol>
            <p>All uploads stay on the server temporarily in <code>storage/temp/</code> and are deleted automatically once the import completes.</p>
            <p><strong>CLI Option:</strong> Run <code>php scripts/import-dataset.php &lt;dataset&gt; &lt;file.csv&gt;</code> for automated imports. Use <code>--delimiter</code>, <code>--no-update</code>, and <code>--allow-blank-overwrite</code> flags as needed.</p>
        </div>

        <div class="help-item">
            <h3>Regulatory Forms Automation</h3>
            <p>Create and export compliance-ready forms directly from ABBIS data:</p>
            <ol>
                <li>Go to <strong>Resources → Regulatory Forms</strong>.</li>
                <li>Author your template with HTML and merge tags like <code>{{ field_report.site_name }}</code> or <code>{{ company.company_name }}</code>.</li>
                <li>Select the reference type (Field Report, Rig, Client, or Custom) and save.</li>
                <li>Use the <strong>Generate Form</strong> tab to pick a template, enter the record ID, and add optional context JSON (e.g., inspector name).</li>
                <li>Preview the rendered form, download the HTML, and access the history from the <strong>Generation Log</strong>.</li>
            </ol>
            <p>All generated files are stored in <code>storage/regulatory/</code> with a full audit trail in <code>regulatory_form_exports</code>.</p>
            <p>See <strong>Documentation → Regulatory Forms Automation Guide</strong> for merge-tag references and API usage.</p>
        </div>

        <div class="help-item">
            <h3>Purging System Data</h3>
            <p><strong>⚠️ DANGEROUS OPERATION:</strong></p>
            <p>To completely wipe all system data:</p>
            <ol>
                <li>Go to <strong>Data Management</strong> → Scroll to <strong>Danger Zone</strong></li>
                <li>Enter your password (twice for confirmation)</li>
                <li>Type <code>DELETE ALL DATA</code> exactly</li>
                <li>Confirm the warning dialogs</li>
                <li>All data will be permanently deleted</li>
            </ol>
            <p><strong>WARNING:</strong> This cannot be undone! Always export a backup first.</p>
        </div>
        </div>
    </div>

    <!-- Video Tutorials -->
    <div id="videos" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🎥 Video Tutorials</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>Getting Started with ABBIS</h3>
            <p>Watch this comprehensive video guide to get started with the system:</p>
            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: var(--bg); border-radius: 8px; margin: 20px 0;">
                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                        src="https://www.youtube.com/embed/dQw4w9WgXcQ" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            <p><small>Replace the YouTube embed URL above with your actual tutorial video link.</small></p>
        </div>

        <div class="help-item">
            <h3>Creating Your First Field Report</h3>
            <p>Step-by-step video tutorial on creating field reports:</p>
            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: var(--bg); border-radius: 8px; margin: 20px 0;">
                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                        src="https://www.youtube.com/embed/dQw4w9WgXcQ" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            <p><small>Replace the YouTube embed URL above with your actual tutorial video link.</small></p>
        </div>

        <div class="help-item">
            <h3>Dashboard & Analytics Overview</h3>
            <p>Learn how to use the dashboard and analytics features:</p>
            <div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; background: var(--bg); border-radius: 8px; margin: 20px 0;">
                <iframe style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;" 
                        src="https://www.youtube.com/embed/dQw4w9WgXcQ" 
                        frameborder="0" 
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                        allowfullscreen>
                </iframe>
            </div>
            <p><small><strong>To add your own videos:</strong> Replace the YouTube embed URLs above with your video links. To get the embed URL, go to your YouTube video → Share → Embed, and copy the video ID (the part after "embed/").</small></p>
        </div>
        </div>
    </div>

    <!-- CMS & Website Management -->
    <div id="cms" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🌐 CMS & Website Management</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>What is the CMS System?</h3>
            <p>
                The <strong>Content Management System (CMS)</strong> is a complete WordPress-like website management system 
                integrated into ABBIS. It allows you to create and manage your company website, including pages, blog posts, 
                product catalog, and e-commerce functionality.
            </p>
            <p>
                <strong>Access:</strong> System → CMS Website → Open CMS Admin
            </p>
        </div>

        <div class="help-item">
            <h3>CMS Features Overview</h3>
            <p>The CMS includes the following features:</p>
            <ul>
                <li><strong>Pages Management:</strong> Create and edit static pages (Homepage, About, Services, etc.)</li>
                <li><strong>Blog/Posts:</strong> Create blog posts with categories and tags</li>
                <li><strong>Media Library:</strong> Advanced media management with folders, search, and bulk actions</li>
                <li><strong>E-Commerce:</strong> Complete WooCommerce-like online store with cart, checkout, and payments</li>
                <li><strong>Menu Management:</strong> WordPress-like menu builder with drag-and-drop ordering</li>
                <li><strong>Widget System:</strong> Add widgets to sidebars and footer areas</li>
                <li><strong>Theme Customization:</strong> Customize colors, typography, and layout settings</li>
                <li><strong>User Management:</strong> Advanced user roles and permissions</li>
                <li><strong>Legal Documents:</strong> Manage drilling agreements, terms, and privacy policies</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Accessing the CMS</h3>
            <p>
                <strong>For Administrators:</strong>
            </p>
            <ol>
                <li>Go to <strong>System</strong> in the main navigation</li>
                <li>Click on <strong>CMS Website</strong> card</li>
                <li>Click <strong>Open CMS Admin</strong> to access the admin dashboard</li>
                <li>Or click <strong>View Website</strong> to see the public site</li>
            </ol>
            <p>
                <strong>Direct Links:</strong>
            </p>
            <ul>
                <li>CMS Admin: <code>/cms/admin/</code></li>
                <li>Public Website: <code>/cms/</code></li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Managing Pages</h3>
            <p>
                <strong>Creating/Editing Pages:</strong>
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Pages</strong></li>
                <li>Click <strong>Add New</strong> or edit an existing page</li>
                <li>Choose your editor:
                    <ul>
                        <li><strong>CKEditor:</strong> Rich text editor with formatting tools</li>
                        <li><strong>GrapesJS:</strong> Visual drag-and-drop page builder</li>
                    </ul>
                </li>
                <li>Set the page slug (URL), SEO title, and description</li>
                <li>Publish the page</li>
            </ol>
            <p>
                <strong>Homepage:</strong> The homepage is listed at the top of the Pages list with a blue "Homepage" badge. 
                You can edit it just like any other page.
            </p>
        </div>

        <div class="help-item">
            <h3>Managing Blog Posts</h3>
            <p>
                <strong>Creating Posts:</strong>
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Posts</strong></li>
                <li>Click <strong>Add New</strong></li>
                <li>Write your content using CKEditor or GrapesJS</li>
                <li>Assign categories and tags</li>
                <li>Set featured image (optional)</li>
                <li>Publish or save as draft</li>
            </ol>
            <p>
                <strong>Categories:</strong> Organize posts by creating categories in CMS Admin → <strong>Categories</strong>
            </p>
        </div>

        <div class="help-item">
            <h3>Request Forms - Quote & Rig Requests</h3>
            <p>
                The CMS includes two public-facing request forms that integrate directly with ABBIS:
            </p>
            <p><strong>📋 Request a Quote Form:</strong></p>
            <ul>
                <li><strong>URL:</strong> <code>/cms/quote</code></li>
                <li><strong>For:</strong> Direct clients and homeowners who need complete borehole services</li>
                <li><strong>Services Available:</strong>
                    <ul>
                        <li>Drilling (borehole drilling with drilling machine)</li>
                        <li>Construction (screen pipe, plain pipe, gravels installation)</li>
                        <li>Mechanization (pump installation and accessories - can select preferred pumps from catalog)</li>
                        <li>Yield Test (water yield testing with all details)</li>
                        <li>Chemical Test (laboratory water quality testing)</li>
                        <li>Polytank Stand Construction (optional)</li>
                    </ul>
                </li>
                <li><strong>Integration:</strong> Automatically creates client record and CRM follow-up in ABBIS</li>
                <li><strong>Management:</strong> View and manage in ABBIS → Requests Management or CRM Dashboard</li>
            </ul>
            <p><strong>🚛 Request Rig Form:</strong></p>
            <ul>
                <li><strong>URL:</strong> <code>/cms/rig-request</code></li>
                <li><strong>For:</strong> Agents and contractors who want to rent drilling rigs</li>
                <li><strong>Features:</strong>
                    <ul>
                        <li>Google Maps integration for location selection (click map or search)</li>
                        <li>Number of boreholes to drill</li>
                        <li>Estimated budget (optional)</li>
                        <li>Preferred start date</li>
                        <li>Urgency level (Low, Medium, High, Urgent)</li>
                        <li>Auto-generated request number (RR-YYYYMMDD-####)</li>
                    </ul>
                </li>
                <li><strong>Integration:</strong> Automatically creates client record (if existing client) and CRM follow-up</li>
                <li><strong>Management:</strong> View and manage in ABBIS → Requests Management or CRM Dashboard</li>
                <li><strong>Workflow:</strong> New → Under Review → Negotiating → Dispatched → Completed</li>
            </ul>
            <p><strong>Distinction in CRM:</strong> Both request types are clearly distinguished in the CRM Dashboard:</p>
            <ul>
                <li><strong>📋 Request a Quote:</strong> Blue border and color (#0ea5e9) - "REQUEST A QUOTE" label</li>
                <li><strong>🚛 Request Rig:</strong> Green border and color (#059669) - "REQUEST RIG" label</li>
            </ul>
            <p><strong>Page Titles:</strong> Forms automatically show the correct title based on the request type. If accessed via type parameter, forms redirect to the appropriate page.</p>
        </div>

        <div class="help-item">
            <h3>Vacancies & Recruitment</h3>
            <p>
                The Vacancies module is CMS-driven and matches the public website theme. Open roles appear on <code>/cms/vacancies</code>, and applications are captured in ABBIS automatically.
            </p>
            <h4>Creating & Publishing Vacancies</h4>
            <ol>
                <li>Go to CMS Admin → <strong>Recruitment</strong> → <strong>Vacancies</strong>.</li>
                <li>Click <strong>Add Vacancy</strong> and enter title, slug, employment type, seniority, location, salary range, and rich descriptions.</li>
                <li>Set the status to <strong>Published</strong> to display it on the website (Draft keeps it internal).</li>
                <li>Optionally set opening/closing dates; expired roles automatically show a “Closed” badge on the public page.</li>
            </ol>
            <h4>Customising the Public Page</h4>
            <ul>
                <li>Edit the <strong>Vacancies</strong> CMS page (Pages → Vacancies) to add introductory content, imagery, or benefits sections.</li>
                <li>The hero title and lead copy pull from the page title and SEO description when provided.</li>
                <li>The layout remains responsive and inherits your active CMS theme and typography settings.</li>
            </ul>
            <h4>Application Flow</h4>
            <ul>
                <li>Visitors click <strong>Apply Now</strong> to open a modal form with personal details, experience, resume upload, and availability.</li>
                <li>Submissions create candidate + application records inside ABBIS and appear instantly in CMS Admin → <strong>Recruitment</strong>.</li>
                <li>Use the status board, filters, and pipeline metrics to track progress from New to Interview, Offer, Hired, or Declined.</li>
            </ul>
            <h4>Add Vacancies to Menus</h4>
            <ol>
                <li>Open CMS Admin → <strong>Menus</strong>.</li>
                <li>Select the menu location (e.g., Main Navigation) and switch to the <strong>Links</strong> or <strong>Pages</strong> tab.</li>
                <li>Check <strong>Vacancies</strong> and click <strong>Add Selected to Menu</strong>.</li>
                <li>Drag to reorder, then <strong>Save Menu</strong>. The new entry now points to <code>/cms/vacancies</code>.</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Media Library</h3>
            <p>
                The advanced media library allows you to:
            </p>
            <ul>
                <li>Upload multiple files at once (drag & drop)</li>
                <li>Organize files into folders</li>
                <li>Search and filter by type (image, video, document, etc.)</li>
                <li>Edit metadata (title, alt text, description, caption)</li>
                <li>Bulk actions (delete, move)</li>
                <li>View file usage and statistics</li>
            </ul>
            <p>
                <strong>Access:</strong> CMS Admin → <strong>Media</strong>
            </p>
        </div>

        <div class="help-item">
            <h3>E-Commerce System (WooCommerce Clone)</h3>
            <p>
                The CMS includes a complete WooCommerce-like e-commerce system:
            </p>
            <p><strong>Features:</strong></p>
            <ul>
                <li><strong>Product Catalog:</strong> Manage products from ABBIS catalog items</li>
                <li><strong>Shopping Cart:</strong> Add products to cart and manage quantities</li>
                <li><strong>Multi-Step Checkout:</strong> Cart → Checkout → Payment → Confirmation</li>
                <li><strong>Payment Methods:</strong> Mobile Money, Bank Transfer, Cash on Delivery, Paystack, Flutterwave</li>
                <li><strong>Order Management:</strong> View, edit, and manage orders in CMS Admin → Orders</li>
                <li><strong>Shipping Methods:</strong> Configure shipping options with costs</li>
                <li><strong>Tax Calculation:</strong> Set up tax rates</li>
                <li><strong>Coupons:</strong> Create discount codes</li>
            </ul>
            <p>
                <strong>Checkout Flow:</strong>
            </p>
            <ol>
                <li>Add products to cart from <code>/cms/shop</code></li>
                <li>Review cart at <code>/cms/cart</code></li>
                <li>Proceed to checkout at <code>/cms/checkout</code></li>
                <li>Fill billing details and select payment method</li>
                <li>Complete payment at <code>/cms/payment</code></li>
                <li>View order confirmation</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Menu Management</h3>
            <p>
                Create and manage navigation menus like WordPress:
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Menus</strong></li>
                <li>Create a new menu or edit existing</li>
                <li>Add menu items:
                    <ul>
                        <li><strong>Pages:</strong> Link to any published page</li>
                        <li><strong>Posts:</strong> Link to blog posts</li>
                        <li><strong>Categories:</strong> Link to category pages</li>
                        <li><strong>Custom Links:</strong> Any URL</li>
                        <li><strong>Home:</strong> Link to homepage</li>
                    </ul>
                </li>
                <li>Drag and drop to reorder items</li>
                <li>Assign menu to locations (Primary, Footer)</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Widget Management</h3>
            <p>
                Add widgets to sidebars and footer areas:
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Appearance</strong> → <strong>Widgets</strong> tab</li>
                <li>Select a widget area (Sidebar, Footer columns, etc.)</li>
                <li>Click <strong>Add Widget</strong></li>
                <li>Choose widget type:
                    <ul>
                        <li><strong>Text/HTML:</strong> Custom content</li>
                        <li><strong>Recent Posts:</strong> Display latest blog posts</li>
                        <li><strong>Categories:</strong> List post categories</li>
                        <li><strong>Search:</strong> Search form</li>
                        <li><strong>Pages:</strong> List of pages</li>
                    </ul>
                </li>
                <li>Configure widget settings and save</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Theme Customization</h3>
            <p>
                Customize your website's appearance:
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Appearance</strong> → <strong>Themes</strong> tab</li>
                <li>Click <strong>Customize</strong> on your active theme</li>
                <li>Adjust:
                    <ul>
                        <li><strong>Colors:</strong> Primary, secondary, links, background, text</li>
                        <li><strong>Header:</strong> Background and text colors</li>
                        <li><strong>Footer:</strong> Background and text colors</li>
                        <li><strong>Buttons:</strong> Background and text colors</li>
                        <li><strong>Typography:</strong> Font family, size, border radius</li>
                    </ul>
                </li>
                <li>Use the live preview to see changes in real-time</li>
                <li>Save changes when satisfied</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Hero Banner Configuration</h3>
            <p>
                Configure the homepage hero banner (large image section below header):
            </p>
            <ol>
                <li>Go to CMS Admin → <strong>Settings</strong> → <strong>Homepage</strong> tab</li>
                <li>Upload a hero banner image (recommended: 1920x800px)</li>
                <li>Set hero title and subtitle</li>
                <li>Configure button texts and links</li>
                <li>Adjust overlay opacity for text readability</li>
                <li>Save settings</li>
            </ol>
        </div>

        <div class="help-item">
            <h3>Legal Documents Management</h3>
            <p>
                Manage system-wide legal documents (accessible from both CMS and ABBIS):
            </p>
            <p><strong>Access:</strong> System → Legal Documents</p>
            <p><strong>Features:</strong></p>
            <ul>
                <li>Create and edit legal documents (Drilling Agreement, Terms, Privacy Policy)</li>
                <li>Version tracking</li>
                <li>Effective date management</li>
                <li>Public viewing and print versions</li>
                <li>Rich text editing with CKEditor</li>
            </ul>
            <p>
                <strong>Default Documents:</strong>
            </p>
            <ul>
                <li>Drilling Agreement (Terms & Conditions)</li>
                <li>Terms of Service</li>
                <li>Privacy Policy</li>
            </ul>
            <p>
                Documents are automatically accessible at <code>/cms/legal/[slug]</code> and can be linked from menus.
            </p>
        </div>

        <div class="help-item">
            <h3>User Management (CMS)</h3>
            <p>
                Advanced user management with WordPress-like features:
            </p>
            <ul>
                <li><strong>User Roles:</strong> Admin, Editor, Author, Manager, Supervisor, Clerk, Contributor, Subscriber</li>
                <li><strong>Bulk Actions:</strong> Delete, change role, activate/deactivate multiple users</li>
                <li><strong>Search & Filter:</strong> Find users by name, email, role, or status</li>
                <li><strong>Activity Logging:</strong> Track user activity and last login</li>
                <li><strong>User Profiles:</strong> Edit user details, change passwords, manage capabilities</li>
            </ul>
            <p>
                <strong>Access:</strong> CMS Admin → <strong>Users</strong>
            </p>
        </div>

        <div class="help-item">
            <h3>Settings & Configuration</h3>
            <p>
                Comprehensive settings in CMS Admin → <strong>Settings</strong>:
            </p>
            <ul>
                <li><strong>General:</strong> Site title, tagline, logo, favicon, admin email</li>
                <li><strong>Homepage:</strong> Hero banner configuration</li>
                <li><strong>Reading:</strong> Front page settings, blog display</li>
                <li><strong>E-Commerce:</strong> Payment methods, shipping, taxes, coupons</li>
                <li><strong>Email:</strong> Email notifications for orders</li>
                <li><strong>Permalinks:</strong> URL structure settings</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Order Management</h3>
            <p>
                Manage e-commerce orders:
            </p>
            <ul>
                <li><strong>View Orders:</strong> CMS Admin → <strong>Orders</strong></li>
                <li><strong>Order Status:</strong> Pending, Processing, Completed, Cancelled</li>
                <li><strong>Edit Orders:</strong> Update customer info, order items, totals</li>
                <li><strong>Delete Orders:</strong> Remove orders if needed</li>
                <li><strong>Order Details:</strong> View complete order information, payment status</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>Payment Methods</h3>
            <p>
                Configure payment gateways:
            </p>
            <ul>
                <li><strong>Mobile Money:</strong> MTN, Vodafone Cash, AirtelTigo Money</li>
                <li><strong>Bank Transfer:</strong> Direct bank transfers</li>
                <li><strong>Cash on Delivery:</strong> Pay when order is delivered</li>
                <li><strong>Paystack:</strong> Online card payments (requires API keys)</li>
                <li><strong>Flutterwave:</strong> Online card payments (requires API keys)</li>
            </ul>
            <p>
                Payment methods are automatically enabled by default. You can manage them in CMS Admin → <strong>Settings</strong> → <strong>E-Commerce</strong> tab.
            </p>
        </div>

        <div class="help-item">
            <h3>SSO Integration (Single Sign-On)</h3>
            <p>
                ABBIS supports Single Sign-On (SSO) for seamless access between systems:
            </p>
            <p><strong>ABBIS ↔ CMS SSO:</strong></p>
            <ul>
                <li>When logged into ABBIS as admin, you can access CMS admin directly</li>
                <li>When logged into CMS admin, you'll see a link to ABBIS system</li>
                <li>SSO uses secure token-based authentication</li>
                <li>No need to log in separately to both systems</li>
            </ul>
            <p><strong>ABBIS ↔ Client Portal SSO:</strong></p>
            <ul>
                <li>Administrators, Super Admins, and Clients can access the Client Portal using their ABBIS credentials</li>
                <li>Access via header icon, navigation menu, or login page destination selector</li>
                <li>Automatic SSO token generation and verification</li>
                <li>Admin Mode enables administrators to view all clients or specific client data</li>
                <li>Secure token expiration (5 minutes) with HMAC-SHA256 signature verification</li>
                <li>See <strong>Client Portal</strong> section for detailed SSO documentation</li>
            </ul>
            <p><strong>SSO Security Features:</strong></p>
            <ul>
                <li>Time-limited tokens with configurable expiration</li>
                <li>Cryptographic signature verification</li>
                <li>Role-based access control</li>
                <li>CSRF protection on all SSO endpoints</li>
                <li>Activity logging for audit purposes</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>CMS URL Structure</h3>
            <p>
                The CMS uses clean, SEO-friendly URLs:
            </p>
            <ul>
                <li><strong>Homepage:</strong> <code>/cms/</code> or <code>/</code></li>
                <li><strong>Pages:</strong> <code>/cms/[page-slug]</code></li>
                <li><strong>Blog Posts:</strong> <code>/cms/post/[post-slug]</code></li>
                <li><strong>Shop:</strong> <code>/cms/shop</code></li>
                <li><strong>Cart:</strong> <code>/cms/cart</code></li>
                <li><strong>Checkout:</strong> <code>/cms/checkout</code></li>
                <li><strong>Payment:</strong> <code>/cms/payment?order=[order-number]</code></li>
                <li><strong>Legal Documents:</strong> <code>/cms/legal/[document-slug]</code></li>
            </ul>
        </div>

        </div>
    </div>

    <!-- Client Portal -->
    <div id="client-portal" class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🌐 Client Portal</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Overview</h3>
                <p>The Client Portal is a self-service portal that allows clients to manage their quotes, invoices, payments, and projects. It features Single Sign-On (SSO) integration with ABBIS, allowing seamless access for administrators and clients.</p>
            </div>

            <div class="help-item">
                <h3>Accessing the Client Portal</h3>
                <p><strong>For Clients:</strong></p>
                <ul>
                    <li>Direct URL: <code>/client-portal/login.php</code> or <code>/cms/client/login.php</code></li>
                    <li>Login with username and password (role: <code>client</code>)</li>
                    <li>Automatic redirect after logging into ABBIS as a client</li>
                </ul>
                <p><strong>For Administrators:</strong></p>
                <ul>
                    <li><strong>From ABBIS Header:</strong> Click the Client Portal icon button (top-right header)</li>
                    <li><strong>From Navigation Menu:</strong> Click "Client Portal" in the left sidebar</li>
                    <li><strong>From Login Page:</strong> Select "Client Portal" from the "Login Destination" dropdown (admin only)</li>
                    <li><strong>Direct URL:</strong> <code>/client-portal/login.php</code> - SSO will automatically authenticate if already logged into ABBIS</li>
                </ul>
                <p><strong>SSO Access:</strong> If you're already logged into ABBIS as an admin, super admin, or client, you can access the Client Portal without re-entering credentials. The system automatically generates a secure SSO token for seamless access.</p>
            </div>

            <div class="help-item">
                <h3>Single Sign-On (SSO) Integration</h3>
                <p>The Client Portal features full SSO integration with ABBIS:</p>
                <ul>
                    <li><strong>Automatic Authentication:</strong> If logged into ABBIS, you can access the Client Portal without re-logging in</li>
                    <li><strong>Secure Token-Based:</strong> SSO uses secure, time-limited tokens (5-minute expiration) with HMAC-SHA256 signature verification</li>
                    <li><strong>Role-Based Access:</strong> Supports Admin, Super Admin, and Client roles</li>
                    <li><strong>Admin Mode:</strong> Administrators can view all clients' data or a specific client's data in "Admin Mode"</li>
                    <li><strong>Visual Indicators:</strong> Admin mode is clearly indicated with badges and banners in the portal</li>
                </ul>
                <p><strong>How SSO Works:</strong></p>
                <ol>
                    <li>When you click the Client Portal link from ABBIS, the system generates a secure SSO token</li>
                    <li>The token contains your user ID, username, role, and expiration timestamp</li>
                    <li>The Client Portal verifies the token signature and expiration</li>
                    <li>If valid, you're automatically logged in and redirected to the dashboard</li>
                    <li>If already logged into ABBIS, the portal detects your session and enables SSO automatically</li>
                </ol>
            </div>

            <div class="help-item">
                <h3>Admin Mode</h3>
                <p>When administrators access the Client Portal via SSO, they enter "Admin Mode":</p>
                <ul>
                    <li><strong>All Clients Overview:</strong> View aggregated statistics across all clients</li>
                    <li><strong>Specific Client View:</strong> Select a specific client to view their detailed data</li>
                    <li><strong>Admin Badge:</strong> Clear visual indicator showing you're in admin mode</li>
                    <li><strong>Quick Navigation:</strong> Easy access back to ABBIS dashboard</li>
                    <li><strong>Client Selection:</strong> Use the client selector to switch between viewing all clients or a specific client</li>
                </ul>
                <p><strong>Admin Mode Features:</strong></p>
                <ul>
                    <li>View all quotes, invoices, payments, and projects across all clients</li>
                    <li>Access any client's detailed information</li>
                    <li>Monitor client portal activity and usage</li>
                    <li>Assist clients with their portal access and issues</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Client Portal Features</h3>
                <p><strong>Dashboard:</strong></p>
                <ul>
                    <li>Overview of quotes, invoices, payments, and projects</li>
                    <li>Quick statistics and recent activity</li>
                    <li>Quick action buttons for common tasks</li>
                </ul>
                <p><strong>Quotes:</strong></p>
                <ul>
                    <li>View all quotes sent to the client</li>
                    <li>Quote status (pending, accepted, rejected)</li>
                    <li>Quote details with line items</li>
                    <li>Download printable quote documents</li>
                </ul>
                <p><strong>Invoices:</strong></p>
                <ul>
                    <li>View all invoices and outstanding balances</li>
                    <li>Invoice status tracking</li>
                    <li>Payment history per invoice</li>
                    <li>Download printable invoice documents</li>
                </ul>
                <p><strong>Payments:</strong></p>
                <ul>
                    <li>Make online payments via Paystack or Flutterwave</li>
                    <li>Record offline payments (mobile money, bank transfer, cash)</li>
                    <li>Payment history and receipts</li>
                    <li>Automatic accounting integration</li>
                </ul>
                <p><strong>Projects:</strong></p>
                <ul>
                    <li>View all field reports/projects for the client</li>
                    <li>Project status and progress</li>
                    <li>Link to detailed project information</li>
                </ul>
                <p><strong>Profile:</strong></p>
                <ul>
                    <li>Update personal information</li>
                    <li>Change password</li>
                    <li>View account settings</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Security Features</h3>
                <ul>
                    <li><strong>SSO Token Expiration:</strong> Tokens expire after 5 minutes for security</li>
                    <li><strong>Token Signature Verification:</strong> All tokens are cryptographically signed</li>
                    <li><strong>CSRF Protection:</strong> All forms include CSRF token validation</li>
                    <li><strong>Session Management:</strong> Secure session handling with timeout</li>
                    <li><strong>Activity Logging:</strong> All portal activity is logged for audit purposes</li>
                    <li><strong>Role-Based Access:</strong> Only authorized roles can access the portal</li>
                </ul>
            </div>

            <div class="help-item">
                <h3>Best Practices</h3>
                <ul>
                    <li><strong>Client Onboarding:</strong> Create client users in ABBIS Users module with role <code>client</code> and link to client record</li>
                    <li><strong>Email Linking:</strong> Users are automatically linked to clients by matching email addresses</li>
                    <li><strong>Admin Access:</strong> Use Admin Mode to assist clients and monitor portal usage</li>
                    <li><strong>Payment Configuration:</strong> Ensure payment gateways are properly configured in CMS → Payment Methods</li>
                    <li><strong>Regular Monitoring:</strong> Review client portal activity logs regularly</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- FAQs -->
    <div class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">❓ Frequently Asked Questions</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>How do I change my password?</h3>
            <p>You can change your password in two ways:</p>
            <ul>
                <li><strong>From Profile:</strong> Go to <strong>Profile</strong> → <strong>Change Password</strong> section → Enter current password and new password</li>
                <li><strong>From Users Module (Admin):</strong> Go to <strong>Users</strong> (admin menu) → Edit your user → Change password</li>
                <li><strong>Forgot Password:</strong> If you forgot your password, click "Forgot Password?" on the login page to reset it via email</li>
            </ul>
        </div>

        <div class="help-item">
            <h3>How do I access the Client Portal?</h3>
            <p>There are several ways to access the Client Portal:</p>
            <ul>
                <li><strong>As a Client:</strong> Go to <code>/client-portal/login.php</code> and log in with your client credentials</li>
                <li><strong>As an Admin:</strong> Click the Client Portal icon in the ABBIS header, or select "Client Portal" from the navigation menu</li>
                <li><strong>From Login Page:</strong> Admins can select "Client Portal" from the "Login Destination" dropdown</li>
                <li><strong>SSO Access:</strong> If already logged into ABBIS, you'll be automatically authenticated via SSO</li>
            </ul>
            <p>See the <strong>Client Portal</strong> section for complete documentation.</p>
        </div>

        <div class="help-item">
            <h3>My account is locked. How do I unlock it?</h3>
            <p>If your account is locked due to too many failed login attempts:</p>
            <ul>
                <li><strong>Wait:</strong> The account automatically unlocks after 15 minutes</li>
                <li><strong>Command Line (Admin):</strong> Run <code>php scripts/unlock-account.php &lt;username&gt;</code> to immediately unlock</li>
                <li><strong>User Management (Admin):</strong> Go to <strong>Users</strong> → Find the user → Click "Unlock" button</li>
                <li><strong>API (Admin):</strong> POST to <code>/api/unlock-account.php</code> with username and CSRF token</li>
            </ul>
            <p>The login page will show lockout information including remaining time until automatic unlock.</p>
        </div>

        <div class="help-item">
            <h3>Can I edit a field report after saving?</h3>
            <p>Yes! Go to Field Reports List → Click the <strong>Actions</strong> dropdown → <strong>Edit Report</strong>. This opens a modal with editable fields for site name, region, location description, supervisor, remarks, incident log, solution log, and recommendation log.</p>
        </div>

        <div class="help-item">
            <h3>How do I generate a payslip for a worker?</h3>
            <p>Go to <strong>Payroll</strong> → Click <strong>Generate Payslip</strong> → Select worker and date range → Click <strong>Generate Payslip</strong>. The payslip opens in a new tab with all payroll entries for that period, including totals and net pay calculations.</p>
        </div>

        <div class="help-item">
            <h3>How do I mark payroll entries as paid?</h3>
            <p>In the Payroll page, find the entry you want to update → Click the <strong>Actions</strong> dropdown → Select <strong>Mark as Paid</strong> or <strong>Mark as Unpaid</strong>. The status updates immediately.</p>
        </div>

        <div class="help-item">
            <h3>Why can't I see the logo I uploaded?</h3>
            <p>
                Make sure the file is:
                <ul>
                    <li>Under 2MB in size</li>
                    <li>PNG, JPG, GIF, or SVG format</li>
                    <li>Try refreshing the page after upload</li>
                </ul>
            </p>
        </div>

        <div class="help-item">
            <h3>How do I filter reports by date?</h3>
            <p>In Field Reports List, use the Start Date and End Date filters, or use quick presets in the Dashboard analytics.</p>
        </div>

        <div class="help-item">
            <h3>What's the difference between Receipt and Technical Report?</h3>
            <p>
                <strong>Receipt:</strong> Contains ONLY financial information (payments, amounts, client payments, materials cost if purchased). Uses Reference ID and unique Receipt Number.<br>
                <strong>Technical Report:</strong> Contains ONLY technical details (drilling specs, materials, site information). Client-facing document without financial or personnel information. Uses the same Reference ID as the receipt for cross-referencing.<br>
                Both share the same Reference ID for cross-referencing. Receipts also have a unique Receipt Number for each payment.
            </p>
        </div>

        <div class="help-item">
            <h3>How do I access the CMS website?</h3>
            <p>
                Go to <strong>System</strong> → <strong>CMS Website</strong> → Click <strong>Open CMS Admin</strong> or <strong>View Website</strong>.
                The CMS link is also available in the top right corner of the ABBIS header (for admins).
            </p>
        </div>

        <div class="help-item">
            <h3>Why don't I see payment methods in checkout?</h3>
            <p>
                Payment methods are automatically created and enabled. If you don't see them, clear your browser cache 
                or refresh the page. They should appear in the checkout form.
            </p>
        </div>

        <div class="help-item">
            <h3>How do I edit the homepage?</h3>
            <p>
                Go to CMS Admin → <strong>Pages</strong> → Find the page marked "Homepage" → Click <strong>Edit</strong>. 
                You can use either CKEditor (rich text) or GrapesJS (visual builder) to edit the content.
            </p>
        </div>

        <div class="help-item">
            <h3>Where can I manage legal documents?</h3>
            <p>
                Go to <strong>System</strong> → <strong>Legal Documents</strong> to view and manage all legal documents. 
                You can also access this from CMS Admin → <strong>Legal Documents</strong>.
            </p>
        </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">🔗 Quick Links</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        <div class="help-quick-links">
            <a href="dashboard.php">Go to Dashboard</a>
            <a href="field-reports.php">Create Field Report</a>
            <a href="crm.php?action=clients">View Clients</a>
            <a href="config.php">Configuration</a>
            <a href="data-management.php">Data Management</a>
            <a href="field-reports-list.php">All Reports</a>
        </div>
        </div>
    </div>

    </div> <!-- /.help-sections-grid -->
    <!-- Modal for topic details -->
    <div id="helpModalBackdrop" class="help-modal-backdrop" onclick="closeHelpModal()"></div>
    <div id="helpModal" class="help-modal" role="dialog" aria-modal="true" aria-labelledby="helpModalTitle">
        <div class="help-modal-header">
            <div id="helpModalTitle" class="help-modal-title">Help</div>
            <button class="help-modal-close" onclick="closeHelpModal()">✕</button>
        </div>
        <div id="helpModalBody" class="help-modal-body"></div>
    </div>

</div>

<script>
function scrollToSection(sectionId) {
    const grid = document.querySelector('.help-sections-grid');
    if (grid && !grid.classList.contains('visible')) {
        grid.classList.add('visible');
    }

    const section = document.getElementById(sectionId);
    if (!section) return;

    if (section.classList.contains('collapsed')) {
        section.classList.remove('collapsed');
        const icon = section.querySelector('.toggle-icon');
        if (icon) icon.textContent = '▾';
    }

    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function searchHelp(query) {
    if (query.length < 2) {
        document.querySelectorAll('.help-section, .help-item').forEach(el => {
            el.style.display = '';
        });
        return;
    }
    
    query = query.toLowerCase();
    document.querySelectorAll('.help-item').forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(query) ? '' : 'none';
    });
    
    // Show/hide sections based on visible items
    document.querySelectorAll('.help-section').forEach(section => {
        const visibleItems = section.querySelectorAll('.help-item[style=""]').length;
        section.style.display = visibleItems > 0 ? '' : 'none';
    });
}

// Handle hash navigation
window.addEventListener('load', function() {
    if (window.location.hash) {
        scrollToSection(window.location.hash.substring(1));
    }
});

function openHelpTopic(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    const titleEl = section.querySelector('h2');
    const contentEl = section.querySelector('.help-section-content') || section;
    const title = titleEl ? titleEl.textContent : 'Help';
    document.getElementById('helpModalTitle').textContent = title;
    document.getElementById('helpModalBody').innerHTML = contentEl.innerHTML;
    document.getElementById('helpModalBackdrop').style.display = 'block';
    document.getElementById('helpModal').style.display = 'block';
}

function closeHelpModal() {
    document.getElementById('helpModalBackdrop').style.display = 'none';
    document.getElementById('helpModal').style.display = 'none';
}

function toggleSection(headerEl) {
    return;
}

function showFullGuide() {
    const grid = document.querySelector('.help-sections-grid');
    if (!grid) return;
    grid.classList.add('visible');
    grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function expandAllSections() {
    document.querySelectorAll('.help-section').forEach(sec => {
        sec.classList.remove('collapsed');
        const icon = sec.querySelector('.toggle-icon');
        if (icon) icon.textContent = '▾';
    });
}

function collapseAllSections() {
    document.querySelectorAll('.help-section').forEach(sec => {
        if (!sec.classList.contains('collapsed')) sec.classList.add('collapsed');
        const icon = sec.querySelector('.toggle-icon');
        if (icon) icon.textContent = '▸';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>

