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
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        align-items: start;
    }
    @media (max-width: 1100px) {
        .help-sections-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Collapsible sections */
    .help-section.collapsed .help-section-content { display: none; }
    .help-section .section-header { display: flex; justify-content: space-between; align-items: center; cursor: pointer; }
    .help-section .toggle-icon { font-size: 18px; color: var(--secondary); }
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
    /* Compact layout + modal to reduce scrolling */
    .help-section { display: none; }
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

    <!-- CRM -->
    <div id="crm" class="help-section">
        <h2>🤝 CRM</h2>
        <div class="help-item">
            <h3>Overview</h3>
            <p>Manage follow-ups, client communications, and templates.</p>
            <ul>
                <li>Tabs: Dashboard, Clients, Follow-ups, Emails, Templates</li>
                <li>Dark mode compliant, simplified tab styling</li>
            </ul>
        </div>
        <div class="help-item">
            <h3>Follow-ups</h3>
            <ul>
                <li>Filter by status, client, assigned, date (today/week/overdue)</li>
                <li>Schedule, complete, and track outcomes</li>
            </ul>
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
                <h3>Integrations</h3>
                <p>Save QuickBooks/Zoho Books credentials in Accounting → Integrations to prepare OAuth and export adapters.</p>
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
                <p>Auto-build a 2–4 week schedule balancing rigs, crew, travel, and cash flow. Enable in Feature Management.</p>
                <p>Open via Resources → Planner.</p>
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
            <h2 style="margin: 0;">📶 Field Offline Mode</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
            <div class="help-item">
                <h3>Use Offline</h3>
                <p>Install the PWA and open <code>offline-field-report.html</code> to capture basic reports offline. Data is stored locally and can be synced later.</p>
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
                <li><strong>Dashboard</strong> - Overview of KPIs and analytics</li>
                <li><strong>Field Reports</strong> - Create and manage field operation reports</li>
                <li><strong>Payroll</strong> - Comprehensive payroll management and payslip generation</li>
                <li><strong>Materials</strong> - Track materials inventory</li>
                <li><strong>Finance</strong> - Financial summaries and reports</li>
                <li><strong>Loans</strong> - Track worker loans and rig fee debts</li>
                <li><strong>Clients</strong> - Manage clients and view transaction history</li>
                <li><strong>Configuration</strong> - System settings (Admin only)</li>
                <li><strong>Data Management</strong> - Import/export data (Admin only)</li>
            </ul>
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
            <p>Field reports record all details of a drilling job:</p>
            <ol>
                <li>Navigate to <strong>Field Reports</strong> → <strong>Create New Report</strong></li>
                <li>Fill in the form across 5 tabs:
                    <ul>
                        <li><strong>Basic Info:</strong> Date, rig, site location, client</li>
                        <li><strong>Financial:</strong> Income, expenses, deposits</li>
                        <li><strong>Operations:</strong> Depth, duration, materials used</li>
                        <li><strong>Payroll:</strong> Worker wages and payments</li>
                        <li><strong>Expenses:</strong> Daily expenses with catalog integration</li>
                    </ul>
                </li>
                <li>Click <strong>Save Report</strong> - a unique Report ID will be generated</li>
            </ol>
            <p><strong>Tip:</strong> Client information is automatically extracted and saved when you enter a client name.</p>
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
                Administrators can seamlessly move between ABBIS and CMS systems:
            </p>
            <ul>
                <li>When logged into ABBIS as admin, you can access CMS admin directly</li>
                <li>When logged into CMS admin, you'll see a link to ABBIS system</li>
                <li>SSO uses secure token-based authentication</li>
                <li>No need to log in separately to both systems</li>
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

    <!-- FAQs -->
    <div class="help-section collapsed">
        <div class="section-header" onclick="toggleSection(this)">
            <h2 style="margin: 0;">❓ Frequently Asked Questions</h2>
            <span class="toggle-icon">▸</span>
        </div>
        <div class="help-section-content">
        
        <div class="help-item">
            <h3>How do I change my password?</h3>
            <p>Go to <strong>Users</strong> (admin menu) → Edit your user → Change password</p>
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
    document.getElementById(sectionId).scrollIntoView({ behavior: 'smooth', block: 'start' });
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
    const section = headerEl.closest('.help-section');
    const icon = headerEl.querySelector('.toggle-icon');
    const isCollapsed = section.classList.toggle('collapsed');
    icon.textContent = isCollapsed ? '▸' : '▾';
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

