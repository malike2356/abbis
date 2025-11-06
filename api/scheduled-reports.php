<?php
/**
 * Scheduled Reports API
 * Generates and sends scheduled dashboard reports
 * Run via cron job: php api/scheduled-reports.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers.php';

// Allow CLI execution
if (php_sapi_name() !== 'cli' && !isset($_GET['key'])) {
    // For web access, require authentication key
    $key = $_GET['key'] ?? '';
    if ($key !== 'scheduled_reports_key_' . date('Y-m-d')) {
        http_response_code(403);
        die('Access denied');
    }
}

$pdo = getDBConnection();
$abbis = new ABBISFunctions($pdo);

// Get report configuration from database or config
try {
    // Check if scheduled_reports table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS scheduled_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        report_type VARCHAR(50) NOT NULL,
        frequency VARCHAR(20) NOT NULL,
        recipients TEXT,
        format VARCHAR(10) DEFAULT 'pdf',
        is_active TINYINT(1) DEFAULT 1,
        last_sent DATETIME,
        next_send DATETIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY idx_next_send (next_send),
        KEY idx_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    // Get active scheduled reports
    $stmt = $pdo->query("SELECT * FROM scheduled_reports WHERE is_active = 1 AND next_send <= NOW()");
    $scheduledReports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($scheduledReports as $report) {
        try {
            generateAndSendReport($report, $pdo, $abbis);
            
            // Update next send time
            $nextSend = calculateNextSendTime($report['frequency']);
            $stmt = $pdo->prepare("UPDATE scheduled_reports SET last_sent = NOW(), next_send = ? WHERE id = ?");
            $stmt->execute([$nextSend, $report['id']]);
            
            echo "Report sent: {$report['report_type']} (ID: {$report['id']})\n";
        } catch (Exception $e) {
            error_log("Error sending scheduled report {$report['id']}: " . $e->getMessage());
            echo "Error sending report {$report['id']}: " . $e->getMessage() . "\n";
        }
    }
    
    if (empty($scheduledReports)) {
        echo "No scheduled reports to send.\n";
    }
    
} catch (Exception $e) {
    error_log("Scheduled reports error: " . $e->getMessage());
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

function generateAndSendReport($report, $pdo, $abbis) {
    $reportType = $report['report_type'];
    $format = $report['format'] ?? 'pdf';
    $recipients = json_decode($report['recipients'] ?? '[]', true);
    
    if (empty($recipients)) {
        // Default to admin email
        $stmt = $pdo->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($admin && !empty($admin['email'])) {
            $recipients = [$admin['email']];
        }
    }
    
    if (empty($recipients)) {
        throw new Exception("No recipients configured for report {$report['id']}");
    }
    
    // Generate report data
    $stats = $abbis->getDashboardStats(false);
    
    // Create report content
    $subject = "ABBIS Dashboard Report - " . ucfirst($reportType) . " - " . date('F Y');
    $content = generateReportContent($stats, $reportType);
    
    // Send email
    foreach ($recipients as $email) {
        sendReportEmail($email, $subject, $content, $format);
    }
}

function generateReportContent($stats, $reportType) {
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ABBIS Dashboard Report</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
            h1 { color: #1e293b; border-bottom: 2px solid #0ea5e9; padding-bottom: 10px; }
            h2 { color: #475569; margin-top: 30px; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
            th { background-color: #f8f9fa; font-weight: 600; }
            .metric { background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 10px 0; }
            .metric-label { font-size: 12px; color: #64748b; text-transform: uppercase; }
            .metric-value { font-size: 24px; font-weight: 700; color: #1e293b; }
        </style>
    </head>
    <body>
        <h1>ABBIS Dashboard Report</h1>
        <p>Generated: ' . date('F d, Y H:i:s') . '</p>';
    
    switch ($reportType) {
        case 'financial':
            $html .= generateFinancialReport($stats);
            break;
        case 'operational':
            $html .= generateOperationalReport($stats);
            break;
        case 'summary':
        default:
            $html .= generateSummaryReport($stats);
            break;
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}

function generateFinancialReport($stats) {
    $html = '<h2>Financial Overview</h2>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Revenue</td><td>GHS ' . number_format($stats['overall']['total_income'] ?? 0, 2) . '</td></tr>
        <tr><td>Total Expenses</td><td>GHS ' . number_format($stats['overall']['total_expenses'] ?? 0, 2) . '</td></tr>
        <tr><td>Net Profit</td><td>GHS ' . number_format($stats['overall']['total_profit'] ?? 0, 2) . '</td></tr>
        <tr><td>Profit Margin</td><td>' . number_format($stats['financial_health']['profit_margin'] ?? 0, 2) . '%</td></tr>
        <tr><td>Total Assets</td><td>GHS ' . number_format($stats['balance_sheet']['total_assets'] ?? 0, 2) . '</td></tr>
        <tr><td>Total Liabilities</td><td>GHS ' . number_format($stats['balance_sheet']['total_liabilities'] ?? 0, 2) . '</td></tr>
        <tr><td>Net Worth</td><td>GHS ' . number_format($stats['balance_sheet']['net_worth'] ?? 0, 2) . '</td></tr>
    </table>';
    
    return $html;
}

function generateOperationalReport($stats) {
    $html = '<h2>Operational Overview</h2>
    <table>
        <tr><th>Metric</th><th>Value</th></tr>
        <tr><td>Total Jobs</td><td>' . number_format($stats['overall']['total_reports'] ?? 0) . '</td></tr>
        <tr><td>Jobs This Month</td><td>' . number_format($stats['this_month']['total_reports_this_month'] ?? 0) . '</td></tr>
        <tr><td>Avg Revenue per Job</td><td>GHS ' . number_format($stats['financial_health']['avg_revenue_per_job'] ?? 0, 2) . '</td></tr>
        <tr><td>Avg Profit per Job</td><td>GHS ' . number_format($stats['financial_health']['avg_profit_per_job'] ?? 0, 2) . '</td></tr>
    </table>';
    
    return $html;
}

function generateSummaryReport($stats) {
    return generateFinancialReport($stats) . generateOperationalReport($stats);
}

function sendReportEmail($to, $subject, $htmlContent, $format) {
    // Use PHP mail() or PHPMailer if available
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: ABBIS System <noreply@abbis.local>\r\n";
    
    mail($to, $subject, $htmlContent, $headers);
}

function calculateNextSendTime($frequency) {
    switch ($frequency) {
        case 'daily':
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        case 'weekly':
            return date('Y-m-d H:i:s', strtotime('+1 week'));
        case 'monthly':
            return date('Y-m-d H:i:s', strtotime('+1 month'));
        default:
            return date('Y-m-d H:i:s', strtotime('+1 day'));
    }
}

