<?php
/**
 * Comprehensive Help & User Guide
 */
$page_title = 'Help & User Guide';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';

$auth->requireAuth();

require_once '../includes/header.php';
?>

<style>
    :root {
        --help-hero-bg: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
        --help-card-bg: rgba(255,255,255,0.96);
    }
    body.cms-admin {
        background: #f1f5f9;
    }
    .help-hero {
        background: var(--help-hero-bg);
        border-radius: 24px;
        padding: 40px;
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 40px 80px -60px rgba(14,165,233,0.7);
        margin-bottom: 36px;
    }
    .help-hero::after {
        content: '';
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at top left, rgba(255,255,255,0.24), transparent 45%);
        pointer-events: none;
    }
    .help-hero h1 {
        margin: 0;
        font-size: 2.4rem;
        font-weight: 700;
    }
    .help-hero p {
        font-size: 1.05rem;
        max-width: 620px;
        margin: 12px 0 0;
        opacity: 0.92;
    }
    .help-search {
        margin-top: 28px;
        position: relative;
    }
    .help-search input {
        width: 100%;
        padding: 18px 20px 18px 48px;
        border-radius: 16px;
        border: none;
        font-size: 1rem;
        box-shadow: 0 18px 45px -28px rgba(15,23,42,0.55);
    }
    .help-search .icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        font-size: 1.2rem;
    }
    .help-wrapper {
        max-width: 1180px;
        margin: 0 auto 80px;
    }
    .help-guide {
        background: var(--help-card-bg);
        border-radius: 20px;
        padding: 32px;
        box-shadow: 0 24px 60px -40px rgba(15,23,42,0.25);
        border: 1px solid rgba(100,116,139,0.12);
    }
    .toc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 18px;
    }
    .toc-card {
        background: rgba(14,165,233,0.08);
        border-radius: 14px;
        padding: 18px;
        border: 1px solid rgba(14,165,233,0.15);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        text-decoration: none;
        color: #0f172a;
    }
    .toc-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 16px 22px -18px rgba(14,165,233,0.45);
    }
    .toc-card h3 {
        margin: 0 0 8px;
        font-size: 1.05rem;
        color: #0f172a;
    }
    .doc-section {
        margin-top: 48px;
        border-top: 1px solid rgba(148,163,184,0.25);
        padding-top: 40px;
    }
    .doc-section h2 {
        font-size: 1.65rem;
        margin-bottom: 14px;
        position: relative;
        color: #0f172a;
    }
    .doc-section h2::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: -6px;
        width: 48px;
        height: 3px;
        background: linear-gradient(90deg, #0ea5e9 0%, #6366f1 100%);
        border-radius: 999px;
    }
    .doc-section p,
    .doc-section li {
        color: #475569;
        line-height: 1.75;
        font-size: 0.97rem;
    }
    .doc-section ul,
    .doc-section ol {
        margin-left: 20px;
        margin-bottom: 16px;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: rgba(14,165,233,0.12);
        color: #0ea5e9;
        padding: 4px 12px;
        border-radius: 999px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    .badge.success { background: rgba(16,185,129,0.12); color: #10b981; }
    .badge.warn { background: rgba(245,158,11,0.15); color: #b45309; }
    .two-column {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 24px;
        margin-top: 18px;
    }
    .info-card {
        background: rgba(248,250,252,0.85);
        border: 1px solid rgba(148,163,184,0.2);
        border-radius: 16px;
        padding: 20px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.6);
    }
    .info-card h3 {
        margin-top: 0;
        color: #0f172a;
    }
    .code-block {
        background: #0f172a;
        color: #e2e8f0;
        padding: 16px 18px;
        border-radius: 12px;
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
        font-size: 0.9rem;
        margin: 14px 0;
        overflow-x: auto;
    }
    .note {
        border-left: 4px solid #0ea5e9;
        background: rgba(14,165,233,0.08);
        padding: 14px 16px;
        border-radius: 12px;
        margin: 16px 0;
        color: #334155;
    }
    .cta-links {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 20px;
    }
    .cta-links a {
        padding: 10px 18px;
        border-radius: 999px;
        text-decoration: none;
        background: #0ea5e9;
        color: #fff;
        font-weight: 600;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .cta-links a:hover {
        transform: translateY(-1px);
        box-shadow: 0 12px 22px -18px rgba(14,165,233,0.7);
    }
    @media (max-width: 768px) {
        .help-guide { padding: 24px; }
        .help-hero { padding: 28px; }
        .help-hero h1 { font-size: 2rem; }
    }
</style>

<div class="help-wrapper">
    <div class="help-hero">
        <h1>ABBIS / CMS User & Operations Guide</h1>
        <p>Everything you need to deploy, run, and extend the ABBIS platform. Browse common tasks by module, follow operational run-books, and review deployment and security best practices.</p>
        <div class="help-search">
            <span class="icon">🔍</span>
            <input type="search" placeholder="Search for topics, e.g. field reports, AI Assistant, backups…" oninput="filterSections(this.value)">
        </div>
        <div class="cta-links">
            <a href="#getting-started">Getting Started</a>
            <a href="#core-platform">Platform Overview</a>
            <a href="#deployment">Deployment & Backups</a>
            <a href="#support">Support & Contacts</a>
        </div>
    </div>

    <div class="help-guide" id="helpGuide">
        <section class="doc-section" id="table-of-contents">
            <h2>Quick Navigation</h2>
            <p>Jump to any area of the system. Each section contains role-based guidance, high-level process maps, and links to configuration screens and scripts.</p>
            <div class="toc-grid">
                <a class="toc-card" href="#getting-started">
                    <h3>1. Getting Started</h3>
                    <p>Platform roles, onboarding checklist, sample data, and interface conventions.</p>
                </a>
                <a class="toc-card" href="#core-platform">
                    <h3>2. Platform Overview</h3>
                    <p>ABBIS architecture, major modules, data flows, and navigation map.</p>
                </a>
                <a class="toc-card" href="#operations-suite">
                    <h3>3. Operations Suite</h3>
                    <p>Field Reports, resource scheduling, maintenance, logistics, customer care.</p>
                </a>
                <a class="toc-card" href="#crm-clients">
                    <h3>4. Clients & CRM</h3>
                    <p>Client lifecycle, quotes, contracts, requests, complaints.</p>
                </a>
                <a class="toc-card" href="#finance-accounting">
                    <h3>5. Finance & Accounting</h3>
                    <p>POS, catalog, receivables, payroll, double-entry ledger, exports.</p>
                </a>
                <a class="toc-card" href="#analytics-ai">
                    <h3>6. Analytics & AI</h3>
                    <p>Dashboards, KPI definitions, AI assistant, forecasting, governance.</p>
                </a>
                <a class="toc-card" href="#admin-security">
                    <h3>7. Administration & Security</h3>
                    <p>Users, roles, feature toggles, auditing, licensing, compliance.</p>
                </a>
                <a class="toc-card" href="#deployment">
                    <h3>8. Deployment & DevOps</h3>
                    <p>Release workflow, backup/restore, environment configs, scripts.</p>
                </a>
                <a class="toc-card" href="#reference">
                    <h3>9. Reference & APIs</h3>
                    <p>Data dictionary, API endpoints, CLI tooling, troubleshooting.</p>
                </a>
                <a class="toc-card" href="#support">
                    <h3>10. Support & Contacts</h3>
                    <p>Escalation policy, ticketing suggestions, training resources.</p>
                </a>
            </div>
        </section>

        <section class="doc-section" id="getting-started">
            <h2>1. Getting Started</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Roles & Personas</h3>
                    <ul>
                        <li><strong>System Administrator:</strong> Manages deployments, backups, user provisioning, feature toggles.</li>
                        <li><strong>Operations Manager:</strong> Oversees field reports, job scheduling, maintenance, resource availability.</li>
                        <li><strong>Finance / Accounts:</strong> Handles POS, invoicing, payroll, double-entry accounting, reporting.</li>
                        <li><strong>Customer Success:</strong> Tracks CRM pipeline, complaints, service recovery and customer communications.</li>
                        <li><strong>Executive / Board:</strong> Consumes dashboards, analytics exports, board packs, scenario forecasts.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>First-Time Checklist</h3>
                    <ol>
                        <li>Complete <code>config/app.php</code> and <code>config/database.php</code>; run initial migrations via <code>modules/database-migrations.php</code>.</li>
                        <li>Enable required modules in <strong>Feature Management</strong>.</li>
                        <li>Configure users & roles (Admin → Users). Use strong passwords and 2FA if available.</li>
                        <li>Populate reference data: rigs, service categories, products, materials, staff positions.</li>
                        <li>Set up <strong>system_config</strong> entries for branding, contact info, SLA defaults.</li>
                        <li>Review dashboards and analytics to confirm data is flowing (Field Reports / CRM / POS).</li>
                    </ol>
                </div>
            </div>
            <div class="note">
                <strong>Data Sources:</strong> ABBIS can import historical data via CSV loaders (Clients, Materials, Catalog) located under <code>modules/**/import</code>, or integrate using the REST API documented in the Reference section.
            </div>
        </section>

        <section class="doc-section" id="core-platform">
            <h2>2. Platform Overview</h2>
            <p>The platform is organised into functional suites accessible through the ABBIS top navigation. Each suite contains dashboards, record screens, and supporting tools.</p>
            <div class="two-column">
                <div class="info-card">
                    <h3>Navigation Map</h3>
                    <ul>
                        <li><strong>Dashboard:</strong> Executive summary, alerts, pipeline snapshots.</li>
                        <li><strong>Field Reports:</strong> Daily drilling/maintenance logs with depth, RPM, job metrics.</li>
                        <li><strong>CRM:</strong> Clients, quotes, requests, contracts, complaints.</li>
                        <li><strong>HR:</strong> Recruitment, worker profiles, payroll, attendance (if enabled).</li>
                        <li><strong>Resources:</strong> Inventory, materials, assets, maintenance schedules.</li>
                        <li><strong>Finance:</strong> POS, catalog, loans, collections, accounting ledger.</li>
                        <li><strong>AI Assistant:</strong> Conversational insights, forecasting, automation scripts.</li>
                        <li><strong>Admin:</strong> Feature toggles, users, settings, theme management, exports.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>Key Data Flows</h3>
                    <ul>
                        <li>Field reports feed maintenance schedules, KPI dashboards, client service history.</li>
                        <li>CRM requests generate jobs → schedule resources → record field reports → invoice.</li>
                        <li>POS sales update inventory and accounting ledger; receivables connect to collections.</li>
                        <li>AI Assistant integrates with CRM, Field Reports, POS to surface contextual answers.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="doc-section" id="operations-suite">
            <h2>3. Operations Suite</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Field Reports</h3>
                    <ul>
                        <li>Log borehole details, start/finish RPM, crew, consumables, maintenance needs.</li>
                        <li>Automatic validation prevents unrealistic readings; use correction scripts for legacy data.</li>
                        <li>Attach photos, geolocation, and client signatures if mobile form is enabled.</li>
                        <li>Reports trigger maintenance work orders and update job profitability metrics.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>Maintenance & Assets</h3>
                    <ul>
                        <li>Maintenance records auto-created from field report findings via <code>MaintenanceExtractor</code>.</li>
                        <li>Schedule preventive tasks, assign technicians, track parts usage.</li>
                        <li>Assets module maintains rig/equipment registry, specifications, cost centres.</li>
                        <li>Duplicate asset protection script ensures clean data when configuring rigs.</li>
                    </ul>
                </div>
            </div>
            <div class="two-column">
                <div class="info-card">
                    <h3>Resources & Inventory</h3>
                    <ul>
                        <li>Centralised materials list, categories, reorder points, vendor references.</li>
                        <li>Inventory ledger per store with adjustments (purchase, transfer, returns, sale).</li>
                        <li>Reorder dashboard highlights low stock, upcoming jobs, expected consumption.</li>
                        <li>Integrates with POS catalog for retail/warehouse operations.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>Scheduling & Jobs</h3>
                    <ul>
                        <li>Requests transform into jobs with crew, rig assignment, expected duration.</li>
                        <li>Job planner (calendar) supports drag-drop scheduling and crew workload balancing.</li>
                        <li>Digital twin dashboards show rig utilisation, downtime, maintenance backlog.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="doc-section" id="crm-clients">
            <h2>4. Clients & CRM</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Client Lifecycle</h3>
                    <ol>
                        <li><strong>Lead Capture:</strong> CRM dashboard, API submissions, POS, or manual entry.</li>
                        <li><strong>Qualification:</strong> Record site data, water needs, budget approvals.</li>
                        <li><strong>Quotation:</strong> Generate proposals; duplicates prevented via CRM rules.</li>
                        <li><strong>Execution:</strong> Request → Job → Field Report → Invoice.</li>
                        <li><strong>Aftercare:</strong> Complaints, maintenance support, AMCs.</li>
                    </ol>
                </div>
                <div class="info-card">
                    <h3>Communication & Complaints</h3>
                    <ul>
                        <li>Complaints module synchronised with CMS portal; track status, priority, resolution SLAs.</li>
                        <li>New CMS complaints form logs into ABBIS complaints tables with ticket code generation.</li>
                        <li>Email/SMS templates configurable under CRM Templates; support multi-channel updates.</li>
                        <li>Customer Care dashboards show open issues, overdue cases, root-cause analysis.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="doc-section" id="finance-accounting">
            <h2>5. Finance & Accounting</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Point of Sale & Catalog</h3>
                    <ul>
                        <li>Product catalog with categories, price tiers, inventory tracking, exposé to shop toggle.</li>
                        <li>POS Admin supports pagination, filters, CSV sync from legacy catalog.</li>
                        <li>Sales integrate with stores, receipts (thermal or PDF), and accounting queue.</li>
                        <li>Collections module manages receivables, follow-up reminders, payment promises.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>Accounting Suite</h3>
                    <ul>
                        <li>Chart of accounts, journal entries, trial balance, balance sheet, P&L.</li>
                        <li>Automated postings from POS, payroll, loans with reconciliation tools.</li>
                        <li>Exports to CSV/Excel, board pack generator with financial ratios and commentary.</li>
                        <li>Integration scripts for external accounting packages (QuickBooks, Sage) via API.</li>
                    </ul>
                </div>
            </div>
            <div class="info-card" style="margin-top: 24px;">
                <h3>Payroll & Loans</h3>
                <ul>
                    <li>Payroll module tracks attendance, overtime, statutory deductions, payslip generation.</li>
                    <li>Loan records with schedules, amortisation, collections interface.</li>
                    <li>Data feeds into accounting ledger and HR worker profiles.</li>
                </ul>
            </div>
        </section>

        <section class="doc-section" id="analytics-ai">
            <h2>6. Analytics & AI</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Dashboards & KPIs</h3>
                    <ul>
                        <li>Executive dashboard: revenue, margin, utilisation, outstanding debt, job pipeline.</li>
                        <li>Operations dashboards: field efficiency, maintenance backlog, resource allocation.</li>
                        <li>Finance dashboards: POS performance, aging, cash flow, variance analysis.</li>
                        <li>Exports available in CSV, JSON, and scheduled emails (configure in admin).</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>AI Assistant & Insights</h3>
                    <ul>
                        <li>Conversational assistant referencing CRM, Field Reports, POS data with guardrails.</li>
                        <li>Domain-specific prompts for executive summaries, cash forecasts, job risk flags.</li>
                        <li>Integration with AI governance plan (<code>docs/phase5-ai-insight-plan.md</code>).</li>
                        <li>Extend via <code>api/ai-insights.php</code> and <code>includes/AI/</code> service clients.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="doc-section" id="admin-security">
            <h2>7. Administration & Security</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>User Management & ACL</h3>
                    <ul>
                        <li>Role-based permissions enforced via <code>includes/auth.php</code>; feature toggles gate modules.</li>
                        <li>Audit logs capture logins, admin actions, export activity (Access Logs module).</li>
                        <li>Support for SSO/Social login under <code>modules/social-auth-config.php</code>.</li>
                        <li>Use complex passwords, rotate admin credentials, enable MFA where possible.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>Configuration</h3>
                    <ul>
                        <li>System settings via Admin → Settings (<code>system_config</code> table).</li>
                        <li>Theme management & converter for CMS front-end and portals.</li>
                        <li>Feature toggle matrix allows staged rollouts (Beta vs GA modules).</li>
                    </ul>
                </div>
            </div>
            <div class="note">
                <strong>Hardening Tips:</strong> Restrict access to <code>/docs</code>, <code>/scripts</code>, <code>/database</code>; deploy with HTTPS; configure rate limiting on login and public endpoints.
            </div>
        </section>

        <section class="doc-section" id="deployment">
            <h2>8. Deployment & DevOps</h2>
            <p>Scripts live under <code>tools/deploy/</code> and automate packaging, backups, and restores. Customise <code>tools/deploy/config.php</code> before first use.</p>
            <div class="two-column">
                <div class="info-card">
                    <h3>Release Workflow</h3>
                    <ol>
                        <li>Run tests locally; update <code>CHANGELOG.md</code>.</li>
                        <li><code>php tools/deploy/build-release.php --version=3.2.x</code> — Creates tarball under <code>releases/</code>.</li>
                        <li><code>php tools/deploy/upload-release.php --target=prod</code> — SCP/rsync to server.</li>
                        <li><code>php tools/deploy/apply-release.php --target=prod</code> — Put app in maintenance mode, expand archive, run migrations, clear caches.</li>
                    </ol>
                </div>
                <div class="info-card">
                    <h3>Backup & Restore</h3>
                    <ul>
                        <li><code>php tools/deploy/backup.php --database --storage</code> — Creates time-stamped backup; retention configurable.</li>
                        <li><code>php tools/deploy/restore.php --backup=YYYYMMDD-HHMM</code> — Restores DB & storage; supports dry-run.</li>
                        <li>Store backups off-site or in S3-compatible storage; verify monthly using <code>--verify</code>.</li>
                    </ul>
                </div>
            </div>
            <div class="info-card" style="margin-top:22px;">
                <h3>Environment Checklist</h3>
                <ul>
                    <li>PHP 8.1+, MariaDB/MySQL 10.5+, Node (optional for asset builds).</li>
                    <li>Configure web server rewrites to direct all CMS requests through <code>cms/public/router.php</code>.</li>
                    <li>Set up cron for scheduled reports, AI retraining, license heartbeat.</li>
                    <li>Monitor logs (<code>storage/logs</code>) and metrics (ELK/Prometheus) for proactive detection.</li>
                </ul>
            </div>
        </section>

        <section class="doc-section" id="reference">
            <h2>9. Reference & APIs</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Documentation Index</h3>
                    <ul>
                        <li><code>docs/rig-tracking-integration.md</code> — GPS providers, configuration, cron sync.</li>
                        <li><code>docs/ai-provider-setup.md</code> — AI assistant provider keys, rate limits.</li>
                        <li><code>docs/phase5-ai-insight-plan.md</code> — AI roadmap, governance model.</li>
                        <li><code>ACCOUNTING_INTEGRATIONS_COMPLETE.md</code> — Accounting data model & integrations.</li>
                    </ul>
                </div>
                <div class="info-card">
                    <h3>API & CLI</h3>
                    <ul>
                        <li>REST API root: <code>/cms/api/rest.php</code> — token-based auth (Bearer).</li>
                        <li>Key endpoints: <code>clients</code>, <code>jobs</code>, <code>field-reports</code>, <code>pos</code>, <code>ai-insights</code>.</li>
                        <li>CLI utilities under <code>tools/cli/</code> for migration, data repair, reporting.</li>
                        <li>Use API keys generated via Admin → API Keys.</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="doc-section" id="support">
            <h2>10. Support & Contacts</h2>
            <div class="two-column">
                <div class="info-card">
                    <h3>Support Workflow</h3>
                    <ol>
                        <li>Capture issue details: URL, user, timestamp, screenshot, logs.</li>
                        <li>Check knowledge base (this guide + <code>docs/</code> folder) before escalating.</li>
                        <li>Log ticket in helpdesk (Jira/Zendesk/RT) with priority (P1–P4).</li>
                        <li>P1/P2 escalate to on-call engineer; use incident run-book (<code>docs/incident-response.md</code> if present).</li>
                        <li>Document resolution, update FAQ/training material when applicable.</li>
                    </ol>
                </div>
                <div class="info-card">
                    <h3>Training & Onboarding</h3>
                    <ul>
                        <li>Host quarterly refresher training; record sessions for asynchronous learning.</li>
                        <li>Create role-specific manuals referencing this guide.</li>
                        <li>Encourage staff to submit improvement suggestions via Feature Management → Requests.</li>
                    </ul>
                </div>
            </div>
            <div class="note">
                <strong>Contact Matrix:</strong>
                <ul>
                    <li><strong>Infrastructure:</strong> infra@yourcompany.com</li>
                    <li><strong>Software Support:</strong> support@yourcompany.com</li>
                    <li><strong>Product Management:</strong> product@yourcompany.com</li>
                    <li><strong>Emergency Escalation:</strong> +233-XXX-XXX (24/7 on-call)</li>
                </ul>
            </div>
        </section>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
    function filterSections(term) {
        const value = term.trim().toLowerCase();
        document.querySelectorAll('.doc-section').forEach(section => {
            if (!value) {
                section.style.display = '';
                return;
            }
            const text = section.innerText.toLowerCase();
            section.style.display = text.includes(value) ? '' : 'none';
        });
    }
</script>
</body>
</html>
