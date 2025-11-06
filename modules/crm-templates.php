<?php
/**
 * Email Templates Management
 */
$pdo = getDBConnection();

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
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO email_templates (name, subject, body, category, created_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$name, $subject, $body, $category, $_SESSION['user_id']]);
                    
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
                    
                    $stmt = $pdo->prepare("
                        UPDATE email_templates 
                        SET name = ?, subject = ?, body = ?, category = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $subject, $body, $category, $isActive, $templateId]);
                    
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
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?>">
        <?php echo e($message); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
    <h2 style="margin: 0;">📝 Email Templates</h2>
    <button onclick="showTemplateModal()" class="btn btn-primary">➕ Add Template</button>
</div>

<div class="dashboard-card">
    <?php if (empty($templates)): ?>
        <p style="text-align: center; padding: 40px; color: var(--secondary);">
            No templates found. <a href="#" onclick="showTemplateModal(); return false;">Create your first template</a>
        </p>
    <?php else: ?>
        <?php foreach ($templatesByCategory as $category => $categoryTemplates): ?>
            <div style="margin-bottom: 30px;">
                <h3 style="margin-bottom: 15px; color: var(--primary); text-transform: capitalize;">
                    <?php echo e($category); ?>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                    <?php foreach ($categoryTemplates as $template): ?>
                    <div style="border: 1px solid var(--border); border-radius: 8px; padding: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
                            <div>
                                <strong><?php echo e($template['name']); ?></strong>
                                <?php if (!$template['is_active']): ?>
                                    <span style="color: var(--secondary); font-size: 12px;">(Inactive)</span>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 5px;">
                                <button onclick="editTemplate(<?php echo htmlspecialchars(json_encode($template)); ?>)" class="btn btn-sm btn-outline">Edit</button>
                                <button onclick="deleteTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-danger">Delete</button>
                            </div>
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong style="font-size: 12px; color: var(--secondary);">Subject:</strong><br>
                            <div style="font-size: 13px;"><?php echo e($template['subject']); ?></div>
                        </div>
                        <div>
                            <strong style="font-size: 12px; color: var(--secondary);">Preview:</strong><br>
                            <div style="font-size: 12px; color: var(--secondary); margin-top: 5px;">
                                <?php echo e(substr(strip_tags($template['body']), 0, 100)); ?>...
                            </div>
                        </div>
                        <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border);">
                            <button onclick="useTemplate(<?php echo $template['id']; ?>)" class="btn btn-sm btn-primary" style="width: 100%;">
                                Use Template
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
<div id="templateModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: var(--card); color: var(--text); padding: 30px; border-radius: 12px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border);">
        <h2 id="templateModalTitle">Add Email Template</h2>
        <form method="POST" id="templateForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" id="templateAction" value="add_template">
            <input type="hidden" name="template_id" id="templateId">
            
            <div style="margin-top: 20px;">
                <div class="form-group">
                    <label class="form-label">Template Name *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="general">General</option>
                        <option value="welcome">Welcome</option>
                        <option value="followup">Follow-up</option>
                        <option value="quote">Quote</option>
                        <option value="proposal">Proposal</option>
                        <option value="invoice">Invoice</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-control" required placeholder="Use {{variable_name}} for dynamic content">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Body *</label>
                    <textarea name="body" class="form-control" rows="10" required placeholder="Use {{variable_name}} for dynamic content"></textarea>
                    <small class="form-text">Available variables: {{client_name}}, {{contact_name}}, {{company_name}}, {{sender_name}}, etc.</small>
                </div>
                
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 8px;">
                        <input type="checkbox" name="is_active" id="templateActive" checked>
                        <span>Template is active</span>
                    </label>
                </div>
            </div>
            
            <div style="margin-top: 20px; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeTemplateModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Template</button>
            </div>
        </form>
    </div>
</div>

<script>
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
            if (input) {
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
    window.location.href = '?action=emails&compose=1&template_id=' + id;
}

function closeTemplateModal() {
    document.getElementById('templateModal').style.display = 'none';
}

document.getElementById('templateModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeTemplateModal();
    }
});
</script>

