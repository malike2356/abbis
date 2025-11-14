<?php
// Lightweight snapshot derived from journal lines
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/AccountingAutoTracker.php';

$auth->requireAuth();
$auth->requirePermission('finance.access');

$pdo = getDBConnection();

// Initialize accounting system if not already done
// This ensures chart of accounts exist
try {
    $tracker = new AccountingAutoTracker($pdo);
} catch (Exception $e) {
    error_log("Error initializing accounting: " . $e->getMessage());
}

$kpis = [
    'accounts' => 0,
    'entries' => 0,
    'debits' => 0,
    'credits' => 0,
];

try { 
    $kpis['accounts'] = (int)$pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE is_active = 1")->fetchColumn(); 
} catch (Throwable $e) {
    error_log("Error counting accounts: " . $e->getMessage());
}

try { 
    $kpis['entries'] = (int)$pdo->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn(); 
} catch (Throwable $e) {
    error_log("Error counting entries: " . $e->getMessage());
}

try { 
    $kpis['debits'] = (float)$pdo->query("SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines")->fetchColumn(); 
} catch (Throwable $e) {
    error_log("Error summing debits: " . $e->getMessage());
}

try { 
    $kpis['credits'] = (float)$pdo->query("SELECT COALESCE(SUM(credit),0) FROM journal_entry_lines")->fetchColumn(); 
} catch (Throwable $e) {
    error_log("Error summing credits: " . $e->getMessage());
}

// Show warning if no data and provide link to initialize
// Also show if entries are 0 even if accounts exist (means data needs to be pulled)
$hasData = $kpis['entries'] > 0;

// Generate CSRF token for initialization
$csrfToken = CSRF::getToken();
?>

<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
    <div class="dashboard-card"><div class="stat-info"><h3><?php echo number_format($kpis['accounts']); ?></h3><p>Accounts</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3><?php echo number_format($kpis['entries']); ?></h3><p>Journal Entries</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3>GHS <?php echo number_format($kpis['debits'],2); ?></h3><p>Total Debits</p></div></div>
    <div class="dashboard-card"><div class="stat-info"><h3>GHS <?php echo number_format($kpis['credits'],2); ?></h3><p>Total Credits</p></div></div>
</div>

<?php 
// Check if there are field reports in the system but no journal entries
$fieldReportsCount = 0;
try {
    $fieldReportsCount = (int)$pdo->query("SELECT COUNT(*) FROM field_reports")->fetchColumn();
} catch (Throwable $e) {
    error_log("Error counting field reports: " . $e->getMessage());
}

$needsInitialization = !$hasData && $fieldReportsCount > 0;
?>

<?php if (!$hasData || $needsInitialization): ?>
<div class="dashboard-card" style="margin-top:16px; background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%); border: 2px solid rgba(37, 99, 235, 0.3);">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <span style="font-size: 32px;">📊</span>
        <div>
            <h2 style="margin: 0; color: #2563eb;"><?php echo $needsInitialization ? '⚠️ Initialize Accounting System' : 'Pull Field Reports to Accounting'; ?></h2>
            <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                <?php if ($needsInitialization): ?>
                    Found <?php echo number_format($fieldReportsCount); ?> field report(s) in your system. Import them into accounting now.
                <?php else: ?>
                    Import all existing field reports from your system into the accounting module
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div style="background: var(--card); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
        <p style="margin: 0; color: var(--text); font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
            <strong>🔄 Import All Financial Data:</strong> This will pull all existing field reports, loans, materials purchases, and payroll transactions from your system and create proper accounting journal entries for them.
        </p>
        <p style="margin: 0 0 12px 0; color: var(--secondary); font-size: 13px;">
            The system will:
        </p>
        <ul style="margin: 0 0 12px 0; padding-left: 20px; color: var(--secondary); font-size: 13px; line-height: 1.8;">
            <li>✅ Process all field reports from <code>field-reports-list.php</code></li>
            <li>✅ Create journal entries for all income and expenses</li>
            <li>✅ Process all worker loans (disbursements & repayments)</li>
            <li>✅ Process all materials purchases</li>
            <li>✅ Skip duplicates if run multiple times</li>
        </ul>
        <div style="background: rgba(37, 99, 235, 0.1); padding: 12px; border-radius: 8px; margin-bottom: 12px; border-left: 4px solid #2563eb;">
            <p style="margin: 0; color: var(--text); font-size: 13px; font-weight: 600;">
                💡 <strong>Note:</strong> This will create double-entry bookkeeping records for all your existing financial transactions. The process may take a few minutes depending on the number of records.
            </p>
        </div>
        <div style="display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" id="csrf_token">
            <button onclick="reconcileAccounting()" class="btn btn-primary" style="padding: 12px 24px; font-size: 15px; font-weight: 600; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                🔍 Scan & Reconcile Accounting
            </button>
            <button onclick="initializeAccounting()" class="btn btn-outline" style="padding: 10px 20px;">
                🔄 Quick Initialize
            </button>
            <a href="?action=accounts" class="btn btn-outline" style="padding: 10px 20px; text-decoration: none;">
                📚 View Accounts
            </a>
        </div>
        <div style="margin-top: 12px; padding: 12px; background: rgba(37, 99, 235, 0.05); border-radius: 8px; border-left: 3px solid #2563eb;">
            <p style="margin: 0; font-size: 12px; color: var(--secondary); line-height: 1.6;">
                <strong>💡 Recommended:</strong> Use "Scan & Reconcile" for comprehensive checking. It will scan all records, populate accounting, check for balance issues, detect discrepancies, auto-fix what it can, and flag items needing your review.
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="dashboard-card" style="margin-top:16px; background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, rgba(14,165,233,0.02) 100%); border: 2px solid rgba(14,165,233,0.3);">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <span style="font-size: 32px;">🤖</span>
        <div>
            <h2 style="margin: 0; color: #0ea5e9;">Automated Accounting</h2>
            <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                All financial transactions are automatically tracked system-wide
            </p>
        </div>
    </div>
    <div style="background: var(--card); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
        <p style="margin: 0; color: var(--text); font-size: 14px; line-height: 1.6;">
            <strong>✨ Fully Automated:</strong> Field reports, payroll, loans, and materials purchases are automatically 
            recorded in the accounting system using proper double-entry bookkeeping. No manual data entry required!
        </p>
        <p style="margin: 12px 0 0 0; color: var(--secondary); font-size: 13px;">
            View all automated entries in <a href="?action=journal" style="color: #0ea5e9;">Journal Entries</a> or check 
            <a href="?action=ledger" style="color: #0ea5e9;">Ledgers</a> for account-specific details.
        </p>
    </div>
</div>

<div class="dashboard-card" style="margin-top:16px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(5, 150, 105, 0.05) 100%); border: 2px solid rgba(16, 185, 129, 0.3);">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
        <span style="font-size: 32px;">🔍</span>
        <div>
            <h2 style="margin: 0; color: #059669;">Scan & Reconcile Accounting</h2>
            <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">
                Comprehensive scan, balance check, and discrepancy detection
            </p>
        </div>
    </div>
    <div style="background: var(--card); padding: 16px; border-radius: 8px; margin-bottom: 16px;">
        <p style="margin: 0; color: var(--text); font-size: 14px; line-height: 1.6; margin-bottom: 12px;">
            <strong>🔍 Full Reconciliation:</strong> This comprehensive tool will scan all ABBIS records (field reports, loans, materials, payroll), populate accounting entries, verify book balance, detect discrepancies, auto-fix issues where possible, and flag items requiring your review.
        </p>
        <ul style="margin: 0 0 12px 0; padding-left: 20px; color: var(--secondary); font-size: 13px; line-height: 1.8;">
            <li>✅ Scans all financial records in ABBIS</li>
            <li>✅ Creates missing journal entries</li>
            <li>✅ Verifies all entries are balanced</li>
            <li>✅ Detects discrepancies and calculation errors</li>
            <li>✅ Auto-fixes issues that can be resolved automatically</li>
            <li>✅ Flags items needing manual review</li>
            <li>✅ Provides detailed reconciliation report</li>
        </ul>
        <div style="display: flex; gap: 8px; margin-top: 16px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>" id="csrf_token_reconcile">
            <button onclick="reconcileAccounting()" class="btn btn-primary" style="padding: 12px 24px; font-size: 15px; font-weight: 600; background: linear-gradient(135deg, #10b981 0%, #059669 100%); border: none; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); color: white;">
                🔍 Scan & Reconcile Now
            </button>
        </div>
    </div>
</div>

<div class="dashboard-card" style="margin-top:16px;">
    <h2>Quick Actions</h2>
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px,1fr)); gap:12px;">
        <a href="?action=accounts" class="btn btn-outline">📚 Manage Accounts</a>
        <a href="?action=journal" class="btn btn-outline">🧾 New Journal Entry</a>
        <a href="?action=trial" class="btn btn-outline">🧮 View Trial Balance</a>
        <a href="?action=integrations" class="btn btn-outline">🔌 Integrations</a>
    </div>
</div>

<script>
function reconcileAccounting() {
    if (!confirm('This will scan all ABBIS records, populate accounting, check balances, and detect/fix discrepancies. This may take a few minutes. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Scanning & Reconciling...';
    
    // Show progress indicator
    const progressDiv = document.createElement('div');
    progressDiv.id = 'reconciliation-progress';
    progressDiv.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.2); z-index: 10000; max-width: 500px; text-align: center;';
    progressDiv.innerHTML = '<div style="font-size: 48px; margin-bottom: 16px;">🔍</div><div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Scanning & Reconciling Accounting</div><div style="font-size: 14px; color: #666; margin-bottom: 20px;">This may take a few minutes...</div><div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #2563eb; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div><style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>';
    document.body.appendChild(progressDiv);
    
    const formData = new FormData();
    const csrfToken = document.getElementById('csrf_token')?.value || document.getElementById('csrf_token_reconcile')?.value;
    if (!csrfToken) {
        alert('❌ Error: CSRF token not found. Please refresh the page.');
        btn.disabled = false;
        btn.innerHTML = originalText;
        return;
    }
    formData.append('csrf_token', csrfToken);
    
    fetch('../api/reconcile-accounting.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        document.body.removeChild(progressDiv);
        
        if (data.success) {
            let message = '✅ ' + data.message + '\n\n';
            message += '📊 SUMMARY:\n';
            message += '• Scanned: ' + data.summary.total_scanned + ' records\n';
            message += '• Processed: ' + data.summary.total_processed + ' new entries\n';
            message += '• Skipped: ' + data.summary.total_skipped + ' (already processed)\n';
            message += '• Auto-fixed: ' + data.summary.total_auto_fixed + ' issues\n';
            message += '• Discrepancies: ' + data.summary.total_discrepancies + '\n';
            message += '• Needs Review: ' + data.summary.total_needs_review + '\n\n';
            
            // Balance status
            if (data.summary.is_balanced) {
                message += '✅ Books are balanced!\n';
            } else {
                message += '⚠️ Books are NOT balanced!\n';
                if (data.results.balance_check) {
                    message += '   Debits: ' + parseFloat(data.results.balance_check.total_debits).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\n';
                    message += '   Credits: ' + parseFloat(data.results.balance_check.total_credits).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\n';
                    message += '   Difference: ' + parseFloat(data.results.balance_check.difference).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '\n';
                }
            }
            
            // Show discrepancies if any
            if (data.results.discrepancies && data.results.discrepancies.length > 0) {
                message += '\n⚠️ DISCREPANCIES FOUND:\n';
                data.results.discrepancies.slice(0, 10).forEach((disc, idx) => {
                    message += (idx + 1) + '. [' + disc.severity.toUpperCase() + '] ' + disc.message + '\n';
                    if (disc.action_required) {
                        message += '   → Action: ' + disc.action_required + '\n';
                    }
                });
                if (data.results.discrepancies.length > 10) {
                    message += '... and ' + (data.results.discrepancies.length - 10) + ' more. See full report after reload.\n';
                }
            }
            
            // Show items needing review
            if (data.results.needs_review && data.results.needs_review.length > 0) {
                message += '\n📋 NEEDS REVIEW:\n';
                data.results.needs_review.slice(0, 5).forEach((item, idx) => {
                    message += (idx + 1) + '. ' + item.message + '\n';
                });
                if (data.results.needs_review.length > 5) {
                    message += '... and ' + (data.results.needs_review.length - 5) + ' more.\n';
                }
            }
            
            // Store results in sessionStorage for detailed view
            sessionStorage.setItem('reconciliation_results', JSON.stringify(data));
            
            alert(message);
            
            // Show detailed results in a modal or reload
            if (data.summary.total_discrepancies > 0 || data.summary.total_needs_review > 0) {
                if (confirm('Reconciliation complete! Some items need your attention. View detailed report now?')) {
                    showReconciliationReport(data);
                } else {
                    window.location.reload();
                }
            } else {
                window.location.reload();
            }
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error occurred'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        if (document.getElementById('reconciliation-progress')) {
            document.body.removeChild(document.getElementById('reconciliation-progress'));
        }
        console.error('Reconciliation error:', error);
        alert('❌ Error: ' + (error.message || 'Failed to reconcile accounting. Please check the console for details.'));
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}

function showReconciliationReport(data) {
    // Create modal for detailed report
    const modal = document.createElement('div');
    modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10001; overflow-y: auto; padding: 20px;';
    modal.innerHTML = `
        <div style="background: white; max-width: 900px; margin: 20px auto; border-radius: 12px; padding: 30px; box-shadow: 0 8px 32px rgba(0,0,0,0.3);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2 style="margin: 0; color: #2563eb;">📊 Reconciliation Report</h2>
                <button onclick="this.closest('div[style*=\"position: fixed\"]').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            </div>
            <div style="max-height: 600px; overflow-y: auto;">
                ${generateReportHTML(data)}
            </div>
            <div style="margin-top: 24px; text-align: center;">
                <button onclick="this.closest('div[style*=\"position: fixed\"]').remove(); window.location.reload();" style="padding: 10px 24px; background: #2563eb; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Close & Reload</button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
}

function generateReportHTML(data) {
    let html = '<div style="line-height: 1.8;">';
    
    // Summary
    html += '<div style="background: #f0f9ff; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2563eb;">';
    html += '<h3 style="margin: 0 0 12px 0;">Summary</h3>';
    html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; font-size: 14px;">';
    html += '<div><strong>Scanned:</strong> ' + data.summary.total_scanned + '</div>';
    html += '<div><strong>Processed:</strong> ' + data.summary.total_processed + '</div>';
    html += '<div><strong>Skipped:</strong> ' + data.summary.total_skipped + '</div>';
    html += '<div><strong>Auto-fixed:</strong> ' + data.summary.total_auto_fixed + '</div>';
    html += '<div><strong>Discrepancies:</strong> ' + data.summary.total_discrepancies + '</div>';
    html += '<div><strong>Needs Review:</strong> ' + data.summary.total_needs_review + '</div>';
    html += '</div>';
    html += '</div>';
    
    // Balance Status
    if (data.results.balance_check) {
        const bc = data.results.balance_check;
        const balanceColor = bc.is_balanced ? '#10b981' : '#ef4444';
        html += '<div style="background: ' + (bc.is_balanced ? '#f0fdf4' : '#fef2f2') + '; padding: 16px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid ' + balanceColor + ';">';
        html += '<h3 style="margin: 0 0 12px 0; color: ' + balanceColor + ';">' + (bc.is_balanced ? '✅ Books Balanced' : '⚠️ Books Unbalanced') + '</h3>';
        html += '<div style="font-size: 14px;">';
        html += '<div><strong>Total Debits:</strong> ' + parseFloat(bc.total_debits).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</div>';
        html += '<div><strong>Total Credits:</strong> ' + parseFloat(bc.total_credits).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</div>';
        html += '<div><strong>Difference:</strong> ' + parseFloat(bc.difference).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + '</div>';
        html += '<div><strong>Total Entries:</strong> ' + bc.total_entries + '</div>';
        html += '</div>';
        html += '</div>';
    }
    
    // Discrepancies
    if (data.results.discrepancies && data.results.discrepancies.length > 0) {
        html += '<div style="margin-bottom: 20px;">';
        html += '<h3 style="margin: 0 0 12px 0; color: #ef4444;">⚠️ Discrepancies (' + data.results.discrepancies.length + ')</h3>';
        data.results.discrepancies.forEach((disc, idx) => {
            const severityColor = disc.severity === 'critical' ? '#ef4444' : disc.severity === 'high' ? '#f59e0b' : '#3b82f6';
            html += '<div style="background: #fef2f2; padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 4px solid ' + severityColor + ';">';
            html += '<div style="font-weight: 600; color: ' + severityColor + ';">[' + disc.severity.toUpperCase() + '] ' + (disc.type || 'Unknown') + '</div>';
            html += '<div style="margin-top: 4px; font-size: 14px;">' + disc.message + '</div>';
            if (disc.action_required) {
                html += '<div style="margin-top: 8px; padding: 8px; background: rgba(239, 68, 68, 0.1); border-radius: 4px; font-size: 13px;"><strong>Action Required:</strong> ' + disc.action_required + '</div>';
            }
            html += '</div>';
        });
        html += '</div>';
    }
    
    // Needs Review
    if (data.results.needs_review && data.results.needs_review.length > 0) {
        html += '<div style="margin-bottom: 20px;">';
        html += '<h3 style="margin: 0 0 12px 0; color: #f59e0b;">📋 Needs Review (' + data.results.needs_review.length + ')</h3>';
        data.results.needs_review.forEach((item, idx) => {
            html += '<div style="background: #fffbeb; padding: 12px; border-radius: 8px; margin-bottom: 8px; border-left: 4px solid #f59e0b;">';
            html += '<div style="font-weight: 600;">' + (item.type || 'Item') + '</div>';
            html += '<div style="margin-top: 4px; font-size: 14px;">' + item.message + '</div>';
            html += '</div>';
        });
        html += '</div>';
    }
    
    html += '</div>';
    return html;
}

function initializeAccounting() {
    if (!confirm('This will process all existing financial data and create accounting entries. This may take a few minutes. Continue?')) {
        return;
    }
    
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '⏳ Processing...';
    
    const formData = new FormData();
    formData.append('csrf_token', document.getElementById('csrf_token').value);
    
    fetch('../api/initialize-accounting.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            let message = '✅ ' + data.message + '\n\n';
            message += 'Field Reports: ' + data.results.field_reports.processed + ' processed, ' + data.results.field_reports.skipped + ' skipped\n';
            message += 'Loans: ' + data.results.loans.processed + ' processed, ' + data.results.loans.skipped + ' skipped\n';
            message += 'Materials: ' + data.results.materials.processed + ' processed, ' + data.results.materials.skipped + ' skipped';
            
            if (data.results.field_reports.errors && data.results.field_reports.errors.length > 0) {
                message += '\n\n⚠️ Some errors occurred:\n' + data.results.field_reports.errors.slice(0, 5).join('\n');
            }
            
            alert(message);
            window.location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error occurred'));
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Initialization error:', error);
        alert('❌ Error: ' + (error.message || 'Failed to initialize accounting. Please check the console for details.'));
        btn.disabled = false;
        btn.innerHTML = originalText;
    });
}
</script>
