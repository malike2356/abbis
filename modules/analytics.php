<?php
/**
 * Advanced Analytics Dashboard - Looker Studio Style
 * Comprehensive business intelligence and analytics
 */
$page_title = 'Advanced Analytics - Business Intelligence';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$pdo = getDBConnection();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$groupBy = $_GET['group_by'] ?? 'month';
$selectedRig = $_GET['rig_id'] ?? '';
$selectedClient = $_GET['client_id'] ?? '';
$selectedJobType = $_GET['job_type'] ?? '';
$activeTab = $_GET['type'] ?? 'overview'; // Handle type parameter for direct tab access

// Get filter options
$rigs = $pdo->query("SELECT id, rig_name FROM rigs WHERE status = 'active' ORDER BY rig_name")->fetchAll();
$clients = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name LIMIT 100")->fetchAll();
$jobTypes = ['direct' => 'Direct', 'subcontract' => 'Subcontract'];

// Set AI Assistant context for analytics (before header is included)
// Note: Header will use these variables when including the AI assistant panel
$aiContext = [
    'entity_type' => 'analytics_dashboard',
    'entity_id' => '',
    'entity_label' => 'Analytics Dashboard' . ($activeTab ? ' · ' . strtoupper($activeTab) : ''),
];
$aiQuickPrompts = [
    'Summarise performance trends this period',
    'Highlight anomalies that need investigation',
    'Recommend actions to improve utilisation',
];

require_once '../includes/header.php';
?>

<style>
    /* Advanced Analytics Styles */
    .analytics-container {
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    
    .filter-panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 150px;
    }
    
    .filter-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .chart-container {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        position: relative;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .chart-container:hover {
        box-shadow: 0 4px 16px rgba(0,0,0,0.1);
    }
    
    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 2px solid var(--border);
    }
    
    .chart-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .chart-actions {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon-small {
        padding: 6px 10px;
        border: 1px solid var(--border);
        background: var(--bg);
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s ease;
    }
    
    .btn-icon-small:hover {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }
    
    .metrics-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    
    .metric-card {
        background: linear-gradient(135deg, var(--card) 0%, rgba(14,165,233,0.03) 100%);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 16px;
        position: relative;
        overflow: hidden;
    }
    
    .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
    }
    
    .metric-label {
        font-size: 12px;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    
    .metric-value {
        font-size: 24px;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 4px;
    }
    
    .metric-change {
        font-size: 11px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    
    .metric-change.positive {
        color: var(--success);
    }
    
    .metric-change.negative {
        color: var(--danger);
    }
    
    .chart-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
        gap: 24px;
    }
    
    .chart-grid.full-width {
        grid-template-columns: 1fr;
    }
    
    .tabs {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid var(--border);
        margin-bottom: 24px;
        overflow-x: auto;
    }
    
    .tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        border-bottom: 3px solid transparent;
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .tab:hover {
        color: var(--primary);
        background: rgba(14,165,233,0.05);
    }
    
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
        background: rgba(14,165,233,0.05);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .loading-spinner {
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 40px;
        color: var(--secondary);
    }
    
    .export-options {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }
</style>

<div class="page-header">
    <div>
        <h1>📊 Advanced Analytics & Business Intelligence</h1>
        <p>Comprehensive data visualization and insights powered by advanced analytics</p>
    </div>
    <div class="export-options">
        <button class="btn btn-primary" onclick="exportDashboard('pdf')">
            📄 Export PDF
        </button>
        <button class="btn btn-primary" onclick="exportDashboard('excel')">
            📊 Export Excel
        </button>
        <button class="btn btn-outline" onclick="refreshAllCharts()">
            🔄 Refresh Data
        </button>
    </div>
</div>

<div class="analytics-container">
    <!-- Advanced Filter Panel -->
    <div class="filter-panel">
        <form method="GET" id="filterForm" style="display: contents;">
            <div class="filter-group">
                <label class="filter-label">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?php echo e($startDate); ?>" onchange="this.form.submit()">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?php echo e($endDate); ?>" onchange="this.form.submit()">
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Group By</label>
                <select name="group_by" class="form-control" onchange="this.form.submit()">
                    <option value="day" <?php echo $groupBy === 'day' ? 'selected' : ''; ?>>Day</option>
                    <option value="week" <?php echo $groupBy === 'week' ? 'selected' : ''; ?>>Week</option>
                    <option value="month" <?php echo $groupBy === 'month' ? 'selected' : ''; ?>>Month</option>
                    <option value="quarter" <?php echo $groupBy === 'quarter' ? 'selected' : ''; ?>>Quarter</option>
                    <option value="year" <?php echo $groupBy === 'year' ? 'selected' : ''; ?>>Year</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Rig</label>
                <select name="rig_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Rigs</option>
                    <?php foreach ($rigs as $rig): ?>
                        <option value="<?php echo $rig['id']; ?>" <?php echo $selectedRig == $rig['id'] ? 'selected' : ''; ?>>
                            <?php echo e($rig['rig_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Client</label>
                <select name="client_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?php echo $client['id']; ?>" <?php echo $selectedClient == $client['id'] ? 'selected' : ''; ?>>
                            <?php echo e($client['client_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Job Type</label>
                <select name="job_type" class="form-control" onchange="this.form.submit()">
                    <option value="">All Types</option>
                    <?php foreach ($jobTypes as $key => $label): ?>
                        <option value="<?php echo $key; ?>" <?php echo $selectedJobType === $key ? 'selected' : ''; ?>>
                            <?php echo e($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">&nbsp;</label>
                <button type="button" class="btn btn-outline" onclick="resetFilters()">Reset Filters</button>
            </div>
        </form>
    </div>

    <!-- Quick Date Presets -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <button class="btn btn-sm" onclick="setDateRange('today')">Today</button>
        <button class="btn btn-sm" onclick="setDateRange('week')">This Week</button>
        <button class="btn btn-sm" onclick="setDateRange('month')">This Month</button>
        <button class="btn btn-sm" onclick="setDateRange('quarter')">This Quarter</button>
        <button class="btn btn-sm" onclick="setDateRange('year')">This Year</button>
        <button class="btn btn-sm" onclick="setDateRange('last_month')">Last Month</button>
        <button class="btn btn-sm" onclick="setDateRange('last_quarter')">Last Quarter</button>
        <button class="btn btn-sm" onclick="setDateRange('last_year')">Last Year</button>
    </div>

    <!-- Key Metrics -->
    <div id="metricsContainer" class="metrics-grid">
        <div class="loading-spinner">Loading metrics...</div>
    </div>

    <!-- Analytics Tabs -->
    <div class="tabs">
        <button class="tab <?php echo $activeTab === 'overview' ? 'active' : ''; ?>" onclick="switchTab('overview')">📊 Overview</button>
        <button class="tab <?php echo $activeTab === 'financial' ? 'active' : ''; ?>" onclick="switchTab('financial')">💰 Financial Analysis</button>
        <button class="tab <?php echo $activeTab === 'operational' ? 'active' : ''; ?>" onclick="switchTab('operational')">⚙️ Operational Metrics</button>
        <button class="tab <?php echo $activeTab === 'performance' ? 'active' : ''; ?>" onclick="switchTab('performance')">🏆 Performance Analysis</button>
        <button class="tab <?php echo $activeTab === 'pos' ? 'active' : ''; ?>" onclick="switchTab('pos')">🛒 POS System</button>
        <button class="tab <?php echo $activeTab === 'cms' ? 'active' : ''; ?>" onclick="switchTab('cms')">🌐 CMS & Ecommerce</button>
        <button class="tab <?php echo $activeTab === 'inventory' ? 'active' : ''; ?>" onclick="switchTab('inventory')">📦 Inventory</button>
        <button class="tab <?php echo $activeTab === 'accounting' ? 'active' : ''; ?>" onclick="switchTab('accounting')">📚 Accounting</button>
        <button class="tab <?php echo $activeTab === 'crm' ? 'active' : ''; ?>" onclick="switchTab('crm')">👥 CRM</button>
        <button class="tab <?php echo $activeTab === 'forecast' ? 'active' : ''; ?>" onclick="switchTab('forecast')">🔮 Forecast & Trends</button>
    </div>

    <!-- Overview Tab -->
    <div id="overview-tab" class="tab-content <?php echo $activeTab === 'overview' ? 'active' : ''; ?>">
        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📈 Revenue & Profit Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('revenueTrend')" title="Export">💾</button>
                        <button class="btn-icon-small" onclick="toggleFullscreen('revenueTrend')" title="Fullscreen">⛶</button>
                    </div>
                </div>
                <canvas id="revenueTrendChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📊 Jobs vs Expenses</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('jobsExpenses')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="jobsExpensesChart" height="80"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🚛 Rig Performance Comparison</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('rigPerformance')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="rigPerformanceChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">👥 Top Workers by Earnings</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('workerEarnings')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="workerEarningsChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Financial Analysis Tab -->
    <div id="financial-tab" class="tab-content <?php echo $activeTab === 'financial' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">💰 Comprehensive Financial Breakdown</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('financialBreakdown')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="financialBreakdownChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">💵 Income vs Expenses</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('incomeExpenses')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="incomeExpensesChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🏢 Client Revenue Analysis</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('clientRevenue')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="clientRevenueChart" height="80"></canvas>
            </div>
        </div>

        <div class="chart-container">
            <div class="chart-header">
                <h3 class="chart-title">📋 Job Type Profitability</h3>
                <div class="chart-actions">
                    <button class="btn-icon-small" onclick="exportChart('jobTypeProfit')" title="Export">💾</button>
                </div>
            </div>
            <canvas id="jobTypeProfitChart" height="80"></canvas>
        </div>
    </div>

    <!-- Operational Metrics Tab -->
    <div id="operational-tab" class="tab-content <?php echo $activeTab === 'operational' ? 'active' : ''; ?>">
        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">⏱️ Average Job Duration Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('jobDuration')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="jobDurationChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📏 Depth Analysis</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('depthAnalysis')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="depthAnalysisChart" height="80"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📦 Materials Usage by Type</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('materialsUsage')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="materialsUsageChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🗺️ Regional Performance</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('regionalPerformance')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="regionalPerformanceChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Performance Analysis Tab -->
    <div id="performance-tab" class="tab-content <?php echo $activeTab === 'performance' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🎯 Comparative Analysis: Current vs Previous Period</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('comparative')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="comparativeChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📊 Profit Margin by Rig</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('rigMargin')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="rigMarginChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">⚡ Efficiency Metrics</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('efficiency')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="efficiencyChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- POS System Tab -->
    <div id="pos-tab" class="tab-content <?php echo $activeTab === 'pos' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">💰 POS Sales Revenue Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('posSalesTrend')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="posSalesTrendChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">💳 Payment Methods Breakdown</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('posPaymentMethods')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="posPaymentMethodsChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🏪 Store Performance</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('posStorePerformance')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="posStorePerformanceChart" height="80"></canvas>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🛍️ Top Selling Products</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('posTopProducts')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="posTopProductsChart" height="80"></canvas>
            </div>

            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">👤 Cashier Performance</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('posCashierPerformance')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="posCashierPerformanceChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- CMS & Ecommerce Tab -->
    <div id="cms-tab" class="tab-content <?php echo $activeTab === 'cms' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🛒 CMS Orders Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('cmsOrdersTrend')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="cmsOrdersTrendChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📋 Quote Requests Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('cmsQuoteRequests')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="cmsQuoteRequestsChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Inventory Tab -->
    <div id="inventory-tab" class="tab-content <?php echo $activeTab === 'inventory' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📊 Inventory Value Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('inventoryValueTrend')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="inventoryValueTrendChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🔧 Material Usage by Type</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('inventoryMaterialUsage')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="inventoryMaterialUsageChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- Accounting Tab -->
    <div id="accounting-tab" class="tab-content <?php echo $activeTab === 'accounting' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📝 Journal Entries Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('accountingJournalEntries')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="accountingJournalEntriesChart" height="100"></canvas>
            </div>
        </div>

        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">💼 Account Balances by Type</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('accountingAccountBalances')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="accountingAccountBalancesChart" height="80"></canvas>
            </div>
        </div>
    </div>

    <!-- CRM Tab -->
    <div id="crm-tab" class="tab-content <?php echo $activeTab === 'crm' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">📞 CRM Follow-ups Trend</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('crmFollowups')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="crmFollowupsChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Forecast & Trends Tab -->
    <div id="forecast-tab" class="tab-content <?php echo $activeTab === 'forecast' ? 'active' : ''; ?>">
        <div class="chart-grid full-width">
            <div class="chart-container">
                <div class="chart-header">
                    <h3 class="chart-title">🔮 Profit Forecast (Next 3 Periods)</h3>
                    <div class="chart-actions">
                        <button class="btn-icon-small" onclick="exportChart('forecast')" title="Export">💾</button>
                    </div>
                </div>
                <canvas id="forecastChart" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Load Chart.js first -->
<script>
    window.chartJsLoaded = false;
    window.analyticsJsLoaded = false;
    window.analyticsInitializing = false;
    window.analyticsInitialized = false;
    
    // Define initializeAnalytics function early so it's available when checkAndInitialize is called
    window.initializeAnalytics = function() {
        // Prevent multiple initializations
        if (window.analyticsInitialized || window.analyticsInitializing) {
            console.log('Analytics already initialized or initializing, skipping...');
            return;
        }
        
        window.analyticsInitializing = true;
        
        // Check if Chart.js loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            window.analyticsInitializing = false;
            return;
        }
        
        // Check if AdvancedAnalytics class is available
        if (typeof window.AdvancedAnalytics === 'undefined') {
            console.error('AdvancedAnalytics class is not available');
            window.analyticsInitializing = false;
            return;
        }
        
        try {
            // Get parameters from URL or use defaults
            const urlParams = new URLSearchParams(window.location.search);
            const startDate = urlParams.get('start_date') || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
            const endDate = urlParams.get('end_date') || new Date().toISOString().split('T')[0];
            const groupBy = urlParams.get('group_by') || 'month';
            const rigId = urlParams.get('rig_id') || '';
            const clientId = urlParams.get('client_id') || '';
            const jobType = urlParams.get('job_type') || '';
            
            // Initialize analytics
            window.analytics = new window.AdvancedAnalytics({
                startDate: startDate,
                endDate: endDate,
                groupBy: groupBy,
                rigId: rigId,
                clientId: clientId,
                jobType: jobType
            });
            
            // Initialize and load data
            window.analytics.init().then(() => {
                window.analyticsInitialized = true;
                window.analyticsInitializing = false;
                console.log('Analytics initialized successfully');
            }).catch(error => {
                console.error('Error initializing analytics:', error);
                window.analyticsInitializing = false;
                const container = document.getElementById('metricsContainer');
                if (container) {
                    container.innerHTML = 
                        '<div class="loading-spinner" style="color: var(--danger);">⚠️ Error: ' + 
                        (error.message || 'Failed to initialize analytics') + '. Please refresh the page.</div>';
                }
            });
        } catch (error) {
            console.error('Error in initializeAnalytics:', error);
            window.analyticsInitializing = false;
            const container = document.getElementById('metricsContainer');
            if (container) {
                container.innerHTML = 
                    '<div class="loading-spinner" style="color: var(--danger);">⚠️ Error: ' + 
                    (error.message || 'Failed to initialize analytics') + '. Please refresh the page.</div>';
            }
        }
    }
    
    window.checkAndInitialize = function() {
        if (window.analyticsInitialized || window.analyticsInitializing) {
            return; // Already initialized or in progress
        }
        
        if (window.chartJsLoaded && window.analyticsJsLoaded && typeof Chart !== 'undefined' && typeof window.AdvancedAnalytics !== 'undefined') {
            if (typeof window.initializeAnalytics === 'function') {
                window.initializeAnalytics();
            } else {
                console.error('initializeAnalytics function not found');
            }
        }
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js" 
        onload="window.chartJsLoaded = true; console.log('Chart.js loaded'); checkAndInitialize();"
        onerror="console.error('Failed to load Chart.js'); window.chartJsLoaded = false; document.getElementById('metricsContainer').innerHTML = '<div class=\"loading-spinner\" style=\"color: var(--danger);\">⚠️ Error: Chart.js library failed to load. Please check your internet connection and refresh the page.</div>';">
</script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"
        onload="console.log('Chart.js adapter loaded');"
        onerror="console.error('Failed to load Chart.js adapter');">
</script>

<!-- Load analytics script with error handling -->
<script src="../assets/js/advanced-analytics.js?v=<?php echo time(); ?>"
        onload="window.analyticsJsLoaded = true; console.log('Advanced Analytics JS loaded'); checkAndInitialize();"
        onerror="console.error('Failed to load advanced-analytics.js'); window.analyticsJsLoaded = false; document.getElementById('metricsContainer').innerHTML = '<div class=\"loading-spinner\" style=\"color: var(--danger);\">⚠️ Error: Analytics script failed to load. Please check the file exists and refresh the page.</div>';">
</script>

<script>
    // Override initializeAnalytics to use PHP variables when available
    (function() {
        const originalInit = window.initializeAnalytics || function() {};
        window.initializeAnalytics = function() {
            // Prevent multiple initializations
            if (window.analyticsInitialized || window.analyticsInitializing) {
                console.log('Analytics already initialized or initializing, skipping...');
                return;
            }
            
            window.analyticsInitializing = true;
            
            // Check if Chart.js loaded
            if (typeof Chart === 'undefined') {
                window.analyticsInitializing = false;
                console.error('Chart.js not loaded!');
                const errorMsg = 'Chart.js library not loaded. ' + 
                               (navigator.onLine ? 'Please check your internet connection and refresh the page.' : 'You are offline. Please check your internet connection.');
                const container = document.getElementById('metricsContainer');
                if (container) {
                    container.innerHTML = '<div class="loading-spinner" style="color: var(--danger);">⚠️ ' + errorMsg + '</div>';
                }
                return;
            }
            
            // Check if AdvancedAnalytics class loaded
            if (typeof window.AdvancedAnalytics === 'undefined') {
                window.analyticsInitializing = false;
                console.error('AdvancedAnalytics class not found!');
                const container = document.getElementById('metricsContainer');
                if (container) {
                    container.innerHTML = '<div class="loading-spinner" style="color: var(--danger);">⚠️ Analytics JavaScript not loaded. Please check browser console for errors and refresh the page.</div>';
                }
                return;
            }
            
            try {
                // Use PHP variables (they're always set, but may be empty strings)
                const phpStartDate = '<?php echo $startDate; ?>';
                const phpEndDate = '<?php echo $endDate; ?>';
                const phpGroupBy = '<?php echo $groupBy; ?>';
                const phpRigId = '<?php echo $selectedRig; ?>';
                const phpClientId = '<?php echo $selectedClient; ?>';
                const phpJobType = '<?php echo $selectedJobType; ?>';
                
                // Get URL parameters as fallback
                const urlParams = new URLSearchParams(window.location.search);
                
                // Use PHP values if they exist and are not empty, otherwise use URL params or defaults
                const startDate = phpStartDate || urlParams.get('start_date') || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
                const endDate = phpEndDate || urlParams.get('end_date') || new Date().toISOString().split('T')[0];
                const groupBy = phpGroupBy || urlParams.get('group_by') || 'month';
                const rigId = phpRigId || urlParams.get('rig_id') || '';
                const clientId = phpClientId || urlParams.get('client_id') || '';
                const jobType = phpJobType || urlParams.get('job_type') || '';
                
                // Create analytics instance
                window.analytics = new window.AdvancedAnalytics({
                    startDate: startDate,
                    endDate: endDate,
                    groupBy: groupBy,
                    rigId: rigId,
                    clientId: clientId,
                    jobType: jobType
                });
                
                // Initialize and load tab data based on active tab
                const activeTab = '<?php echo isset($activeTab) ? $activeTab : "overview"; ?>';
                
                window.analytics.init().then(() => {
                    console.log('Analytics initialized successfully');
                    window.analyticsInitialized = true;
                    window.analyticsInitializing = false;
                    if (activeTab && activeTab !== 'overview' && typeof window.analytics.loadTabData === 'function') {
                        window.analytics.loadTabData(activeTab);
                    }
                }).catch(error => {
                    window.analyticsInitializing = false;
                    console.error('Error initializing analytics:', error);
                    console.error('Error stack:', error.stack);
                    const container = document.getElementById('metricsContainer');
                    if (container) {
                        container.innerHTML = 
                            '<div class="loading-spinner" style="color: var(--danger);">⚠️ Error loading analytics: ' + 
                            (error.message || 'Unknown error') + '. Please refresh the page or check browser console.</div>';
                    }
                });
            } catch (error) {
                window.analyticsInitializing = false;
                console.error('Error creating analytics instance:', error);
                console.error('Error stack:', error.stack);
                const container = document.getElementById('metricsContainer');
                if (container) {
                    container.innerHTML = 
                        '<div class="loading-spinner" style="color: var(--danger);">⚠️ Error: ' + 
                        (error.message || 'Failed to initialize analytics') + '. Please refresh the page.</div>';
                }
            }
        };
    })();
    
    // Try multiple initialization strategies
    if (document.readyState === 'loading') {
        // DOM is still loading
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for scripts to load
            setTimeout(initializeAnalytics, 100);
        });
    } else {
        // DOM is already loaded
        setTimeout(initializeAnalytics, 100);
    }
    
    // Fallback: try again after delays if still not initialized
    let retryCount = 0;
    const maxRetries = 5;
    const retryInterval = setInterval(function() {
        retryCount++;
        if (window.analytics) {
            clearInterval(retryInterval);
            return;
        }
        
        if (typeof Chart !== 'undefined' && typeof window.AdvancedAnalytics !== 'undefined') {
            console.log('Retrying analytics initialization (attempt ' + retryCount + ')...');
            initializeAnalytics();
            clearInterval(retryInterval);
        } else if (retryCount >= maxRetries) {
            clearInterval(retryInterval);
            console.error('Failed to initialize analytics after ' + maxRetries + ' attempts');
            if (!window.analytics) {
                document.getElementById('metricsContainer').innerHTML = 
                    '<div class="loading-spinner" style="color: var(--danger);">⚠️ Failed to initialize analytics. Chart.js: ' + 
                    (typeof Chart !== 'undefined' ? '✓' : '✗') + 
                    ', AdvancedAnalytics: ' + 
                    (typeof window.AdvancedAnalytics !== 'undefined' ? '✓' : '✗') + 
                    '. Please refresh the page.</div>';
            }
        }
    }, 500);
</script>

<script>
    // Make all functions globally accessible for onclick handlers
    window.switchTab = function(tabName) {
        // Update URL without reload
        const url = new URL(window.location);
        url.searchParams.set('type', tabName);
        window.history.pushState({}, '', url);
        
        // Update UI
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        // Find and activate the clicked tab
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            const tabText = tab.textContent.toLowerCase();
            const tabMap = {
                'overview': 'overview',
                'financial': 'financial',
                'operational': 'operational',
                'performance': 'performance',
                'pos': 'pos',
                'cms': 'cms',
                'inventory': 'inventory',
                'accounting': 'accounting',
                'crm': 'crm',
                'forecast': 'forecast'
            };
            const expectedText = tabMap[tabName] || '';
            if (tabText.includes(expectedText)) {
                tab.classList.add('active');
            }
        });
        
        const tabContent = document.getElementById(tabName + '-tab');
        if (tabContent) {
            tabContent.classList.add('active');
        }
        
        // Load data for active tab if not already loaded
        if (window.analytics) {
            window.analytics.loadTabData(tabName);
        }
    }

    window.resetFilters = function() {
        window.location.href = 'analytics.php';
    }

    window.setDateRange = function(range) {
        const today = new Date();
        let start, end;
        
        switch(range) {
            case 'today':
                start = end = today.toISOString().split('T')[0];
                break;
            case 'week':
                const weekStart = new Date(today);
                weekStart.setDate(today.getDate() - today.getDay());
                start = weekStart.toISOString().split('T')[0];
                end = today.toISOString().split('T')[0];
                break;
            case 'month':
                start = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
                end = today.toISOString().split('T')[0];
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                start = new Date(today.getFullYear(), quarter * 3, 1).toISOString().split('T')[0];
                end = today.toISOString().split('T')[0];
                break;
            case 'year':
                start = new Date(today.getFullYear(), 0, 1).toISOString().split('T')[0];
                end = today.toISOString().split('T')[0];
                break;
            case 'last_month':
                const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                start = lastMonth.toISOString().split('T')[0];
                end = new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0];
                break;
            case 'last_quarter':
                const lastQuarter = Math.floor((today.getMonth() - 3) / 3);
                const lastQuarterYear = lastQuarter < 0 ? today.getFullYear() - 1 : today.getFullYear();
                const lastQuarterMonth = lastQuarter < 0 ? 9 : lastQuarter * 3;
                start = new Date(lastQuarterYear, lastQuarterMonth, 1).toISOString().split('T')[0];
                end = new Date(today.getFullYear(), today.getMonth(), 0).toISOString().split('T')[0];
                break;
            case 'last_year':
                start = new Date(today.getFullYear() - 1, 0, 1).toISOString().split('T')[0];
                end = new Date(today.getFullYear() - 1, 11, 31).toISOString().split('T')[0];
                break;
        }
        
        const startInput = document.querySelector('input[name="start_date"]');
        const endInput = document.querySelector('input[name="end_date"]');
        const filterForm = document.getElementById('filterForm');
        
        if (startInput) startInput.value = start;
        if (endInput) endInput.value = end;
        if (filterForm) filterForm.submit();
    }

    window.exportChart = function(chartId) {
        if (window.analytics) {
            window.analytics.exportChart(chartId);
        }
    }

    window.toggleFullscreen = function(chartId) {
        if (window.analytics) {
            window.analytics.toggleFullscreen(chartId);
        }
    }

    window.refreshAllCharts = function() {
        if (window.analytics) {
            window.analytics.refreshAll();
        }
    }

    window.exportDashboard = function(format) {
        if (window.analytics) {
            window.analytics.exportDashboard(format);
        }
    }
</script>

<?php
// Note: AI assistant panel is already included in header.php for all pages
// The context variables were set before the header was included (around line 33-44),
// so the panel will use the correct context for the analytics dashboard.

require_once '../includes/footer.php';
?>
