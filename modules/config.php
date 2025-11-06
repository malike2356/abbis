<?php
/**
 * System Configuration - Full CRUD Interface
 */
$page_title = 'System Configuration';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/config-manager.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();

// Get current configuration
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

// Get rigs, clients, materials
$rigs = $configManager->getRigs();
$clients = $pdo->query("SELECT * FROM clients ORDER BY client_name")->fetchAll();
$materials = $configManager->getMaterials();
$rodLengths = $configManager->getRodLengths();

require_once '../includes/header.php';
?>

            <style>
                /* Critical CSS - Must be first to prevent FOUC (Flash of Unstyled Content) */
                .config-tabs .tab-pane {
                    display: none !important;
                }
                .config-tabs .tab-pane.active {
                    display: block !important;
                }
                
                /* Ensure tabs are properly styled from the start */
                .config-tabs .tabs .tab {
                    all: unset;
                    box-sizing: border-box;
                    display: flex !important;
                    align-items: center;
                    gap: 8px;
                    border: 2px solid transparent !important;
                    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%) !important;
                    color: #475569 !important;
                    padding: 14px 24px !important;
                    border-radius: 12px !important;
                    cursor: pointer;
                    font-weight: 700 !important;
                    font-size: 14px !important;
                    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
                    white-space: nowrap;
                    position: relative;
                    overflow: hidden;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
                    margin: 0 !important;
                    outline: none !important;
                    text-decoration: none !important;
                    font-family: inherit !important;
                    appearance: none !important;
                    -webkit-appearance: none !important;
                }
            </style>

            <div class="page-header">
                <div>
                    <h1>System Configuration</h1>
                    <p>Manage system settings, rigs, materials, and rod lengths</p>
                </div>
            </div>

            <!-- Configuration Tabs -->
            <div class="config-tabs">
                <div class="tabs">
                    <button type="button" class="tab active" onclick="switchConfigTab('company')">
                        <span>🏢</span> Company Info
                    </button>
                    <button type="button" class="tab" onclick="switchConfigTab('rigs')">
                        <span>🚛</span> Rigs Management
                    </button>
                    <button type="button" class="tab" onclick="switchConfigTab('materials')">
                        <span>📦</span> Materials & Pricing
                    </button>
                    <button type="button" class="tab" onclick="switchConfigTab('rod-lengths')">
                        <span>📏</span> Rod Lengths
                    </button>
                </div>

                <!-- Company Info Tab -->
                <div class="tab-pane active" id="company-tab" style="display: block;">
                    <?php
                    // Messages are now shown via JavaScript alerts from AJAX responses
                    ?>
                    
                    <!-- Simple Logo Upload Form -->
                    <div class="dashboard-card" style="margin-bottom: 20px;">
                        <h2>Company Logo</h2>
                        <form method="POST" action="../api/upload-logo.php" enctype="multipart/form-data" id="logoUploadForm" class="ajax-form">
                            <?php echo CSRF::getTokenField(); ?>
                            <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                                <div style="flex: 1; min-width: 250px;">
                                    <label for="company_logo" class="form-label">Upload Logo</label>
                                    <input type="file" id="company_logo" name="company_logo" 
                                           accept="image/png,image/jpeg,image/jpg,image/gif,image/svg+xml" 
                                           class="form-control" 
                                           required
                                           onchange="previewLogo(this); document.getElementById('logoUploadBtn').style.display='block';">
                                    <small class="form-text">PNG, JPG, GIF, or SVG - Max 2MB. Logo appears in header, receipts, and reports.</small>
                                    <button type="submit" id="logoUploadBtn" class="btn btn-primary" style="margin-top: 10px; display: none;">
                                        📤 Upload Logo
                                    </button>
                                </div>
                                <div style="min-width: 150px;">
                                    <?php 
                                    $logoPath = $config['company_logo'] ?? '';
                                    $logoUrl = '';
                                    $logoExists = false;
                                    if ($logoPath) {
                                        // Check if it's a full URL or relative path
                                        if (strpos($logoPath, 'http') === 0) {
                                            $logoUrl = $logoPath;
                                            $logoExists = true;
                                        } else {
                                            // Check if path starts with uploads/ or if we need to prepend it
                                            $checkPath = strpos($logoPath, 'uploads/') === 0 ? $logoPath : 'uploads/logos/' . basename($logoPath);
                                            $fullPath = __DIR__ . '/../' . $checkPath;
                                            if (file_exists($fullPath)) {
                                                $logoUrl = '../' . $checkPath;
                                                $logoExists = true;
                                            }
                                        }
                                    }
                                    ?>
                                    <label class="form-label">Current Logo Preview</label>
                                    <div id="logo-preview" style="width: 150px; height: 150px; border: 2px dashed var(--border); border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--bg); overflow: hidden;">
                                        <?php if ($logoExists && $logoUrl): ?>
                                            <img id="current-logo-img" src="<?php echo e($logoUrl); ?>?t=<?php echo time(); ?>" alt="Current Company Logo" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                        <?php else: ?>
                                            <div id="current-logo-placeholder" style="text-align: center; color: var(--secondary); padding: 20px;">
                                                <div style="font-size: 48px; margin-bottom: 10px;">🖼️</div>
                                                <div style="font-size: 12px;">No logo</div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Company Info Form -->
                    <form method="POST" action="../api/update-company-info.php" id="companyInfoForm">
                        <?php echo CSRF::getTokenField(); ?>
                        
                        <div class="dashboard-card">
                            <h2>Company Information</h2>
                            <div class="form-grid form-grid-compact">
                                <div class="form-group">
                                    <label for="config_company_name" class="form-label">Company Name</label>
                                    <input type="text" id="config_company_name" name="config_company_name" class="form-control" 
                                           value="<?php echo e($config['company_name'] ?? 'ABBIS - Advanced Borehole Business Intelligence System'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_tagline" class="form-label">Tagline</label>
                                    <input type="text" id="config_company_tagline" name="config_company_tagline" class="form-control" 
                                           value="<?php echo e($config['company_tagline'] ?? 'Advanced Borehole Business Intelligence System'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_address" class="form-label">Address</label>
                                    <input type="text" id="config_company_address" name="config_company_address" class="form-control" 
                                           value="<?php echo e($config['company_address'] ?? '123 Engineering Lane, Accra, Ghana'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_contact" class="form-label">Contact Number</label>
                                    <input type="text" id="config_company_contact" name="config_company_contact" class="form-control" 
                                           value="<?php echo e($config['company_contact'] ?? '+233 555 123 456'); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_email" class="form-label">Email Address</label>
                                    <input type="email" id="config_company_email" name="config_company_email" class="form-control" 
                                           value="<?php echo e($config['company_email'] ?? 'info@abbis.africa'); ?>">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Save Company Info</button>
                            </div>
                        </div>
                        
                        <script>
                        function previewLogo(input) {
                            if (input.files && input.files[0]) {
                                const file = input.files[0];
                                // Check file size
                                if (file.size > 2 * 1024 * 1024) {
                                    alert('File size exceeds 2MB limit');
                                    input.value = '';
                                    return;
                                }
                                const reader = new FileReader();
                                reader.onload = function(e) {
                                    const preview = document.getElementById('logo-preview');
                                    // Hide placeholder if exists
                                    const placeholder = document.getElementById('current-logo-placeholder');
                                    if (placeholder) {
                                        placeholder.style.display = 'none';
                                    }
                                    // Update or create image
                                    let img = document.getElementById('current-logo-img');
                                    if (!img) {
                                        img = document.createElement('img');
                                        img.id = 'current-logo-img';
                                        img.style.cssText = 'max-width: 100%; max-height: 100%; object-fit: contain;';
                                        preview.appendChild(img);
                                    }
                                    img.src = e.target.result;
                                    img.alt = 'Logo Preview';
                                };
                                reader.readAsDataURL(file);
                            }
                        }
                        </script>
                    </form>
                </div>

                <!-- Rigs Management Tab -->
                <div class="tab-pane" id="rigs-tab" style="display: none;">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2>Rigs Management</h2>
                            <button type="button" class="btn btn-primary" onclick="showRigModal()">Add New Rig</button>
                        </div>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Rig Code</th>
                                        <th>Rig Name</th>
                                        <th>Current RPM</th>
                                        <th>Maintenance Due At</th>
                                        <th>RPM Remaining</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rigs as $rig): 
                                        $currentRpm = floatval($rig['current_rpm'] ?? 0);
                                        $maintenanceDueAtRpm = $rig['maintenance_due_at_rpm'] ? floatval($rig['maintenance_due_at_rpm']) : null;
                                        $rpmRemaining = $maintenanceDueAtRpm ? max(0, $maintenanceDueAtRpm - $currentRpm) : null;
                                        $maintenanceStatus = $rig['maintenance_status'] ?? 'ok';
                                        
                                        // Determine status color
                                        $statusColor = 'success';
                                        if ($maintenanceStatus === 'due') $statusColor = 'danger';
                                        elseif ($maintenanceStatus === 'soon') $statusColor = 'warning';
                                    ?>
                                    <tr>
                                        <td><code><?php echo e($rig['rig_code']); ?></code></td>
                                        <td><?php echo e($rig['rig_name']); ?></td>
                                        <td style="font-weight: 600; color: var(--primary);">
                                            <?php echo number_format($currentRpm, 2); ?> RPM
                                        </td>
                                        <td>
                                            <?php if ($maintenanceDueAtRpm): ?>
                                                <span style="font-weight: 600;"><?php echo number_format($maintenanceDueAtRpm, 2); ?> RPM</span>
                                            <?php else: ?>
                                                <span style="color: var(--secondary);">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rpmRemaining !== null): ?>
                                                <span class="status-badge status-<?php echo $statusColor; ?>" style="font-weight: 600;">
                                                    <?php echo number_format($rpmRemaining, 2); ?> RPM
                                                </span>
                                                <?php if ($maintenanceStatus === 'due'): ?>
                                                    <br><small style="color: #dc3545;">⚠️ Maintenance Due!</small>
                                                <?php elseif ($maintenanceStatus === 'soon'): ?>
                                                    <br><small style="color: #ffc107;">⏰ Maintenance Soon</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color: var(--secondary);">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo e($rig['status']); ?>">
                                                <?php echo ucfirst($rig['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="editRig(<?php echo htmlspecialchars(json_encode($rig)); ?>)">Edit</button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteRig(<?php echo $rig['id']; ?>)">Delete</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Materials & Pricing Tab -->
                <div class="tab-pane" id="materials-tab" style="display: none;">
                    <div class="dashboard-card">
                        <h2>Materials Inventory & Pricing</h2>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Material Type</th>
                                        <th>Quantity Received</th>
                                        <th>Quantity Used</th>
                                        <th>Quantity Remaining</th>
                                        <th>Unit Cost (GHS)</th>
                                        <th>Total Value (GHS)</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($materials as $material): ?>
                                    <tr>
                                        <td><strong><?php echo ucfirst(str_replace('_', ' ', e($material['material_type']))); ?></strong></td>
                                        <td><?php echo number_format($material['quantity_received']); ?></td>
                                        <td><?php echo number_format($material['quantity_used']); ?></td>
                                        <td><?php echo number_format($material['quantity_remaining']); ?></td>
                                        <td><?php echo formatCurrency($material['unit_cost']); ?></td>
                                        <td><?php echo formatCurrency($material['total_value']); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" onclick="editMaterial('<?php echo e($material['material_type']); ?>', <?php echo htmlspecialchars(json_encode($material)); ?>)">Edit</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Rod Lengths Tab -->
                <div class="tab-pane" id="rod-lengths-tab" style="display: none;">
                    <div class="dashboard-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h2>Rod Lengths Management</h2>
                                <p style="margin: 4px 0 0 0; color: var(--secondary);">Manage available rod lengths in the system</p>
                            </div>
                            <button type="button" class="btn btn-primary" onclick="showRodLengthModal()">➕ Add Rod Length</button>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Length (meters)</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rodLengths)): ?>
                                        <tr>
                                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--secondary);">
                                                No rod lengths configured. Click "Add Rod Length" to create one.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php 
                                        // Sort rod lengths numerically
                                        usort($rodLengths, function($a, $b) {
                                            return floatval($a) <=> floatval($b);
                                        });
                                        foreach ($rodLengths as $length): 
                                        ?>
                                        <tr>
                                            <td><strong><?php echo e($length); ?>m</strong></td>
                                            <td>
                                                <span class="status-badge status-active">Active</span>
                                            </td>
                                            <td style="color: var(--secondary); font-size: 13px;">—</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" onclick="editRodLength('<?php echo e($length); ?>')">Edit</button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteRodLength('<?php echo e($length); ?>')">Delete</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Modals -->
            <!-- Rig Modal -->
            <div id="rigModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="rigModalTitle">Add New Rig</h2>
                        <button type="button" class="modal-close" onclick="closeRigModal()">&times;</button>
                    </div>
                    <form method="POST" action="../api/config-crud.php" class="ajax-form" id="rigForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" id="rigAction" value="add_rig">
                        <input type="hidden" name="id" id="rigId" value="">
                        
                        <div class="form-group">
                            <label for="rig_name" class="form-label">Rig Name *</label>
                            <input type="text" id="rig_name" name="rig_name" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="rig_code" class="form-label">Rig Code *</label>
                            <input type="text" id="rig_code" name="rig_code" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="truck_model" class="form-label">Truck Model</label>
                            <input type="text" id="truck_model" name="truck_model" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="registration_number" class="form-label">Registration Number</label>
                            <input type="text" id="registration_number" name="registration_number" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="rig_status" class="form-label">Status</label>
                            <select id="rig_status" name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        
                        <div style="border-top: 2px solid var(--border); padding-top: 16px; margin-top: 16px;">
                            <h3 style="margin-bottom: 16px; color: var(--text); font-size: 16px;">🔧 RPM Maintenance Settings</h3>
                            
                            <div class="form-group">
                                <label for="current_rpm" class="form-label">
                                    Current RPM
                                    <small style="display: block; font-weight: normal; color: var(--secondary); margin-top: 4px;">
                                        Cumulative RPM from all field reports. Auto-updated when reports are saved.
                                    </small>
                                </label>
                                <input type="number" id="current_rpm" name="current_rpm" class="form-control" 
                                       step="0.01" min="0" value="0.00">
                            </div>
                            
                            <div class="form-group">
                                <label for="maintenance_rpm_interval" class="form-label">
                                    Maintenance RPM Interval
                                    <small style="display: block; font-weight: normal; color: var(--secondary); margin-top: 4px;">
                                        Service interval (e.g., 30.00 means service every 30 RPM)
                                    </small>
                                </label>
                                <input type="number" id="maintenance_rpm_interval" name="maintenance_rpm_interval" 
                                       class="form-control" step="0.01" min="0" value="30.00" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="maintenance_due_at_rpm" class="form-label">
                                    Next Maintenance Due At (RPM)
                                    <small style="display: block; font-weight: normal; color: var(--secondary); margin-top: 4px;">
                                        RPM threshold when maintenance is due. Auto-calculated if not set.
                                    </small>
                                </label>
                                <input type="number" id="maintenance_due_at_rpm" name="maintenance_due_at_rpm" 
                                       class="form-control" step="0.01" min="0">
                                <small id="rpm_calculation_hint" style="display: none; color: var(--primary); margin-top: 4px; font-weight: 600;">
                                    Will be calculated as: Current RPM + Interval
                                </small>
                            </div>
                            
                            <div id="rpm_status_display" style="padding: 12px; background: #f8f9fa; border-radius: 6px; margin-top: 12px; display: none;">
                                <div style="font-weight: 600; margin-bottom: 8px; color: var(--text);">RPM Status:</div>
                                <div id="rpm_status_text" style="color: var(--secondary);"></div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" class="btn btn-outline" onclick="closeRigModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Material Modal -->
            <div id="materialModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="materialModalTitle">Update Material</h2>
                        <button type="button" class="modal-close" onclick="closeMaterialModal()">&times;</button>
                    </div>
                    <form method="POST" action="../api/config-crud.php" class="ajax-form" id="materialForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" value="update_material">
                        <input type="hidden" name="material_type" id="material_type" value="">
                        
                        <div class="form-group">
                            <label for="quantity_received" class="form-label">Quantity Received</label>
                            <input type="number" id="quantity_received" name="quantity_received" class="form-control" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="unit_cost" class="form-label">Unit Cost (GHS)</label>
                            <input type="number" id="unit_cost" name="unit_cost" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update</button>
                            <button type="button" class="btn btn-outline" onclick="closeMaterialModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rod Length Modal -->
            <div id="rodLengthModal" class="modal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2 id="rodLengthModalTitle">Add New Rod Length</h2>
                        <button type="button" class="modal-close" onclick="closeRodLengthModal()">&times;</button>
                    </div>
                    <form method="POST" action="../api/config-crud.php" class="ajax-form" id="rodLengthForm">
                        <?php echo CSRF::getTokenField(); ?>
                        <input type="hidden" name="action" id="rodLengthAction" value="add_rod_length">
                        <input type="hidden" name="old_length" id="rodLengthOld" value="">
                        
                        <div class="form-group">
                            <label for="rod_length" class="form-label">Length (meters) *</label>
                            <input type="number" id="rod_length" name="length" class="form-control" step="0.1" min="0.1" max="20" required placeholder="e.g., 3.5">
                            <small class="form-text">Enter the rod length in meters (e.g., 3.5 for 3.5 meters)</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save</button>
                            <button type="button" class="btn btn-outline" onclick="closeRodLengthModal()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>


            <script>
                // Ensure rod length functions are available immediately
                // These will be overridden by config.js if it loads, but available as fallback
                if (typeof showRodLengthModal === 'undefined') {
                    window.showRodLengthModal = function() {
                        const modal = document.getElementById('rodLengthModal');
                        if (modal) {
                            modal.style.display = 'flex';
                            const form = document.getElementById('rodLengthForm');
                            if (form) form.reset();
                            const actionInput = document.getElementById('rodLengthAction');
                            if (actionInput) actionInput.value = 'add_rod_length';
                            const oldLengthInput = document.getElementById('rodLengthOld');
                            if (oldLengthInput) oldLengthInput.value = '';
                            const lengthInput = document.getElementById('rod_length');
                            if (lengthInput) lengthInput.value = '';
                            const title = document.getElementById('rodLengthModalTitle');
                            if (title) title.textContent = 'Add New Rod Length';
                        }
                    };
                }
                if (typeof editRodLength === 'undefined') {
                    window.editRodLength = function(length) {
                        const modal = document.getElementById('rodLengthModal');
                        if (modal) {
                            modal.style.display = 'flex';
                            const actionInput = document.getElementById('rodLengthAction');
                            if (actionInput) actionInput.value = 'update_rod_length';
                            const oldLengthInput = document.getElementById('rodLengthOld');
                            if (oldLengthInput) oldLengthInput.value = length;
                            const lengthInput = document.getElementById('rod_length');
                            if (lengthInput) lengthInput.value = length;
                            const title = document.getElementById('rodLengthModalTitle');
                            if (title) title.textContent = 'Edit Rod Length';
                        }
                    };
                }
                if (typeof closeRodLengthModal === 'undefined') {
                    window.closeRodLengthModal = function() {
                        const modal = document.getElementById('rodLengthModal');
                        if (modal) modal.style.display = 'none';
                    };
                }
                if (typeof deleteRodLength === 'undefined') {
                    window.deleteRodLength = function(length) {
                        if (confirm('Are you sure you want to delete rod length ' + length + 'm? This action cannot be undone.')) {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '../api/config-crud.php';
                            const csrfToken = document.querySelector('[name="csrf_token"]');
                            if (csrfToken) {
                                const csrfInput = document.createElement('input');
                                csrfInput.type = 'hidden';
                                csrfInput.name = 'csrf_token';
                                csrfInput.value = csrfToken.value;
                                form.appendChild(csrfInput);
                            }
                            const actionInput = document.createElement('input');
                            actionInput.type = 'hidden';
                            actionInput.name = 'action';
                            actionInput.value = 'delete_rod_length';
                            form.appendChild(actionInput);
                            const lengthInput = document.createElement('input');
                            lengthInput.type = 'hidden';
                            lengthInput.name = 'length';
                            lengthInput.value = length;
                            form.appendChild(lengthInput);
                            document.body.appendChild(form);
                            form.submit();
                        }
                    };
                }
            </script>
            <script src="../assets/js/config.js"></script>
            <style>
                .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
                .modal-content { background-color: var(--card); margin: auto; padding: 0; border-radius: 8px; width: 90%; max-width: 600px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
                .modal-header { padding: 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
                .modal-header h2 { margin: 0; }
                .modal-close { background: none; border: none; font-size: 28px; cursor: pointer; color: var(--text); }
                .modal form { padding: 20px; }
                
                /* Force tab initialization on page load */
                .config-tabs .tab-pane:not(.active) {
                    display: none !important;
                }
                
                /* Spinner animation */
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
            </style>
            <script>
                // Initialize tabs immediately - multiple strategies for reliability
                (function() {
                    function initTabs() {
                        const panes = document.querySelectorAll('.config-tabs .tab-pane');
                        panes.forEach(pane => {
                            const isActive = pane.classList.contains('active');
                            if (!isActive) {
                                pane.style.display = 'none';
                                pane.classList.remove('active');
                            } else {
                                pane.style.display = 'block';
                            }
                        });
                        
                        // Ensure only one tab button is active
                        const tabs = document.querySelectorAll('.config-tabs .tabs .tab');
                        tabs.forEach((tab, index) => {
                            if (index === 0) { // First tab (Company Info) should be active
                                tab.classList.add('active');
                            } else {
                                tab.classList.remove('active');
                            }
                        });
                    }
                    
                    // Try immediate execution
                    if (document.body) {
                        initTabs();
                    }
                    
                    // Also run on DOMContentLoaded
                    document.addEventListener('DOMContentLoaded', initTabs);
                    
                    // And run after a tiny delay as fallback
                    setTimeout(initTabs, 10);
                    setTimeout(initTabs, 100);
                })();
                
                // Update modal close handler
                window.onclick = function(event) {
                    const modals = ['rigModal', 'materialModal', 'rodLengthModal', 'duplicateAnalysisModal'];
                    modals.forEach(modalId => {
                        const modal = document.getElementById(modalId);
                        if (event.target == modal) {
                            modal.style.display = 'none';
                        }
                    });
                }
            </script>

            <!-- Company Info Form Handler -->
            <script src="../assets/js/company-info.js"></script>

<?php require_once '../includes/footer.php'; ?>
