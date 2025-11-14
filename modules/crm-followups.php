<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

/**
 * CRM Follow-ups Management
 */
$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];

// Get filters
$statusFilter = $_GET['status'] ?? '';
$clientFilter = intval($_GET['client_id'] ?? 0);
$assignedFilter = intval($_GET['assigned'] ?? 0);
$dateFilter = $_GET['date'] ?? '';

// Build query
$where = [];
$params = [];

if (!empty($statusFilter)) {
    $where[] = "cf.status = ?";
    $params[] = $statusFilter;
} else {
    // Default: show scheduled
    $where[] = "cf.status = 'scheduled'";
}

if ($clientFilter > 0) {
    $where[] = "cf.client_id = ?";
    $params[] = $clientFilter;
}

if ($assignedFilter > 0) {
    $where[] = "cf.assigned_to = ?";
    $params[] = $assignedFilter;
}

if ($dateFilter === 'today') {
    $where[] = "DATE(cf.scheduled_date) = CURDATE()";
} elseif ($dateFilter === 'week') {
    $where[] = "cf.scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)";
} elseif ($dateFilter === 'overdue') {
    $where[] = "cf.scheduled_date < NOW() AND cf.status = 'scheduled'";
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : 'WHERE cf.status = "scheduled"';

// Get follow-ups
try {
    $sql = "
        SELECT cf.*, c.client_name, cc.name as contact_name, u.full_name as assigned_name, creator.full_name as creator_name
        FROM client_followups cf
        LEFT JOIN clients c ON cf.client_id = c.id
        LEFT JOIN client_contacts cc ON cf.contact_id = cc.id
        LEFT JOIN users u ON cf.assigned_to = u.id
        LEFT JOIN users creator ON cf.created_by = creator.id
        $whereClause
        ORDER BY cf.scheduled_date ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $followups = $stmt->fetchAll();
} catch (PDOException $e) {
    $followups = [];
}

// Get clients for filter
try {
    $stmt = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
}

// Get users
try {
    $stmt = $pdo->query("SELECT id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
}
?>

<script>
// Ensure CRM Follow-ups respects saved theme and toggle
(function(){
    try {
        var saved = localStorage.getItem('abbis-theme');
        if (saved) {
            document.documentElement.setAttribute('data-theme', saved);
            document.body && document.body.setAttribute('data-theme', saved);
        }
        if (window.abbisApp && typeof window.abbisApp.initializeTheme === 'function') {
            window.abbisApp.initializeTheme();
        }
    } catch (e) {}
})();
</script>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0; color: var(--text);">📅 Follow-ups & Tasks</h2>
    <button onclick="showFollowupModal()" class="btn btn-primary">➕ Schedule Follow-up</button>
</div>

<!-- Filters -->
<div class="dashboard-card" style="margin-bottom: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr auto; gap: 15px; align-items: end;">
        <input type="hidden" name="action" value="followups">
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">Scheduled</option>
                <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                <option value="postponed" <?php echo $statusFilter === 'postponed' ? 'selected' : ''; ?>>Postponed</option>
            </select>
        </div>
        <div>
            <label class="form-label">Client</label>
            <select name="client_id" class="form-control">
                <option value="">All</option>
                <?php foreach ($clients as $client): ?>
                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter === $client['id'] ? 'selected' : ''; ?>>
                        <?php echo e($client['client_name']); ?>
                    </option>
                <?php endforeach; ?>
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
            <label class="form-label">Date</label>
            <select name="date" class="form-control">
                <option value="">All</option>
                <option value="today" <?php echo $dateFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                <option value="week" <?php echo $dateFilter === 'week' ? 'selected' : ''; ?>>This Week</option>
                <option value="overdue" <?php echo $dateFilter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="?action=followups" class="btn btn-outline">Clear</a>
        </div>
    </form>
</div>

<!-- Follow-ups List -->
<div class="dashboard-card">
    <?php if (empty($followups)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No follow-ups found. <a href="#" onclick="showFollowupModal(); return false;" style="color: var(--primary);">Schedule your first follow-up</a>
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Client</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Priority</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($followups as $followup): ?>
                    <?php
                    $isOverdue = strtotime($followup['scheduled_date']) < time() && $followup['status'] === 'scheduled';
                    $rowStyle = $isOverdue ? 'background: rgba(239, 68, 68, 0.1);' : '';
                    ?>
                    <tr style="<?php echo $rowStyle; ?>">
                        <td style="color: var(--text);">
                            <?php echo date('M j, Y', strtotime($followup['scheduled_date'])); ?><br>
                            <small style="color: var(--secondary);"><?php echo date('g:i A', strtotime($followup['scheduled_date'])); ?></small>
                        </td>
                        <td>
                            <a href="?action=client-detail&client_id=<?php echo $followup['client_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                <?php echo e($followup['client_name']); ?>
                            </a>
                        </td>
                        <td style="color: var(--text);"><?php echo e($followup['contact_name'] ?? 'N/A'); ?></td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: rgba(14, 165, 233, 0.2); color: var(--primary);">
                                <?php echo ucfirst($followup['type']); ?>
                            </span>
                        </td>
                        <td style="color: var(--text);"><?php echo e($followup['subject']); ?></td>
                        <td>
                            <?php
                            $priorityColors = [
                                'low' => ['bg' => 'rgba(16, 185, 129, 0.2)', 'fg' => '#10b981'],
                                'medium' => ['bg' => 'rgba(245, 158, 11, 0.2)', 'fg' => '#f59e0b'],
                                'high' => ['bg' => 'rgba(239, 68, 68, 0.2)', 'fg' => '#ef4444'],
                                'urgent' => ['bg' => 'rgba(220, 38, 38, 0.2)', 'fg' => '#dc2626']
                            ];
                            $priorityStyle = $priorityColors[$followup['priority']] ?? ['bg' => 'rgba(100, 116, 139, 0.2)', 'fg' => '#64748b'];
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $priorityStyle['bg']; ?>; color: <?php echo $priorityStyle['fg']; ?>;">
                                <?php echo ucfirst($followup['priority']); ?>
                            </span>
                        </td>
                        <td style="color: var(--text);"><?php echo e($followup['assigned_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <?php
                            $statusColors = [
                                'scheduled' => ['bg' => 'rgba(59, 130, 246, 0.2)', 'fg' => '#3b82f6'],
                                'completed' => ['bg' => 'rgba(16, 185, 129, 0.2)', 'fg' => '#10b981'],
                                'cancelled' => ['bg' => 'rgba(100, 116, 139, 0.2)', 'fg' => 'var(--secondary)'],
                                'postponed' => ['bg' => 'rgba(245, 158, 11, 0.2)', 'fg' => '#f59e0b']
                            ];
                            $statusStyle = $statusColors[$followup['status']] ?? ['bg' => 'rgba(100, 116, 139, 0.2)', 'fg' => 'var(--secondary)'];
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $statusStyle['bg']; ?>; color: <?php echo $statusStyle['fg']; ?>;">
                                <?php echo ucfirst($followup['status']); ?>
                            </span>
                            <?php if ($isOverdue): ?>
                                <span style="color: var(--danger); font-size: 11px; display: block;">⚠ Overdue</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($followup['status'] === 'scheduled'): ?>
                                <button onclick="completeFollowup(<?php echo $followup['id']; ?>)" class="btn btn-sm btn-success">Complete</button>
                            <?php endif; ?>
                            <button onclick="viewFollowup(<?php echo $followup['id']; ?>)" class="btn btn-sm btn-outline">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Follow-up Modal (simplified - full implementation in JS) -->
<script>
function showFollowupModal() {
    window.location.href = '?action=followups&add=1';
}

function completeFollowup(followupId) {
    if (confirm('Mark this follow-up as completed?')) {
        const outcome = prompt('Enter outcome/notes (optional):');
        
        fetch('../api/crm-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'complete_followup',
                followup_id: followupId,
                outcome: outcome || '',
                csrf_token: '<?php echo CSRF::getToken(); ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error completing follow-up');
            }
        });
    }
}

function viewFollowup(id) {
    window.location.href = '?action=followups&id=' + id;
}
</script>

