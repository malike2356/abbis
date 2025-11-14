<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/AccountingAutoTracker.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Initialize accounting system to ensure accounts exist
try {
    $tracker = new AccountingAutoTracker($pdo);
} catch (Exception $e) {
    error_log("Error initializing accounting: " . $e->getMessage());
}

// Check if there's existing financial data to process
$hasFinancialData = false;
$dataCounts = [
    'field_reports' => 0,
    'loans' => 0,
    'payroll' => 0,
    'materials' => 0,
    'journal_entries' => 0
];

try {
    $dataCounts['field_reports'] = (int)$pdo->query("SELECT COUNT(*) FROM field_reports")->fetchColumn();
    $dataCounts['loans'] = (int)$pdo->query("SELECT COUNT(*) FROM loans")->fetchColumn();
    $dataCounts['payroll'] = (int)$pdo->query("SELECT COUNT(*) FROM payroll_entries WHERE paid_today = 1")->fetchColumn();
    $dataCounts['materials'] = (int)$pdo->query("SELECT COUNT(*) FROM materials_transactions WHERE transaction_type = 'purchase'")->fetchColumn();
    $dataCounts['journal_entries'] = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn();
    
    $hasFinancialData = $dataCounts['field_reports'] > 0 || $dataCounts['loans'] > 0 || $dataCounts['payroll'] > 0 || $dataCounts['materials'] > 0;
} catch (Exception $e) {
    // Tables might not exist
}

$rows = [];
try {
    $rows = $pdo->query("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_type, account_code")->fetchAll();
} catch (PDOException $e) { 
    $rows = []; 
}

$csrfToken = CSRF::getToken();
?>

<div class="dashboard-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="margin: 0;">Chart of Accounts</h2>
        <?php if ($hasFinancialData && $dataCounts['journal_entries'] == 0): ?>
        <button onclick="initializeAccounting()" class="btn btn-primary" style="padding: 8px 16px;">
            🔄 Initialize with Existing Data
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($hasFinancialData && $dataCounts['journal_entries'] == 0): ?>
    <div class="alert alert-info" style="margin-bottom: 16px; background: linear-gradient(135deg, rgba(59,130,246,0.1) 0%, rgba(59,130,246,0.05) 100%); border: 2px solid rgba(59,130,246,0.3);">
        <div style="display: flex; align-items: start; gap: 12px;">
            <span style="font-size: 24px;">💡</span>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 8px 0; color: #3b82f6;">Process Existing Financial Data</h3>
                <p style="margin: 0 0 12px 0; color: var(--text); font-size: 14px; line-height: 1.6;">
                    You have existing financial data in ABBIS that hasn't been imported into the accounting system yet:
                </p>
                <ul style="margin: 0 0 12px 0; padding-left: 20px; color: var(--text); font-size: 14px; line-height: 1.8;">
                    <?php if ($dataCounts['field_reports'] > 0): ?>
                    <li><strong><?php echo number_format($dataCounts['field_reports']); ?></strong> Field Reports</li>
                    <?php endif; ?>
                    <?php if ($dataCounts['loans'] > 0): ?>
                    <li><strong><?php echo number_format($dataCounts['loans']); ?></strong> Loans</li>
                    <?php endif; ?>
                    <?php if ($dataCounts['payroll'] > 0): ?>
                    <li><strong><?php echo number_format($dataCounts['payroll']); ?></strong> Payroll Payments</li>
                    <?php endif; ?>
                    <?php if ($dataCounts['materials'] > 0): ?>
                    <li><strong><?php echo number_format($dataCounts['materials']); ?></strong> Materials Purchases</li>
                    <?php endif; ?>
                </ul>
                <p style="margin: 0; color: var(--text); font-size: 14px; line-height: 1.6;">
                    Click <strong>"Initialize with Existing Data"</strong> to process all this data into journal entries. This will create accounting records for all your existing transactions.
                </p>
            </div>
        </div>
    </div>
    <?php elseif (!$hasFinancialData): ?>
    <div class="alert alert-warning" style="margin-bottom: 16px; background: linear-gradient(135deg, rgba(245,158,11,0.1) 0%, rgba(245,158,11,0.05) 100%); border: 2px solid rgba(245,158,11,0.3);">
        <div style="display: flex; align-items: start; gap: 12px;">
            <span style="font-size: 24px;">📊</span>
            <div style="flex: 1;">
                <h3 style="margin: 0 0 8px 0; color: #f59e0b;">No Financial Data Found</h3>
                <p style="margin: 0 0 12px 0; color: var(--text); font-size: 14px; line-height: 1.6;">
                    There's no existing financial data in ABBIS to import into the accounting system. To test the accounting system:
                </p>
                <ol style="margin: 0 0 12px 0; padding-left: 20px; color: var(--text); font-size: 14px; line-height: 1.8;">
                    <li>Create a <strong>Field Report</strong> with financial data (contract sum, rig fee, wages, expenses)</li>
                    <li>Create a <strong>Loan</strong> for a worker</li>
                    <li>Mark a <strong>Payroll</strong> entry as "Paid"</li>
                    <li>Create a <strong>Materials Purchase</strong> transaction</li>
                </ol>
                <p style="margin: 0; color: var(--text); font-size: 14px; line-height: 1.6;">
                    All new transactions will be automatically tracked in the accounting system. View them in <a href="?action=journal" style="color: #3b82f6; font-weight: 600;">Journal Entries</a>.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account Name</th>
                    <th>Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 40px; color: var(--secondary);">
                        No accounts found. The accounting system will create default accounts automatically when first used.
                    </td>
                </tr>
                <?php else: ?>
                <?php 
                $currentType = '';
                foreach ($rows as $r): 
                    if ($currentType !== $r['account_type']):
                        $currentType = $r['account_type'];
                ?>
                <tr style="background: rgba(0,0,0,0.02);">
                    <td colspan="4" style="font-weight: 600; padding: 12px 8px; color: var(--primary);">
                        <?php echo htmlspecialchars($r['account_type']); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><?php echo e($r['account_code']); ?></td>
                    <td><?php echo e($r['account_name']); ?></td>
                    <td><span style="padding: 4px 8px; background: rgba(59,130,246,0.1); color: #3b82f6; border-radius: 4px; font-size: 12px;"><?php echo e($r['account_type']); ?></span></td>
                    <td>
                        <?php if ($r['is_active']): ?>
                        <span style="color: #10b981; font-weight: 600;">✓ Active</span>
                        <?php else: ?>
                        <span style="color: #ef4444; font-weight: 600;">✗ Inactive</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 16px; padding: 16px; background: #f8fafc; border-radius: 8px; border-left: 4px solid #3b82f6;">
        <h4 style="margin: 0 0 8px 0; color: #1e293b;">📚 About Chart of Accounts</h4>
        <p style="margin: 0; color: #475569; font-size: 14px; line-height: 1.6;">
            The chart of accounts is automatically created when the accounting system is first used. These accounts are used to track all financial transactions in ABBIS. 
            When you create field reports, loans, payroll payments, or materials purchases, the system automatically creates journal entries using these accounts.
        </p>
    </div>
</div>

<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" id="csrf_token_accounts">

<script>
function initializeAccounting() {
    if (!confirm('This will process all existing financial data and create accounting entries. This may take a few minutes. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Processing...';
    
    const csrfToken = document.getElementById('csrf_token_accounts')?.value || document.querySelector('input[name="csrf_token"]')?.value || '';
    
    fetch('api/initialize-accounting.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = originalText;
        
        if (data.success) {
            alert('Accounting system initialized successfully!\n\n' + 
                  'Accounts: ' + data.accounts + '\n' +
                  'Journal Entries Created: ' + data.entries + '\n' +
                  'Total Debits: GHS ' + data.total_debits + '\n' +
                  'Total Credits: GHS ' + data.total_credits + '\n\n' +
                  'Processed:\n' +
                  '- Field Reports: ' + (data.processed?.reports || 0) + '\n' +
                  '- Loans: ' + (data.processed?.loans || 0) + '\n' +
                  '- Payroll: ' + (data.processed?.payroll || 0));
            window.location.reload();
        } else {
            alert('Error: ' + (data.message || 'Initialization failed'));
        }
    })
    .catch(error => {
        btn.disabled = false;
        btn.textContent = originalText;
        console.error('Error:', error);
        alert('Error initializing accounting system. Please check the console for details.');
    });
}
</script>
