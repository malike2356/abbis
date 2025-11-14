/**
 * Advanced Analytics Engine - Looker Studio Style
 * Comprehensive data visualization and business intelligence
 */
(function() {
    'use strict';
    
    // Wait for Chart.js if needed (for cases where this script loads first)
    function waitForChartJs(callback, maxAttempts = 10) {
        let attempts = 0;
        const checkInterval = setInterval(function() {
            attempts++;
            if (typeof Chart !== 'undefined') {
                clearInterval(checkInterval);
                callback();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
                console.warn('Chart.js not found after waiting, analytics may not work properly');
                callback(); // Still proceed, initialization will handle the error
            }
        }, 100);
    }
    
class AdvancedAnalytics {
    constructor(options = {}) {
        this.config = {
            startDate: options.startDate || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
            endDate: options.endDate || new Date().toISOString().split('T')[0],
            groupBy: options.groupBy || 'month',
            rigId: options.rigId || '',
            clientId: options.clientId || '',
            jobType: options.jobType || '',
            apiUrl: '../api/analytics-api.php'
        };
        
        this.charts = new Map();
        this.dataCache = new Map();
        this.colors = {
            primary: '#0ea5e9',
            success: '#10b981',
            warning: '#f59e0b',
            danger: '#ef4444',
            purple: '#8b5cf6',
            teal: '#14b8a6',
            orange: '#f97316',
            pink: '#ec4899'
        };
    }

    async init() {
        try {
            await this.loadMetrics();
            await this.loadOverviewCharts();
        } catch (error) {
            console.error('Error in analytics initialization:', error);
            throw error;
        }
    }

    async loadMetrics() {
        const container = document.getElementById('metricsContainer');
        if (!container) {
            console.error('Metrics container not found');
            return;
        }
        
        try {
            const apiUrl = `${this.config.apiUrl}?type=financial_overview&${this.getParams()}`;
            console.log('Fetching metrics from:', apiUrl);
            
            const response = await fetch(apiUrl);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                const text = await response.text();
                throw new Error(`Invalid response format. Expected JSON, got: ${contentType}. Response: ${text.substring(0, 200)}`);
            }
            
            const result = await response.json();
            console.log('Metrics API response:', result);
            
            if (!result.success) {
                throw new Error(result.message || 'API returned unsuccessful response');
            }
            
            const data = result.data || {};
            
            // Ensure all numeric values are properly converted to numbers and handle NaN
            const profitMargin = parseFloat(data.profit_margin);
            const profitMarginValue = (isNaN(profitMargin) ? 0 : profitMargin);
            const totalRevenue = parseFloat(data.total_revenue);
            const totalRevenueValue = (isNaN(totalRevenue) ? 0 : totalRevenue);
            const totalProfit = parseFloat(data.total_profit);
            const totalProfitValue = (isNaN(totalProfit) ? 0 : totalProfit);
            const totalJobs = parseInt(data.total_jobs);
            const totalJobsValue = (isNaN(totalJobs) ? 0 : totalJobs);
            const avgProfitPerJob = parseFloat(data.avg_profit_per_job);
            const avgProfitPerJobValue = (isNaN(avgProfitPerJob) ? 0 : avgProfitPerJob);
            const totalExpenses = parseFloat(data.total_expenses);
            const totalExpensesValue = (isNaN(totalExpenses) ? 0 : totalExpenses);
            
            // Also get comparative data (but don't fail if it fails)
            let compResult = { success: false, data: { changes: {} } };
            try {
                const compResponse = await fetch(`${this.config.apiUrl}?type=comparative_analysis&${this.getParams()}`);
                if (compResponse.ok) {
                    compResult = await compResponse.json();
                }
            } catch (compError) {
                console.warn('Failed to load comparative data:', compError);
            }
            
            const metrics = [
                {
                    label: 'Total Revenue',
                    value: this.formatCurrency(totalRevenueValue),
                    change: compResult.success && compResult.data && compResult.data.changes ? compResult.data.changes.revenue_change : null,
                    icon: '💰'
                },
                {
                    label: 'Net Profit',
                    value: this.formatCurrency(totalProfitValue),
                    change: compResult.success && compResult.data && compResult.data.changes ? compResult.data.changes.profit_change : null,
                    icon: '📈'
                },
                {
                    label: 'Total Jobs',
                    value: this.formatNumber(totalJobsValue),
                    change: compResult.success && compResult.data && compResult.data.changes ? compResult.data.changes.jobs_change : null,
                    icon: '📊'
                },
                {
                    label: 'Profit Margin',
                    value: profitMarginValue.toFixed(2) + '%',
                    change: null,
                    icon: '💎'
                },
                {
                    label: 'Avg Profit/Job',
                    value: this.formatCurrency(avgProfitPerJobValue),
                    change: null,
                    icon: '🎯'
                },
                {
                    label: 'Total Expenses',
                    value: this.formatCurrency(totalExpensesValue),
                    change: null,
                    icon: '💸'
                }
            ];
            
            this.renderMetrics(metrics);
        } catch (error) {
            console.error('Error loading metrics:', error);
            console.error('Error stack:', error.stack);
            const errorMsg = error.message || 'Unknown error occurred';
            container.innerHTML = `
                <div class="loading-spinner" style="color: var(--danger); padding: 20px; text-align: center;">
                    <div style="font-size: 24px; margin-bottom: 10px;">⚠️</div>
                    <div style="font-weight: 600; margin-bottom: 5px;">Error loading metrics</div>
                    <div style="font-size: 12px; color: var(--secondary);">${errorMsg}</div>
                    <button onclick="location.reload()" style="margin-top: 15px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer;">
                        Retry
                    </button>
                </div>
            `;
        }
    }

    renderMetrics(metrics) {
        const container = document.getElementById('metricsContainer');
        container.innerHTML = metrics.map(metric => `
            <div class="metric-card">
                <div class="metric-label">${metric.label}</div>
                <div class="metric-value">${metric.value}</div>
                ${metric.change !== null ? `
                    <div class="metric-change ${metric.change >= 0 ? 'positive' : 'negative'}">
                        ${metric.change >= 0 ? '↑' : '↓'} ${Math.abs(metric.change).toFixed(1)}%
                    </div>
                ` : ''}
            </div>
        `).join('');
    }

    async loadOverviewCharts() {
        await Promise.all([
            this.loadRevenueTrendChart(),
            this.loadJobsExpensesChart(),
            this.loadRigPerformanceChart(),
            this.loadWorkerEarningsChart()
        ]);
    }

    async loadTabData(tabName) {
        switch(tabName) {
            case 'financial':
                await this.loadFinancialCharts();
                break;
            case 'operational':
                await this.loadOperationalCharts();
                break;
            case 'performance':
                await this.loadPerformanceCharts();
                break;
            case 'pos':
                await this.loadPosCharts();
                break;
            case 'cms':
                await this.loadCmsCharts();
                break;
            case 'inventory':
                await this.loadInventoryCharts();
                break;
            case 'accounting':
                await this.loadAccountingCharts();
                break;
            case 'crm':
                await this.loadCrmCharts();
                break;
            case 'forecast':
                await this.loadForecastChart();
                break;
        }
    }

    async loadRevenueTrendChart() {
        try {
            const data = await this.fetchData('time_series');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const revenue = data.map(item => parseFloat(item.revenue || 0));
            const profit = data.map(item => parseFloat(item.profit || 0));
            const expenses = data.map(item => parseFloat(item.expenses || 0));

            const ctx = document.getElementById('revenueTrendChart');
            if (!ctx) return;

            if (this.charts.has('revenueTrend')) {
                this.charts.get('revenueTrend').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: revenue,
                            borderColor: this.colors.primary,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Profit',
                            data: profit,
                            borderColor: this.colors.success,
                            backgroundColor: this.alphaColor(this.colors.success, 0.1),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Expenses',
                            data: expenses,
                            borderColor: this.colors.danger,
                            backgroundColor: this.alphaColor(this.colors.danger, 0.1),
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: (context) => {
                                    return context.dataset.label + ': GHS ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    }
                }
            });

            this.charts.set('revenueTrend', chart);
        } catch (error) {
            console.error('Error loading revenue trend:', error);
        }
    }

    async loadJobsExpensesChart() {
        try {
            const data = await this.fetchData('time_series');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const jobs = data.map(item => parseInt(item.job_count || 0));
            const expenses = data.map(item => parseFloat(item.expenses || 0));

            const ctx = document.getElementById('jobsExpensesChart');
            if (!ctx) return;

            if (this.charts.has('jobsExpenses')) {
                this.charts.get('jobsExpenses').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Jobs Completed',
                            data: jobs,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.7),
                            borderColor: this.colors.primary,
                            borderWidth: 1,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Expenses (GHS)',
                            data: expenses,
                            type: 'line',
                            borderColor: this.colors.warning,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.1),
                            borderWidth: 3,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jobs'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Expenses (GHS)'
                            },
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            this.charts.set('jobsExpenses', chart);
        } catch (error) {
            console.error('Error loading jobs/expenses chart:', error);
        }
    }

    async loadRigPerformanceChart() {
        try {
            const data = await this.fetchData('rig_performance');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.rig_name || 'Unknown');
            const profits = data.map(item => parseFloat(item.total_profit || 0));
            const jobs = data.map(item => parseInt(item.job_count || 0));

            const ctx = document.getElementById('rigPerformanceChart');
            if (!ctx) return;

            if (this.charts.has('rigPerformance')) {
                this.charts.get('rigPerformance').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Profit (GHS)',
                            data: profits,
                            backgroundColor: this.generateColors(labels.length, 0.7)
                        },
                        {
                            label: 'Jobs',
                            data: jobs,
                            type: 'line',
                            borderColor: this.colors.success,
                            backgroundColor: this.colors.success,
                            borderWidth: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    if (context.datasetIndex === 0) {
                                        return 'Profit: GHS ' + context.parsed.y.toLocaleString();
                                    }
                                    return 'Jobs: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('rigPerformance', chart);
        } catch (error) {
            console.error('Error loading rig performance:', error);
        }
    }

    async loadWorkerEarningsChart() {
        try {
            const data = await this.fetchData('worker_productivity');
            if (!data || data.length === 0) return;

            const top10 = data.slice(0, 10);
            const labels = top10.map(item => item.worker_name);
            const earnings = top10.map(item => parseFloat(item.total_earnings || 0));

            const ctx = document.getElementById('workerEarningsChart');
            if (!ctx) return;

            if (this.charts.has('workerEarnings')) {
                this.charts.get('workerEarnings').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Earnings (GHS)',
                        data: earnings,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => 'GHS ' + context.parsed.x.toLocaleString()
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('workerEarnings', chart);
        } catch (error) {
            console.error('Error loading worker earnings:', error);
        }
    }

    async loadFinancialCharts() {
        await Promise.all([
            this.loadFinancialBreakdownChart(),
            this.loadIncomeExpensesChart(),
            this.loadClientRevenueChart(),
            this.loadJobTypeProfitChart()
        ]);
    }

    async loadFinancialBreakdownChart() {
        try {
            const data = await this.fetchData('financial_overview');
            if (!data) return;

            const ctx = document.getElementById('financialBreakdownChart');
            if (!ctx) return;

            if (this.charts.has('financialBreakdown')) {
                this.charts.get('financialBreakdown').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Revenue', 'Expenses', 'Profit', 'Wages', 'Materials Cost', 'Deposits'],
                    datasets: [{
                        label: 'Amount (GHS)',
                        data: [
                            parseFloat(data.total_revenue || 0),
                            parseFloat(data.total_expenses || 0),
                            parseFloat(data.total_profit || 0),
                            parseFloat(data.total_wages || 0),
                            parseFloat(data.total_materials_cost || 0),
                            parseFloat(data.total_deposits || 0)
                        ],
                        backgroundColor: [
                            this.colors.success,
                            this.colors.danger,
                            this.colors.primary,
                            this.colors.warning,
                            this.colors.orange,
                            this.colors.teal
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => 'GHS ' + context.parsed.y.toLocaleString()
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('financialBreakdown', chart);
        } catch (error) {
            console.error('Error loading financial breakdown:', error);
        }
    }

    async loadIncomeExpensesChart() {
        try {
            const data = await this.fetchData('time_series');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const income = data.map(item => parseFloat(item.revenue || 0));
            const expenses = data.map(item => parseFloat(item.expenses || 0));

            const ctx = document.getElementById('incomeExpensesChart');
            if (!ctx) return;

            if (this.charts.has('incomeExpenses')) {
                this.charts.get('incomeExpenses').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Income',
                            data: income,
                            backgroundColor: this.alphaColor(this.colors.success, 0.7)
                        },
                        {
                            label: 'Expenses',
                            data: expenses,
                            backgroundColor: this.alphaColor(this.colors.danger, 0.7)
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.dataset.label + ': GHS ' + context.parsed.y.toLocaleString()
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('incomeExpenses', chart);
        } catch (error) {
            console.error('Error loading income/expenses:', error);
        }
    }

    async loadClientRevenueChart() {
        try {
            const data = await this.fetchData('client_analysis');
            if (!data || data.length === 0) return;

            const top10 = data.slice(0, 10);
            const labels = top10.map(item => item.client_name);
            const revenue = top10.map(item => parseFloat(item.total_revenue || 0));

            const ctx = document.getElementById('clientRevenueChart');
            if (!ctx) return;

            if (this.charts.has('clientRevenue')) {
                this.charts.get('clientRevenue').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: revenue,
                        backgroundColor: this.generateColors(labels.length, 0.8)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': GHS ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            this.charts.set('clientRevenue', chart);
        } catch (error) {
            console.error('Error loading client revenue:', error);
        }
    }

    async loadJobTypeProfitChart() {
        try {
            const data = await this.fetchData('job_type_analysis');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.job_type.charAt(0).toUpperCase() + item.job_type.slice(1));
            const profit = data.map(item => parseFloat(item.total_profit || 0));

            const ctx = document.getElementById('jobTypeProfitChart');
            if (!ctx) return;

            if (this.charts.has('jobTypeProfit')) {
                this.charts.get('jobTypeProfit').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: profit,
                        backgroundColor: [this.colors.primary, this.colors.success, this.colors.warning]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': GHS ' + context.parsed.toLocaleString() + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            this.charts.set('jobTypeProfit', chart);
        } catch (error) {
            console.error('Error loading job type profit:', error);
        }
    }

    async loadOperationalCharts() {
        // Load operational metrics charts
        await this.loadJobDurationChart();
        await this.loadDepthAnalysisChart();
        await this.loadMaterialsUsageChart();
        await this.loadRegionalPerformanceChart();
    }

    async loadJobDurationChart() {
        try {
            const data = await this.fetchData('time_series');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const durations = data.map(item => parseFloat(item.avg_duration || 0) / 60); // Convert to hours

            const ctx = document.getElementById('jobDurationChart');
            if (!ctx) return;

            if (this.charts.has('jobDuration')) {
                this.charts.get('jobDuration').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Avg Duration (Hours)',
                        data: durations,
                        borderColor: this.colors.primary,
                        backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.parsed.y.toFixed(2) + ' hours'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Hours'
                            }
                        }
                    }
                }
            });

            this.charts.set('jobDuration', chart);
        } catch (error) {
            console.error('Error loading job duration:', error);
        }
    }

    async loadDepthAnalysisChart() {
        try {
            const data = await this.fetchData('time_series');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const avgDepth = data.map(item => parseFloat(item.avg_depth || 0));
            const totalDepth = data.map(item => parseFloat(item.total_depth || 0));

            const ctx = document.getElementById('depthAnalysisChart');
            if (!ctx) return;

            if (this.charts.has('depthAnalysis')) {
                this.charts.get('depthAnalysis').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Avg Depth (m)',
                            data: avgDepth,
                            backgroundColor: this.alphaColor(this.colors.teal, 0.7),
                            yAxisID: 'y'
                        },
                        {
                            label: 'Total Depth (m)',
                            data: totalDepth,
                            type: 'line',
                            borderColor: this.colors.purple,
                            borderWidth: 3,
                            fill: false,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Avg Depth (m)'
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Depth (m)'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        }
                    }
                }
            });

            this.charts.set('depthAnalysis', chart);
        } catch (error) {
            console.error('Error loading depth analysis:', error);
        }
    }

    async loadMaterialsUsageChart() {
        try {
            const data = await this.fetchData('materials_analysis');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.materials_provided_by || 'Unknown');
            const screenPipes = data.map(item => parseInt(item.total_screen_pipes || 0));
            const plainPipes = data.map(item => parseInt(item.total_plain_pipes || 0));
            const gravel = data.map(item => parseInt(item.total_gravel || 0));

            const ctx = document.getElementById('materialsUsageChart');
            if (!ctx) return;

            if (this.charts.has('materialsUsage')) {
                this.charts.get('materialsUsage').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Screen Pipes',
                            data: screenPipes,
                            backgroundColor: this.colors.primary
                        },
                        {
                            label: 'Plain Pipes',
                            data: plainPipes,
                            backgroundColor: this.colors.success
                        },
                        {
                            label: 'Gravel',
                            data: gravel,
                            backgroundColor: this.colors.warning
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            this.charts.set('materialsUsage', chart);
        } catch (error) {
            console.error('Error loading materials usage:', error);
        }
    }

    async loadRegionalPerformanceChart() {
        try {
            const data = await this.fetchData('regional_analysis');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.region);
            const revenue = data.map(item => parseFloat(item.total_revenue || 0));

            const ctx = document.getElementById('regionalPerformanceChart');
            if (!ctx) return;

            if (this.charts.has('regionalPerformance')) {
                this.charts.get('regionalPerformance').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue (GHS)',
                        data: revenue,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => 'GHS ' + context.parsed.y.toLocaleString()
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('regionalPerformance', chart);
        } catch (error) {
            console.error('Error loading regional performance:', error);
        }
    }

    async loadPerformanceCharts() {
        await this.loadComparativeChart();
        await this.loadRigMarginChart();
        await this.loadEfficiencyChart();
    }

    async loadComparativeChart() {
        try {
            const result = await this.fetchData('comparative_analysis');
            if (!result || !result.success) return;
            
            const data = result.data || result;

            const current = data.current || {};
            const previous = data.previous || {};

            const ctx = document.getElementById('comparativeChart');
            if (!ctx) return;

            if (this.charts.has('comparative')) {
                this.charts.get('comparative').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Revenue', 'Profit', 'Jobs'],
                    datasets: [
                        {
                            label: 'Current Period',
                            data: [
                                parseFloat(current.revenue || 0),
                                parseFloat(current.profit || 0),
                                parseInt(current.jobs || 0)
                            ],
                            backgroundColor: this.colors.success
                        },
                        {
                            label: 'Previous Period',
                            data: [
                                parseFloat(previous.revenue || 0),
                                parseFloat(previous.profit || 0),
                                parseInt(previous.jobs || 0)
                            ],
                            backgroundColor: this.colors.secondary || '#94a3b8'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    if (context.dataIndex === 2) {
                                        return context.dataset.label + ': ' + context.parsed.y;
                                    }
                                    return context.dataset.label + ': GHS ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => {
                                    if (value >= 1000) {
                                        return 'GHS ' + (value / 1000).toFixed(1) + 'K';
                                    }
                                    return 'GHS ' + value;
                                }
                            }
                        }
                    }
                }
            });

            this.charts.set('comparative', chart);
        } catch (error) {
            console.error('Error loading comparative chart:', error);
        }
    }

    async loadRigMarginChart() {
        try {
            const data = await this.fetchData('rig_performance');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.rig_name);
            const margins = data.map(item => parseFloat(item.profit_margin || 0));

            const ctx = document.getElementById('rigMarginChart');
            if (!ctx) return;

            if (this.charts.has('rigMargin')) {
                this.charts.get('rigMargin').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Profit Margin (%)',
                        data: margins,
                        backgroundColor: margins.map(m => m >= 20 ? this.colors.success : m >= 10 ? this.colors.warning : this.colors.danger)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.parsed.y.toFixed(2) + '%'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Profit Margin (%)'
                            },
                            ticks: {
                                callback: (value) => value + '%'
                            }
                        }
                    }
                }
            });

            this.charts.set('rigMargin', chart);
        } catch (error) {
            console.error('Error loading rig margin:', error);
        }
    }

    async loadEfficiencyChart() {
        try {
            const data = await this.fetchData('operational_metrics');
            if (!data) return;

            const ctx = document.getElementById('efficiencyChart');
            if (!ctx) return;

            const jobsPerDay = parseFloat(data.jobs_per_day || 0);
            const avgDuration = parseFloat(data.avg_job_duration_minutes || 0) / 60;
            const avgDepth = parseFloat(data.avg_depth || 0);
            const utilization = (jobsPerDay / 2) * 100; // Assuming 2 jobs/day is 100%

            if (this.charts.has('efficiency')) {
                this.charts.get('efficiency').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['Jobs/Day', 'Avg Duration (hrs)', 'Avg Depth (m)', 'Utilization (%)'],
                    datasets: [{
                        label: 'Efficiency Metrics',
                        data: [
                            Math.min(jobsPerDay, 5),
                            Math.min(avgDuration, 10),
                            Math.min(avgDepth, 100),
                            Math.min(utilization, 100)
                        ],
                        backgroundColor: this.alphaColor(this.colors.primary, 0.2),
                        borderColor: this.colors.primary,
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        r: {
                            beginAtZero: true
                        }
                    }
                }
            });

            this.charts.set('efficiency', chart);
        } catch (error) {
            console.error('Error loading efficiency chart:', error);
        }
    }

    async loadForecastChart() {
        try {
            const result = await this.fetchData('trend_forecast');
            if (!result || !result.success) return;

            const data = result.data || result;
            const historical = data.historical || result.historical || [];
            const forecast = data.forecast || result.forecast || [];

            const allData = [...historical, ...forecast];
            const labels = allData.map((item, index) => {
                if (item.is_forecast) {
                    return item.period;
                }
                return this.formatPeriod(item.period);
            });

            const profits = allData.map(item => parseFloat(item.profit || 0));

            const ctx = document.getElementById('forecastChart');
            if (!ctx) return;

            if (this.charts.has('forecast')) {
                this.charts.get('forecast').destroy();
            }

            const historicalLength = historical.length;
            const backgroundColors = profits.map((_, index) => {
                if (index >= historicalLength) {
                    return this.alphaColor(this.colors.warning, 0.3);
                }
                return this.alphaColor(this.colors.primary, 0.5);
            });

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Historical Profit',
                            data: profits.slice(0, historicalLength),
                            borderColor: this.colors.primary,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Forecasted Profit',
                            data: [...Array(historicalLength).fill(null), ...profits.slice(historicalLength)],
                            borderColor: this.colors.warning,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.1),
                            borderWidth: 3,
                            borderDash: [5, 5],
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    if (context.parsed.y === null) return '';
                                    return context.dataset.label + ': GHS ' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => 'GHS ' + value.toLocaleString()
                            }
                        }
                    }
                }
            });

            this.charts.set('forecast', chart);
        } catch (error) {
            console.error('Error loading forecast:', error);
        }
    }

    // POS Charts
    async loadPosCharts() {
        await Promise.all([
            this.loadPosSalesTrendChart(),
            this.loadPosPaymentMethodsChart(),
            this.loadPosStorePerformanceChart(),
            this.loadPosTopProductsChart(),
            this.loadPosCashierPerformanceChart()
        ]);
    }

    async loadPosSalesTrendChart() {
        try {
            const data = await this.fetchData('pos_sales_trend');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const sales = data.map(item => parseFloat(item.total_sales || 0));
            const cash = data.map(item => parseFloat(item.cash_sales || 0));
            const card = data.map(item => parseFloat(item.card_sales || 0));
            const momo = data.map(item => parseFloat(item.momo_sales || 0));

            const ctx = document.getElementById('posSalesTrendChart');
            if (!ctx) return;

            if (this.charts.has('posSalesTrend')) {
                this.charts.get('posSalesTrend').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Sales',
                            data: sales,
                            borderColor: this.colors.primary,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Cash',
                            data: cash,
                            borderColor: this.colors.success,
                            backgroundColor: this.alphaColor(this.colors.success, 0.1),
                            borderWidth: 2,
                            fill: false
                        },
                        {
                            label: 'Card',
                            data: card,
                            borderColor: this.colors.warning,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.1),
                            borderWidth: 2,
                            fill: false
                        },
                        {
                            label: 'Mobile Money',
                            data: momo,
                            borderColor: this.colors.purple,
                            backgroundColor: this.alphaColor(this.colors.purple, 0.1),
                            borderWidth: 2,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: (context) => context.dataset.label + ': ' + this.formatCurrency(context.parsed.y)
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('posSalesTrend', chart);
        } catch (error) {
            console.error('Error loading POS sales trend:', error);
        }
    }

    async loadPosPaymentMethodsChart() {
        try {
            const data = await this.fetchData('pos_payment_methods');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.payment_method.toUpperCase());
            const amounts = data.map(item => parseFloat(item.total_amount || 0));

            const ctx = document.getElementById('posPaymentMethodsChart');
            if (!ctx) return;

            if (this.charts.has('posPaymentMethods')) {
                this.charts.get('posPaymentMethods').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: amounts,
                        backgroundColor: this.generateColors(labels.length, 0.8)
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'right' },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const total = amounts.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + this.formatCurrency(context.parsed) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });

            this.charts.set('posPaymentMethods', chart);
        } catch (error) {
            console.error('Error loading POS payment methods:', error);
        }
    }

    async loadPosStorePerformanceChart() {
        try {
            const data = await this.fetchData('pos_store_performance');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.store_name || 'Unknown');
            const sales = data.map(item => parseFloat(item.total_sales || 0));

            const ctx = document.getElementById('posStorePerformanceChart');
            if (!ctx) return;

            if (this.charts.has('posStorePerformance')) {
                this.charts.get('posStorePerformance').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Sales',
                        data: sales,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => this.formatCurrency(context.parsed.x)
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('posStorePerformance', chart);
        } catch (error) {
            console.error('Error loading POS store performance:', error);
        }
    }

    async loadPosTopProductsChart() {
        try {
            const data = await this.fetchData('pos_top_products');
            if (!data || data.length === 0) return;

            const top10 = data.slice(0, 10);
            const labels = top10.map(item => item.name || item.sku);
            const revenue = top10.map(item => parseFloat(item.total_revenue || 0));

            const ctx = document.getElementById('posTopProductsChart');
            if (!ctx) return;

            if (this.charts.has('posTopProducts')) {
                this.charts.get('posTopProducts').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Revenue',
                        data: revenue,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => this.formatCurrency(context.parsed.x)
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('posTopProducts', chart);
        } catch (error) {
            console.error('Error loading POS top products:', error);
        }
    }

    async loadPosCashierPerformanceChart() {
        try {
            const data = await this.fetchData('pos_cashier_performance');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.cashier_name || 'Unknown');
            const sales = data.map(item => parseFloat(item.total_sales || 0));

            const ctx = document.getElementById('posCashierPerformanceChart');
            if (!ctx) return;

            if (this.charts.has('posCashierPerformance')) {
                this.charts.get('posCashierPerformance').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Sales',
                        data: sales,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => this.formatCurrency(context.parsed.x)
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('posCashierPerformance', chart);
        } catch (error) {
            console.error('Error loading POS cashier performance:', error);
        }
    }

    // CMS Charts
    async loadCmsCharts() {
        await Promise.all([
            this.loadCmsOrdersTrendChart(),
            this.loadCmsQuoteRequestsChart()
        ]);
    }

    async loadCmsOrdersTrendChart() {
        try {
            const data = await this.fetchData('cms_orders_trend');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const revenue = data.map(item => parseFloat(item.total_revenue || 0));
            const orders = data.map(item => parseInt(item.order_count || 0));

            const ctx = document.getElementById('cmsOrdersTrendChart');
            if (!ctx) return;

            if (this.charts.has('cmsOrdersTrend')) {
                this.charts.get('cmsOrdersTrend').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Revenue',
                            data: revenue,
                            borderColor: this.colors.primary,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                            borderWidth: 3,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Orders',
                            data: orders,
                            type: 'bar',
                            backgroundColor: this.alphaColor(this.colors.success, 0.5),
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });

            this.charts.set('cmsOrdersTrend', chart);
        } catch (error) {
            console.error('Error loading CMS orders trend:', error);
        }
    }

    async loadCmsQuoteRequestsChart() {
        try {
            const data = await this.fetchData('cms_quote_requests');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const total = data.map(item => parseInt(item.request_count || 0));
            const converted = data.map(item => parseInt(item.converted_count || 0));
            const pending = data.map(item => parseInt(item.pending_count || 0));

            const ctx = document.getElementById('cmsQuoteRequestsChart');
            if (!ctx) return;

            if (this.charts.has('cmsQuoteRequests')) {
                this.charts.get('cmsQuoteRequests').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Requests',
                            data: total,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.7)
                        },
                        {
                            label: 'Converted',
                            data: converted,
                            backgroundColor: this.alphaColor(this.colors.success, 0.7)
                        },
                        {
                            label: 'Pending',
                            data: pending,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.7)
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            this.charts.set('cmsQuoteRequests', chart);
        } catch (error) {
            console.error('Error loading CMS quote requests:', error);
        }
    }

    // Inventory Charts
    async loadInventoryCharts() {
        await Promise.all([
            this.loadInventoryValueTrendChart(),
            this.loadInventoryMaterialUsageChart()
        ]);
    }

    async loadInventoryValueTrendChart() {
        try {
            const data = await this.fetchData('inventory_value_trend');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const value = data.map(item => parseFloat(item.total_inventory_value || 0));
            const added = data.map(item => parseFloat(item.inventory_added || 0));
            const used = data.map(item => parseFloat(item.inventory_used || 0));

            const ctx = document.getElementById('inventoryValueTrendChart');
            if (!ctx) return;

            if (this.charts.has('inventoryValueTrend')) {
                this.charts.get('inventoryValueTrend').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Inventory Value',
                            data: value,
                            borderColor: this.colors.primary,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.1),
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Added',
                            data: added,
                            borderColor: this.colors.success,
                            backgroundColor: this.alphaColor(this.colors.success, 0.1),
                            borderWidth: 2,
                            fill: false
                        },
                        {
                            label: 'Used',
                            data: used,
                            borderColor: this.colors.danger,
                            backgroundColor: this.alphaColor(this.colors.danger, 0.1),
                            borderWidth: 2,
                            fill: false
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: (context) => context.dataset.label + ': ' + this.formatCurrency(context.parsed.y)
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('inventoryValueTrend', chart);
        } catch (error) {
            console.error('Error loading inventory value trend:', error);
        }
    }

    async loadInventoryMaterialUsageChart() {
        try {
            const data = await this.fetchData('inventory_material_usage');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.material_type);
            const quantity = data.map(item => parseFloat(item.quantity_used || 0));
            const cost = data.map(item => parseFloat(item.total_cost || 0));

            const ctx = document.getElementById('inventoryMaterialUsageChart');
            if (!ctx) return;

            if (this.charts.has('inventoryMaterialUsage')) {
                this.charts.get('inventoryMaterialUsage').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Quantity Used',
                            data: quantity,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.7),
                            yAxisID: 'y'
                        },
                        {
                            label: 'Total Cost',
                            data: cost,
                            type: 'line',
                            borderColor: this.colors.warning,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.1),
                            borderWidth: 3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: {
                            position: 'left',
                            beginAtZero: true
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });

            this.charts.set('inventoryMaterialUsage', chart);
        } catch (error) {
            console.error('Error loading inventory material usage:', error);
        }
    }

    // Accounting Charts
    async loadAccountingCharts() {
        await Promise.all([
            this.loadAccountingJournalEntriesChart(),
            this.loadAccountingAccountBalancesChart()
        ]);
    }

    async loadAccountingJournalEntriesChart() {
        try {
            const data = await this.fetchData('accounting_journal_entries');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const debits = data.map(item => parseFloat(item.total_debits || 0));
            const credits = data.map(item => parseFloat(item.total_credits || 0));

            const ctx = document.getElementById('accountingJournalEntriesChart');
            if (!ctx) return;

            if (this.charts.has('accountingJournalEntries')) {
                this.charts.get('accountingJournalEntries').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Debits',
                            data: debits,
                            backgroundColor: this.alphaColor(this.colors.danger, 0.7)
                        },
                        {
                            label: 'Credits',
                            data: credits,
                            backgroundColor: this.alphaColor(this.colors.success, 0.7)
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: (context) => context.dataset.label + ': ' + this.formatCurrency(context.parsed.y)
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('accountingJournalEntries', chart);
        } catch (error) {
            console.error('Error loading accounting journal entries:', error);
        }
    }

    async loadAccountingAccountBalancesChart() {
        try {
            const data = await this.fetchData('accounting_account_balances');
            if (!data || data.length === 0) return;

            const labels = data.map(item => item.account_type);
            const balances = data.map(item => parseFloat(item.total_balance || 0));

            const ctx = document.getElementById('accountingAccountBalancesChart');
            if (!ctx) return;

            if (this.charts.has('accountingAccountBalances')) {
                this.charts.get('accountingAccountBalances').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Balance',
                        data: balances,
                        backgroundColor: this.generateColors(labels.length, 0.7)
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (context) => this.formatCurrency(context.parsed.x)
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: (value) => this.formatCurrency(value).replace('GHS ', '')
                            }
                        }
                    }
                }
            });

            this.charts.set('accountingAccountBalances', chart);
        } catch (error) {
            console.error('Error loading accounting account balances:', error);
        }
    }

    // CRM Charts
    async loadCrmCharts() {
        await this.loadCrmFollowupsChart();
    }

    async loadCrmFollowupsChart() {
        try {
            const data = await this.fetchData('crm_followups');
            if (!data || data.length === 0) return;

            const labels = data.map(item => this.formatPeriod(item.period));
            const total = data.map(item => parseInt(item.followup_count || 0));
            const completed = data.map(item => parseInt(item.completed_count || 0));
            const scheduled = data.map(item => parseInt(item.scheduled_count || 0));
            const overdue = data.map(item => parseInt(item.overdue_count || 0));

            const ctx = document.getElementById('crmFollowupsChart');
            if (!ctx) return;

            if (this.charts.has('crmFollowups')) {
                this.charts.get('crmFollowups').destroy();
            }

            const chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total',
                            data: total,
                            backgroundColor: this.alphaColor(this.colors.primary, 0.7)
                        },
                        {
                            label: 'Completed',
                            data: completed,
                            backgroundColor: this.alphaColor(this.colors.success, 0.7)
                        },
                        {
                            label: 'Scheduled',
                            data: scheduled,
                            backgroundColor: this.alphaColor(this.colors.warning, 0.7)
                        },
                        {
                            label: 'Overdue',
                            data: overdue,
                            backgroundColor: this.alphaColor(this.colors.danger, 0.7)
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: { mode: 'index', intersect: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });

            this.charts.set('crmFollowups', chart);
        } catch (error) {
            console.error('Error loading CRM followups:', error);
        }
    }

    // Utility functions
    async fetchData(type) {
        const cacheKey = `${type}_${this.config.startDate}_${this.config.endDate}_${this.config.groupBy}`;
        
        if (this.dataCache.has(cacheKey)) {
            return this.dataCache.get(cacheKey);
        }

        try {
            const url = `${this.config.apiUrl}?type=${type}&${this.getParams()}`;
            const response = await fetch(url);
            
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                // For comparative_analysis and trend_forecast, return the full result
                if (type === 'comparative_analysis' || type === 'trend_forecast') {
                    this.dataCache.set(cacheKey, result);
                    return result;
                }
                
                const data = result.data || result;
                this.dataCache.set(cacheKey, data);
                return data;
            } else {
                console.error(`API error for ${type}:`, result.message);
                return null;
            }
        } catch (error) {
            console.error(`Error fetching ${type}:`, error);
            return null;
        }
    }

    getParams() {
        const params = new URLSearchParams({
            start_date: this.config.startDate,
            end_date: this.config.endDate,
            group_by: this.config.groupBy
        });

        if (this.config.rigId) params.append('rig_id', this.config.rigId);
        if (this.config.clientId) params.append('client_id', this.config.clientId);
        if (this.config.jobType) params.append('job_type', this.config.jobType);

        return params.toString();
    }

    formatPeriod(period) {
        if (!period) return '';
        
        // Handle different period formats
        if (period.includes('Q')) {
            return period; // Quarter format like "2024-Q1"
        }
        
        if (period.length === 7) {
            // Month format YYYY-MM
            const [year, month] = period.split('-');
            return new Date(year, month - 1).toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
        }
        
        if (period.length === 10) {
            // Date format YYYY-MM-DD
            return new Date(period).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        }
        
        return period;
    }

    formatCurrency(amount) {
        return 'GHS ' + parseFloat(amount || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    formatNumber(num) {
        return parseFloat(num || 0).toLocaleString('en-US');
    }

    alphaColor(color, alpha) {
        if (color.startsWith('#')) {
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }
        return color;
    }

    generateColors(count, alpha = 0.7) {
        const colors = [
            this.colors.primary,
            this.colors.success,
            this.colors.warning,
            this.colors.danger,
            this.colors.purple,
            this.colors.teal,
            this.colors.orange,
            this.colors.pink
        ];
        
        const result = [];
        for (let i = 0; i < count; i++) {
            result.push(this.alphaColor(colors[i % colors.length], alpha));
        }
        return result;
    }

    exportChart(chartId) {
        const chart = this.charts.get(chartId);
        if (chart) {
            const url = chart.toBase64Image();
            const link = document.createElement('a');
            link.download = `${chartId}-${new Date().toISOString().split('T')[0]}.png`;
            link.href = url;
            link.click();
        }
    }

    toggleFullscreen(chartId) {
        // Implement fullscreen toggle
        const container = document.querySelector(`#${chartId}Chart`).closest('.chart-container');
        if (container) {
            container.classList.toggle('fullscreen');
        }
    }

    refreshAll() {
        this.dataCache.clear();
        this.charts.forEach(chart => chart.destroy());
        this.charts.clear();
        this.init();
    }

    exportDashboard(format) {
        if (format === 'pdf') {
            window.print();
        } else if (format === 'excel') {
            window.location.href = `../api/export-data.php?type=analytics&format=csv&${this.getParams()}`;
        }
    }
}

// Make AdvancedAnalytics available globally
if (typeof window !== 'undefined') {
    window.AdvancedAnalytics = AdvancedAnalytics;
    console.log('AdvancedAnalytics class registered globally');
} else {
    console.error('window object not available, cannot register AdvancedAnalytics');
}

})();

