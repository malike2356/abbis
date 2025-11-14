<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

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
    $ch = curl_init(app_url('api/crm-api.php?action=get_client_data&client_id=' . $clientId));
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
            <a href="?action=customer-statement&client_id=<?php echo $clientId; ?>" class="btn btn-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-weight: 600;">
                📄 View Statement
            </a>
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

<!-- Edit Client Modal -->
<div id="clientModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content" style="max-width: 700px;">
        <div class="crm-modal-header">
            <h2 id="clientModalTitle">Edit Client</h2>
            <button type="button" class="crm-modal-close" onclick="closeClientModal()">&times;</button>
        </div>
        <form id="clientForm" method="POST">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" id="clientFormAction" value="update_client">
            <input type="hidden" name="client_id" id="clientFormId" value="<?php echo $clientId; ?>">
            
            <div style="padding: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
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
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeClientModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Client</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Follow-up Modal -->
<div id="followupModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content">
        <div class="crm-modal-header">
            <h2>📅 Schedule Follow-up</h2>
            <button type="button" class="crm-modal-close" onclick="closeFollowupModal()">&times;</button>
        </div>
        <form id="followupForm" method="POST" action="<?php echo api_url('crm-api.php'); ?>">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_followup">
            <input type="hidden" name="client_id" id="followupClientId" value="<?php echo $clientId; ?>">
            
            <div style="padding: 20px;">
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-control" required>
                        <option value="call">Phone Call</option>
                        <option value="email">Email</option>
                        <option value="meeting">Meeting</option>
                        <option value="visit">Site Visit</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-control" required placeholder="e.g., Follow up on quote">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Additional notes..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Scheduled Date/Time *</label>
                        <input type="datetime-local" name="scheduled_date" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeFollowupModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Schedule Follow-up</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Email Modal -->
<div id="emailModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content" style="max-width: 800px;">
        <div class="crm-modal-header">
            <h2>✉️ Send Email</h2>
            <button type="button" class="crm-modal-close" onclick="closeEmailModal()">&times;</button>
        </div>
        <form id="emailForm" method="POST" action="<?php echo api_url('crm-api.php'); ?>">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="send_email">
            <input type="hidden" name="client_id" id="emailClientId" value="<?php echo $clientId; ?>">
            <input type="hidden" name="template_id" id="emailTemplateId" value="">
            
            <div style="padding: 20px;">
                <div class="form-group">
                    <label class="form-label">
                        Use Template
                        <button type="button" onclick="showTemplateSelector()" class="btn btn-xs btn-outline" style="margin-left: 8px; padding: 2px 8px; font-size: 11px;">Select Template</button>
                    </label>
                    <select id="templateSelector" class="form-control" onchange="loadTemplate(this.value)">
                        <option value="">-- Select a template (optional) --</option>
                        <?php
                        try {
                            $templatesStmt = $pdo->query("SELECT id, name, category FROM email_templates WHERE is_active = 1 ORDER BY category, name");
                            $availableTemplates = $templatesStmt->fetchAll();
                            foreach ($availableTemplates as $tmpl) {
                                echo '<option value="' . $tmpl['id'] . '">' . e($tmpl['name']) . ' (' . ucfirst($tmpl['category']) . ')</option>';
                            }
                        } catch (PDOException $e) {
                            // Templates table might not exist
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">To *</label>
                    <input type="email" name="to" id="emailTo" class="form-control" required 
                           value="<?php echo e($client['email'] ?? ''); ?>" placeholder="recipient@example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" id="emailSubject" class="form-control" required placeholder="Email subject">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="body" id="emailBody" class="form-control" rows="10" required placeholder="Email message..."></textarea>
                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                        You can use template variables like {{client_name}}, {{report_id}}, etc. Variables will be replaced automatically.
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="save_copy" value="1" checked> Save copy to CRM
                    </label>
                </div>
                
                <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeEmailModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Email</button>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.crm-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.crm-modal-content {
    background: var(--card);
    color: var(--text);
    padding: 0;
    border-radius: 12px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    border: 1px solid var(--border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

.crm-modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.crm-modal-header h2 {
    margin: 0;
    font-size: 20px;
}

.crm-modal-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: var(--text);
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.crm-modal-close:hover {
    color: var(--danger);
}
</style>

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
    const tabElement = document.getElementById(tabName + '-tab');
    if (tabElement) {
        tabElement.classList.add('active');
    }
    
    const clickedTab = event.target.closest('.tab');
    if (clickedTab) {
        clickedTab.classList.add('active');
    }
}

function showFollowupModal(clientId) {
    try {
        const modal = document.getElementById('followupModal');
        const clientIdInput = document.getElementById('followupClientId');
        
        if (!modal) {
            console.error('Follow-up modal not found');
            alert('Error: Follow-up modal not found. Please refresh the page.');
            return;
        }
        
        if (clientIdInput && clientId) {
            clientIdInput.value = clientId;
        }
        
        // Set default datetime to tomorrow at 9 AM
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(9, 0, 0, 0);
        const datetimeLocal = tomorrow.toISOString().slice(0, 16);
        const datetimeInput = document.querySelector('#followupForm input[name="scheduled_date"]');
        if (datetimeInput && !datetimeInput.value) {
            datetimeInput.value = datetimeLocal;
        }
        
        modal.style.display = 'flex';
        
        // Focus on subject field
        setTimeout(() => {
            const subjectInput = document.querySelector('#followupForm input[name="subject"]');
            if (subjectInput) {
                subjectInput.focus();
            }
        }, 100);
    } catch (error) {
        console.error('Error showing follow-up modal:', error);
        alert('An error occurred while opening the follow-up form. Please check the console for details.');
    }
}

function closeFollowupModal() {
    const modal = document.getElementById('followupModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('followupForm');
        if (form) {
            form.reset();
        }
    }
}

function showEmailModal(clientId) {
    try {
        const modal = document.getElementById('emailModal');
        const clientIdInput = document.getElementById('emailClientId');
        const emailToInput = document.getElementById('emailTo');
        
        if (!modal) {
            console.error('Email modal not found');
            alert('Error: Email modal not found. Please refresh the page.');
            return;
        }
        
        if (clientIdInput && clientId) {
            clientIdInput.value = clientId;
        }
        
        // Check if template_id is in URL
        const urlParams = new URLSearchParams(window.location.search);
        const templateId = urlParams.get('template_id');
        if (templateId) {
            loadTemplate(templateId);
        }
        
        modal.style.display = 'flex';
        
        // Focus on subject field
        setTimeout(() => {
            const subjectInput = document.getElementById('emailSubject');
            if (subjectInput) {
                subjectInput.focus();
            }
        }, 100);
    } catch (error) {
        console.error('Error showing email modal:', error);
        alert('An error occurred while opening the email form. Please check the console for details.');
    }
}

function loadTemplate(templateId) {
    if (!templateId) return;
    
    fetch(`crm.php?action=templates&get_template=${templateId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.template) {
                document.getElementById('emailTemplateId').value = templateId;
                document.getElementById('emailSubject').value = data.template.subject || '';
                document.getElementById('emailBody').value = data.template.body || '';
                document.getElementById('templateSelector').value = templateId;
            }
        })
        .catch(error => {
            console.error('Error loading template:', error);
        });
}

function showTemplateSelector() {
    // Template selector is already in the form, just focus it
    document.getElementById('templateSelector').focus();
}

function closeEmailModal() {
    const modal = document.getElementById('emailModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('emailForm');
        if (form) {
            form.reset();
        }
    }
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const clientModal = document.getElementById('clientModal');
    const followupModal = document.getElementById('followupModal');
    const emailModal = document.getElementById('emailModal');
    
    if (clientModal && event.target === clientModal) {
        closeClientModal();
    }
    
    if (followupModal && event.target === followupModal) {
        closeFollowupModal();
    }
    
    if (emailModal && event.target === emailModal) {
        closeEmailModal();
    }
});

// Handle form submissions
document.addEventListener('DOMContentLoaded', function() {
    // Client form submission
    const clientForm = document.getElementById('clientForm');
    if (clientForm) {
        clientForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            // Submit to crm.php?action=clients which handles the update
            fetch('crm.php?action=clients', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is HTML (redirect) or JSON
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    // If HTML response, assume success and reload
                    return { success: true };
                }
            })
            .then(data => {
                if (data && data.success === false) {
                    alert(data.message || 'Error updating client');
                } else {
                    closeClientModal();
                    // Reload to show updated data
                    location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Even on error, try to reload as the update might have succeeded
                closeClientModal();
                location.reload();
            });
        });
    }
    
    // Follow-up form submission
    const followupForm = document.getElementById('followupForm');
    if (followupForm) {
        followupForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/crm-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeFollowupModal();
                    location.reload();
                } else {
                    alert(data.message || 'Error scheduling follow-up');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while scheduling the follow-up. Please try again.');
            });
        });
    }
    
    const emailForm = document.getElementById('emailForm');
    if (emailForm) {
        emailForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/crm-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEmailModal();
                    alert('Email sent successfully!');
                    location.reload();
                } else {
                    alert(data.message || 'Error sending email');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the email. Please try again.');
            });
        });
    }
});

function editClient(clientData) {
    try {
        // Handle both object and JSON string
        let client = clientData;
        if (typeof clientData === 'string') {
            try {
                client = JSON.parse(clientData);
            } catch (e) {
                console.error('Error parsing client data:', e);
                alert('Error loading client data. Please refresh the page and try again.');
                return;
            }
        }
        
        const modal = document.getElementById('clientModal');
        const form = document.getElementById('clientForm');
        const title = document.getElementById('clientModalTitle');
        
        if (!modal || !form || !title) {
            console.error('Client modal elements not found');
            alert('Error: Client modal not found. Please refresh the page.');
            return;
        }
        
        // Update title and action
        title.textContent = 'Edit Client';
        document.getElementById('clientFormAction').value = 'update_client';
        document.getElementById('clientFormId').value = client.id || client.client_id || '';
        
        // Fill form fields
        Object.keys(client).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input) {
                if (input.type === 'checkbox') {
                    input.checked = client[key] ? true : false;
                } else {
                    input.value = client[key] || '';
                }
            }
        });
        
        // Show modal
        modal.style.display = 'flex';
        
        // Focus on first input
        setTimeout(() => {
            const firstInput = form.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
        }, 100);
    } catch (error) {
        console.error('Error in editClient function:', error);
        alert('An error occurred while opening the edit form. Please check the console for details.');
    }
}

function closeClientModal() {
    const modal = document.getElementById('clientModal');
    if (modal) {
        modal.style.display = 'none';
        const form = document.getElementById('clientForm');
        if (form) {
            form.reset();
        }
    }
}

function showContactModal(clientId, contact = null) {
    alert('Contact modal - Client ID: ' + clientId + (contact ? ' (Edit mode)' : ' (Add mode)'));
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
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while completing the follow-up. Please try again.');
        });
    }
}
</script>

