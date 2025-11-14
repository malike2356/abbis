<?php
/**
 * Requests Management - Unified page for Quote Requests and Rig Requests
 * CRUD operations with modal view for details
 */
$page_title = 'Requests Management';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/request-response-manager.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];
$responseManager = new RequestResponseManager($pdo);

// Determine request type - check action parameter (from CRM) or type parameter (direct access)
$action = $_GET['action'] ?? '';
$isFromCRM = in_array($action, ['quote-requests', 'rig-requests']);
if ($action === 'quote-requests') {
    $_GET['type'] = 'quote';
    $page_title = 'Quote Requests - CRM';
} elseif ($action === 'rig-requests') {
    $_GET['type'] = 'rig';
    $page_title = 'Rig Requests - CRM';
} else {
    $page_title = 'Requests Management';
}

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
                case 'update_quote_status':
                    $id = intval($_POST['id']);
                    $status = $_POST['status'];
                    $stmt = $pdo->prepare("UPDATE cms_quote_requests SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $message = 'Quote request status updated';
                    $messageType = 'success';
                    break;
                    
                case 'update_rig_status':
                    $id = intval($_POST['id']);
                    $status = $_POST['status'];
                    $assignedRigId = !empty($_POST['assigned_rig_id']) ? intval($_POST['assigned_rig_id']) : null;
                    $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
                    $internalNotes = sanitizeInput($_POST['internal_notes'] ?? '');
                    
                    $stmt = $pdo->prepare("
                        UPDATE rig_requests 
                        SET status = ?, assigned_rig_id = ?, assigned_to = ?, internal_notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$status, $assignedRigId, $assignedTo, $internalNotes, $id]);
                    
                    if ($status === 'dispatched') {
                        $pdo->prepare("UPDATE rig_requests SET dispatched_at = NOW() WHERE id = ?")->execute([$id]);
                    }
                    if ($status === 'completed') {
                        $pdo->prepare("UPDATE rig_requests SET completed_at = NOW() WHERE id = ?")->execute([$id]);
                    }
                    
                    $message = 'Rig request updated';
                    $messageType = 'success';
                    break;
                    
                case 'delete_quote':
                    $id = intval($_POST['id']);
                    $pdo->prepare("DELETE FROM cms_quote_requests WHERE id = ?")->execute([$id]);
                    $message = 'Quote request deleted';
                    $messageType = 'success';
                    break;
                    
                case 'delete_rig':
                    $id = intval($_POST['id']);
                    $pdo->prepare("DELETE FROM rig_requests WHERE id = ?")->execute([$id]);
                    $message = 'Rig request deleted';
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
$typeFilter = $_GET['type'] ?? 'all'; // 'quote', 'rig', 'all'
$statusFilter = $_GET['status'] ?? 'all';

// Get quote requests
$quoteRequests = [];
try {
    $where = [];
    $params = [];
    
    if ($statusFilter !== 'all') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT qr.*, c.client_name
        FROM cms_quote_requests qr
        LEFT JOIN clients c ON qr.converted_to_client_id = c.id
        $whereClause
        ORDER BY qr.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $quoteRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    $quoteRequests = [];
}

foreach ($quoteRequests as &$qr) {
    $qr['responses'] = $responseManager->getResponsesForRequest('quote', (int)$qr['id']);
    $qr['history'] = $responseManager->getStatusHistoryForRequest('quote', (int)$qr['id']);
}
unset($qr);

// Get rig requests
$rigRequests = [];
try {
    $where = [];
    $params = [];
    
    if ($statusFilter !== 'all') {
        $where[] = "status = ?";
        $params[] = $statusFilter;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "
        SELECT rr.*, c.client_name, r.rig_name, r.rig_code, u.full_name as assigned_name
        FROM rig_requests rr
        LEFT JOIN clients c ON rr.client_id = c.id
        LEFT JOIN rigs r ON rr.assigned_rig_id = r.id
        LEFT JOIN users u ON rr.assigned_to = u.id
        $whereClause
        ORDER BY rr.created_at DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rigRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    $rigRequests = [];
}

foreach ($rigRequests as &$rr) {
    $rr['responses'] = $responseManager->getResponsesForRequest('rig', (int)$rr['id']);
    $rr['history'] = $responseManager->getStatusHistoryForRequest('rig', (int)$rr['id']);
}
unset($rr);

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

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <?php if (!$isFromCRM): ?>
    <nav aria-label="Breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <span>System</span> <span style="opacity:0.6;">→</span> <span>Requests Management</span>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="page-header">
        <h1>📋 Requests Management</h1>
        <p>Manage quote requests and rig rental requests</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="dashboard-card" style="margin-bottom: 24px;">
        <form method="get" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
            <div>
                <label class="form-label">Request Type</label>
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?php echo $typeFilter === 'all' ? 'selected' : ''; ?>>All Requests</option>
                    <option value="quote" <?php echo $typeFilter === 'quote' ? 'selected' : ''; ?>>Quote Requests Only</option>
                    <option value="rig" <?php echo $typeFilter === 'rig' ? 'selected' : ''; ?>>Rig Requests Only</option>
                </select>
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                    <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="contacted" <?php echo $statusFilter === 'contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="quoted" <?php echo $statusFilter === 'quoted' ? 'selected' : ''; ?>>Quoted</option>
                    <option value="under_review" <?php echo $statusFilter === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                    <option value="negotiating" <?php echo $statusFilter === 'negotiating' ? 'selected' : ''; ?>>Negotiating</option>
                    <option value="dispatched" <?php echo $statusFilter === 'dispatched' ? 'selected' : ''; ?>>Dispatched</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="converted" <?php echo $statusFilter === 'converted' ? 'selected' : ''; ?>>Converted</option>
                </select>
            </div>
        </form>
    </div>

    <!-- Quote Requests Section -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'quote'): ?>
    <div class="dashboard-card" style="margin-bottom: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">📋 Quote Requests (<?php echo count($quoteRequests); ?>)</h2>
            <a href="/abbis3.2/cms/quote" target="_blank" class="btn btn-outline">View Form →</a>
        </div>
        
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Location</th>
                        <th>Services</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($quoteRequests)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem; color: var(--secondary);">
                                No quote requests found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($quoteRequests as $qr): ?>
                            <tr>
                                <td><?php echo $qr['id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($qr['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($qr['email']); ?></td>
                                <td><?php echo htmlspecialchars($qr['phone'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($qr['location'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $services = [];
                                    if ($qr['include_drilling']) $services[] = 'Drilling';
                                    if ($qr['include_construction']) $services[] = 'Construction';
                                    if ($qr['include_mechanization']) $services[] = 'Mechanization';
                                    if ($qr['include_yield_test']) $services[] = 'Yield Test';
                                    if ($qr['include_chemical_test']) $services[] = 'Chemical Test';
                                    if ($qr['include_polytank_stand']) $services[] = 'Polytank';
                                    echo !empty($services) ? implode(', ', $services) : 'N/A';
                                    ?>
                                </td>
                                <td>
                                    <span id="quote-status-<?php echo (int)$qr['id']; ?>" class="badge badge-<?php 
                                        echo $qr['status'] === 'new' ? 'danger' : 
                                            ($qr['status'] === 'completed' || $qr['status'] === 'converted' ? 'success' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst($qr['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($qr['created_at'])); ?></td>
                                <td>
                                    <button onclick="openQuoteModal(<?php echo (int)$qr['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                                    <button onclick="editQuoteStatus(<?php echo $qr['id']; ?>, '<?php echo $qr['status']; ?>')" class="btn btn-sm btn-outline">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rig Requests Section -->
    <?php if ($typeFilter === 'all' || $typeFilter === 'rig'): ?>
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0;">🚛 Rig Requests (<?php echo count($rigRequests); ?>)</h2>
            <a href="/abbis3.2/cms/rig-request" target="_blank" class="btn btn-outline">View Form →</a>
        </div>
        
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
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rigRequests)): ?>
                        <tr>
                            <td colspan="10" style="text-align: center; padding: 2rem; color: var(--secondary);">
                                No rig requests found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rigRequests as $rr): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($rr['request_number']); ?></strong></td>
                                <td>
                                    <div><?php echo htmlspecialchars($rr['requester_name']); ?></div>
                                    <small style="color: var(--secondary);"><?php echo htmlspecialchars($rr['requester_email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($rr['location_address']); ?></td>
                                <td><?php echo $rr['number_of_boreholes']; ?></td>
                                <td>
                                    <?php if ($rr['estimated_budget']): ?>
                                        GHS <?php echo number_format($rr['estimated_budget'], 2); ?>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $rr['urgency'] === 'urgent' ? 'danger' : 
                                            ($rr['urgency'] === 'high' ? 'warning' : 
                                            ($rr['urgency'] === 'medium' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($rr['urgency']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span id="rig-status-<?php echo (int)$rr['id']; ?>" class="badge badge-<?php 
                                        echo $rr['status'] === 'new' ? 'danger' : 
                                            ($rr['status'] === 'completed' ? 'success' : 
                                            ($rr['status'] === 'dispatched' ? 'info' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $rr['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($rr['rig_name']): ?>
                                        <?php echo htmlspecialchars($rr['rig_name']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($rr['created_at'])); ?></td>
                                <td>
                                    <button onclick="openRigModal(<?php echo (int)$rr['id']; ?>)" class="btn btn-sm btn-primary">View</button>
                                    <button onclick="editRigStatus(<?php echo $rr['id']; ?>, '<?php echo $rr['status']; ?>', <?php echo $rr['assigned_rig_id'] ?: 'null'; ?>, <?php echo $rr['assigned_to'] ?: 'null'; ?>)" class="btn btn-sm btn-outline">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quote Request Details Modal -->
<div id="quoteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 900px; margin: 2rem auto; background: var(--bg); border-radius: 8px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>📋 Quote Request Details</h2>
            <button onclick="closeQuoteModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="quoteModalContent"></div>
    </div>
</div>

<!-- Rig Request Details Modal -->
<div id="rigModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
    <div style="max-width: 900px; margin: 2rem auto; background: var(--bg); border-radius: 8px; padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h2>🚛 Rig Request Details</h2>
            <button onclick="closeRigModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div id="rigModalContent"></div>
    </div>
</div>

<!-- Edit Status Modals -->
<div id="editQuoteModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="max-width: 500px; margin: 2rem auto; background: var(--bg); border-radius: 8px; padding: 2rem;">
        <h3>Edit Quote Request Status</h3>
        <form method="post" id="editQuoteForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="update_quote_status">
            <input type="hidden" name="id" id="edit_quote_id">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_quote_status" class="form-control" required>
                    <option value="new">New</option>
                    <option value="contacted">Contacted</option>
                    <option value="quoted">Quoted</option>
                    <option value="converted">Converted</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="closeEditQuoteModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<div id="editRigModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="max-width: 600px; margin: 2rem auto; background: var(--bg); border-radius: 8px; padding: 2rem;">
        <h3>Edit Rig Request</h3>
        <form method="post" id="editRigForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="update_rig_status">
            <input type="hidden" name="id" id="edit_rig_id">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_rig_status" class="form-control" required>
                    <option value="new">New</option>
                    <option value="under_review">Under Review</option>
                    <option value="negotiating">Negotiating</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="declined">Declined</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assign Rig</label>
                <select name="assigned_rig_id" id="edit_rig_rig_id" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($rigs as $rig): ?>
                        <option value="<?php echo $rig['id']; ?>">
                            <?php echo htmlspecialchars($rig['rig_name'] . ' (' . $rig['rig_code'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Assign To User</label>
                <select name="assigned_to" id="edit_rig_user_id" class="form-control">
                    <option value="">Unassigned</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Internal Notes</label>
                <textarea name="internal_notes" id="edit_rig_notes" class="form-control" rows="4"></textarea>
            </div>
            <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" onclick="closeEditRigModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = '<?php echo CSRF::getToken(); ?>';
const quoteData = <?php echo json_encode($quoteRequests, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
const rigData = <?php echo json_encode($rigRequests, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
const quoteDataMap = {};
const rigDataMap = {};

const quoteServiceFlags = [
    'include_drilling',
    'include_construction',
    'include_mechanization',
    'include_yield_test',
    'include_chemical_test',
    'include_polytank_stand'
];

function getParentRecord(type, parentId) {
    return type === 'quote' ? quoteDataMap[parentId] : rigDataMap[parentId];
}

quoteData.forEach((q) => {
    const id = Number(q.id);
    q.id = id;
    q.responses = Array.isArray(q.responses) ? q.responses : [];
    quoteServiceFlags.forEach((flag) => {
        q[flag] = q[flag] === 1 || q[flag] === '1' || q[flag] === true;
    });
    if (typeof q.pump_preferences === 'string' && q.pump_preferences.trim() !== '') {
        try {
            const parsed = JSON.parse(q.pump_preferences);
            if (Array.isArray(parsed)) {
                q.pump_preferences = parsed;
            }
        } catch (e) {
            // leave as string
        }
    }
    quoteDataMap[id] = q;
});

rigData.forEach((r) => {
    const id = Number(r.id);
    r.id = id;
    r.responses = Array.isArray(r.responses) ? r.responses : [];
    rigDataMap[id] = r;
});

function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

function formatDateTime(value) {
    if (!value) return 'N/A';
    const converted = typeof value === 'string' && value.indexOf('T') === -1 ? value.replace(' ', 'T') : value;
    const date = new Date(converted);
    if (Number.isNaN(date.getTime())) {
        return escapeHtml(value);
    }
    return date.toLocaleString();
}

function formatCurrency(currency, amount) {
    const value = Number(amount || 0);
    return `${currency || 'GHS'} ${value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function getQuoteStatusClass(status) {
    switch ((status || '').toLowerCase()) {
        case 'new':
            return 'danger';
        case 'contacted':
            return 'warning';
        case 'quoted':
            return 'info';
        case 'converted':
            return 'success';
        case 'rejected':
            return 'dark';
        default:
            return 'secondary';
    }
}

function getRigStatusClass(status) {
    switch ((status || '').toLowerCase()) {
        case 'new':
            return 'danger';
        case 'under_review':
        case 'negotiating':
            return 'warning';
        case 'dispatched':
            return 'info';
        case 'completed':
            return 'success';
        case 'declined':
            return 'dark';
        case 'cancelled':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function getResponseStatusClass(status) {
    switch ((status || '').toLowerCase()) {
        case 'draft':
            return 'warning';
        case 'pending_approval':
            return 'info';
        case 'approved':
            return 'primary';
        case 'sent':
            return 'success';
        case 'declined':
        case 'cancelled':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getUrgencyBadgeClass(urgency) {
    switch ((urgency || '').toLowerCase()) {
        case 'urgent':
            return 'danger';
        case 'high':
            return 'warning';
        case 'medium':
            return 'info';
        case 'low':
            return 'secondary';
        default:
            return 'secondary';
    }
}

function formatStatusLabel(status) {
    if (!status) return '';
    return status.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function openQuoteModal(id) {
    const quote = quoteDataMap[id];
    if (!quote) return;
    viewQuoteDetails(quote);
    document.getElementById('quoteModal').style.display = 'block';
}

function viewQuoteDetails(quote) {
    const services = [];
    if (quote.include_drilling) services.push('Drilling');
    if (quote.include_construction) services.push('Construction');
    if (quote.include_mechanization) services.push('Mechanization');
    if (quote.include_yield_test) services.push('Yield Test');
    if (quote.include_chemical_test) services.push('Chemical Test');
    if (quote.include_polytank_stand) services.push('Polytank Stand');

    let pumpInfo = '';
    if (Array.isArray(quote.pump_preferences) && quote.pump_preferences.length > 0) {
        pumpInfo = `<div><strong>Preferred Pumps:</strong> ${escapeHtml(quote.pump_preferences.join(', '))}</div>`;
    }

    const summaryHtml = `
        <div style="display: grid; gap: 1.5rem;">
            <div><strong>ID:</strong> ${escapeHtml(quote.id)}</div>
            <div><strong>Name:</strong> ${escapeHtml(quote.name || '')}</div>
            <div><strong>Email:</strong> ${escapeHtml(quote.email || '')}</div>
            <div><strong>Phone:</strong> ${escapeHtml(quote.phone || 'N/A')}</div>
            <div><strong>Location:</strong> ${escapeHtml(quote.location || 'N/A')}</div>
            ${quote.address ? `<div><strong>Address:</strong> ${escapeHtml(quote.address)}</div>` : ''}
            ${(quote.latitude && quote.longitude) ? `<div><strong>Coordinates:</strong> ${quote.latitude}, ${quote.longitude}</div>` : ''}
            <div><strong>Service Type:</strong> ${escapeHtml(quote.service_type || 'N/A')}</div>
            <div><strong>Services Required:</strong> ${services.length ? escapeHtml(services.join(', ')) : 'None selected'}</div>
            ${pumpInfo}
            ${quote.estimated_budget ? `<div><strong>Estimated Budget:</strong> ${formatCurrency('GHS', quote.estimated_budget)}</div>` : ''}
            <div><strong>Description:</strong> ${escapeHtml(quote.description || 'None')}</div>
            <div><strong>Status:</strong> <span class="badge badge-${getQuoteStatusClass(quote.status)}">${formatStatusLabel(quote.status)}</span></div>
            <div><strong>Created:</strong> ${formatDateTime(quote.created_at)}</div>
            ${quote.client_name ? `<div><strong>Linked Client:</strong> ${escapeHtml(quote.client_name)}</div>` : ''}
        </div>
    `;

    const content = summaryHtml + renderQuoteResponses(quote) + renderStatusHistory('quote', quote);
    document.getElementById('quoteModalContent').innerHTML = content;
}

function openRigModal(id) {
    const rig = rigDataMap[id];
    if (!rig) return;
    viewRigDetails(rig);
    document.getElementById('rigModal').style.display = 'block';
}

function viewRigDetails(rig) {
    const summaryHtml = `
        <div style="display: grid; gap: 1.5rem;">
            <div><strong>Request Number:</strong> ${escapeHtml(rig.request_number || '')}</div>
            <div><strong>Requester Name:</strong> ${escapeHtml(rig.requester_name || '')}</div>
            <div><strong>Email:</strong> ${escapeHtml(rig.requester_email || '')}</div>
            <div><strong>Phone:</strong> ${escapeHtml(rig.requester_phone || 'N/A')}</div>
            <div><strong>Type:</strong> ${formatStatusLabel(rig.requester_type)}</div>
            ${rig.company_name ? `<div><strong>Company:</strong> ${escapeHtml(rig.company_name)}</div>` : ''}
            <div><strong>Location:</strong> ${escapeHtml(rig.location_address || '')}</div>
            ${rig.region ? `<div><strong>Region:</strong> ${escapeHtml(rig.region)}</div>` : ''}
            ${(rig.latitude && rig.longitude) ? `<div><strong>Coordinates:</strong> ${rig.latitude}, ${rig.longitude}</div>` : ''}
            <div><strong>Number of Boreholes:</strong> ${escapeHtml(rig.number_of_boreholes || 0)}</div>
            ${rig.estimated_budget ? `<div><strong>Estimated Budget:</strong> ${formatCurrency('GHS', rig.estimated_budget)}</div>` : ''}
            ${rig.preferred_start_date ? `<div><strong>Preferred Start Date:</strong> ${escapeHtml(rig.preferred_start_date)}</div>` : ''}
            <div><strong>Urgency:</strong> <span class="badge badge-${getUrgencyBadgeClass(rig.urgency)}">${formatStatusLabel(rig.urgency)}</span></div>
            <div><strong>Status:</strong> <span class="badge badge-${getRigStatusClass(rig.status)}">${formatStatusLabel(rig.status)}</span></div>
            ${rig.rig_name ? `<div><strong>Assigned Rig:</strong> ${escapeHtml(rig.rig_name)} (${escapeHtml(rig.rig_code || '')})</div>` : ''}
            ${rig.assigned_name ? `<div><strong>Assigned To:</strong> ${escapeHtml(rig.assigned_name)}</div>` : ''}
            ${rig.notes ? `<div><strong>Notes:</strong> ${escapeHtml(rig.notes)}</div>` : ''}
            ${rig.internal_notes ? `<div><strong>Internal Notes:</strong> ${escapeHtml(rig.internal_notes)}</div>` : ''}
            <div><strong>Created:</strong> ${formatDateTime(rig.created_at)}</div>
            ${rig.client_name ? `<div><strong>Linked Client:</strong> ${escapeHtml(rig.client_name)}</div>` : ''}
        </div>
    `;

    const content = summaryHtml + renderRigResponses(rig) + renderStatusHistory('rig', rig);
    document.getElementById('rigModalContent').innerHTML = content;
}

function renderQuoteResponses(quote) {
    return renderResponses('quote', quote);
}

function renderRigResponses(rig) {
    return renderResponses('rig', rig);
}

function renderResponses(type, parent) {
    const responses = Array.isArray(parent.responses) ? parent.responses : [];
    const generateFn = type === 'quote' ? 'generateQuoteResponse' : 'generateRigResponse';
    const header = type === 'quote' ? 'Generated Quotes' : 'Generated Rig Responses';

    let html = `
        <div style="margin-top: 32px; border-top: 1px solid var(--border); padding-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 style="margin: 0; font-size: 18px;">${header}</h3>
                <button class="btn btn-sm btn-primary" onclick="${generateFn}(${parent.id}, this)">Generate Response</button>
            </div>
    `;

    if (!responses.length) {
        html += `
            <div style="padding: 16px; background: var(--bg-soft); border: 1px dashed var(--border); border-radius: 10px; color: var(--secondary);">
                No responses generated yet. Click "Generate Response" to build a proposal from catalog pricing.
            </div>
        `;
    } else {
        responses.forEach((response) => {
            html += renderResponseCard(type, parent, response);
        });
    }

    html += '</div>';
    return html;
}

function renderResponseCard(type, parent, response) {
    const items = Array.isArray(response.items) ? response.items : [];
    const currency = response.currency || 'GHS';
    const statusClass = getResponseStatusClass(response.status);
    const responseTitle = response.response_code || `Draft #${response.id}`;

    let itemsHtml = '';
    if (!items.length) {
        itemsHtml = '<div style="padding: 12px; color: var(--secondary);">No line items yet. Add custom items to detail this response.</div>';
    } else {
        itemsHtml = `
            <table style="width: 100%; border-collapse: collapse; margin-top: 12px;">
                <thead>
                    <tr style="background: #f8fafc;">
                        <th style="text-align:left; padding: 8px 12px;">Item</th>
                        <th style="text-align:center; padding: 8px 12px; width: 80px;">Qty</th>
                        <th style="text-align:right; padding: 8px 12px; width: 120px;">Unit</th>
                        <th style="text-align:right; padding: 8px 12px; width: 120px;">Total</th>
                        <th style="text-align:center; padding: 8px 12px; width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map((item) => `
                        <tr>
                            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">
                                <strong>${escapeHtml(item.item_name)}</strong>
                                ${item.description ? `<div style="margin-top:4px; color:#64748b; font-size:13px;">${escapeHtml(item.description)}</div>` : ''}
                            </td>
                            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align:center;">${Number(item.quantity || 0).toLocaleString(undefined, { maximumFractionDigits: 3 })}</td>
                            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align:right;">${formatCurrency(currency, item.unit_price)}</td>
                            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align:right;">${formatCurrency(currency, item.total)}</td>
                            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; text-align:center;">
                                <button class="btn btn-sm btn-outline" onclick="editResponseItem(${response.id}, ${item.id}, '${type}', ${parent.id})">Edit</button>
                                <button class="btn btn-sm btn-danger" onclick="deleteResponseItem(${item.id}, '${type}', ${parent.id})">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    const actions = [];
    actions.push(`<button class="btn btn-sm btn-outline" onclick="addCustomResponseItem(${response.id}, '${type}', ${parent.id})">Add Line Item</button>`);

    if ((response.status || '').toLowerCase() === 'draft' || !response.status) {
        actions.push(`<button class="btn btn-sm btn-secondary" onclick="submitResponseForApproval(${response.id}, '${type}', ${parent.id}, this)">Submit for Approval</button>`);
    }
    if ((response.status || '').toLowerCase() === 'pending_approval') {
        actions.push(`<button class="btn btn-sm btn-primary" onclick="approveResponse(${response.id}, '${type}', ${parent.id}, this)">Approve</button>`);
    }
    if (["draft", "approved", "pending_approval", "sent"].includes((response.status || '').toLowerCase())) {
        actions.push(`<button class="btn btn-sm btn-success" onclick="sendResponseEmail(${response.id}, '${type}', ${parent.id}, this)">Send to Client</button>`);
    }

    return `
        <div style="border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 16px; background: var(--card); box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);">
            <div style="display:flex; justify-content: space-between; align-items:flex-start; gap:16px;">
                <div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <h4 style="margin:0; font-size:16px; color:var(--text);">${escapeHtml(responseTitle)}</h4>
                        <span class="badge badge-${statusClass}">${formatStatusLabel(response.status)}</span>
                    </div>
                    <div style="margin-top:6px; color:#64748b; font-size:13px;">
                        Updated ${formatDateTime(response.updated_at || response.created_at)}
                    </div>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:18px; font-weight:600; color:var(--text-strong);">${formatCurrency(currency, response.total)}</div>
                    <div style="font-size:12px; color:#94a3b8;">Subtotal ${formatCurrency(currency, response.subtotal)} · Tax ${formatCurrency(currency, response.tax_total)}</div>
                </div>
            </div>
            ${itemsHtml}
            <div style="margin-top:16px; display:flex; flex-wrap:wrap; gap:8px;">
                ${actions.join('')}
            </div>
        </div>
    `;
}

function renderStatusHistory(type, parent) {
    const history = Array.isArray(parent.history) ? parent.history : [];
    let html = `
        <div style="margin-top: 32px;">
            <div style="display:flex; justify-content: space-between; align-items:center; margin-bottom: 12px;">
                <h3 style="margin:0; font-size:18px;">Request Status History</h3>
            </div>
    `;

    if (!history.length) {
        html += `
            <div style="padding: 16px; background: var(--bg-soft); border: 1px dashed var(--border); border-radius: 10px; color: var(--secondary);">
                No status changes have been recorded yet.
            </div>
        `;
    } else {
        html += '<div style="border: 1px solid var(--border); border-radius: 10px; overflow: hidden;">';
        history.forEach((entry, index) => {
            const badgeClass = type === 'quote' ? getQuoteStatusClass(entry.new_status) : getRigStatusClass(entry.new_status);
            html += `
                <div style="padding: 12px 16px; background: ${index % 2 === 0 ? '#f8fafc' : '#ffffff'}; border-bottom: 1px solid #e2e8f0;">
                    <div style="display:flex; justify-content: space-between; align-items:center; gap:16px;">
                        <span class="badge badge-${badgeClass}">${formatStatusLabel(entry.new_status)}</span>
                        <span style="color:#64748b; font-size:13px;">${formatDateTime(entry.created_at)}</span>
                    </div>
                    ${entry.note ? `<div style="margin-top:6px; color:#475569; font-size:13px;">${escapeHtml(entry.note)}</div>` : ''}
                    <div style="margin-top:6px; color:#94a3b8; font-size:12px;">Changed by ${entry.changed_by ? 'User #' + escapeHtml(entry.changed_by) : 'System'}</div>
                </div>
            `;
        });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

function closeQuoteModal() {
    document.getElementById('quoteModal').style.display = 'none';
}

function closeRigModal() {
    document.getElementById('rigModal').style.display = 'none';
}

function editQuoteStatus(id, status) {
    document.getElementById('edit_quote_id').value = id;
    document.getElementById('edit_quote_status').value = status;
    document.getElementById('editQuoteModal').style.display = 'block';
}

function closeEditQuoteModal() {
    document.getElementById('editQuoteModal').style.display = 'none';
}

function editRigStatus(id, status, rigId, userId) {
    document.getElementById('edit_rig_id').value = id;
    document.getElementById('edit_rig_status').value = status;
    document.getElementById('edit_rig_rig_id').value = rigId || '';
    document.getElementById('edit_rig_user_id').value = userId || '';
    document.getElementById('editRigModal').style.display = 'block';
}

function closeEditRigModal() {
    document.getElementById('editRigModal').style.display = 'none';
}

async function apiPost(action, payload) {
    const response = await fetch(`../api/crm-api.php?action=${encodeURIComponent(action)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(payload)
    });
    const data = await response.json();
    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'Request failed');
    }
    return data;
}

function updateParent(type, id, payload) {
    const map = type === 'quote' ? quoteDataMap : rigDataMap;
    const parent = map[id];
    if (!parent) return null;

    if (payload.responses) {
        parent.responses = payload.responses;
    } else if (payload.response) {
        const existingIndex = (parent.responses || []).findIndex((r) => Number(r.id) === Number(payload.response.id));
        if (existingIndex >= 0) {
            parent.responses[existingIndex] = payload.response;
        } else {
            parent.responses = [payload.response, ...(parent.responses || [])];
        }
    }

    if (payload.history) {
        parent.history = payload.history;
    }

    if (payload.request_status) {
        parent.status = payload.request_status;
    }

    return parent;
}

function updateStatusBadge(type, parent) {
    const elementId = type === 'quote' ? `quote-status-${parent.id}` : `rig-status-${parent.id}`;
    const el = document.getElementById(elementId);
    if (!el) return;
    const status = parent.status || '';
    const badgeClass = type === 'quote' ? getQuoteStatusClass(status) : getRigStatusClass(status);
    el.className = `badge badge-${badgeClass}`;
    el.textContent = formatStatusLabel(status);
}

async function generateQuoteResponse(id, button) {
    const quote = quoteDataMap[id];
    if (!quote) return;
    const originalText = button ? button.textContent : null;
    if (button) {
        button.disabled = true;
        button.textContent = 'Generating...';
    }
    try {
        const data = await apiPost('generate_request_response', {
            csrf_token: csrfToken,
            request_type: 'quote',
            request_id: id
        });
        const updated = updateParent('quote', id, data);
        if (updated) {
            updateStatusBadge('quote', updated);
            viewQuoteDetails(updated);
        }
        alert('Quote response generated successfully.');
    } catch (error) {
        alert(error.message);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText || 'Generate Response';
        }
    }
}

async function generateRigResponse(id, button) {
    const rig = rigDataMap[id];
    if (!rig) return;
    const originalText = button ? button.textContent : null;
    if (button) {
        button.disabled = true;
        button.textContent = 'Generating...';
    }
    try {
        const data = await apiPost('generate_request_response', {
            csrf_token: csrfToken,
            request_type: 'rig',
            request_id: id
        });
        const updated = updateParent('rig', id, data);
        if (updated) {
            updateStatusBadge('rig', updated);
            viewRigDetails(updated);
        }
        alert('Rig response generated successfully.');
    } catch (error) {
        alert(error.message);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalText || 'Generate Response';
        }
    }
}

async function addCustomResponseItem(responseId, type, parentId) {
    const parent = getParentRecord(type, parentId);
    if (!parent) return;

    const itemName = window.prompt('Enter line item description:', '');
    if (itemName === null || itemName.trim() === '') {
        return;
    }
    const description = window.prompt('Enter additional details (optional):', '');
    const quantityInput = window.prompt('Enter quantity:', '1');
    if (quantityInput === null) return;
    const unitPriceInput = window.prompt('Enter unit price:', '0');
    if (unitPriceInput === null) return;
    const discountInput = window.prompt('Discount amount (optional):', '0');
    if (discountInput === null) return;
    const taxRateInput = window.prompt('Tax rate % (optional):', '0');
    if (taxRateInput === null) return;

    const quantity = Number(quantityInput);
    const unitPrice = Number(unitPriceInput);
    const discountAmount = Number(discountInput);
    const taxRate = Number(taxRateInput);

    if (!Number.isFinite(quantity) || quantity <= 0) {
        alert('Please enter a valid quantity.');
        return;
    }
    if (!Number.isFinite(unitPrice)) {
        alert('Please enter a valid unit price.');
        return;
    }
    if (!Number.isFinite(discountAmount)) {
        alert('Please enter a valid discount amount.');
        return;
    }
    if (!Number.isFinite(taxRate)) {
        alert('Please enter a valid tax rate.');
        return;
    }

    try {
        const data = await apiPost('add_response_item', {
            csrf_token: csrfToken,
            response_id: responseId,
            item_name: itemName.trim(),
            description: description || '',
            quantity,
            unit_price: unitPrice,
            discount_amount: discountAmount,
            tax_rate: taxRate,
        });
        const updated = updateParent(type, parentId, data);
        if (updated) {
            if (type === 'quote') {
                viewQuoteDetails(updated);
            } else {
                viewRigDetails(updated);
            }
        }
        alert('Line item added.');
    } catch (error) {
        alert(error.message);
    }
}

async function editResponseItem(responseId, itemId, type, parentId) {
    const parent = getParentRecord(type, parentId);
    if (!parent) return;
    const response = (parent.responses || []).find((r) => Number(r.id) === Number(responseId));
    if (!response) {
        alert('Response not found.');
        return;
    }
    const item = (response.items || []).find((i) => Number(i.id) === Number(itemId));
    if (!item) {
        alert('Line item not found.');
        return;
    }

    const itemName = window.prompt('Edit line item description:', item.item_name || '');
    if (itemName === null || itemName.trim() === '') {
        return;
    }
    const description = window.prompt('Edit details (optional):', item.description || '');
    if (description === null) return;
    const quantityInput = window.prompt('Edit quantity:', item.quantity ?? 1);
    if (quantityInput === null) return;
    const unitPriceInput = window.prompt('Edit unit price:', item.unit_price ?? 0);
    if (unitPriceInput === null) return;
    const discountInput = window.prompt('Edit discount amount:', item.discount_amount ?? 0);
    if (discountInput === null) return;
    const taxRateInput = window.prompt('Edit tax rate %:', item.tax_rate ?? 0);
    if (taxRateInput === null) return;

    const quantity = Number(quantityInput);
    const unitPrice = Number(unitPriceInput);
    const discountAmount = Number(discountInput);
    const taxRate = Number(taxRateInput);

    if (!Number.isFinite(quantity) || quantity <= 0) {
        alert('Please enter a valid quantity.');
        return;
    }
    if (!Number.isFinite(unitPrice)) {
        alert('Please enter a valid unit price.');
        return;
    }
    if (!Number.isFinite(discountAmount)) {
        alert('Please enter a valid discount amount.');
        return;
    }
    if (!Number.isFinite(taxRate)) {
        alert('Please enter a valid tax rate.');
        return;
    }

    try {
        const data = await apiPost('update_response_item', {
            csrf_token: csrfToken,
            item_id: itemId,
            item_name: itemName.trim(),
            description: description || '',
            quantity,
            unit_price: unitPrice,
            discount_amount: discountAmount,
            tax_rate: taxRate,
        });
        const updated = updateParent(type, parentId, data);
        if (updated) {
            if (type === 'quote') {
                viewQuoteDetails(updated);
            } else {
                viewRigDetails(updated);
            }
        }
        alert('Line item updated.');
    } catch (error) {
        alert(error.message);
    }
}

async function deleteResponseItem(itemId, type, parentId) {
    if (!window.confirm('Delete this line item?')) {
        return;
    }

    try {
        const data = await apiPost('delete_response_item', {
            csrf_token: csrfToken,
            item_id: itemId,
        });
        const updated = updateParent(type, parentId, data);
        if (updated) {
            if (type === 'quote') {
                viewQuoteDetails(updated);
            } else {
                viewRigDetails(updated);
            }
        }
        alert('Line item deleted.');
    } catch (error) {
        alert(error.message);
    }
}

document.getElementById('quoteModal').addEventListener('click', function (e) {
    if (e.target === this) closeQuoteModal();
});

document.getElementById('rigModal').addEventListener('click', function (e) {
    if (e.target === this) closeRigModal();
});

document.getElementById('editQuoteModal').addEventListener('click', function (e) {
    if (e.target === this) closeEditQuoteModal();
});

document.getElementById('editRigModal').addEventListener('click', function (e) {
    if (e.target === this) closeEditRigModal();
});
</script>

<?php require_once '../includes/footer.php'; ?>

