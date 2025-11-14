<?php
session_start();

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/includes/helpers.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$currentUser = $cmsAuth->getCurrentUser();

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';

$statuses = [
    'new' => 'New',
    'triage' => 'Triage',
    'in_progress' => 'In Progress',
    'awaiting_customer' => 'Awaiting Customer',
    'resolved' => 'Resolved',
    'closed' => 'Closed',
    'cancelled' => 'Cancelled',
];

$priorities = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',
];

$channels = [
    'phone' => 'Phone',
    'email' => 'Email',
    'web' => 'Web Form',
    'mobile' => 'Mobile App',
    'walk_in' => 'Walk-in',
    'other' => 'Other',
];

$complaintsTablesReady = true;
try {
    $pdo->query("SELECT 1 FROM complaints LIMIT 1");
} catch (Throwable $e) {
    $complaintsTablesReady = false;
}

if ($complaintsTablesReady && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        flash('error', 'Invalid security token');
        redirect('complaints.php');
    }

    $postAction = $_POST['action'] ?? '';

    try {
        switch ($postAction) {
            case 'create':
                $complaintCode = generateComplaintCode($pdo);
                $summary = trim($_POST['summary'] ?? '');
                if (empty($summary)) {
                    throw new Exception('Summary is required');
                }
                $stmt = $pdo->prepare("
                    INSERT INTO complaints (
                        complaint_code, source, channel, customer_name,
                        customer_email, customer_phone, customer_reference,
                        category, subcategory, priority, status, summary, description,
                        due_date, assigned_to, created_by
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )
                ");
                $stmt->execute([
                    $complaintCode,
                    sanitizeInput($_POST['source'] ?? ''),
                    sanitizeInput($_POST['channel'] ?? 'other'),
                    sanitizeInput($_POST['customer_name'] ?? ''),
                    sanitizeInput($_POST['customer_email'] ?? ''),
                    sanitizeInput($_POST['customer_phone'] ?? ''),
                    sanitizeInput($_POST['customer_reference'] ?? ''),
                    sanitizeInput($_POST['category'] ?? ''),
                    sanitizeInput($_POST['subcategory'] ?? ''),
                    sanitizeInput($_POST['priority'] ?? 'medium'),
                    sanitizeInput($_POST['status'] ?? 'new'),
                    $summary,
                    sanitizeInput($_POST['description'] ?? ''),
                    sanitizeInput($_POST['due_date'] ?? '') ?: null,
                    !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null,
                    $currentUser['id'],
                ]);

                $complaintId = $pdo->lastInsertId();
                $note = trim($_POST['initial_note'] ?? '');
                if (!empty($note)) {
                    addComplaintUpdate($pdo, $complaintId, [
                        'update_type' => 'note',
                        'update_text' => $note,
                        'added_by' => $currentUser['id'],
                    ]);
                }

                flash('success', 'Complaint logged successfully');
                redirect('complaints.php?action=view&id=' . $complaintId);
                break;

            case 'add_note':
                $complaintId = intval($_POST['complaint_id'] ?? 0);
                $noteText = trim($_POST['note_text'] ?? '');
                $internal = !empty($_POST['internal_only']) ? 1 : 0;

                if ($complaintId <= 0 || empty($noteText)) {
                    throw new Exception('Note text is required');
                }

                addComplaintUpdate($pdo, $complaintId, [
                    'update_type' => 'note',
                    'update_text' => $noteText,
                    'internal_only' => $internal,
                    'added_by' => $currentUser['id'],
                ]);

                flash('success', 'Note added');
                redirect('complaints.php?action=view&id=' . $complaintId);
                break;

            case 'update_status':
                $complaintId = intval($_POST['complaint_id'] ?? 0);
                $newStatus = sanitizeInput($_POST['status'] ?? 'new');

                if (!isset($statuses[$newStatus])) {
                    throw new Exception('Invalid status');
                }

                $stmt = $pdo->prepare("SELECT status FROM complaints WHERE id = ?");
                $stmt->execute([$complaintId]);
                $currentStatus = $stmt->fetchColumn();
                if (!$currentStatus) {
                    throw new Exception('Complaint not found');
                }

                $stmt = $pdo->prepare("
                    UPDATE complaints 
                    SET status = ?, updated_by = ?, updated_at = NOW(),
                        resolved_at = CASE WHEN ? IN ('resolved','closed') THEN NOW() ELSE resolved_at END,
                        closed_at = CASE WHEN ? = 'closed' THEN NOW() ELSE closed_at END
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $currentUser['id'], $newStatus, $newStatus, $complaintId]);

                $comment = trim($_POST['status_comment'] ?? '');
                addComplaintUpdate($pdo, $complaintId, [
                    'update_type' => 'status_change',
                    'status_before' => $currentStatus,
                    'status_after' => $newStatus,
                    'update_text' => $comment,
                    'added_by' => $currentUser['id'],
                ]);

                flash('success', 'Status updated');
                redirect('complaints.php?action=view&id=' . $complaintId);
                break;

            case 'assign':
                $complaintId = intval($_POST['complaint_id'] ?? 0);
                $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;

                $stmt = $pdo->prepare("SELECT status FROM complaints WHERE id = ?");
                $stmt->execute([$complaintId]);
                if (!$stmt->fetchColumn()) {
                    throw new Exception('Complaint not found');
                }

                $stmt = $pdo->prepare("UPDATE complaints SET assigned_to = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$assignedTo, $currentUser['id'], $complaintId]);

                $assignmentNote = trim($_POST['assignment_note'] ?? '');
                addComplaintUpdate($pdo, $complaintId, [
                    'update_type' => 'assignment',
                    'update_text' => $assignmentNote,
                    'added_by' => $currentUser['id'],
                ]);

                flash('success', 'Assignment updated');
                redirect('complaints.php?action=view&id=' . $complaintId);
                break;

            default:
                flash('error', 'Unsupported action');
                redirect('complaints.php');
        }
    } catch (Exception $e) {
        flash('error', $e->getMessage());
        redirect('complaints.php');
    }
}

$action = $_GET['action'] ?? 'overview';

$filters = [
    'status' => $_GET['status'] ?? 'all',
    'priority' => $_GET['priority'] ?? 'all',
    'assigned' => $_GET['assigned'] ?? 'mine',
    'channel' => $_GET['channel'] ?? 'all',
    'search' => trim($_GET['search'] ?? ''),
];

if ($complaintsTablesReady && isset($_GET['export']) && in_array($_GET['export'], ['csv', 'json'], true)) {
    $exportType = $_GET['export'];
    $exportData = fetchComplaints($pdo, $filters, $currentUser['id'], null);

    if ($exportType === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="complaints-export-' . date('Ymd-His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Complaint Code',
            'Summary',
            'Status',
            'Priority',
            'Channel',
            'Customer Name',
            'Customer Email',
            'Assigned To',
            'Due Date',
            'Created At',
        ]);

        foreach ($exportData as $row) {
            fputcsv($out, [
                $row['complaint_code'],
                $row['summary'],
                $row['status'],
                $row['priority'],
                $row['channel'],
                $row['customer_name'],
                $row['customer_email'],
                $row['assigned_name'],
                $row['due_date'],
                $row['created_at'],
            ]);
        }

        fclose($out);
        exit;
    }

    if ($exportType === 'json') {
        header('Content-Type: application/json');
        echo json_encode([
            'generated_at' => date(DATE_ATOM),
            'filters' => $filters,
            'data' => $exportData,
        ]);
        exit;
    }
}

$users = [];
try {
    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
} catch (Throwable $e) {
    $users = [];
}

$complaint = null;
$updates = [];

if ($complaintsTablesReady && $action === 'view') {
    $complaintId = intval($_GET['id'] ?? 0);
    if ($complaintId > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS assigned_name, creator.full_name AS created_name
            FROM complaints c
            LEFT JOIN users u ON u.id = c.assigned_to
            LEFT JOIN users creator ON creator.id = c.created_by
            WHERE c.id = ?
        ");
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($complaint) {
            $stmt = $pdo->prepare("
                SELECT cu.*, u.full_name 
                FROM complaint_updates cu
                LEFT JOIN users u ON u.id = cu.added_by
                WHERE cu.complaint_id = ?
                ORDER BY cu.created_at DESC
            ");
            $stmt->execute([$complaintId]);
            $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (!$complaint) {
        flash('error', 'Complaint not found');
        redirect('complaints.php');
    }
}

$metrics = $complaintsTablesReady ? getComplaintMetrics($pdo, $currentUser['id']) : [
    'total' => 0,
    'open' => 0,
    'overdue' => 0,
    'resolved_month' => 0,
    'my_open' => 0,
    'logged_today' => 0,
];
$statusBreakdown = $complaintsTablesReady ? getComplaintBreakdown($pdo, 'status') : [];
$priorityBreakdown = $complaintsTablesReady ? getComplaintBreakdown($pdo, 'priority') : [];
$complaintList = ($complaintsTablesReady && $action === 'overview') ? fetchComplaints($pdo, $filters, $currentUser['id']) : [];

$currentPage = 'complaints';

include 'header.php';
?>

<style>
    .hidden {
        display: none;
    }
    .cms-complaints-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
        margin-bottom: 24px;
    }
    .cms-complaints-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 18px;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .cms-complaints-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
    }
    .cms-complaints-card h4 {
        margin: 0;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
    }
    .cms-complaints-card p {
        margin: 8px 0 0;
        font-size: 26px;
        font-weight: 700;
        color: #1f2937;
    }
    .cms-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 22px 24px;
        margin-bottom: 24px;
        box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
    }
    .cms-card h2, .cms-card h3 {
        margin: 0;
        color: #1f2937;
    }
    .cms-card h3 {
        font-size: 18px;
        margin-bottom: 12px;
    }
    .cms-card p.helper {
        margin: 6px 0 16px;
        color: #64748b;
        font-size: 13px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 16px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 6px;
    }
    .form-control {
        width: 100%;
        padding: 9px 12px;
        border-radius: 8px;
        border: 1px solid #d7dce4;
        font-size: 14px;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }
    .form-control:focus {
        border-color: #2563eb;
        outline: none;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }
    .form-checkbox {
        display: inline-flex;
        align-items: flex-start;
        gap: 10px;
        font-size: 13px;
        color: #1f2937;
    }
    .form-grid textarea {
        min-height: 110px;
    }
    .cms-table {
        width: 100%;
        border-collapse: collapse;
    }
    .cms-table th {
        background: #f8fafc;
        text-align: left;
        padding: 12px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
    }
    .cms-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
        font-size: 14px;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-status {
        background: rgba(37, 99, 235, 0.1);
        color: #1d4ed8;
    }
    .badge-priority {
        background: rgba(217, 119, 6, 0.15);
        color: #b45309;
    }
    .badge-channel {
        background: rgba(14, 165, 233, 0.15);
        color: #0c4a6e;
    }
    .timeline {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .timeline-item {
        display: grid;
        grid-template-columns: 40px 1fr;
        gap: 12px;
    }
    .timeline-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(37, 99, 235, 0.12);
        color: #1d4ed8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: 600;
    }
    .timeline-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 16px;
        background: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
    }
    .timeline-card header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 13px;
        margin-bottom: 6px;
    }
    .timeline-note {
        margin-top: 8px;
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(37, 99, 235, 0.08);
        white-space: pre-wrap;
        font-size: 13px;
        color: #1f2937;
    }
    .flex-between {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .filters {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    .filters select, .filters input {
        min-width: 140px;
    }
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 18px;
        margin-top: 20px;
    }
    .detail-stack {
        display: flex;
        flex-direction: column;
        gap: 8px;
        font-size: 13px;
        color: #1f2937;
    }
    .detail-stack span {
        display: inline-block;
        min-width: 110px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-right: 8px;
    }
    .description-box {
        margin-top: 18px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        background: #f8fafc;
        font-size: 14px;
        color: #1f2937;
        min-height: 80px;
        white-space: pre-wrap;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 10px 18px;
        border-radius: 10px;
        border: 1px solid transparent;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    .btn-primary {
        background: #2563eb;
        color: #ffffff;
    }
    .btn-primary:hover {
        background: #1d4ed8;
    }
    .btn-outline {
        background: #ffffff;
        border-color: #cbd5f5;
        color: #1d4ed8;
    }
    .btn-outline:hover {
        border-color: #2563eb;
        color: #2563eb;
    }
    .btn-sm {
        padding: 8px 14px;
        font-size: 13px;
    }
    .badge-internal {
        font-size: 11px;
        color: #b45309;
        margin-left: 6px;
    }
</style>

<div class="wrap">
    <div class="admin-page-header">
        <h1>📣 Complaints Management</h1>
        <p>Capture feedback, assign ownership, and track resolution progress directly from the CMS.</p>
    </div>

    <?php if ($flash = getFlash()): ?>
        <div class="cms-card" style="border-left:4px solid <?php echo $flash['type'] === 'success' ? '#22c55e' : ($flash['type'] === 'error' ? '#ef4444' : '#2563eb'); ?>;">
            <strong><?php echo ucfirst($flash['type']); ?>:</strong>
            <p style="margin:6px 0 0;"><?php echo e($flash['message']); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!$complaintsTablesReady): ?>
        <div class="cms-card" style="border-left:4px solid #f97316; background: rgba(251, 191, 36, 0.08);">
            <h3>Database migration required</h3>
            <p class="helper">Run <code>database/complaints_module_migration.sql</code> within ABBIS to initialise the complaints tables, then refresh this page.</p>
        </div>
    <?php elseif ($action === 'view' && $complaint): ?>
        <div class="cms-card" style="margin-bottom:24px;">
            <div class="flex-between">
                <div>
                    <h2><?php echo e($complaint['complaint_code']); ?> · <?php echo e($complaint['summary']); ?></h2>
                    <p class="helper">
                        Logged <?php echo date('M d, Y H:i', strtotime($complaint['created_at'])); ?>
                        <?php if (!empty($complaint['created_name'])): ?> · by <?php echo e($complaint['created_name']); ?><?php endif; ?>
                    </p>
                </div>
                <a href="complaints.php" class="btn btn-outline btn-sm">← Back to register</a>
            </div>

            <div class="cms-complaints-grid" style="margin-top:12px;">
                <div class="cms-complaints-card">
                    <h4>Status</h4>
                    <p><?php echo e($statuses[$complaint['status']] ?? ucfirst($complaint['status'])); ?></p>
                </div>
                <div class="cms-complaints-card">
                    <h4>Priority</h4>
                    <p><?php echo e($priorities[$complaint['priority']] ?? ucfirst($complaint['priority'])); ?></p>
                </div>
                <div class="cms-complaints-card">
                    <h4>Owner</h4>
                    <p><?php echo e($complaint['assigned_name'] ?: 'Unassigned'); ?></p>
                </div>
                <div class="cms-complaints-card">
                    <h4>Due Date</h4>
                    <p><?php echo $complaint['due_date'] ? date('M d, Y', strtotime($complaint['due_date'])) : '—'; ?></p>
                </div>
            </div>

            <div class="detail-grid">
                <div>
                    <h3>Customer Details</h3>
                    <div class="detail-stack">
                        <div><span>Customer</span><?php echo e($complaint['customer_name'] ?: 'Not provided'); ?></div>
                        <div><span>Email</span><?php echo e($complaint['customer_email'] ?: '—'); ?></div>
                        <div><span>Phone</span><?php echo e($complaint['customer_phone'] ?: '—'); ?></div>
                        <div><span>Reference</span><?php echo e($complaint['customer_reference'] ?: '—'); ?></div>
                        <div><span>Channel</span><?php echo e($channels[$complaint['channel']] ?? ucfirst($complaint['channel'])); ?></div>
                    </div>
                </div>
                <div>
                    <h3>Classification</h3>
                    <div class="detail-stack">
                        <div><span>Source</span><?php echo e($complaint['source'] ?: '—'); ?></div>
                        <div><span>Category</span><?php echo e($complaint['category'] ?: '—'); ?></div>
                        <div><span>Subcategory</span><?php echo e($complaint['subcategory'] ?: '—'); ?></div>
                        <div><span>Resolved</span><?php echo $complaint['resolved_at'] ? date('M d, Y H:i', strtotime($complaint['resolved_at'])) : '—'; ?></div>
                        <div><span>Closed</span><?php echo $complaint['closed_at'] ? date('M d, Y H:i', strtotime($complaint['closed_at'])) : '—'; ?></div>
                    </div>
                </div>
            </div>

            <div class="description-box">
                <?php echo nl2br(e($complaint['description'] ?: 'No detailed description captured.')); ?>
            </div>
        </div>

        <div class="cms-complaints-grid" style="margin-bottom:24px;">
            <div class="cms-card">
                <h3>Update Status</h3>
                <form method="post" class="form-grid" style="grid-template-columns:1fr;">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">

                    <div>
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php foreach ($statuses as $key => $label): ?>
                                <option value="<?php echo e($key); ?>" <?php echo $complaint['status'] === $key ? 'selected' : ''; ?>>
                                    <?php echo e($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Comment (optional)</label>
                        <textarea name="status_comment" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            </div>

            <div class="cms-card">
                <h3>Assign Owner</h3>
                <form method="post" class="form-grid" style="grid-template-columns:1fr;">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="action" value="assign">
                    <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">

                    <div>
                        <label class="form-label">Assign To</label>
                        <select name="assigned_to" class="form-control">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo intval($user['id']); ?>" <?php echo ($complaint['assigned_to'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($user['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Assignment Note</label>
                        <textarea name="assignment_note" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Assignment</button>
                </form>
            </div>
        </div>

        <div class="cms-card">
            <div class="flex-between" style="margin-bottom:16px;">
                <h3>Activity History</h3>
            </div>

            <form method="post" class="form-grid" style="margin-bottom:20px;">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="add_note">
                <input type="hidden" name="complaint_id" value="<?php echo intval($complaint['id']); ?>">
                <div style="grid-column:1 / -1;">
                    <label class="form-label">Add Note</label>
                    <textarea name="note_text" class="form-control" rows="3" required placeholder="Provide update, summary or next steps..."></textarea>
                </div>
                <label class="form-checkbox" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" name="internal_only" value="1">
                    <span>Internal only (hidden from customer exports)</span>
                </label>
                <div style="display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">Post Note</button>
                    <button type="reset" class="btn btn-outline">Clear</button>
                </div>
            </form>

            <div class="timeline">
                <?php if (empty($updates)): ?>
                    <p style="color:#64748b;">No updates have been captured yet.</p>
                <?php else: ?>
                    <?php foreach ($updates as $update): ?>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <?php
                                $icon = '📝';
                                if ($update['update_type'] === 'status_change') $icon = '🔁';
                                if ($update['update_type'] === 'assignment') $icon = '👤';
                                if ($update['update_type'] === 'escalation') $icon = '🚨';
                                echo $icon;
                                ?>
                            </div>
                            <div class="timeline-card">
                                <header>
                                    <strong><?php echo e($update['full_name'] ?? 'System'); ?></strong>
                                    <span><?php echo date('M d, Y H:i', strtotime($update['created_at'])); ?></span>
                                </header>
                                <div>
                                    <?php if ($update['update_type'] === 'status_change'): ?>
                                        <p style="margin:0;">Status changed from <strong><?php echo e($statuses[$update['status_before']] ?? $update['status_before']); ?></strong> to <strong><?php echo e($statuses[$update['status_after']] ?? $update['status_after']); ?></strong>.</p>
                                    <?php elseif ($update['update_type'] === 'assignment'): ?>
                                        <p style="margin:0;">Assignment updated.</p>
                                    <?php endif; ?>
                                    <?php if (!empty($update['update_text'])): ?>
                                        <div class="timeline-note">
                                            <?php echo nl2br(e($update['update_text'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($update['internal_only'])): ?>
                                        <span class="badge-internal">Internal note</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="cms-complaints-grid">
            <div class="cms-complaints-card">
                <h4>Total Complaints</h4>
                <p><?php echo number_format($metrics['total']); ?></p>
            </div>
            <div class="cms-complaints-card">
                <h4>Open / Active</h4>
                <p><?php echo number_format($metrics['open']); ?></p>
            </div>
            <div class="cms-complaints-card">
                <h4>Overdue</h4>
                <p><?php echo number_format($metrics['overdue']); ?></p>
            </div>
            <div class="cms-complaints-card">
                <h4>Resolved This Month</h4>
                <p><?php echo number_format($metrics['resolved_month']); ?></p>
            </div>
            <div class="cms-complaints-card">
                <h4>My Open Items</h4>
                <p><?php echo number_format($metrics['my_open']); ?></p>
            </div>
            <div class="cms-complaints-card">
                <h4>Logged Today</h4>
                <p><?php echo number_format($metrics['logged_today']); ?></p>
            </div>
        </div>

        <div class="cms-card">
            <div class="flex-between">
                <div>
                    <h2>Log New Complaint</h2>
                    <p class="helper">Capture customer feedback, assign owners, and define follow-up actions.</p>
                </div>
                <button id="toggleComplaintForm" type="button" class="btn btn-primary">
                    + New Complaint
                </button>
            </div>

            <form id="newComplaintForm" class="form-grid hidden" method="post" style="margin-top:18px;">
                <?php echo CSRF::getTokenField(); ?>
                <input type="hidden" name="action" value="create">

                <div>
                    <label class="form-label">Summary *</label>
                    <input type="text" name="summary" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Source</label>
                    <input type="text" name="source" class="form-control" placeholder="e.g. Contract ABC">
                </div>
                <div>
                    <label class="form-label">Channel</label>
                    <select name="channel" class="form-control">
                        <?php foreach ($channels as $key => $label): ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?php echo e($key); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" class="form-control">
                </div>
                <div>
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-control">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo intval($user['id']); ?>"><?php echo e($user['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Customer Name</label>
                    <input type="text" name="customer_name" class="form-control">
                </div>
                <div>
                    <label class="form-label">Customer Email</label>
                    <input type="email" name="customer_email" class="form-control">
                </div>
                <div>
                    <label class="form-label">Customer Phone</label>
                    <input type="text" name="customer_phone" class="form-control">
                </div>
                <div>
                    <label class="form-label">Customer Reference</label>
                    <input type="text" name="customer_reference" class="form-control" placeholder="e.g. Ticket #, Account ID">
                </div>
                <div>
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Billing, Service Delivery">
                </div>
                <div>
                    <label class="form-label">Subcategory</label>
                    <input type="text" name="subcategory" class="form-control">
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Detailed description of the issue"></textarea>
                </div>
                <div style="grid-column:1 / -1;">
                    <label class="form-label">Initial Note (optional)</label>
                    <textarea name="initial_note" class="form-control" rows="2"></textarea>
                </div>
                <div style="grid-column:1 / -1; display:flex; gap:12px;">
                    <button type="submit" class="btn btn-primary">Save Complaint</button>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('newComplaintForm').reset();">Clear</button>
                </div>
            </form>
        </div>

        <div class="cms-card">
            <div class="flex-between">
                <div>
                    <h2>Complaint Register</h2>
                    <p class="helper">Filter by status, priority, channel, and ownership.</p>
                </div>
                <form method="get" class="filters">
                    <input type="hidden" name="action" value="overview">
                    <select name="status" class="form-control">
                        <option value="all">All Statuses</option>
                        <?php foreach ($statuses as $key => $label): ?>
                            <option value="<?php echo e($key); ?>" <?php echo $filters['status'] === $key ? 'selected' : ''; ?>>
                                <?php echo e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="priority" class="form-control">
                        <option value="all">All Priorities</option>
                        <?php foreach ($priorities as $key => $label): ?>
                            <option value="<?php echo e($key); ?>" <?php echo $filters['priority'] === $key ? 'selected' : ''; ?>>
                                <?php echo e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="channel" class="form-control">
                        <option value="all">All Channels</option>
                        <?php foreach ($channels as $key => $label): ?>
                            <option value="<?php echo e($key); ?>" <?php echo $filters['channel'] === $key ? 'selected' : ''; ?>>
                                <?php echo e($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="assigned" class="form-control">
                        <option value="mine" <?php echo $filters['assigned'] === 'mine' ? 'selected' : ''; ?>>Assigned to me</option>
                        <option value="all" <?php echo $filters['assigned'] === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="unassigned" <?php echo $filters['assigned'] === 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                    </select>
                    <input type="text" name="search" class="form-control" placeholder="Search code, customer, summary" value="<?php echo e($filters['search']); ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                    <a href="complaints.php" class="btn btn-outline btn-sm">Reset</a>
                </form>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="<?php echo buildExportUrl('csv'); ?>" class="btn btn-outline btn-sm">Export CSV</a>
                    <a href="<?php echo buildExportUrl('json'); ?>" class="btn btn-outline btn-sm">Export JSON</a>
                </div>
            </div>

            <div style="overflow-x:auto; margin-top:16px;">
                <table class="cms-table">
                    <thead>
                        <tr>
                            <th>Complaint</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Channel</th>
                            <th>Assigned</th>
                            <th>Due</small></th>
                            <th>Logged</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaintList)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:28px; color:#64748b;">No complaints match your filters yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($complaintList as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo e($item['complaint_code']); ?></strong>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?php echo e($item['summary']); ?><br>
                                            <?php echo e($item['customer_name'] ?: 'No customer'); ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-status"><?php echo e($statuses[$item['status']] ?? ucfirst($item['status'])); ?></span></td>
                                    <td><span class="badge badge-priority"><?php echo e($priorities[$item['priority']] ?? ucfirst($item['priority'])); ?></span></td>
                                    <td><span class="badge badge-channel"><?php echo e($channels[$item['channel']] ?? ucfirst($item['channel'])); ?></span></td>
                                    <td><?php echo e($item['assigned_name'] ?: 'Unassigned'); ?></td>
                                    <td><?php echo $item['due_date'] ? date('M d, Y', strtotime($item['due_date'])) : '—'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($item['created_at'])); ?></td>
                                    <td style="text-align:right;">
                                        <a href="complaints.php?action=view&id=<?php echo intval($item['id']); ?>" class="btn btn-outline btn-sm">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cms-complaints-grid">
            <div class="cms-card">
                <h3>Status Breakdown</h3>
                <p class="helper">Distribution of complaints by status across the entire organisation.</p>
                <?php if (empty($statusBreakdown)): ?>
                    <p style="color:#64748b;">No data available.</p>
                <?php else: ?>
                    <div>
                        <?php foreach ($statusBreakdown as $item): ?>
                            <?php
                                $statusKey = $item['label'];
                                $label = $statuses[$statusKey] ?? ucfirst($statusKey);
                                $count = (int) $item['count'];
                                $percent = $metrics['total'] > 0 ? ($count / $metrics['total']) * 100 : 0;
                            ?>
                            <div style="margin-bottom:14px;">
                                <div class="flex-between" style="margin-bottom:4px;">
                                    <strong><?php echo e($label); ?></strong>
                                    <span style="color:#64748b;"><?php echo number_format($percent, 1); ?>% · <?php echo $count; ?></span>
                                </div>
                                <div style="height:8px; border-radius:999px; background:#e2e8f0;">
                                    <div style="height:100%; border-radius:999px; background:#2563eb; width:<?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="cms-card">
                <h3>Priority Mix</h3>
                <p class="helper">Track workload severity to plan team allocations.</p>
                <?php if (empty($priorityBreakdown)): ?>
                    <p style="color:#64748b;">No data available.</p>
                <?php else: ?>
                    <div>
                        <?php foreach ($priorityBreakdown as $item): ?>
                            <?php
                                $priorityKey = $item['label'];
                                $label = $priorities[$priorityKey] ?? ucfirst($priorityKey);
                                $count = (int) $item['count'];
                                $percent = $metrics['total'] > 0 ? ($count / $metrics['total']) * 100 : 0;
                                $color = '#2563eb';
                                if ($priorityKey === 'high') $color = '#f97316';
                                if ($priorityKey === 'urgent') $color = '#ef4444';
                                if ($priorityKey === 'low') $color = '#0ea5e9';
                                if ($priorityKey === 'medium') $color = '#10b981';
                            ?>
                            <div style="margin-bottom:14px;">
                                <div class="flex-between" style="margin-bottom:4px;">
                                    <strong><?php echo e($label); ?></strong>
                                    <span style="color:#64748b;"><?php echo number_format($percent, 1); ?>% · <?php echo $count; ?></span>
                                </div>
                                <div style="height:8px; border-radius:999px; background:#e2e8f0;">
                                    <div style="height:100%; border-radius:999px; background:<?php echo $color; ?>; width:<?php echo $percent; ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var toggleButton = document.getElementById('toggleComplaintForm');
    var form = document.getElementById('newComplaintForm');
    if (!toggleButton || !form) {
        return;
    }

    var defaultLabel = toggleButton.innerHTML;

    toggleButton.addEventListener('click', function() {
        var isHidden = form.classList.contains('hidden');

        if (isHidden) {
            form.classList.remove('hidden');
            toggleButton.innerHTML = '− Close Form';
            setTimeout(function() {
                var firstField = form.querySelector('input, select, textarea');
                if (firstField) {
                    firstField.focus();
                }
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 50);
        } else {
            form.classList.add('hidden');
            toggleButton.innerHTML = defaultLabel;
        }
    });
});
</script>

<?php include 'footer.php'; ?>

<?php
function generateComplaintCode(PDO $pdo): string {
    $prefix = 'CMP-' . date('Ymd');
    $stmt = $pdo->prepare("SELECT complaint_code FROM complaints WHERE complaint_code LIKE ? ORDER BY complaint_code DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastCode = $stmt->fetchColumn();
    if ($lastCode) {
        $number = intval(substr($lastCode, -4)) + 1;
    } else {
        $number = 1;
    }
    return sprintf('%s-%04d', $prefix, $number);
}

function addComplaintUpdate(PDO $pdo, int $complaintId, array $data): void {
    $stmt = $pdo->prepare("
        INSERT INTO complaint_updates (
            complaint_id, update_type, update_text, internal_only,
            status_before, status_after, added_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $complaintId,
        $data['update_type'] ?? 'note',
        $data['update_text'] ?? '',
        $data['internal_only'] ?? 0,
        $data['status_before'] ?? null,
        $data['status_after'] ?? null,
        $data['added_by'] ?? null,
    ]);
}

function getComplaintMetrics(PDO $pdo, int $currentUserId): array {
    $metrics = [
        'total' => 0,
        'open' => 0,
        'overdue' => 0,
        'resolved_month' => 0,
        'my_open' => 0,
        'logged_today' => 0,
    ];

    try {
        $metrics['total'] = (int) $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
        $metrics['open'] = (int) $pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('new','triage','in_progress','awaiting_customer')")->fetchColumn();
        $metrics['overdue'] = (int) $pdo->query("
            SELECT COUNT(*) FROM complaints 
            WHERE due_date IS NOT NULL 
              AND due_date < CURDATE() 
              AND status NOT IN ('resolved','closed','cancelled')
        ")->fetchColumn();
        $metrics['resolved_month'] = (int) $pdo->query("
            SELECT COUNT(*) FROM complaints 
            WHERE status IN ('resolved','closed')
              AND resolved_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ")->fetchColumn();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE status IN ('new','triage','in_progress','awaiting_customer') AND (assigned_to = ? OR (assigned_to IS NULL AND created_by = ?))");
        $stmt->execute([$currentUserId, $currentUserId]);
        $metrics['my_open'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        $metrics['logged_today'] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        // Ignore errors so the dashboard still loads
    }

    return $metrics;
}

function getComplaintBreakdown(PDO $pdo, string $dimension): array {
    $allowed = [
        'status' => 'status',
        'priority' => 'priority',
    ];

    if (!isset($allowed[$dimension])) {
        return [];
    }

    $column = $allowed[$dimension];

    try {
        $stmt = $pdo->query("SELECT {$column} AS label, COUNT(*) AS count FROM complaints GROUP BY {$column} ORDER BY count DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function fetchComplaints(PDO $pdo, array $filters, int $currentUserId, ?int $limit = 200): array {
    $whereConditions = [];
    $params = [];

    if ($filters['status'] !== 'all') {
        $whereConditions[] = "c.status = ?";
        $params[] = $filters['status'];
    }

    if ($filters['priority'] !== 'all') {
        $whereConditions[] = "c.priority = ?";
        $params[] = $filters['priority'];
    }

    if ($filters['channel'] !== 'all') {
        $whereConditions[] = "c.channel = ?";
        $params[] = $filters['channel'];
    }

    if ($filters['assigned'] === 'mine') {
        $whereConditions[] = "(c.assigned_to = ? OR (c.assigned_to IS NULL AND c.created_by = ?))";
        $params[] = $currentUserId;
        $params[] = $currentUserId;
    } elseif ($filters['assigned'] === 'unassigned') {
        $whereConditions[] = "c.assigned_to IS NULL";
    }

    if (!empty($filters['search'])) {
        $whereConditions[] = "(c.complaint_code LIKE ? OR c.summary LIKE ? OR c.customer_name LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }

    $where = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

    $limitClause = $limit ? "LIMIT " . intval($limit) : '';

    $sql = "
        SELECT c.*, u.full_name AS assigned_name
        FROM complaints c
        LEFT JOIN users u ON u.id = c.assigned_to
        $where
        ORDER BY c.created_at DESC
        $limitClause
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildExportUrl(string $type): string {
    $query = $_GET;
    $query['export'] = $type;
    return 'complaints.php?' . http_build_query($query);
}

