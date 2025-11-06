<?php
/**
 * Payslip HTML Generator
 * Generates payslip HTML content for file saving and email attachments
 */

require_once __DIR__ . '/helpers.php';

class PayslipGenerator {
    /**
     * Generate payslip HTML content
     */
    public static function generateHTML($pdo, $workerName, $dateFrom, $dateTo, $baseUrl = '') {
        // Get company information
        $configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
        $config = [];
        while ($row = $configStmt->fetch()) {
            $config[$row['config_key']] = $row['config_value'];
        }

        $companyName = $config['company_name'] ?? 'Company';
        $companyAddress = $config['company_address'] ?? '';
        $companyContact = $config['company_contact'] ?? '';
        $companyEmail = $config['company_email'] ?? '';
        $logoPath = $config['company_logo'] ?? '';
        $logoUrl = '';
        if ($logoPath && file_exists(__DIR__ . '/../' . $logoPath)) {
            $logoUrl = $baseUrl . '/' . $logoPath;
        }

        // Get payroll entries for the worker in the date range
        $entriesStmt = $pdo->prepare("
            SELECT 
                pe.*,
                fr.report_id as field_report_id,
                fr.report_date,
                fr.site_name,
                r.rig_code
            FROM payroll_entries pe
            LEFT JOIN field_reports fr ON pe.report_id = fr.id
            LEFT JOIN rigs r ON fr.rig_id = r.id
            WHERE pe.worker_name = ?
              AND DATE(fr.report_date) >= ?
              AND DATE(fr.report_date) <= ?
            ORDER BY fr.report_date ASC
        ");
        $entriesStmt->execute([$workerName, $dateFrom, $dateTo]);
        $entries = $entriesStmt->fetchAll(PDO::FETCH_ASSOC);

        // Continue even if empty - we'll generate a payslip with zero amounts
        // if (empty($entries)) {
        //     return null;
        // }

        // Aggregate entries by wage_type (payment type)
        $paymentsByType = [];
        $totalUnits = 0;
        $totalAmount = 0;
        $totalBenefits = 0;
        $totalLoanReclaim = 0;
        $totalPaid = 0;
        $totalUnpaid = 0;

        // Handle empty entries - generate zero-amount payslip
        if (empty($entries)) {
            // Initialize with empty payment types to show zero amounts
            $paymentsByType = [];
        }

        foreach ($entries as $entry) {
            $wageType = $entry['wage_type'];
            $wageTypeLabel = ucwords(str_replace('_', ' ', $wageType));

            // Initialize if not exists
            if (!isset($paymentsByType[$wageType])) {
                $paymentsByType[$wageType] = [
                    'label' => $wageTypeLabel,
                    'total_units' => 0,
                    'total_amount' => 0,
                    'weighted_rate' => 0,
                    'count' => 0
                ];
            }

            // Aggregate data
            $paymentsByType[$wageType]['total_units'] += $entry['units'];
            $paymentsByType[$wageType]['total_amount'] += $entry['amount'];
            $paymentsByType[$wageType]['count']++;

            // Calculate weighted average rate
            $entryRate = $entry['pay_per_unit'] ?? ($entry['units'] > 0 ? $entry['amount'] / $entry['units'] : 0);
            if ($paymentsByType[$wageType]['count'] == 1) {
                $paymentsByType[$wageType]['weighted_rate'] = $entryRate;
            } else {
                $oldTotal = $paymentsByType[$wageType]['total_amount'] - $entry['amount'];
                $oldUnits = $paymentsByType[$wageType]['total_units'] - $entry['units'];
                $oldRate = $oldUnits > 0 ? $oldTotal / $oldUnits : $paymentsByType[$wageType]['weighted_rate'];
                $newRate = $entryRate;

                $totalUnitsNow = $paymentsByType[$wageType]['total_units'];
                if ($totalUnitsNow > 0) {
                    $paymentsByType[$wageType]['weighted_rate'] = 
                        (($oldUnits * $oldRate) + ($entry['units'] * $newRate)) / $totalUnitsNow;
                } else {
                    $paymentsByType[$wageType]['weighted_rate'] = 
                        ($paymentsByType[$wageType]['weighted_rate'] * ($paymentsByType[$wageType]['count'] - 1) + $newRate) / $paymentsByType[$wageType]['count'];
                }
            }

            // Totals
            $totalAmount += $entry['amount'];
            $totalBenefits += $entry['benefits'] ?? 0;
            $totalLoanReclaim += $entry['loan_reclaim'] ?? 0;

            if ($entry['paid_today']) {
                $totalPaid += $entry['amount'];
            } else {
                $totalUnpaid += $entry['amount'];
            }
        }

        // Calculate final rates
        foreach ($paymentsByType as $key => $payment) {
            if ($payment['total_units'] == 0) {
                $avgRate = 0;
                $count = 0;
                foreach ($entries as $entry) {
                    if ($entry['wage_type'] == $key) {
                        $avgRate += $entry['pay_per_unit'];
                        $count++;
                    }
                }
                $paymentsByType[$key]['weighted_rate'] = $count > 0 ? $avgRate / $count : 0;
                $paymentsByType[$key]['total_units'] = $count;
            }
        }

        $netPay = $totalAmount + $totalBenefits - $totalLoanReclaim;

        // Get year-to-date totals
        $yearStart = date('Y-01-01', strtotime($dateTo));
        $ytdStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(pe.amount), 0) as total_earnings,
                COALESCE(SUM(pe.benefits), 0) as total_benefits,
                COALESCE(SUM(pe.loan_reclaim), 0) as total_loans
            FROM payroll_entries pe
            LEFT JOIN field_reports fr ON pe.report_id = fr.id
            WHERE pe.worker_name = ?
              AND DATE(fr.report_date) >= ?
              AND DATE(fr.report_date) <= ?
        ");
        $ytdStmt->execute([$workerName, $yearStart, $dateTo]);
        $ytdTotals = $ytdStmt->fetch(PDO::FETCH_ASSOC);
        $ytdGross = ($ytdTotals['total_earnings'] ?? 0) + ($ytdTotals['total_benefits'] ?? 0);

        // Get worker information
        $workerStmt = $pdo->prepare("SELECT * FROM workers WHERE worker_name = ? LIMIT 1");
        $workerStmt->execute([$workerName]);
        $worker = $workerStmt->fetch(PDO::FETCH_ASSOC);

        // Generate HTML
        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($workerName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: #f8fafc; color: #1e293b; line-height: 1.6; padding: 40px 20px; }
        .payslip-container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); overflow: hidden; }
        .payslip-header { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: white; padding: 30px 40px; display: flex; justify-content: space-between; align-items: center; gap: 30px; }
        .payslip-header-left { flex: 1; display: flex; align-items: center; gap: 25px; }
        .company-logo { max-width: 120px; max-height: 80px; object-fit: contain; background: white; padding: 10px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .payslip-header-info { flex: 1; }
        .payslip-header h1 { font-size: 24px; font-weight: 700; margin-bottom: 5px; letter-spacing: -0.5px; }
        .payslip-header p { font-size: 13px; opacity: 0.95; }
        .payslip-body { padding: 40px; }
        .employee-tax-info { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; padding-bottom: 25px; border-bottom: 2px solid #e2e8f0; }
        .info-section h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 12px; font-weight: 600; }
        .info-section p { font-size: 14px; color: #1e293b; margin-bottom: 6px; }
        .info-section .value { font-weight: 600; font-size: 15px; color: #1e293b; }
        .financial-breakdown { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; margin-bottom: 30px; }
        .panel { background: #f8fafc; border-radius: 8px; padding: 25px; border: 1px solid #e2e8f0; }
        .panel-header { font-size: 14px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
        .payments-table, .deductions-table, .ytd-table { width: 100%; border-collapse: collapse; }
        .payments-table th, .deductions-table th, .ytd-table th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; padding: 10px 8px; border-bottom: 1px solid #e2e8f0; }
        .payments-table th.text-right, .deductions-table th.text-right, .ytd-table th.text-right { text-align: right; }
        .payments-table th:first-child, .deductions-table th:first-child, .ytd-table th:first-child { padding-left: 0; }
        .payments-table th:last-child, .deductions-table th:last-child, .ytd-table th:last-child { padding-right: 0; }
        .payments-table td, .deductions-table td, .ytd-table td { padding: 12px 8px; font-size: 13px; color: #334155; border-bottom: 1px solid #f1f5f9; }
        .payments-table td.text-right, .deductions-table td.text-right, .ytd-table td.text-right { text-align: right; }
        .payments-table td:first-child, .deductions-table td:first-child, .ytd-table td:first-child { padding-left: 0; }
        .payments-table td:last-child, .deductions-table td:last-child, .ytd-table td:last-child { padding-right: 0; }
        .payments-table tr:last-child td, .deductions-table tr:last-child td, .ytd-table tr:last-child td { border-bottom: none; }
        .total-bar { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: white; padding: 12px 15px; border-radius: 6px; margin-top: 15px; display: flex; justify-content: space-between; align-items: center; font-weight: 600; font-size: 14px; }
        .net-pay-section { background: linear-gradient(135deg, #7c3aed 0%, #5b21b6 100%); color: white; padding: 20px; border-radius: 8px; margin-top: 20px; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 18px; }
        .footer-section { margin-top: 30px; padding-top: 25px; border-top: 2px solid #e2e8f0; }
        .footer-section p { font-size: 12px; color: #64748b; margin-bottom: 8px; line-height: 1.6; }
        .footer-section strong { color: #1e293b; }
        .no-print { }
        @media print { 
            body { padding: 0; background: white; } 
            .payslip-container { box-shadow: none; }
            .no-print { display: none !important; visibility: hidden !important; }
        }
    </style>
</head>
<body>
    <div class="payslip-container">
        <div class="payslip-header">
            <div class="payslip-header-left">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="<?php echo htmlspecialchars($companyName); ?> Logo" class="company-logo">
                <?php endif; ?>
                <div class="payslip-header-info">
                    <h1>Payslip details: <?php echo date('d M Y', strtotime($dateTo)); ?></h1>
                    <p><?php echo htmlspecialchars($companyName); ?></p>
                </div>
            </div>
        </div>
        
        <div class="payslip-body">
            <div class="employee-tax-info">
                <div class="info-section">
                    <h3>Employee Information</h3>
                    <p><span class="value"><?php echo htmlspecialchars($workerName); ?></span></p>
                    <?php if ($worker): ?>
                        <p style="color: #64748b; font-size: 13px; margin-top: 8px;"><?php echo htmlspecialchars($worker['role'] ?? ''); ?></p>
                        <?php if (!empty($worker['contact_number'])): ?>
                            <p style="color: #64748b; font-size: 13px;">Contact: <?php echo htmlspecialchars($worker['contact_number']); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p style="color: #64748b; font-size: 12px; margin-top: 10px;">
                        <strong>Reference No.:</strong> <?php echo strtoupper(substr(md5($workerName . $dateFrom . $dateTo), 0, 7)); ?>
                    </p>
                </div>
                
                <div class="info-section">
                    <h3>Payroll Period</h3>
                    <p><span class="value">Period: <?php echo date('d M', strtotime($dateFrom)); ?> - <?php echo date('d M, Y', strtotime($dateTo)); ?></span></p>
                    <p style="color: #64748b; font-size: 13px; margin-top: 8px;">
                        Payday: <?php echo date('l d/m/Y', strtotime($dateTo)); ?>
                    </p>
                    <p style="color: #64748b; font-size: 12px; margin-top: 10px;">
                        Generated: <?php echo date('d M Y \a\t g:i A'); ?>
                    </p>
                </div>
            </div>
            
            <div class="financial-breakdown">
                <div class="panel">
                    <div class="panel-header">Payments</div>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Payment</th>
                                <th class="text-right">U/T</th>
                                <th class="text-right">Rate</th>
                                <th class="text-right">Cash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paymentsByType)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: #94a3b8; font-size: 12px; padding: 20px 0;">
                                        No payments recorded for this period
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paymentsByType as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['label']); ?></td>
                                        <td class="text-right"><?php echo number_format($payment['total_units'], 2); ?></td>
                                        <td class="text-right"><?php echo number_format($payment['weighted_rate'], 4); ?></td>
                                        <td class="text-right"><strong><?php echo formatCurrency($payment['total_amount']); ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if ($totalBenefits > 0): ?>
                                <tr>
                                    <td>Benefits</td>
                                    <td class="text-right">—</td>
                                    <td class="text-right">—</td>
                                    <td class="text-right"><strong><?php echo formatCurrency($totalBenefits); ?></strong></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="total-bar">
                        <span>Total Payments</span>
                        <span><?php echo formatCurrency($totalAmount + $totalBenefits); ?></span>
                    </div>
                </div>
                
                <div class="panel">
                    <div class="panel-header">Deductions</div>
                    <table class="deductions-table">
                        <thead>
                            <tr>
                                <th>Deduction</th>
                                <th class="text-right">Rate</th>
                                <th class="text-right">Cash</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($totalLoanReclaim > 0): ?>
                                <tr>
                                    <td>Loan Reclaim</td>
                                    <td class="text-right">—</td>
                                    <td class="text-right"><strong style="color: #dc2626;"><?php echo formatCurrency($totalLoanReclaim); ?></strong></td>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #94a3b8; font-size: 12px; padding: 20px 0;">No deductions</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="total-bar">
                        <span>Total Deductions</span>
                        <span><?php echo formatCurrency($totalLoanReclaim); ?></span>
                    </div>
                </div>
                
                <div class="panel">
                    <div class="panel-header">Year-to-date</div>
                    <table class="ytd-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Taxable Gross</td>
                                <td class="text-right"><strong><?php echo formatCurrency($ytdGross); ?></strong></td>
                            </tr>
                            <tr>
                                <td>Total Benefits</td>
                                <td class="text-right"><?php echo formatCurrency($ytdTotals['total_benefits'] ?? 0); ?></td>
                            </tr>
                            <tr>
                                <td>Total Loans</td>
                                <td class="text-right"><?php echo formatCurrency($ytdTotals['total_loans'] ?? 0); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="net-pay-section">
                <span>NET PAY</span>
                <span><?php echo formatCurrency($netPay); ?></span>
            </div>
            
            <div class="footer-section">
                <p><strong>Payday:</strong> Payday is <?php echo date('l d/m/Y', strtotime($dateTo)); ?>.</p>
                <?php if ($companyEmail): ?>
                    <p><strong>Pay related queries:</strong> Email <?php echo htmlspecialchars($companyEmail); ?></p>
                <?php endif; ?>
                <?php if ($companyContact): ?>
                    <p><strong>Contact:</strong> <?php echo htmlspecialchars($companyContact); ?></p>
                <?php endif; ?>
                <?php if ($companyAddress): ?>
                    <p><strong>Company Address:</strong> <?php echo htmlspecialchars($companyAddress); ?></p>
                <?php endif; ?>
                <p style="margin-top: 20px; font-size: 11px; color: #94a3b8; font-style: italic;">
                    PLEASE KEEP THIS PAY ADVICE IN A SAFE PLACE. IT MAY BE REQUIRED FOR THE PURPOSE OF RECORD KEEPING.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /**
     * Save payslip HTML to file
     */
    public static function saveToFile($pdo, $workerName, $dateFrom, $dateTo, $baseUrl = '', $saveDir = null) {
        try {
            $html = self::generateHTML($pdo, $workerName, $dateFrom, $dateTo, $baseUrl);
            if (!$html) {
                error_log("PayslipGenerator: generateHTML returned null for {$workerName} ({$dateFrom} to {$dateTo})");
                return null;
            }

            // Determine save directory
            if ($saveDir === null) {
                $saveDir = __DIR__ . '/../uploads/payslips/';
            }

            // Create directory if it doesn't exist
            if (!is_dir($saveDir)) {
                if (!@mkdir($saveDir, 0755, true)) {
                    error_log("PayslipGenerator: Failed to create directory: {$saveDir}");
                    throw new Exception("Failed to create payslip directory");
                }
            }

            // Check if directory is writable
            if (!is_writable($saveDir)) {
                error_log("PayslipGenerator: Directory is not writable: {$saveDir}");
                throw new Exception("Payslip directory is not writable");
            }

            // Generate filename
            $safeWorkerName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $workerName);
            $filename = 'payslip_' . $safeWorkerName . '_' . date('Y-m-d', strtotime($dateFrom)) . '_to_' . date('Y-m-d', strtotime($dateTo)) . '.html';
            $filepath = $saveDir . $filename;

            // Save file
            $bytesWritten = @file_put_contents($filepath, $html);
            if ($bytesWritten === false) {
                error_log("PayslipGenerator: file_put_contents failed for: {$filepath}");
                throw new Exception("Failed to write payslip file");
            }

            return [
                'filepath' => $filepath,
                'filename' => $filename,
                'url' => str_replace(__DIR__ . '/../', $baseUrl . '/', $filepath)
            ];
        } catch (Exception $e) {
            error_log("PayslipGenerator::saveToFile error for {$workerName}: " . $e->getMessage());
            throw $e; // Re-throw to be caught by caller
        }
    }
}
