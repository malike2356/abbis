<?php
/**
 * Complaints & Feedback Management
 * Phase 3 – Operational Modules
 */

$page_title = 'Complaints & Feedback';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/functions.php';
require_once '../includes/tab-navigation.php';

$auth->requireAuth();
$auth->requirePermission('complaints.manage');

$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];
$complaintsTablesReady = true;

// Ensure migration executed
try {
    $pdo->query("SELECT 1 FROM complaints LIMIT 1");
} catch (Throwable $e) {
    $complaintsTablesReady = false;
    flash('warning', 'Complaints database tables are missing. Run <code>database/complaints_module_migration.sql</code>.');
}

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
                    $currentUserId,
                ]);

                $complaintId = $pdo->lastInsertId();

                $note = trim($_POST['initial_note'] ?? '');
                if (!empty($note)) {
                    addComplaintUpdate($pdo, $complaintId, [
                        'update_type' => 'note',
                        'update_text' => $note,
                        'added_by' => $currentUserId,
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
                    'added_by' => $currentUserId,
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
                $stmt->execute([$newStatus, $currentUserId, $newStatus, $newStatus, $complaintId]);

                $comment = trim($_POST['status_comment'] ?? '');
                addComplaintUpdate($pdo, $complaintId, [
                    'update_type' => 'status_change',
                    'status_before' => $currentStatus,
                    'status_after' => $newStatus,
                    'update_text' => $comment,
                    'added_by' => $currentUserId,
                ]);

                flash('success', 'Status updated');
                redirect('complaints.php?action=view&id=' . $complaintId);
                break;

            case 'assign':
                $complaintId = intval($_POST['complaint_id'] ?? 0);
                $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;

                $stmt = $pdo->prepare("SELECT status, assigned_to FROM complaints WHERE id = ?");
                $stmt->execute([$complaintId]);
                $row = $stmt->fetch();
                if (!$row) {
                    throw new Exception('Complaint not found');
                }

                $stmt = $pdo->prepare("UPDATE complaints SET assigned_to = ?, updated_by = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$assignedTo, $currentUserId, $complaintId]);

                $assignmentNote = trim($_POST['assignment_note'] ?? '');
                addComplaintUpdate($pdo, $complaintId, [
                    'update_type' => 'assignment',
                    'update_text' => $assignmentNote,
                    'added_by' => $currentUserId,
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

$users = [];
try {
    $users = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name")->fetchAll();
} catch (Throwable $e) {
    $users = [];
}

if ($complaintsTablesReady && $action === 'view') {
    $complaintId = intval($_GET['id'] ?? 0);
    $complaint = null;
    $updates = [];

    if ($complaintId > 0) {
        $stmt = $pdo->prepare("
            SELECT c.*, u.full_name AS assigned_name, creator.full_name AS created_name
            FROM complaints c
            LEFT JOIN users u ON u.id = c.assigned_to
            LEFT JOIN users creator ON creator.id = c.created_by
            WHERE c.id = ?
        ");
        $stmt->execute([$complaintId]);
        $complaint = $stmt->fetch();

        if ($complaint) {
            $stmt = $pdo->prepare("
                SELECT cu.*, u.full_name 
                FROM complaint_updates cu
                LEFT JOIN users u ON u.id = cu.added_by
                WHERE cu.complaint_id = ?
                ORDER BY cu.created_at DESC
            ");
            $stmt->execute([$complaintId]);
            $updates = $stmt->fetchAll();
        }
    }

    if (!$complaint) {
        flash('error', 'Complaint not found');
        redirect('complaints.php');
    }
}

// Dashboard metrics
$metrics = $complaintsTablesReady ? getComplaintMetrics($pdo) : ['total' => 0, 'open' => 0, 'overdue' => 0, 'resolved_month' => 0];
$complaintList = ($complaintsTablesReady && $action === 'overview') ? fetchComplaints($pdo, $filters, $currentUserId) : [];

require_once '../includes/header.php';
?>

<?php
$crmTabs = [
    [ 'label' => '📊 Dashboard', 'url' => 'crm.php?action=dashboard', 'active' => false ],
    [ 'label' => '👥 Clients', 'url' => 'crm.php?action=clients', 'active' => false ],
    [ 'label' => '📅 Follow-ups', 'url' => 'crm.php?action=followups', 'active' => false ],
    [ 'label' => '⚠️ Complaints', 'url' => 'complaints.php', 'active' => true ],
    [ 'label' => '💰 Quote Requests', 'url' => 'crm.php?action=quote-requests', 'active' => false ],
    [ 'label' => '🚛 Rig Requests', 'url' => 'crm.php?action=rig-requests', 'active' => false ],
    [ 'label' => '📧 Emails', 'url' => 'crm.php?action=emails', 'active' => false ],
    [ 'label' => '📝 Templates', 'url' => 'crm.php?action=templates', 'active' => false ],
];
?>

<div class="config-tabs" style="margin-bottom: 30px;">
    <div class="tabs">
        <?php foreach ($crmTabs as $tab): ?>
            <button type="button"
                    class="tab <?php echo $tab['active'] ? 'active' : ''; ?>"
                    onclick="window.location.href='<?php echo e($tab['url']); ?>'">
                <span><?php echo e($tab['label']); ?></span>
            </button>
        <?php endforeach; ?>
    </div>
</div>

<style>
.mini-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
}
.mini-card h4 {
    margin: 0;
    font-size: 13px;
    text-transform: uppercase;
    color: var(--secondary);
    letter-spacing: 0.6px;
}
.mini-card p {
    margin: 6px 0 0 0;
    font-size: 16px;
    font-weight: 600;
    color: var(--text);
}
.hidden { display: none; }
.detail-stack {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
}
.detail-stack span {
    color: var(--secondary);
    margin-right: 6px;
    font-weight: 500;
}
.description-box {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    background: var(--bg);
    min-height: 80px;
    font-size: 14px;
    color: var(--text);
}
.timeline {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.timeline-item {
    display: grid;
    grid-template-columns: 32px 1fr;
    gap: 12px;
}
.timeline-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
}
.timeline-content {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 12px 16px;
    background: var(--card);
}
.timeline-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    margin-bottom: 6px;
}
.timeline-header span {
    color: var(--secondary);
    font-size: 12px;
}
.timeline-note {
    background: rgba(37, 99, 235, 0.08);
    padding: 10px 12px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--text);
    margin-top: 8px;
    white-space: pre-wrap;
}
.grid-two {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
}

.complaint-form {
    margin-top: 20px;
}
</style>

<div class="container-fluid">
    <nav aria-label="breadcrumb" style="margin-bottom: 12px;">
        <div style="display:inline-block; padding:6px 10px; border:1px solid var(--border); background: var(--bg); border-radius: 6px; font-size: 13px; color: var(--text);">
            <span>Operations</span> <span style="opacity:0.6;">→</span> <span>Complaints & Feedback</span>
        </div>
    </nav>

    <?php $flash = getFlash(); if ($flash): ?>
        <div class="alert alert-<?php echo $flash['type']; ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$complaintsTablesReady): ?>
        <div class="alert alert-warning">
            Complaints tables not initialized. Please run <code>database/complaints_module_migration.sql</code> and refresh.
        </div>
    <?php elseif ($action === 'view' && isset($complaint)): ?>
        <?php include __DIR__ . '/complaints_view.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/complaints_overview.php'; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

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

<?php
// Helper functions
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
        INSERT INTO complaint_updates
        (complaint_id, update_type, status_before, status_after, update_text, internal_only, added_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $complaintId,
        $data['update_type'] ?? 'note',
        $data['status_before'] ?? null,
        $data['status_after'] ?? null,
        $data['update_text'] ?? null,
        !empty($data['internal_only']) ? 1 : 0,
        $data['added_by'] ?? null,
    ]);
}

function getComplaintMetrics(PDO $pdo): array {
    $metrics = [
        'total' => 0,
        'open' => 0,
        'overdue' => 0,
        'resolved_month' => 0,
    ];

    try {
        $metrics['total'] = (int)$pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn();
        $metrics['open'] = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('resolved','closed','cancelled')")->fetchColumn();
        $metrics['overdue'] = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('resolved','closed','cancelled') AND due_date IS NOT NULL AND due_date < CURDATE()")->fetchColumn();
        $metrics['resolved_month'] = (int)$pdo->query("
            SELECT COUNT(*) FROM complaints 
            WHERE status IN ('resolved','closed') AND DATE_FORMAT(updated_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
        ")->fetchColumn();
    } catch (Throwable $e) {}

    return $metrics;
}

function fetchComplaints(PDO $pdo, array $filters, int $currentUserId): array {
    $conditions = [];
    $params = [];

    if (!empty($filters['status']) && $filters['status'] !== 'all') {
        $conditions[] = 'c.status = ?';
        $params[] = $filters['status'];
    }

    if (!empty($filters['priority']) && $filters['priority'] !== 'all') {
        $conditions[] = 'c.priority = ?';
        $params[] = $filters['priority'];
    }

    if (!empty($filters['channel']) && $filters['channel'] !== 'all') {
        $conditions[] = 'c.channel = ?';
        $params[] = $filters['channel'];
    }

    if ($filters['assigned'] === 'mine') {
        $conditions[] = '(c.assigned_to = ? OR (c.assigned_to IS NULL AND c.created_by = ?))';
        $params[] = $currentUserId;
        $params[] = $currentUserId;
    } elseif ($filters['assigned'] === 'unassigned') {
        $conditions[] = 'c.assigned_to IS NULL';
    }

    if (!empty($filters['search'])) {
        $conditions[] = "(c.complaint_code LIKE ? OR c.customer_name LIKE ? OR c.summary LIKE ?)";
        $search = '%' . $filters['search'] . '%';
        $params[] = $search;
        $params[] = $search;
        $params[] = $search;
    }

    $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "
        SELECT c.*, u.full_name AS assigned_name
        FROM complaints c
        LEFT JOIN users u ON u.id = c.assigned_to
        $where
        ORDER BY c.created_at DESC
        LIMIT 150
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

