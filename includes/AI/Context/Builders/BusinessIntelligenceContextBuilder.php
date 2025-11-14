<?php

require_once __DIR__ . '/../ContextBuilderInterface.php';
require_once __DIR__ . '/../ContextSlice.php';
require_once __DIR__ . '/../../../../config/database.php';

class BusinessIntelligenceContextBuilder implements ContextBuilderInterface
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    public function getKey(): string
    {
        return 'business_intelligence';
    }

    public function supports(array $options): bool
    {
        // Always available - provides general business context
        return true;
    }

    public function build(array $options): array
    {
        $slices = [];

        // 1. Top Clients by Revenue
        $topClients = $this->getTopClients();
        if (!empty($topClients)) {
            $slices[] = new ContextSlice(
                'top_clients',
                $topClients,
                priority: 25,
                approxTokens: 300
            );
        }

        // 2. Recent Field Reports Summary
        $recentReports = $this->getRecentFieldReports();
        if (!empty($recentReports)) {
            $slices[] = new ContextSlice(
                'recent_reports',
                $recentReports,
                priority: 25,
                approxTokens: 400
            );
        }

        // 3. Dashboard KPIs
        $kpis = $this->getDashboardKPIs();
        if (!empty($kpis)) {
            $slices[] = new ContextSlice(
                'dashboard_kpis',
                $kpis,
                priority: 20,
                approxTokens: 500
            );
        }

        // 4. Today's Priorities
        $priorities = $this->getTodaysPriorities();
        if (!empty($priorities)) {
            $slices[] = new ContextSlice(
                'todays_priorities',
                $priorities,
                priority: 30,
                approxTokens: 350
            );
        }

        // 5. Top Performing Rigs
        $topRigs = $this->getTopRigs();
        if (!empty($topRigs)) {
            $slices[] = new ContextSlice(
                'top_rigs',
                $topRigs,
                priority: 24,
                approxTokens: 250
            );
        }

        // 6. Financial Health Summary
        $financialHealth = $this->getFinancialHealth();
        if (!empty($financialHealth)) {
            $slices[] = new ContextSlice(
                'financial_health',
                $financialHealth,
                priority: 22,
                approxTokens: 300
            );
        }

        // 7. Pending Quote Requests
        $pendingQuotes = $this->getPendingQuotes();
        if (!empty($pendingQuotes)) {
            $slices[] = new ContextSlice(
                'pending_quotes',
                $pendingQuotes,
                priority: 26,
                approxTokens: 200
            );
        }

        // 8. Operational Metrics
        $operational = $this->getOperationalMetrics();
        if (!empty($operational)) {
            $slices[] = new ContextSlice(
                'operational_metrics',
                $operational,
                priority: 23,
                approxTokens: 200
            );
        }

        // 9. POS & Ecommerce Data
        $posData = $this->getPOSData();
        if (!empty($posData)) {
            $slices[] = new ContextSlice(
                'pos_ecommerce',
                $posData,
                priority: 24,
                approxTokens: 400
            );
        }

        // 10. Materials Inventory
        $materialsData = $this->getMaterialsData();
        if (!empty($materialsData)) {
            $slices[] = new ContextSlice(
                'materials_inventory',
                $materialsData,
                priority: 25,
                approxTokens: 350
            );
        }

        // 11. Recent Payments & Transactions
        $paymentsData = $this->getPaymentsData();
        if (!empty($paymentsData)) {
            $slices[] = new ContextSlice(
                'payments_transactions',
                $paymentsData,
                priority: 22,
                approxTokens: 300
            );
        }

        // 12. Catalog & Products
        $catalogData = $this->getCatalogData();
        if (!empty($catalogData)) {
            $slices[] = new ContextSlice(
                'catalog_products',
                $catalogData,
                priority: 21,
                approxTokens: 300
            );
        }

        return $slices;
    }

    private function getTopClients(int $limit = 5): array
    {
        try {
            // First, try to get clients with jobs (by revenue)
            $stmt = $this->pdo->prepare("
                SELECT 
                    c.id,
                    c.client_name,
                    c.email,
                    c.phone,
                    COUNT(fr.id) as job_count,
                    COALESCE(SUM(fr.total_income), 0) as total_revenue,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job,
                    MAX(fr.report_date) as last_job_date
                FROM clients c
                LEFT JOIN field_reports fr ON c.id = fr.client_id
                GROUP BY c.id, c.client_name, c.email, c.phone
                HAVING job_count > 0
                ORDER BY total_revenue DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If no clients with jobs, get all clients (fallback)
            if (empty($results)) {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        c.id,
                        c.client_name,
                        c.email,
                        c.phone,
                        0 as job_count,
                        0 as total_revenue,
                        0 as total_profit,
                        0 as avg_profit_per_job,
                        NULL as last_job_date
                    FROM clients c
                    ORDER BY c.client_name
                    LIMIT :limit
                ");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Format for AI consumption
            return array_map(function($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['client_name'],
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'total_jobs' => (int) $row['job_count'],
                    'total_revenue' => (float) $row['total_revenue'],
                    'total_profit' => (float) $row['total_profit'],
                    'avg_profit_per_job' => (float) $row['avg_profit_per_job'],
                    'last_job_date' => $row['last_job_date'],
                ];
            }, $results);
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching top clients: ' . $e->getMessage());
            return [];
        }
    }

    private function getRecentFieldReports(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    fr.id,
                    fr.report_date,
                    fr.project_name,
                    fr.location,
                    fr.status,
                    c.client_name,
                    fr.total_income,
                    fr.total_expenses,
                    fr.net_profit,
                    fr.created_at
                FROM field_reports fr
                LEFT JOIN clients c ON fr.client_id = c.id
                ORDER BY fr.created_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                return [
                    'id' => (int) $row['id'],
                    'date' => $row['report_date'],
                    'project' => $row['project_name'],
                    'location' => $row['location'],
                    'status' => $row['status'],
                    'client' => $row['client_name'],
                    'income' => (float) $row['total_income'],
                    'expenses' => (float) $row['total_expenses'],
                    'profit' => (float) $row['net_profit'],
                    'created' => $row['created_at'],
                ];
            }, $results);
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching recent reports: ' . $e->getMessage());
            return [];
        }
    }

    private function getDashboardKPIs(): array
    {
        try {
            // Today's metrics
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as reports_today,
                    COALESCE(SUM(total_income), 0) as income_today,
                    COALESCE(SUM(total_expenses), 0) as expenses_today,
                    COALESCE(SUM(net_profit), 0) as profit_today
                FROM field_reports 
                WHERE DATE(created_at) = CURDATE()
            ");
            $stmt->execute();
            $today = $stmt->fetch(PDO::FETCH_ASSOC);

            // This month
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as reports_this_month,
                    COALESCE(SUM(total_income), 0) as income_this_month,
                    COALESCE(SUM(total_expenses), 0) as expenses_this_month,
                    COALESCE(SUM(net_profit), 0) as profit_this_month
                FROM field_reports 
                WHERE YEAR(created_at) = YEAR(CURDATE()) 
                AND MONTH(created_at) = MONTH(CURDATE())
            ");
            $stmt->execute();
            $thisMonth = $stmt->fetch(PDO::FETCH_ASSOC);

            // Overall totals
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_reports,
                    COALESCE(SUM(total_income), 0) as total_income,
                    COALESCE(SUM(total_expenses), 0) as total_expenses,
                    COALESCE(SUM(net_profit), 0) as total_profit,
                    COALESCE(SUM(bank_deposit), 0) as total_deposits,
                    COALESCE(AVG(net_profit), 0) as avg_profit_per_job
                FROM field_reports
            ");
            $stmt->execute();
            $overall = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'today' => [
                    'reports' => (int) $today['reports_today'],
                    'income' => (float) $today['income_today'],
                    'expenses' => (float) $today['expenses_today'],
                    'profit' => (float) $today['profit_today'],
                ],
                'this_month' => [
                    'reports' => (int) $thisMonth['reports_this_month'],
                    'income' => (float) $thisMonth['income_this_month'],
                    'expenses' => (float) $thisMonth['expenses_this_month'],
                    'profit' => (float) $thisMonth['profit_this_month'],
                ],
                'overall' => [
                    'total_reports' => (int) $overall['total_reports'],
                    'total_income' => (float) $overall['total_income'],
                    'total_expenses' => (float) $overall['total_expenses'],
                    'total_profit' => (float) $overall['total_profit'],
                    'total_deposits' => (float) $overall['total_deposits'],
                    'avg_profit_per_job' => (float) $overall['avg_profit_per_job'],
                ],
            ];
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching KPIs: ' . $e->getMessage());
            return [];
        }
    }

    private function getTodaysPriorities(): array
    {
        $priorities = [];

        try {
            // Pending follow-ups (today and overdue)
            $stmt = $this->pdo->prepare("
                SELECT 
                    cf.id,
                    cf.subject,
                    cf.type,
                    cf.priority,
                    cf.scheduled_date,
                    c.client_name,
                    CASE 
                        WHEN cf.scheduled_date < NOW() THEN 'overdue'
                        WHEN DATE(cf.scheduled_date) = CURDATE() THEN 'today'
                        ELSE 'upcoming'
                    END as urgency
                FROM client_followups cf
                LEFT JOIN clients c ON cf.client_id = c.id
                WHERE cf.status = 'scheduled'
                AND (DATE(cf.scheduled_date) <= CURDATE() OR DATE(cf.scheduled_date) = DATE_ADD(CURDATE(), INTERVAL 1 DAY))
                ORDER BY 
                    CASE urgency
                        WHEN 'overdue' THEN 1
                        WHEN 'today' THEN 2
                        ELSE 3
                    END,
                    CASE cf.priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        ELSE 4
                    END,
                    cf.scheduled_date ASC
                LIMIT 10
            ");
            $stmt->execute();
            $followups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($followups)) {
                $priorities['followups'] = array_map(function($row) {
                    return [
                        'id' => (int) $row['id'],
                        'subject' => $row['subject'],
                        'type' => $row['type'],
                        'priority' => $row['priority'],
                        'scheduled_date' => $row['scheduled_date'],
                        'client' => $row['client_name'],
                        'urgency' => $row['urgency'],
                    ];
                }, $followups);
            }
        } catch (PDOException $e) {
            // Table might not exist
            error_log('[AI Context] client_followups table might not exist: ' . $e->getMessage());
        }

        try {
            // Pending quote requests
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    location,
                    status,
                    estimated_budget,
                    created_at
                FROM cms_quote_requests
                WHERE status IN ('pending', 'new', 'in_progress')
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($quotes)) {
                $priorities['pending_quotes'] = array_map(function($row) {
                    return [
                        'id' => (int) $row['id'],
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'location' => $row['location'],
                        'status' => $row['status'],
                        'budget' => $row['estimated_budget'] ? (float) $row['estimated_budget'] : null,
                        'created' => $row['created_at'],
                    ];
                }, $quotes);
            }
        } catch (PDOException $e) {
            // Table might not exist
            error_log('[AI Context] cms_quote_requests table might not exist: ' . $e->getMessage());
        }

        try {
            // Pending rig requests
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    request_number,
                    requester_name,
                    location_address,
                    status,
                    priority,
                    number_of_boreholes,
                    created_at
                FROM rig_requests
                WHERE status IN ('pending', 'new', 'in_progress')
                ORDER BY 
                    CASE priority
                        WHEN 'urgent' THEN 1
                        WHEN 'high' THEN 2
                        WHEN 'medium' THEN 3
                        ELSE 4
                    END,
                    created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $rigRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rigRequests)) {
                $priorities['pending_rig_requests'] = array_map(function($row) {
                    return [
                        'id' => (int) $row['id'],
                        'request_number' => $row['request_number'],
                        'requester' => $row['requester_name'],
                        'location' => $row['location_address'],
                        'status' => $row['status'],
                        'priority' => $row['priority'],
                        'boreholes' => (int) $row['number_of_boreholes'],
                        'created' => $row['created_at'],
                    ];
                }, $rigRequests);
            }
        } catch (PDOException $e) {
            // Table might not exist
            error_log('[AI Context] rig_requests table might not exist: ' . $e->getMessage());
        }

        return $priorities;
    }

    private function getTopRigs(int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    r.id,
                    r.rig_name,
                    r.rig_code,
                    COUNT(fr.id) as job_count,
                    COALESCE(SUM(fr.total_income), 0) as total_revenue,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit_per_job
                FROM rigs r
                LEFT JOIN field_reports fr ON r.id = fr.rig_id
                WHERE r.status = 'active'
                GROUP BY r.id, r.rig_name, r.rig_code
                HAVING job_count > 0
                ORDER BY total_profit DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['rig_name'],
                    'code' => $row['rig_code'],
                    'total_jobs' => (int) $row['job_count'],
                    'total_revenue' => (float) $row['total_revenue'],
                    'total_profit' => (float) $row['total_profit'],
                    'avg_profit_per_job' => (float) $row['avg_profit_per_job'],
                ];
            }, $results);
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching top rigs: ' . $e->getMessage());
            return [];
        }
    }

    private function getFinancialHealth(): array
    {
        try {
            // Get overall financial data
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(SUM(total_income), 0) as total_income,
                    COALESCE(SUM(total_expenses), 0) as total_expenses,
                    COALESCE(SUM(net_profit), 0) as total_profit,
                    COALESCE(SUM(bank_deposit), 0) as total_deposits,
                    COALESCE(SUM(outstanding_rig_fee), 0) as outstanding_fees
                FROM field_reports
            ");
            $stmt->execute();
            $financial = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalIncome = (float) $financial['total_income'];
            $totalExpenses = (float) $financial['total_expenses'];
            $totalProfit = (float) $financial['total_profit'];

            // Calculate ratios
            $profitMargin = $totalIncome > 0 ? ($totalProfit / $totalIncome) * 100 : 0;
            $expenseRatio = $totalIncome > 0 ? ($totalExpenses / $totalIncome) * 100 : 0;

            // Get materials value
            $materialsValue = 0;
            try {
                $materialsStmt = $this->pdo->query("
                    SELECT COALESCE(SUM(total_value), 0) as total_value
                    FROM materials_inventory
                ");
                $materialsValue = (float) ($materialsStmt->fetchColumn() ?: 0);
            } catch (PDOException $e) {
                // Table might not exist
            }

            // Get loans
            $loans = ['total_outstanding' => 0];
            try {
                $loansStmt = $this->pdo->query("
                    SELECT COALESCE(SUM(outstanding_balance), 0) as total_outstanding
                    FROM loans
                    WHERE status = 'active'
                ");
                $loans['total_outstanding'] = (float) ($loansStmt->fetchColumn() ?: 0);
            } catch (PDOException $e) {
                // Table might not exist
            }

            return [
                'total_income' => $totalIncome,
                'total_expenses' => $totalExpenses,
                'total_profit' => $totalProfit,
                'profit_margin_percent' => round($profitMargin, 2),
                'expense_ratio_percent' => round($expenseRatio, 2),
                'total_deposits' => (float) $financial['total_deposits'],
                'outstanding_fees' => (float) $financial['outstanding_fees'],
                'materials_value' => $materialsValue,
                'total_loans' => $loans['total_outstanding'],
            ];
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching financial health: ' . $e->getMessage());
            return [];
        }
    }

    private function getPendingQuotes(int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    name,
                    email,
                    phone,
                    location,
                    status,
                    estimated_budget,
                    created_at
                FROM cms_quote_requests
                WHERE status IN ('pending', 'new', 'in_progress')
                ORDER BY created_at DESC
                LIMIT :limit
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(function($row) {
                return [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'location' => $row['location'],
                    'status' => $row['status'],
                    'budget' => $row['estimated_budget'] ? (float) $row['estimated_budget'] : null,
                    'created' => $row['created_at'],
                ];
            }, $results);
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching pending quotes: ' . $e->getMessage());
            return [];
        }
    }

    private function getOperationalMetrics(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COALESCE(AVG(total_duration), 0) as avg_job_duration_minutes,
                    COALESCE(AVG(total_depth), 0) as avg_depth_per_job,
                    COALESCE(SUM(total_duration), 0) as total_operating_minutes,
                    COALESCE(COUNT(DISTINCT rig_id), 0) as active_rigs,
                    COALESCE(COUNT(*), 0) as total_jobs
                FROM field_reports
                WHERE total_duration IS NOT NULL
            ");
            $stmt->execute();
            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalOperatingHours = (float) $metrics['total_operating_minutes'] / 60;
            $activeRigs = (int) $metrics['active_rigs'];
            $totalJobs = (int) $metrics['total_jobs'];

            return [
                'avg_job_duration_minutes' => (float) $metrics['avg_job_duration_minutes'],
                'avg_depth_per_job' => (float) $metrics['avg_depth_per_job'],
                'total_operating_hours' => round($totalOperatingHours, 2),
                'active_rigs' => $activeRigs,
                'total_jobs' => $totalJobs,
                'jobs_per_rig' => $activeRigs > 0 ? round($totalJobs / $activeRigs, 2) : 0,
            ];
        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching operational metrics: ' . $e->getMessage());
            return [];
        }
    }

    private function getPOSData(): array
    {
        $data = [];
        
        try {
            // Recent POS sales (last 7 days)
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_sales,
                    COALESCE(SUM(total_amount), 0) as total_revenue,
                    COALESCE(AVG(total_amount), 0) as avg_sale_amount,
                    MAX(created_at) as last_sale_date
                FROM pos_sales
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");
            $stmt->execute();
            $recentSales = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($recentSales) {
                $data['recent_sales'] = [
                    'total_sales' => (int) $recentSales['total_sales'],
                    'total_revenue' => (float) $recentSales['total_revenue'],
                    'avg_sale_amount' => (float) $recentSales['avg_sale_amount'],
                    'last_sale_date' => $recentSales['last_sale_date'],
                ];
            }

            // Top selling products (last 30 days)
            $stmt = $this->pdo->prepare("
                SELECT 
                    pp.product_name,
                    SUM(psi.quantity) as total_sold,
                    COALESCE(SUM(psi.total_price), 0) as total_revenue
                FROM pos_sale_items psi
                INNER JOIN pos_products pp ON psi.product_id = pp.id
                INNER JOIN pos_sales ps ON psi.sale_id = ps.id
                WHERE ps.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY pp.id, pp.product_name
                ORDER BY total_sold DESC
                LIMIT 10
            ");
            $stmt->execute();
            $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($topProducts)) {
                $data['top_products'] = array_map(function($row) {
                    return [
                        'product_name' => $row['product_name'],
                        'total_sold' => (int) $row['total_sold'],
                        'total_revenue' => (float) $row['total_revenue'],
                    ];
                }, $topProducts);
            }

            // Inventory alerts (low stock)
            $stmt = $this->pdo->prepare("
                SELECT 
                    pp.product_name,
                    pi.quantity as available_quantity,
                    pi.reorder_level,
                    pi.store_name
                FROM pos_inventory pi
                INNER JOIN pos_products pp ON pi.product_id = pp.id
                WHERE pi.quantity <= pi.reorder_level
                AND pi.quantity > 0
                ORDER BY pi.quantity ASC
                LIMIT 10
            ");
            $stmt->execute();
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($lowStock)) {
                $data['low_stock_alerts'] = array_map(function($row) {
                    return [
                        'product_name' => $row['product_name'],
                        'available_quantity' => (int) $row['available_quantity'],
                        'reorder_level' => (int) $row['reorder_level'],
                        'store_name' => $row['store_name'],
                    ];
                }, $lowStock);
            }

            // CMS orders (last 30 days)
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(*) as total_orders,
                        COALESCE(SUM(total_amount), 0) as total_revenue,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders
                    FROM cms_orders
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $cmsOrders = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($cmsOrders) {
                    $data['cms_orders'] = [
                        'total_orders' => (int) $cmsOrders['total_orders'],
                        'total_revenue' => (float) $cmsOrders['total_revenue'],
                        'pending_orders' => (int) $cmsOrders['pending_orders'],
                        'completed_orders' => (int) $cmsOrders['completed_orders'],
                    ];
                }
            } catch (PDOException $e) {
                // Table might not exist
                error_log('[AI Context] cms_orders table might not exist: ' . $e->getMessage());
            }

        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching POS data: ' . $e->getMessage());
        }

        return $data;
    }

    private function getMaterialsData(): array
    {
        $data = [];
        
        try {
            // Materials inventory summary
            $stmt = $this->pdo->query("
                SELECT 
                    material_type,
                    COUNT(*) as item_count,
                    SUM(quantity_received) as total_received,
                    SUM(quantity_used) as total_used,
                    SUM(quantity_remaining) as total_remaining,
                    SUM(total_value) as total_value
                FROM materials_inventory
                GROUP BY material_type
                ORDER BY total_remaining DESC
            ");
            $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($materials)) {
                $data['by_type'] = array_map(function($row) {
                    return [
                        'material_type' => $row['material_type'],
                        'item_count' => (int) $row['item_count'],
                        'total_received' => (float) $row['total_received'],
                        'total_used' => (float) $row['total_used'],
                        'total_remaining' => (float) $row['total_remaining'],
                        'total_value' => (float) $row['total_value'],
                    ];
                }, $materials);
            }

            // Low stock materials
            $stmt = $this->pdo->prepare("
                SELECT 
                    material_type,
                    material_name,
                    quantity_remaining,
                    unit_cost,
                    total_value
                FROM materials_inventory
                WHERE quantity_remaining <= 10
                ORDER BY quantity_remaining ASC
                LIMIT 10
            ");
            $stmt->execute();
            $lowStock = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($lowStock)) {
                $data['low_stock'] = array_map(function($row) {
                    return [
                        'material_type' => $row['material_type'],
                        'material_name' => $row['material_name'],
                        'quantity_remaining' => (float) $row['quantity_remaining'],
                        'unit_cost' => (float) $row['unit_cost'],
                        'total_value' => (float) $row['total_value'],
                    ];
                }, $lowStock);
            }

            // Recent material returns
            try {
                $stmt = $this->pdo->prepare("
                    SELECT 
                        COUNT(*) as total_returns,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_returns,
                        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_returns,
                        COALESCE(SUM(actual_quantity_received), 0) as total_quantity_returned
                    FROM pos_material_returns
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $stmt->execute();
                $returns = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($returns) {
                    $data['material_returns'] = [
                        'total_returns' => (int) $returns['total_returns'],
                        'pending_returns' => (int) $returns['pending_returns'],
                        'accepted_returns' => (int) $returns['accepted_returns'],
                        'total_quantity_returned' => (float) $returns['total_quantity_returned'],
                    ];
                }
            } catch (PDOException $e) {
                // Table might not exist
                error_log('[AI Context] pos_material_returns table might not exist: ' . $e->getMessage());
            }

        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching materials data: ' . $e->getMessage());
        }

        return $data;
    }

    private function getPaymentsData(): array
    {
        $data = [];
        
        try {
            // Recent payments (last 30 days)
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_payments,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COUNT(CASE WHEN payment_method = 'cash' THEN 1 END) as cash_payments,
                    COUNT(CASE WHEN payment_method = 'bank_transfer' THEN 1 END) as bank_payments,
                    MAX(created_at) as last_payment_date
                FROM payments
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $payments = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payments) {
                $data['recent_payments'] = [
                    'total_payments' => (int) $payments['total_payments'],
                    'total_amount' => (float) $payments['total_amount'],
                    'cash_payments' => (int) $payments['cash_payments'],
                    'bank_payments' => (int) $payments['bank_payments'],
                    'last_payment_date' => $payments['last_payment_date'],
                ];
            }

            // Outstanding amounts from field reports
            $stmt = $this->pdo->query("
                SELECT 
                    COALESCE(SUM(outstanding_rig_fee), 0) as total_outstanding_fees,
                    COALESCE(SUM(bank_deposit), 0) as total_deposits,
                    COUNT(CASE WHEN outstanding_rig_fee > 0 THEN 1 END) as reports_with_outstanding
                FROM field_reports
            ");
            $outstanding = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($outstanding) {
                $data['outstanding'] = [
                    'total_outstanding_fees' => (float) $outstanding['total_outstanding_fees'],
                    'total_deposits' => (float) $outstanding['total_deposits'],
                    'reports_with_outstanding' => (int) $outstanding['reports_with_outstanding'],
                ];
            }

        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching payments data: ' . $e->getMessage());
        }

        return $data;
    }

    private function getCatalogData(): array
    {
        $data = [];
        
        try {
            // Catalog summary
            $stmt = $this->pdo->query("
                SELECT 
                    COUNT(*) as total_items,
                    COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_items,
                    COUNT(CASE WHEN is_active = 0 THEN 1 END) as inactive_items,
                    COUNT(CASE WHEN stock_quantity <= 0 THEN 1 END) as out_of_stock,
                    COUNT(CASE WHEN stock_quantity > 0 AND stock_quantity <= 10 THEN 1 END) as low_stock,
                    COALESCE(SUM(stock_quantity * unit_price), 0) as total_inventory_value
                FROM catalog_items
            ");
            $catalog = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($catalog) {
                $data['summary'] = [
                    'total_items' => (int) $catalog['total_items'],
                    'active_items' => (int) $catalog['active_items'],
                    'inactive_items' => (int) $catalog['inactive_items'],
                    'out_of_stock' => (int) $catalog['out_of_stock'],
                    'low_stock' => (int) $catalog['low_stock'],
                    'total_inventory_value' => (float) $catalog['total_inventory_value'],
                ];
            }

            // Top categories by item count
            $stmt = $this->pdo->prepare("
                SELECT 
                    category,
                    COUNT(*) as item_count,
                    COALESCE(SUM(stock_quantity), 0) as total_stock,
                    COALESCE(SUM(stock_quantity * unit_price), 0) as category_value
                FROM catalog_items
                WHERE category IS NOT NULL AND category != ''
                GROUP BY category
                ORDER BY item_count DESC
                LIMIT 10
            ");
            $stmt->execute();
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($categories)) {
                $data['by_category'] = array_map(function($row) {
                    return [
                        'category' => $row['category'],
                        'item_count' => (int) $row['item_count'],
                        'total_stock' => (float) $row['total_stock'],
                        'category_value' => (float) $row['category_value'],
                    ];
                }, $categories);
            }

        } catch (PDOException $e) {
            error_log('[AI Context] Error fetching catalog data: ' . $e->getMessage());
        }

        return $data;
    }
}

