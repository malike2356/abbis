<?php
/**
 * CRM Client Detail View
 * Comprehensive client profile with all CRM features
 */
$pdo = getDBConnection();
$currentUserId = $_SESSION['user_id'];

if (empty($clientId)) {
    header('Location: crm.php?action=clients');
    exit;
}

// Get client data via API
$clientData = null;
try {
    $ch = curl_init(APP_URL . '/api/crm-api.php?action=get_client_data&client_id=' . $clientId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            $clientData = $result;
        }
    }
} catch (Exception $e) {
    error_log("Error fetching client data: " . $e->getMessage());
}

// Fallback: direct database query
if (!$clientData) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        
        if (!$client) {
            header('Location: crm.php?action=clients');
            exit;
        }
        
        $clientData = [
            'client' => $client,
            'contacts' => [],
            'followups' => [],
            'emails' => [],
            'activities' => []
        ];
    } catch (PDOException $e) {
        header('Location: crm.php?action=clients');
        exit;
    }
}

$client = $clientData['client'];
$contacts = $clientData['contacts'] ?? [];
$followups = $clientData['followups'] ?? [];
$emails = $clientData['emails'] ?? [];
$activities = $clientData['activities'] ?? [];

// Get client statistics
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(total_income) as total_revenue,
            SUM(net_profit) as total_profit,
            AVG(net_profit) as avg_profit_per_job,
            MIN(report_date) as first_job_date,
            MAX(report_date) as last_job_date
        FROM field_reports
        WHERE client_id = ?
    ");
    $stmt->execute([$clientId]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = null;
}
?>

<div style="margin-bottom: 20px;">
    <a href="?action=clients" class="btn btn-outline" style="margin-bottom: 15px;">← Back to Clients</a>
    <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="margin: 0;"><?php echo e($client['client_name']); ?></h1>
            <p style="color: var(--secondary); margin: 5px 0 0 0;">
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
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="showFollowupModal(<?php echo $clientId; ?>)" class="btn btn-primary">📅 Schedule Follow-up</button>
            <button onclick="showEmailModal(<?php echo $clientId; ?>)" class="btn btn-primary">✉️ Send Email</button>
            <button onclick="editClient(<?php echo htmlspecialchars(json_encode($client)); ?>)" class="btn btn-outline">✏️ Edit</button>
        </div>
    </div>
</div>

<div class="dashboard-grid">
    <!-- Client Info -->
    <div class="dashboard-card">
        <h2>ℹ️ Client Information</h2>
        <div style="margin-top: 15px;">
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary); width: 40%;">Company Type:</td>
                    <td style="padding: 8px 0;"><?php echo e($client['company_type'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary);">Contact Person:</td>
                    <td style="padding: 8px 0;"><?php echo e($client['contact_person'] ?? 'N/A'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary);">Email:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($client['email']): ?>
                            <a href="mailto:<?php echo e($client['email']); ?>"><?php echo e($client['email']); ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary);">Phone:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($client['contact_number']): ?>
                            <a href="tel:<?php echo e($client['contact_number']); ?>"><?php echo e($client['contact_number']); ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary);">Website:</td>
                    <td style="padding: 8px 0;">
                        <?php if ($client['website']): ?>
                            <a href="<?php echo e($client['website']); ?>" target="_blank"><?php echo e($client['website']); ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary);">Address:</td>
                    <td style="padding: 8px 0;"><?php echo e($client['address'] ?? 'N/A'); ?></td>
                </tr>
                <?php if ($client['notes']): ?>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: var(--secondary); vertical-align: top;">Notes:</td>
                    <td style="padding: 8px 0;"><?php echo nl2br(e($client['notes'])); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
    
    <!-- Client Stats -->
    <div class="dashboard-card">
        <h2>📊 Client Statistics</h2>
        <?php if ($stats && $stats['total_jobs'] > 0): ?>
            <div style="margin-top: 15px;">
                <div style="padding: 15px; background: #f1f5f9; border-radius: 8px; margin-bottom: 10px;">
                    <div style="font-size: 32px; font-weight: bold; color: var(--primary);">
                        <?php echo number_format($stats['total_jobs']); ?>
                    </div>
                    <div style="color: var(--secondary); font-size: 14px;">Total Jobs</div>
                </div>
                <div style="padding: 15px; background: #f1f5f9; border-radius: 8px; margin-bottom: 10px;">
                    <div style="font-size: 28px; font-weight: bold; color: #10b981;">
                        <?php echo formatCurrency($stats['total_revenue'] ?? 0); ?>
                    </div>
                    <div style="color: var(--secondary); font-size: 14px;">Total Revenue</div>
                </div>
                <div style="padding: 15px; background: #f1f5f9; border-radius: 8px;">
                    <div style="font-size: 28px; font-weight: bold; color: #3b82f6;">
                        <?php echo formatCurrency($stats['total_profit'] ?? 0); ?>
                    </div>
                    <div style="color: var(--secondary); font-size: 14px;">Total Profit</div>
                </div>
            </div>
        <?php else: ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No jobs recorded yet
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs for Contacts, Follow-ups, Emails, Activities -->
<div class="config-tabs" style="margin-top: 30px;">
    <div class="tabs">
        <button type="button" class="tab active" onclick="showTab('contacts')">
            <span>👥 Contacts</span>
        </button>
        <button type="button" class="tab" onclick="showTab('followups')">
            <span>📅 Follow-ups</span>
        </button>
        <button type="button" class="tab" onclick="showTab('emails')">
            <span>📧 Emails</span>
        </button>
        <button type="button" class="tab" onclick="showTab('activities')">
            <span>📋 Activities</span>
        </button>
    </div>
</div>

<!-- Contacts Tab -->
<div id="contacts-tab" class="tab-content active">
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>👥 Client Contacts</h2>
            <button onclick="showContactModal(<?php echo $clientId; ?>)" class="btn btn-primary">➕ Add Contact</button>
        </div>
        
        <?php if (empty($contacts)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No contacts added. <a href="#" onclick="showContactModal(<?php echo $clientId; ?>); return false;">Add first contact</a>
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Title</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Department</th>
                            <th>Primary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td><strong><?php echo e($contact['name']); ?></strong></td>
                            <td><?php echo e($contact['title'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($contact['email']): ?>
                                    <a href="mailto:<?php echo e($contact['email']); ?>"><?php echo e($contact['email']); ?></a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($contact['phone']): ?>
                                    <a href="tel:<?php echo e($contact['phone']); ?>"><?php echo e($contact['phone']); ?></a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($contact['department'] ?? 'N/A'); ?></td>
                            <td>
                                <?php if ($contact['is_primary']): ?>
                                    <span style="color: #10b981;">✓ Primary</span>
                                <?php else: ?>
                                    <span style="color: var(--secondary);">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button onclick="showContactModal(<?php echo $clientId; ?>, <?php echo htmlspecialchars(json_encode($contact)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Follow-ups Tab -->
<div id="followups-tab" class="tab-content">
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>📅 Follow-ups</h2>
            <button onclick="showFollowupModal(<?php echo $clientId; ?>)" class="btn btn-primary">➕ Schedule Follow-up</button>
        </div>
        
        <?php if (empty($followups)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No follow-ups scheduled. <a href="#" onclick="showFollowupModal(<?php echo $clientId; ?>); return false;">Schedule one</a>
            </p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Type</th>
                            <th>Subject</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups as $followup): ?>
                        <tr>
                            <td><?php echo date('M j, Y g:i A', strtotime($followup['scheduled_date'])); ?></td>
                            <td><?php echo ucfirst($followup['type']); ?></td>
                            <td><?php echo e($followup['subject']); ?></td>
                            <td>
                                <?php
                                $priorityColors = ['low' => '#10b981', 'medium' => '#f59e0b', 'high' => '#ef4444', 'urgent' => '#dc2626'];
                                $color = $priorityColors[$followup['priority']] ?? '#64748b';
                                ?>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: <?php echo $color; ?>20; color: <?php echo $color; ?>;">
                                    <?php echo ucfirst($followup['priority']); ?>
                                </span>
                            </td>
                            <td><?php echo ucfirst($followup['status']); ?></td>
                            <td>
                                <?php if ($followup['status'] === 'scheduled'): ?>
                                    <button onclick="completeFollowup(<?php echo $followup['id']; ?>)" class="btn btn-sm btn-success">Complete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Emails Tab -->
<div id="emails-tab" class="tab-content">
    <div class="dashboard-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>📧 Email Communications</h2>
            <button onclick="showEmailModal(<?php echo $clientId; ?>)" class="btn btn-primary">✉️ Send Email</button>
        </div>
        
        <?php if (empty($emails)): ?>
            <p style="text-align: center; padding: 40px; color: var(--secondary);">
                No emails recorded. <a href="#" onclick="showEmailModal(<?php echo $clientId; ?>); return false;">Send first email</a>
            </p>
        <?php else: ?>
            <div>
                <?php foreach ($emails as $email): ?>
                <div style="padding: 15px; border-bottom: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                        <div>
                            <strong><?php echo e($email['subject']); ?></strong>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 5px;">
                                <?php if ($email['direction'] === 'outbound'): ?>
                                    To: <?php echo e($email['to_email']); ?>
                                <?php else: ?>
                                    From: <?php echo e($email['from_email']); ?>
                                <?php endif; ?>
                                • <?php echo date('M j, Y g:i A', strtotime($email['created_at'])); ?>
                            </div>
                        </div>
                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                            background: <?php echo $email['direction'] === 'outbound' ? '#dbeafe' : '#f3e8ff'; ?>; 
                            color: <?php echo $email['direction'] === 'outbound' ? '#1e40af' : '#6b21a8'; ?>;">
                            <?php echo $email['direction'] === 'outbound' ? '→ Out' : '← In'; ?>
                        </span>
                    </div>
                    <div style="font-size: 13px; color: var(--secondary);">
                        <?php echo e(substr(strip_tags($email['body']), 0, 200)); ?>...
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Activities Tab -->
<div id="activities-tab" class="tab-content">
    <div class="dashboard-card">
        <h2>📋 Activity Timeline</h2>
        <div style="margin-top: 20px;">
            <?php if (empty($activities)): ?>
                <p style="text-align: center; padding: 40px; color: var(--secondary);">
                    No activities recorded
                </p>
            <?php else: ?>
                <?php foreach ($activities as $activity): ?>
                <div style="padding: 12px; border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: start;">
                    <div style="flex-shrink: 0; font-size: 24px;">
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
                        <?php if ($activity['description']): ?>
                        <div style="font-size: 13px; color: var(--secondary); margin-bottom: 4px;">
                            <?php echo e($activity['description']); ?>
                        </div>
                        <?php endif; ?>
                        <div style="font-size: 12px; color: var(--secondary);">
                            <?php echo e($activity['creator_name'] ?? 'System'); ?> • 
                            <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    event.target.closest('.tab').classList.add('active');
}

function showFollowupModal(clientId) {
    // Open follow-up modal (implement based on your modal system)
    alert('Follow-up modal - Client ID: ' + clientId);
}

function showEmailModal(clientId) {
    window.location.href = '?action=emails&compose=1&client_id=' + clientId;
}

function showContactModal(clientId, contact = null) {
    alert('Contact modal - Client ID: ' + clientId);
}

function completeFollowup(id) {
    if (confirm('Mark this follow-up as completed?')) {
        const outcome = prompt('Enter outcome/notes (optional):');
        
        fetch('../api/crm-api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'complete_followup',
                followup_id: id,
                outcome: outcome || '',
                csrf_token: '<?php echo CSRF::getToken(); ?>'
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Error');
            }
        });
    }
}
</script>

