<?php
/**
 * Customer Statement Content (reusable)
 * This file contains just the statement HTML content
 * Used by both standalone and embedded modes
 */

// Ensure variables are set
if (!isset($client)) {
    die('Client data not available');
}
if (!isset($statementData)) {
    die('Statement data not available');
}

$transactions = $statementData['transactions'] ?? [];
$summary = $statementData['summary'] ?? [];
$period = $statementData['period'] ?? [];
$statementNumber = $statementNumber ?? ('STMT-' . date('Ymd', strtotime($period['start_date'])) . '-' . date('Ymd', strtotime($period['end_date'])) . '-' . str_pad($clientId, 6, '0', STR_PAD_LEFT));
$logoPath = $logoPath ?? null;
$companyConfig = $companyConfig ?? [];
$clientId = $clientId ?? 0;
$startDate = $period['start_date'] ?? date('Y-m-01', strtotime('-12 months'));
$endDate = $period['end_date'] ?? date('Y-m-t');
$standalone = $standalone ?? false;
?>

<?php if (!$standalone): ?>
<style>
    /* Embedded mode styles - works within CRM layout */
    .statement-wrapper {
        margin: 20px 0;
    }
    
    .action-buttons {
        position: relative;
        top: 0;
        right: 0;
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .action-btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .btn-print {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    
    .btn-print:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }
    
    .btn-back {
        background: white;
        color: #4a5568;
        border: 2px solid #cbd5e0;
    }
    
    .btn-back:hover {
        background: #f7fafc;
        border-color: #a0aec0;
    }
    
    .statement-container {
        max-width: 100%;
        margin: 0 auto;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border-radius: 8px;
        overflow: hidden;
    }
    
    .statement-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        display: flex;
        justify-content: space-between;
        align-items: start;
        flex-wrap: wrap;
        gap: 20px;
    }
    
    .company-info {
        flex: 1;
        min-width: 250px;
    }
    
    .company-logo {
        width: 70px;
        height: 70px;
        object-fit: contain;
        background: white;
        padding: 6px;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    
    .company-name {
        font-size: 22px;
        font-weight: 700;
        margin-bottom: 5px;
    }
    
    .company-tagline {
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .company-details {
        font-size: 11px;
        opacity: 0.85;
        line-height: 1.6;
    }
    
    .statement-meta {
        text-align: right;
        color: white;
        min-width: 200px;
    }
    
    .statement-title {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    
    .statement-number {
        font-size: 13px;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .statement-date {
        font-size: 12px;
        opacity: 0.85;
    }
    
    .statement-body {
        padding: 25px;
    }
    
    .client-section {
        background: #f8f9fa;
        padding: 18px;
        border-radius: 6px;
        margin-bottom: 25px;
        border-left: 4px solid #667eea;
    }
    
    .client-name {
        font-size: 18px;
        font-weight: 700;
        color: #1a202c;
        margin-bottom: 10px;
    }
    
    .client-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 8px;
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
        min-width: 70px;
    }
    
    .filter-section {
        background: #f8f9fa;
        padding: 18px;
        border-radius: 6px;
        margin-bottom: 25px;
        display: flex;
        gap: 12px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .filter-section label {
        font-weight: 600;
        color: #4a5568;
        font-size: 13px;
    }
    
    .filter-section input[type="date"] {
        padding: 8px 12px;
        border: 1px solid #cbd5e0;
        border-radius: 6px;
        font-size: 13px;
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
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .summary-card {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 18px;
        border-radius: 6px;
        border-left: 4px solid #667eea;
    }
    
    .summary-card-label {
        font-size: 11px;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    
    .summary-card-value {
        font-size: 22px;
        font-weight: 700;
        color: #1a202c;
    }
    
    .transactions-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
        font-size: 13px;
    }
    
    .transactions-table thead {
        background: #667eea;
        color: white;
    }
    
    .transactions-table th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .transactions-table td {
        padding: 12px;
        border-bottom: 1px solid #e2e8f0;
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
        font-size: 12px;
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
        padding: 20px 25px;
        border-top: 2px solid #e2e8f0;
        margin-top: 25px;
    }
    
    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
    
    /* Print styles for embedded mode */
    @media print {
        /* Hide EVERYTHING in the body except statement container */
        body > *:not(.statement-container):not(script) {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Hide ABBIS header, navigation, footer, CRM tabs */
        header,
        .app-header,
        nav,
        .main-nav,
        footer,
        .app-footer,
        .container-fluid > .page-header,
        .container-fluid > .config-tabs,
        .container-fluid > .tabs,
        .action-buttons {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Hide everything using visibility trick */
        body {
            visibility: hidden !important;
        }
        
        .statement-container,
        .statement-container * {
            visibility: visible !important;
        }
        
        /* Position statement at top of page */
        .statement-container {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
        }
        
        /* Reset body for print */
        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background: white !important;
            width: 100% !important;
            height: auto !important;
        }
        
        /* Hide action buttons and filters when printing */
        .filter-section {
            display: none !important;
        }
        
        /* Ensure colors print */
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        
        .statement-header {
            background: #667eea !important;
            padding: 20px !important;
        }
        
        .statement-body {
            padding: 20px !important;
        }
    }
</style>
<?php endif; ?>

<?php if (!$standalone): ?>
<div class="statement-wrapper">
<?php endif; ?>

<div class="action-buttons">
    <button onclick="printStatement()" class="action-btn btn-print" title="Print Statement (Press Ctrl+P)">🖨️ Print Statement</button>
    <?php if ($standalone): ?>
    <button onclick="goBack()" class="action-btn btn-back" type="button">← Back to Client</button>
    <?php else: ?>
    <a href="crm.php?action=client-detail&client_id=<?php echo $clientId; ?>" class="action-btn btn-back">← Back to Client</a>
    <?php endif; ?>
</div>

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
        <div class="filter-section">
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
</div>
<?php endif; ?>

<script>
function goBack() {
    // If opened in a popup/standalone window (from print), close it
    if (window.opener && !window.opener.closed) {
        // Focus the opener window first, then close this window
        try {
            window.opener.focus();
            window.close();
            // If close() doesn't work (some browsers block it), try after a short delay
            setTimeout(function() {
                if (!window.closed) {
                    window.close();
                }
            }, 100);
        } catch(e) {
            // If we can't access opener, just try to close
            window.close();
        }
    } else if (window.history.length > 1) {
        // If there's history, go back
        window.history.back();
    } else {
        // Fallback: navigate to client detail
        const currentUrl = new URL(window.location.href);
        const baseUrl = currentUrl.origin + currentUrl.pathname.substring(0, currentUrl.pathname.lastIndexOf('/'));
        window.location.href = baseUrl + '/crm.php?action=client-detail&client_id=<?php echo $clientId; ?>';
    }
}

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
    url.searchParams.delete('month');
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
            startDate = new Date(2020, 0, 1);
            endDate = today;
            break;
        default:
            return;
    }
    
    const formatDate = (date) => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };
    
    document.getElementById('startDate').value = formatDate(startDate);
    document.getElementById('endDate').value = formatDate(endDate);
    applyDateFilter();
}

function printStatement() {
    <?php if ($standalone): ?>
    // In standalone mode, just print directly
    window.print();
    <?php else: ?>
    // In embedded mode, open in new window for clean printing
    const url = new URL(window.location.href);
    url.searchParams.set('standalone', '1');
    
    // Open statement in new tab (not new window) - like receipt printing
    // Using _blank without window features opens as a tab
    const printTab = window.open(url.toString(), '_blank', 'noopener,noreferrer');
    
    // Focus on the new tab
    if (printTab) {
        printTab.focus();
    } else {
        // Fallback: print current page if popup blocked
        window.print();
    }
    <?php endif; ?>
}

// Keyboard shortcut for printing
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        printStatement();
    }
});
</script>

