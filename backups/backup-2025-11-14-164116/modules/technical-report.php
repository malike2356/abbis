<?php
/**
 * Generate Technical Report (Technical Details Only - No Financial Data)
 * Professional technical report for clients, agents, and contractors
 */
require_once '../config/app.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/pos/PosRepository.php';

$auth->requireAuth();

$reportId = intval($_GET['report_id'] ?? 0);
if (!$reportId) {
    die('Report ID required');
}

$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT fr.*, r.rig_name, r.rig_code, c.*
    FROM field_reports fr
    LEFT JOIN rigs r ON fr.rig_id = r.id
    LEFT JOIN clients c ON fr.client_id = c.id
    WHERE fr.id = ?
");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report) {
    die('Report not found');
}

$materialsStoreName = null;
if (!empty($report['materials_store_id'])) {
    try {
        $posRepo = new PosRepository($pdo);
        $store = $posRepo->fetchStoreById((int) $report['materials_store_id']);
        $materialsStoreName = $store['store_name'] ?? null;
    } catch (Throwable $e) {
        $materialsStoreName = null;
    }
}

// Get company config
$config = [];
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

// Get logo path
$logoPath = $config['company_logo'] ?? '';
$logoUrl = '';
if ($logoPath && file_exists('../' . $logoPath)) {
    $logoUrl = '../' . $logoPath;
}

// Reference ID (same as used in receipts - identifies the job/report)
$referenceId = $report['report_id'];

// Generate QR code
require_once '../includes/qr-code-generator.php';
$technicalReportUrl = app_url('modules/technical-report.php?report_id=' . $reportId);
$qrCodePath = QRCodeGenerator::generate($technicalReportUrl, 'technical', 150);

// Calculate duration from stored minutes (or calculate if not stored)
$startTime = $report['start_time'] ?? null;
$finishTime = $report['finish_time'] ?? null;
$duration = '';
$durationHours = 0;

if ($report['total_duration']) {
    // Use stored duration in minutes
    $totalMinutes = intval($report['total_duration']);
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    if ($hours > 0) {
        $duration = $minutes > 0 ? sprintf('%dh %dm', $hours, $minutes) : sprintf('%dh', $hours);
    } else {
        $duration = sprintf('%dm', $minutes);
    }
    $durationHours = round($totalMinutes / 60, 2);
} elseif ($startTime && $finishTime) {
    // Fallback: calculate from times if duration not stored
    $start = strtotime($startTime);
    $finish = strtotime($finishTime);
    $diff = abs($finish - $start);
    $hours = floor($diff / 3600);
    $minutes = floor(($diff % 3600) / 60);
    if ($hours > 0) {
        $duration = $minutes > 0 ? sprintf('%dh %dm', $hours, $minutes) : sprintf('%dh', $hours);
    } else {
        $duration = sprintf('%dm', $minutes);
    }
    $durationHours = round($diff / 3600, 2);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technical Report - <?php echo e($referenceId); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 1000px; 
            margin: 0 auto; 
            padding: 40px 20px; 
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        .report-container {
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .report-header { 
            text-align: center; 
            border-bottom: 3px solid #0ea5e9; 
            padding-bottom: 30px; 
            margin-bottom: 30px; 
        }
        .logo-section {
            margin-bottom: 20px;
        }
        .logo-section img {
            max-height: 80px;
            max-width: 200px;
            margin-bottom: 10px;
        }
        .report-header h1 { 
            margin: 10px 0 5px 0;
            font-size: 32px;
            color: #1e293b;
            font-weight: 700;
        }
        .report-header .tagline {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .report-header .company-info {
            color: #475569;
            font-size: 13px;
            line-height: 1.8;
        }
        .reference-id {
            background: #f1f5f9;
            padding: 15px;
            border-left: 4px solid #0ea5e9;
            margin: 25px 0;
            border-radius: 4px;
            text-align: center;
        }
        .reference-id strong {
            color: #0ea5e9;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .reference-id code {
            font-size: 18px;
            color: #1e293b;
            font-weight: bold;
        }
        .section { 
            margin: 35px 0; 
            page-break-inside: avoid;
        }
        .section h2 { 
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 12px; 
            margin-bottom: 20px; 
            color: #1e293b;
            font-size: 22px;
            font-weight: 600;
        }
        .report-details { 
            margin: 15px 0; 
        }
        .report-details table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .report-details thead {
            background: #f8fafc;
        }
        .report-details th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-details td { 
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .report-details td:first-child { 
            font-weight: 600; 
            color: #475569;
            width: 35%; 
        }
        .two-column { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 30px; 
            margin: 20px 0;
        }
        @media (max-width: 768px) {
            .two-column {
                grid-template-columns: 1fr;
            }
        }
        .signature-section { 
            margin-top: 60px; 
            page-break-inside: avoid;
        }
        .signature-box { 
            border-top: 2px solid #1e293b; 
            width: 250px; 
            padding-top: 15px; 
            margin-top: 50px; 
        }
        .signature-box p {
            margin-bottom: 8px;
            color: #475569;
            font-size: 14px;
        }
        .qr-code-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }
        .qr-code-section img {
            max-width: 150px;
            margin: 15px auto;
            display: block;
        }
        .qr-code-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 10px;
        }
        .notes-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid #0ea5e9;
            margin: 15px 0;
        }
        .notes-box h3 {
            color: #0ea5e9;
            margin-bottom: 12px;
            font-size: 16px;
        }
        .notes-box p {
            color: #475569;
            white-space: pre-wrap;
            line-height: 1.8;
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
            body { 
                background: white;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                padding: 20px;
            }
            .no-print { 
                display: none; 
            }
            .section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <?php if ($logoUrl): ?>
                <div class="logo-section">
                    <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($config['company_name'] ?? 'Company Logo'); ?>">
                </div>
            <?php endif; ?>
            <h1>BOREHOLE DRILLING TECHNICAL REPORT</h1>
            <div class="company-info">
                <?php if (!empty($config['company_name'])): ?>
                    <p><strong><?php echo e($config['company_name']); ?></strong></p>
                <?php endif; ?>
                <?php if (!empty($config['company_address'])): ?>
                    <p><?php echo e($config['company_address']); ?></p>
                <?php endif; ?>
                <p>
                    <?php if (!empty($config['company_contact'])): ?>
                        Tel: <?php echo e($config['company_contact']); ?>
                    <?php endif; ?>
                    <?php if (!empty($config['company_contact']) && !empty($config['company_email'])): ?>
                        | Email: <?php echo e($config['company_email']); ?>
                    <?php elseif (!empty($config['company_email'])): ?>
                        Email: <?php echo e($config['company_email']); ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="reference-id">
            <strong>Reference ID:</strong><br>
            <code><?php echo e($referenceId); ?></code>
            <small style="display: block; margin-top: 5px; color: #64748b;">This Reference ID is shared across all receipts and reports for this job. Use it to reference all related documents.</small>
        </div>

        <!-- Site Information -->
        <div class="section">
            <h2>📍 Site Information</h2>
            <div class="report-details">
                <table>
                    <tr>
                        <td>Report Date:</td>
                        <td><?php echo formatDate($report['report_date']); ?></td>
                    </tr>
                    <tr>
                        <td>Site Name:</td>
                        <td><strong><?php echo e($report['site_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <td>Region:</td>
                        <td><?php echo e($report['region'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php if ($report['plus_code']): ?>
                    <tr>
                        <td>Plus Code (GPS):</td>
                        <td><code><?php echo e($report['plus_code']); ?></code></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($report['latitude'] && $report['longitude']): ?>
                    <tr>
                        <td>Coordinates:</td>
                        <td><?php echo e($report['latitude']); ?>, <?php echo e($report['longitude']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($report['location_description']): ?>
                    <tr>
                        <td>Location Description:</td>
                        <td><?php echo nl2br(e($report['location_description'])); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Client:</td>
                        <td><?php echo e($report['client_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php if ($report['contact_person']): ?>
                    <tr>
                        <td>Contact Person:</td>
                        <td><?php echo e($report['contact_person']); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Drilling Information -->
        <div class="section">
            <h2>⛏️ Drilling Information</h2>
            <div class="two-column">
                <div class="report-details">
                    <table>
                        <tr>
                            <td>Rig Used:</td>
                            <td><strong><?php echo e($report['rig_name']); ?></strong> <small>(<?php echo e($report['rig_code']); ?>)</small></td>
                        </tr>
                        <tr>
                            <td>Job Type:</td>
                            <td><?php echo ucfirst(e($report['job_type'])); ?></td>
                        </tr>
                        <tr>
                            <td>Supervisor:</td>
                            <td><?php echo e($report['supervisor'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>
                <div class="report-details">
                    <table>
                        <?php if ($startTime && $finishTime): ?>
                        <tr>
                            <td>Start Time:</td>
                            <td><?php echo date('H:i', strtotime($startTime)); ?></td>
                        </tr>
                        <tr>
                            <td>Finish Time:</td>
                            <td><?php echo date('H:i', strtotime($finishTime)); ?></td>
                        </tr>
                        <tr>
                            <td>Duration:</td>
                            <td><strong><?php echo $duration; ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td>Rod Length:</td>
                            <td><?php echo e($report['rod_length'] ?? 0); ?> meters</td>
                        </tr>
                        <tr>
                            <td>Rods Used:</td>
                            <td><?php echo $report['rods_used'] ?? 0; ?> rods</td>
                        </tr>
                        <tr>
                            <td>Total Depth:</td>
                            <td><strong><?php echo e($report['total_depth'] ?? 0); ?> meters</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Materials Used -->
        <div class="section">
            <h2>📦 Materials Used</h2>
            <div class="report-details">
                <table>
                    <tr>
                        <td>Materials Provided By:</td>
                        <td>
                            <?php
                                $providerLabel = ucfirst(str_replace('_', ' ', e($report['materials_provided_by'] ?? 'N/A')));
                                if (($report['materials_provided_by'] ?? '') === 'store' && $materialsStoreName) {
                                    $providerLabel .= ' - ' . e($materialsStoreName);
                                }
                                echo $providerLabel;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Screen Pipes Used:</td>
                        <td><?php echo $report['screen_pipes_used'] ?? 0; ?> units</td>
                    </tr>
                    <tr>
                        <td>Plain Pipes Used:</td>
                        <td><?php echo $report['plain_pipes_used'] ?? 0; ?> units</td>
                    </tr>
                    <tr>
                        <td>Gravel Used:</td>
                        <td><?php echo $report['gravel_used'] ?? 0; ?> bags</td>
                    </tr>
                    <tr>
                        <td>Construction Depth:</td>
                        <td><?php echo e($report['construction_depth'] ?? 0); ?> meters</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Notes & Observations -->
        <?php if ($report['remarks'] || $report['incident_log'] || $report['solution_log'] || $report['recommendation_log']): ?>
        <div class="section">
            <h2>📝 Notes & Observations</h2>
            
            <?php if ($report['remarks']): ?>
            <div class="notes-box">
                <h3>General Remarks:</h3>
                <p><?php echo nl2br(e($report['remarks'])); ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($report['incident_log'] || $report['solution_log']): ?>
            <div class="two-column">
                <?php if ($report['incident_log']): ?>
                <div class="notes-box">
                    <h3>⚠️ Incidents Encountered:</h3>
                    <p><?php echo nl2br(e($report['incident_log'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($report['solution_log']): ?>
                <div class="notes-box">
                    <h3>✅ Solutions Applied:</h3>
                    <p><?php echo nl2br(e($report['solution_log'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($report['recommendation_log']): ?>
            <div class="notes-box">
                <h3>💡 Recommendations:</h3>
                <p><?php echo nl2br(e($report['recommendation_log'])); ?></p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Signature -->
        <div class="signature-section">
            <div class="two-column">
                <div>
                    <div class="signature-box">
                        <p><strong>Prepared By:</strong></p>
                        <p style="margin-top: 40px;">___________________________</p>
                        <p style="margin-top: 5px; font-size: 12px; color: #64748b;">Signature</p>
                    </div>
                </div>
                <div>
                    <div class="signature-box">
                        <p><strong>Verified By:</strong></p>
                        <p style="margin-top: 40px;">___________________________</p>
                        <p style="margin-top: 5px; font-size: 12px; color: #64748b;">Signature</p>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 12px;">
            <p>This technical report contains only operational and technical information. No financial data is included.</p>
            <p style="margin-top: 10px;">Reference ID: <code><?php echo e($referenceId); ?></code></p>
            <p style="margin-top: 10px;">Generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
        </div>
        
        <!-- QR Code Section -->
        <div class="qr-code-section">
            <strong style="display: block; margin-bottom: 10px; color: #1e293b;">📱 Scan QR Code to View Technical Report Online</strong>
            <?php 
            $qrImagePath = '';
            if (!empty($qrCodePath)) {
                if (strpos($qrCodePath, 'data:') === 0) {
                    $qrImagePath = $qrCodePath;
                } elseif (file_exists('../' . $qrCodePath)) {
                    $qrImagePath = '../' . $qrCodePath;
                } elseif (file_exists($qrCodePath)) {
                    $qrImagePath = $qrCodePath;
                }
            }
            
            if (!empty($qrImagePath)): ?>
                <img src="<?php echo e($qrImagePath); ?>" alt="QR Code for Technical Report" style="max-width: 150px; margin: 15px auto; display: block;" />
            <?php else: ?>
                <div style="padding: 20px; background: #f1f5f9; border-radius: 6px; color: #64748b; text-align: center; font-size: 12px; word-break: break-all;">
                    <strong>QR Code URL:</strong><br>
                    <?php echo e($technicalReportUrl); ?>
                </div>
            <?php endif; ?>
            <div class="qr-code-label">
                Reference ID: <?php echo e($referenceId); ?><br>
                Scan to view this technical report anytime
            </div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
        <a href="field-reports-list.php" class="btn btn-outline">← Back to Reports</a>
        <a href="receipt.php?report_id=<?php echo $reportId; ?>" class="btn btn-outline">💰 View Receipt/Invoice</a>
    </div>
</body>
</html>
