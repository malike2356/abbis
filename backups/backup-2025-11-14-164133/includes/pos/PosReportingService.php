<?php

declare(strict_types=1);

class PosReportingService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getDashboardSnapshot(int $userId): array
    {
        $todayStart = (new DateTimeImmutable('today'))->format('Y-m-d 00:00:00');
        $todayEnd = (new DateTimeImmutable('tomorrow'))->format('Y-m-d 00:00:00');
        $weekStart = (new DateTimeImmutable('monday this week'))->format('Y-m-d 00:00:00');
        $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');

        return [
            'summary' => [
                'today' => $this->getSalesAggregate($todayStart, $todayEnd),
                'week' => $this->getSalesAggregate($weekStart, $todayEnd),
                'month' => $this->getSalesAggregate($monthStart, $todayEnd),
            ],
            'material_returns' => [
                'today' => $this->getMaterialReturnsAggregate($todayStart, $todayEnd),
                'week' => $this->getMaterialReturnsAggregate($weekStart, $todayEnd),
                'month' => $this->getMaterialReturnsAggregate($monthStart, $todayEnd),
            ],
            'top_products' => $this->getTopProducts($weekStart, $todayEnd, 5),
            'top_cashiers' => $this->getTopCashiers($weekStart, $todayEnd, 5),
            'inventory_alerts' => $this->getInventoryAlerts(5),
            'recent_sales' => $this->getRecentSales(8),
            'cashier' => $userId ? $this->getCashierInsights($userId, $todayStart, $todayEnd, $monthStart) : null,
        ];
    }

    private function getSalesAggregate(string $start, string $end): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COUNT(*) AS transactions,
                COALESCE(SUM(amount_paid), 0) AS total_paid
            FROM pos_sales
            WHERE sale_timestamp >= :start
              AND sale_timestamp < :end
              AND sale_status = 'completed'
        ");
        $stmt->execute([
            ':start' => $start,
            ':end' => $end,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_amount' => 0, 'transactions' => 0, 'total_paid' => 0];

        $transactions = (int) $row['transactions'];
        $avg = $transactions > 0 ? ((float) $row['total_amount']) / $transactions : 0;

        return [
            'total_amount' => (float) $row['total_amount'],
            'transactions' => $transactions,
            'average_ticket' => round($avg, 2),
        ];
    }

    private function getTopProducts(string $start, string $end, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.name,
                SUM(si.quantity) AS total_quantity,
                SUM(si.line_total) AS total_value
            FROM pos_sale_items si
            INNER JOIN pos_sales s ON s.id = si.sale_id
            INNER JOIN pos_products p ON p.id = si.product_id
            WHERE s.sale_timestamp >= :start
              AND s.sale_timestamp < :end
              AND s.sale_status = 'completed'
            GROUP BY p.id, p.name
            ORDER BY total_quantity DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function ($row) {
            return [
                'product_id' => (int) $row['id'],
                'name' => $row['name'],
                'quantity_sold' => (float) $row['total_quantity'],
                'revenue' => (float) $row['total_value'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getTopCashiers(string $start, string $end, int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.cashier_id,
                COALESCE(u.full_name, u.username, CONCAT('User #', s.cashier_id)) AS cashier_name,
                COUNT(*) AS transactions,
                SUM(s.total_amount) AS total_amount
            FROM pos_sales s
            LEFT JOIN users u ON u.id = s.cashier_id
            WHERE s.sale_timestamp >= :start
              AND s.sale_timestamp < :end
              AND s.sale_status = 'completed'
              AND s.cashier_id IS NOT NULL
            GROUP BY s.cashier_id, cashier_name
            ORDER BY total_amount DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':start', $start);
        $stmt->bindValue(':end', $end);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function ($row) {
            return [
                'cashier_id' => (int) $row['cashier_id'],
                'name' => $row['cashier_name'],
                'sales_count' => (int) $row['transactions'],
                'revenue' => (float) $row['total_amount'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getInventoryAlerts(int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                inv.id,
                inv.store_id,
                s.store_name,
                p.name as product_name,
                inv.quantity_on_hand,
                inv.reorder_level
            FROM pos_inventory inv
            INNER JOIN pos_products p ON p.id = inv.product_id
            INNER JOIN pos_stores s ON s.id = inv.store_id
            WHERE inv.reorder_level > 0 AND inv.quantity_on_hand <= inv.reorder_level
            ORDER BY inv.quantity_on_hand ASC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentSales(int $limit): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.id,
                s.sale_number,
                s.sale_timestamp,
                s.total_amount,
                s.amount_paid,
                s.change_due,
                st.store_name,
                COALESCE(u.full_name, u.username, CONCAT('User #', s.cashier_id)) AS cashier_name
            FROM pos_sales s
            INNER JOIN pos_stores st ON st.id = s.store_id
            LEFT JOIN users u ON u.id = s.cashier_id
            ORDER BY s.sale_timestamp DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function ($row) {
            return [
                'sale_id' => (int) $row['id'],
                'sale_number' => $row['sale_number'],
                'sale_timestamp' => $row['sale_timestamp'],
                'total_amount' => (float) $row['total_amount'],
                'amount_paid' => (float) $row['amount_paid'],
                'change_due' => (float) $row['change_due'],
                'store_name' => $row['store_name'],
                'cashier_name' => $row['cashier_name'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function getMaterialReturnsAggregate(string $start, string $end): array
    {
        try {
            // Check if table exists
            $this->pdo->query("SELECT 1 FROM pos_material_returns LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, return empty stats
            return [
                'total_returns' => 0,
                'pending' => 0,
                'accepted' => 0,
                'rejected' => 0,
                'cancelled' => 0,
                'total_quantity' => 0,
                'total_quantity_received' => 0,
            ];
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) AS total_returns,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COALESCE(SUM(actual_quantity_received), 0) AS total_quantity_received
            FROM pos_material_returns
            WHERE requested_at >= :start
              AND requested_at < :end
        ");
        $stmt->execute([
            ':start' => $start,
            ':end' => $end,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_returns' => 0,
            'pending' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'cancelled' => 0,
            'total_quantity' => 0,
            'total_quantity_received' => 0,
        ];

        return [
            'total_returns' => (int) $row['total_returns'],
            'pending' => (int) $row['pending'],
            'accepted' => (int) $row['accepted'],
            'rejected' => (int) $row['rejected'],
            'cancelled' => (int) $row['cancelled'],
            'total_quantity' => (float) $row['total_quantity'],
            'total_quantity_received' => (float) $row['total_quantity_received'],
        ];
    }

    private function getCashierInsights(int $userId, string $todayStart, string $todayEnd, string $monthStart): array
    {
        $dailyStmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COUNT(*) AS transactions
            FROM pos_sales
            WHERE cashier_id = :user_id
              AND sale_timestamp >= :start
              AND sale_timestamp < :end
              AND sale_status = 'completed'
        ");
        $dailyStmt->execute([
            ':user_id' => $userId,
            ':start' => $todayStart,
            ':end' => $todayEnd,
        ]);
        $today = $dailyStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_amount' => 0, 'transactions' => 0];

        $monthStmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(total_amount), 0) AS total_amount,
                COUNT(*) AS transactions
            FROM pos_sales
            WHERE cashier_id = :user_id
              AND sale_timestamp >= :start
              AND sale_timestamp < :end
              AND sale_status = 'completed'
        ");
        $monthStmt->execute([
            ':user_id' => $userId,
            ':start' => $monthStart,
            ':end' => $todayEnd,
        ]);
        $month = $monthStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_amount' => 0, 'transactions' => 0];

        $recentStmt = $this->pdo->prepare("
            SELECT sale_number, sale_timestamp, total_amount
            FROM pos_sales
            WHERE cashier_id = :user_id
              AND sale_status = 'completed'
            ORDER BY sale_timestamp DESC
            LIMIT 3
        ");
        $recentStmt->execute([':user_id' => $userId]);

        return [
            'today' => [
                'total_amount' => (float) $today['total_amount'],
                'transactions' => (int) $today['transactions'],
            ],
            'month' => [
                'total_amount' => (float) $month['total_amount'],
                'transactions' => (int) $month['transactions'],
            ],
            'recent_sales' => array_map(static function ($row) {
                return [
                    'sale_number' => $row['sale_number'],
                    'sale_timestamp' => $row['sale_timestamp'],
                    'total_amount' => (float) $row['total_amount'],
                ];
            }, $recentStmt->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    /* ---------- Advanced Analytics ---------- */

    public function getSalesReport(string $startDate, string $endDate, ?int $storeId = null, ?int $cashierId = null): array
    {
        $params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }
        if ($cashierId) {
            $where .= " AND s.cashier_id = :cashier_id";
            $params[':cashier_id'] = $cashierId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) AS total_transactions,
                COALESCE(SUM(s.subtotal_amount), 0) AS total_subtotal,
                COALESCE(SUM(s.discount_total), 0) AS total_discounts,
                COALESCE(SUM(s.tax_total), 0) AS total_tax,
                COALESCE(SUM(s.total_amount), 0) AS total_revenue,
                COALESCE(SUM(s.amount_paid), 0) AS total_paid,
                COALESCE(SUM(s.change_due), 0) AS total_change,
                COALESCE(AVG(s.total_amount), 0) AS avg_transaction,
                COUNT(DISTINCT s.cashier_id) AS unique_cashiers,
                COUNT(DISTINCT s.customer_id) AS unique_customers
            FROM pos_sales s
            WHERE {$where}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Payment method breakdown
        $paymentStmt = $this->pdo->prepare("
            SELECT 
                sp.payment_method,
                COUNT(*) AS transaction_count,
                SUM(sp.amount) AS total_amount
            FROM pos_sale_payments sp
            INNER JOIN pos_sales s ON s.id = sp.sale_id
            WHERE {$where}
            GROUP BY sp.payment_method
            ORDER BY total_amount DESC
        ");
        $paymentStmt->execute($params);
        $paymentMethods = $paymentStmt->fetchAll(PDO::FETCH_ASSOC);

        // Hourly breakdown
        $hourlyStmt = $this->pdo->prepare("
            SELECT 
                HOUR(s.sale_timestamp) AS hour,
                COUNT(*) AS transactions,
                SUM(s.total_amount) AS revenue
            FROM pos_sales s
            WHERE {$where}
            GROUP BY HOUR(s.sale_timestamp)
            ORDER BY hour
        ");
        $hourlyStmt->execute($params);
        $hourlyData = $hourlyStmt->fetchAll(PDO::FETCH_ASSOC);

        // Daily breakdown
        $dailyStmt = $this->pdo->prepare("
            SELECT 
                DATE(s.sale_timestamp) AS sale_date,
                COUNT(*) AS transactions,
                SUM(s.total_amount) AS revenue
            FROM pos_sales s
            WHERE {$where}
            GROUP BY DATE(s.sale_timestamp)
            ORDER BY sale_date
        ");
        $dailyStmt->execute($params);
        $dailyData = $dailyStmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'summary' => [
                'total_transactions' => (int)($summary['total_transactions'] ?? 0),
                'total_subtotal' => (float)($summary['total_subtotal'] ?? 0),
                'total_discounts' => (float)($summary['total_discounts'] ?? 0),
                'total_tax' => (float)($summary['total_tax'] ?? 0),
                'total_revenue' => (float)($summary['total_revenue'] ?? 0),
                'total_paid' => (float)($summary['total_paid'] ?? 0),
                'total_change' => (float)($summary['total_change'] ?? 0),
                'avg_transaction' => (float)($summary['avg_transaction'] ?? 0),
                'unique_cashiers' => (int)($summary['unique_cashiers'] ?? 0),
                'unique_customers' => (int)($summary['unique_customers'] ?? 0),
            ],
            'payment_methods' => $paymentMethods,
            'hourly_breakdown' => $hourlyData,
            'daily_breakdown' => $dailyData,
        ];
    }

    public function getProductPerformanceReport(string $startDate, string $endDate, ?int $storeId = null, int $limit = 50): array
    {
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
            ':limit' => $limit,
        ];
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.sku,
                p.name,
                p.unit_price,
                SUM(si.quantity) AS total_quantity_sold,
                SUM(si.line_total) AS total_revenue,
                COUNT(DISTINCT si.sale_id) AS times_sold,
                AVG(si.unit_price) AS avg_selling_price,
                SUM(si.discount_amount) AS total_discounts
            FROM pos_sale_items si
            INNER JOIN pos_sales s ON s.id = si.sale_id
            INNER JOIN pos_products p ON p.id = si.product_id
            WHERE {$where}
            GROUP BY p.id, p.sku, p.name, p.unit_price
            ORDER BY total_revenue DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute($params);

        return array_map(static function ($row) {
            return [
                'product_id' => (int)$row['id'],
                'sku' => $row['sku'],
                'name' => $row['name'],
                'unit_price' => (float)$row['unit_price'],
                'total_quantity_sold' => (float)$row['total_quantity_sold'],
                'total_revenue' => (float)$row['total_revenue'],
                'times_sold' => (int)$row['times_sold'],
                'avg_selling_price' => (float)$row['avg_selling_price'],
                'total_discounts' => (float)$row['total_discounts'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getCashierPerformanceReport(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
        ];
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                s.cashier_id,
                COALESCE(u.full_name, u.username, CONCAT('User #', s.cashier_id)) AS cashier_name,
                COUNT(*) AS total_transactions,
                SUM(s.total_amount) AS total_revenue,
                AVG(s.total_amount) AS avg_transaction,
                SUM(s.discount_total) AS total_discounts,
                MIN(s.sale_timestamp) AS first_sale,
                MAX(s.sale_timestamp) AS last_sale
            FROM pos_sales s
            LEFT JOIN users u ON u.id = s.cashier_id
            WHERE {$where}
            GROUP BY s.cashier_id, cashier_name
            ORDER BY total_revenue DESC
        ");
        $stmt->execute($params);

        return array_map(static function ($row) {
            return [
                'cashier_id' => (int)$row['cashier_id'],
                'cashier_name' => $row['cashier_name'],
                'total_transactions' => (int)$row['total_transactions'],
                'total_revenue' => (float)$row['total_revenue'],
                'avg_transaction' => (float)$row['avg_transaction'],
                'total_discounts' => (float)$row['total_discounts'],
                'first_sale' => $row['first_sale'],
                'last_sale' => $row['last_sale'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getInventoryAlertsDetailed(?int $storeId = null): array
    {
        $params = [];
        $where = "inv.reorder_level > 0 AND inv.quantity_on_hand <= inv.reorder_level";
        
        if ($storeId) {
            $where .= " AND inv.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                inv.id,
                inv.store_id,
                s.store_name,
                p.id AS product_id,
                p.sku,
                p.name AS product_name,
                inv.quantity_on_hand,
                inv.reorder_level,
                inv.reorder_quantity,
                inv.average_cost,
                CASE 
                    WHEN inv.quantity_on_hand = 0 THEN 'out_of_stock'
                    WHEN inv.quantity_on_hand < inv.reorder_level * 0.5 THEN 'critical'
                    ELSE 'low_stock'
                END AS alert_level,
                DATEDIFF(NOW(), inv.last_restocked_at) AS days_since_restock
            FROM pos_inventory inv
            INNER JOIN pos_products p ON p.id = inv.product_id
            INNER JOIN pos_stores s ON s.id = inv.store_id
            WHERE {$where}
            ORDER BY 
                CASE alert_level
                    WHEN 'out_of_stock' THEN 1
                    WHEN 'critical' THEN 2
                    ELSE 3
                END,
                inv.quantity_on_hand ASC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMaterialReturnsReport(string $startDate, string $endDate, ?string $status = null): array
    {
        try {
            // Check if table exists
            $this->pdo->query("SELECT 1 FROM pos_material_returns LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist
            return [
                'summary' => [
                    'total_returns' => 0,
                    'pending' => 0,
                    'accepted' => 0,
                    'rejected' => 0,
                    'cancelled' => 0,
                    'total_quantity' => 0,
                    'total_quantity_received' => 0,
                ],
                'by_type' => [],
                'by_status' => [],
            ];
        }

        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
        ];
        $where = "requested_at >= :start AND requested_at <= :end";
        
        if ($status) {
            $where .= " AND status = :status";
            $params[':status'] = $status;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) AS total_returns,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(quantity), 0) AS total_quantity,
                COALESCE(SUM(actual_quantity_received), 0) AS total_quantity_received
            FROM pos_material_returns
            WHERE {$where}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Breakdown by material type
        $typeStmt = $this->pdo->prepare("
            SELECT 
                material_type,
                COUNT(*) AS count,
                SUM(quantity) AS total_quantity,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count
            FROM pos_material_returns
            WHERE {$where}
            GROUP BY material_type
            ORDER BY count DESC
        ");
        $typeStmt->execute($params);

        // Breakdown by status
        $statusStmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) AS count,
                SUM(quantity) AS total_quantity
            FROM pos_material_returns
            WHERE {$where}
            GROUP BY status
            ORDER BY count DESC
        ");
        $statusStmt->execute($params);

        return [
            'summary' => [
                'total_returns' => (int)($summary['total_returns'] ?? 0),
                'pending' => (int)($summary['pending'] ?? 0),
                'accepted' => (int)($summary['accepted'] ?? 0),
                'rejected' => (int)($summary['rejected'] ?? 0),
                'cancelled' => (int)($summary['cancelled'] ?? 0),
                'total_quantity' => (float)($summary['total_quantity'] ?? 0),
                'total_quantity_received' => (float)($summary['total_quantity_received'] ?? 0),
            ],
            'by_type' => $typeStmt->fetchAll(PDO::FETCH_ASSOC),
            'by_status' => $statusStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getRefundReport(string $startDate, string $endDate, ?int $storeId = null): array
    {
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end' => $endDate . ' 23:59:59',
        ];
        $where = "r.refund_timestamp >= :start AND r.refund_timestamp <= :end AND r.refund_status = 'completed'";
        
        if ($storeId) {
            $where .= " AND r.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) AS total_refunds,
                SUM(r.total_amount) AS total_refunded,
                AVG(r.total_amount) AS avg_refund,
                COUNT(DISTINCT r.refund_method) AS refund_methods_count
            FROM pos_refunds r
            WHERE {$where}
        ");
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Refund method breakdown
        $methodStmt = $this->pdo->prepare("
            SELECT 
                refund_method,
                COUNT(*) AS count,
                SUM(total_amount) AS total
            FROM pos_refunds r
            WHERE {$where}
            GROUP BY refund_method
        ");
        $methodStmt->execute($params);

        return [
            'summary' => [
                'total_refunds' => (int)($summary['total_refunds'] ?? 0),
                'total_refunded' => (float)($summary['total_refunded'] ?? 0),
                'avg_refund' => (float)($summary['avg_refund'] ?? 0),
            ],
            'by_method' => $methodStmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    public function getChartData(string $type, ?int $storeId = null, int $days = 30): array
    {
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$days} days"));

        switch ($type) {
            case 'daily_sales':
                return $this->getDailySalesData($startDate, $endDate, $storeId);
            case 'payment_methods':
                return $this->getPaymentMethodData($startDate, $endDate, $storeId);
            case 'hourly_sales':
                return $this->getHourlySalesData($startDate, $endDate, $storeId);
            case 'top_products_chart':
                return $this->getTopProductsChartData($startDate, $endDate, $storeId, 10);
            default:
                return [];
        }
    }

    private function getDailySalesData(string $startDate, string $endDate, ?int $storeId): array
    {
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        $params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(s.sale_timestamp) AS sale_date,
                COUNT(*) AS transactions,
                COALESCE(SUM(s.total_amount), 0) AS revenue
            FROM pos_sales s
            WHERE {$where}
            GROUP BY DATE(s.sale_timestamp)
            ORDER BY sale_date ASC
        ");
        $stmt->execute($params);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($data, 'sale_date'),
            'transactions' => array_column($data, 'transactions'),
            'revenue' => array_map('floatval', array_column($data, 'revenue')),
        ];
    }

    private function getPaymentMethodData(string $startDate, string $endDate, ?int $storeId): array
    {
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        $params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                sp.payment_method,
                COUNT(*) AS transaction_count,
                SUM(sp.amount) AS total_amount
            FROM pos_sale_payments sp
            INNER JOIN pos_sales s ON s.id = sp.sale_id
            WHERE {$where}
            GROUP BY sp.payment_method
            ORDER BY total_amount DESC
        ");
        $stmt->execute($params);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($data, 'payment_method'),
            'amounts' => array_map('floatval', array_column($data, 'total_amount')),
            'counts' => array_map('intval', array_column($data, 'transaction_count')),
        ];
    }

    private function getHourlySalesData(string $startDate, string $endDate, ?int $storeId): array
    {
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        $params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(s.sale_timestamp) AS hour,
                COUNT(*) AS transactions,
                COALESCE(SUM(s.total_amount), 0) AS revenue
            FROM pos_sales s
            WHERE {$where}
            GROUP BY HOUR(s.sale_timestamp)
            ORDER BY hour ASC
        ");
        $stmt->execute($params);
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Fill in missing hours with 0
        $hourlyData = [];
        for ($i = 0; $i < 24; $i++) {
            $hourlyData[$i] = ['transactions' => 0, 'revenue' => 0];
        }
        
        foreach ($data as $row) {
            $hour = (int)$row['hour'];
            $hourlyData[$hour] = [
                'transactions' => (int)$row['transactions'],
                'revenue' => (float)$row['revenue'],
            ];
        }
        
        return [
            'labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)),
            'transactions' => array_column($hourlyData, 'transactions'),
            'revenue' => array_column($hourlyData, 'revenue'),
        ];
    }

    private function getTopProductsChartData(string $startDate, string $endDate, ?int $storeId, int $limit): array
    {
        $where = "s.sale_timestamp >= :start AND s.sale_timestamp <= :end AND s.sale_status = 'completed'";
        $params = [':start' => $startDate . ' 00:00:00', ':end' => $endDate . ' 23:59:59'];
        
        if ($storeId) {
            $where .= " AND s.store_id = :store_id";
            $params[':store_id'] = $storeId;
        }

        $stmt = $this->pdo->prepare("
            SELECT 
                p.name,
                SUM(si.quantity) AS total_quantity,
                SUM(si.line_total) AS total_revenue
            FROM pos_sale_items si
            INNER JOIN pos_sales s ON s.id = si.sale_id
            INNER JOIN pos_products p ON p.id = si.product_id
            WHERE {$where}
            GROUP BY p.id, p.name
            ORDER BY total_revenue DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'labels' => array_column($data, 'name'),
            'quantities' => array_map('floatval', array_column($data, 'total_quantity')),
            'revenue' => array_map('floatval', array_column($data, 'total_revenue')),
        ];
    }
}


