<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

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
    
    // New quote requests
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM cms_quote_requests WHERE status = 'new'");
        $newQuoteRequests = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        $newQuoteRequests = 0;
    }
    
    // New rig requests
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM rig_requests WHERE status = 'new'");
        $newRigRequests = $stmt->fetch()['total'];
    } catch (PDOException $e) {
        $newRigRequests = 0;
    }
} catch (PDOException $e) {
    $totalClients = $activeClients = $upcomingFollowups = $overdueFollowups = $recentEmails = $newLeads = 0;
    $newQuoteRequests = $newRigRequests = 0;
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

// Get recent quote requests
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, c.client_name
        FROM cms_quote_requests qr
        LEFT JOIN clients c ON qr.converted_to_client_id = c.id
        ORDER BY qr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentQuoteRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentQuoteRequests = [];
}

// Get recent rig requests
try {
    $stmt = $pdo->prepare("
        SELECT rr.*, c.client_name, r.rig_name
        FROM rig_requests rr
        LEFT JOIN clients c ON rr.client_id = c.id
        LEFT JOIN rigs r ON rr.assigned_rig_id = r.id
        ORDER BY rr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentRigRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    $recentRigRequests = [];
}
?>

<!-- CRM Statistics - 4 Column Grid -->
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px;">
    <!-- Total Clients -->
    <div class="dashboard-card" style="padding: 24px; text-align: center; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
        <div style="font-size: 48px; margin-bottom: 12px;">👥</div>
        <div style="font-size: 42px; font-weight: bold; color: var(--primary); margin-bottom: 8px;">
            <?php echo number_format($totalClients); ?>
        </div>
        <div style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Total Clients</div>
        <div style="font-size: 13px; color: var(--secondary);">
            <span style="color: var(--success);">✓ <?php echo $activeClients; ?> Active</span>
        </div>
    </div>
    
    <!-- Upcoming Follow-ups -->
    <div class="dashboard-card" style="padding: 24px; text-align: center; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
        <div style="font-size: 48px; margin-bottom: 12px;">📅</div>
        <div style="font-size: 42px; font-weight: bold; color: var(--primary); margin-bottom: 8px;">
            <?php echo number_format($upcomingFollowups); ?>
        </div>
        <div style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Upcoming Follow-ups</div>
        <div style="font-size: 13px; color: var(--secondary);">
            <?php if ($overdueFollowups > 0): ?>
                <span style="color: var(--danger);">⚠ <?php echo $overdueFollowups; ?> Overdue</span>
            <?php else: ?>
                Next 7 days
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Emails -->
    <div class="dashboard-card" style="padding: 24px; text-align: center; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
        <div style="font-size: 48px; margin-bottom: 12px;">📧</div>
        <div style="font-size: 42px; font-weight: bold; color: var(--primary); margin-bottom: 8px;">
            <?php echo number_format($recentEmails); ?>
        </div>
        <div style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px;">Recent Emails</div>
        <div style="font-size: 13px; color: var(--secondary);">Last 7 days</div>
    </div>
    
    <!-- New Leads -->
    <div class="dashboard-card" style="padding: 24px; text-align: center; border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s;" onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'" onmouseout="this.style.transform=''; this.style.boxShadow=''">
        <div style="font-size: 48px; margin-bottom: 12px;">🎯</div>
        <div style="font-size: 42px; font-weight: bold; color: var(--primary); margin-bottom: 8px;">
            <?php echo number_format($newLeads); ?>
        </div>
        <div style="font-size: 16px; font-weight: 600; color: var(--text); margin-bottom: 6px;">New Leads</div>
        <div style="font-size: 13px; color: var(--secondary);">This month</div>
    </div>
</div>

<style>
@media (max-width: 1200px) {
    div[style*="grid-template-columns: repeat(4"] {
        grid-template-columns: repeat(2, 1fr) !important;
    }
}
@media (max-width: 768px) {
    div[style*="grid-template-columns: repeat(4"] {
        grid-template-columns: 1fr !important;
    }
}
</style>

<!-- Upcoming Follow-ups -->
<div class="dashboard-card" style="margin-top: 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border);">
        <h2 style="margin: 0; font-size: 20px; font-weight: 600;">📅 Upcoming Follow-ups</h2>
        <a href="?action=followups" class="btn btn-outline" style="font-size: 13px; padding: 8px 16px;">View All →</a>
    </div>
    
    <?php if (empty($upcomingFollowupsList)): ?>
        <div style="text-align: center; padding: 60px 20px; color: var(--secondary);">
            <div style="font-size: 48px; margin-bottom: 16px; opacity: 0.5;">📅</div>
            <p style="font-size: 16px; margin: 0;">No upcoming follow-ups scheduled</p>
            <a href="?action=followups" class="btn btn-primary" style="margin-top: 16px; display: inline-block;">Schedule Follow-up</a>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="data-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--bg);">
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Date/Time</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Client</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Type</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Subject</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Assigned To</th>
                        <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Priority</th>
                        <th style="padding: 12px; text-align: center; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcomingFollowupsList as $followup): ?>
                    <tr style="border-bottom: 1px solid var(--border); transition: background 0.2s;" onmouseover="this.style.background='var(--bg)'" onmouseout="this.style.background='transparent'">
                        <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                            <div style="font-weight: 500;"><?php echo date('M j, Y', strtotime($followup['scheduled_date'])); ?></div>
                            <div style="font-size: 12px; color: var(--secondary);"><?php echo date('g:i A', strtotime($followup['scheduled_date'])); ?></div>
                        </td>
                        <td style="padding: 14px 12px; font-size: 13px; color: var(--text); font-weight: 500;">
                            <?php echo e($followup['client_name'] ?? 'N/A'); ?>
                        </td>
                        <td style="padding: 14px 12px;">
                            <span style="padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: #e0f2fe; color: #0369a1; display: inline-block;">
                                <?php echo ucfirst($followup['type']); ?>
                            </span>
                        </td>
                        <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                            <?php echo e($followup['subject']); ?>
                        </td>
                        <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                            <?php echo e($followup['assigned_name'] ?? '<span style="color: var(--secondary);">Unassigned</span>'); ?>
                        </td>
                        <td style="padding: 14px 12px;">
                            <?php
                            $priorityColors = [
                                'low' => ['bg' => '#d1fae5', 'text' => '#065f46'],
                                'medium' => ['bg' => '#fef3c7', 'text' => '#92400e'],
                                'high' => ['bg' => '#fee2e2', 'text' => '#991b1b'],
                                'urgent' => ['bg' => '#fecaca', 'text' => '#7f1d1d']
                            ];
                            $priority = $priorityColors[$followup['priority']] ?? ['bg' => '#f1f5f9', 'text' => '#64748b'];
                            ?>
                            <span style="padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?php echo $priority['bg']; ?>; color: <?php echo $priority['text']; ?>; display: inline-block;">
                                <?php echo ucfirst($followup['priority']); ?>
                            </span>
                        </td>
                        <td style="padding: 14px 12px; text-align: center;">
                            <a href="?action=followups&id=<?php echo $followup['id']; ?>" class="btn btn-sm btn-outline" style="font-size: 12px; padding: 6px 12px;">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Quote Requests -->
<?php if (!empty($recentQuoteRequests)): ?>
<div class="dashboard-card" style="margin-top: 30px; border-left: 4px solid #0ea5e9;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border);">
        <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #0ea5e9;">📋 Request a Quote - Recent Requests</h2>
        <a href="requests.php?type=quote" class="btn btn-outline" style="font-size: 13px; padding: 8px 16px; border-color: #0ea5e9; color: #0ea5e9;">View All →</a>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--bg);">
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Name</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Email</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Location</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Services</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Status</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentQuoteRequests as $qr): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text); font-weight: 500;">
                        <?php echo e($qr['name']); ?>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo e($qr['email']); ?>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo e($qr['location'] ?? 'N/A'); ?>
                    </td>
                    <td style="padding: 14px 12px; font-size: 12px; color: var(--text);">
                        <?php
                        $services = [];
                        if ($qr['include_drilling']) $services[] = 'Drilling';
                        if ($qr['include_construction']) $services[] = 'Construction';
                        if ($qr['include_mechanization']) $services[] = 'Mechanization';
                        if ($qr['include_yield_test']) $services[] = 'Yield Test';
                        if ($qr['include_chemical_test']) $services[] = 'Chemical Test';
                        if ($qr['include_polytank_stand']) $services[] = 'Polytank Stand';
                        echo !empty($services) ? implode(', ', $services) : 'N/A';
                        ?>
                    </td>
                    <td style="padding: 14px 12px;">
                        <span style="padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?php echo $qr['status'] === 'new' ? '#fee2e2' : '#d1fae5'; ?>; color: <?php echo $qr['status'] === 'new' ? '#991b1b' : '#065f46'; ?>; display: inline-block;">
                            <?php echo ucfirst($qr['status']); ?>
                        </span>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo date('M j, Y', strtotime($qr['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Recent Rig Requests -->
<?php if (!empty($recentRigRequests)): ?>
<div class="dashboard-card" style="margin-top: 30px; border-left: 4px solid #059669;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid var(--border);">
        <h2 style="margin: 0; font-size: 20px; font-weight: 600; color: #059669;">🚛 Request Rig - Recent Requests</h2>
        <a href="requests.php?type=rig" class="btn btn-outline" style="font-size: 13px; padding: 8px 16px; border-color: #059669; color: #059669;">View All →</a>
    </div>
    
    <div style="overflow-x: auto;">
        <table class="data-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="background: var(--bg);">
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Request #</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Requester</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Location</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Boreholes</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Urgency</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Status</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600; font-size: 13px; color: var(--text); border-bottom: 2px solid var(--border);">Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentRigRequests as $rr): ?>
                <tr style="border-bottom: 1px solid var(--border);">
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text); font-weight: 500;">
                        <?php echo e($rr['request_number']); ?>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <div><?php echo e($rr['requester_name']); ?></div>
                        <small style="color: var(--secondary);"><?php echo e($rr['requester_email']); ?></small>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo e($rr['location_address']); ?>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo $rr['number_of_boreholes']; ?>
                    </td>
                    <td style="padding: 14px 12px;">
                        <span style="padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?php 
                            echo $rr['urgency'] === 'urgent' ? '#fee2e2' : 
                                ($rr['urgency'] === 'high' ? '#fef3c7' : 
                                ($rr['urgency'] === 'medium' ? '#dbeafe' : '#d1fae5')); 
                        ?>; color: <?php 
                            echo $rr['urgency'] === 'urgent' ? '#991b1b' : 
                                ($rr['urgency'] === 'high' ? '#92400e' : 
                                ($rr['urgency'] === 'medium' ? '#1e40af' : '#065f46')); 
                        ?>; display: inline-block;">
                            <?php echo ucfirst($rr['urgency']); ?>
                        </span>
                    </td>
                    <td style="padding: 14px 12px;">
                        <span style="padding: 5px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?php 
                            echo $rr['status'] === 'new' ? '#fee2e2' : 
                                ($rr['status'] === 'completed' ? '#d1fae5' : 
                                ($rr['status'] === 'dispatched' ? '#dbeafe' : '#f1f5f9')); 
                        ?>; color: <?php 
                            echo $rr['status'] === 'new' ? '#991b1b' : 
                                ($rr['status'] === 'completed' ? '#065f46' : 
                                ($rr['status'] === 'dispatched' ? '#1e40af' : '#64748b')); 
                        ?>; display: inline-block;">
                            <?php echo ucfirst(str_replace('_', ' ', $rr['status'])); ?>
                        </span>
                    </td>
                    <td style="padding: 14px 12px; font-size: 13px; color: var(--text);">
                        <?php echo date('M j, Y', strtotime($rr['created_at'])); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>


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

