<?php
/**
 * Customer Statement API
 * Fetches all transactions for a client/customer/agent/contractor
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth->requireAuth();

header('Content-Type: application/json');

$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$month = $_GET['month'] ?? null; // Format: YYYY-MM (for backward compatibility)

if ($clientId <= 0) {
    jsonResponse(['success' => false, 'message' => 'Client ID is required'], 400);
}

try {
    $pdo = getDBConnection();
    
    // Get client information
    $clientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
    $clientStmt->execute([$clientId]);
    $client = $clientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$client) {
        jsonResponse(['success' => false, 'message' => 'Client not found'], 404);
    }
    
    // Determine date range
    if ($month) {
        // Parse month (YYYY-MM) - backward compatibility
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate)); // Last day of month
    } else if (!$startDate || !$endDate) {
        // Default to last 12 months
        $startDate = date('Y-m-01', strtotime('-12 months'));
        $endDate = date('Y-m-t');
    }
    
    // Validate dates
    if (!strtotime($startDate) || !strtotime($endDate)) {
        $startDate = date('Y-m-01', strtotime('-12 months'));
        $endDate = date('Y-m-t');
    }
    
    // Ensure start date is before end date
    if (strtotime($startDate) > strtotime($endDate)) {
        $temp = $startDate;
        $startDate = $endDate;
        $endDate = $temp;
    }
    
    $transactions = [];
    
    // 1. Field Reports (Jobs/Invoices)
    $reportsStmt = $pdo->prepare("
        SELECT 
            fr.id,
            fr.report_id,
            fr.report_date,
            'field_report' as transaction_type,
            'Job/Service' as description,
            fr.total_income as credit,
            0.00 as debit,
            fr.total_income as balance,
            fr.rig_fee_collected,
            fr.cash_received,
            fr.momo_transfer,
            fr.bank_deposit,
            fr.contract_sum,
            fr.materials_income,
            fr.total_expenses as expenses,
            fr.net_profit,
            r.rig_name,
            r.rig_code,
            fr.job_type,
            fr.site_name
        FROM field_reports fr
        LEFT JOIN rigs r ON r.id = fr.rig_id
        WHERE fr.client_id = ?
        AND fr.report_date >= ? AND fr.report_date <= ?
        ORDER BY fr.report_date ASC, fr.id ASC
    ");
    $reportsStmt->execute([$clientId, $startDate, $endDate]);
    $reports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($reports as $report) {
        $transactions[] = [
            'id' => $report['id'],
            'reference' => $report['report_id'],
            'date' => $report['report_date'],
            'type' => 'field_report',
            'description' => 'Job: ' . $report['site_name'] . ' (' . $report['rig_name'] . ')',
            'credit' => floatval($report['credit']),
            'debit' => 0.00,
            'balance' => 0.00, // Will calculate running balance
            'details' => [
                'rig_fee_collected' => floatval($report['rig_fee_collected']),
                'cash_received' => floatval($report['cash_received']),
                'momo_transfer' => floatval($report['momo_transfer']),
                'bank_deposit' => floatval($report['bank_deposit']),
                'contract_sum' => floatval($report['contract_sum']),
                'materials_income' => floatval($report['materials_income']),
                'expenses' => floatval($report['expenses']),
                'net_profit' => floatval($report['net_profit']),
                'job_type' => $report['job_type']
            ]
        ];
    }
    
    // 2. POS Sales (if client is linked to POS customer)
    try {
        $posSalesStmt = $pdo->prepare("
            SELECT 
                s.id,
                s.sale_number,
                s.sale_timestamp,
                'pos_sale' as transaction_type,
                CONCAT('POS Sale #', s.sale_number) as description,
                s.total_amount as credit,
                0.00 as debit,
                s.total_amount as balance,
                s.payment_method,
                s.amount_paid,
                s.change_due,
                st.store_name
            FROM pos_sales s
            LEFT JOIN pos_stores st ON st.id = s.store_id
            WHERE s.customer_id = ?
            AND DATE(s.sale_timestamp) >= ? AND DATE(s.sale_timestamp) <= ?
            ORDER BY s.sale_timestamp ASC
        ");
        $posSalesStmt->execute([$clientId, $startDate, $endDate]);
        $posSales = $posSalesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($posSales as $sale) {
            $transactions[] = [
                'id' => $sale['id'],
                'reference' => $sale['sale_number'],
                'date' => date('Y-m-d', strtotime($sale['sale_timestamp'])),
                'type' => 'pos_sale',
                'description' => 'POS Sale at ' . ($sale['store_name'] ?? 'Store'),
                'credit' => floatval($sale['credit']),
                'debit' => 0.00,
                'balance' => 0.00,
                'details' => [
                    'payment_method' => $sale['payment_method'],
                    'amount_paid' => floatval($sale['amount_paid']),
                    'change_due' => floatval($sale['change_due'])
                ]
            ];
        }
    } catch (PDOException $e) {
        // POS tables might not exist, ignore
    }
    
    // 3. CMS Orders (if client is linked to CMS customer)
    try {
        $cmsOrdersStmt = $pdo->prepare("
            SELECT 
                o.id,
                o.order_number,
                o.order_date,
                'cms_order' as transaction_type,
                CONCAT('Online Order #', o.order_number) as description,
                o.total_amount as credit,
                0.00 as debit,
                o.total_amount as balance,
                o.status,
                o.payment_method,
                o.shipping_address
            FROM cms_orders o
            WHERE o.customer_id = ?
            AND DATE(o.order_date) >= ? AND DATE(o.order_date) <= ?
            ORDER BY o.order_date ASC
        ");
        $cmsOrdersStmt->execute([$clientId, $startDate, $endDate]);
        $cmsOrders = $cmsOrdersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($cmsOrders as $order) {
            $transactions[] = [
                'id' => $order['id'],
                'reference' => $order['order_number'],
                'date' => $order['order_date'],
                'type' => 'cms_order',
                'description' => 'Online Order',
                'credit' => floatval($order['credit']),
                'debit' => 0.00,
                'balance' => 0.00,
                'details' => [
                    'status' => $order['status'],
                    'payment_method' => $order['payment_method']
                ]
            ];
        }
    } catch (PDOException $e) {
        // CMS orders table might not exist, ignore
    }
    
    // 4. Payments/Deposits (if there's a payments table)
    try {
        $paymentsStmt = $pdo->prepare("
            SELECT 
                id,
                payment_reference,
                payment_date,
                'payment' as transaction_type,
                description,
                amount as credit,
                0.00 as debit,
                amount as balance,
                payment_method
            FROM client_payments
            WHERE client_id = ?
            AND payment_date >= ? AND payment_date <= ?
            ORDER BY payment_date ASC
        ");
        $paymentsStmt->execute([$clientId, $startDate, $endDate]);
        $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($payments as $payment) {
            $transactions[] = [
                'id' => $payment['id'],
                'reference' => $payment['payment_reference'] ?? 'PAY-' . $payment['id'],
                'date' => $payment['payment_date'],
                'type' => 'payment',
                'description' => $payment['description'] ?? 'Payment Received',
                'credit' => floatval($payment['credit']),
                'debit' => 0.00,
                'balance' => 0.00,
                'details' => [
                    'payment_method' => $payment['payment_method'] ?? 'Cash'
                ]
            ];
        }
    } catch (PDOException $e) {
        // Payments table might not exist, ignore
    }
    
    // Sort all transactions by date
    usort($transactions, function($a, $b) {
        $dateA = strtotime($a['date']);
        $dateB = strtotime($b['date']);
        if ($dateA === $dateB) {
            return $a['id'] <=> $b['id'];
        }
        return $dateA <=> $dateB;
    });
    
    // Calculate running balance
    $runningBalance = 0.00;
    foreach ($transactions as &$transaction) {
        $runningBalance += $transaction['credit'] - $transaction['debit'];
        $transaction['balance'] = $runningBalance;
    }
    
    // Calculate summary
    $summary = [
        'total_credit' => array_sum(array_column($transactions, 'credit')),
        'total_debit' => array_sum(array_column($transactions, 'debit')),
        'opening_balance' => 0.00, // Could calculate from previous period
        'closing_balance' => $runningBalance,
        'transaction_count' => count($transactions)
    ];
    
    // Get company configuration
    $configStmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('company_name', 'company_address', 'company_contact', 'company_email', 'company_logo', 'company_tagline')");
    $companyConfig = [];
    while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
        $companyConfig[$row['config_key']] = $row['config_value'];
    }
    
    jsonResponse([
        'success' => true,
        'client' => $client,
        'transactions' => $transactions,
        'summary' => $summary,
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'company' => $companyConfig
    ]);
    
} catch (Throwable $e) {
    error_log('[Customer Statement API] Error: ' . $e->getMessage());
    error_log('[Customer Statement API] Stack trace: ' . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => 'Failed to generate statement: ' . $e->getMessage()], 500);
}

