<?php
/**
 * CRM Dashboard
 */
// Use $pdo from router if available, otherwise get new connection
if (!isset($pdo) || !$pdo) {
    $pdo = getDBConnection();
}
if (!isset($currentUserId)) {
    $currentUserId = $_SESSION['user_id'] ?? 0;
}

// Get CRM statistics
try {
    // Total clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients");
    $totalClients = $stmt->fetch()['total'];
    
    // Active clients
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clients WHERE status IN ('active', 'customer')");
    $activeClients = $stmt->fetch()['total'];
    
    // Upcoming follow-ups (next 7 days)
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM client_followups 
        WHERE scheduled_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
        AND status = 'scheduled'
    ");
    $upcomingFollowups = $stmt->fetch()['total'];
    
    // Overdue follow-ups
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM client_followups 
        WHERE scheduled_date < NOW()
        AND status = 'scheduled'
    ");
    $overdueFollowups = $stmt->fetch()['total'];
    
    // Recent emails
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM client_emails 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $recentEmails = $stmt->fetch()['total'];
    
    // New leads this month
    $stmt = $pdo->query("
        SELECT COUNT(*) as total 
        FROM clients 
        WHERE status = 'lead' 
        AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $newLeads = $stmt->fetch()['total'];
} catch (PDOException $e) {
    $totalClients = $activeClients = $upcomingFollowups = $overdueFollowups = $recentEmails = $newLeads = 0;
}

// Get upcoming follow-ups
try {
    $stmt = $pdo->prepare("
        SELECT cf.*, c.client_name, u.full_name as assigned_name
        FROM client_followups cf
        LEFT JOIN clients c ON cf.client_id = c.id
        LEFT JOIN users u ON cf.assigned_to = u.id
        WHERE cf.scheduled_date >= NOW()
        AND cf.status = 'scheduled'
        ORDER BY cf.scheduled_date ASC
        LIMIT 10
    ");
    $stmt->execute();
    $upcomingFollowupsList = $stmt->fetchAll();
} catch (PDOException $e) {
    $upcomingFollowupsList = [];
}

// Get recent activities
try {
    $stmt = $pdo->prepare("
        SELECT ca.*, c.client_name, u.full_name as creator_name
        FROM client_activities ca
        LEFT JOIN clients c ON ca.client_id = c.id
        LEFT JOIN users u ON ca.created_by = u.id
        ORDER BY ca.created_at DESC
        LIMIT 15
    ");
    $stmt->execute();
    $recentActivities = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentActivities = [];
}
?>

<div class="dashboard-grid">
    <!-- CRM Stats -->
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <span style="font-size: 32px;">👥</span>
            <div>
                <h2 style="margin: 0;">Total Clients</h2>
                <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">All registered clients</p>
            </div>
        </div>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary);">
            <?php echo number_format($totalClients); ?>
        </div>
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
            <span style="color: var(--success);">✓ <?php echo $activeClients; ?> Active</span>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <span style="font-size: 32px;">📅</span>
            <div>
                <h2 style="margin: 0;">Upcoming Follow-ups</h2>
                <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Next 7 days</p>
            </div>
        </div>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary);">
            <?php echo number_format($upcomingFollowups); ?>
        </div>
        <?php if ($overdueFollowups > 0): ?>
        <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border);">
            <span style="color: var(--danger);">⚠ <?php echo $overdueFollowups; ?> Overdue</span>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <span style="font-size: 32px;">📧</span>
            <div>
                <h2 style="margin: 0;">Recent Emails</h2>
                <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">Last 7 days</p>
            </div>
        </div>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary);">
            <?php echo number_format($recentEmails); ?>
        </div>
    </div>
    
    <div class="dashboard-card">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <span style="font-size: 32px;">🎯</span>
            <div>
                <h2 style="margin: 0;">New Leads</h2>
                <p style="margin: 4px 0 0 0; color: var(--secondary); font-size: 14px;">This month</p>
            </div>
        </div>
        <div style="font-size: 36px; font-weight: bold; color: var(--primary);">
            <?php echo number_format($newLeads); ?>
        </div>
    </div>
</div>

<!-- Upcoming Follow-ups -->
<div class="dashboard-card" style="margin-top: 30px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2>📅 Upcoming Follow-ups</h2>
        <a href="?action=followups" class="btn btn-outline">View All</a>
    </div>
    
    <?php if (empty($upcomingFollowupsList)): ?>
        <p style="color: var(--secondary); text-align: center; padding: 40px;">
            No upcoming follow-ups scheduled
        </p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Assigned To</th>
                        <th>Priority</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingFollowupsList as $followup): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($followup['scheduled_date'])); ?></td>
                        <td><?php echo e($followup['client_name'] ?? 'N/A'); ?></td>
                        <td>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #e0f2fe; color: #0369a1;">
                                <?php echo ucfirst($followup['type']); ?>
                            </span>
                        </td>
                        <td><?php echo e($followup['subject']); ?></td>
                        <td><?php echo e($followup['assigned_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <?php
                            $priorityColors = [
                                'low' => '#10b981',
                                'medium' => '#f59e0b',
                                'high' => '#ef4444',
                                'urgent' => '#dc2626'
                            ];
                            $color = $priorityColors[$followup['priority']] ?? '#64748b';
                            ?>
                            <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                <?php echo ucfirst($followup['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="?action=followups&id=<?php echo $followup['id']; ?>" class="btn btn-sm btn-outline">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div class="dashboard-card" style="margin-top: 30px;">
    <h2 style="margin-bottom: 20px; color: var(--text);">📊 Quick Actions</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <a href="materials.php" class="btn btn-primary" style="text-align: center; padding: 12px;" onclick="handleAddMaterial(event);">
            📦 Add Material
        </a>
        <a href="?action=clients" class="btn btn-outline" style="text-align: center; padding: 12px;">
            👥 Add Client
        </a>
        <a href="?action=followups" class="btn btn-outline" style="text-align: center; padding: 12px;">
            📅 Schedule Follow-up
        </a>
        <a href="?action=emails" class="btn btn-outline" style="text-align: center; padding: 12px;">
            📧 Send Email
        </a>
    </div>
</div>

<!-- Recent Activities -->
<div class="dashboard-card" style="margin-top: 30px;">
    <h2>📋 Recent Activities</h2>
    
    <?php if (empty($recentActivities)): ?>
        <p style="color: var(--secondary); text-align: center; padding: 40px;">
            No recent activities
        </p>
    <?php else: ?>
        <div style="margin-top: 20px;">
            <?php foreach ($recentActivities as $activity): ?>
            <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: start;">
                <div style="flex-shrink: 0;">
                    <?php
                    $icons = [
                        'note' => '📝',
                        'call' => '📞',
                        'email' => '📧',
                        'meeting' => '🤝',
                        'document' => '📄',
                        'status_change' => '🔄',
                        'update' => '✏️',
                        'system' => '⚙️'
                    ];
                    echo $icons[$activity['type']] ?? '📌';
                    ?>
                </div>
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 4px;">
                        <?php echo e($activity['title']); ?>
                    </div>
                    <div style="font-size: 13px; color: var(--secondary); margin-bottom: 4px;">
                        <?php echo e($activity['client_name']); ?> • <?php echo e($activity['creator_name']); ?>
                    </div>
                    <?php if ($activity['description']): ?>
                    <div style="font-size: 13px; color: var(--secondary);">
                        <?php echo e(substr($activity['description'], 0, 150)); ?>
                        <?php if (strlen($activity['description']) > 150): ?>...<?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div style="flex-shrink: 0; font-size: 12px; color: var(--secondary);">
                    <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Handle Add Material button click
function handleAddMaterial(event) {
    // Check if we're already on materials page - if so, open the modal
    if (window.location.pathname.includes('materials.php')) {
        event.preventDefault();
        if (typeof window.openAddModal === 'function') {
            window.openAddModal();
        } else {
            // Fallback: just navigate normally
            window.location.href = 'materials.php';
        }
    }
    // Otherwise, let the link work normally
}

// Make function globally available
window.handleAddMaterial = handleAddMaterial;

// Also handle any button with onclick="openAddModal()" that might exist
if (typeof window.openAddModal === 'undefined') {
    window.openAddModal = function() {
        window.location.href = 'materials.php';
    };
}
</script>

