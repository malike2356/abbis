<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

/**
 * CRM Email Management
 */
$pdo = getDBConnection();

// Check if we're in compose mode
$composeMode = isset($_GET['compose']) && $_GET['compose'] == '1';
$templateId = isset($_GET['template_id']) ? intval($_GET['template_id']) : null;
$selectedClientId = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;

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
    $stmt = $pdo->query("SELECT id, client_name, email, contact_person FROM clients ORDER BY client_name");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
}

// Get template if template_id is provided
$template = null;
if ($templateId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $template = null;
    }
}

// Get selected client data if client_id is provided
$selectedClient = null;
if ($selectedClientId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$selectedClientId]);
        $selectedClient = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $selectedClient = null;
    }
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

<!-- Email Compose Modal -->
<div id="emailComposeModal" class="crm-modal" style="display: <?php echo $composeMode ? 'flex' : 'none'; ?>;">
    <div class="crm-modal-content" style="max-width: 800px;">
        <div class="crm-modal-header">
            <h2>✉️ Send Email<?php echo $template ? ' - Using Template' : ''; ?></h2>
            <button type="button" class="crm-modal-close" onclick="closeEmailComposeModal()">&times;</button>
        </div>
        <form id="emailComposeForm" method="POST" action="<?php echo api_url('crm-api.php'); ?>">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="send_email">
            <input type="hidden" name="client_id" id="composeClientId" value="<?php echo $selectedClientId; ?>">
            <input type="hidden" name="template_id" id="composeTemplateId" value="<?php echo $templateId ?? ''; ?>">
            
            <div style="padding: 20px;">
                <div class="form-group">
                    <label class="form-label">
                        Select Client *
                        <a href="?action=clients" target="_blank" style="font-size: 11px; margin-left: 8px; color: var(--primary);">Browse Clients</a>
                    </label>
                    <select name="client_id" id="composeClientSelect" class="form-control" required onchange="updateClientEmail(this.value)">
                        <option value="">-- Select a client --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>" 
                                    data-email="<?php echo e($client['email'] ?? ''); ?>"
                                    data-contact="<?php echo e($client['contact_person'] ?? ''); ?>"
                                    <?php echo $selectedClientId == $client['id'] ? 'selected' : ''; ?>>
                                <?php echo e($client['client_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Use Template
                        <a href="?action=templates" target="_blank" style="font-size: 11px; margin-left: 8px; color: var(--primary);">Browse Templates</a>
                    </label>
                    <select id="composeTemplateSelector" class="form-control" onchange="loadTemplateIntoCompose(this.value)">
                        <option value="">-- Select a template (optional) --</option>
                        <?php
                        try {
                            $templatesStmt = $pdo->query("SELECT id, name, category FROM email_templates WHERE is_active = 1 ORDER BY category, name");
                            $availableTemplates = $templatesStmt->fetchAll();
                            foreach ($availableTemplates as $tmpl) {
                                echo '<option value="' . $tmpl['id'] . '"' . ($templateId == $tmpl['id'] ? ' selected' : '') . '>';
                                echo e($tmpl['name']) . ' (' . ucfirst($tmpl['category']) . ')';
                                echo '</option>';
                            }
                        } catch (PDOException $e) {
                            // Templates table might not exist
                        }
                        ?>
                    </select>
                    <?php if ($template): ?>
                        <div style="margin-top: 8px; padding: 10px; background: rgba(14,165,233,0.1); border-radius: 4px; font-size: 12px;">
                            <strong>Template Selected:</strong> <?php echo e($template['name']); ?>
                            <br><small>Subject: <?php echo e($template['subject']); ?></small>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label class="form-label">To *</label>
                    <input type="email" name="to" id="composeEmailTo" class="form-control" required 
                           value="<?php echo e($selectedClient['email'] ?? $template ? '' : ''); ?>" 
                           placeholder="recipient@example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" id="composeSubject" class="form-control" required 
                           value="<?php echo $template ? e($template['subject']) : ''; ?>" 
                           placeholder="Email subject">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="body" id="composeBody" class="form-control" rows="12" required 
                              placeholder="Email message..."><?php echo $template ? e($template['body']) : ''; ?></textarea>
                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                        You can use template variables like {{client_name}}, {{report_id}}, etc. Variables will be replaced automatically when sending.
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="save_copy" value="1" checked> Save copy to CRM
                    </label>
                </div>
                
                <div class="form-actions" style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-outline" onclick="closeEmailComposeModal()">Cancel</button>
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
    max-width: 800px;
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
function showEmailModal() {
    document.getElementById('emailComposeModal').style.display = 'flex';
}

function closeEmailComposeModal() {
    document.getElementById('emailComposeModal').style.display = 'none';
    // Clear URL parameters
    if (window.history && window.history.replaceState) {
        window.history.replaceState({}, document.title, window.location.pathname + '?action=emails');
    }
}

function updateClientEmail(clientId) {
    const select = document.getElementById('composeClientSelect');
    const option = select.options[select.selectedIndex];
    const emailInput = document.getElementById('composeEmailTo');
    const clientIdInput = document.getElementById('composeClientId');
    
    if (option && option.dataset.email) {
        emailInput.value = option.dataset.email;
    }
    if (clientIdInput) {
        clientIdInput.value = clientId;
    }
}

function loadTemplateIntoCompose(templateId) {
    if (!templateId) {
        // Clear template
        document.getElementById('composeTemplateId').value = '';
        return;
    }
    
    fetch(`crm.php?action=templates&get_template=${templateId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && data.template) {
                document.getElementById('composeTemplateId').value = templateId;
                document.getElementById('composeSubject').value = data.template.subject || '';
                document.getElementById('composeBody').value = data.template.body || '';
            }
        })
        .catch(error => {
            console.error('Error loading template:', error);
        });
}

// Handle form submission
document.addEventListener('DOMContentLoaded', function() {
    const composeForm = document.getElementById('emailComposeForm');
    if (composeForm) {
        composeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('../api/crm-api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEmailComposeModal();
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
    
    // If in compose mode, initialize client email if client is selected
    <?php if ($selectedClient && $selectedClient['email']): ?>
    document.getElementById('composeEmailTo').value = '<?php echo e($selectedClient['email']); ?>';
    <?php endif; ?>
    
    // If template is provided, it's already loaded in the form
});

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('emailComposeModal');
    if (modal && event.target === modal) {
        closeEmailComposeModal();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEmailComposeModal();
    }
});

function viewEmail(id) {
    window.location.href = '?action=emails&id=' + id;
}
</script>

