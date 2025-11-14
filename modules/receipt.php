<?php
/**
 * Generate Receipt/Invoice (Financial Only - No Technical Details)
 * Professional receipt/invoice document with company branding
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
    SELECT fr.*, r.rig_name, r.rig_code, c.*, u.full_name as created_by_name
    FROM field_reports fr
    LEFT JOIN rigs r ON fr.rig_id = r.id
    LEFT JOIN clients c ON fr.client_id = c.id
    LEFT JOIN users u ON fr.created_by = u.id
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

// Reference ID (report_id - same for all receipts related to this report/job)
// This same Reference ID will appear on the technical report as well
$referenceId = $report['report_id'];

// Generate unique receipt number (unique per receipt - multiple receipts can have same reference ID)
// Format: RCP-YYYYMMDD-HHMMSS-UNIQID-REPORTID (e.g., RCP-20241103-143025-a1b2c3-001)
// This ensures uniqueness even if multiple receipts are generated in the same second
$uniqueId = substr(uniqid('', true), -6); // Get last 6 characters of uniqid for uniqueness
$receiptNumber = 'RCP-' . date('Ymd-His') . '-' . $uniqueId . '-' . str_pad($reportId, 3, '0', STR_PAD_LEFT);

// Generate QR code
require_once '../includes/qr-code-generator.php';
$receiptUrl = app_url('modules/receipt.php?report_id=' . $reportId);
$qrCodePath = QRCodeGenerator::generate($receiptUrl, 'receipt', 150);

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt/Invoice - <?php echo e($receiptNumber); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            max-width: 800px; 
            margin: 0 auto; 
            padding: 40px 20px; 
            background: #f5f5f5;
            color: #333;
        }
        .receipt-container {
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .receipt-header { 
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
        .receipt-header h1 { 
            margin: 10px 0 5px 0;
            font-size: 28px;
            color: #1e293b;
        }
        .receipt-header .tagline {
            color: #64748b;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .receipt-header .company-info {
            color: #475569;
            font-size: 13px;
            line-height: 1.8;
        }
        .receipt-title {
            font-size: 32px;
            font-weight: bold;
            color: #0ea5e9;
            margin-top: 20px;
            letter-spacing: 2px;
        }
        .reference-id {
            background: #f1f5f9;
            padding: 15px;
            border-left: 4px solid #0ea5e9;
            margin: 25px 0;
            border-radius: 4px;
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
        .receipt-details { 
            margin: 30px 0; 
        }
        .receipt-details table { 
            width: 100%; 
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .receipt-details thead {
            background: #f8fafc;
        }
        .receipt-details th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .receipt-details td { 
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .receipt-details td:first-child { 
            font-weight: 600; 
            color: #475569;
            width: 40%; 
        }
        .receipt-details tr:last-child td {
            border-bottom: none;
        }
        .amount { 
            font-size: 20px; 
            font-weight: bold; 
            color: #10b981;
        }
        .total-section {
            background: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-size: 16px;
        }
        .total-row.final {
            border-top: 2px solid #0ea5e9;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 24px;
            font-weight: bold;
            color: #0ea5e9;
        }
        .receipt-footer { 
            margin-top: 50px; 
            text-align: center; 
            border-top: 2px solid #e2e8f0; 
            padding-top: 30px; 
            color: #64748b;
            font-size: 13px;
        }
        .receipt-footer p {
            margin: 8px 0;
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
            .receipt-container {
                box-shadow: none;
                padding: 20px;
            }
            .no-print { 
                display: none; 
            }
            .reference-id {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-header">
            <?php if ($logoUrl): ?>
                <div class="logo-section">
                    <img src="<?php echo e($logoUrl); ?>" alt="<?php echo e($config['company_name'] ?? 'Company Logo'); ?>">
                </div>
            <?php endif; ?>
            <h1><?php echo e($config['company_name'] ?? 'ABBIS'); ?></h1>
            <?php if (!empty($config['company_tagline'])): ?>
                <p class="tagline"><?php echo e($config['company_tagline']); ?></p>
            <?php endif; ?>
            <div class="company-info">
                <?php if (!empty($config['company_address'])): ?>
                    <p><?php echo e($config['company_address']); ?></p>
                <?php endif; ?>
                <p>
                    <?php if (!empty($config['company_contact'])): ?>
                        Tel: <?php echo e($config['company_contact']); ?>
                    <?php endif; ?>
                    <?php if (!empty($config['company_contact']) && !empty($config['company_email'])): ?>
                        | 
                    <?php endif; ?>
                    <?php if (!empty($config['company_email'])): ?>
                        Email: <?php echo e($config['company_email']); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="receipt-title">RECEIPT / INVOICE</div>
        </div>

        <div class="reference-id">
            <strong>Reference ID:</strong><br>
            <code><?php echo e($referenceId); ?></code>
            <small style="display: block; margin-top: 5px; color: #64748b;">This Reference ID is shared across all receipts and reports for this job. Use it to reference all related documents.</small>
        </div>

        <div class="receipt-details">
            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Receipt Number:</td>
                        <td><code style="font-size: 16px;"><?php echo e($receiptNumber); ?></code></td>
                    </tr>
                    <tr>
                        <td>Reference ID:</td>
                        <td><code style="font-size: 14px;"><?php echo e($referenceId); ?></code></td>
                    </tr>
                    <tr>
                        <td>Date:</td>
                        <td><?php echo formatDate($report['report_date']); ?></td>
                    </tr>
                    <tr>
                        <td>Client Name:</td>
                        <td><strong><?php echo e($report['client_name'] ?? 'N/A'); ?></strong></td>
                    </tr>
                    <?php if (!empty($report['contact_person'])): ?>
                    <tr>
                        <td>Contact Person:</td>
                        <td><?php echo e($report['contact_person']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($report['contact_number'])): ?>
                    <tr>
                        <td>Contact Number:</td>
                        <td><?php echo e($report['contact_number']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($report['email'])): ?>
                    <tr>
                        <td>Email:</td>
                        <td><?php echo e($report['email']); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td>Job Type:</td>
                        <td><?php echo ucfirst(e($report['job_type'])); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <?php
        // Calculate total amount received from client only
        // Only include: Rig Fee Collected, Materials Income, and Catalog Items
        // Exclude: Contract Sum (expected, not necessarily received), Cash Received (from company/office)
        $totalClientPayment = 0;
        
        // Rig Fee Collected (from client)
        $rigFeeCollected = floatval($report['rig_fee_collected'] ?? 0);
        $totalClientPayment += $rigFeeCollected;
        
        // Materials Income (from client)
        $materialsIncome = floatval($report['materials_income'] ?? 0);
        $totalClientPayment += $materialsIncome;
        
        // Itemized charges from catalog-linked items (from client)
        $items = [];
        $catalogItemsTotal = 0;
        try {
            $it = $pdo->prepare("SELECT fri.*, ci.name as item_name FROM field_report_items fri LEFT JOIN catalog_items ci ON ci.id=fri.catalog_item_id WHERE fri.report_id = ? ORDER BY fri.id");
            $it->execute([$reportId]);
            $items = $it->fetchAll(PDO::FETCH_ASSOC);
            foreach ($items as $li) {
                $catalogItemsTotal += floatval($li['total_amount'] ?? 0);
            }
        } catch (Throwable $e) { 
            $items = []; 
        }
        $totalClientPayment += $catalogItemsTotal;
        
        // Materials cost (if client purchased materials from company/store)
        $materialsCost = 0;
        $showMaterialsCost = false;
        if (in_array(($report['materials_provided_by'] ?? ''), ['company', 'store'], true)) {
            $materialsCost = floatval($report['materials_cost'] ?? 0);
            $showMaterialsCost = $materialsCost > 0;
        }
        
        // Grand total = payments received + materials cost (if applicable)
        $grandTotal = $totalClientPayment + $materialsCost;
        ?>

        <div class="receipt-details">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align: right;">Amount (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rigFeeCollected > 0): ?>
                    <tr>
                        <td><strong>Rig Fee Collected</strong></td>
                        <td style="text-align: right;" class="amount"><?php echo formatCurrency($rigFeeCollected); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($materialsIncome > 0): ?>
                    <tr>
                        <td><strong>Materials Income</strong></td>
                        <td style="text-align: right;" class="amount"><?php echo formatCurrency($materialsIncome); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($items)): ?>
        <div class="receipt-details">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit Price (GHS)</th>
                        <th style="text-align:right;">Total (GHS)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $li): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($li['item_name'] ?: $li['description']); ?></strong>
                            <?php if (!empty($li['unit'])): ?>
                                <div style="color:#64748b; font-size:12px;">Unit: <?php echo htmlspecialchars($li['unit']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">&times; <?php echo number_format((float)$li['quantity'], 2); ?></td>
                        <td style="text-align:right;">GHS <?php echo number_format((float)$li['unit_price'], 2); ?></td>
                        <td style="text-align:right;">GHS <?php echo number_format((float)$li['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="total-section">
            <?php if ($totalClientPayment > 0): ?>
            <div class="total-row">
                <span>Subtotal (Payments Received):</span>
                <span style="color: #10b981; font-weight: 600;"><?php echo formatCurrency($totalClientPayment); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($showMaterialsCost): ?>
            <div class="total-row">
                <span>
                    Materials Cost (
                    <?php
                        if (($report['materials_provided_by'] ?? '') === 'store' && $materialsStoreName) {
                            echo 'POS Store: ' . e($materialsStoreName);
                        } else {
                            echo 'Purchased from Company';
                        }
                    ?>
                    ):
                </span>
                <span style="color: #0ea5e9; font-weight: 600;"><?php echo formatCurrency($materialsCost); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="total-row final">
                <span><strong>Total Amount Due:</strong></span>
                <span style="color: #10b981; font-size: 22px; font-weight: bold;"><?php echo formatCurrency($grandTotal); ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            <p><strong>Thank you for your business!</strong></p>
            <p>This is an official receipt/invoice for services rendered.</p>
            <p style="margin-top: 20px;">
                <small>
                    Generated on <?php echo date('F j, Y \a\t g:i A'); ?> by <?php echo e($report['created_by_name'] ?? 'System'); ?><br>
                    Receipt Number: <code><?php echo e($receiptNumber); ?></code><br>
                    Reference ID: <code><?php echo e($referenceId); ?></code>
                </small>
            </p>
        </div>
        
        <!-- QR Code Section -->
        <div class="qr-code-section">
            <strong style="display: block; margin-bottom: 10px; color: #1e293b;">📱 Scan QR Code to View Receipt Online</strong>
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
                <img src="<?php echo e($qrImagePath); ?>" alt="QR Code for Receipt" style="max-width: 150px; margin: 15px auto; display: block;" />
            <?php else: ?>
                <div style="padding: 20px; background: #f1f5f9; border-radius: 6px; color: #64748b; text-align: center; font-size: 12px; word-break: break-all;">
                    <strong>QR Code URL:</strong><br>
                    <?php echo e($receiptUrl); ?>
                </div>
            <?php endif; ?>
            <div class="qr-code-label">
                Reference ID: <?php echo e($referenceId); ?><br>
                Receipt Number: <?php echo e($receiptNumber); ?><br>
                Scan to view this receipt anytime
            </div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Print Receipt</button>
        <a href="field-reports-list.php" class="btn btn-outline">← Back to Reports</a>
        <a href="technical-report.php?report_id=<?php echo $reportId; ?>" class="btn btn-outline">📄 View Technical Report</a>
    </div>
</body>
</html>
