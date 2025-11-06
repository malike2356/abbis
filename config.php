<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$auth->requireAuth();
$auth->requireRole('admin');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'config_') === 0) {
            $configKey = str_replace('config_', '', $key);
            $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$configKey, $value, $value]);
        }
    }
    
    $_SESSION['success'] = "Configuration updated successfully!";
    header('Location: config.php');
    exit;
}

// Get current configuration
$pdo = getDBConnection();
$configStmt = $pdo->query("SELECT config_key, config_value FROM system_config");
$config = [];
while ($row = $configStmt->fetch()) {
    $config[$row['config_key']] = $row['config_value'];
}

// Get rigs, workers, clients for management
$rigs = $pdo->query("SELECT * FROM rigs ORDER BY rig_name")->fetchAll();
$workers = $pdo->query("SELECT * FROM workers ORDER BY worker_name")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY client_name")->fetchAll();
$materials = $pdo->query("SELECT * FROM materials_inventory")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Configuration - ABBIS</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="container-fluid">
        <?php include 'includes/header.php'; ?>

        <main class="main-content">
            <div class="page-header">
                <h1>System Configuration</h1>
                <p>Manage system settings, rigs, workers, clients, and materials</p>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <div class="config-tabs">
                <div class="tabs">
                    <button type="button" class="tab active" data-tab="company">Company Info</button>
                    <button type="button" class="tab" data-tab="rigs">Rigs Management</button>
                    <button type="button" class="tab" data-tab="workers">Workers Management</button>
                    <button type="button" class="tab" data-tab="clients">Clients Management</button>
                    <button type="button" class="tab" data-tab="materials">Materials Pricing</button>
                    <button type="button" class="tab" data-tab="system">System Settings</button>
                </div>

                <form method="POST" class="config-form">
                    <!-- Company Info Tab -->
                    <div class="tab-pane active" id="company-tab">
                        <div class="dashboard-card">
                            <h2>Company Information</h2>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="config_company_name" class="form-label">Company Name</label>
                                    <input type="text" id="config_company_name" name="config_company_name" class="form-control" value="<?php echo $config['company_name'] ?? 'ABBIS - Advanced Borehole Business Intelligence System'; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_tagline" class="form-label">Tagline</label>
                                    <input type="text" id="config_company_tagline" name="config_company_tagline" class="form-control" value="<?php echo $config['company_tagline'] ?? 'Automating Field Intelligence & Analytics'; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_address" class="form-label">Address</label>
                                    <input type="text" id="config_company_address" name="config_company_address" class="form-control" value="<?php echo $config['company_address'] ?? '123 Engineering Lane, Accra, Ghana'; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_contact" class="form-label">Contact Number</label>
                                    <input type="text" id="config_company_contact" name="config_company_contact" class="form-control" value="<?php echo $config['company_contact'] ?? '+233 555 123 456'; ?>">
                                </div>
                                <div class="form-group">
                                    <label for="config_company_email" class="form-label">Email Address</label>
                                    <input type="email" id="config_company_email" name="config_company_email" class="form-control" value="<?php echo $config['company_email'] ?? 'info@abbis.africa'; ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rigs Management Tab -->
                    <div class="tab-pane" id="rigs-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2>Rigs Management</h2>
                                <button type="button" class="btn btn-primary" onclick="showAddRigModal()">Add New Rig</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Rig Code</th>
                                            <th>Rig Name</th>
                                            <th>Truck Model</th>
                                            <th>Registration</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($rigs as $rig): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rig['rig_code']); ?></td>
                                            <td><?php echo htmlspecialchars($rig['rig_name']); ?></td>
                                            <td><?php echo htmlspecialchars($rig['truck_model']); ?></td>
                                            <td><?php echo htmlspecialchars($rig['registration_number']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $rig['status']; ?>">
                                                    <?php echo ucfirst($rig['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline" onclick="editRig(<?php echo $rig['id']; ?>)">Edit</button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteRig(<?php echo $rig['id']; ?>)">Delete</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Workers Management Tab -->
                    <div class="tab-pane" id="workers-tab">
                        <div class="dashboard-card">
                            <div class="card-header">
                                <h2>Workers Management</h2>
                                <button type="button" class="btn btn-primary" onclick="showAddWorkerModal()">Add New Worker</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Role</th>
                                            <th>Default Rate (GHS)</th>
                                            <th>Contact</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($workers as $worker): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($worker['worker_name']); ?></td>
                                            <td><?php echo htmlspecialchars($worker['role']); ?></td>
                                            <td>GHS <?php echo number_format($worker['default_rate'], 2); ?></td>
                                            <td><?php echo htmlspecialchars($worker['contact_number']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $worker['status']; ?>">
                                                    <?php echo ucfirst($worker['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline" onclick="editWorker(<?php echo $worker['id']; ?>)">Edit</button>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteWorker(<?php echo $worker['id']; ?>)">Delete</button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Materials Pricing Tab -->
                    <div class="tab-pane" id="materials-tab">
                        <div class="dashboard-card">
                            <h2>Materials Pricing</h2>
                            <div class="form-grid">
                                <?php foreach ($materials as $material): ?>
                                <div class="form-group">
                                    <label for="config_<?php echo $material['material_type']; ?>_cost" class="form-label">
                                        <?php echo ucfirst(str_replace('_', ' ', $material['material_type'])); ?> Cost (GHS)
                                    </label>
                                    <input type="number" step="0.01" id="config_<?php echo $material['material_type']; ?>_cost" 
                                           name="config_<?php echo $material['material_type']; ?>_cost" 
                                           class="form-control" 
                                           value="<?php echo $material['unit_cost']; ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Configuration</button>
                        <button type="button" class="btn btn-outline" onclick="window.history.back()">Cancel</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Tab functionality
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and panes
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
                
                // Show corresponding pane
                const tabId = this.getAttribute('data-tab');
                document.getElementById(tabId + '-tab').classList.add('active');
            });
        });

        function showAddRigModal() {
            alert('Add rig functionality would open a modal here');
            // Implementation for adding rigs
        }

        function editRig(id) {
            alert('Edit rig ' + id + ' functionality would open here');
            // Implementation for editing rigs
        }

        function deleteRig(id) {
            if (confirm('Are you sure you want to delete this rig?')) {
                // AJAX call to delete rig
                fetch('api/delete-rig.php?id=' + id)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting rig: ' + data.message);
                        }
                    });
            }
        }
    </script>
</body>
</html>