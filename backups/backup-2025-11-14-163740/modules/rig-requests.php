<?php
/**
 * Rig Requests Management Module
 * Track and manage rig rental requests from agents and contractors
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'] ?? null;

function rigRequestsMapSettings(PDO $pdo): array
{
    $provider = 'leaflet';
    $apiKey = '';

    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('map_provider', 'map_api_key')");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['config_key'] === 'map_provider' && !empty($row['config_value'])) {
                $provider = strtolower($row['config_value']) === 'google' ? 'google' : 'leaflet';
            }
            if ($row['config_key'] === 'map_api_key' && !empty($row['config_value'])) {
                $apiKey = $row['config_value'];
            }
        }
    } catch (PDOException $e) {
        // ignore and fallback to defaults
    }

    if ($provider === 'google' && empty($apiKey)) {
        try {
            $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'google_maps_api_key' LIMIT 1");
            $legacyKey = $stmt->fetchColumn();
            if ($legacyKey) {
                $apiKey = $legacyKey;
            } else {
                $provider = 'leaflet';
            }
        } catch (PDOException $e) {
            $provider = 'leaflet';
        }
    }

    if ($provider !== 'google' || empty($apiKey)) {
        $provider = 'leaflet';
        $apiKey = '';
    }

    return [
        'provider' => $provider,
        'api_key' => $apiKey,
    ];
}

$mapSettings = rigRequestsMapSettings($pdo);

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    // CSRF protection
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        die('Invalid security token');
    }
    $requestId = intval($_POST['request_id']);
    $status = $_POST['status'];
    $assignedRigId = !empty($_POST['assigned_rig_id']) ? intval($_POST['assigned_rig_id']) : null;
    $internalNotes = sanitizeInput($_POST['internal_notes'] ?? '');
    $locationAddress = sanitizeInput($_POST['location_address'] ?? '');
    $region = sanitizeInput($_POST['region'] ?? '');
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? floatval($_POST['latitude']) : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? floatval($_POST['longitude']) : null;
    $numberOfBoreholes = max(1, intval($_POST['number_of_boreholes'] ?? 1));
    $estimatedBudget = isset($_POST['estimated_budget']) && $_POST['estimated_budget'] !== '' ? floatval($_POST['estimated_budget']) : null;
    $preferredStartDate = !empty($_POST['preferred_start_date']) ? $_POST['preferred_start_date'] : null;
    if ($preferredStartDate && !DateTime::createFromFormat('Y-m-d', $preferredStartDate)) {
        $preferredStartDate = null;
    }
    $urgency = strtolower($_POST['urgency'] ?? 'medium');
    $allowedUrgencies = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($urgency, $allowedUrgencies, true)) {
        $urgency = 'medium';
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE rig_requests 
            SET status = ?, 
                assigned_rig_id = ?, 
                internal_notes = ?, 
                location_address = ?, 
                region = ?, 
                latitude = ?, 
                longitude = ?, 
                number_of_boreholes = ?, 
                estimated_budget = ?, 
                preferred_start_date = ?, 
                urgency = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $status,
            $assignedRigId,
            $internalNotes,
            $locationAddress,
            $region ?: null,
            $latitude,
            $longitude,
            $numberOfBoreholes,
            $estimatedBudget,
            $preferredStartDate,
            $urgency,
            $requestId
        ]);
        
        // If status is dispatched, set dispatched_at
        if ($status === 'dispatched') {
            $pdo->prepare("UPDATE rig_requests SET dispatched_at = NOW() WHERE id = ?")->execute([$requestId]);
        }
        
        // If status is completed, set completed_at
        if ($status === 'completed') {
            $pdo->prepare("UPDATE rig_requests SET completed_at = NOW() WHERE id = ?")->execute([$requestId]);
        }
        
        $msg = 'Status updated successfully';
        $msgType = 'success';
    } catch (PDOException $e) {
        $msg = 'Failed to update status: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_to'])) {
    $requestId = intval($_POST['request_id']);
    $assignedTo = intval($_POST['assigned_to']);
    
    try {
        $pdo->prepare("UPDATE rig_requests SET assigned_to = ? WHERE id = ?")->execute([$assignedTo, $requestId]);
        $msg = 'Request assigned successfully';
        $msgType = 'success';
    } catch (PDOException $e) {
        $msg = 'Failed to assign request';
        $msgType = 'danger';
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$urgencyFilter = $_GET['urgency'] ?? 'all';
$assignedFilter = intval($_GET['assigned'] ?? 0);

// Build query
$where = [];
$params = [];

if ($statusFilter !== 'all') {
    $where[] = "rr.status = ?";
    $params[] = $statusFilter;
}

if ($urgencyFilter !== 'all') {
    $where[] = "rr.urgency = ?";
    $params[] = $urgencyFilter;
}

if ($assignedFilter > 0) {
    $where[] = "rr.assigned_to = ?";
    $params[] = $assignedFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get rig requests
try {
    $sql = "
        SELECT 
            rr.*,
            r.rig_name, r.rig_code,
            c.client_name,
            u.full_name as assigned_name,
            creator.full_name as creator_name
        FROM rig_requests rr
        LEFT JOIN rigs r ON rr.assigned_rig_id = r.id
        LEFT JOIN clients c ON rr.client_id = c.id
        LEFT JOIN users u ON rr.assigned_to = u.id
        LEFT JOIN users creator ON rr.created_by = creator.id
        $whereClause
        ORDER BY 
            CASE rr.urgency
                WHEN 'urgent' THEN 1
                WHEN 'high' THEN 2
                WHEN 'medium' THEN 3
                WHEN 'low' THEN 4
            END,
            rr.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $requests = [];
    $msg = 'Error loading requests: ' . $e->getMessage();
    $msgType = 'danger';
}

// Get rigs for assignment
try {
    $rigs = $pdo->query("SELECT id, rig_name, rig_code FROM rigs WHERE status = 'active' ORDER BY rig_name")->fetchAll();
} catch (PDOException $e) {
    $rigs = [];
}

// Get users for assignment
try {
    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
} catch (PDOException $e) {
    $users = [];
}

// Get statistics
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM rig_requests")->fetchColumn(),
        'new' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status = 'new'")->fetchColumn(),
        'under_review' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status = 'under_review'")->fetchColumn(),
        'negotiating' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status = 'negotiating'")->fetchColumn(),
        'dispatched' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status = 'dispatched'")->fetchColumn(),
        'completed' => $pdo->query("SELECT COUNT(*) FROM rig_requests WHERE status = 'completed'")->fetchColumn(),
    ];
} catch (PDOException $e) {
    $stats = ['total' => 0, 'new' => 0, 'under_review' => 0, 'negotiating' => 0, 'dispatched' => 0, 'completed' => 0];
}

require_once '../includes/header.php';
?>

<style>
    .modal-map {
        width: 100%;
        height: 320px;
        border-radius: 10px;
        border: 1px solid var(--border);
        margin-top: 12px;
    }
    .modal-location-search {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }
    .modal-location-search input {
        flex: 1;
    }
    .modal-location-search button {
        min-width: 90px;
    }
    .modal-coordinate-grid {
        display: flex;
        gap: 12px;
        margin-top: 12px;
        flex-wrap: wrap;
    }
    .modal-coordinate-grid > div {
        flex: 1;
        min-width: 160px;
    }
    .modal-readonly {
        padding: 0.75rem;
        background: var(--surface);
        border-radius: 6px;
        color: var(--text);
    }
</style>

<div class="container-fluid">
    <nav aria-label="Breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <span>System</span> <span style="opacity:0.6;">→</span> <span>Rig Requests</span>
        </div>
    </nav>
    
    <div class="page-header">
        <h1>🚛 Rig Requests</h1>
        <p>Manage rig rental requests from agents and contractors</p>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: var(--primary);"><?php echo $stats['total']; ?></div>
            <div style="color: var(--secondary); font-size: 0.9rem;">Total Requests</div>
        </div>
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #ef4444;"><?php echo $stats['new']; ?></div>
            <div style="color: var(--secondary); font-size: 0.9rem;">New</div>
        </div>
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #f59e0b;"><?php echo $stats['negotiating']; ?></div>
            <div style="color: var(--secondary); font-size: 0.9rem;">Negotiating</div>
        </div>
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #3b82f6;"><?php echo $stats['dispatched']; ?></div>
            <div style="color: var(--secondary); font-size: 0.9rem;">Dispatched</div>
        </div>
        <div class="dashboard-card" style="text-align: center;">
            <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?php echo $stats['completed']; ?></div>
            <div style="color: var(--secondary); font-size: 0.9rem;">Completed</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="dashboard-card" style="margin-bottom: 24px;">
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="under_review" <?php echo $statusFilter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="negotiating" <?php echo $statusFilter === 'negotiating' ? 'selected' : ''; ?>>Negotiating</option>
                    <option value="dispatched" <?php echo $statusFilter === 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                    <option value="declined" <?php echo $statusFilter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                </select>
            </div>
            <div>
                <label class="form-label">Urgency</label>
                <select name="urgency" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?php echo $urgencyFilter === 'all' ? 'selected' : ''; ?>>All Urgencies</option>
                    <option value="urgent" <?php echo $urgencyFilter === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $urgencyFilter === 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $urgencyFilter === 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $urgencyFilter === 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            <div>
                <label class="form-label">Assigned To</label>
                <select name="assigned" class="form-control" onchange="this.form.submit()">
                    <option value="0" <?php echo $assignedFilter === 0 ? 'selected' : ''; ?>>All Users</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $assignedFilter === $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Requests Table -->
    <div class="dashboard-card">
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request #</th>
                        <th>Requester</th>
                        <th>Location</th>
                        <th>Boreholes</th>
                        <th>Budget</th>
                        <th>Urgency</th>
                        <th>Status</th>
                        <th>Assigned Rig</th>
                        <th>Assigned To</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; padding: 2rem; color: var(--secondary);">
                                No rig requests found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($req['request_number']); ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($req['requester_name']); ?></div>
                                    <small style="color: var(--secondary);"><?php echo htmlspecialchars($req['requester_email']); ?></small>
                                    <?php if ($req['company_name']): ?>
                                        <div><small style="color: var(--secondary);"><?php echo htmlspecialchars($req['company_name']); ?></small></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($req['location_address']); ?></div>
                                    <?php if ($req['region']): ?>
                                        <small style="color: var(--secondary);"><?php echo htmlspecialchars($req['region']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $req['number_of_boreholes']; ?></td>
                                <td>
                                    <?php if ($req['estimated_budget']): ?>
                                        GHS <?php echo number_format($req['estimated_budget'], 2); ?>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">Not specified</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $req['urgency'] === 'urgent' ? 'danger' : 
                                            ($req['urgency'] === 'high' ? 'warning' : 
                                            ($req['urgency'] === 'medium' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($req['urgency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $req['status'] === 'new' ? 'danger' : 
                                            ($req['status'] === 'completed' ? 'success' : 
                                            ($req['status'] === 'dispatched' ? 'info' : 
                                            ($req['status'] === 'declined' ? 'secondary' : 'warning'))); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $req['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($req['rig_name']): ?>
                                        <?php echo htmlspecialchars($req['rig_name']); ?>
                                        <small style="color: var(--secondary);">(<?php echo htmlspecialchars($req['rig_code']); ?>)</small>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $req['assigned_name'] ? htmlspecialchars($req['assigned_name']) : '<span style="color: var(--secondary);">Unassigned</span>'; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($req['created_at'])); ?></td>
                                <td>
                                    <button onclick="openRequestModal(<?php echo htmlspecialchars(json_encode($req)); ?>)" class="btn btn-sm btn-primary">View/Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Request Detail Modal -->
<div id="requestModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 800px; margin: 2rem auto; background: var(--bg); border-radius: 8px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>Rig Request Details</h2>
            <button onclick="closeRequestModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        
        <form method="post" id="requestForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="request_id" id="modal_request_id">
            <input type="hidden" name="update_status" value="1">
            
            <div style="display: grid; gap: 1.75rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">Request Number</label>
                        <div id="modal_request_number" class="modal-readonly"></div>
                    </div>
                    <div>
                        <label class="form-label">Submitted</label>
                        <div id="modal_created_at" class="modal-readonly"></div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">Requester Name</label>
                        <div id="modal_requester_name" class="modal-readonly"></div>
                    </div>
                    <div>
                        <label class="form-label">Email</label>
                        <div id="modal_requester_email" class="modal-readonly"></div>
                    </div>
                    <div>
                        <label class="form-label">Phone</label>
                        <div id="modal_requester_phone" class="modal-readonly"></div>
                    </div>
                </div>

                <div>
                    <label class="form-label">Company</label>
                    <div id="modal_company" class="modal-readonly"></div>
                </div>

                <div>
                    <label class="form-label">Location Address *</label>
                    <input type="text" name="location_address" id="modal_location_address" class="form-control" required>
                    <div class="modal-location-search">
                        <input type="text" id="modal_location_search" class="form-control" placeholder="Search location...">
                        <button type="button" class="btn btn-outline" id="modal_location_search_button">Search</button>
                    </div>
                    <div id="modal_map" class="modal-map" data-provider="<?php echo htmlspecialchars($mapSettings['provider']); ?>" data-api-key="<?php echo htmlspecialchars($mapSettings['api_key']); ?>"></div>
                    <div class="modal-coordinate-grid">
                        <div>
                            <label class="form-label">Latitude</label>
                            <input type="text" name="latitude" id="modal_latitude" class="form-control" placeholder="e.g. 5.6037">
                        </div>
                        <div>
                            <label class="form-label">Longitude</label>
                            <input type="text" name="longitude" id="modal_longitude" class="form-control" placeholder="-0.1870">
                        </div>
                    </div>
                    <small style="display:block; margin-top:8px; color: var(--secondary);">Click the map or drag the marker to update the exact site location.</small>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">Region</label>
                        <input type="text" name="region" id="modal_region" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Number of Boreholes *</label>
                        <input type="number" min="1" name="number_of_boreholes" id="modal_boreholes" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">Estimated Budget (GHS)</label>
                        <input type="number" step="0.01" min="0" name="estimated_budget" id="modal_estimated_budget" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Preferred Start Date</label>
                        <input type="date" name="preferred_start_date" id="modal_preferred_start_date" class="form-control">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div>
                        <label class="form-label">Urgency</label>
                        <select name="urgency" id="modal_urgency" class="form-control">
                            <option value="low">Low - Flexible</option>
                            <option value="medium">Medium - 2-4 weeks</option>
                            <option value="high">High - 1-2 weeks</option>
                            <option value="urgent">Urgent - ASAP</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Status *</label>
                        <select name="status" id="modal_status" class="form-control" required>
                            <option value="new">New</option>
                            <option value="under_review">Under Review</option>
                            <option value="negotiating">Negotiating</option>
                            <option value="dispatched">Dispatched</option>
                            <option value="declined">Declined</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Assign Rig</label>
                        <select name="assigned_rig_id" id="modal_assigned_rig" class="form-control">
                            <option value="">None</option>
                            <?php foreach ($rigs as $rig): ?>
                                <option value="<?php echo $rig['id']; ?>">
                                    <?php echo htmlspecialchars($rig['rig_name'] . ' (' . $rig['rig_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Assign To</label>
                        <select name="assigned_to" id="modal_assigned_to" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="form-label">Internal Notes</label>
                    <textarea name="internal_notes" id="modal_internal_notes" class="form-control" rows="4" placeholder="Visible to staff only"></textarea>
                </div>

                <div>
                    <label class="form-label">Notes from Requester</label>
                    <div id="modal_notes" class="modal-readonly" style="min-height: 60px;"></div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: space-between; align-items: center;">
                    <small style="color: var(--secondary);">Saving will update CRM records linked to this request.</small>
                    <div style="display: flex; gap: 1rem;">
                        <button type="button" onclick="closeRequestModal()" class="btn btn-outline">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-o9N1j7kG6G8GuI3p3VbK2s9O+Vx0SlhKpQtJ6Ck0Pzk=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-vI8sNcdxYvxZQ74dNwxLPp1kX3VzJ4IcQP+NlV0z0XQ=" crossorigin=""></script>
<?php if ($mapSettings['provider'] === 'google'): ?>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo htmlspecialchars($mapSettings['api_key']); ?>&libraries=places&callback=initRigRequestsModalMap" async defer></script>
<?php endif; ?>

<script>
const modalMapConfig = {
    provider: '<?php echo $mapSettings['provider']; ?>',
    apiKey: '<?php echo $mapSettings['api_key']; ?>',
    defaultLat: 5.6037,
    defaultLng: -0.1870,
    defaultZoom: 12
};

let modalMap = null;
let modalMarker = null;
let modalGeocoder = null;
let modalAutocomplete = null;
let modalMapInitialized = false;
let pendingModalCallback = null;
let currentModalRequest = null;

function openRequestModal(request) {
    currentModalRequest = request;
    document.getElementById('modal_request_id').value = request.id;
    document.getElementById('modal_request_number').textContent = request.request_number || '—';
    document.getElementById('modal_created_at').textContent = formatDateTime(request.created_at);
    document.getElementById('modal_requester_name').textContent = request.requester_name || '—';
    document.getElementById('modal_requester_email').textContent = request.requester_email || '—';
    document.getElementById('modal_requester_phone').textContent = request.requester_phone || '—';
    document.getElementById('modal_company').textContent = request.company_name || '—';
    document.getElementById('modal_location_address').value = request.location_address || '';
    document.getElementById('modal_location_search').value = request.location_address || '';
    document.getElementById('modal_region').value = request.region || '';
    document.getElementById('modal_boreholes').value = request.number_of_boreholes || 1;
    document.getElementById('modal_estimated_budget').value = request.estimated_budget ? parseFloat(request.estimated_budget) : '';
    document.getElementById('modal_preferred_start_date').value = request.preferred_start_date ? request.preferred_start_date : '';
    document.getElementById('modal_urgency').value = request.urgency || 'medium';
    document.getElementById('modal_status').value = request.status || 'new';
    document.getElementById('modal_assigned_rig').value = request.assigned_rig_id || '';
    document.getElementById('modal_assigned_to').value = request.assigned_to || '';
    document.getElementById('modal_internal_notes').value = request.internal_notes || '';
    document.getElementById('modal_notes').textContent = request.notes && request.notes.trim() !== '' ? request.notes : 'None';

    const latValue = request.latitude !== null ? parseFloat(request.latitude) : NaN;
    const lngValue = request.longitude !== null ? parseFloat(request.longitude) : NaN;
    document.getElementById('modal_latitude').value = !isNaN(latValue) ? latValue.toFixed(6) : '';
    document.getElementById('modal_longitude').value = !isNaN(lngValue) ? lngValue.toFixed(6) : '';

    ensureModalMap(() => {
        applyRequestToMap(request);
    });

    document.getElementById('requestModal').style.display = 'block';
}

function applyRequestToMap(request) {
    let lat = parseFloat(request.latitude);
    let lng = parseFloat(request.longitude);

    if (isNaN(lat) || isNaN(lng)) {
        lat = modalMapConfig.defaultLat;
        lng = modalMapConfig.defaultLng;
    }

    updateMapPosition(lat, lng);
}

function updateMapPosition(lat, lng) {
    if (modalMapConfig.provider === 'google' && modalMap && modalMarker) {
        const position = { lat, lng };
        modalMarker.setPosition(position);
        modalMap.setCenter(position);
        modalMap.setZoom(15);
        setTimeout(() => google.maps.event.trigger(modalMap, 'resize'), 150);
    } else if (modalMap && modalMarker) {
        modalMarker.setLatLng([lat, lng]);
        modalMap.setView([lat, lng], 15);
        setTimeout(() => modalMap.invalidateSize(), 150);
    }

    document.getElementById('modal_latitude').value = lat.toFixed(6);
    document.getElementById('modal_longitude').value = lng.toFixed(6);
}

function ensureModalMap(callback) {
    if (modalMapInitialized) {
        callback && callback();
        return;
    }

    pendingModalCallback = callback;

    if (modalMapConfig.provider === 'google') {
        if (typeof google !== 'undefined' && google.maps) {
            initGoogleModalMap();
        }
        return;
    }

    initLeafletModalMap();
}

function initLeafletModalMap() {
    const mapElement = document.getElementById('modal_map');
    if (!mapElement) return;

    modalMap = L.map('modal_map', {
        center: [modalMapConfig.defaultLat, modalMapConfig.defaultLng],
        zoom: modalMapConfig.defaultZoom,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(modalMap);

    modalMarker = L.marker([modalMapConfig.defaultLat, modalMapConfig.defaultLng], { draggable: true }).addTo(modalMap);
    modalMarker.on('dragend', e => {
        const { lat, lng } = e.target.getLatLng();
        updateMapPosition(lat, lng);
        reverseGeocodeLeaflet(lat, lng);
    });

    modalMap.on('click', e => {
        const { lat, lng } = e.latlng;
        updateMapPosition(lat, lng);
        reverseGeocodeLeaflet(lat, lng);
    });

    modalMap.whenReady(() => {
        setTimeout(() => modalMap.invalidateSize(), 150);
    });

    modalMapInitialized = true;
    if (typeof pendingModalCallback === 'function') {
        pendingModalCallback();
        pendingModalCallback = null;
    }
}

function initGoogleModalMap() {
    const mapElement = document.getElementById('modal_map');
    if (!mapElement) return;

    const defaultLocation = { lat: modalMapConfig.defaultLat, lng: modalMapConfig.defaultLng };
    modalMap = new google.maps.Map(mapElement, {
        center: defaultLocation,
        zoom: modalMapConfig.defaultZoom,
        mapTypeId: 'roadmap'
    });

    modalGeocoder = new google.maps.Geocoder();

    modalMarker = new google.maps.Marker({
        position: defaultLocation,
        map: modalMap,
        draggable: true
    });

    modalMarker.addListener('dragend', () => {
        const position = modalMarker.getPosition();
        const lat = position.lat();
        const lng = position.lng();
        updateMapPosition(lat, lng);
        reverseGeocodeGoogle(position);
    });

    modalMap.addListener('click', e => {
        const position = e.latLng;
        const lat = position.lat();
        const lng = position.lng();
        updateMapPosition(lat, lng);
        reverseGeocodeGoogle(position);
    });

    const searchInput = document.getElementById('modal_location_search');
    modalAutocomplete = new google.maps.places.Autocomplete(searchInput, {
        fields: ['geometry', 'formatted_address'],
    });
    modalAutocomplete.addListener('place_changed', () => {
        const place = modalAutocomplete.getPlace();
        if (!place.geometry) {
            return;
        }
        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();
        updateMapPosition(lat, lng);
        updateModalAddress(place.formatted_address);
    });

    modalMapInitialized = true;
    if (typeof pendingModalCallback === 'function') {
        pendingModalCallback();
        pendingModalCallback = null;
    }
}

function initRigRequestsModalMap() {
    initGoogleModalMap();
}

function updateModalAddress(address) {
    if (address) {
        document.getElementById('modal_location_address').value = address;
        document.getElementById('modal_location_search').value = address;
    }
}

async function reverseGeocodeLeaflet(lat, lng) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
        if (!response.ok) return;
        const data = await response.json();
        updateModalAddress(data.display_name || '');
    } catch (error) {
        console.warn('Reverse geocode failed', error);
    }
}

function reverseGeocodeGoogle(latLng) {
    if (!modalGeocoder) return;
    modalGeocoder.geocode({ location: latLng }, (results, status) => {
        if (status === 'OK' && results[0]) {
            updateModalAddress(results[0].formatted_address);
        }
    });
}

async function searchModalLocation(query) {
    if (!query) return;

    if (modalMapConfig.provider === 'google' && modalGeocoder) {
        modalGeocoder.geocode({ address: query }, (results, status) => {
            if (status === 'OK' && results[0]) {
                const location = results[0].geometry.location;
                updateMapPosition(location.lat(), location.lng());
                updateModalAddress(results[0].formatted_address);
            } else {
                alert('Location not found. Try a different search term.');
            }
        });
        return;
    }

    try {
        const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`;
        const response = await fetch(url, { headers: { 'Accept-Language': 'en' } });
        const results = await response.json();
        if (results && results.length > 0) {
            const result = results[0];
            const lat = parseFloat(result.lat);
            const lon = parseFloat(result.lon);
            updateMapPosition(lat, lon);
            updateModalAddress(result.display_name || query);
        } else {
            alert('Location not found. Try a different search term.');
        }
    } catch (error) {
        alert('Unable to search for that location at the moment.');
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    const parsed = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return parsed.toLocaleString();
}

function closeRequestModal() {
    document.getElementById('requestModal').style.display = 'none';
}

document.getElementById('requestModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRequestModal();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const searchBtn = document.getElementById('modal_location_search_button');
    const searchInput = document.getElementById('modal_location_search');

    if (searchBtn && searchInput) {
        searchBtn.addEventListener('click', () => {
            const query = searchInput.value.trim();
            if (!query) return;
            searchModalLocation(query);
        });
        searchInput.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchBtn.click();
            }
        });
    }

    ['modal_latitude', 'modal_longitude'].forEach(id => {
        const input = document.getElementById(id);
        if (input) {
            input.addEventListener('change', () => {
                const lat = parseFloat(document.getElementById('modal_latitude').value);
                const lng = parseFloat(document.getElementById('modal_longitude').value);
                if (!isNaN(lat) && !isNaN(lng)) {
                    updateMapPosition(lat, lng);
                }
            });
        }
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>

