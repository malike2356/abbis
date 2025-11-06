<?php
/**
 * CRM Email Management
 */
$pdo = getDBConnection();

// Get filters
$clientFilter = intval($_GET['client_id'] ?? 0);
$directionFilter = $_GET['direction'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Build query
$where = [];
$params = [];

if ($clientFilter > 0) {
    $where[] = "ce.client_id = ?";
    $params[] = $clientFilter;
}

if (!empty($directionFilter)) {
    $where[] = "ce.direction = ?";
    $params[] = $directionFilter;
}

if (!empty($statusFilter)) {
    $where[] = "ce.status = ?";
    $params[] = $statusFilter;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get emails
try {
    $sql = "
        SELECT ce.*, c.client_name, u.full_name as sender_name
        FROM client_emails ce
        LEFT JOIN clients c ON ce.client_id = c.id
        LEFT JOIN users u ON ce.created_by = u.id
        $whereClause
        ORDER BY ce.created_at DESC
        LIMIT 50
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $emails = $stmt->fetchAll();
} catch (PDOException $e) {
    $emails = [];
}

// Get clients
try {
    $stmt = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0;">📧 Email Communications</h2>
    <button onclick="showEmailModal()" class="btn btn-primary">✉️ Send Email</button>
</div>

<!-- Filters -->
<div class="dashboard-card" style="margin-bottom: 20px;">
    <form method="GET" style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
        <input type="hidden" name="action" value="emails">
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
            <label class="form-label">Direction</label>
            <select name="direction" class="form-control">
                <option value="">All</option>
                <option value="inbound" <?php echo $directionFilter === 'inbound' ? 'selected' : ''; ?>>Inbound</option>
                <option value="outbound" <?php echo $directionFilter === 'outbound' ? 'selected' : ''; ?>>Outbound</option>
            </select>
        </div>
        <div>
            <label class="form-label">Status</label>
            <select name="status" class="form-control">
                <option value="">All</option>
                <option value="sent" <?php echo $statusFilter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="?action=emails" class="btn btn-outline">Clear</a>
        </div>
    </form>
</div>

<!-- Emails List -->
<div class="dashboard-card">
    <?php if (empty($emails)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No emails found. <a href="#" onclick="showEmailModal(); return false;">Send your first email</a>
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Direction</th>
                        <th>Subject</th>
                        <th>From/To</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($emails as $email): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($email['created_at'])); ?></td>
                        <td>
                            <a href="?action=client-detail&client_id=<?php echo $email['client_id']; ?>" style="color: var(--primary); text-decoration: none;">
                                <?php echo e($email['client_name']); ?>
                            </a>
                        </td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                background: <?php echo $email['direction'] === 'outbound' ? '#dbeafe' : '#f3e8ff'; ?>; 
                                color: <?php echo $email['direction'] === 'outbound' ? '#1e40af' : '#6b21a8'; ?>;">
                                <?php echo $email['direction'] === 'outbound' ? '→ Out' : '← In'; ?>
                            </span>
                        </td>
                        <td><?php echo e($email['subject']); ?></td>
                        <td>
                            <small>
                                <?php if ($email['direction'] === 'outbound'): ?>
                                    To: <?php echo e($email['to_email']); ?>
                                <?php else: ?>
                                    From: <?php echo e($email['from_email']); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>
                            <?php
                            $statusColors = [
                                'sent' => '#10b981',
                                'delivered' => '#10b981',
                                'draft' => '#f59e0b',
                                'failed' => '#ef4444',
                                'opened' => '#3b82f6',
                                'replied' => '#8b5cf6'
                            ];
                            $color = $statusColors[$email['status']] ?? '#64748b';
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo ucfirst($email['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="viewEmail(<?php echo $email['id']; ?>)" class="btn btn-sm btn-outline">View</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function showEmailModal() {
    window.location.href = '?action=emails&compose=1';
}

function viewEmail(id) {
    window.location.href = '?action=emails&id=' + id;
}
</script>

