<?php
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('crm.access');

/**
 * Enhanced Email Templates Management for ABBIS
 * Supports field reports, financial data, rig information, and more
 */
$pdo = getDBConnection();

// Ensure default specialist templates exist (idempotent)
$defaultTemplateSeeds = [
    [
        'name' => 'Rig Request Acknowledgement',
        'subject' => 'Rig Request Received - {{request_number}}',
        'body' => "Dear {{requester_name}},

Thank you for submitting a rig request ({{request_number}}). Our team has received the details below and will contact you shortly.

Request Summary:
- Company/Requester: {{company_name}} ({{requester_type}})
- Contact Email: {{requester_email}}
- Contact Phone: {{requester_phone}}
- Location: {{location_address}}
- Number of Boreholes: {{number_of_boreholes}}
- Preferred Start Date: {{preferred_start_date}}
- Urgency: {{urgency}}
- Estimated Budget: {{currency}} {{estimated_budget}}

If any of this information changes, please let us know so we can update your request.

Best regards,
{{sender_name}}
{{company_name}}
{{company_phone}}",
        'category' => 'rig_request',
        'variables' => json_encode([
            'request_number',
            'requester_name',
            'requester_email',
            'requester_phone',
            'requester_type',
            'company_name',
            'location_address',
            'number_of_boreholes',
            'preferred_start_date',
            'urgency',
            'estimated_budget',
            'currency',
            'sender_name',
            'company_phone'
        ]),
    ],
];

foreach ($defaultTemplateSeeds as $templateSeed) {
    try {
        $check = $pdo->prepare("SELECT id FROM email_templates WHERE name = ? LIMIT 1");
        $check->execute([$templateSeed['name']]);
        if (!$check->fetchColumn()) {
            $insert = $pdo->prepare("
                INSERT INTO email_templates (name, subject, body, category, variables, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, 1, ?)
            ");
            $insert->execute([
                $templateSeed['name'],
                $templateSeed['subject'],
                $templateSeed['body'],
                $templateSeed['category'],
                $templateSeed['variables'],
                $_SESSION['user_id'] ?? null
            ]);
        }
    } catch (PDOException $e) {
        error_log('Failed to ensure default template "' . $templateSeed['name'] . '": ' . $e->getMessage());
    }
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
                case 'add_template':
                    $name = sanitizeInput($_POST['name'] ?? '');
                    $subject = sanitizeInput($_POST['subject'] ?? '');
                    $body = $_POST['body'] ?? '';
                    $category = sanitizeInput($_POST['category'] ?? 'general');
                    $variables = !empty($_POST['variables']) ? json_encode(json_decode($_POST['variables'])) : null;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO email_templates (name, subject, body, category, variables, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $subject, $body, $category, $variables, $_SESSION['user_id']]);
                    
                    $message = 'Template added successfully';
                    $messageType = 'success';
                    break;
                    
                case 'update_template':
                    $templateId = intval($_POST['template_id']);
                    $name = sanitizeInput($_POST['name'] ?? '');
                    $subject = sanitizeInput($_POST['subject'] ?? '');
                    $body = $_POST['body'] ?? '';
                    $category = sanitizeInput($_POST['category'] ?? 'general');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $variables = !empty($_POST['variables']) ? json_encode(json_decode($_POST['variables'])) : null;
                    
                    $stmt = $pdo->prepare("
                        UPDATE email_templates 
                        SET name = ?, subject = ?, body = ?, category = ?, is_active = ?, variables = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $subject, $body, $category, $isActive, $variables, $templateId]);
                    
                    $message = 'Template updated successfully';
                    $messageType = 'success';
                    break;
                    
                case 'delete_template':
                    $templateId = intval($_POST['template_id']);
                    $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = ?");
                    $stmt->execute([$templateId]);
                    
                    $message = 'Template deleted successfully';
                    $messageType = 'success';
                    break;
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get templates
try {
    $stmt = $pdo->query("SELECT * FROM email_templates ORDER BY category, name");
    $templates = $stmt->fetchAll();
} catch (PDOException $e) {
    $templates = [];
}

// Group by category
$templatesByCategory = [];
foreach ($templates as $template) {
    $cat = $template['category'];
    if (!isset($templatesByCategory[$cat])) {
        $templatesByCategory[$cat] = [];
    }
    $templatesByCategory[$cat][] = $template;
}

// Get company name for preview
try {
    $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
    $companyName = $stmt->fetchColumn() ?: 'ABBIS';
} catch (PDOException $e) {
    $companyName = 'ABBIS';
}

// Handle GET requests for preview, copy, etc.
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['preview'])) {
        $templateId = intval($_GET['preview']);
        try {
            $stmt = $pdo->prepare("SELECT subject, body FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($template) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'subject' => $template['subject'], 'body' => $template['body']]);
                exit;
            }
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error loading template']);
            exit;
        }
    }
    
    if (isset($_GET['copy'])) {
        $templateId = intval($_GET['copy']);
        try {
            $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($template) {
                // Open modal with template data for editing
                $template['name'] = $template['name'] . ' (Copy)';
                $template['id'] = null;
                echo "<script>window.templateToEdit = " . json_encode($template) . "; window.addEventListener('DOMContentLoaded', function() { showTemplateModal(window.templateToEdit); });</script>";
            }
        } catch (PDOException $e) {
            // Error handled below
        }
    }
    
    if (isset($_GET['get_template'])) {
        $templateId = intval($_GET['get_template']);
        try {
            $stmt = $pdo->prepare("SELECT * FROM email_templates WHERE id = ?");
            $stmt->execute([$templateId]);
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($template) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'template' => $template]);
                exit;
            }
        } catch (PDOException $e) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error loading template']);
            exit;
        }
    }
}
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <div>
        <h2 style="margin: 0;">📝 Email Templates</h2>
        <p style="margin: 5px 0 0 0; color: var(--secondary); font-size: 14px;">
            Create and manage email templates with dynamic variables for ABBIS operations
        </p>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="showVariablesGuide()" class="btn btn-outline">📖 Variables Guide</button>
        <button onclick="showTemplateModal()" class="btn btn-primary">➕ Add Template</button>
    </div>
</div>

<!-- Variables Guide Modal -->
<div id="variablesGuideModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content" style="max-width: 900px;">
        <div class="crm-modal-header">
            <h2>📖 Available Template Variables</h2>
            <button type="button" class="crm-modal-close" onclick="closeVariablesGuide()">&times;</button>
        </div>
        <div style="padding: 20px; max-height: 70vh; overflow-y: auto;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                <!-- Client Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">👥 Client Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{client_name}}</code> - Client name<br>
                        <code>{{contact_name}}</code> - Contact person<br>
                        <code>{{contact_number}}</code> - Phone number<br>
                        <code>{{client_email}}</code> - Client email<br>
                        <code>{{company_type}}</code> - Company type<br>
                        <code>{{client_address}}</code> - Client address<br>
                        <code>{{client_status}}</code> - Client status
                    </div>
                </div>
                
                <!-- Field Report Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">📋 Field Report Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{report_id}}</code> - Report ID<br>
                        <code>{{report_date}}</code> - Report date<br>
                        <code>{{site_name}}</code> - Site name<br>
                        <code>{{job_type}}</code> - Job type<br>
                        <code>{{total_depth}}</code> - Total depth (meters)<br>
                        <code>{{total_rpm}}</code> - Total RPM<br>
                        <code>{{total_duration}}</code> - Duration (hours)<br>
                        <code>{{rig_name}}</code> - Rig name<br>
                        <code>{{rig_code}}</code> - Rig code<br>
                        <code>{{location_description}}</code> - Location details
                    </div>
                </div>
                
                <!-- Financial Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">💰 Financial Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{contract_sum}}</code> - Contract amount<br>
                        <code>{{rig_fee_charged}}</code> - Rig fee charged<br>
                        <code>{{rig_fee_collected}}</code> - Rig fee collected<br>
                        <code>{{total_income}}</code> - Total income<br>
                        <code>{{total_expenses}}</code> - Total expenses<br>
                        <code>{{net_profit}}</code> - Net profit<br>
                        <code>{{outstanding_balance}}</code> - Outstanding balance<br>
                        <code>{{currency}}</code> - Currency (GHS)
                    </div>
                </div>
                
                <!-- Company Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">🏢 Company Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{company_name}}</code> - Company name<br>
                        <code>{{sender_name}}</code> - Sender name<br>
                        <code>{{sender_email}}</code> - Sender email<br>
                        <code>{{company_phone}}</code> - Company phone<br>
                        <code>{{company_address}}</code> - Company address<br>
                        <code>{{current_date}}</code> - Current date<br>
                        <code>{{current_time}}</code> - Current time
                    </div>
                </div>
                
                <!-- Maintenance Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">🔧 Maintenance Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{maintenance_type}}</code> - Maintenance type<br>
                        <code>{{maintenance_date}}</code> - Maintenance date<br>
                        <code>{{maintenance_cost}}</code> - Maintenance cost<br>
                        <code>{{rpm_at_maintenance}}</code> - RPM at maintenance<br>
                        <code>{{next_maintenance_due}}</code> - Next maintenance due
                    </div>
                </div>
                
                <!-- Rig Request Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">🚛 Rig Request Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{request_number}}</code> - Rig request number<br>
                        <code>{{requester_name}}</code> - Requester name<br>
                        <code>{{requester_email}}</code> - Requester email<br>
                        <code>{{requester_phone}}</code> - Requester phone<br>
                        <code>{{requester_type}}</code> - Requester type (contractor/client)<br>
                        <code>{{company_name}}</code> - Company name<br>
                        <code>{{location_address}}</code> - Project location<br>
                        <code>{{number_of_boreholes}}</code> - Number of boreholes requested<br>
                        <code>{{estimated_budget}}</code> - Estimated budget<br>
                        <code>{{preferred_start_date}}</code> - Preferred start date<br>
                        <code>{{urgency}}</code> - Request urgency
                    </div>
                </div>
                
                <!-- Payment Variables -->
                <div style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
                    <h3 style="margin: 0 0 10px 0; color: var(--primary); font-size: 16px;">💳 Payment Variables</h3>
                    <div style="font-size: 13px; line-height: 1.8;">
                        <code>{{payment_amount}}</code> - Payment amount<br>
                        <code>{{payment_date}}</code> - Payment date<br>
                        <code>{{payment_method}}</code> - Payment method<br>
                        <code>{{invoice_number}}</code> - Invoice number<br>
                        <code>{{receipt_number}}</code> - Receipt number
                    </div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: rgba(14,165,233,0.1); border-radius: 8px; border-left: 4px solid var(--primary);">
                <strong style="color: var(--primary);">💡 Usage Tip:</strong>
                <p style="margin: 8px 0 0 0; font-size: 13px; color: var(--text);">
                    Use variables in your templates by wrapping them in double curly braces, e.g., <code>{{client_name}}</code>. 
                    Variables will be automatically replaced with actual data when the email is sent. You can use variables in both the subject and body of your templates.
                </p>
            </div>
        </div>
        <div style="padding: 15px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeVariablesGuide()" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<div class="dashboard-card">
    <?php if (empty($templates)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No templates found. <a href="#" onclick="showTemplateModal(); return false;">Create your first template</a>
        </p>
    <?php else: ?>
        <?php 
        $categoryLabels = [
            'general' => 'General',
            'welcome' => 'Welcome',
            'followup' => 'Follow-up',
            'quote' => 'Quote',
            'proposal' => 'Proposal',
            'invoice' => 'Invoice',
            'job_completion' => 'Job Completion',
            'payment_reminder' => 'Payment Reminder',
            'maintenance' => 'Maintenance',
            'receipt' => 'Receipt',
            'thank_you' => 'Thank You',
            'announcement' => 'Announcement',
            'rig_request' => 'Rig Request'
        ];
        ?>
        <?php foreach ($templatesByCategory as $category => $categoryTemplates): ?>
            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: var(--primary); text-transform: capitalize;">
                    <?php echo e($categoryLabels[$category] ?? ucfirst($category)); ?>
                    <span style="font-size: 14px; font-weight: normal; color: var(--secondary); margin-left: 10px;">
                        (<?php echo count($categoryTemplates); ?> template<?php echo count($categoryTemplates) != 1 ? 's' : ''; ?>)
                    </span>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px;">
                    <?php foreach ($categoryTemplates as $template): ?>
                    <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px; background: var(--card);">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div style="flex: 1;">
                                <strong style="font-size: 15px;"><?php echo e($template['name']); ?></strong>
                                <?php if (!$template['is_active']): ?>
                                    <span style="color: var(--secondary); font-size: 12px; margin-left: 8px;">(Inactive)</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <button onclick="previewTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-outline" title="Preview">👁️</button>
                                <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" class="btn btn-sm btn-outline" title="Edit">✏️</button>
                                <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-danger" title="Delete">🗑️</button>
                            </div>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong style="font-size: 12px; color: var(--secondary);">Subject:</strong><br>
                            <div style="font-size: 13px; margin-top: 4px;"><?php echo e($template['subject']); ?></div>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <strong style="font-size: 12px; color: var(--secondary);">Preview:</strong><br>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 4px; line-height: 1.5;">
                                <?php echo e(substr(strip_tags($template['body']), 0, 120)); ?>...
                            </div>
                        </div>
                        <div style="padding-top: 15px; border-top: 1px solid var(--border); display: flex; gap: 8px;">
                            <button onclick="useTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-primary" style="flex: 1;">
                                Use Template
                            </button>
                            <button onclick="copyTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-outline" title="Duplicate">
                                📋
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Template Modal -->
<div id="templateModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content" style="max-width: 800px;">
        <div class="crm-modal-header">
            <h2 id="templateModalTitle">Add Email Template</h2>
            <button type="button" class="crm-modal-close" onclick="closeTemplateModal()">&times;</button>
        </div>
        <form method="POST" id="templateForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" id="templateAction" value="add_template">
            <input type="hidden" name="template_id" id="templateId">
            <input type="hidden" name="variables" id="templateVariables">
            
            <div style="padding: 20px;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label class="form-label">Template Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Job Completion Notification">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category *</label>
                        <select name="category" class="form-control" required>
                            <option value="general">General</option>
                            <option value="welcome">Welcome</option>
                            <option value="followup">Follow-up</option>
                            <option value="quote">Quote</option>
                            <option value="proposal">Proposal</option>
                            <option value="invoice">Invoice</option>
                            <option value="job_completion">Job Completion</option>
                            <option value="payment_reminder">Payment Reminder</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="receipt">Receipt</option>
                            <option value="thank_you">Thank You</option>
                            <option value="announcement">Announcement</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Subject *
                        <button type="button" onclick="insertVariable('subject', '{{client_name}}')" class="btn btn-xs btn-outline" style="margin-left: 8px; padding: 2px 8px; font-size: 11px;">Insert Variable</button>
                    </label>
                    <input type="text" name="subject" id="templateSubject" class="form-control" required placeholder="Use {{variable_name}} for dynamic content">
                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                        Example: Job Completed - {{report_id}} for {{client_name}}
                    </small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Body *
                        <button type="button" onclick="showVariableSelector('body')" class="btn btn-xs btn-outline" style="margin-left: 8px; padding: 2px 8px; font-size: 11px;">Insert Variable</button>
                        <button type="button" onclick="showTemplatePreview()" class="btn btn-xs btn-outline" style="margin-left: 4px; padding: 2px 8px; font-size: 11px;">Preview</button>
                    </label>
                    <textarea name="body" id="templateBody" class="form-control" rows="12" required placeholder="Use {{variable_name}} for dynamic content"></textarea>
                    <small class="form-text" style="display: block; margin-top: 4px; color: var(--secondary);">
                        Available variables: Click "Insert Variable" or see Variables Guide. HTML is supported.
                    </small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_active" id="templateActive" checked>
                        <span>Template is active</span>
                    </label>
                </div>
            </div>
            
            <div style="padding: 15px 20px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeTemplateModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Template</button>
            </div>
        </form>
    </div>
</div>

<!-- Template Preview Modal -->
<div id="templatePreviewModal" class="crm-modal" style="display: none;">
    <div class="crm-modal-content" style="max-width: 700px;">
        <div class="crm-modal-header">
            <h2>👁️ Template Preview</h2>
            <button type="button" class="crm-modal-close" onclick="closeTemplatePreview()">&times;</button>
        </div>
        <div style="padding: 20px;">
            <div style="margin-bottom: 15px;">
                <strong style="color: var(--secondary); font-size: 12px;">Subject:</strong>
                <div id="previewSubject" style="margin-top: 5px; padding: 10px; background: var(--bg); border-radius: 4px; font-size: 14px;"></div>
            </div>
            <div>
                <strong style="color: var(--secondary); font-size: 12px;">Body:</strong>
                <div id="previewBody" style="margin-top: 5px; padding: 15px; background: var(--bg); border-radius: 4px; font-size: 13px; line-height: 1.6; max-height: 400px; overflow-y: auto;"></div>
            </div>
            <div style="margin-top: 15px; padding: 10px; background: rgba(14,165,233,0.1); border-radius: 4px; font-size: 12px; color: var(--secondary);">
                <strong>Note:</strong> This is a preview with sample data. Actual values will be used when sending emails.
            </div>
        </div>
        <div style="padding: 15px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end;">
            <button type="button" onclick="closeTemplatePreview()" class="btn btn-primary">Close</button>
        </div>
    </div>
</div>

<!-- Variable Selector -->
<div id="variableSelector" style="display: none; position: absolute; background: var(--card); border: 1px solid var(--border); border-radius: 8px; padding: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10001; max-width: 300px; max-height: 300px; overflow-y: auto;">
    <div style="font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--primary);">Select Variable:</div>
    <div id="variableList" style="display: flex; flex-direction: column; gap: 4px; font-size: 12px;">
        <!-- Variables will be populated by JavaScript -->
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

.variable-item {
    padding: 6px 10px;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.2s;
}

.variable-item:hover {
    background: rgba(14,165,233,0.1);
}
</style>

<script>
// Sample data for preview
const sampleData = {
    client_name: 'Owenase Client',
    contact_name: 'John Doe',
    contact_number: '+233 XX XXX XXXX',
    client_email: 'client@example.com',
    company_type: 'Individual',
    client_address: '123 Main Street, Accra',
    client_status: 'Active',
    report_id: 'FR-2024-001',
    report_date: '<?php echo date('M d, Y'); ?>',
    site_name: 'Owenase Site',
    job_type: 'Direct',
    total_depth: '45.5',
    total_rpm: '1250.00',
    total_duration: '8',
    rig_name: 'RED RIG',
    rig_code: 'RR-001',
    location_description: 'Near the main road, accessible by truck',
    contract_sum: '5000.00',
    rig_fee_charged: '3000.00',
    rig_fee_collected: '3000.00',
    total_income: '5000.00',
    total_expenses: '1500.00',
    net_profit: '3500.00',
    outstanding_balance: '0.00',
    currency: 'GHS',
    company_name: '<?php echo e($companyName); ?>',
    sender_name: '<?php echo e($_SESSION['full_name'] ?? 'System Admin'); ?>',
    sender_email: '<?php echo e($_SESSION['email'] ?? 'admin@abbis.africa'); ?>',
    company_phone: '+233 XX XXX XXXX',
    company_address: 'ABBIS Office, Accra',
    current_date: '<?php echo date('M d, Y'); ?>',
    current_time: '<?php echo date('g:i A'); ?>',
    maintenance_type: 'Preventive',
    maintenance_date: '<?php echo date('M d, Y'); ?>',
    maintenance_cost: '500.00',
    rpm_at_maintenance: '1250.00',
    next_maintenance_due: '<?php echo date('M d, Y', strtotime('+30 days')); ?>',
    payment_amount: '3000.00',
    payment_date: '<?php echo date('M d, Y'); ?>',
    payment_method: 'Mobile Money',
    invoice_number: 'INV-2024-001',
    receipt_number: 'RCP-2024-001',
    request_number: 'RR-2024-1001',
    requester_name: 'Ben Supervisor',
    requester_email: 'ben@example.com',
    requester_phone: '+233 20 876 5432',
    requester_type: 'Contractor',
    location_address: 'Kumasi, Ashanti Region',
    number_of_boreholes: '3',
    preferred_start_date: '<?php echo date('M d, Y', strtotime('+14 days')); ?>',
    urgency: 'High',
    estimated_budget: '120000.00'
};

// Available variables grouped by category
const variableGroups = {
    'Client': ['client_name', 'contact_name', 'contact_number', 'client_email', 'company_type', 'client_address', 'client_status'],
    'Field Report': ['report_id', 'report_date', 'site_name', 'job_type', 'total_depth', 'total_rpm', 'total_duration', 'rig_name', 'rig_code', 'location_description'],
    'Financial': ['contract_sum', 'rig_fee_charged', 'rig_fee_collected', 'total_income', 'total_expenses', 'net_profit', 'outstanding_balance', 'currency'],
    'Company': ['company_name', 'sender_name', 'sender_email', 'company_phone', 'company_address', 'current_date', 'current_time'],
    'Maintenance': ['maintenance_type', 'maintenance_date', 'maintenance_cost', 'rpm_at_maintenance', 'next_maintenance_due'],
    'Rig Request': ['request_number', 'requester_name', 'requester_email', 'requester_phone', 'requester_type', 'company_name', 'location_address', 'number_of_boreholes', 'estimated_budget', 'preferred_start_date', 'urgency'],
    'Payment': ['payment_amount', 'payment_date', 'payment_method', 'invoice_number', 'receipt_number']
};

function showTemplateModal(template = null) {
    const modal = document.getElementById('templateModal');
    const form = document.getElementById('templateForm');
    const title = document.getElementById('templateModalTitle');
    
    if (template) {
        title.textContent = 'Edit Email Template';
        document.getElementById('templateAction').value = 'update_template';
        document.getElementById('templateId').value = template.id;
        document.getElementById('templateActive').checked = template.is_active == 1;
        
        Object.keys(template).forEach(key => {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && key !== 'id' && key !== 'is_active') {
                input.value = template[key] || '';
            }
        });
    } else {
        title.textContent = 'Add Email Template';
        document.getElementById('templateAction').value = 'add_template';
        document.getElementById('templateId').value = '';
        form.reset();
        document.getElementById('templateActive').checked = true;
    }
    
    modal.style.display = 'flex';
}

function editTemplate(template) {
    showTemplateModal(template);
}

function deleteTemplate(id) {
    if (confirm('Are you sure you want to delete this template?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="delete_template">
            <input type="hidden" name="template_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function useTemplate(id) {
    // Check if we're on a client detail page
    const urlParams = new URLSearchParams(window.location.search);
    const clientId = urlParams.get('client_id');
    
    if (clientId) {
        // If on client detail page, open email modal with template
        if (typeof showEmailModal === 'function') {
            // Load template and open modal
            fetch(`crm.php?action=templates&get_template=${id}`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.template) {
                        // Open email modal on client detail page
                        showEmailModal(parseInt(clientId));
                        // Load template after a short delay to ensure modal is open
                        setTimeout(() => {
                            loadTemplate(id);
                        }, 300);
                    }
                })
                .catch(() => {
                    // Fallback: redirect to emails page
                    window.location.href = `crm.php?action=emails&compose=1&template_id=${id}`;
                });
        } else {
            // Fallback: redirect to emails page
            window.location.href = `crm.php?action=emails&compose=1&template_id=${id}&client_id=${clientId}`;
        }
    } else {
        // Not on client detail page, go to emails compose page
        window.location.href = `crm.php?action=emails&compose=1&template_id=${id}`;
    }
}

function copyTemplate(id) {
    // Fetch template and open in modal for editing
    fetch(`?action=templates&get_template=${id}`)
        .then(r => r.text())
        .then(html => {
            // Parse and extract template data, then open modal
            // For now, just redirect to create new with same category
            window.location.href = '?action=templates&copy=' + id;
        })
        .catch(() => {
            alert('Could not copy template. Please create a new one manually.');
        });
}

function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
}

function showVariablesGuide() {
    document.getElementById('variablesGuideModal').style.display = 'flex';
}

function closeVariablesGuide() {
    document.getElementById('variablesGuideModal').style.display = 'none';
}

function insertVariable(field, variable) {
    const input = document.getElementById(`template${field.charAt(0).toUpperCase() + field.slice(1)}`);
    if (input) {
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const text = input.value;
        input.value = text.substring(0, start) + variable + text.substring(end);
        input.focus();
        input.setSelectionRange(start + variable.length, start + variable.length);
    }
    hideVariableSelector();
}

function showVariableSelector(field) {
    const selector = document.getElementById('variableSelector');
    const list = document.getElementById('variableList');
    list.innerHTML = '';
    
    Object.keys(variableGroups).forEach(group => {
        const groupDiv = document.createElement('div');
        groupDiv.style.marginBottom = '8px';
        groupDiv.innerHTML = `<div style="font-weight: 600; color: var(--primary); margin-bottom: 4px; font-size: 11px;">${group}:</div>`;
        
        variableGroups[group].forEach(variable => {
            const item = document.createElement('div');
            item.className = 'variable-item';
            item.textContent = `{{${variable}}}`;
            item.onclick = () => insertVariable(field, `{{${variable}}}`);
            groupDiv.appendChild(item);
        });
        
        list.appendChild(groupDiv);
    });
    
    // Position selector near the button
    const button = event.target;
    const rect = button.getBoundingClientRect();
    selector.style.display = 'block';
    selector.style.top = (rect.bottom + 5) + 'px';
    selector.style.left = rect.left + 'px';
}

function hideVariableSelector() {
    document.getElementById('variableSelector').style.display = 'none';
}

function showTemplatePreview() {
    const subject = document.getElementById('templateSubject').value;
    const body = document.getElementById('templateBody').value;
    
    // Replace variables with sample data
    let previewSubject = subject;
    let previewBody = body;
    
    Object.keys(sampleData).forEach(key => {
        const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'g');
        previewSubject = previewSubject.replace(regex, sampleData[key]);
        previewBody = previewBody.replace(regex, sampleData[key]);
    });
    
    // Replace any remaining variables with placeholder
    previewSubject = previewSubject.replace(/\{\{(\w+)\}\}/g, '[Variable: $1]');
    previewBody = previewBody.replace(/\{\{(\w+)\}\}/g, '[Variable: $1]');
    
    document.getElementById('previewSubject').textContent = previewSubject;
    document.getElementById('previewBody').innerHTML = previewBody.replace(/\n/g, '<br>');
    document.getElementById('templatePreviewModal').style.display = 'flex';
}

function closeTemplatePreview() {
    document.getElementById('templatePreviewModal').style.display = 'none';
}

function previewTemplate(id) {
    // Fetch template and show preview
    fetch(`?action=templates&preview=${id}`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                document.getElementById('previewSubject').textContent = data.subject;
                document.getElementById('previewBody').innerHTML = data.body.replace(/\n/g, '<br>');
                document.getElementById('templatePreviewModal').style.display = 'flex';
            }
        })
        .catch(() => {
            alert('Could not load template preview.');
        });
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const modals = ['templateModal', 'variablesGuideModal', 'templatePreviewModal'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && event.target === modal) {
            if (modalId === 'templateModal') closeTemplateModal();
            if (modalId === 'variablesGuideModal') closeVariablesGuide();
            if (modalId === 'templatePreviewModal') closeTemplatePreview();
        }
    });
    
    // Hide variable selector if clicking outside
    const selector = document.getElementById('variableSelector');
    if (selector && !selector.contains(event.target) && !event.target.closest('button[onclick*="showVariableSelector"]')) {
        hideVariableSelector();
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTemplateModal();
        closeVariablesGuide();
        closeTemplatePreview();
        hideVariableSelector();
    }
});
</script>
