(() => {
    const dashboard = document.getElementById('posDashboard');
    if (!dashboard) {
        return;
    }

    const endpoint = dashboard.dataset.endpoint;
    const currentUserId = parseInt(dashboard.dataset.currentUser || '0', 10);

    function formatCurrency(value) {
        return 'GHS ' + Number(value || 0).toFixed(2);
    }

    function setText(selector, text) {
        const el = dashboard.querySelector(selector);
        if (el) {
            el.textContent = text;
        }
    }

    function renderSummary(summary) {
        if (!summary) {
            return;
        }
        const today = summary.today || {};
        const week = summary.week || {};
        const month = summary.month || {};

        setText('[data-metric="today-sales"]', formatCurrency(today.total_amount));
        setText('[data-metric="today-transactions"]', today.transactions || 0);
        setText('[data-metric="today-average"]', formatCurrency(today.average_ticket));

        setText('[data-metric="week-sales"]', formatCurrency(week.total_amount));
        setText('[data-metric="month-sales"]', formatCurrency(month.total_amount));
    }

    function renderTable(rows, tbodySelector, renderer) {
        const tbody = dashboard.querySelector(tbodySelector);
        if (!tbody) return;
        tbody.innerHTML = '';

        if (!rows || rows.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            const columnCount =
                tbody.closest('table')?.tHead?.rows[0]?.cells.length ||
                tbody.closest('table')?.rows[0]?.cells.length ||
                4;
            cell.colSpan = columnCount;
            cell.textContent = 'No data available.';
            cell.style.textAlign = 'center';
            row.appendChild(cell);
            tbody.appendChild(row);
            return;
        }

        rows.forEach((rowData, index) => {
            const row = renderer(rowData, index);
            tbody.appendChild(row);
        });
    }

    function renderTopProducts(products) {
        renderTable(products, '#posTopProducts', (product, index) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${product.name}</td>
                <td style="text-align:right;">${Number(product.quantity).toFixed(0)}</td>
                <td style="text-align:right;">${formatCurrency(product.revenue)}</td>
            `;
            return tr;
        });
    }

    function renderTopCashiers(cashiers) {
        renderTable(cashiers, '#posTopCashiers', (cashier, index) => {
            const tr = document.createElement('tr');
            const isCurrent = cashier.cashier_id === currentUserId;
            tr.innerHTML = `
                <td>${index + 1}</td>
                <td>${cashier.name || 'Unknown'}</td>
                <td style="text-align:right;">${cashier.transactions}</td>
                <td style="text-align:right;">${formatCurrency(cashier.total_amount)}</td>
            `;
            if (isCurrent) {
                tr.classList.add('highlight');
            }
            return tr;
        });
    }

    function renderInventoryAlerts(alerts) {
        renderTable(alerts, '#posInventoryAlerts', (alert) => {
            const tr = document.createElement('tr');
            const reorder = Number(alert.reorder_level || 0);
            const quantity = Number(alert.quantity_on_hand || 0);
            const status =
                reorder > 0 && quantity <= 0
                    ? '<span class="badge badge-danger">Out</span>'
                    : '<span class="badge badge-warning">Low</span>';

            tr.innerHTML = `
                <td>${alert.product_name}</td>
                <td>${alert.store_name}</td>
                <td style="text-align:right;">${quantity.toFixed(0)}</td>
                <td style="text-align:center;">${status}</td>
            `;
            return tr;
        });
    }

    function renderRecentSales(sales) {
        renderTable(sales, '#posRecentSales', (sale) => {
            const tr = document.createElement('tr');
            const date = new Date(sale.sale_timestamp);
            tr.innerHTML = `
                <td>${sale.sale_number}</td>
                <td>${sale.store_name}</td>
                <td>${sale.cashier_name || 'N/A'}</td>
                <td>${date.toLocaleString()}</td>
                <td style="text-align:right;">${formatCurrency(sale.total_amount)}</td>
            `;
            return tr;
        });
    }

    function renderCashierInsights(insights) {
        if (!insights) {
            dashboard.classList.remove('has-cashier-insights');
            return;
        }
        dashboard.classList.add('has-cashier-insights');
        setText('[data-cashier="today-sales"]', formatCurrency(insights.today?.total_amount || 0));
        setText('[data-cashier="today-transactions"]', insights.today?.transactions || 0);
        setText('[data-cashier="month-sales"]', formatCurrency(insights.month?.total_amount || 0));
        setText('[data-cashier="month-transactions"]', insights.month?.transactions || 0);

        const list = dashboard.querySelector('#cashierRecentSales');
        if (list) {
            list.innerHTML = '';
            (insights.recent_sales || []).forEach((sale) => {
                const li = document.createElement('li');
                const date = new Date(sale.sale_timestamp);
                li.innerHTML = `
                    <span>${sale.sale_number}</span>
                    <span>${date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                    <span>${formatCurrency(sale.total_amount)}</span>
                `;
                list.appendChild(li);
            });
            if (list.children.length === 0) {
                const li = document.createElement('li');
                li.textContent = 'No recent sales recorded.';
                li.classList.add('empty');
                list.appendChild(li);
            }
        }
    }

    async function loadDashboard() {
        dashboard.classList.add('loading');
        try {
            const response = await fetch(endpoint, { credentials: 'include' });
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || 'Failed to load POS metrics.');
            }
            const data = result.data || {};
            renderSummary(data.summary);
            renderTopProducts(data.top_products);
            renderTopCashiers(data.top_cashiers);
            renderInventoryAlerts(data.inventory_alerts);
            renderRecentSales(data.recent_sales);
            renderCashierInsights(data.cashier);
        } catch (error) {
            console.error('POS dashboard error:', error);
            setText('[data-metric="today-sales"]', 'GHS 0.00');
            setText('[data-metric="today-transactions"]', '0');
            setText('[data-metric="today-average"]', 'GHS 0.00');
        } finally {
            dashboard.classList.remove('loading');
        }
    }

    loadDashboard();
    setInterval(loadDashboard, 60000);
})();


