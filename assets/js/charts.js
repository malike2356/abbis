// Charting functionality for ABBIS analytics
class ABBISCharts {
    constructor() {
        this.charts = new Map();
    }

    // Initialize all charts on the page
    initCharts() {
        this.initProfitTrendChart();
        this.initRigPerformanceChart();
        this.initMaterialsUsageChart();
        this.initWorkerEarningsChart();
    }

    // Profit trend chart (line chart)
    initProfitTrendChart() {
        const ctx = document.getElementById('profitTrendChart');
        if (!ctx) return;

        this.fetchChartData('monthly_profits').then(data => {
            const labels = data.map(item => {
                const [year, month] = item.month.split('-');
                return new Date(year, month - 1).toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short' 
                });
            }).reverse();

            const profits = data.map(item => item.total_profit).reverse();
            const jobCounts = data.map(item => item.job_count).reverse();

            this.charts.set('profitTrend', new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Monthly Profit (GHS)',
                            data: profits,
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Jobs Completed',
                            data: jobCounts,
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14, 165, 233, 0.1)',
                            borderWidth: 2,
                            fill: false,
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Profit (GHS)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'GHS ' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            title: {
                                display: true,
                                text: 'Jobs Count'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        if (label.includes('Profit')) {
                                            return label + ': GHS ' + context.parsed.y.toLocaleString();
                                        } else {
                                            return label + ': ' + context.parsed.y;
                                        }
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            }));
        });
    }

    // Rig performance chart (bar chart)
    initRigPerformanceChart() {
        const ctx = document.getElementById('rigPerformanceChart');
        if (!ctx) return;

        this.fetchChartData('rig_performance').then(data => {
            const labels = data.map(item => item.rig_name || 'Unassigned');
            const profits = data.map(item => item.total_profit);
            const jobCounts = data.map(item => item.job_count);

            this.charts.set('rigPerformance', new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Total Profit (GHS)',
                            data: profits,
                            backgroundColor: 'rgba(14, 165, 233, 0.8)',
                            borderColor: '#0ea5e9',
                            borderWidth: 1
                        },
                        {
                            label: 'Jobs Completed',
                            data: jobCounts,
                            backgroundColor: 'rgba(16, 185, 129, 0.6)',
                            borderColor: '#10b981',
                            borderWidth: 1,
                            type: 'line',
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Profit (GHS)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'GHS ' + value.toLocaleString();
                                }
                            }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Jobs Count'
                            },
                            grid: {
                                drawOnChartArea: false,
                            },
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Profit')) {
                                        return label + ': GHS ' + context.parsed.y.toLocaleString();
                                    } else {
                                        return label + ': ' + context.parsed.y;
                                    }
                                }
                            }
                        }
                    }
                }
            }));
        });
    }

    // Materials usage chart (doughnut chart)
    initMaterialsUsageChart() {
        const ctx = document.getElementById('materialsUsageChart');
        if (!ctx) return;

        this.fetchChartData('materials_usage').then(data => {
            const labels = data.map(item => {
                return item.material_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
            });
            const used = data.map(item => item.total_used);
            const remaining = data.map(item => item.total_remaining);

            this.charts.set('materialsUsage', new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: used,
                        backgroundColor: [
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(14, 165, 233, 0.8)'
                        ],
                        borderColor: [
                            '#ef4444',
                            '#f59e0b',
                            '#0ea5e9'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} units (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            }));
        });
    }

    // Worker earnings chart (horizontal bar chart)
    initWorkerEarningsChart() {
        const ctx = document.getElementById('workerEarningsChart');
        if (!ctx) return;

        this.fetchChartData('worker_earnings').then(data => {
            // Sort by earnings and take top 10
            const sortedData = data.sort((a, b) => b.total_earnings - a.total_earnings).slice(0, 10);
            
            const labels = sortedData.map(item => item.worker_name);
            const earnings = sortedData.map(item => item.total_earnings);
            const colors = this.generateColors(sortedData.length);

            this.charts.set('workerEarnings', new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Earnings (GHS)',
                        data: earnings,
                        backgroundColor: colors,
                        borderColor: colors.map(color => color.replace('0.8', '1')),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    scales: {
                        x: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Earnings (GHS)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return 'GHS ' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Earnings: GHS ' + context.parsed.x.toLocaleString();
                                }
                            }
                        }
                    }
                }
            }));
        });
    }

    // Fetch chart data from API
    async fetchChartData(dataType) {
        try {
            const response = await fetch(`../api/get-data.php?type=${dataType}`);
            const result = await response.json();
            
            if (result.success) {
                return result.data;
            } else {
                console.error('Error fetching chart data:', result.message);
                return [];
            }
        } catch (error) {
            console.error('Error fetching chart data:', error);
            return [];
        }
    }

    // Generate colors for charts
    generateColors(count) {
        const baseColors = [
            'rgba(14, 165, 233, 0.8)',    // Blue
            'rgba(16, 185, 129, 0.8)',    // Green
            'rgba(245, 158, 11, 0.8)',    // Yellow
            'rgba(239, 68, 68, 0.8)',     // Red
            'rgba(139, 92, 246, 0.8)',    // Purple
            'rgba(20, 184, 166, 0.8)',    // Teal
            'rgba(249, 115, 22, 0.8)',    // Orange
            'rgba(236, 72, 153, 0.8)',    // Pink
            'rgba(8, 145, 178, 0.8)',     // Cyan
            'rgba(101, 163, 13, 0.8)'     // Lime
        ];
        
        return baseColors.slice(0, count);
    }

    // Export chart as image
    exportChart(chartId, filename = 'chart') {
        const chart = this.charts.get(chartId);
        if (chart) {
            const link = document.createElement('a');
            link.download = `${filename}-${new Date().toISOString().split('T')[0]}.png`;
            link.href = chart.toBase64Image();
            link.click();
        }
    }

    // Update all charts
    refreshCharts() {
        this.charts.forEach((chart, chartId) => {
            chart.destroy();
        });
        this.charts.clear();
        this.initCharts();
    }
}

// Initialize charts when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.abbisCharts = new ABBISCharts();
    abbisCharts.initCharts();
});