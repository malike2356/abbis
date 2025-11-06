/**
 * Real-time Dashboard Updates
 * Polls for updates and refreshes dashboard data
 */

(function() {
    'use strict';
    
    let updateInterval = null;
    let isActive = false;
    
    // Configuration
    const CONFIG = {
        enabled: true,
        interval: 30000, // 30 seconds
        endpoints: {
            alerts: '../api/alerts-api.php',
            stats: '../api/analytics-api.php?type=financial_overview'
        }
    };
    
    /**
     * Initialize real-time updates
     */
    function init() {
        if (!CONFIG.enabled) return;
        
        // Only run on dashboard
        if (!document.getElementById('dashboard-stats') && !window.location.pathname.includes('dashboard.php')) {
            return;
        }
        
        // Start polling
        startPolling();
        
        // Stop when page is hidden (browser tab switch)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                stopPolling();
            } else {
                startPolling();
            }
        });
        
        // Stop on page unload
        window.addEventListener('beforeunload', stopPolling);
    }
    
    /**
     * Start polling for updates
     */
    function startPolling() {
        if (isActive) return;
        
        isActive = true;
        
        // Immediate update
        updateDashboard();
        
        // Then poll at intervals
        updateInterval = setInterval(updateDashboard, CONFIG.interval);
    }
    
    /**
     * Stop polling
     */
    function stopPolling() {
        if (updateInterval) {
            clearInterval(updateInterval);
            updateInterval = null;
        }
        isActive = false;
    }
    
    /**
     * Update dashboard data
     */
    async function updateDashboard() {
        try {
            // Update alerts
            await updateAlerts();
            
            // Update stats if on dashboard
            if (window.location.pathname.includes('dashboard.php')) {
                await updateStats();
            }
        } catch (error) {
            console.error('Real-time update error:', error);
        }
    }
    
    /**
     * Update alerts
     */
    async function updateAlerts() {
        try {
            const response = await fetch(CONFIG.endpoints.alerts);
            const data = await response.json();
            
            if (data.success && data.alerts) {
                updateAlertsDisplay(data.alerts);
            }
        } catch (error) {
            console.error('Alerts update error:', error);
        }
    }
    
    /**
     * Update alerts display
     */
    function updateAlertsDisplay(alerts) {
        // Find or create alerts container
        let alertsContainer = document.getElementById('realtime-alerts');
        
        if (!alertsContainer) {
            // Create alerts container if it doesn't exist
            const dashboard = document.querySelector('.dashboard-card, .container-fluid');
            if (dashboard) {
                alertsContainer = document.createElement('div');
                alertsContainer.id = 'realtime-alerts';
                alertsContainer.style.cssText = 'margin-bottom: 20px;';
                dashboard.insertBefore(alertsContainer, dashboard.firstChild);
            } else {
                return;
            }
        }
        
        if (alerts.length === 0) {
            alertsContainer.style.display = 'none';
            return;
        }
        
        alertsContainer.style.display = 'block';
        
        let html = '<div style="display: grid; gap: 12px;">';
        alerts.forEach(alert => {
            const priorityClass = alert.priority === 'high' ? 'danger' : alert.priority === 'medium' ? 'warning' : 'info';
            html += `
                <div class="dashboard-card" style="border-left: 4px solid var(--${priorityClass}); padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 12px;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 8px 0; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                                <span>${alert.icon}</span>
                                <span>${alert.title}</span>
                            </h3>
                            <p style="margin: 0; color: var(--secondary); font-size: 14px;">${alert.message}</p>
                        </div>
                        <a href="${alert.url}" class="btn btn-sm btn-primary">View →</a>
                    </div>
                </div>
            `;
        });
        html += '</div>';
        
        alertsContainer.innerHTML = html;
    }
    
    /**
     * Update dashboard stats
     */
    async function updateStats() {
        try {
            const response = await fetch(CONFIG.endpoints.stats);
            const data = await response.json();
            
            if (data.success && data.data) {
                // Update KPI cards if they exist
                updateKPICard('total-revenue', data.data.total_revenue);
                updateKPICard('total-expenses', data.data.total_expenses);
                updateKPICard('total-profit', data.data.total_profit);
            }
        } catch (error) {
            console.error('Stats update error:', error);
        }
    }
    
    /**
     * Update a KPI card value with animation
     */
    function updateKPICard(id, value) {
        const card = document.getElementById(id);
        if (!card) return;
        
        const valueElement = card.querySelector('.kpi-card-value, .stat-value, [class*="value"]');
        if (!valueElement) return;
        
        const oldValue = parseFloat(valueElement.textContent.replace(/[^0-9.]/g, '')) || 0;
        const newValue = parseFloat(value) || 0;
        
        if (Math.abs(oldValue - newValue) > 0.01) {
            // Animate update
            valueElement.style.transition = 'opacity 0.3s';
            valueElement.style.opacity = '0.5';
            
            setTimeout(() => {
                if (valueElement.textContent.includes('GHS')) {
                    valueElement.textContent = 'GHS ' + number_format(newValue, 2);
                } else {
                    valueElement.textContent = number_format(newValue);
                }
                valueElement.style.opacity = '1';
            }, 150);
        }
    }
    
    /**
     * Number format helper
     */
    function number_format(number, decimals = 2) {
        return parseFloat(number).toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    // Export for manual control
    window.realtimeUpdates = {
        start: startPolling,
        stop: stopPolling,
        update: updateDashboard
    };
})();

