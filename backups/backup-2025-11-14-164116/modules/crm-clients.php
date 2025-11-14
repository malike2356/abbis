<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

/**
 * CRM Clients Management
 */
$pdo = getDBConnection();

// Handle actions
$message = null;
$messageType = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid security token';
        $messageType = 'error';
    } else {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'add_client':
                    $clientName = sanitizeInput($_POST['client_name'] ?? '');
                    $contactPerson = sanitizeInput($_POST['contact_person'] ?? '');
                    $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
                    $email = sanitizeInput($_POST['email'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $companyType = sanitizeInput($_POST['company_type'] ?? '');
                    $website = sanitizeInput($_POST['website'] ?? '');
                    $industry = sanitizeInput($_POST['industry'] ?? '');
                    $status = $_POST['status'] ?? 'lead';
                    $source = sanitizeInput($_POST['source'] ?? '');
                    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO clients (
                            client_name, contact_person, contact_number, email, address,
                            company_type, website, industry, status, source, assigned_to
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $clientName, $contactPerson, $contactNumber, $email, $address,
                        $companyType, $website, $industry, $status, $source, $assignedTo
                    ]);
                    
                    // Record activity
                    $clientId = $pdo->lastInsertId();
                    $stmt = $pdo->prepare("
                        INSERT INTO client_activities (client_id, type, title, description, created_by)
                        VALUES (?, 'update', 'Client Created', 'New client added to CRM', ?)
                    ");
                    $stmt->execute([$clientId, $_SESSION['user_id']]);
                    
                    $message = 'Client added successfully';
                    $messageType = 'success';
                    break;
                    
                case 'update_client':
                    $clientId = intval($_POST['client_id']);
                    $clientName = sanitizeInput($_POST['client_name'] ?? '');
                    $contactPerson = sanitizeInput($_POST['contact_person'] ?? '');
                    $contactNumber = sanitizeInput($_POST['contact_number'] ?? '');
                    $email = sanitizeInput($_POST['email'] ?? '');
                    $address = sanitizeInput($_POST['address'] ?? '');
                    $companyType = sanitizeInput($_POST['company_type'] ?? '');
                    $website = sanitizeInput($_POST['website'] ?? '');
                    $industry = sanitizeInput($_POST['industry'] ?? '');
                    $status = $_POST['status'] ?? 'lead';
                    $source = sanitizeInput($_POST['source'] ?? '');
                    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
                    $notes = sanitizeInput($_POST['notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        UPDATE clients SET
                        client_name = ?, contact_person = ?, contact_number = ?, email = ?,
                        address = ?, company_type = ?, website = ?, industry = ?,
                        status = ?, source = ?, assigned_to = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $clientName, $contactPerson, $contactNumber, $email,
                        $address, $companyType, $website, $industry,
                        $status, $source, $assignedTo, $notes, $clientId
                    ]);
                    
                    // Record activity
                    $stmt = $pdo->prepare("
                        INSERT INTO client_activities (client_id, type, title, description, created_by)
                        VALUES (?, 'update', 'Client Updated', 'Client information was updated', ?)
                    ");
                    $stmt->execute([$clientId, $_SESSION['user_id']]);
                    
                    $message = 'Client updated successfully';
                    $messageType = 'success';
                    break;
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get filters
$search = sanitizeInput($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$assignedFilter = intval($_GET['assigned'] ?? 0);
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(client_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($statusFilter)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
}

if ($assignedFilter > 0) {
    $where[] = "assigned_to = ?";
    $params[] = $assignedFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get clients
try {
    $sql = "
        SELECT c.*, u.full_name as assigned_name,
               (SELECT COUNT(*) FROM field_reports WHERE client_id = c.id) as total_jobs,
               (SELECT SUM(total_income) FROM field_reports WHERE client_id = c.id) as total_revenue
        FROM clients c
        LEFT JOIN users u ON c.assigned_to = u.id
        $whereClause
        ORDER BY c.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($sql);
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
    
    // Get total count
    $countSql = "SELECT COUNT(*) FROM clients c $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countParams = array_slice($params, 0, -2); // Remove limit/offset
    $countStmt->execute($countParams);
    $totalClients = $countStmt->fetchColumn();
    $totalPages = ceil($totalClients / $perPage);
} catch (PDOException $e) {
    $clients = [];
    $totalClients = 0;
    $totalPages = 1;
}

// Get users for assignment
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0;">👥 Clients</h2>
    <button onclick="showClientModal()" class="btn btn-primary">➕ Add New Client</button>
</div>

<!-- Filters -->
<div class="dashboard-card" style="margin-bottom: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
        <input type="hidden" name="action" value="clients">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control" placeholder="Name, email, phone..." value="<?php echo e($search); ?>">
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="lead" <?php echo $statusFilter === 'lead' ? 'selected' : ''; ?>>Lead</option>
                <option value="prospect" <?php echo $statusFilter === 'prospect' ? 'selected' : ''; ?>>Prospect</option>
                <option value="customer" <?php echo $statusFilter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div>
            <label class="form-label">Assigned To</label>
            <select name="assigned" class="form-control">
                <option value="">All</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $assignedFilter === $user['id'] ? 'selected' : ''; ?>>
                        <?php echo e($user['full_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="?action=clients" class="btn btn-outline">Clear</a>
        </div>
    </form>
</div>

<!-- Clients Table -->
<div class="dashboard-card">
    <?php if (empty($clients)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No clients found. <a href="#" onclick="showClientModal(); return false;">Add your first client</a>
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Client Name</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Jobs</th>
                        <th>Revenue</th>
                        <th>Assigned To</th>
                        <th>Last Contact</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($client['client_name']); ?></strong>
                            <?php if ($client['company_type']): ?>
                                <br><small style="color: var(--secondary);"><?php echo e($client['company_type']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($client['contact_person']): ?>
                                <div><?php echo e($client['contact_person']); ?></div>
                            <?php endif; ?>
                            <?php if ($client['email']): ?>
                                <div><small><?php echo e($client['email']); ?></small></div>
                            <?php endif; ?>
                            <?php if ($client['contact_number']): ?>
                                <div><small><?php echo e($client['contact_number']); ?></small></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'lead' => '#f59e0b',
                                'prospect' => '#3b82f6',
                                'customer' => '#10b981',
                                'active' => '#10b981',
                                'inactive' => '#64748b'
                            ];
                            $color = $statusColors[$client['status']] ?? '#64748b';
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo ucfirst($client['status']); ?>
                            </span>
                        </td>
                        <td><?php echo number_format($client['total_jobs'] ?? 0); ?></td>
                        <td><?php echo formatCurrency($client['total_revenue'] ?? 0); ?></td>
                        <td><?php echo e($client['assigned_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <?php
                            if ($client['last_contact_date']):
                                echo date('M j, Y', strtotime($client['last_contact_date']));
                            else:
                                echo '<span style="color: var(--secondary);">Never</span>';
                            endif;
                            ?>
                        </td>
                        <td>
                            <a href="?action=client-detail&client_id=<?php echo $client['id']; ?>" class="btn btn-sm btn-primary">View</a>
                            <button onclick="editClient(<?php echo htmlspecialchars(json_encode($client)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div style="margin-top: 20px; display: flex; justify-content: center; gap: 10px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?action=clients&page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>&assigned=<?php echo $assignedFilter; ?>" 
                   class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-outline'; ?>">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add/Edit Client Modal -->
<div id="clientModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: var(--card); color: var(--text); padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border);">
        <h2 id="modalTitle">Add New Client</h2>
        <form method="POST" id="clientForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" id="formAction" value="add_client">
            <input type="hidden" name="client_id" id="clientId">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 20px;">
                <div class="form-group">
                    <label class="form-label">Client Name *</label>
                    <input type="text" name="client_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Company Type</label>
                    <input type="text" name="company_type" class="form-control" placeholder="e.g., Corporation, Individual">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact Person</label>
                    <input type="text" name="contact_person" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Contact Number</label>
                    <input type="tel" name="contact_number" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Website</label>
                    <input type="url" name="website" class="form-control" placeholder="https://">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Industry</label>
                    <input type="text" name="industry" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="lead">Lead</option>
                        <option value="prospect">Prospect</option>
                        <option value="customer">Customer</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Source</label>
                    <input type="text" name="source" class="form-control" placeholder="How did they find us?">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">Unassigned</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo e($user['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeClientModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Client</button>
            </div>
        </form>
    </div>
</div>

<script>
function showClientModal(clientData = null) {
    const modal = document.getElementById('clientModal');
    const form = document.getElementById('clientForm');
    const title = document.getElementById('modalTitle');
    
    if (clientData) {
        title.textContent = 'Edit Client';
        document.getElementById('formAction').value = 'update_client';
        document.getElementById('clientId').value = clientData.id;
        
        // Fill form
        Object.keys(clientData).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                input.value = clientData[key] || '';
            }
        });
    } else {
        title.textContent = 'Add New Client';
        document.getElementById('formAction').value = 'add_client';
        document.getElementById('clientId').value = '';
        form.reset();
    }
    
    modal.style.display = 'flex';
}

function editClient(clientData) {
    showClientModal(clientData);
}

function closeClientModal() {
    document.getElementById('clientModal').style.display = 'none';
}

// Close modal on outside click
document.getElementById('clientModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeClientModal();
    }
});
</script>

