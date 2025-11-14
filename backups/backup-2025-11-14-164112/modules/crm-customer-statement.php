<?php
/**
 * Customer Statement Page
 * Professional bank statement-style transaction history
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

// Check if this should be rendered as a standalone page (for printing)
$standalone = isset($_GET['standalone']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/pdf') !== false);

$pdo = getDBConnection();
$clientId = isset($clientId) ? (int)$clientId : (isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0);

// Get date range from query parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01', strtotime('-12 months')); // Default to 12 months ago
$endDate = $_GET['end_date'] ?? date('Y-m-t'); // Default to end of current month

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

if ($clientId <= 0) {
    header('Location: crm.php?action=clients');
    exit;
}

// Get client information
$clientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$clientStmt->execute([$clientId]);
$client = $clientStmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header('Location: crm.php?action=clients');
    exit;
}

// Get company configuration
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('company_name', 'company_address', 'company_contact', 'company_email', 'company_logo', 'company_tagline')");
$companyConfig = [];
while ($row = $configStmt->fetch(PDO::FETCH_ASSOC)) {
    $companyConfig[$row['config_key']] = $row['config_value'];
}

// Get logo path
$logoPath = null;
if (!empty($companyConfig['company_logo'])) {
    $logoFile = ROOT_PATH . '/' . $companyConfig['company_logo'];
    if (file_exists($logoFile)) {
        $logoPath = app_base_path() . '/' . $companyConfig['company_logo'];
    }
}

// Fetch statement data directly from database (avoid API call to prevent freezing)
$statementData = null;
try {
    
    // Get transactions directly
    $reportsStmt = $pdo->prepare("
        SELECT 
            fr.id,
            fr.report_id,
            fr.report_date,
            'field_report' as transaction_type,
            CONCAT('Job: ', fr.site_name, ' (', COALESCE(r.rig_name, 'N/A'), ')') as description,
            fr.total_income as credit,
            0.00 as debit,
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
    
    $transactions = [];
    foreach ($reports as $report) {
        $transactions[] = [
            'id' => $report['id'],
            'reference' => $report['report_id'],
            'date' => $report['report_date'],
            'type' => 'field_report',
            'description' => $report['description'],
            'credit' => floatval($report['credit']),
            'debit' => 0.00,
            'balance' => 0.00,
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
    
    // Calculate running balance
    $runningBalance = 0.00;
    foreach ($transactions as &$transaction) {
        $runningBalance += $transaction['credit'] - $transaction['debit'];
        $transaction['balance'] = $runningBalance;
    }
    
    $statementData = [
        'client' => $client,
        'transactions' => $transactions,
        'summary' => [
            'total_credit' => array_sum(array_column($transactions, 'credit')),
            'total_debit' => array_sum(array_column($transactions, 'debit')),
            'opening_balance' => 0.00,
            'closing_balance' => $runningBalance,
            'transaction_count' => count($transactions)
        ],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'company' => $companyConfig
    ];
} catch (PDOException $e) {
    error_log("Error fetching statement data: " . $e->getMessage());
    $statementData = [
        'client' => $client,
        'transactions' => [],
        'summary' => [
            'total_credit' => 0.00,
            'total_debit' => 0.00,
            'opening_balance' => 0.00,
            'closing_balance' => 0.00,
            'transaction_count' => 0
        ],
        'period' => [
            'start_date' => $startDate,
            'end_date' => $endDate
        ],
        'company' => $companyConfig
    ];
}

$transactions = $statementData['transactions'] ?? [];
$summary = $statementData['summary'] ?? [];
$period = $statementData['period'] ?? [];

// Generate statement number
$statementNumber = 'STMT-' . date('Ymd', strtotime($startDate)) . '-' . date('Ymd', strtotime($endDate)) . '-' . str_pad($clientId, 6, '0', STR_PAD_LEFT);

$page_title = 'Customer Statement - ' . e($client['client_name']);

// If standalone mode, render full page and exit (don't include in CRM layout)
if ($standalone) {
    // Output full standalone HTML page
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            color: #1a202c;
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }
        
        .statement-container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        /* Landscape layout for standalone print view */
        @media (min-width: 1024px) {
            .statement-container {
                max-width: 1400px;
            }
        }
        
        .statement-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            display: flex;
            justify-content: space-between;
            align-items: start;
        }
        
        .company-info {
            flex: 1;
        }
        
        .company-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            background: white;
            padding: 8px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .company-name {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .company-tagline {
            font-size: 13px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .company-details {
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.8;
        }
        
        .statement-meta {
            text-align: right;
            color: white;
        }
        
        .statement-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .statement-number {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .statement-date {
            font-size: 13px;
            opacity: 0.85;
        }
        
        .statement-body {
            padding: 30px;
        }
        
        .client-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 4px solid #667eea;
        }
        
        .client-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 10px;
        }
        
        .client-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            font-size: 13px;
            color: #4a5568;
        }
        
        .client-detail-item {
            display: flex;
            align-items: start;
            gap: 8px;
        }
        
        .client-detail-label {
            font-weight: 600;
            min-width: 80px;
        }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-section label {
            font-weight: 600;
            color: #4a5568;
        }
        
        .filter-section input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        
        .filter-section select {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        
        .filter-section button {
            padding: 8px 16px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            font-size: 13px;
        }
        
        .filter-section button:hover {
            background: #5568d3;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        
        .summary-card-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        
        .summary-card-value {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
        }
        
        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .transactions-table thead {
            background: #667eea;
            color: white;
        }
        
        .transactions-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .transactions-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        
        .transactions-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .transaction-date {
            color: #4a5568;
            font-weight: 600;
        }
        
        .transaction-reference {
            color: #667eea;
            font-weight: 600;
            font-family: 'Courier New', monospace;
        }
        
        .transaction-description {
            color: #1a202c;
        }
        
        .transaction-amount {
            text-align: right;
            font-weight: 600;
        }
        
        .transaction-credit {
            color: #10b981;
        }
        
        .transaction-debit {
            color: #ef4444;
        }
        
        .transaction-balance {
            text-align: right;
            font-weight: 700;
            color: #1a202c;
        }
        
        .statement-footer {
            background: #f8f9fa;
            padding: 25px 30px;
            border-top: 2px solid #e2e8f0;
            margin-top: 30px;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            font-size: 12px;
            color: #718096;
        }
        
        .footer-section h4 {
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .no-print { 
            margin-top: 30px; 
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 5px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }
        .btn-primary {
            background: #0ea5e9;
            color: white;
        }
        .btn-primary:hover {
            background: #0284c7;
        }
        .btn-outline {
            background: white;
            color: #0ea5e9;
            border: 2px solid #0ea5e9;
        }
        .btn-outline:hover {
            background: #f0f9ff;
        }
        
        @media print {
            /* Page setup - landscape orientation */
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
            
            body { 
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .statement-container {
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .filter-section {
                display: none !important;
            }
            
            /* Maintain statement design in print */
            .statement-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                page-break-inside: avoid;
                page-break-after: avoid;
                padding: 20px !important;
            }
            
            .statement-body {
                padding: 20px !important;
            }
            
            .client-section {
                page-break-inside: avoid;
                margin-bottom: 15px !important;
            }
            
            .summary-cards {
                page-break-inside: avoid;
                margin-bottom: 15px !important;
                display: grid !important;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)) !important;
            }
            
            .transactions-table {
                page-break-inside: auto;
                width: 100% !important;
                font-size: 11px !important;
            }
            
            .transactions-table thead {
                display: table-header-group !important;
                background: #667eea !important;
                color: white !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            
            .transactions-table thead tr {
                page-break-inside: avoid;
                page-break-after: avoid;
            }
            
            .transactions-table tbody tr {
                page-break-inside: avoid;
            }
            
            .transactions-table tfoot {
                display: table-footer-group !important;
                background: #f8f9fa !important;
            }
            
            .transactions-table td,
            .transactions-table th {
                padding: 8px !important;
            }
            
            .statement-footer {
                page-break-inside: avoid;
                page-break-before: auto;
                padding: 15px !important;
            }
            
            /* Ensure colors print correctly */
            .transaction-credit {
                color: #10b981 !important;
            }
            
            .transaction-debit {
                color: #ef4444 !important;
            }
            
            .summary-card-value {
                color: #1a202c !important;
            }
            
            /* Hide links and show only text */
            a[href]:after {
                content: none !important;
            }
        }
    </style>
</head>
<body>
    
    <div class="statement-container">
        <!-- Statement Header -->
        <div class="statement-header">
            <div class="company-info">
                <?php if ($logoPath): ?>
                <img src="<?php echo e($logoPath); ?>" alt="Company Logo" class="company-logo">
                <?php endif; ?>
                <div class="company-name"><?php echo e($companyConfig['company_name'] ?? 'ABBIS'); ?></div>
                <?php if (!empty($companyConfig['company_tagline'])): ?>
                <div class="company-tagline"><?php echo e($companyConfig['company_tagline']); ?></div>
                <?php endif; ?>
                <div class="company-details">
                    <?php if (!empty($companyConfig['company_address'])): ?>
                    <div>📍 <?php echo e($companyConfig['company_address']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($companyConfig['company_contact'])): ?>
                    <div>📞 <?php echo e($companyConfig['company_contact']); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($companyConfig['company_email'])): ?>
                    <div>✉️ <?php echo e($companyConfig['company_email']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="statement-meta">
                <div class="statement-title">Account Statement</div>
                <div class="statement-number">Statement #: <?php echo e($statementNumber); ?></div>
                <div class="statement-date">
                    Period: <?php echo date('F d, Y', strtotime($period['start_date'])); ?> - <?php echo date('F d, Y', strtotime($period['end_date'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Statement Body -->
        <div class="statement-body">
            <!-- Client Information -->
            <div class="client-section">
                <div class="client-name"><?php echo e($client['client_name']); ?></div>
                <div class="client-details">
                    <div class="client-detail-item">
                        <span class="client-detail-label">Client ID:</span>
                        <span><?php echo str_pad($clientId, 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <?php if (!empty($client['company_type'])): ?>
                    <div class="client-detail-item">
                        <span class="client-detail-label">Type:</span>
                        <span><?php echo e($client['company_type']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['contact_person'])): ?>
                    <div class="client-detail-item">
                        <span class="client-detail-label">Contact:</span>
                        <span><?php echo e($client['contact_person']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['contact_number'])): ?>
                    <div class="client-detail-item">
                        <span class="client-detail-label">Phone:</span>
                        <span><?php echo e($client['contact_number']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['email'])): ?>
                    <div class="client-detail-item">
                        <span class="client-detail-label">Email:</span>
                        <span><?php echo e($client['email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($client['address'])): ?>
                    <div class="client-detail-item" style="grid-column: 1 / -1;">
                        <span class="client-detail-label">Address:</span>
                        <span><?php echo e($client['address']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section no-print">
                <label for="startDate">From Date:</label>
                <input type="date" id="startDate" value="<?php echo e($startDate); ?>" style="padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; background: white;">
                
                <label for="endDate">To Date:</label>
                <input type="date" id="endDate" value="<?php echo e($endDate); ?>" style="padding: 8px 12px; border: 1px solid #cbd5e0; border-radius: 6px; font-size: 14px; background: white;">
                
                <button onclick="applyDateFilter()">Apply Period</button>
                <button onclick="setQuickPeriod('month')" style="background: #10b981;">This Month</button>
                <button onclick="setQuickPeriod('quarter')" style="background: #10b981;">This Quarter</button>
                <button onclick="setQuickPeriod('year')" style="background: #10b981;">This Year</button>
                <button onclick="setQuickPeriod('all')" style="background: #3b82f6;">All Time</button>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-card-label">Opening Balance</div>
                    <div class="summary-card-value"><?php echo formatCurrency($summary['opening_balance'] ?? 0); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-label">Total Credits</div>
                    <div class="summary-card-value" style="color: #10b981;"><?php echo formatCurrency($summary['total_credit'] ?? 0); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-label">Total Debits</div>
                    <div class="summary-card-value" style="color: #ef4444;"><?php echo formatCurrency($summary['total_debit'] ?? 0); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-label">Closing Balance</div>
                    <div class="summary-card-value" style="color: #667eea;"><?php echo formatCurrency($summary['closing_balance'] ?? 0); ?></div>
                </div>
                <div class="summary-card">
                    <div class="summary-card-label">Transactions</div>
                    <div class="summary-card-value"><?php echo number_format($summary['transaction_count'] ?? 0); ?></div>
                </div>
            </div>
            
            <!-- Transactions Table -->
            <?php if (empty($transactions)): ?>
            <div style="text-align: center; padding: 60px 20px; color: #718096;">
                <div style="font-size: 48px; margin-bottom: 15px;">📋</div>
                <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">No Transactions Found</div>
                <div style="font-size: 14px;">No transactions recorded for the selected period.</div>
            </div>
            <?php else: ?>
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Reference</th>
                        <th>Description</th>
                        <th style="text-align: right;">Credit</th>
                        <th style="text-align: right;">Debit</th>
                        <th style="text-align: right;">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td class="transaction-date"><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                        <td class="transaction-reference"><?php echo e($transaction['reference']); ?></td>
                        <td class="transaction-description"><?php echo e($transaction['description']); ?></td>
                        <td class="transaction-amount transaction-credit">
                            <?php if ($transaction['credit'] > 0): ?>
                                <?php echo formatCurrency($transaction['credit']); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="transaction-amount transaction-debit">
                            <?php if ($transaction['debit'] > 0): ?>
                                <?php echo formatCurrency($transaction['debit']); ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="transaction-balance"><?php echo formatCurrency($transaction['balance']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8f9fa; font-weight: 700;">
                        <td colspan="3" style="text-align: right; padding: 15px;">Totals:</td>
                        <td class="transaction-amount transaction-credit" style="color: #10b981;">
                            <?php echo formatCurrency($summary['total_credit'] ?? 0); ?>
                        </td>
                        <td class="transaction-amount transaction-debit" style="color: #ef4444;">
                            <?php echo formatCurrency($summary['total_debit'] ?? 0); ?>
                        </td>
                        <td class="transaction-balance" style="color: #667eea;">
                            <?php echo formatCurrency($summary['closing_balance'] ?? 0); ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <?php endif; ?>
            
            <!-- Statement Footer -->
            <div class="statement-footer">
                <div class="footer-content">
                    <div class="footer-section">
                        <h4>Statement Information</h4>
                        <div>Statement Number: <?php echo e($statementNumber); ?></div>
                        <div>Period: <?php echo date('F d', strtotime($period['start_date'])); ?> - <?php echo date('F d, Y', strtotime($period['end_date'])); ?></div>
                        <div>Generated: <?php echo date('F d, Y g:i A'); ?></div>
                    </div>
                    <div class="footer-section">
                        <h4>Contact Information</h4>
                        <?php if (!empty($companyConfig['company_contact'])): ?>
                        <div>Phone: <?php echo e($companyConfig['company_contact']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($companyConfig['company_email'])): ?>
                        <div>Email: <?php echo e($companyConfig['company_email']); ?></div>
                        <?php endif; ?>
                        <?php if (!empty($companyConfig['company_address'])): ?>
                        <div>Address: <?php echo e($companyConfig['company_address']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="footer-section">
                        <h4>Notes</h4>
                        <div>This is an automated statement generated by the ABBIS system.</div>
                        <div style="margin-top: 8px;">For inquiries, please contact us using the information above.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$standalone): ?>
    <div class="no-print">
        <button onclick="openPrintView()" class="btn btn-primary">🖨️ Print Statement</button>
        <a href="crm.php?action=client-detail&client_id=<?php echo $clientId; ?>" class="btn btn-outline">← Back to Client</a>
    </div>
    <?php else: ?>
    <!-- Print button for standalone mode -->
    <div class="no-print" style="text-align: center; padding: 20px; background: #f8f9fa; border-top: 2px solid #e2e8f0;">
        <button onclick="window.print()" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px;">🖨️ Print Statement</button>
    </div>
    <?php endif; ?>
    
    <script>
    function openPrintView() {
        // Get current URL and add standalone parameter
        const url = new URL(window.location.href);
        url.searchParams.set('standalone', '1');
        
        // Open statement in new tab (not new window) for printing
        // Using _blank with no window features ensures it opens as a tab
        const newTab = window.open(url.toString(), '_blank', 'noopener,noreferrer');
        
        // Focus on the new tab
        if (newTab) {
            newTab.focus();
        }
    }
    
    <?php if ($standalone): ?>
    // Auto-focus on print button when page loads in standalone mode
    window.addEventListener('load', function() {
        const printBtn = document.querySelector('.no-print button');
        if (printBtn) {
            // Optional: Auto-trigger print dialog (uncomment if desired)
            // setTimeout(function() {
            //     window.print();
            // }, 500);
        }
    });
    <?php endif; ?>
    </script>
    
    <script>
    function applyDateFilter() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date must be before or equal to end date');
            return;
        }
        
        const url = new URL(window.location.href);
        url.searchParams.set('start_date', startDate);
        url.searchParams.set('end_date', endDate);
        url.searchParams.delete('month'); // Remove old month parameter if present
        window.location.href = url.toString();
    }
    
    function setQuickPeriod(period) {
        const today = new Date();
        let startDate, endDate;
        
        switch(period) {
            case 'month':
                startDate = new Date(today.getFullYear(), today.getMonth(), 1);
                endDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'quarter':
                const quarter = Math.floor(today.getMonth() / 3);
                startDate = new Date(today.getFullYear(), quarter * 3, 1);
                endDate = new Date(today.getFullYear(), (quarter + 1) * 3, 0);
                break;
            case 'year':
                startDate = new Date(today.getFullYear(), 0, 1);
                endDate = new Date(today.getFullYear(), 11, 31);
                break;
            case 'all':
                // Set to a very early date and today
                startDate = new Date(2020, 0, 1);
                endDate = today;
                break;
            default:
                return;
        }
        
        // Format dates as YYYY-MM-DD
        const formatDate = (date) => {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        };
        
        document.getElementById('startDate').value = formatDate(startDate);
        document.getElementById('endDate').value = formatDate(endDate);
        
        // Apply the filter
        applyDateFilter();
    }
    </script>
</body>
</html>
    <?php
    // Exit early to prevent being wrapped in CRM layout
    exit;
}
// End of standalone mode - if we reach here, we're being included in CRM layout
// Include the statement content (shared between standalone and embedded modes)
require_once __DIR__ . '/crm-customer-statement-content.php';

