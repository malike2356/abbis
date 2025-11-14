<?php
/**
 * Field Reports - Comprehensive Form with Tabs
 */
$page_title = 'Field Reports';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/config-manager.php';
require_once '../includes/helpers.php';
require_once '../includes/pos/PosRepository.php';

$auth->requireAuth();
$auth->requirePermission('field_reports.manage');

// Load config data dynamically (no hardcoding)
$rigs = $configManager->getRigs('active');
$workers = $configManager->getWorkers('active');
$materials = $configManager->getMaterials();
$rodLengths = $configManager->getRodLengths();
$clients = []; // Will be loaded via AJAX
$posRepo = new PosRepository();
$posStores = $posRepo->getStores();

require_once '../includes/header.php';
?>

<script>
    window.POS_STORES = <?php echo json_encode($posStores, JSON_UNESCAPED_UNICODE); ?>;
    window.SYSTEM_CURRENCY = <?php echo json_encode(getCurrency(), JSON_UNESCAPED_UNICODE); ?>;
</script>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Field Operations Report</h1>
                    <p>Create comprehensive drilling field report</p>
                </div>
                <div>
                    <a href="field-reports-list.php" class="btn btn-outline">View All Reports</a>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">🏗️</div>
                    <div class="stat-info">
                        <h3><?php echo count($rigs); ?></h3>
                        <p>Available Rigs</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">👷</div>
                    <div class="stat-info">
                        <h3><?php echo count($workers); ?></h3>
                        <p>Active Workers</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">📦</div>
                    <div class="stat-info">
                        <h3><?php echo count($materials); ?></h3>
                        <p>Material Types</p>
                    </div>
                </div>
            </div>

            <!-- Main Form with Tabs -->
            <form method="POST" action="<?php echo api_url('save-report.php'); ?>" class="ajax-form" id="fieldReportForm">
                <?php echo CSRF::getTokenField(); ?>
                
                <!-- Tab Navigation -->
                <div class="form-tabs">
                    <button type="button" class="form-tab active" data-tab="management">Management Information</button>
                    <button type="button" class="form-tab" data-tab="drilling">Drilling / Construction</button>
                    <button type="button" class="form-tab" data-tab="workers">Workers / Payroll</button>
                    <button type="button" class="form-tab" data-tab="financial">Financial Information</button>
                    <button type="button" class="form-tab" data-tab="incidents">Incident / Case Log</button>
                </div>

                <!-- Tab 1: Management Information -->
                <div class="tab-content active" id="management-tab">
                    <div class="dashboard-card">
                        <h2>Management Information</h2>
                        
                        <div class="form-grid form-grid-compact form-grid-justify">
                            <!-- Row: Report Date, Rig, Job Type, Site Name -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="report_date" class="form-label">Report Date *</label>
                                    <input type="date" id="report_date" name="report_date" class="form-control" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="rig_id" class="form-label">Rig *</label>
                                    <select id="rig_id" name="rig_id" class="form-control" required>
                                        <option value="">Select Rig</option>
                                        <?php foreach ($rigs as $rig): ?>
                                            <option value="<?php echo $rig['id']; ?>" data-rig-code="<?php echo e($rig['rig_code']); ?>">
                                                <?php echo e($rig['rig_name']); ?> (<?php echo e($rig['rig_code']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="job_type" class="form-label">Job Type *</label>
                                    <select id="job_type" name="job_type" class="form-control" required onchange="toggleMaintenanceFields()">
                                        <option value="">Select Type</option>
                                        <option value="direct">Direct Job</option>
                                        <option value="subcontract">Subcontract</option>
                                        <option value="maintenance">Maintenance Work</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" id="is_maintenance_work" name="is_maintenance_work" value="1" onchange="toggleMaintenanceFields()" style="width: auto; cursor: pointer;">
                                        <span>🔧 This is maintenance work (not drilling)</span>
                                    </label>
                                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                                        Check if team performed maintenance instead of drilling
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="site_name" class="form-label">Site Name *</label>
                                    <input type="text" id="site_name" name="site_name" class="form-control" required>
                                </div>
                            </div>
                            
                            <!-- Maintenance Work Fields (shown when maintenance is selected) -->
                            <div id="maintenanceFieldsSection" style="display: none; margin-top: 20px; padding: 16px; background: var(--bg); border-radius: 8px; border-left: 4px solid #f59e0b;">
                                <h3 style="margin: 0 0 16px 0; font-size: 16px; color: var(--text);">🔧 Maintenance Work Details</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="maintenance_work_type" class="form-label">Maintenance Type</label>
                                        <select id="maintenance_work_type" name="maintenance_work_type" class="form-control">
                                            <option value="">Select Type</option>
                                            <option value="Repair">Repair</option>
                                            <option value="Breakdown">Breakdown</option>
                                            <option value="Service">Service</option>
                                            <option value="Inspection">Inspection</option>
                                            <option value="Replacement">Replacement</option>
                                            <option value="Lubrication">Lubrication</option>
                                            <option value="Cleaning">Cleaning</option>
                                            <option value="Calibration">Calibration</option>
                                            <option value="General Maintenance">General Maintenance</option>
                                        </select>
                                        <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                                            Maintenance type will be auto-detected from logs if not specified
                                        </small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="asset_id" class="form-label">Asset/Equipment</label>
                                        <select id="asset_id" name="asset_id" class="form-control">
                                            <option value="">Select Asset (Optional)</option>
                                            <?php
                                            try {
                                                $assetsStmt = $pdo->query("SELECT id, asset_name, asset_type FROM assets WHERE is_active = 1 ORDER BY asset_name");
                                                $assets = $assetsStmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($assets as $asset) {
                                                    echo '<option value="' . $asset['id'] . '">' . e($asset['asset_name']) . ' (' . e($asset['asset_type']) . ')</option>';
                                                }
                                            } catch (PDOException $e) {
                                                // Assets table might not exist
                                            }
                                            ?>
                                        </select>
                                        <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                                            Leave empty if maintenance is on the rig itself
                                        </small>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <small class="form-text" style="display: block; color: var(--info); padding: 8px; background: rgba(14,165,233,0.1); border-radius: 4px;">
                                        💡 <strong>Tip:</strong> Describe the maintenance work in the "Incident Log" and "Solution Log" fields below. 
                                        The system will automatically extract maintenance information and create a maintenance record.
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Location Information Row -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="region" class="form-label">Region</label>
                                    <input type="text" id="region" name="region" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="latitude" class="form-label">Latitude</label>
                                    <input type="number" id="latitude" name="latitude" class="form-control" step="0.000001" placeholder="e.g., 5.6037">
                                </div>
                                
                                <div class="form-group">
                                    <label for="longitude" class="form-label">Longitude</label>
                                    <input type="number" id="longitude" name="longitude" class="form-control" step="0.000001" placeholder="e.g., -0.1870">
                                </div>
                                
                                <div class="form-group">
                                    <label for="location_description" class="form-label">Location Description</label>
                                    <input type="text" id="location_description" name="location_description" class="form-control" placeholder="Location description">
                                </div>
                            </div>
                            
                            <!-- Google Maps Location Picker -->
                            <div class="form-group full-width">
                                <label class="form-label">📍 Pick Location on Map</label>
                                <input type="text" id="location_search" class="form-control" placeholder="Search location... (e.g., Accra, Ghana)" style="margin-bottom: 10px;">
                                <div id="map-container" style="width: 100%; height: 400px; border: 1px solid #ddd; border-radius: 6px; margin-top: 10px;"></div>
                                <small class="form-text" style="margin-top: 8px; display: block;">
                                    💡 Click on the map or search to set location. Coordinates will be auto-filled.
                                </small>
                            </div>
                            
                            <!-- Client & Worker Information Row -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="client_name" class="form-label">Client Name *</label>
                                    <input type="text" id="client_name" name="client_name" class="form-control" 
                                           list="client-suggestions" required>
                                    <datalist id="client-suggestions"></datalist>
                                </div>
                                
                                <div class="form-group">
                                    <label for="client_contact_person" class="form-label">Contact Person</label>
                                    <input type="text" id="client_contact_person" name="client_contact_person" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="client_contact" class="form-label">Contact Number</label>
                                    <input type="text" id="client_contact" name="client_contact" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="client_email" class="form-label">Email</label>
                                    <input type="email" id="client_email" name="client_email" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="supervisor" class="form-label">Supervisor</label>
                                    <input type="text" id="supervisor" name="supervisor" class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="total_workers" class="form-label">Total Workers</label>
                                    <input type="number" id="total_workers" name="total_workers" class="form-control" min="0" value="0">
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label for="remarks" class="form-label">Remarks</label>
                                <textarea id="remarks" name="remarks" class="form-control" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 2: Drilling / Construction Information -->
                <div class="tab-content" id="drilling-tab">
                    <div class="dashboard-card">
                        <h2>Drilling / Construction Information</h2>
                        
                        <div class="form-grid form-grid-compact form-grid-justify">
                            <!-- Row 1: Time Information -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_time" class="form-label">Start Time</label>
                                    <input type="time" id="start_time" name="start_time" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="finish_time" class="form-label">Finish Time</label>
                                    <input type="time" id="finish_time" name="finish_time" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="total_duration" class="form-label">Total Duration</label>
                                    <input type="text" id="duration_display" class="form-control" readonly style="font-size: 1.1em; font-weight: 500;">
                                    <input type="hidden" id="total_duration" name="total_duration">
                                </div>
                            </div>
                            
                            <!-- Row 2: RPM Information -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="start_rpm" class="form-label">Start RPM</label>
                                    <input type="number" id="start_rpm" name="start_rpm" class="form-control" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label for="finish_rpm" class="form-label">Finish RPM</label>
                                    <input type="number" id="finish_rpm" name="finish_rpm" class="form-control" step="0.01" min="0">
                                </div>
                                <div class="form-group">
                                    <label for="total_rpm" class="form-label">Total RPM</label>
                                    <input type="number" id="total_rpm" name="total_rpm" class="form-control" step="0.01" readonly>
                                    <div id="rpmWarning" style="display: none;"></div>
                                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                                        💡 Typical RPM per job: 0.5-5.0. If values seem high, check for decimal point errors.
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Row 3: Drilling Information -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="rod_length" class="form-label">Rod Length (m)</label>
                                    <select id="rod_length" name="rod_length" class="form-control">
                                        <option value="">Select Length</option>
                                        <?php foreach ($rodLengths as $length): ?>
                                            <option value="<?php echo e($length); ?>"><?php echo e($length); ?>m</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="rods_used" class="form-label">Rods Used</label>
                                    <input type="number" id="rods_used" name="rods_used" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="total_depth" class="form-label">Total Depth (m)</label>
                                    <input type="number" id="total_depth" name="total_depth" class="form-control" step="0.1" readonly>
                                </div>
                            </div>
                            
                            <!-- Materials Provider -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="materials_provided_by" class="form-label">Materials Provided By</label>
                                    <select id="materials_provided_by" name="materials_provided_by" class="form-control">
                                        <option value="client">Client</option>
                                        <option value="company_shop">Company (Shop/POS)</option>
                                        <option value="company_store">Company (Store/Warehouse)</option>
                                    </select>
                                    <small class="form-text" style="display:block;margin-top:4px;color:var(--secondary);">
                                        <strong>Client:</strong> Materials provided by the client<br>
                                        <strong>Company (Shop/POS):</strong> Materials directly from the shop/POS<br>
                                        <strong>Company (Store/Warehouse):</strong> Materials from the Material Store (already moved from POS)
                                    </small>
                                </div>
                                <div class="form-group" id="materials_store_group" style="display: none;">
                                    <label for="materials_store_id" class="form-label">POS Store</label>
                                    <select id="materials_store_id" name="materials_store_id" class="form-control" onchange="fieldReportsManager.loadStoreStock(this.value)">
                                        <option value="">Select Store</option>
                                        <?php foreach ($posStores as $store): ?>
                                            <option value="<?php echo (int) $store['id']; ?>" data-store-name="<?php echo e($store['store_name']); ?>">
                                                <?php echo e($store['store_name']); ?> (<?php echo e($store['store_code']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text" style="display:block;margin-top:4px;color:var(--secondary);">
                                        Selecting a POS store will show available stock and update levels automatically when this report is submitted.
                                    </small>
                                    <div id="store_stock_info" style="margin-top: 8px; padding: 8px; background: rgba(59, 130, 246, 0.1); border-radius: 6px; font-size: 12px; display: none;">
                                        <strong>Available Stock:</strong>
                                        <div id="store_stock_details" style="margin-top: 4px;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Row 4: Materials Received -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="screen_pipes_received" class="form-label">Screen Pipes Received</label>
                                    <input type="number" id="screen_pipes_received" name="screen_pipes_received" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="plain_pipes_received" class="form-label">Plain Pipes Received</label>
                                    <input type="number" id="plain_pipes_received" name="plain_pipes_received" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="gravel_received" class="form-label">Gravel Received</label>
                                    <input type="number" id="gravel_received" name="gravel_received" class="form-control" min="0" value="0">
                                </div>
                            </div>
                            
                            <!-- Row 5: Construction Information (Materials Used + Construction Depth) -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="screen_pipes_used" class="form-label">Screen Pipes Used</label>
                                    <input type="number" id="screen_pipes_used" name="screen_pipes_used" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="plain_pipes_used" class="form-label">Plain Pipes Used</label>
                                    <input type="number" id="plain_pipes_used" name="plain_pipes_used" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="gravel_used" class="form-label">Gravel Used</label>
                                    <input type="number" id="gravel_used" name="gravel_used" class="form-control" min="0" value="0">
                                </div>
                                <div class="form-group">
                                    <label for="construction_depth" class="form-label">Construction Depth (m)</label>
                                    <input type="number" id="construction_depth" name="construction_depth" class="form-control" step="0.1" readonly>
                                </div>
                            </div>
                            
                            <!-- Row 6: Remaining Materials and Value -->
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Screen Pipes Remaining</label>
                                    <input type="text" id="screen_pipes_remaining" class="form-control" readonly style="font-weight: 500;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Plain Pipes Remaining</label>
                                    <input type="text" id="plain_pipes_remaining" class="form-control" readonly style="font-weight: 500;">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Gravel Remaining</label>
                                    <input type="text" id="gravel_remaining" class="form-control" readonly style="font-weight: 500;">
                                </div>
                                <div class="form-group">
                                    <label for="materials_value" class="form-label">Materials Value (Assets) (<?php echo e(getCurrency()); ?>)</label>
                                    <input type="number" id="materials_value" name="materials_value" class="form-control" step="0.01" readonly style="font-weight: 500;">
                                </div>
                            </div>
                            
                            <!-- Row 7: Materials Cost Calculation Info -->
                            <div class="form-row" id="materials_cost_info_row" style="display: none;">
                                <div class="form-group full-width">
                                    <div style="padding: 12px; background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; border-radius: 6px; font-size: 13px;">
                                        <strong style="color: #3b82f6;">💡 Materials Cost Calculation:</strong>
                                        <div id="materials_cost_info" style="margin-top: 6px; color: var(--text);">
                                            <!-- Dynamically updated based on job_type and materials_provided_by -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Compliance -->
                            <div class="form-group full-width">
                                <label class="form-label"><strong>Compliance</strong></label>
                                <div style="margin-top: 10px;">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="checkbox" id="compliance_agreed" name="compliance_agreed" value="1" style="width: auto; margin: 0;">
                                        <span> Siting (Survey) was done and drilling terms agreed on</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Compliance Documents Row (shown only when agreed) -->
                            <div class="form-row" id="compliance_documents_row" style="display: none;">
                                <div class="form-group">
                                    <label for="survey_document" class="form-label">Survey Document</label>
                                    <input type="file" id="survey_document" name="survey_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                
                                <div class="form-group">
                                    <label for="contract_document" class="form-label">Contract Document</label>
                                    <input type="file" id="contract_document" name="contract_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab 3: Workers / Payroll -->
                <div class="tab-content" id="workers-tab">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2>Workers / Payroll</h2>
                            <button type="button" class="btn btn-primary" onclick="fieldReportsManager.addPayrollRow()">Add Worker</button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table" id="payrollTable">
                                <thead>
                                    <tr>
                                        <th>Worker</th>
                                        <th>Role</th>
                                        <th>Wage Type</th>
                                        <th>Units</th>
                                        <th>Rate</th>
                                        <th>Benefits</th>
                                        <th>Loan Reclaim</th>
                                        <th>Amount</th>
                                        <th>Paid Today</th>
                                        <th>Notes</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Rows will be added dynamically -->
                                </tbody>
                            </table>
                        </div>
                        <div style="margin-top: 20px;">
                            <strong>Total Wages: <span id="total_wages_display"><?php echo e(getCurrency()); ?> 0.00</span></strong>
                        </div>
                    </div>
                </div>

                <!-- Tab 4: Financial Information -->
                <div class="tab-content" id="financial-tab">
                    <div class="dashboard-card">
                        <h2>Financial Information</h2>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                            <!-- Left: MONEY INFLOW (+) -->
                            <div style="background: linear-gradient(135deg, rgba(14,165,233,0.05) 0%, rgba(14,165,233,0.02) 100%); border: 2px solid rgba(14,165,233,0.3); border-radius: 12px; padding: 24px; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="color: #0ea5e9; margin: 0; font-weight: 700; font-size: 16px; text-transform: uppercase;">MONEY INFLOW (+)</h3>
                                    <button type="button" onclick="openFinancialGuide('inflow')" style="background: rgba(14,165,233,0.1); border: 1px solid rgba(14,165,233,0.3); border-radius: 6px; padding: 6px 12px; color: #0ea5e9; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px;" title="Field Guide">
                                        <span>ℹ️</span> Guide
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px;">
                                    <div class="form-group">
                                        <label for="balance_bf" class="form-label">Balance B/F (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="balance_bf" name="balance_bf" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="contract_sum" class="form-label" id="contract_sum_label">Contract Sum (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="contract_sum" name="contract_sum" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="rig_fee_charged" class="form-label">Rig Fee Expected (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="rig_fee_charged" name="rig_fee_charged" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="rig_fee_collected" class="form-label">Rig Fee Collected (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="rig_fee_collected" name="rig_fee_collected" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cash_received" class="form-label">Cash from Office (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="cash_received" name="cash_received" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="materials_income" class="form-label">Materials Income (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="materials_income" name="materials_income" class="form-control" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #10b981; background: rgba(16,185,129,0.1);">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right: MONEY OUTFLOW (-) -->
                            <div style="background: linear-gradient(135deg, rgba(239,68,68,0.05) 0%, rgba(239,68,68,0.02) 100%); border: 2px solid rgba(239,68,68,0.3); border-radius: 12px; padding: 24px; position: relative;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                    <h3 style="color: #ef4444; margin: 0; font-weight: 700; font-size: 16px; text-transform: uppercase;">MONEY OUTFLOW (-)</h3>
                                    <button type="button" onclick="openFinancialGuide('outflow')" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); border-radius: 6px; padding: 6px 12px; color: #ef4444; cursor: pointer; font-size: 12px; font-weight: 600; display: flex; align-items: center; gap: 6px;" title="Field Guide">
                                        <span>ℹ️</span> Guide
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                                    <div class="form-group">
                                        <label for="materials_cost" class="form-label">Materials Cost (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="materials_cost" name="materials_cost" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #ef4444; background: rgba(239,68,68,0.1);">
                                    </div>
                                    

                                    <div class="form-group">
                                        <label for="momo_transfer" class="form-label">MoMo to Company (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="momo_transfer" name="momo_transfer" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #0ea5e9; background: rgba(14,165,233,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="cash_given" class="form-label">Cash to Company (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="cash_given" name="cash_given" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #0ea5e9; background: rgba(14,165,233,0.1);">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bank_deposit" class="form-label">Bank Deposit (<?php echo e(getCurrency()); ?>)</label>
                                        <input type="number" id="bank_deposit" name="bank_deposit" class="form-control financial-input" 
                                               step="0.01" min="0" value="0" style="border-left: 4px solid #0ea5e9; background: rgba(14,165,233,0.1);">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Daily Expenses Table -->
                        <div style="margin-top: 24px;">
                            <h3 style="margin-bottom: 16px;">Daily Expenses</h3>
                            <div class="table-responsive">
                                <table class="table" id="expensesTable">
                                    <thead>
                                        <tr>
                                            <th>Description</th>
                                            <th>Unit Cost</th>
                                            <th>Quantity</th>
                                            <th>Amount</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Rows will be added dynamically -->
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline" onclick="fieldReportsManager.addExpenseRow()" style="margin-top: 12px;">Add Expense</button>
                        </div>
                        
                        <!-- Financial Summary removed (moved to Finance module) -->
                    </div>
                </div>

                <!-- Financial Field Guide Modal -->
                <div id="financialGuideModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
                    <div style="max-width: 700px; margin: 30px auto; background: var(--card); border-radius: 12px; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid var(--border); background: var(--bg); border-radius: 12px 12px 0 0;">
                            <h2 style="margin: 0; color: var(--text);">📚 Financial Fields Guide</h2>
                            <button onclick="closeFinancialGuide()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--secondary); padding: 0; width: 32px; height: 32px; line-height: 1;">&times;</button>
                        </div>
                        <div id="guideContent" style="padding: 30px; max-height: calc(100vh - 150px); overflow-y: auto;">
                            <!-- Content will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Tab 5: Incident / Case Log -->
                <div class="tab-content" id="incidents-tab">
                    <div class="dashboard-card">
                        <h2>Incident / Case Log</h2>
                        
                        <div class="form-grid form-grid-compact" style="grid-template-columns: repeat(3, 1fr);">
                            <div class="form-group">
                                <label for="incident_log" class="form-label">Incident Log</label>
                                <textarea id="incident_log" name="incident_log" class="form-control" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="solution_log" class="form-label">Solution Log</label>
                                <textarea id="solution_log" name="solution_log" class="form-control" rows="4"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="recommendation_log" class="form-label">Recommendation Log</label>
                                <textarea id="recommendation_log" name="recommendation_log" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons (Persistent) -->
                <div class="form-actions" style="position: sticky; bottom: 0; background: var(--card); padding: 20px; border-top: 1px solid var(--border); margin-top: 30px;">
                    <button type="submit" class="btn btn-primary btn-lg">Save Report</button>
                    <button type="button" class="btn btn-outline" onclick="window.location.href='field-reports-list.php'">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="generateReceipt()" style="display: none;" id="generateReceiptBtn">Generate Receipt</button>
                    <button type="button" class="btn btn-success" onclick="generateReport()" style="display: none;" id="generateReportBtn">Generate Technical Report</button>
                </div>
            </form>

<?php
// Add Google Maps API
$pdo = getDBConnection();
$providerRow = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'map_provider'")->fetch();
$apiKeyRow = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'map_api_key'")->fetch();
$map_provider = $providerRow['config_value'] ?? 'google';
$map_api_key = $apiKeyRow['config_value'] ?? '';

$additional_js = [];
echo '<script>window.ABBIS_MAP_PROVIDER='. json_encode($map_provider) .';</script>';
if ($map_provider === 'google') {
    if (!empty($map_api_key)) {
        $additional_js[] = 'https://maps.googleapis.com/maps/api/js?key=' . urlencode($map_api_key) . '&libraries=places';
        // Plus Code library removed - no longer needed
        $additional_js[] = '../assets/js/location-picker.js';
    } else {
        echo '<script>console.warn("Google Maps API key not configured. Set map_api_key in System → APIs & Integrations → Map Providers.");</script>';
        // Fallback to Leaflet
        $additional_js[] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
        $additional_js[] = 'https://cdnjs.cloudflare.com/ajax/libs/open-location-code/1.0.4/openlocationcode.min.js';
        $additional_js[] = '../assets/js/location-picker.js';
    }
} else {
    // Leaflet provider
    $additional_js[] = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    echo '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />';
    $additional_js[] = 'https://cdnjs.cloudflare.com/ajax/libs/open-location-code/1.0.4/openlocationcode.min.js';
    $additional_js[] = '../assets/js/location-picker.js';
}
$additional_js[] = '../assets/js/field-reports.js?v=' . time();
?>

<script>
// Financial Field Guide Modal Functions
const financialGuideData = {
    inflow: {
        title: '💰 Income (+) positives',
        fields: [
            {
                name: 'Balance B/F',
                meaning: 'company money that is still at hand from previous day(s)'
            },
            {
                name: 'Full contract Sum',
                meaning: 'money charged and or collected fo job that is a direct borehole'
            },
            {
                name: 'Rig Fee charged',
                meaning: 'money expected from each drilling especially when it\'s a sucontract. but when the job is a direct contract, the rig fee will be deducted from the full contract sum. Note: Full contract sum IS ONLY used when the job is a direct contract'
            },
            {
                name: 'Rig Fee collected (from client)',
                meaning: 'the actual amount of money collected from the client and this is income'
            },
            {
                name: 'Cash from Office',
                meaning: 'Cash received from company/office for business operations. This is NOT cash received from client'
            },
            {
                name: 'Material Sold',
                meaning: 'money gotten from selling company materials'
            }
        ]
    },
    outflow: {
        title: '💸 Expenses (-) negatives',
        fields: [
            {
                name: 'Materials Purchased',
                meaning: 'money spent on buying materials to make a borehole. this is usually ONLY the case when the job is a direct contract'
            },
            {
                name: 'Wages',
                meaning: 'Salaries or wages paid to workers'
            },
            {
                name: 'Loans',
                meaning: 'monies borrowed by workers (ties into the loan managment in the moals)'
            },
            {
                name: 'Daily Expenses',
                meaning: 'The total monies spent on business operations for that day\'s record'
            }
        ]
    },
    deposits: {
        title: '🏦 Deposits / Savings',
        fields: [
            {
                name: 'Money sent to company Momo',
                meaning: 'Money sent to company Momo'
            },
            {
                name: 'Money given in cash to company',
                meaning: 'Money given in cash to company'
            },
            {
                name: 'Money deposited to company bank account',
                meaning: 'Money deposited to company bank account'
            },
            {
                name: 'Total Money Banked',
                meaning: 'These 3 are all together = the money banked'
            }
        ]
    },
    summary: {
        title: '📊 Financial Summary session',
        fields: [
            {
                name: 'Total Income',
                meaning: 'the sum of all the Positives (+)'
            },
            {
                name: 'Total Expense',
                meaning: 'the sum of all the negatives (-)'
            },
            {
                name: 'Total Wages',
                meaning: 'the sum of all the wages ONLY'
            },
            {
                name: 'Total Banked',
                meaning: 'the sum of all the monies deposited in different forms (Momo + cash given + bank deposits)'
            },
            {
                name: 'Day\'s Balance',
                meaning: 'the money remaining at hand after all the expenses and deposits made'
            },
            {
                name: 'Net profit',
                meaning: 'All income - All expenses (expenese here does not inlcude bank deposits)'
            },
            {
                name: 'Outstanding Rig fee',
                meaning: 'Rig fee charged - Actual Rig fee collected'
            },
            {
                name: 'Loans Outstanding',
                meaning: 'the amount of money that workers has borrowed from the company and have not paid yey'
            },
            {
                name: 'Total money in debt',
                meaning: '(Outstanding Rig fee + Loans Outstanding)'
            }
        ]
    }
};

window.openFinancialGuide = function(type) {
    const modal = document.getElementById('financialGuideModal');
    const content = document.getElementById('guideContent');
    const data = financialGuideData[type];
    
    if (!data || !modal || !content) return;
    
    const sectionColors = {
        'inflow': '#10b981',
        'outflow': '#ef4444',
        'deposits': '#0ea5e9',
        'summary': '#f59e0b'
    };
    
    const borderColor = sectionColors[type] || '#64748b';
    
    let html = `<h3 style="color: var(--text); margin: 0 0 20px 0; font-size: 18px;">${data.title}</h3>`;
    
    data.fields.forEach((field, index) => {
        html += `
            <div style="margin-bottom: 24px; padding: 16px; background: var(--bg); border-radius: 8px; border-left: 4px solid ${borderColor};">
                <div style="font-weight: 700; color: var(--text); margin-bottom: 8px; font-size: 15px;">
                    ${index + 1}. ${field.name}
                </div>
                <div style="color: var(--secondary); font-size: 14px; line-height: 1.6;">
                    ${field.meaning}
                </div>
            </div>
        `;
    });
    
    content.innerHTML = html;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
};

window.closeFinancialGuide = function() {
    const modal = document.getElementById('financialGuideModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
};

// Close modal when clicking outside
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('financialGuideModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeFinancialGuide();
            }
        });
    }
});

// Close modal on Escape key (global listener)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('financialGuideModal');
        if (modal && modal.style.display === 'block') {
            closeFinancialGuide();
        }
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>

