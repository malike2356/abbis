<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

/**
 * Check if a feature is enabled
 */
function isFeatureEnabled($featureKey) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT is_enabled FROM feature_toggles WHERE feature_key = ?");
        $stmt->execute([$featureKey]);
        $feature = $stmt->fetch();
        return $feature && $feature['is_enabled'];
    } catch (PDOException $e) {
        // If feature_toggles table doesn't exist, assume feature is enabled
        return true;
    }
}

/**
 * Get all enabled features (cached for performance)
 */
function getEnabledFeatures() {
    static $enabledFeatures = null;
    if ($enabledFeatures !== null) {
        return $enabledFeatures;
    }
    
    $enabledFeatures = [];
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT feature_key FROM feature_toggles WHERE is_enabled = 1");
        $enabledFeatures = array_column($stmt->fetchAll(), 'feature_key');
    } catch (PDOException $e) {
        // If table doesn't exist, return empty array
    }
    return $enabledFeatures;
}

class ABBISFunctions {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Calculate construction depth
     * Formula: (screen pipes + plain pipes) * 3 meters per pipe
     * @param int $screenPipesUsed Number of screen pipes used
     * @param int $plainPipesUsed Number of plain pipes used
     * @return float Construction depth in meters
     */
    public function calculateConstructionDepth($screenPipesUsed, $plainPipesUsed) {
        $screen = intval($screenPipesUsed) ?: 0;
        $plain = intval($plainPipesUsed) ?: 0;
        return ($screen + $plain) * 3.0;
    }
    
    // Generate unique report ID with rig code
    public function generateReportId($rigCode = null, $type = REPORT_PREFIX) {
        $date = date('Ymd');
        
        if (!$rigCode) {
            // Get rig code from most recent active rig if available
            $rigStmt = $this->pdo->query("SELECT rig_code FROM rigs WHERE status = 'active' ORDER BY id DESC LIMIT 1");
            $rig = $rigStmt->fetch();
            $rigCode = $rig ? $rig['rig_code'] : 'SYS';
        }
        
        // Count existing reports for today with this rig code
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM field_reports fr
            JOIN rigs r ON fr.rig_id = r.id
            WHERE DATE(fr.created_at) = CURDATE() AND r.rig_code = ?
        ");
        $stmt->execute([$rigCode]);
        $count = $stmt->fetch()['count'] + 1;
        
        return sprintf('%s-%s-%s-%04d', $type, $date, $rigCode, $count);
    }
    
    // Calculate financial totals based on corrected business logic
    public function calculateFinancialTotals($data) {
        $totals = [
            'total_income' => 0,
            'total_expenses' => 0,
            'total_wages' => 0,
            'net_profit' => 0,
            'total_money_banked' => 0,
            'days_balance' => 0,
            'outstanding_rig_fee' => 0,
            'materials_income' => 0
        ];
        
        // INCOME (+) - Positives
        $balanceBF = floatval($data['balance_bf'] ?? 0);
        
        // Balance B/F - company money at hand from previous day(s)
        $totals['total_income'] += $balanceBF;
        
        // Full Contract Sum (only for direct jobs)
        $rigFeeCharged = floatval($data['rig_fee_charged'] ?? 0);
        if (($data['job_type'] ?? '') === JOB_DIRECT) {
            $contractSum = floatval($data['contract_sum'] ?? 0);
            $totals['total_income'] += $contractSum;
            
            // For direct jobs, rig fee charged is deducted from contract sum
            if ($rigFeeCharged > 0) {
                $totals['total_income'] -= $rigFeeCharged; // Deduct rig fee from contract sum
            }
        }
        
        // Rig Fee Collected (from client) - always income
        $rigFeeCollected = floatval($data['rig_fee_collected'] ?? 0);
        $totals['total_income'] += $rigFeeCollected;
        
        // Cash Received (from company, not client)
        $cashReceived = floatval($data['cash_received'] ?? 0);
        $totals['total_income'] += $cashReceived;
        
        // Material Sold - money gotten from selling company materials
        $materialsIncome = floatval($data['materials_income'] ?? 0);
        $totals['materials_income'] = $materialsIncome;
        $totals['total_income'] += $materialsIncome;
        
        // EXPENSES (-) - Negatives
        // Materials Purchased
        // Note: For subcontract jobs, if materials_provided_by = 'client', materials cost is NOT included
        $materialsCost = floatval($data['materials_cost'] ?? 0);
        $jobType = $data['job_type'] ?? 'direct';
        $materialsProvidedBy = $data['materials_provided_by'] ?? 'client';
        
        // Rule: If contractor job AND materials provided by client → NOT in cost
        if (!($jobType === 'subcontract' && $materialsProvidedBy === 'client')) {
            $totals['total_expenses'] += $materialsCost;
        }
        
        // Wages (calculated separately)
        $totalWages = floatval($data['total_wages'] ?? 0);
        $totals['total_wages'] = $totalWages;
        $totals['total_expenses'] += $totalWages;
        
        // Loans - monies borrowed by workers
        $loansAmount = floatval($data['loans_amount'] ?? 0);
        $totals['total_expenses'] += $loansAmount;
        
        // Daily Expenses (from expense entries)
        $dailyExpenses = floatval($data['daily_expenses'] ?? 0);
        $totals['total_expenses'] += $dailyExpenses;
        
        // Calculate net profit (income - expenses, excluding deposits)
        $totals['net_profit'] = $totals['total_income'] - $totals['total_expenses'];
        
        // Money Banked (deposits/savings)
        $momoTransfer = floatval($data['momo_transfer'] ?? 0);
        $cashGiven = floatval($data['cash_given'] ?? 0);
        $bankDeposit = floatval($data['bank_deposit'] ?? 0);
        $totals['total_money_banked'] = $momoTransfer + $cashGiven + $bankDeposit;
        
        // Day's Balance calculation - money remaining at hand after expenses and deposits
        // Start with Balance B/F (already included in total_income)
        // Add new income received today (excluding B/F which is already at hand)
        $newIncomeToday = $totals['total_income'] - $balanceBF; // Remove B/F from income for balance calc
        $cashAtStart = $balanceBF;
        $cashBeforeBanking = $cashAtStart + $newIncomeToday - $totals['total_expenses'];
        $totals['days_balance'] = $cashBeforeBanking - $totals['total_money_banked'];
        
        // Outstanding Rig Fee
        $totals['outstanding_rig_fee'] = max(0, $rigFeeCharged - $rigFeeCollected);
        
        // Loans Outstanding
        $totals['loans_outstanding'] = $loansAmount;
        
        // Total Money in Debt
        $totals['total_debt'] = $totals['outstanding_rig_fee'] + $totals['loans_outstanding'];
        
        return $totals;
    }
    
    // Get dashboard statistics with caching - Enhanced with investor KPIs
    public function getDashboardStats($useCache = true) {
        // Use hourly cache for better performance
        $cacheKey = 'dashboard_stats_' . date('Y-m-d-H');
        
        // Try to get from cache first
        if ($useCache) {
            $cached = $this->getCache($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }
        
        $stats = [];
        
        try {
        // Today's totals
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reports_today,
                COALESCE(SUM(total_income), 0) as total_income_today,
                COALESCE(SUM(total_expenses), 0) as total_expenses_today,
                COALESCE(SUM(net_profit), 0) as net_profit_today,
                COALESCE(SUM(total_money_banked), 0) as money_banked_today,
                COALESCE(SUM(materials_income), 0) as materials_income_today
            FROM field_reports 
            WHERE DATE(created_at) = CURDATE()
        ");
        $stmt->execute();
        $stats['today'] = $stmt->fetch();
        
        // Overall totals
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reports,
                COALESCE(SUM(total_income), 0) as total_income,
                COALESCE(SUM(total_expenses), 0) as total_expenses,
                COALESCE(SUM(net_profit), 0) as total_profit,
                COALESCE(SUM(outstanding_rig_fee), 0) as outstanding_rig_fees,
                COALESCE(SUM(materials_income), 0) as total_materials_income,
                COALESCE(AVG(net_profit), 0) as avg_profit_per_job,
                COALESCE(AVG(total_income), 0) as avg_income_per_job,
                COALESCE(AVG(total_expenses), 0) as avg_expenses_per_job,
                COALESCE(SUM(total_wages), 0) as total_wages,
                COALESCE(SUM(materials_cost), 0) as total_materials_cost,
                COALESCE(SUM(bank_deposit), 0) as total_bank_deposits,
                COALESCE(SUM(cash_received), 0) as total_cash_received,
                COALESCE(SUM(contract_sum), 0) as total_contract_value
            FROM field_reports
        ");
        $stmt->execute();
        $stats['overall'] = $stmt->fetch();
        
        // Calculate financial health metrics
        $totalIncome = (float)$stats['overall']['total_income'];
        $totalExpenses = (float)$stats['overall']['total_expenses'];
        $totalProfit = (float)$stats['overall']['total_profit'];
        $totalJobs = (int)$stats['overall']['total_reports'];
        
        $stats['financial_health'] = [
            'profit_margin' => $totalIncome > 0 ? ($totalProfit / $totalIncome) * 100 : 0,
            'expense_ratio' => $totalIncome > 0 ? ($totalExpenses / $totalIncome) * 100 : 0,
            'gross_margin' => $totalIncome > 0 ? (($totalIncome - $totalExpenses) / $totalIncome) * 100 : 0,
            'avg_profit_per_job' => $totalJobs > 0 ? ($totalProfit / $totalJobs) : 0,
            'avg_revenue_per_job' => $totalJobs > 0 ? ($totalIncome / $totalJobs) : 0,
            'avg_cost_per_job' => $totalJobs > 0 ? ($totalExpenses / $totalJobs) : 0,
        ];
        
        // This Month totals
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reports_this_month,
                COALESCE(SUM(total_income), 0) as total_income_this_month,
                COALESCE(SUM(total_expenses), 0) as total_expenses_this_month,
                COALESCE(SUM(net_profit), 0) as total_profit_this_month,
                COALESCE(AVG(net_profit), 0) as avg_profit_this_month
            FROM field_reports 
            WHERE YEAR(created_at) = YEAR(CURDATE()) 
            AND MONTH(created_at) = MONTH(CURDATE())
        ");
        $stmt->execute();
        $stats['this_month'] = $stmt->fetch();
        
        // Last Month totals (for comparison)
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reports_last_month,
                COALESCE(SUM(total_income), 0) as total_income_last_month,
                COALESCE(SUM(total_expenses), 0) as total_expenses_last_month,
                COALESCE(SUM(net_profit), 0) as total_profit_last_month
            FROM field_reports 
            WHERE YEAR(created_at) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
            AND MONTH(created_at) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
        ");
        $stmt->execute();
        $lastMonth = $stmt->fetch();
        
        // Calculate growth metrics
        $thisMonthRevenue = (float)$stats['this_month']['total_income_this_month'];
        $lastMonthRevenue = (float)$lastMonth['total_income_last_month'];
        $thisMonthProfit = (float)$stats['this_month']['total_profit_this_month'];
        $lastMonthProfit = (float)$lastMonth['total_profit_last_month'];
        $thisMonthJobs = (int)$stats['this_month']['total_reports_this_month'];
        $lastMonthJobs = (int)$lastMonth['total_reports_last_month'];
        
        // Calculate profit growth safely (handle negative profits)
        $profitGrowth = 0;
        if ($lastMonthProfit != 0) {
            $profitGrowth = (($thisMonthProfit - $lastMonthProfit) / abs($lastMonthProfit)) * 100;
        } elseif ($thisMonthProfit > 0 && $lastMonthProfit == 0) {
            // If we went from 0 to positive, that's 100% growth
            $profitGrowth = 100;
        } elseif ($thisMonthProfit < 0 && $lastMonthProfit == 0) {
            // If we went from 0 to negative, that's -100% growth
            $profitGrowth = -100;
        }
        
        $stats['growth'] = [
            'revenue_growth_mom' => $lastMonthRevenue > 0 ? (($thisMonthRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100 : ($thisMonthRevenue > 0 ? 100 : 0),
            'profit_growth_mom' => $profitGrowth,
            'jobs_growth_mom' => $lastMonthJobs > 0 ? (($thisMonthJobs - $lastMonthJobs) / $lastMonthJobs) * 100 : ($thisMonthJobs > 0 ? 100 : 0),
            'this_month_revenue' => $thisMonthRevenue,
            'last_month_revenue' => $lastMonthRevenue,
            'this_month_profit' => $thisMonthProfit,
            'last_month_profit' => $lastMonthProfit,
            'this_month_jobs' => $thisMonthJobs,
            'last_month_jobs' => $lastMonthJobs,
        ];
        
        // This Year totals
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_reports_this_year,
                COALESCE(SUM(total_income), 0) as total_income_this_year,
                COALESCE(SUM(net_profit), 0) as total_profit_this_year
            FROM field_reports 
            WHERE YEAR(created_at) = YEAR(CURDATE())
        ");
        $stmt->execute();
        $stats['this_year'] = $stmt->fetch();
        
        // Loan statistics (with error handling if table doesn't exist)
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_loans,
                    COALESCE(SUM(loan_amount), 0) as total_loan_amount,
                    COALESCE(SUM(outstanding_balance), 0) as total_outstanding
                FROM loans 
                WHERE status = 'active'
            ");
            $stmt->execute();
            $stats['loans'] = $stmt->fetch();
        } catch (PDOException $e) {
            // Table might not exist
            $stats['loans'] = ['total_loans' => 0, 'total_loan_amount' => 0, 'total_outstanding' => 0];
        }
        
        // Materials value and costs (with error handling if table doesn't exist)
        try {
            $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_value), 0) as total_materials_value FROM materials_inventory");
            $stmt->execute();
            $stats['materials'] = $stmt->fetch();
        } catch (PDOException $e) {
            // Table might not exist
            $stats['materials'] = ['total_materials_value' => 0];
        }
        
        // Rig fee debts (if table exists, otherwise use outstanding_rig_fee from field_reports)
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_debts,
                    COALESCE(SUM(outstanding_balance), 0) as total_debt_amount,
                    SUM(CASE WHEN status = 'bad_debt' THEN outstanding_balance ELSE 0 END) as bad_debt_amount
                FROM rig_fee_debts 
                WHERE status IN ('pending', 'partially_paid', 'bad_debt')
            ");
            $stmt->execute();
            $stats['rig_fee_debts'] = $stmt->fetch();
        } catch (PDOException $e) {
            // Table doesn't exist, use field_reports data instead
            $stats['rig_fee_debts'] = [
                'total_debts' => 0,
                'total_debt_amount' => (float)$stats['overall']['outstanding_rig_fees'],
                'bad_debt_amount' => 0
            ];
        }
        
        // Get cash on hand (latest days_balance from most recent report)
        $cashOnHand = 0;
        try {
            $stmt = $this->pdo->prepare("SELECT days_balance FROM field_reports ORDER BY created_at DESC, id DESC LIMIT 1");
            $stmt->execute();
            $latestReport = $stmt->fetch();
            $cashOnHand = (float)($latestReport['days_balance'] ?? 0);
        } catch (PDOException $e) {
            $cashOnHand = 0;
        }
        
        // Calculate assets and liabilities
        $totalAssets = (float)$stats['materials']['total_materials_value'] + (float)$stats['overall']['total_bank_deposits'] + $cashOnHand;
        $totalLiabilities = (float)$stats['loans']['total_outstanding'] + (float)$stats['overall']['outstanding_rig_fees'];
        
        // Additional financial ratios
        $totalProfit = (float)$stats['overall']['total_profit'];
        $totalIncome = (float)$stats['overall']['total_income'];
        $totalExpenses = (float)$stats['overall']['total_expenses'];
        
        // Return on Assets (ROA)
        $roa = $totalAssets > 0 ? ($totalProfit / $totalAssets) * 100 : 0;
        
        // Current Ratio (Current Assets / Current Liabilities)
        // For simplicity, treating all assets as current and all liabilities as current
        $currentRatio = $totalLiabilities > 0 ? ($totalAssets / $totalLiabilities) : ($totalAssets > 0 ? 999.99 : 0);
        
        // Debt Service Coverage Ratio (Net Income / Total Debt Service)
        // Using net profit as income, total liabilities as debt service
        $dscr = $totalLiabilities > 0 ? ($totalProfit / $totalLiabilities) : ($totalProfit > 0 ? 999.99 : 0);
        
        $stats['balance_sheet'] = [
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'net_worth' => $totalAssets - $totalLiabilities,
            'debt_to_asset_ratio' => $totalAssets > 0 ? ($totalLiabilities / $totalAssets) * 100 : 0,
            'cash_reserves' => (float)$stats['overall']['total_bank_deposits'],
            'cash_on_hand' => $cashOnHand,
            'materials_value' => (float)$stats['materials']['total_materials_value'],
            'return_on_assets' => $roa,
            'current_ratio' => $currentRatio,
            'debt_service_coverage_ratio' => $dscr,
        ];
        
        // Operational Efficiency Metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(AVG(total_duration), 0) as avg_job_duration_minutes,
                COALESCE(AVG(total_depth), 0) as avg_depth_per_job,
                COALESCE(SUM(total_duration), 0) as total_operating_hours,
                COALESCE(COUNT(DISTINCT rig_id), 0) as active_rigs
            FROM field_reports
            WHERE total_duration IS NOT NULL
        ");
        $stmt->execute();
        $stats['operational'] = $stmt->fetch();
        
        // Calculate utilization and efficiency
        $activeRigs = (int)$stats['operational']['active_rigs'];
        $daysInMonth = date('t'); // Current month days
        $expectedHoursPerRig = $daysInMonth * 8; // 8 hours per day assumption
        $totalOperatingHours = (float)$stats['operational']['total_operating_hours'] / 60; // Convert minutes to hours
        
        $stats['operational']['rig_utilization_rate'] = $activeRigs > 0 && $expectedHoursPerRig > 0 
            ? min(100, ($totalOperatingHours / ($activeRigs * $expectedHoursPerRig)) * 100) : 0;
        $daysSinceYearStart = max(1, (time() - strtotime("first day of this year")) / 86400);
        $stats['operational']['jobs_per_day'] = $totalJobs > 0 ? ($totalJobs / $daysSinceYearStart) : 0;
        
        // Top performing clients (by revenue)
        $stmt = $this->pdo->prepare("
            SELECT 
                c.client_name,
                COUNT(fr.id) as job_count,
                COALESCE(SUM(fr.total_income), 0) as total_revenue,
                COALESCE(SUM(fr.net_profit), 0) as total_profit,
                COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job
            FROM clients c
            LEFT JOIN field_reports fr ON c.id = fr.client_id
            GROUP BY c.id, c.client_name
            HAVING job_count > 0
            ORDER BY total_revenue DESC
            LIMIT 5
        ");
        $stmt->execute();
        $stats['top_clients'] = $stmt->fetchAll();
        
        // Top performing rigs
        $stmt = $this->pdo->prepare("
            SELECT 
                r.rig_name,
                r.rig_code,
                COUNT(fr.id) as job_count,
                COALESCE(SUM(fr.total_income), 0) as total_revenue,
                COALESCE(SUM(fr.net_profit), 0) as total_profit,
                COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
                COALESCE(AVG(fr.net_profit / NULLIF(fr.total_income, 0)), 0) * 100 as profit_margin
            FROM rigs r
            LEFT JOIN field_reports fr ON r.id = fr.rig_id
            GROUP BY r.id, r.rig_name, r.rig_code
            HAVING job_count > 0
            ORDER BY total_profit DESC
            LIMIT 5
        ");
        $stmt->execute();
        $stats['top_rigs'] = $stmt->fetchAll();
        
        // Job type distribution
        $stmt = $this->pdo->prepare("
            SELECT 
                job_type,
                COUNT(*) as job_count,
                COALESCE(SUM(total_income), 0) as total_revenue,
                COALESCE(SUM(net_profit), 0) as total_profit
            FROM field_reports
            GROUP BY job_type
        ");
        $stmt->execute();
        $stats['job_types'] = $stmt->fetchAll();
        
        // Cash flow metrics (last 30 days)
        // Note: total_expenses already includes total_wages, so we don't double-count
        // Cash outflow = cash_given + materials_cost + loans_amount + daily_expenses + wages
        // But since we don't have loans_amount in the table yet, we use total_expenses which includes it
        // For accurate cash flow: cash_outflow = cash_given + total_expenses (wages already included)
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(cash_received + momo_transfer), 0) as cash_inflow,
                COALESCE(SUM(cash_given + total_expenses), 0) as cash_outflow,
                COALESCE(SUM(bank_deposit), 0) as deposits,
                COALESCE(SUM(net_profit), 0) as net_cash_flow
            FROM field_reports
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $stats['cash_flow'] = $stmt->fetch();
        
        } catch (PDOException $e) {
            // Log error but return empty stats structure
            error_log("Dashboard stats error: " . $e->getMessage());
            // Return minimal structure to prevent dashboard errors
            return [
                'today' => ['total_reports_today' => 0, 'total_income_today' => 0, 'net_profit_today' => 0, 'money_banked_today' => 0],
                'overall' => ['total_reports' => 0, 'total_income' => 0, 'total_expenses' => 0, 'total_profit' => 0, 'outstanding_rig_fees' => 0],
                'financial_health' => ['profit_margin' => 0, 'gross_margin' => 0, 'expense_ratio' => 0, 'avg_profit_per_job' => 0, 'avg_revenue_per_job' => 0, 'avg_cost_per_job' => 0],
                'this_month' => ['total_reports_this_month' => 0, 'total_income_this_month' => 0, 'total_profit_this_month' => 0],
                'growth' => ['revenue_growth_mom' => 0, 'profit_growth_mom' => 0, 'jobs_growth_mom' => 0, 'this_month_revenue' => 0, 'last_month_revenue' => 0, 'this_month_profit' => 0, 'last_month_profit' => 0, 'this_month_jobs' => 0, 'last_month_jobs' => 0],
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
        } catch (Exception $e) {
            // Catch any other exceptions
            error_log("Dashboard stats error: " . $e->getMessage());
            return [];
        }
        
        // Cache for 1 hour
        if ($useCache) {
            $this->setCache($cacheKey, $stats, 3600);
        }
        
        return $stats;
    }
    
    // Get recent activity
    public function getRecentActivity($limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT fr.report_id, fr.site_name, fr.report_date, fr.net_profit, r.rig_name, c.client_name
            FROM field_reports fr
            LEFT JOIN rigs r ON fr.rig_id = r.id
            LEFT JOIN clients c ON fr.client_id = c.id
            ORDER BY fr.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    // Update materials inventory with transaction logging
    public function updateMaterialsInventory($materialType, $quantityUsed, $reportId = null) {
        $this->pdo->beginTransaction();
        try {
            // Update inventory
            $stmt = $this->pdo->prepare("
                UPDATE materials_inventory 
                SET quantity_used = quantity_used + ?, 
                    quantity_remaining = quantity_received - (quantity_used + ?),
                    total_value = (quantity_received - (quantity_used + ?)) * unit_cost,
                    last_updated = NOW()
                WHERE material_type = ?
            ");
            $stmt->execute([$quantityUsed, $quantityUsed, $quantityUsed, $materialType]);
            
            // Log transaction
            $material = $this->pdo->prepare("SELECT unit_cost FROM materials_inventory WHERE material_type = ?")->execute([$materialType]);
            $materialData = $this->pdo->prepare("SELECT unit_cost FROM materials_inventory WHERE material_type = ?");
            $materialData->execute([$materialType]);
            $materialRow = $materialData->fetch();
            $unitCost = $materialRow ? $materialRow['unit_cost'] : 0;
            
            $logStmt = $this->pdo->prepare("
                INSERT INTO material_transactions 
                (material_type, transaction_type, quantity, unit_cost, total_cost, report_id, created_by) 
                VALUES (?, 'used', ?, ?, ?, ?, ?)
            ");
            $logStmt->execute([
                $materialType,
                $quantityUsed,
                $unitCost,
                $quantityUsed * $unitCost,
                $reportId,
                $_SESSION['user_id'] ?? 1
            ]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    // Extract and save client from field report
    public function extractAndSaveClient($clientData) {
        if (empty($clientData['client_name'])) {
            return null;
        }
        
        // Check if client already exists
        $stmt = $this->pdo->prepare("SELECT id FROM clients WHERE client_name = ?");
        $stmt->execute([$clientData['client_name']]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Update if has additional info
            if (!empty($clientData['client_contact']) || !empty($clientData['email'])) {
                $updateStmt = $this->pdo->prepare("
                    UPDATE clients 
                    SET contact_person = COALESCE(?, contact_person),
                        contact_number = COALESCE(?, contact_number),
                        email = COALESCE(?, email),
                        address = COALESCE(?, address)
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $clientData['contact_person'] ?? null,
                    $clientData['client_contact'] ?? null,
                    $clientData['email'] ?? null,
                    $clientData['address'] ?? null,
                    $existing['id']
                ]);
            }
            return $existing['id'];
        } else {
            // Create new client
            $insertStmt = $this->pdo->prepare("
                INSERT INTO clients (client_name, contact_person, contact_number, email, address) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $clientData['client_name'],
                $clientData['contact_person'] ?? null,
                $clientData['client_contact'] ?? null,
                $clientData['email'] ?? null,
                $clientData['address'] ?? null
            ]);
            return $this->pdo->lastInsertId();
        }
    }
    
    // Cache management (graceful handling if table doesn't exist)
    public function getCache($key) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT cache_value FROM cache_stats 
                WHERE cache_key = ? AND expires_at > NOW()
            ");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? json_decode($result['cache_value'], true) : false;
        } catch (PDOException $e) {
            // Table doesn't exist, return false to bypass cache
            return false;
        }
    }
    
    public function setCache($key, $value, $ttl = 3600) {
        try {
            $this->pdo->prepare("
                INSERT INTO cache_stats (cache_key, cache_value, expires_at) 
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
                ON DUPLICATE KEY UPDATE 
                    cache_value = ?,
                    expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND)
            ")->execute([
                $key,
                json_encode($value),
                $ttl,
                json_encode($value),
                $ttl
            ]);
            
            // Clean old cache entries
            $this->pdo->exec("DELETE FROM cache_stats WHERE expires_at < NOW()");
        } catch (PDOException $e) {
            // Table doesn't exist, silently fail - caching is optional
        }
    }
    
    public function clearCache($pattern = null) {
        try {
            if ($pattern) {
                $this->pdo->prepare("DELETE FROM cache_stats WHERE cache_key LIKE ?")->execute(["%$pattern%"]);
            } else {
                $this->pdo->exec("DELETE FROM cache_stats");
            }
        } catch (PDOException $e) {
            // Table doesn't exist, silently fail
        }
    }
}

$abbis = new ABBISFunctions();
?>
