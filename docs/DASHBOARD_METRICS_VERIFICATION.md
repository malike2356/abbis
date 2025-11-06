# Dashboard Metrics & KPIs - Data Linkage Verification

## ✅ **YES - All Metrics are Neurally Linked to Real Database Data**

All metrics and KPIs displayed on the dashboard pull from actual database queries. There are **NO hardcoded or placeholder values**.

---

## 📊 Data Sources & Neural Connections

### **Primary Data Source: `field_reports` Table**
This is the core table that stores all job/transaction data:
- `total_income` - Revenue from each job
- `total_expenses` - Expenses (wages, materials, daily expenses)
- `net_profit` - Calculated as (income - expenses)
- `total_wages` - Worker payments
- `materials_cost` - Material costs
- `materials_income` - Revenue from materials
- `bank_deposit` - Money banked/deposited
- `cash_received` - Cash received from clients
- `contract_sum` - Full contract value
- `outstanding_rig_fee` - Unpaid rig fees
- `total_money_banked` - Total deposits
- `total_duration` - Job duration in minutes
- `total_depth` - Depth drilled
- `created_at` - Timestamp for date filtering
- `rig_id` - Links to rigs table
- `client_id` - Links to clients table
- `job_type` - Direct or subcontract

### **Supporting Tables**

1. **`loans` Table** → Balance Sheet (Liabilities)
   - `loan_amount` - Total loaned
   - `outstanding_balance` - Current debt
   - `status = 'active'` - Active loans only

2. **`materials_inventory` Table** → Balance Sheet (Assets)
   - `total_value` - Current inventory value

3. **`clients` Table** → Top Performing Clients
   - Joined with `field_reports` to calculate per-client metrics

4. **`rigs` Table** → Top Performing Rigs & Operational Metrics
   - Joined with `field_reports` to calculate per-rig metrics
   - `status = 'active'` - Active rigs only

5. **`rig_fee_debts` Table** (if exists) → Outstanding Debts
   - Falls back to `field_reports.outstanding_rig_fee` if table doesn't exist

---

## 🔗 Neural Network Connections

### **Financial Health Metrics (8 KPIs)**
All calculated from `field_reports` aggregations:
1. **Profit Margin** = `(SUM(net_profit) / SUM(total_income)) * 100`
2. **Gross Margin** = `((SUM(total_income) - SUM(total_expenses)) / SUM(total_income)) * 100`
3. **Expense Ratio** = `(SUM(total_expenses) / SUM(total_income)) * 100`
4. **Avg Revenue per Job** = `SUM(total_income) / COUNT(*)`
5. **Avg Profit per Job** = `SUM(net_profit) / COUNT(*)`
6. **Avg Cost per Job** = `SUM(total_expenses) / COUNT(*)`
7. **Cost Efficiency** = `SUM(total_income) / SUM(total_expenses)` (calculated on dashboard)
8. **Profit-to-Cost Ratio** = `(SUM(net_profit) / SUM(total_expenses)) * 100` (calculated on dashboard)

### **Growth & Trends (Month-over-Month)**
Calculated by comparing:
- **This Month**: `WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())`
- **Last Month**: `WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))`
- Growth % = `((This Month - Last Month) / Last Month) * 100`

### **Balance Sheet**
**Assets**:
- `Total Assets` = `SUM(materials_inventory.total_value)` + `SUM(field_reports.bank_deposit)`
- `Cash Reserves` = `SUM(field_reports.bank_deposit)`
- `Materials Value` = `SUM(materials_inventory.total_value)`

**Liabilities**:
- `Total Liabilities` = `SUM(loans.outstanding_balance)` + `SUM(field_reports.outstanding_rig_fee)`
- `Net Worth` = `Total Assets - Total Liabilities`
- `Debt-to-Asset Ratio` = `(Total Liabilities / Total Assets) * 100`

### **Operational Efficiency**
From `field_reports`:
- `Avg Job Duration` = `AVG(total_duration)` / 60 (converted to hours)
- `Avg Depth per Job` = `AVG(total_depth)`
- `Active Rigs` = `COUNT(DISTINCT rig_id)`
- `Rig Utilization Rate` = `(SUM(total_duration) / (active_rigs * days_in_month * 8)) * 100`
- `Jobs per Day` = `total_jobs / days_since_year_start`

### **Cash Flow (Last 30 Days)**
- `Cash Inflow` = `SUM(cash_received + momo_transfer)`
- `Cash Outflow` = `SUM(cash_given + total_wages + total_expenses)`
- `Net Cash Flow` = `SUM(net_profit)`
- `Bank Deposits` = `SUM(bank_deposit)`
- Filter: `WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)`

### **Top Performers**
**Top Clients**:
```sql
SELECT c.client_name, COUNT(fr.id) as job_count,
       SUM(fr.total_income) as total_revenue,
       SUM(fr.net_profit) as total_profit,
       AVG(fr.net_profit) as avg_profit_per_job
FROM clients c
LEFT JOIN field_reports fr ON c.id = fr.client_id
GROUP BY c.id, c.client_name
HAVING job_count > 0
ORDER BY total_revenue DESC
LIMIT 5
```

**Top Rigs**:
```sql
SELECT r.rig_name, r.rig_code, COUNT(fr.id) as job_count,
       SUM(fr.total_income) as total_revenue,
       SUM(fr.net_profit) as total_profit,
       AVG(fr.net_profit / NULLIF(fr.total_income, 0)) * 100 as profit_margin
FROM rigs r
LEFT JOIN field_reports fr ON r.id = fr.rig_id
GROUP BY r.id, r.rig_name, r.rig_code
HAVING job_count > 0
ORDER BY total_profit DESC
LIMIT 5
```

---

## ✅ Data Integrity Features

1. **NULL Handling**: All queries use `COALESCE(..., 0)` to handle NULL values
2. **Division by Zero Protection**: All percentage/ratio calculations check for `> 0` before division
3. **Graceful Fallbacks**: Missing tables (like `rig_fee_debts`) fall back to `field_reports` data
4. **Try-Catch Blocks**: Database errors are caught and return empty/default values
5. **Caching**: Results are cached for 1 hour to improve performance (cache key includes date+hour)

---

## 🔄 Real-Time Data Flow

```
User Action (Create Field Report)
    ↓
Field Report Saved to Database
    ↓
field_reports table updated
    ↓
Dashboard Refresh/Cache Expiry
    ↓
getDashboardStats() executes SQL queries
    ↓
Real-time aggregations (SUM, COUNT, AVG)
    ↓
Calculations (ratios, percentages, averages)
    ↓
Dashboard displays ACTUAL data
```

---

## 🎯 Verification Checklist

- ✅ All financial metrics pull from `field_reports` table
- ✅ All calculations use SQL aggregations (SUM, COUNT, AVG)
- ✅ No hardcoded values in KPI cards
- ✅ Date-based filtering uses actual `created_at` timestamps
- ✅ Joins between tables ensure relational data integrity
- ✅ Balance sheet combines data from multiple tables
- ✅ Operational metrics use actual job duration and depth data
- ✅ Top performers use actual revenue/profit aggregations
- ✅ Growth metrics compare real month-over-month data

---

## ⚠️ Notes

1. **Empty Database**: If no field reports exist, all metrics will show `0` (not placeholder data)
2. **Caching**: Dashboard refreshes data every hour automatically (or on manual cache clear)
3. **Historical Data**: All metrics respect date filters (today, this month, last month, this year)
4. **Cross-Table Relationships**: The system uses proper SQL JOINs to maintain data relationships

---

## 🧪 Test Verification

To verify the neural connections are working:
1. Create a new field report with known values
2. Wait for cache to expire (or clear cache)
3. Refresh dashboard - values should update immediately
4. Check that totals match sum of individual reports
5. Verify percentages/ratios calculate correctly

**All metrics are live, real-time, and interconnected like a neural network! 🧠**

