<?php
/**
 * Data Management - Import/Export & Purge
 */
$page_title = 'Data Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

// Get system statistics
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'rigs' => $pdo->query("SELECT COUNT(*) FROM rigs")->fetchColumn(),
    'workers' => $pdo->query("SELECT COUNT(*) FROM workers")->fetchColumn(),
    'clients' => $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn(),
    'reports' => $pdo->query("SELECT COUNT(*) FROM field_reports")->fetchColumn(),
    'payroll_entries' => $pdo->query("SELECT COUNT(*) FROM payroll_entries")->fetchColumn(),
    'materials' => 0,
    'active_loans' => 0
];

// Get materials count (handle if table doesn't exist)
try {
    $stats['materials'] = $pdo->query("SELECT COUNT(*) FROM materials_inventory")->fetchColumn();
} catch (PDOException $e) {
    $stats['materials'] = 0;
}

// Get active loans count (handle if table doesn't exist)
try {
    $stats['active_loans'] = $pdo->query("SELECT COUNT(*) FROM loans WHERE status = 'active'")->fetchColumn();
} catch (PDOException $e) {
    $stats['active_loans'] = 0;
}

// Get catalog counts (handle if tables don't exist)
try {
    $stats['catalog_items'] = $pdo->query("SELECT COUNT(*) FROM catalog_items")->fetchColumn();
} catch (PDOException $e) {
    $stats['catalog_items'] = 0;
}
try {
    $stats['catalog_categories'] = $pdo->query("SELECT COUNT(*) FROM catalog_categories")->fetchColumn();
} catch (PDOException $e) {
    $stats['catalog_categories'] = 0;
}

require_once '../includes/header.php';
?>

<style>
    .data-management-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: linear-gradient(135deg, var(--card) 0%, rgba(14,165,233,0.03) 100%);
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 20px;
        text-align: center;
    }
    .stat-card .value {
        font-size: 32px;
        font-weight: 800;
        color: var(--primary);
        margin: 10px 0;
    }
    .stat-card .label {
        color: var(--secondary);
        font-size: 14px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .danger-zone {
        border: 2px solid #ef4444;
        background: #fef2f2;
        border-radius: 12px;
        padding: 24px;
        margin-top: 30px;
    }
    .danger-zone h3 {
        color: #dc2626;
        margin-bottom: 15px;
    }
    .password-confirm-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }
    @media (max-width: 768px) {
        .password-confirm-group {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1>💾 Data Management</h1>
        <p>Import, export, and manage system data</p>
    </div>
</div>

<!-- System Statistics -->
<div class="data-management-grid">
    <div class="stat-card">
        <div class="label">Users</div>
        <div class="value"><?php echo number_format($stats['users']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Rigs</div>
        <div class="value"><?php echo number_format($stats['rigs']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Workers</div>
        <div class="value"><?php echo number_format($stats['workers']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Clients</div>
        <div class="value"><?php echo number_format($stats['clients']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Field Reports</div>
        <div class="value"><?php echo number_format($stats['reports']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Payroll Entries</div>
        <div class="value"><?php echo number_format($stats['payroll_entries']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Materials Items</div>
        <div class="value"><?php echo number_format($stats['materials']); ?></div>
    </div>
    <div class="stat-card">
        <div class="label">Active Loans</div>
        <div class="value"><?php echo number_format($stats['active_loans']); ?></div>
    </div>
</div>

<div class="dashboard-card" style="border-left: 4px solid rgba(59,130,246,0.5); background: linear-gradient(135deg, rgba(59,130,246,0.08), rgba(59,130,246,0.02));">
    <h2>🧭 Guided Onboarding Wizard</h2>
    <p style="color: var(--secondary); margin-bottom: 16px;">
        Need to import clients, rigs, workers, or catalog items from spreadsheets? Launch the step-by-step onboarding wizard for column mapping, preview, and import tracking.
    </p>
    <a href="onboarding-wizard.php" class="btn btn-primary">
        Launch Onboarding Wizard →
    </a>
</div>

<!-- Export Section -->
<div class="dashboard-card">
    <h2>📤 Export System Data</h2>
    <p style="color: var(--secondary); margin-bottom: 20px;">
        Export all system data for backup or transfer. Supports multiple formats.
    </p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="<?php echo api_url('export.php', ['module' => 'system', 'format' => 'json']); ?>" class="btn btn-primary" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; margin-bottom: 10px;">📄</div>
            <div><strong>Export JSON</strong></div>
            <small style="display: block; margin-top: 5px; opacity: 0.8;">Complete data in JSON format</small>
        </a>
        
        <a href="<?php echo api_url('export.php', ['module' => 'system', 'format' => 'sql']); ?>" class="btn btn-primary" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; margin-bottom: 10px;">🗄️</div>
            <div><strong>Export SQL</strong></div>
            <small style="display: block; margin-top: 5px; opacity: 0.8;">SQL INSERT statements</small>
        </a>
        
        <a href="<?php echo api_url('export.php', ['module' => 'system', 'format' => 'csv']); ?>" class="btn btn-primary" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; margin-bottom: 10px;">📊</div>
            <div><strong>Export CSV</strong></div>
            <small style="display: block; margin-top: 5px; opacity: 0.8;">ZIP with CSV files</small>
        </a>
    </div>
</div>

<div class="dashboard-card">
    <h2>🖥️ Command Line Imports</h2>
    <p style="color: var(--secondary); margin-bottom: 16px;">
        Automate onboarding by running imports from the server terminal. Best for large datasets or scripted migrations.
    </p>
    <pre style="background: #0f172a; color: #e2e8f0; padding: 14px 18px; border-radius: 8px; font-size: 13px; overflow-x: auto;">
php scripts/import-dataset.php clients storage/import/clients.csv
php scripts/import-dataset.php rigs data/rigs.csv --delimiter=";" --no-update</pre>
    <p style="color: var(--secondary); font-size: 13px;">
        Use <code>--delimiter</code> to override CSV delimiter, <code>--no-update</code> to prevent updates, and <code>--allow-blank-overwrite</code> to overwrite existing values with blanks.
    </p>
</div>

<div class="dashboard-card" style="border-left: 4px solid rgba(16,185,129,0.45); background: linear-gradient(135deg, rgba(16,185,129,0.08), rgba(16,185,129,0.02));">
    <h2>🪱 Geology Wells Dataset</h2>
    <p style="color: var(--secondary); margin-bottom: 16px;">
        Feed the Geology Estimator with historical well logs. Upload latitude/longitude, depth, lithology, aquifer type, and yield information to improve predictions.
    </p>
    <ul style="color: var(--secondary); font-size: 13px; margin: 0 0 18px 18px;">
        <li>Download a sample template from the <strong>Onboarding Wizard</strong> (choose Geology Wells).</li>
        <li>Include accurate coordinates (WGS84) and drilled depth (meters).</li>
        <li>Optional fields: water level, yield, lithology, aquifer type, TDS, confidence score.</li>
    </ul>
    <div style="display:flex; flex-wrap:wrap; gap:12px;">
        <a href="onboarding-wizard.php" class="btn btn-primary">Import Geology Wells →</a>
        <a href="geology-estimator.php" class="btn btn-primary" style="background: rgba(16,185,129,0.12); color:#047857; border-color: rgba(16,185,129,0.25);">
            Open Geology Estimator
        </a>
    </div>
</div>

<!-- Test Data Generation Section -->
<div class="dashboard-card" style="border-left: 4px solid #fbbf24; background: linear-gradient(135deg, #fffbeb 0%, #fff7ed 100%);">
    <h2>🧪 Generate Test Data</h2>
    <p style="color: var(--secondary); margin-bottom: 20px;">
        Generate dummy field reports for testing purposes (e.g., testing receipt and technical report generation).
        <strong style="color: #d97706;">⚠️ These are test records and should be purged before deployment.</strong>
    </p>
    
    <form id="testDataForm" method="POST" action="<?php echo api_url('insert-dummy-reports.php'); ?>" class="ajax-form">
        <?php echo CSRF::getTokenField(); ?>
        
        <div class="form-group">
            <label for="report_count" class="form-label">Number of Reports to Generate</label>
            <input type="number" id="report_count" name="count" class="form-control" 
                   value="5" min="1" max="50" required>
            <small class="form-text">
                Enter the number of dummy field reports you want to create (1-50). 
                Each report includes client data, payroll entries, expenses, and financial information.
            </small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn" style="background: #fbbf24; color: #78350f; border-color: #fbbf24; font-weight: 600;">
                🧪 Generate Test Reports
            </button>
        </div>
    </form>
    
    <div id="testDataResult" style="margin-top: 20px;"></div>
</div>

<script>
// Handle test data form submission
document.addEventListener('DOMContentLoaded', function() {
    const testDataForm = document.getElementById('testDataForm');
    if (testDataForm) {
        testDataForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(testDataForm);
            const count = formData.get('count');
            const resultDiv = document.getElementById('testDataResult');
            const submitBtn = testDataForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            // Validate count
            if (count < 1 || count > 50) {
                resultDiv.innerHTML = '<div class="alert alert-danger">Please enter a number between 1 and 50.</div>';
                return;
            }
            
            if (!confirm(`This will create ${count} dummy field report(s) for testing. Continue?`)) {
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Generating...';
            resultDiv.innerHTML = '<div class="alert alert-info">⏳ Generating test reports, please wait...</div>';
            
            fetch(testDataForm.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success">
                        ✅ Successfully created ${data.count} dummy report(s)!<br>
                        Report IDs: ${data.reports.join(', ')}<br>
                        <small>You can now test receipt and technical report generation.</small>
                    </div>`;
                    
                    // Reload page after 2 seconds to show new reports
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${data.message || 'Failed to create dummy reports'}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalBtnText;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${error.message}</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
        });
    }
});
</script>

<!-- Import Section -->
<div class="dashboard-card">
    <h2>📥 Import System Data</h2>
    <p style="color: var(--secondary); margin-bottom: 20px;">
        Import data from a previous export to prepopulate the system.
    </p>
    
    <form id="importForm" method="POST" action="<?php echo api_url('system-import.php'); ?>" enctype="multipart/form-data" class="ajax-form">
        <?php echo CSRF::getTokenField(); ?>
        
        <div class="form-group">
            <label for="import_file" class="form-label">Import File</label>
            <input type="file" id="import_file" name="import_file" class="form-control" 
                   accept=".json,.sql,.zip" required>
            <small class="form-text">Supported formats: JSON, SQL, or ZIP (with CSV files)</small>
        </div>
        
        <div class="form-group">
            <label for="import_mode" class="form-label">Import Mode</label>
            <select id="import_mode" name="import_mode" class="form-control" required>
                <option value="append">Append (Add to existing data)</option>
                <option value="replace">Replace (Delete existing data first)</option>
            </select>
            <small class="form-text" style="color: #ef4444;">
                ⚠️ Replace mode will delete existing data before importing!
            </small>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">📥 Import Data</button>
        </div>
    </form>
    
    <div id="importResult" style="margin-top: 20px;"></div>
</div>

<!-- Danger Zone - Purge -->
<div class="danger-zone">
    <h3>⚠️ Danger Zone - Data Purge</h3>
    <p style="color: #dc2626; margin-bottom: 20px;">
        <strong>WARNING:</strong> Selected data will be permanently deleted. 
        This action cannot be undone. Make sure you have exported a backup before proceeding.
    </p>
    
    <form id="purgeForm" method="POST" action="<?php echo api_url('system-purge.php'); ?>" class="ajax-form">
        <?php echo CSRF::getTokenField(); ?>
        
        <!-- Purge Mode Selection -->
        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label" style="font-weight: 600; margin-bottom: 12px;">Purge Mode</label>
            <div style="display: flex; gap: 12px;">
                <label style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" id="mode_all_label" onmouseover="this.style.borderColor='#dc2626'" onmouseout="if(!document.getElementById('purge_mode_all').checked) this.style.borderColor='#e2e8f0'">
                    <input type="radio" id="purge_mode_all" name="purge_mode" value="all" style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 500;">🗑️ Purge Everything</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s;" id="mode_selective_label" onmouseover="this.style.borderColor='#7c3aed'" onmouseout="if(!document.getElementById('purge_mode_selective').checked) this.style.borderColor='#e2e8f0'">
                    <input type="radio" id="purge_mode_selective" name="purge_mode" value="selective" checked style="width: 18px; height: 18px; cursor: pointer;">
                    <span style="font-weight: 500;">✅ Selective Purge (Choose what to delete)</span>
                </label>
            </div>
        </div>
        
        <!-- Selective Purge Options -->
        <div id="selectivePurgeOptions" style="margin-bottom: 24px; padding: 20px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
            <label class="form-label" style="font-weight: 600; margin-bottom: 16px;">Select Data to Purge:</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <!-- Operational Data -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Operational Data</h4>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="field_reports" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📋 Field Reports</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['reports']); ?> reports</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="payroll_entries" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">💰 Payroll Entries</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['payroll_entries']); ?> entries</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="expense_entries" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">💸 Expenses</div>
                            <div style="font-size: 12px; color: #64748b;">All expense records</div>
                        </div>
                    </label>
                </div>
                
                <!-- People & Assets -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">People & Assets</h4>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="workers" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">👷 Workers</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['workers']); ?> workers</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="clients" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">👤 Clients</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['clients']); ?> clients</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="rigs" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">🚜 Rigs</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['rigs']); ?> rigs</div>
                        </div>
                    </label>
                </div>
                
                <!-- Financial & Inventory -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Financial & Inventory</h4>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="worker_loans" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">💳 Worker Loans</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['active_loans']); ?> active loans</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="rig_fee_debts" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">💵 Rig Fee Debts</div>
                            <div style="font-size: 12px; color: #64748b;">All debt records</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="materials_inventory" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📦 Materials Inventory</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['materials']); ?> items</div>
                        </div>
                    </label>
                </div>
                
                <!-- Catalog -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">Catalog</h4>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="catalog_items" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📦 Catalog Items</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['catalog_items']); ?> items</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="catalog_categories" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📁 Catalog Categories</div>
                            <div style="font-size: 12px; color: #64748b;"><?php echo number_format($stats['catalog_categories']); ?> categories</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="catalog_price_history" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📊 Price History</div>
                            <div style="font-size: 12px; color: #64748b;">All price change records</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="field_report_items" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">🔗 Field Report Items</div>
                            <div style="font-size: 12px; color: #64748b;">Catalog item links in reports</div>
                        </div>
                    </label>
                </div>
                
                <!-- System Data -->
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <h4 style="font-size: 13px; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin: 0;">System Data</h4>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="login_attempts" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">🔐 Login Attempts</div>
                            <div style="font-size: 12px; color: #64748b;">Security logs</div>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; background: white; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='white'">
                        <input type="checkbox" name="purge_tables[]" value="cache_stats" class="purge-checkbox">
                        <div style="flex: 1;">
                            <div style="font-weight: 500; font-size: 14px;">📊 Cache & Stats</div>
                            <div style="font-size: 12px; color: #64748b;">Cached statistics</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div style="margin-top: 16px; padding: 12px; background: #fff7ed; border-left: 4px solid #f59e0b; border-radius: 4px;">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                    <button type="button" onclick="selectAllPurge()" class="btn" style="padding: 6px 12px; font-size: 12px; background: #f59e0b; color: white;">Select All</button>
                    <button type="button" onclick="deselectAllPurge()" class="btn" style="padding: 6px 12px; font-size: 12px; background: #64748b; color: white;">Deselect All</button>
                </div>
                <small style="color: #92400e; font-size: 12px;">
                    💡 <strong>Note:</strong> Unchecked items will be kept. Users table is always preserved for security.
                </small>
            </div>
        </div>
        
        <div class="password-confirm-group">
            <div class="form-group">
                <label for="purge_password" class="form-label">Your Password</label>
                <input type="password" id="purge_password" name="password" class="form-control" required>
                <small class="form-text">Enter your account password to confirm</small>
            </div>
            
            <div class="form-group">
                <label for="purge_password_confirm" class="form-label">Confirm Password</label>
                <input type="password" id="purge_password_confirm" name="password_confirm" class="form-control" required>
                <small class="form-text">Re-enter your password</small>
            </div>
        </div>
        
        <div class="form-group">
            <label for="confirm_text" class="form-label">Type to Confirm</label>
            <input type="text" id="confirm_text" name="confirm_text" class="form-control" 
                   placeholder="Type 'DELETE ALL DATA' or 'DELETE SELECTED DATA'" required>
            <small class="form-text" style="color: #dc2626;">
                Type <strong id="confirm_required_text">DELETE SELECTED DATA</strong> exactly as shown to enable purge
            </small>
        </div>
        
        <div class="form-actions">
            <button type="submit" id="purgeSubmitBtn" class="btn" style="background: #dc2626; color: white; border-color: #dc2626;">
                🗑️ Purge Selected Data
            </button>
        </div>
    </form>
    
    <div id="purgeResult" style="margin-top: 20px;"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle import form
    const importForm = document.getElementById('importForm');
    if (importForm) {
        importForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const resultDiv = document.getElementById('importResult');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Importing...';
            resultDiv.innerHTML = '';
            
            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ ${result.message}<br>
                            ${result.imported ? 'Imported: ' + JSON.stringify(result.imported, null, 2) : ''}
                        </div>
                    `;
                    setTimeout(() => location.reload(), 3000);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ ${result.message}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            } catch (error) {
                resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${error.message}</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        });
    }
    
    // Handle purge mode switching
    const purgeModeAll = document.getElementById('purge_mode_all');
    const purgeModeSelective = document.getElementById('purge_mode_selective');
    const selectiveOptions = document.getElementById('selectivePurgeOptions');
    const confirmTextInput = document.getElementById('confirm_text');
    const confirmRequiredText = document.getElementById('confirm_required_text');
    const purgeSubmitBtn = document.getElementById('purgeSubmitBtn');
    
    function updatePurgeMode() {
        const modeAllLabel = document.getElementById('mode_all_label');
        const modeSelectiveLabel = document.getElementById('mode_selective_label');
        
        if (purgeModeAll.checked) {
            selectiveOptions.style.display = 'none';
            confirmRequiredText.textContent = 'DELETE ALL DATA';
            confirmTextInput.placeholder = "Type 'DELETE ALL DATA' to confirm";
            purgeSubmitBtn.innerHTML = '🗑️ Purge All Data';
            
            // Update visual styling
            if (modeAllLabel) {
                modeAllLabel.style.borderColor = '#dc2626';
                modeAllLabel.style.backgroundColor = '#fef2f2';
            }
            if (modeSelectiveLabel) {
                modeSelectiveLabel.style.borderColor = '#e2e8f0';
                modeSelectiveLabel.style.backgroundColor = 'transparent';
            }
        } else {
            selectiveOptions.style.display = 'block';
            confirmRequiredText.textContent = 'DELETE SELECTED DATA';
            confirmTextInput.placeholder = "Type 'DELETE SELECTED DATA' to confirm";
            purgeSubmitBtn.innerHTML = '🗑️ Purge Selected Data';
            
            // Update visual styling
            if (modeSelectiveLabel) {
                modeSelectiveLabel.style.borderColor = '#7c3aed';
                modeSelectiveLabel.style.backgroundColor = '#f5f3ff';
            }
            if (modeAllLabel) {
                modeAllLabel.style.borderColor = '#e2e8f0';
                modeAllLabel.style.backgroundColor = 'transparent';
            }
        }
    }
    
    if (purgeModeAll) purgeModeAll.addEventListener('change', updatePurgeMode);
    if (purgeModeSelective) purgeModeSelective.addEventListener('change', updatePurgeMode);
    updatePurgeMode(); // Initial update
    
    // Select/Deselect All functions
    window.selectAllPurge = function() {
        document.querySelectorAll('.purge-checkbox').forEach(cb => cb.checked = true);
    };
    
    window.deselectAllPurge = function() {
        document.querySelectorAll('.purge-checkbox').forEach(cb => cb.checked = false);
    };
    
    // Handle purge form with extra confirmation
    const purgeForm = document.getElementById('purgeForm');
    if (purgeForm) {
        purgeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const purgeMode = document.querySelector('input[name="purge_mode"]:checked')?.value;
            const password = document.getElementById('purge_password').value;
            const passwordConfirm = document.getElementById('purge_password_confirm').value;
            const confirmText = document.getElementById('confirm_text').value;
            
            if (password !== passwordConfirm) {
                alert('Passwords do not match!');
                return;
            }
            
            // Validate confirmation text based on mode
            if (purgeMode === 'all') {
                if (confirmText !== 'DELETE ALL DATA') {
                    alert('Please type "DELETE ALL DATA" exactly to confirm');
                    return;
                }
            } else {
                const selectedTables = Array.from(document.querySelectorAll('.purge-checkbox:checked')).map(cb => cb.value);
                if (selectedTables.length === 0) {
                    alert('Please select at least one data type to purge');
                    return;
                }
                if (confirmText !== 'DELETE SELECTED DATA') {
                    alert('Please type "DELETE SELECTED DATA" exactly to confirm');
                    return;
                }
            }
            
            // Final confirmation
            const warningMsg = purgeMode === 'all' 
                ? '⚠️ FINAL WARNING: This will PERMANENTLY DELETE ALL DATA. This cannot be undone. Are you absolutely sure?'
                : '⚠️ FINAL WARNING: This will PERMANENTLY DELETE the selected data. This cannot be undone. Are you absolutely sure?';
            
            if (!confirm(warningMsg)) {
                return;
            }
            
            const lastChanceMsg = purgeMode === 'all'
                ? '⚠️ LAST CHANCE: This action is IRREVERSIBLE. Click OK to proceed with deletion of ALL data.'
                : '⚠️ LAST CHANCE: This action is IRREVERSIBLE. Click OK to proceed with deletion of selected data.';
            
            if (!confirm(lastChanceMsg)) {
                return;
            }
            
            // Submit the form
            const formData = new FormData(this);
            
            // Add selected tables if in selective mode
            if (purgeMode === 'selective') {
                const selectedTables = Array.from(document.querySelectorAll('.purge-checkbox:checked')).map(cb => cb.value);
                // Remove existing purge_tables entries and add new ones
                formData.delete('purge_tables[]');
                selectedTables.forEach(table => {
                    formData.append('purge_tables[]', table);
                });
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            const resultDiv = document.getElementById('purgeResult');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '⏳ Purging...';
            resultDiv.innerHTML = '';
            
            fetch(this.action, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    let detailsHtml = `<div style="margin-top: 12px; padding: 12px; background: #f8fafc; border-radius: 6px;">`;
                    detailsHtml += `<strong style="display: block; margin-bottom: 8px;">Purged Tables:</strong>`;
                    
                    if (result.purged_tables && result.purged_tables.length > 0) {
                        detailsHtml += `<ul style="margin: 0; padding-left: 20px;">`;
                        result.purged_tables.forEach(table => {
                            const count = result.deleted_counts[table];
                            const countText = count === 'preserved' ? '(preserved)' : `${count} records deleted`;
                            detailsHtml += `<li style="margin: 4px 0;"><strong>${table.replace(/_/g, ' ')}</strong>: ${countText}</li>`;
                        });
                        detailsHtml += `</ul>`;
                    }
                    
                    // Show preserved tables
                    if (result.deleted_counts) {
                        const preservedTables = Object.entries(result.deleted_counts)
                            .filter(([table, count]) => count === 'preserved')
                            .map(([table]) => table);
                        
                        if (preservedTables.length > 0) {
                            detailsHtml += `<div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0;">`;
                            detailsHtml += `<strong style="display: block; margin-bottom: 8px; color: #16a34a;">Preserved Tables:</strong>`;
                            detailsHtml += `<ul style="margin: 0; padding-left: 20px;">`;
                            preservedTables.forEach(table => {
                                detailsHtml += `<li style="margin: 4px 0; color: #64748b;">${table.replace(/_/g, ' ')}</li>`;
                            });
                            detailsHtml += `</ul></div>`;
                        }
                    }
                    
                    detailsHtml += `</div>`;
                    
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            ✅ ${result.message}
                            ${detailsHtml}
                        </div>
                    `;
                    setTimeout(() => location.reload(), 5000);
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ ${result.message}</div>`;
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${error.message}</div>`;
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>

