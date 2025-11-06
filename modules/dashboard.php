<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$page_title = 'Dashboard - Executive Overview';

// Get analytics filter parameters (for analytics tab)
$pdo = getDBConnection();
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$groupBy = $_GET['group_by'] ?? 'month';
$selectedRig = $_GET['rig_id'] ?? '';
$selectedClient = $_GET['client_id'] ?? '';
$selectedJobType = $_GET['job_type'] ?? '';

// Get filter options for analytics
$rigs = $pdo->query("SELECT id, rig_name FROM rigs WHERE status = 'active' ORDER BY rig_name")->fetchAll();
$clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name LIMIT 100")->fetchAll();
$jobTypes = ['direct' => 'Direct', 'subcontract' => 'Subcontract'];

try {
    $stats = $abbis->getDashboardStats();
    $recentActivity = $abbis->getRecentActivity(10);
} catch (Exception $e) {
    // Initialize empty stats structure if function fails
    $stats = [
        'today' => ['total_reports_today' => 0, 'total_income_today' => 0, 'net_profit_today' => 0, 'money_banked_today' => 0],
        'overall' => ['total_reports' => 0, 'total_income' => 0, 'total_expenses' => 0, 'total_profit' => 0, 'outstanding_rig_fees' => 0],
        'financial_health' => ['profit_margin' => 0, 'gross_margin' => 0, 'expense_ratio' => 0, 'avg_profit_per_job' => 0, 'avg_revenue_per_job' => 0, 'avg_cost_per_job' => 0],
        'this_month' => ['total_reports_this_month' => 0, 'total_income_this_month' => 0, 'total_profit_this_month' => 0],
        'growth' => ['revenue_growth_mom' => 0, 'profit_growth_mom' => 0, 'jobs_growth_mom' => 0, 'this_month_revenue' => 0, 'last_month_revenue' => 0, 'this_month_profit' => 0, 'last_month_profit' => 0],
        'this_year' => ['total_reports_this_year' => 0, 'total_income_this_year' => 0, 'total_profit_this_year' => 0],
        'loans' => ['total_loans' => 0, 'total_outstanding' => 0],
        'materials' => ['total_materials_value' => 0],
        'balance_sheet' => ['total_assets' => 0, 'total_liabilities' => 0, 'net_worth' => 0, 'debt_to_asset_ratio' => 0, 'cash_reserves' => 0, 'materials_value' => 0],
        'operational' => ['rig_utilization_rate' => 0, 'avg_job_duration_minutes' => 0, 'avg_depth_per_job' => 0, 'active_rigs' => 0, 'jobs_per_day' => 0],
        'cash_flow' => ['cash_inflow' => 0, 'cash_outflow' => 0, 'net_cash_flow' => 0, 'deposits' => 0],
        'top_clients' => [],
        'top_rigs' => [],
        'job_types' => []
    ];
    $recentActivity = [];
}

// Ensure all array keys exist with defaults
$stats = array_merge([
    'today' => [],
    'overall' => [],
    'financial_health' => [],
    'this_month' => [],
    'growth' => [],
    'this_year' => [],
    'loans' => [],
    'materials' => [],
    'balance_sheet' => [],
    'operational' => [],
    'cash_flow' => [],
    'top_clients' => [],
    'top_rigs' => [],
    'job_types' => []
], $stats ?: []);

// Helper function to safely get array values
function getStat($array, $key, $default = 0) {
    return isset($array[$key]) ? $array[$key] : $default;
}

// Helper function to format percentage with trend indicator
function formatTrend($value, $showSign = true) {
    $sign = $value >= 0 ? '+' : '';
    $color = $value >= 0 ? 'positive' : 'negative';
    $icon = $value >= 0 ? '↑' : '↓';
    return '<span class="trend ' . $color . '">' . $icon . ' ' . ($showSign ? $sign : '') . number_format($value, 2) . '%</span>';
}

require_once '../includes/header.php';
?>

<style>
    /* Sleek, Thin KPI Cards */
    .kpi-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-left: 3px solid var(--primary);
        border-radius: 8px;
        padding: 16px 18px;
        position: relative;
        transition: all 0.2s ease;
    }
    
    .kpi-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-left-color: var(--primary-dark);
    }
    
    .kpi-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }
    
    .kpi-card-title {
        font-size: 11px;
        font-weight: 600;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        margin-bottom: 6px;
    }
    
    .kpi-card-value {
        font-size: 24px;
        font-weight: 700;
        color: var(--text);
        margin: 4px 0;
        line-height: 1.2;
        letter-spacing: -0.5px;
    }
    
    .kpi-card-subtitle {
        font-size: 11px;
        color: var(--secondary);
        margin-top: 6px;
        line-height: 1.4;
    }
    
    .trend {
        font-size: 12px;
        font-weight: 700;
        padding: 4px 8px;
        border-radius: 6px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    
    .trend.positive {
        background: rgba(16,185,129,0.1);
        color: var(--success);
    }
    
    .trend.negative {
        background: rgba(239,68,68,0.1);
        color: var(--danger);
    }
    
    .metric-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 16px;
    }
    
    @media (max-width: 1400px) {
        .metric-grid {
            grid-template-columns: repeat(3, 1fr);
        }
    }
    
    @media (max-width: 1000px) {
        .metric-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 600px) {
        .metric-grid {
            grid-template-columns: 1fr;
        }
    }
    
    /* Cash Flow Cards Responsive */
    @media (max-width: 768px) {
        .cash-flow-cards {
            grid-template-columns: 1fr !important;
        }
    }
    
    .stat-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 18px 20px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: all 0.2s ease;
    }
    
    .stat-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transform: translateY(-1px);
    }
    
    .stat-icon {
        font-size: 28px;
        opacity: 0.7;
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(14,165,233,0.08);
        border-radius: 8px;
    }
    
    .stat-info h3 {
        font-size: 22px;
        font-weight: 700;
        color: var(--text);
        margin: 0 0 4px 0;
        line-height: 1.2;
    }
    
    .stat-info p {
        font-size: 12px;
        color: var(--secondary);
        margin: 0;
    }
    
    .section-header {
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 1px solid var(--border);
    }
    
    .section-title {
        font-size: 16px;
        font-weight: 600;
        color: var(--text);
        margin: 0;
        letter-spacing: -0.3px;
    }
    
    .performance-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .performance-table th {
        background: var(--bg);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        color: var(--secondary);
        border-bottom: 2px solid var(--border);
    }
    
    .performance-table td {
        padding: 12px;
        border-bottom: 1px solid var(--border);
    }
    
    .performance-table tr:hover {
        background: var(--bg);
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .badge-success {
        background: rgba(16,185,129,0.1);
        color: var(--success);
    }
    
    .badge-warning {
        background: rgba(245,158,11,0.1);
        color: var(--warning);
    }
    
    .badge-info {
        background: rgba(14,165,233,0.1);
        color: var(--primary);
    }
    
    .progress-bar {
        height: 8px;
        background: var(--bg);
        border-radius: 4px;
        overflow: hidden;
        margin-top: 8px;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        transition: width 0.3s ease;
    }
    
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
    }
    
    @media (max-width: 1200px) {
        .kpi-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 600px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
    
    .kpi-item {
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
    }
    
    .kpi-item:last-child {
        border-bottom: none;
    }
    
    .kpi-label {
        font-size: 11px;
        font-weight: 600;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 6px;
    }
    
    .kpi-value {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        display: block;
        letter-spacing: -0.3px;
    }
    
    .kpi-value.debt {
        color: var(--danger);
    }
    
    .dashboard-card h2 {
        font-size: 15px;
        font-weight: 600;
        color: var(--text);
        margin: 0 0 16px 0;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--border);
        letter-spacing: -0.2px;
    }
    
    /* Key Performance Indicators Section */
    .kpi-hero-section {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        color: var(--text);
        box-shadow: 0 2px 6px color-mix(in srgb, var(--text) 8%, transparent);
    }
    
    .kpi-hero-section h2 {
        color: var(--text);
        margin: 0 0 20px 0;
        font-size: 20px;
        font-weight: 700;
        border-bottom: 1px solid var(--border);
        padding-bottom: 12px;
        letter-spacing: -0.3px;
    }
    
    .kpi-hero-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .kpi-hero-card {
        background: var(--card);
        border-radius: 12px;
        padding: 18px;
        border: 1px solid var(--border);
        transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
        position: relative; /* for corner icon */
    }
    
    /* subtle edge styling */
    .kpi-hero-card.edge {
        border-left: 3px solid var(--primary);
        box-shadow: 0 2px 8px color-mix(in srgb, var(--text) 10%, transparent);
    }
    
    .kpi-hero-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.10);
        background: var(--card);
    }
    
    .kpi-hero-label {
        font-size: 13px;
        color: var(--secondary);
        margin-bottom: 8px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .kpi-hero-value {
        font-size: 32px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 4px;
        color: var(--text);
    }

    /* Top client tweaks */
    .kpi-hero-card.top-client .kpi-hero-value {
        font-size: 20px; /* reduced name size */
        line-height: 1.2;
        word-break: break-word;
    }
    .kpi-hero-card.top-client .top-icon {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 16px;
        color: var(--warning);
        opacity: 0.9;
    }
    
    .kpi-hero-subtitle {
        font-size: 12px;
        color: var(--secondary);
        margin-top: 4px;
    }
    
    .clients-breakdown {
        background: rgba(255,255,255,0.95);
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .clients-breakdown h3 {
        color: var(--text);
        margin: 0 0 16px 0;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .client-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 12px;
        padding: 12px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        align-items: center;
        transition: background 0.2s;
    }
    
    .client-row:last-child {
        border-bottom: none;
    }
    
    .client-row:hover {
        background: rgba(14, 165, 233, 0.05);
    }
    
    .client-name {
        font-weight: 600;
        color: var(--text);
        font-size: 14px;
    }
    
    .client-stat {
        text-align: center;
        font-size: 14px;
        color: var(--secondary);
    }
    
    .client-stat-value {
        font-weight: 700;
        color: var(--primary);
        font-size: 16px;
        display: block;
    }
    
    .client-stat-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 2px;
    }
    
    @media (max-width: 768px) {
        .kpi-hero-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .client-row {
            grid-template-columns: 1fr;
            gap: 8px;
            text-align: center;
        }
        
        .client-stat {
            text-align: center;
        }
    }
    
    /* Export Menu Styles */
    .export-menu {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 4px;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 200px;
        z-index: 1000;
        padding: 8px 0;
    }
    
    .export-menu a {
        display: block;
        padding: 10px 16px;
        color: var(--text);
        text-decoration: none;
        font-size: 13px;
        transition: background 0.2s;
    }
    
    .export-menu a:hover {
        background: var(--bg);
    }
    
    /* Filter Grid Responsive */
    @media (max-width: 768px) {
        .filter-grid {
            grid-template-columns: 1fr !important;
        }
    }
    
    /* Alert/Notification Styles */
    .dashboard-alert {
        padding: 12px 16px;
        border-radius: 8px;
        margin-bottom: 16px;
        display: flex;
        align-items: center;
        gap: 12px;
        border-left: 4px solid;
        animation: slideIn 0.3s ease;
    }
    
    .dashboard-alert.warning {
        background: rgba(245,158,11,0.1);
        border-left-color: #f59e0b;
        color: var(--text);
    }
    
    .dashboard-alert.danger {
        background: rgba(239,68,68,0.1);
        border-left-color: #ef4444;
        color: var(--text);
    }
    
    .dashboard-alert.info {
        background: rgba(14,165,233,0.1);
        border-left-color: #0ea5e9;
        color: var(--text);
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100px);
        }
    }
    
    .trend.positive {
        color: #10b981;
    }
    
    .trend.negative {
        color: #ef4444;
    }
    
    /* Clickable KPI cards */
    .kpi-card.clickable {
        cursor: pointer;
    }
    
    .kpi-card.clickable:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }
</style>

<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Business Intelligence & Analytics</p>
    </div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <div style="font-size: 13px; color: var(--secondary);">
            Updated <?php echo date('M d, Y H:i'); ?>
        </div>
        <div class="dashboard-export-buttons" style="display: flex; gap: 8px;">
            <button onclick="exportDashboard('csv')" class="btn btn-sm btn-outline" title="Export as CSV">
                📥 CSV
            </button>
            <button onclick="exportDashboard('json')" class="btn btn-sm btn-outline" title="Export as JSON">
                📥 JSON
            </button>
            <div class="export-dropdown" style="position: relative;">
                <button onclick="toggleExportMenu()" class="btn btn-sm btn-outline" title="More Export Options">
                    📥 More ▼
                </button>
                <div id="exportMenu" class="export-menu" style="display: none;">
                    <a href="#" onclick="exportDashboard('csv', 'financial'); return false;">Financial Data (CSV)</a>
                    <a href="#" onclick="exportDashboard('json', 'financial'); return false;">Financial Data (JSON)</a>
                    <a href="#" onclick="exportDashboard('csv', 'operational'); return false;">Operational Data (CSV)</a>
                    <a href="#" onclick="exportDashboard('json', 'operational'); return false;">Operational Data (JSON)</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Alerts -->
<div id="dashboardAlerts"></div>

<!-- Interactive Filters -->
<div class="dashboard-card" style="margin-bottom: 24px; border-left: 4px solid var(--primary);">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <h3 style="margin: 0; font-size: 16px; font-weight: 600; color: var(--text);">🔍 Interactive Filters</h3>
        <button onclick="resetFilters()" class="btn btn-sm btn-outline" style="font-size: 12px;">Reset Filters</button>
    </div>
    <div class="filter-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <div class="form-group" style="margin: 0;">
            <label style="display: block; font-size: 12px; color: var(--secondary); margin-bottom: 6px; font-weight: 500;">Date From</label>
            <input type="date" id="filterDateFrom" class="form-control" value="<?php echo date('Y-m-01'); ?>" 
                   style="font-size: 13px; padding: 8px;" onchange="applyFilters()">
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="display: block; font-size: 12px; color: var(--secondary); margin-bottom: 6px; font-weight: 500;">Date To</label>
            <input type="date" id="filterDateTo" class="form-control" value="<?php echo date('Y-m-t'); ?>" 
                   style="font-size: 13px; padding: 8px;" onchange="applyFilters()">
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="display: block; font-size: 12px; color: var(--secondary); margin-bottom: 6px; font-weight: 500;">Rig</label>
            <select id="filterRig" class="form-control" style="font-size: 13px; padding: 8px;" onchange="applyFilters()">
                <option value="">All Rigs</option>
                <?php foreach ($rigs as $rig): ?>
                    <option value="<?php echo $rig['id']; ?>"><?php echo e($rig['rig_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="display: block; font-size: 12px; color: var(--secondary); margin-bottom: 6px; font-weight: 500;">Client</label>
            <select id="filterClient" class="form-control" style="font-size: 13px; padding: 8px;" onchange="applyFilters()">
                <option value="">All Clients</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>"><?php echo e($client['client_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="display: block; font-size: 12px; color: var(--secondary); margin-bottom: 6px; font-weight: 500;">Job Type</label>
            <select id="filterJobType" class="form-control" style="font-size: 13px; padding: 8px;" onchange="applyFilters()">
                <option value="">All Types</option>
                <?php foreach ($jobTypes as $key => $label): ?>
                    <option value="<?php echo $key; ?>"><?php echo e($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div id="filterStatus" style="margin-top: 12px; padding: 8px; background: var(--bg); border-radius: 6px; font-size: 12px; color: var(--secondary); display: none;">
        <span id="filterStatusText"></span>
    </div>
</div>

<?php
// Key Performance Indicators - Boreholes & Clients
$kpi_stats = [
    'total_jobs' => 0,
    'jobs_this_month' => 0,
    'jobs_this_year' => 0,
    'total_unique_clients' => 0,
    'clients_with_jobs' => []
];

try {
    // Total jobs/boreholes (all time)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM field_reports");
    $kpi_stats['total_jobs'] = (int)$stmt->fetchColumn();
    
    // Jobs this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM field_reports 
        WHERE YEAR(created_at) = YEAR(CURDATE()) 
        AND MONTH(created_at) = MONTH(CURDATE())
    ");
    $kpi_stats['jobs_this_month'] = (int)$stmt->fetchColumn();
    
    // Jobs this year
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM field_reports 
        WHERE YEAR(created_at) = YEAR(CURDATE())
    ");
    $kpi_stats['jobs_this_year'] = (int)$stmt->fetchColumn();
    
    // Total unique clients
    $stmt = $pdo->query("SELECT COUNT(DISTINCT id) as total FROM clients");
    $kpi_stats['total_unique_clients'] = (int)$stmt->fetchColumn();
    
    // Jobs per client breakdown
    $stmt = $pdo->query("
        SELECT 
            c.id,
            c.client_name,
            COUNT(fr.id) as job_count,
            SUM(CASE WHEN YEAR(fr.created_at) = YEAR(CURDATE()) THEN 1 ELSE 0 END) as jobs_this_year,
            SUM(CASE WHEN YEAR(fr.created_at) = YEAR(CURDATE()) AND MONTH(fr.created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as jobs_this_month
        FROM clients c
        LEFT JOIN field_reports fr ON c.id = fr.client_id
        GROUP BY c.id, c.client_name
        ORDER BY job_count DESC, c.client_name ASC
    ");
    $kpi_stats['clients_with_jobs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Determine top client by total jobs
    $kpi_stats['top_client_name'] = '';
    $kpi_stats['top_client_jobs'] = 0;
    if (!empty($kpi_stats['clients_with_jobs'])) {
        $top = $kpi_stats['clients_with_jobs'][0];
        $kpi_stats['top_client_name'] = $top['client_name'] ?? '';
        $kpi_stats['top_client_jobs'] = (int)($top['job_count'] ?? 0);
    }
} catch (PDOException $e) {
    // If query fails, keep defaults
}

// Operations Snapshot metrics (lightweight, resilient)
$ops = [
    'materials_items' => 0,
    'materials_value' => 0,
    'inv_tx_today' => 0,
    'assets_count' => 0,
    'assets_value' => 0,
    'maint_pending' => 0,
    'crm_followups' => 0,
    'clients_count' => 0,
    'workers_count' => 0,
    'features_enabled' => 0,
];

// Materials
try {
    $row = $pdo->query("SELECT COUNT(*) as c, COALESCE(SUM(total_value),0) v FROM materials_inventory")->fetch();
    $ops['materials_items'] = (int)($row['c'] ?? 0);
    $ops['materials_value'] = (float)($row['v'] ?? 0);
} catch (Throwable $e) {}

// Inventory transactions today
try {
    $ops['inv_tx_today'] = (int)$pdo->query("SELECT COUNT(*) FROM inventory_transactions WHERE DATE(created_at)=CURDATE()")->fetchColumn();
} catch (Throwable $e) {}

// Assets
try {
    $row = $pdo->query("SELECT COUNT(*) c, COALESCE(SUM(current_value),0) v FROM assets WHERE status='active'")->fetch();
    $ops['assets_count'] = (int)($row['c'] ?? 0);
    $ops['assets_value'] = (float)($row['v'] ?? 0);
} catch (Throwable $e) {}

// Maintenance pending
try {
    $ops['maint_pending'] = (int)$pdo->query("SELECT COUNT(*) FROM maintenance_records WHERE status IN ('logged','scheduled','progress','in_progress')")->fetchColumn();
} catch (Throwable $e) {}

// CRM follow-ups upcoming (next 7 days)
try {
    $ops['crm_followups'] = (int)$pdo->query("SELECT COUNT(*) FROM client_followups WHERE status='scheduled' AND scheduled_date >= NOW() AND scheduled_date <= DATE_ADD(NOW(), INTERVAL 7 DAY)")->fetchColumn();
} catch (Throwable $e) {}

// Clients
try { $ops['clients_count'] = (int)$pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(); } catch (Throwable $e) {}
// Workers
try { $ops['workers_count'] = (int)$pdo->query("SELECT COUNT(*) FROM workers WHERE status='active'")->fetchColumn(); } catch (Throwable $e) {}
// Features enabled
try { $ops['features_enabled'] = (int)$pdo->query("SELECT COUNT(*) FROM feature_toggles WHERE is_enabled=1")->fetchColumn(); } catch (Throwable $e) {}

// RPM-based rig performance snapshot - Show ALL active rigs
$rigPerformance = [];
try {
    $sql = "
        SELECT 
            r.id AS rig_id,
            r.rig_name,
            r.rig_code,
            COALESCE(r.current_rpm, 0) AS current_rpm,
            COALESCE(r.maintenance_due_at_rpm, 0) AS due_rpm,
            COUNT(fr.id) AS job_count,
            COALESCE(SUM(fr.total_income), 0) AS total_revenue,
            COALESCE(SUM(fr.net_profit), 0) AS total_profit,
            COALESCE(SUM(fr.total_rpm), 0) AS total_rpm,
            COALESCE(SUM(fr.total_expenses), 0) AS total_expenses,
            COALESCE(AVG(fr.net_profit), 0) AS avg_profit_per_job,
            COALESCE(MAX(fr.report_date), NULL) AS last_job_date
        FROM rigs r
        LEFT JOIN field_reports fr ON fr.rig_id = r.id
        WHERE r.status = 'active'
        GROUP BY r.id, r.rig_name, r.rig_code, r.current_rpm, r.maintenance_due_at_rpm
        ORDER BY total_rpm DESC, total_revenue DESC
    ";
    $rigPerformance = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rigPerformance as &$rp) {
        $rpm = (float)($rp['total_rpm'] ?? 0);
        $revenue = (float)($rp['total_revenue'] ?? 0);
        $profit = (float)($rp['total_profit'] ?? 0);
        $expenses = (float)($rp['total_expenses'] ?? 0);
        $jobs = (int)($rp['job_count'] ?? 0);
        $rp['revenue_per_rpm'] = $rpm > 0 ? ($revenue / $rpm) : 0;
        $rp['profit_per_rpm'] = $rpm > 0 ? ($profit / $rpm) : 0;
        $rp['profit_margin'] = $revenue > 0 ? (($profit / $revenue) * 100) : 0;
        $rp['avg_profit_per_job'] = $jobs > 0 ? ($profit / $jobs) : 0;
        $due = (float)($rp['due_rpm'] ?? 0);
        $current = (float)($rp['current_rpm'] ?? 0);
        $rp['rpm_progress_pct'] = $due > 0 ? min(100, max(0, ($current / $due) * 100)) : 0;
    }
    unset($rp);
} catch (Throwable $e) {
    $rigPerformance = [];
}

// Get debt recovery stats for dashboard alert
$debtRecoveryAlert = [];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as count,
        COALESCE(SUM(remaining_debt), 0) as total_amount,
        SUM(CASE WHEN due_date < CURDATE() AND status IN ('outstanding', 'partially_paid', 'in_collection') THEN 1 ELSE 0 END) as overdue_count,
        SUM(CASE WHEN next_followup_date = CURDATE() AND status IN ('outstanding', 'partially_paid', 'in_collection') THEN 1 ELSE 0 END) as due_today_count
        FROM debt_recoveries 
        WHERE status IN ('outstanding', 'partially_paid', 'in_collection')");
    $debtRecoveryAlert = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $debtRecoveryAlert = ['count' => 0, 'total_amount' => 0, 'overdue_count' => 0, 'due_today_count' => 0];
}

// Get unpaid rig fees stats
$unpaidRigFees = [];
try {
    $stmt = $pdo->query("SELECT 
        COUNT(*) as count,
        COALESCE(SUM(outstanding_rig_fee), 0) as total_amount
        FROM field_reports
        WHERE outstanding_rig_fee > 0");
    $unpaidRigFees = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0, 'total_amount' => 0];
} catch (PDOException $e) {
    $unpaidRigFees = ['count' => 0, 'total_amount' => 0];
}

?>

<!-- Real-time Alerts Container -->
<div id="realtime-alerts"></div>

<!-- Alerts Cards - 3 Columns -->
<?php if (intval($unpaidRigFees['count'] ?? 0) > 0 || intval($debtRecoveryAlert['count'] ?? 0) > 0): ?>
<div class="alerts-grid">
    <!-- Card 1: Unpaid Rig Fees -->
    <?php if (intval($unpaidRigFees['count'] ?? 0) > 0): ?>
    <div class="dashboard-card" style="border-left: 4px solid #f59e0b; padding: 20px;">
        <div style="display: flex; align-items: start; gap: 12px; margin-bottom: 16px;">
            <div style="font-size: 32px; color: #f59e0b;">💰</div>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: var(--text);">Unpaid Rig Fees</h3>
                <p style="margin: 0; font-size: 14px; color: var(--secondary);">
                    <?php echo number_format($unpaidRigFees['count']); ?> report<?php echo $unpaidRigFees['count'] != 1 ? 's' : ''; ?> with unpaid rig fees totaling GHS <?php echo number_format($unpaidRigFees['total_amount'], 2); ?>
                </p>
            </div>
        </div>
        <a href="financial.php" class="btn btn-primary" style="width: 100%; font-size: 14px; padding: 10px;">
            View →
        </a>
    </div>
    <?php endif; ?>

    <!-- Card 2: Outstanding Debts Requiring Attention -->
    <?php if (intval($debtRecoveryAlert['count'] ?? 0) > 0): ?>
    <div class="dashboard-card" style="background: linear-gradient(135deg, rgba(239,68,68,0.1) 0%, rgba(239,68,68,0.05) 100%); border: 2px solid rgba(239,68,68,0.3); border-left: 4px solid #ef4444; padding: 20px;">
        <div style="display: flex; align-items: start; gap: 12px; margin-bottom: 16px;">
            <div style="font-size: 32px; color: #ef4444;">⚠️</div>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 8px 0; font-size: 16px; font-weight: 600; color: #ef4444;">Outstanding Debts Requiring Attention</h3>
                <div style="margin-top: 8px;">
                    <div style="margin-bottom: 6px;">
                        <span style="font-size: 13px; color: var(--secondary);">Outstanding Debts:</span>
                        <strong style="font-size: 15px; color: var(--text); margin-left: 8px;"><?php echo number_format($debtRecoveryAlert['count'] ?? 0); ?></strong>
                    </div>
                    <div>
                        <span style="font-size: 13px; color: var(--secondary);">Total Amount:</span>
                        <strong style="font-size: 15px; color: #ef4444; margin-left: 8px;">GHS <?php echo number_format($debtRecoveryAlert['total_amount'] ?? 0, 2); ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <a href="debt-recovery.php" class="btn btn-primary" style="width: 100%; font-size: 14px; padding: 10px;">
            View & Follow Up →
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Responsive: Stack on mobile -->
<style>
.alerts-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

@media (max-width: 1024px) {
    .alerts-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Key Performance Indicators - Boreholes & Clients -->
<div class="kpi-hero-section">
    <h2>📊 Key Performance Indicators</h2>
    <div class="kpi-hero-grid">
        <div class="kpi-hero-card edge">
            <div class="kpi-hero-label">Total Boreholes</div>
            <div class="kpi-hero-value"><?php echo number_format($kpi_stats['total_jobs']); ?></div>
            <div class="kpi-hero-subtitle">All Time</div>
        </div>
        <div class="kpi-hero-card edge">
            <div class="kpi-hero-label">This Month</div>
            <div class="kpi-hero-value"><?php echo number_format($kpi_stats['jobs_this_month']); ?></div>
            <div class="kpi-hero-subtitle">Jobs Completed</div>
        </div>
        <div class="kpi-hero-card edge">
            <div class="kpi-hero-label">This Year</div>
            <div class="kpi-hero-value"><?php echo number_format($kpi_stats['jobs_this_year']); ?></div>
            <div class="kpi-hero-subtitle">Year to Date</div>
        </div>
        <div class="kpi-hero-card edge">
            <div class="kpi-hero-label">Total Clients</div>
            <div class="kpi-hero-value"><?php echo number_format($kpi_stats['total_unique_clients']); ?></div>
            <div class="kpi-hero-subtitle">Unique Clients</div>
        </div>
        <?php if (!empty($kpi_stats['top_client_name'])): ?>
        <div class="kpi-hero-card top-client edge">
            <div class="top-icon" title="Top Client">🏆</div>
            <div class="kpi-hero-label">Top Client</div>
            <div class="kpi-hero-value" title="Jobs with this client">
                <?php echo htmlspecialchars($kpi_stats['top_client_name']); ?>
            </div>
            <div class="kpi-hero-subtitle"><?php echo number_format($kpi_stats['top_client_jobs']); ?> Jobs</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Operations Snapshot -->
<div class="section-header" style="margin-top: 8px;">
    <h2 class="section-title">Operations Snapshot</h2>
    <div style="font-size: 12px; color: var(--secondary);">Live glance across Resources, CRM, and System</div>
</div>
<div class="metric-grid">
    <div class="kpi-card">
        <div class="kpi-card-title">Materials Items</div>
        <div class="kpi-card-value"><?php echo number_format($ops['materials_items']); ?></div>
        <div class="kpi-card-subtitle">Value: GHS <?php echo number_format($ops['materials_value'], 2); ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Inventory Transactions (Today)</div>
        <div class="kpi-card-value"><?php echo number_format($ops['inv_tx_today']); ?></div>
        <div class="kpi-card-subtitle">Advanced Inventory</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Active Assets</div>
        <div class="kpi-card-value"><?php echo number_format($ops['assets_count']); ?></div>
        <div class="kpi-card-subtitle">Value: GHS <?php echo number_format($ops['assets_value'], 2); ?></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Maintenance Pending</div>
        <div class="kpi-card-value"><?php echo number_format($ops['maint_pending']); ?></div>
        <div class="kpi-card-subtitle">Logged, Scheduled, In Progress</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Upcoming Follow-ups (7d)</div>
        <div class="kpi-card-value"><?php echo number_format($ops['crm_followups']); ?></div>
        <div class="kpi-card-subtitle">CRM Pipeline</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Clients</div>
        <div class="kpi-card-value"><?php echo number_format($ops['clients_count']); ?></div>
        <div class="kpi-card-subtitle">Total records</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Active Workers</div>
        <div class="kpi-card-value"><?php echo number_format($ops['workers_count']); ?></div>
        <div class="kpi-card-subtitle">Workforce</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-card-title">Features Enabled</div>
        <div class="kpi-card-value"><?php echo number_format($ops['features_enabled']); ?></div>
        <div class="kpi-card-subtitle">Customization State</div>
    </div>
</div>

<!-- Today's Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-info">
            <h3><?php echo number_format(getStat($stats['today'], 'total_reports_today', 0)); ?></h3>
            <p>Reports Today</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-info">
            <h3>GHS <?php echo number_format(getStat($stats['today'], 'total_income_today', 0), 2); ?></h3>
            <p>Today's Revenue</p>
            <?php 
            // Compare to yesterday (if available)
            $yesterdayIncome = 0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_income), 0) as total FROM field_reports WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
                $stmt->execute();
                $yesterdayIncome = (float)$stmt->fetchColumn();
            } catch (Exception $e) {}
            $todayIncome = getStat($stats['today'], 'total_income_today', 0);
            $revenueTrend = $yesterdayIncome > 0 ? (($todayIncome - $yesterdayIncome) / $yesterdayIncome) * 100 : 0;
            if ($yesterdayIncome > 0): ?>
                <div style="font-size: 11px; margin-top: 4px;">
                    <?php echo formatTrend($revenueTrend, false); ?>
                    <span style="color: var(--secondary);">vs yesterday</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">📈</div>
        <div class="stat-info">
            <h3>GHS <?php echo number_format(getStat($stats['today'], 'net_profit_today', 0), 2); ?></h3>
            <p>Today's Profit</p>
            <?php 
            // Compare to yesterday
            $yesterdayProfit = 0;
            try {
                $stmt = $pdo->prepare("SELECT COALESCE(SUM(net_profit), 0) as total FROM field_reports WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
                $stmt->execute();
                $yesterdayProfit = (float)$stmt->fetchColumn();
            } catch (Exception $e) {}
            $todayProfit = getStat($stats['today'], 'net_profit_today', 0);
            $profitTrend = $yesterdayProfit != 0 ? (($todayProfit - $yesterdayProfit) / abs($yesterdayProfit)) * 100 : 0;
            if ($yesterdayProfit != 0): ?>
                <div style="font-size: 11px; margin-top: 4px;">
                    <?php echo formatTrend($profitTrend, false); ?>
                    <span style="color: var(--secondary);">vs yesterday</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🏦</div>
        <div class="stat-info">
            <h3>GHS <?php echo number_format(getStat($stats['today'], 'money_banked_today', 0), 2); ?></h3>
            <p>Money Banked Today</p>
        </div>
    </div>
</div>

<!-- Financial Health KPIs -->
<div class="section-header">
    <h2 class="section-title">Financial Health Metrics</h2>
</div>

<div class="metric-grid">
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Profit Margin</span>
        </div>
        <div class="kpi-card-value">
            <?php echo number_format(getStat($stats['financial_health'], 'profit_margin', 0), 2); ?>%
        </div>
        <div class="kpi-card-subtitle">
            Net Profit / Total Revenue
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Gross Margin</span>
        </div>
        <div class="kpi-card-value">
            <?php echo number_format(getStat($stats['financial_health'], 'gross_margin', 0), 2); ?>%
        </div>
        <div class="kpi-card-subtitle">
            Gross Profit / Total Revenue
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Avg Revenue per Job</span>
            <?php 
            // Calculate trend vs last month average
            $thisMonthReports = getStat($stats['this_month'], 'total_reports_this_month', 0);
            $lastMonthReports = getStat($stats['overall'], 'total_reports', 0) - $thisMonthReports;
            $thisMonthAvg = $thisMonthReports > 0 
                ? (getStat($stats['this_month'], 'total_income_this_month', 0) / $thisMonthReports)
                : 0;
            $lastMonthAvg = $lastMonthReports > 0
                ? (getStat($stats['growth'], 'last_month_revenue', 0) / $lastMonthReports)
                : 0;
            $avgTrend = $lastMonthAvg > 0 ? (($thisMonthAvg - $lastMonthAvg) / $lastMonthAvg) * 100 : 0;
            if ($lastMonthAvg > 0) echo formatTrend($avgTrend, false);
            ?>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['financial_health'], 'avg_revenue_per_job', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            Total Revenue / <?php echo number_format(getStat($stats['overall'], 'total_reports', 0)); ?> Jobs
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Avg Profit per Job</span>
            <?php 
            // Calculate trend vs last month average
            $thisMonthProfitAvg = $thisMonthReports > 0 
                ? (getStat($stats['this_month'], 'total_profit_this_month', 0) / $thisMonthReports)
                : 0;
            $lastMonthProfitAvg = $lastMonthReports > 0
                ? (getStat($stats['growth'], 'last_month_profit', 0) / $lastMonthReports)
                : 0;
            $profitAvgTrend = $lastMonthProfitAvg != 0 ? (($thisMonthProfitAvg - $lastMonthProfitAvg) / abs($lastMonthProfitAvg)) * 100 : 0;
            if ($lastMonthProfitAvg != 0) echo formatTrend($profitAvgTrend, false);
            ?>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['financial_health'], 'avg_profit_per_job', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            Total Profit / <?php echo number_format(getStat($stats['overall'], 'total_reports', 0)); ?> Jobs
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Expense Ratio</span>
        </div>
        <div class="kpi-card-value">
            <?php echo number_format(getStat($stats['financial_health'], 'expense_ratio', 0), 2); ?>%
        </div>
        <div class="kpi-card-subtitle">
            Total Expenses / Total Revenue
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Avg Cost per Job</span>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['financial_health'], 'avg_cost_per_job', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            Total Expenses / <?php echo number_format(getStat($stats['overall'], 'total_reports', 0)); ?> Jobs
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Cost Efficiency</span>
        </div>
        <div class="kpi-card-value">
            <?php 
            $totalIncome = getStat($stats['overall'], 'total_income', 0);
            $totalExpenses = getStat($stats['overall'], 'total_expenses', 0);
            $costEfficiency = $totalExpenses > 0 ? ($totalIncome / $totalExpenses) : 0;
            echo number_format($costEfficiency, 2);
            ?>
        </div>
        <div class="kpi-card-subtitle">
            Revenue per GHS 1 Expense
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Profit-to-Cost Ratio</span>
        </div>
        <div class="kpi-card-value">
            <?php 
            $totalExpenses = getStat($stats['overall'], 'total_expenses', 0);
            $totalProfit = getStat($stats['overall'], 'total_profit', 0);
            $profitToCost = $totalExpenses > 0 ? ($totalProfit / $totalExpenses) * 100 : 0;
            echo number_format($profitToCost, 2);
            ?>%
        </div>
        <div class="kpi-card-subtitle">
            Profit Generated per GHS 1 Cost
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Return on Assets (ROA)</span>
        </div>
        <div class="kpi-card-value">
            <?php echo number_format(getStat($stats['balance_sheet'], 'return_on_assets', 0), 2); ?>%
        </div>
        <div class="kpi-card-subtitle">
            Net Profit / Total Assets
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Current Ratio</span>
        </div>
        <div class="kpi-card-value">
            <?php 
            $currentRatio = getStat($stats['balance_sheet'], 'current_ratio', 0);
            echo $currentRatio >= 999 ? '∞' : number_format($currentRatio, 2);
            ?>
        </div>
        <div class="kpi-card-subtitle">
            Current Assets / Current Liabilities
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Debt Service Coverage</span>
        </div>
        <div class="kpi-card-value">
            <?php 
            $dscr = getStat($stats['balance_sheet'], 'debt_service_coverage_ratio', 0);
            echo $dscr >= 999 ? '∞' : number_format($dscr, 2);
            ?>
        </div>
        <div class="kpi-card-subtitle">
            Net Income / Total Debt Service
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Working Capital</span>
        </div>
        <div class="kpi-card-value">
            <?php 
            // Working Capital = Current Assets - Current Liabilities
            $cashOnHand = 0;
            try {
                $stmt = $pdo->prepare("SELECT days_balance FROM field_reports ORDER BY created_at DESC, id DESC LIMIT 1");
                $stmt->execute();
                $latestReport = $stmt->fetch();
                $cashOnHand = (float)($latestReport['days_balance'] ?? 0);
            } catch (PDOException $e) {
                $cashOnHand = 0;
            }
            
            $currentAssets = $cashOnHand + getStat($stats['balance_sheet'], 'cash_reserves', 0) + getStat($stats['materials'], 'total_materials_value', 0);
            $currentLiabilities = getStat($stats['loans'], 'total_outstanding', 0) + getStat($stats['overall'], 'outstanding_rig_fees', 0);
            $workingCapital = $currentAssets - $currentLiabilities;
            
            $workingCapitalColor = $workingCapital >= 0 ? '' : 'debt';
            ?>
            <span class="<?php echo $workingCapitalColor; ?>">
                GHS <?php echo number_format($workingCapital, 2); ?>
            </span>
        </div>
        <div class="kpi-card-subtitle">
            Current Assets - Current Liabilities
        </div>
    </div>
</div>

<!-- Growth & Trends -->
<div class="section-header">
    <h2 class="section-title">Growth & Trends (Month-over-Month)</h2>
</div>

<div class="metric-grid">
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Revenue Growth</span>
            <?php echo formatTrend(getStat($stats['growth'], 'revenue_growth_mom', 0)); ?>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['growth'], 'this_month_revenue', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            This Month vs GHS <?php echo number_format(getStat($stats['growth'], 'last_month_revenue', 0), 2); ?> Last Month
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Profit Growth</span>
            <?php echo formatTrend(getStat($stats['growth'], 'profit_growth_mom', 0)); ?>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['growth'], 'this_month_profit', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            This Month vs GHS <?php echo number_format(getStat($stats['growth'], 'last_month_profit', 0), 2); ?> Last Month
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Jobs Growth</span>
            <?php echo formatTrend(getStat($stats['growth'], 'jobs_growth_mom', 0)); ?>
        </div>
        <div class="kpi-card-value">
            <?php echo number_format(getStat($stats['this_month'], 'total_reports_this_month', 0)); ?>
        </div>
        <div class="kpi-card-subtitle">
            This Month (Avg: <?php echo number_format(getStat($stats['operational'], 'jobs_per_day', 0), 1); ?> jobs/day)
        </div>
    </div>
    
    <div class="kpi-card">
        <div class="kpi-card-header">
            <span class="kpi-card-title">Year-to-Date Revenue</span>
        </div>
        <div class="kpi-card-value">
            GHS <?php echo number_format(getStat($stats['this_year'], 'total_income_this_year', 0), 2); ?>
        </div>
        <div class="kpi-card-subtitle">
            <?php echo number_format(getStat($stats['this_year'], 'total_reports_this_year', 0)); ?> Jobs Completed
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Balance Sheet Overview -->
    <div class="dashboard-card">
        <h2>Balance Sheet Overview</h2>
        <div class="kpi-grid">
            <div class="kpi-item">
                <span class="kpi-label">Total Assets</span>
                <span class="kpi-value">GHS <?php echo number_format(getStat($stats['balance_sheet'], 'total_assets', 0), 2); ?></span>
                <div style="font-size: 11px; color: var(--secondary); margin-top: 4px;">
                    Cash on Hand: GHS <?php echo number_format(getStat($stats['balance_sheet'], 'cash_on_hand', 0), 2); ?> | 
                    Bank Deposits: GHS <?php echo number_format(getStat($stats['balance_sheet'], 'cash_reserves', 0), 2); ?> | 
                    Materials: GHS <?php echo number_format(getStat($stats['balance_sheet'], 'materials_value', 0), 2); ?>
                </div>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Total Liabilities</span>
                <span class="kpi-value debt">GHS <?php echo number_format(getStat($stats['balance_sheet'], 'total_liabilities', 0), 2); ?></span>
                <div style="font-size: 11px; color: var(--secondary); margin-top: 4px;">
                    Loans: GHS <?php echo number_format(getStat($stats['loans'], 'total_outstanding', 0), 2); ?>
                </div>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Net Worth</span>
                <span class="kpi-value <?php echo getStat($stats['balance_sheet'], 'net_worth', 0) >= 0 ? '' : 'debt'; ?>">
                    GHS <?php echo number_format(getStat($stats['balance_sheet'], 'net_worth', 0), 2); ?>
                </span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Debt-to-Asset Ratio</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['balance_sheet'], 'debt_to_asset_ratio', 0), 2); ?>%</span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, getStat($stats['balance_sheet'], 'debt_to_asset_ratio', 0)); ?>%;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Operational Efficiency -->
    <div class="dashboard-card">
        <h2>Operational Efficiency</h2>
        <div class="kpi-grid">
            <div class="kpi-item">
                <span class="kpi-label">Rig Utilization Rate</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['operational'], 'rig_utilization_rate', 0), 1); ?>%</span>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(100, getStat($stats['operational'], 'rig_utilization_rate', 0)); ?>%;"></div>
                </div>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Avg Job Duration</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['operational'], 'avg_job_duration_minutes', 0) / 60, 1); ?> hrs</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Avg Depth per Job</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['operational'], 'avg_depth_per_job', 0), 2); ?> m</span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Active Rigs</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['operational'], 'active_rigs', 0)); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Cash Flow (Last 30 Days) -->
    <div class="dashboard-card">
        <h2>Cash Flow Analysis (Last 30 Days)</h2>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;" class="cash-flow-cards">
            <!-- Cash Inflow Card -->
            <div style="background: linear-gradient(135deg, rgba(16,185,129,0.1) 0%, rgba(16,185,129,0.05) 100%); border: 2px solid rgba(16,185,129,0.3); border-radius: 12px; padding: 24px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(16,185,129,0.1); border-radius: 50%;"></div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 48px; height: 48px; background: rgba(16,185,129,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📈</div>
                        <h3 style="margin: 0; color: var(--text); font-size: 18px; font-weight: 600;">Cash Inflow</h3>
                    </div>
                    <div style="font-size: 32px; font-weight: 700; color: #10b981; margin-bottom: 8px;">
                        GHS <?php echo number_format(getStat($stats['cash_flow'], 'cash_inflow', 0), 2); ?>
                    </div>
                    <div style="font-size: 13px; color: var(--secondary);">Money coming into the business</div>
                </div>
            </div>
            
            <!-- Cash Outflow Card -->
            <div style="background: linear-gradient(135deg, rgba(239,68,68,0.1) 0%, rgba(239,68,68,0.05) 100%); border: 2px solid rgba(239,68,68,0.3); border-radius: 12px; padding: 24px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -20px; right: -20px; width: 100px; height: 100px; background: rgba(239,68,68,0.1); border-radius: 50%;"></div>
                <div style="position: relative; z-index: 1;">
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                        <div style="width: 48px; height: 48px; background: rgba(239,68,68,0.2); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">📉</div>
                        <h3 style="margin: 0; color: var(--text); font-size: 18px; font-weight: 600;">Cash Outflow</h3>
                    </div>
                    <div style="font-size: 32px; font-weight: 700; color: #ef4444; margin-bottom: 8px;">
                        GHS <?php echo number_format(getStat($stats['cash_flow'], 'cash_outflow', 0), 2); ?>
                    </div>
                    <div style="font-size: 13px; color: var(--secondary);">Money going out of the business</div>
                </div>
            </div>
        </div>
        
        <!-- Net Cash Flow and Bank Deposits -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
            <div class="kpi-item">
                <span class="kpi-label">Net Cash Flow</span>
                <span class="kpi-value <?php echo getStat($stats['cash_flow'], 'net_cash_flow', 0) >= 0 ? '' : 'debt'; ?>">
                    GHS <?php echo number_format(getStat($stats['cash_flow'], 'net_cash_flow', 0), 2); ?>
                </span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Bank Deposits</span>
                <span class="kpi-value">GHS <?php echo number_format(getStat($stats['cash_flow'], 'deposits', 0), 2); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Overall Financial Summary -->
    <div class="dashboard-card">
        <h2>Overall Financial Summary</h2>
        <div class="kpi-grid" style="grid-template-columns: repeat(2, 1fr);">
            <div class="kpi-item">
                <span class="kpi-label">Total Revenue</span>
                <span class="kpi-value">GHS <?php echo number_format(getStat($stats['overall'], 'total_income', 0), 2); ?></span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Total Expenses</span>
                <span class="kpi-value debt">GHS <?php echo number_format(getStat($stats['overall'], 'total_expenses', 0), 2); ?></span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Net Profit</span>
                <span class="kpi-value <?php echo getStat($stats['overall'], 'total_profit', 0) >= 0 ? '' : 'debt'; ?>">
                    GHS <?php echo number_format(getStat($stats['overall'], 'total_profit', 0), 2); ?>
                </span>
            </div>
            <div class="kpi-item">
                <span class="kpi-label">Total Jobs</span>
                <span class="kpi-value"><?php echo number_format(getStat($stats['overall'], 'total_reports', 0)); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Top Performers -->
<div class="dashboard-grid" style="margin-top: 16px;">
    <!-- Top Clients -->
    <div class="dashboard-card">
        <h2>Top Performing Clients</h2>
        <?php if (!empty($stats['top_clients'])): ?>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Jobs</th>
                        <th>Revenue</th>
                        <th>Profit</th>
                        <th>Avg/Job</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_clients'] as $client): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($client['client_name']); ?></strong></td>
                            <td><?php echo number_format($client['job_count']); ?></td>
                            <td>GHS <?php echo number_format($client['total_revenue'], 2); ?></td>
                            <td class="<?php echo $client['total_profit'] >= 0 ? '' : 'text-danger'; ?>">
                                GHS <?php echo number_format($client['total_profit'], 2); ?>
                            </td>
                            <td>GHS <?php echo number_format($client['avg_profit_per_job'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">No client data available</p>
        <?php endif; ?>
    </div>
    
    <!-- Top Rigs (RPM-based) -->
    <div class="dashboard-card">
        <h2>🚛 Rig Performance Overview (RPM-Based)</h2>
        <?php if (!empty($rigPerformance)): ?>
            <div style="margin-bottom: 20px;">
                <p style="color: var(--secondary); font-size: 13px; margin: 0;">
                    Performance metrics aggregated across all field reports for each active rig.
                </p>
            </div>
            <table class="performance-table">
                <thead>
                    <tr>
                        <th>Rig</th>
                        <th>Jobs</th>
                        <th>Total RPM</th>
                        <th>Current RPM</th>
                        <th>Revenue</th>
                        <th>Expenses</th>
                        <th>Profit</th>
                        <th>Margin</th>
                        <th>Avg/Job</th>
                        <th>Last Job</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rigPerformance as $rig): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($rig['rig_name']); ?></strong><br>
                                <span style="font-size: 11px; color: var(--secondary);"><?php echo htmlspecialchars($rig['rig_code']); ?></span>
                            </td>
                            <td><?php echo number_format($rig['job_count']); ?></td>
                            <td><strong><?php echo number_format($rig['total_rpm'], 2); ?></strong></td>
                            <td><?php echo number_format($rig['current_rpm'], 2); ?></td>
                            <td>GHS <?php echo number_format($rig['total_revenue'], 2); ?></td>
                            <td>GHS <?php echo number_format($rig['total_expenses'], 2); ?></td>
                            <td class="<?php echo $rig['total_profit'] >= 0 ? '' : 'text-danger'; ?>">
                                <strong>GHS <?php echo number_format($rig['total_profit'], 2); ?></strong>
                            </td>
                            <td class="<?php echo $rig['profit_margin'] >= 0 ? '' : 'text-danger'; ?>">
                                <?php echo number_format($rig['profit_margin'], 1); ?>%
                            </td>
                            <td>GHS <?php echo number_format($rig['avg_profit_per_job'], 2); ?></td>
                            <td style="font-size: 11px;">
                                <?php echo $rig['last_job_date'] ? date('M d, Y', strtotime($rig['last_job_date'])) : 'N/A'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="margin-top: 16px; padding: 12px; background: var(--card-secondary); border-radius: 8px; font-size: 12px; color: var(--secondary);">
                <strong>Note:</strong> Total RPM represents cumulative RPM from all completed jobs. Current RPM shows the rig's current meter reading. 
                Revenue and profit are aggregated from all field reports for each rig.
            </div>
        <?php else: ?>
            <p class="no-data">No rig performance data available</p>
        <?php endif; ?>
    </div>
</div>

<!-- Job Type Distribution -->
<div class="dashboard-card" style="margin-top: 16px;">
    <h2>Job Type Distribution</h2>
    <?php if (!empty($stats['job_types'])): ?>
        <table class="performance-table">
            <thead>
                <tr>
                    <th>Job Type</th>
                    <th>Count</th>
                    <th>Revenue</th>
                    <th>Profit</th>
                    <th>Avg Profit/Job</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalJobCount = array_sum(array_column($stats['job_types'], 'job_count'));
                foreach ($stats['job_types'] as $type): 
                    $percentage = $totalJobCount > 0 ? ($type['job_count'] / $totalJobCount) * 100 : 0;
                ?>
                    <tr>
                        <td>
                            <strong><?php echo ucfirst(htmlspecialchars($type['job_type'])); ?></strong>
                        </td>
                        <td>
                            <?php echo number_format($type['job_count']); ?>
                            <span style="font-size: 11px; color: var(--secondary);">
                                (<?php echo number_format($percentage, 1); ?>%)
                            </span>
                        </td>
                        <td>GHS <?php echo number_format($type['total_revenue'], 2); ?></td>
                        <td class="<?php echo $type['total_profit'] >= 0 ? '' : 'text-danger'; ?>">
                            GHS <?php echo number_format($type['total_profit'], 2); ?>
                        </td>
                        <td>GHS <?php echo number_format($type['total_profit'] / max(1, $type['job_count']), 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="no-data">No job type data available</p>
    <?php endif; ?>
</div>

<!-- Recent Activity -->
<div class="dashboard-card" style="margin-top: 16px;">
    <h2>🕐 Recent Activity</h2>
    <div class="activity-list">
        <?php if (empty($recentActivity)): ?>
            <p class="no-data">No recent activity</p>
        <?php else: ?>
            <?php foreach ($recentActivity as $activity): ?>
                <div class="activity-item">
                    <div class="activity-details">
                        <strong><?php echo htmlspecialchars($activity['site_name']); ?></strong>
                        <span>
                            <?php echo htmlspecialchars($activity['rig_name'] ?? 'N/A'); ?> • 
                            <?php echo htmlspecialchars($activity['client_name'] ?? 'N/A'); ?> • 
                            <?php echo $activity['report_date']; ?>
                        </span>
                    </div>
                    <div class="activity-profit <?php echo $activity['net_profit'] >= 0 ? 'positive' : 'negative'; ?>">
                        GHS <?php echo number_format($activity['net_profit'], 2); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Analytics Charts Section -->
<div class="section-header" style="margin-top: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <h2 class="section-title">Analytics & Trends</h2>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button onclick="refreshCharts()" class="btn btn-sm btn-outline" title="Refresh Charts">
                🔄 Refresh
            </button>
            <button onclick="exportChart('revenue')" class="btn btn-sm btn-outline" title="Export Chart Data">
                📥 Export Data
            </button>
            <button onclick="exportChartAsImage('revenueChart')" class="btn btn-sm btn-outline" title="Export as Image">
                📷 Image
            </button>
            <a href="export.php?module=reports&format=pdf" class="btn btn-sm btn-outline" title="Export Full Report as PDF" target="_blank">
                📄 PDF
            </a>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Revenue Trend Chart -->
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 14px; font-weight: 600; color: var(--text); margin: 0;">📈 Revenue & Profit Trend</h3>
            <span style="font-size: 11px; color: var(--secondary);">Click to drill down</span>
        </div>
        <div style="height: 300px; display: flex; align-items: center; justify-content: center; background: var(--bg); border-radius: 6px; position: relative;">
            <canvas id="revenueChart" style="max-height: 100%; cursor: pointer;"></canvas>
            <div id="revenueChartLoading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: var(--secondary); font-size: 14px; display: none;">
                Loading chart data...
            </div>
        </div>
    </div>
    
    <!-- Profit Analysis Chart -->
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="font-size: 14px; font-weight: 600; color: var(--text); margin: 0;">💰 Income vs Expenses</h3>
            <span style="font-size: 11px; color: var(--secondary);">Click to drill down</span>
        </div>
        <div style="height: 300px; display: flex; align-items: center; justify-content: center; background: var(--bg); border-radius: 6px; position: relative;">
            <canvas id="profitChart" style="max-height: 100%; cursor: pointer;"></canvas>
            <div id="profitChartLoading" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: var(--secondary); font-size: 14px; display: none;">
                Loading chart data...
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions moved to bottom -->

<!-- Reports & Receipts Section -->
<div class="dashboard-card" style="margin-top: 16px; border-left: 4px solid #0ea5e9;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="margin: 0; color: #0ea5e9;">📄 Reports & Receipts</h2>
            <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                Generate professional receipts/invoices and technical reports for clients
            </p>
        </div>
        <a href="field-reports-list.php" class="btn btn-primary">
            View All Reports →
        </a>
    </div>
    
    <?php
    // Get recent reports for quick access
    try {
        $recentReports = $pdo->query("
            SELECT fr.id, fr.report_id, fr.report_date, fr.site_name, fr.total_income, 
                   c.client_name, r.rig_name, fr.job_type
            FROM field_reports fr
            LEFT JOIN clients c ON fr.client_id = c.id
            LEFT JOIN rigs r ON fr.rig_id = r.id
            ORDER BY fr.report_date DESC, fr.id DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $recentReports = [];
    }
    ?>
    
    <?php if (empty($recentReports)): ?>
        <div style="text-align: center; padding: 40px 20px; color: var(--secondary);">
            <p style="font-size: 16px; margin-bottom: 16px;">No reports found yet.</p>
            <p style="margin-bottom: 20px;">Create your first field report to generate receipts and technical reports.</p>
            <a href="field-reports.php" class="btn btn-primary">📝 Create First Report</a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="margin-top: 0;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Report ID</th>
                        <th>Client</th>
                        <th>Site</th>
                        <th>Amount</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentReports as $report): ?>
                    <tr>
                        <td><?php echo formatDate($report['report_date']); ?></td>
                        <td><code style="font-size: 12px;"><?php echo e($report['report_id']); ?></code></td>
                        <td><?php echo e($report['client_name'] ?? 'N/A'); ?></td>
                        <td><?php echo e($report['site_name'] ?? 'N/A'); ?></td>
                        <td style="font-weight: 600; color: var(--success);">
                            <?php echo formatCurrency($report['total_income'] ?? 0); ?>
                        </td>
                        <td>
                            <div class="action-dropdown">
                                <button class="btn btn-sm btn-outline action-dropdown-toggle" onclick="toggleActionDropdown(<?php echo $report['id']; ?>)">
                                    Actions <span style="margin-left: 5px;">▼</span>
                                </button>
                                <div id="action-menu-<?php echo $report['id']; ?>" class="action-dropdown-menu" style="display: none;">
                                    <a href="#" onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" class="action-menu-item">
                                        <span style="margin-right: 8px;">👁️</span> View Report
                                    </a>
                                    <a href="receipt.php?report_id=<?php echo $report['id']; ?>" target="_blank" class="action-menu-item">
                                        <span style="margin-right: 8px;">💰</span> Generate Receipt
                                    </a>
                                    <a href="technical-report.php?report_id=<?php echo $report['id']; ?>" target="_blank" class="action-menu-item">
                                        <span style="margin-right: 8px;">📄</span> Generate Report
                                    </a>
                                    <a href="#" onclick="editReportDetails(<?php echo $report['id']; ?>); return false;" class="action-menu-item">
                                        <span style="margin-right: 8px;">✏️</span> Edit Report
                                    </a>
                                    <a href="#" onclick="deleteReport(event, <?php echo $report['id']; ?>, '<?php echo e($report['report_id']); ?>'); return false;" class="action-menu-item action-menu-item-danger">
                                        <span style="margin-right: 8px;">🗑️</span> Delete Report
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center;">
            <p style="color: var(--secondary); font-size: 13px; margin-bottom: 12px;">
                💡 <strong>Tip:</strong> Click "💰 Receipt" to generate a professional invoice/receipt with company branding.<br>
                Click "📄 Technical" to generate a detailed technical report (no financial info).
            </p>
            <a href="field-reports-list.php" class="btn btn-outline" style="font-size: 14px;">
                View All Reports & Generate More →
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Report Details Modal -->
<div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 900px; margin: 30px auto; background: white; border-radius: 8px; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa; border-radius: 8px 8px 0 0;">
            <h2 style="margin: 0; color: #1e293b;">Report Details</h2>
            <button onclick="closeReportModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; width: 32px; height: 32px; line-height: 1;">&times;</button>
        </div>
        <div id="reportModalContent" style="padding: 30px; max-height: calc(100vh - 150px); overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                <p style="margin-top: 15px; color: #64748b;">Loading report details...</p>
            </div>
        </div>
    </div>
</div>

<!-- AI Insights (lightweight baseline) -->
<div class="dashboard-card" style="margin-top: 16px;">
    <h2>🤖 AI Insights</h2>
    <div id="aiInsights" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:12px;"></div>
</div>

<!-- Quick Actions -->
<div class="dashboard-card" style="margin-top: 16px;">
    <h2>Quick Actions</h2>
    <div class="action-buttons" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px;">
        <a href="field-reports.php" class="action-btn">
            <span class="action-icon" style="font-size: 20px; opacity: 0.7;">📝</span>
            <span>New Field Report</span>
        </a>
        <a href="payroll.php" class="action-btn">
            <span class="action-icon" style="font-size: 20px; opacity: 0.7;">💰</span>
            <span>Process Payroll</span>
        </a>
        <a href="resources.php?action=materials" class="action-btn">
            <span class="action-icon" style="font-size: 20px; opacity: 0.7;">📦</span>
            <span>Manage Materials</span>
        </a>
        <a href="finance.php" class="action-btn">
            <span class="action-icon" style="font-size: 20px; opacity: 0.7;">📊</span>
            <span>Financial Reports</span>
        </a>
    </div>
    <style>
        @media (max-width: 900px) {
            .dashboard-card .action-buttons {
                grid-template-columns: repeat(2, 1fr) !important;
            }
        }
        @media (max-width: 500px) {
            .dashboard-card .action-buttons {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</div>

<script>
// Fetch baseline cash flow forecast and render quick insights
(function(){
    fetch('../api/ai-service.php?action=forecast_cashflow').then(r=>r.json()).then(res=>{
        if(!res.success) return;
        const cont = document.getElementById('aiInsights');
        if (!cont) return;
        const items = res.data.horizons || [];
        items.slice(0,3).forEach(h => {
            const card = document.createElement('div');
            card.style.border = '1px solid var(--border)';
            card.style.borderRadius = '8px';
            card.style.padding = '12px';
            card.style.background = 'var(--bg)';
            card.innerHTML = `<div style="font-size:12px; color: var(--secondary);">Cash Flow ${h.horizon.toUpperCase()}</div>
                              <div style="font-size:18px; font-weight:700; color: var(--text);">Net: GHS ${Number(h.net).toFixed(2)}</div>
                              <div style="font-size:12px; color: var(--secondary); margin-top:4px;">In: GHS ${Number(h.inflow).toFixed(2)} • Out: GHS ${Number(h.outflow).toFixed(2)}</div>`;
            cont.appendChild(card);
        });
    }).catch(()=>{});
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Initialize charts after DOM and main.js are loaded
(function() {
    function initDashboard() {
        // Ensure theme toggle is working (main.js should handle this, but ensure it's working)
        if (window.abbisApp) {
            window.abbisApp.initializeTheme();
        }
        
        // Initialize simple analytics charts
        loadDashboardCharts();
    }
    
    // Run when DOM is ready and main.js is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit to ensure main.js has loaded
            setTimeout(initDashboard, 50);
        });
    } else {
        // DOM already loaded
        setTimeout(initDashboard, 50);
    }
})();

// Global chart instances
let revenueChart = null;
let profitChart = null;

function loadDashboardCharts() {
    loadEnhancedCharts();
}

function loadEnhancedCharts() {
    // Get filter values
    const filters = getFilters();
    
    // Load Revenue Trend Chart with real data
    loadRevenueChart(filters);
    
    // Load Profit Analysis Chart with real data
    loadProfitChart(filters);
}

function getFilters() {
    return {
        start_date: document.getElementById('filterDateFrom')?.value || '<?php echo date('Y-m-01'); ?>',
        end_date: document.getElementById('filterDateTo')?.value || '<?php echo date('Y-m-t'); ?>',
        rig_id: document.getElementById('filterRig')?.value || '',
        client_id: document.getElementById('filterClient')?.value || '',
        job_type: document.getElementById('filterJobType')?.value || '',
        group_by: 'month'
    };
}

function loadRevenueChart(filters) {
    const revenueCtx = document.getElementById('revenueChart');
    const loadingDiv = document.getElementById('revenueChartLoading');
    if (!revenueCtx) return;
    
    // Show loading indicator
    if (loadingDiv) loadingDiv.style.display = 'block';
    
    // Destroy existing chart
    if (revenueChart) {
        revenueChart.destroy();
    }
    
    // Fetch time series data
    const params = new URLSearchParams({
        type: 'time_series',
        ...filters
    });
    
    fetch(`../api/analytics-api.php?${params}`)
        .then(r => r.json())
        .then(res => {
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            if (!res.success || !res.data || res.data.length === 0) {
                // Fallback to empty chart
                createRevenueChart([], []);
                return;
            }
            
            const labels = res.data.map(item => formatPeriodLabel(item.period));
            const revenue = res.data.map(item => parseFloat(item.revenue || 0));
            const profit = res.data.map(item => parseFloat(item.profit || 0));
            
            // Add forecast
            const forecast = calculateForecast(revenue);
            const forecastLabels = ['Next Month'];
            const forecastData = [forecast];
            
            createRevenueChart(labels, revenue, profit, forecastLabels, forecastData);
        })
        .catch(err => {
            console.error('Error loading revenue chart:', err);
            if (loadingDiv) loadingDiv.style.display = 'none';
            createRevenueChart([], []);
        });
}

function createRevenueChart(labels, revenue, profit = [], forecastLabels = [], forecastData = []) {
    const revenueCtx = document.getElementById('revenueChart');
    if (!revenueCtx) return;
    
    revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: labels.length > 0 ? labels : ['No Data'],
            datasets: [
                {
                    label: 'Revenue',
                    data: revenue.length > 0 ? revenue : [0],
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                ...(profit.length > 0 ? [{
                    label: 'Profit',
                    data: profit,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.1)',
                    tension: 0.4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }] : []),
                ...(forecastData.length > 0 ? [{
                    label: 'Forecast',
                    data: [...Array(labels.length).fill(null), ...forecastData],
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245,158,11,0.1)',
                    borderDash: [5, 5],
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }] : [])
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': GHS ' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return 'GHS ' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    drillDownChart('revenue', elements[0].index);
                }
            }
        }
    });
}

function loadProfitChart(filters) {
    const profitCtx = document.getElementById('profitChart');
    const loadingDiv = document.getElementById('profitChartLoading');
    if (!profitCtx) return;
    
    // Show loading indicator
    if (loadingDiv) loadingDiv.style.display = 'block';
    
    // Destroy existing chart
    if (profitChart) {
        profitChart.destroy();
    }
    
    // Fetch financial overview
    const params = new URLSearchParams({
        type: 'financial_overview',
        ...filters
    });
    
    fetch(`../api/analytics-api.php?${params}`)
        .then(r => r.json())
        .then(res => {
            if (loadingDiv) loadingDiv.style.display = 'none';
            
            if (!res.success || !res.data) {
                createProfitChart(0, 0, 0);
                return;
            }
            
            const income = parseFloat(res.data.total_revenue || 0);
            const expenses = parseFloat(res.data.total_expenses || 0);
            const profit = parseFloat(res.data.total_profit || 0);
            
            createProfitChart(income, expenses, profit);
        })
        .catch(err => {
            console.error('Error loading profit chart:', err);
            if (loadingDiv) loadingDiv.style.display = 'none';
            createProfitChart(0, 0, 0);
        });
}

function exportChart(chartType) {
    const filters = getFilters();
    const params = new URLSearchParams({
        format: 'csv',
        section: chartType === 'revenue' ? 'financial' : 'financial',
        ...filters
    });
    window.location.href = `../api/dashboard-export.php?${params}`;
}

function exportChartAsImage(canvasId) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) {
        showNotification('Chart not found', 'error');
        return;
    }
    
    const chart = window[canvasId.replace('Chart', '') + 'Chart'];
    if (!chart) {
        showNotification('Chart instance not found', 'error');
        return;
    }
    
    // Get chart canvas and convert to image
    const url = chart.toBase64Image('image/png', 1.0);
    
    // Create download link
    const link = document.createElement('a');
    link.download = `${canvasId}_${new Date().toISOString().split('T')[0]}.png`;
    link.href = url;
    link.click();
    
    showNotification('Chart exported as image', 'success');
}

function refreshCharts() {
    if (revenueChart) {
        revenueChart.destroy();
        revenueChart = null;
    }
    if (profitChart) {
        profitChart.destroy();
        profitChart = null;
    }
    loadEnhancedCharts();
    showNotification('Charts refreshed', 'success');
}

function showNotification(message, type = 'info') {
    // Simple notification system
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 20px;
        background: var(--card);
        border: 1px solid var(--border);
        border-left: 4px solid ${type === 'success' ? 'var(--success)' : 'var(--primary)'};
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function createProfitChart(income, expenses, profit) {
    const profitCtx = document.getElementById('profitChart');
    if (!profitCtx) return;
    
    profitChart = new Chart(profitCtx, {
        type: 'bar',
        data: {
            labels: ['Income', 'Expenses', 'Profit'],
            datasets: [{
                label: 'Amount (GHS)',
                data: [income, expenses, profit],
                backgroundColor: [
                    'rgba(16,185,129,0.8)',
                    'rgba(239,68,68,0.8)',
                    profit >= 0 ? 'rgba(14,165,233,0.8)' : 'rgba(239,68,68,0.8)'
                ],
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'GHS ' + context.parsed.y.toLocaleString('en-US', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.05)' },
                    ticks: {
                        callback: function(value) {
                            return 'GHS ' + value.toLocaleString();
                        }
                    }
                },
                x: {
                    grid: { display: false }
                }
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    drillDownChart('profit', elements[0].index);
                }
            }
        }
    });
}

function formatPeriodLabel(period) {
    if (!period) return '';
    // Handle different period formats
    if (period.includes('-')) {
        const parts = period.split('-');
        if (parts.length === 3) {
            // YYYY-MM-DD
            const date = new Date(period);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        } else if (parts.length === 2) {
            // YYYY-MM
            const date = new Date(period + '-01');
            return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
        }
    }
    return period;
}

function calculateForecast(data) {
    if (data.length < 3) return 0;
    
    // Simple linear regression for forecasting
    const n = data.length;
    let sumX = 0, sumY = 0, sumXY = 0, sumX2 = 0;
    
    for (let i = 0; i < n; i++) {
        sumX += i;
        sumY += data[i];
        sumXY += i * data[i];
        sumX2 += i * i;
    }
    
    const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
    const intercept = (sumY - slope * sumX) / n;
    
    return Math.max(0, intercept + slope * n); // Forecast for next period
}

function applyFilters() {
    const filters = getFilters();
    const statusDiv = document.getElementById('filterStatus');
    const statusText = document.getElementById('filterStatusText');
    
    if (statusDiv) {
        statusDiv.style.display = 'block';
        statusText.textContent = '🔄 Updating charts...';
        statusDiv.style.background = 'rgba(14,165,233,0.1)';
        statusDiv.style.color = 'var(--primary)';
    }
    
    // Reload charts with new filters
    loadEnhancedCharts();
    
    // Update status after a delay
    setTimeout(() => {
        if (statusDiv) {
            statusText.textContent = '✅ Charts updated with filters';
            statusDiv.style.background = 'rgba(16,185,129,0.1)';
            statusDiv.style.color = 'var(--success)';
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        }
    }, 1000);
}

function resetFilters() {
    document.getElementById('filterDateFrom').value = '<?php echo date('Y-m-01'); ?>';
    document.getElementById('filterDateTo').value = '<?php echo date('Y-m-t'); ?>';
    document.getElementById('filterRig').value = '';
    document.getElementById('filterClient').value = '';
    document.getElementById('filterJobType').value = '';
    applyFilters();
}

function exportDashboard(format, section = 'all') {
    const filters = getFilters();
    const params = new URLSearchParams({
        format: format,
        section: section,
        ...filters
    });
    
    window.location.href = `../api/dashboard-export.php?${params}`;
}

function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    if (menu) {
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }
}

// Close export menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('exportMenu');
    const button = event.target.closest('.export-dropdown button');
    if (menu && !menu.contains(event.target) && !button) {
        menu.style.display = 'none';
    }
});

function drillDownChart(chartType, index) {
    // Open detailed view based on chart type and clicked element
    const filters = getFilters();
    
    if (chartType === 'revenue') {
        // Could open a detailed modal or navigate to analytics page
        window.location.href = `analytics.php?type=financial&start_date=${filters.start_date}&end_date=${filters.end_date}`;
    } else if (chartType === 'profit') {
        const labels = ['Income', 'Expenses', 'Profit'];
        const metric = labels[index];
        window.location.href = `finance.php?metric=${metric}&start_date=${filters.start_date}&end_date=${filters.end_date}`;
    }
}

function drillDownKPI(kpiType) {
    const filters = getFilters();
    
    switch(kpiType) {
        case 'profit_margin':
            window.location.href = `finance.php?metric=profit_margin&start_date=${filters.start_date}&end_date=${filters.end_date}`;
            break;
        case 'revenue':
            window.location.href = `finance.php?metric=revenue&start_date=${filters.start_date}&end_date=${filters.end_date}`;
            break;
        case 'expenses':
            window.location.href = `finance.php?metric=expenses&start_date=${filters.start_date}&end_date=${filters.end_date}`;
            break;
        default:
            window.location.href = `analytics.php?type=financial&start_date=${filters.start_date}&end_date=${filters.end_date}`;
    }
}

// Load and display dashboard alerts
function loadDashboardAlerts() {
    const alertsContainer = document.getElementById('dashboardAlerts');
    if (!alertsContainer) return;
    
    const alertsData = <?php
    // Check for alerts
    $alerts = [];
    
    // Check profit margin
    $profitMargin = getStat($stats['financial_health'], 'profit_margin', 0);
    if ($profitMargin < 10 && $profitMargin > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "Profit margin is below 10% (" . number_format($profitMargin, 2) . "%). Consider reviewing expenses.",
            'icon' => '⚠️'
        ];
    }
    
    // Outstanding debts and rig fees are now shown in dedicated alert cards above
    // Removed duplicate alert here to avoid repetition
    
    // Check cash flow
    $netCashFlow = getStat($stats['cash_flow'], 'net_cash_flow', 0);
    if ($netCashFlow < 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "Negative cash flow detected. Review expenses and revenue.",
            'icon' => '💸'
        ];
    }
    
    // Check debt-to-asset ratio
    $debtRatio = getStat($stats['balance_sheet'], 'debt_to_asset_ratio', 0);
    if ($debtRatio > 50 && $debtRatio > 0) {
        $alerts[] = [
            'type' => 'warning',
            'message' => "Debt-to-asset ratio is " . number_format($debtRatio, 2) . "%. Consider reducing liabilities.",
            'icon' => '📊'
        ];
    }
    
    // Check maintenance pending
    $maintPending = $ops['maint_pending'] ?? 0;
    if ($maintPending > 5) {
        $alerts[] = [
            'type' => 'info',
            'message' => "{$maintPending} maintenance tasks pending. Review maintenance schedule.",
            'icon' => '🔧'
        ];
    }
    
    echo json_encode($alerts);
    ?>;
    
    if (alertsData.length === 0) {
        alertsContainer.innerHTML = '';
        return;
    }
    
    alertsContainer.innerHTML = '';
    alertsData.forEach(alert => {
        const alertDiv = document.createElement('div');
        alertDiv.className = `dashboard-alert ${alert.type}`;
        alertDiv.innerHTML = `
            <span style="font-size: 20px;">${alert.icon}</span>
            <span style="flex: 1; font-size: 14px;">${alert.message}</span>
            <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; font-size: 18px; color: var(--secondary); padding: 0; width: 24px; height: 24px;">&times;</button>
        `;
        alertsContainer.appendChild(alertDiv);
    });
}

// Initialize alerts on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardAlerts();
    
    // Auto-refresh alerts every 5 minutes
    setInterval(loadDashboardAlerts, 300000);
});

// Report Details Modal Functions
let currentReportId = null;
let currentEditMode = false;

// Format report details for viewing
function formatReportDetails(report) {
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const formatCurrency = (val) => {
        return val ? 'GHS ' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'GHS 0.00';
    };
    
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };
    
    const formatTime = (timeStr) => {
        if (!timeStr) return 'N/A';
        return escapeHtml(timeStr.substring(0, 5));
    };
    
    let html = `
        <div class="report-detail-section">
            <h3>📋 Basic Information</h3>
            <div class="report-detail-grid">
                <div class="report-detail-item">
                    <strong>Report ID</strong>
                    <span><code>${escapeHtml(report.report_id || 'N/A')}</code></span>
                </div>
                <div class="report-detail-item">
                    <strong>Report Date</strong>
                    <span>${formatDate(report.report_date)}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Job Type</strong>
                    <span><span class="badge">${escapeHtml((report.job_type || '').charAt(0).toUpperCase() + (report.job_type || '').slice(1))}</span></span>
                </div>
                <div class="report-detail-item">
                    <strong>Rig</strong>
                    <span>${escapeHtml(report.rig_name || 'N/A')} ${report.rig_code ? '(' + escapeHtml(report.rig_code) + ')' : ''}</span>
                </div>
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>📍 Site Information</h3>
            <div class="report-detail-grid">
                <div class="report-detail-item">
                    <strong>Site Name</strong>
                    <span>${escapeHtml(report.site_name || 'N/A')}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Region</strong>
                    <span>${escapeHtml(report.region || 'N/A')}</span>
                </div>
                ${report.latitude && report.longitude ? `
                <div class="report-detail-item">
                    <strong>Coordinates</strong>
                    <span>${escapeHtml(report.latitude)}, ${escapeHtml(report.longitude)}</span>
                </div>
                ` : ''}
            </div>
            ${report.location_description ? `
            <div style="margin-top: 15px;">
                <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">Location Description</strong>
                <p style="color: #1e293b; margin: 0; line-height: 1.6;">${escapeHtml(report.location_description || '').replace(/\n/g, '<br>')}</p>
            </div>
            ` : ''}
        </div>
        
        <div class="report-detail-section">
            <h3>⛏️ Drilling Operations</h3>
            <div class="report-detail-grid">
                <div class="report-detail-item">
                    <strong>Supervisor</strong>
                    <span>${escapeHtml(report.supervisor || 'N/A')}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Total Workers</strong>
                    <span>${report.total_workers || 0} personnel</span>
                </div>
                <div class="report-detail-item">
                    <strong>Total Depth</strong>
                    <span>${report.total_depth || 0} meters</span>
                </div>
                ${report.total_duration ? `
                <div class="report-detail-item">
                    <strong>Duration</strong>
                    <span>${Math.floor(report.total_duration / 60)}h ${report.total_duration % 60}m</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>📦 Materials Used</h3>
            <div class="report-detail-grid">
                <div class="report-detail-item">
                    <strong>Screen Pipes</strong>
                    <span>${report.screen_pipes_used || 0}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Plain Pipes</strong>
                    <span>${report.plain_pipes_used || 0}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Gravel</strong>
                    <span>${report.gravel_used || 0}</span>
                </div>
            </div>
        </div>
    `;
    
    // Add notes sections if available
    if (report.remarks || report.incident_log || report.solution_log || report.recommendation_log) {
        html += `<div class="report-detail-section">
            <h3>📝 Notes & Observations</h3>`;
        
        if (report.remarks) {
            html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">General Remarks</strong>
                <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.remarks || '').replace(/\n/g, '<br>')}</p>
            </div>`;
        }
        
        if (report.incident_log) {
            html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">⚠️ Incidents Encountered</strong>
                <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.incident_log || '').replace(/\n/g, '<br>')}</p>
            </div>`;
        }
        
        if (report.solution_log) {
            html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">✅ Solutions Applied</strong>
                <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.solution_log || '').replace(/\n/g, '<br>')}</p>
            </div>`;
        }
        
        if (report.recommendation_log) {
            html += `
            <div style="margin-bottom: 15px;">
                <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">💡 Recommendations</strong>
                <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.recommendation_log || '').replace(/\n/g, '<br>')}</p>
            </div>`;
        }
        
        html += `</div>`;
    }
    
    // Add action buttons
    html += `
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
            <a href="receipt.php?report_id=${report.id}" target="_blank" class="btn btn-primary">💰 Receipt</a>
            <a href="technical-report.php?report_id=${report.id}" target="_blank" class="btn btn-success">📄 Technical Report</a>
            <button onclick="closeReportModal()" class="btn btn-outline">Close</button>
        </div>
    `;
    
    return html;
}

// Format report details for editing - shows full report with editable fields
function formatReportDetailsEditable(report) {
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const formatCurrency = (val) => {
        return val ? 'GHS ' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'GHS 0.00';
    };
    
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };
    
    let html = `
        <form id="editReportForm" onsubmit="updateReport(event); return false;">
            <div class="report-detail-section">
                <h3>📋 Basic Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Report ID</strong>
                        <span><code>${escapeHtml(report.report_id || 'N/A')}</code></span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Report Date</strong>
                        <span>${formatDate(report.report_date)}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Job Type</strong>
                        <span><span class="badge">${escapeHtml((report.job_type || '').charAt(0).toUpperCase() + (report.job_type || '').slice(1))}</span></span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Rig</strong>
                        <span>${escapeHtml(report.rig_name || 'N/A')} ${report.rig_code ? '(' + escapeHtml(report.rig_code) + ')' : ''}</span>
                    </div>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>📍 Site Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Site Name</strong>
                        <input type="text" name="site_name" value="${escapeHtml(report.site_name || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="report-detail-item">
                        <strong>Region</strong>
                        <input type="text" name="region" value="${escapeHtml(report.region || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    ${report.latitude && report.longitude ? `
                    <div class="report-detail-item">
                        <strong>Coordinates</strong>
                        <span>${escapeHtml(report.latitude)}, ${escapeHtml(report.longitude)}</span>
                    </div>
                    ` : ''}
                </div>
                <div style="margin-top: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">Location Description</strong>
                    <textarea name="location_description" class="form-control" rows="3" style="width: 100%;">${escapeHtml(report.location_description || '')}</textarea>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>👥 Client Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Client Name</strong>
                        <span>${escapeHtml(report.client_name || 'N/A')}</span>
                    </div>
                    ${report.contact_person ? `
                    <div class="report-detail-item">
                        <strong>Contact Person</strong>
                        <span>${escapeHtml(report.contact_person)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>⛏️ Drilling Operations</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Supervisor</strong>
                        <input type="text" name="supervisor" value="${escapeHtml(report.supervisor || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="report-detail-item">
                        <strong>Total Workers</strong>
                        <span>${report.total_workers || 0} personnel</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Total Depth</strong>
                        <span>${report.total_depth || 0} meters</span>
                    </div>
                    ${report.total_duration ? `
                    <div class="report-detail-item">
                        <strong>Duration</strong>
                        <span>${Math.floor(report.total_duration / 60)}h ${report.total_duration % 60}m</span>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>📦 Materials Used</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Screen Pipes</strong>
                        <span>${report.screen_pipes_used || 0}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Plain Pipes</strong>
                        <span>${report.plain_pipes_used || 0}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Gravel</strong>
                        <span>${report.gravel_used || 0}</span>
                    </div>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>💰 Financial Summary</h3>
                <table class="report-detail-table">
                    <tr>
                        <td>Contract Sum</td>
                        <td style="text-align: right;">${formatCurrency(report.contract_sum)}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Income</strong></td>
                        <td style="text-align: right;"><strong>${formatCurrency(report.total_income)}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Total Expenses</strong></td>
                        <td style="text-align: right;"><strong>${formatCurrency(report.total_expenses)}</strong></td>
                    </tr>
                    <tr style="background: #f8f9fa; border-top: 2px solid #1e293b;">
                        <td><strong>Net Profit</strong></td>
                        <td style="text-align: right; color: ${parseFloat(report.net_profit || 0) >= 0 ? '#10b981' : '#ef4444'};">
                            <strong>${formatCurrency(report.net_profit)}</strong>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="report-detail-section">
                <h3>📝 Notes & Observations</h3>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">General Remarks</strong>
                    <textarea name="remarks" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.remarks || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">⚠️ Incidents Encountered</strong>
                    <textarea name="incident_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.incident_log || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">✅ Solutions Applied</strong>
                    <textarea name="solution_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.solution_log || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">💡 Recommendations</strong>
                    <textarea name="recommendation_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.recommendation_log || '')}</textarea>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeReportModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Report</button>
            </div>
            <input type="hidden" name="id" value="${report.id}">
        </form>
    `;
    
    return html;
}

function viewReportDetails(reportId, editMode = false) {
    currentReportId = reportId;
    currentEditMode = editMode;
    const modal = document.getElementById('reportModal');
    const content = document.getElementById('reportModalContent');
    
    // Update modal title
    const modalTitle = modal.querySelector('h2');
    if (modalTitle) {
        modalTitle.textContent = editMode ? 'Edit Report' : 'Report Details';
    }
    
    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset content
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            <p style="margin-top: 15px; color: #64748b;">Loading report details...</p>
        </div>
    `;
    
    // Fetch report details
    const apiUrl = '../api/get-report-details.php?id=' + reportId;
    
    fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Server did not return JSON");
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (editMode) {
                    content.innerHTML = formatReportDetailsEditable(data.report);
                } else {
                    content.innerHTML = formatReportDetails(data.report);
                }
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #ef4444;">
                        <p style="font-size: 16px;">Error loading report details</p>
                        <p style="color: #64748b; font-size: 14px; margin-top: 10px;">${data.message || 'Unknown error'}</p>
                        <button onclick="closeReportModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    <p style="font-size: 16px;">Error loading report details</p>
                    <p style="color: #64748b; font-size: 14px; margin-top: 10px;">${error.message || 'Network error. Please try again.'}</p>
                    <button onclick="closeReportModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
                </div>
            `;
        });
}

function editReportDetails(reportId) {
    viewReportDetails(reportId, true);
}

// Update report function
function updateReport(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = {};
    
    // Convert FormData to object
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Get the submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;
    
    // Send update request
    fetch('../api/update-report.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showNotification('Report updated successfully', 'success');
            // Close modal and refresh the view
            setTimeout(() => {
                closeReportModal();
                location.reload();
            }, 1000);
        } else {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            alert('Error updating report: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        console.error('Update error:', error);
        alert('Error updating report. Please try again.');
    });
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReportModal();
    }
});

// Toggle action dropdown
function toggleActionDropdown(reportId) {
    const menu = document.getElementById('action-menu-' + reportId);
    const allMenus = document.querySelectorAll('.action-dropdown-menu');
    
    // Close all other dropdowns
    allMenus.forEach(m => {
        if (m.id !== 'action-menu-' + reportId) {
            m.style.display = 'none';
        }
    });
    
    // Toggle current dropdown
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

// Delete report function
function deleteReport(event, reportId, reportIdText) {
    if (!confirm(`Are you sure you want to delete report "${reportIdText}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const button = event.target.closest('.action-menu-item');
    const originalText = button.innerHTML;
    button.innerHTML = '<span style="margin-right: 8px;">⏳</span> Deleting...';
    button.style.pointerEvents = 'none';
    
    // Find the table row
    const row = event.target.closest('tr');
    
    // Close dropdown
    document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    
    // Send delete request
    fetch('../api/delete-report.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: reportId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the row from the table
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    
                    // Show success message
                    showNotification('Report deleted successfully', 'success');
                    
                    // Reload page if no reports left
                    const remainingRows = document.querySelectorAll('tbody tr');
                    if (remainingRows.length === 0) {
                        setTimeout(() => location.reload(), 500);
                    }
                }, 300);
            } else {
                showNotification('Report deleted successfully', 'success');
                setTimeout(() => location.reload(), 500);
            }
        } else {
            button.innerHTML = originalText;
            button.style.pointerEvents = 'auto';
            alert('Error deleting report: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        button.innerHTML = originalText;
        button.style.pointerEvents = 'auto';
        console.error('Delete error:', error);
        alert('Error deleting report. Please try again.');
    });
}

// Simple notification function
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10001;
        font-size: 14px;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations for notifications
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}
</script>
<?php require_once '../includes/footer.php'; ?>

