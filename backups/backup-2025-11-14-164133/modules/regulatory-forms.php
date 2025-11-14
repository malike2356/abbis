<?php
$page_title = 'Regulatory Forms Automation';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/navigation-tracker.php';
require_once __DIR__ . '/../includes/Forms/RegulatoryFormRenderer.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

$pdo = getDBConnection();
$csrfField = CSRF::getTokenField();

// Fetch templates
$templates = [];
try {
    $stmt = $pdo->query("SELECT * FROM regulatory_form_templates ORDER BY created_at DESC");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $templates = [];
}

// Fetch recent exports
$exports = [];
try {
    $sql = "
        SELECT e.*, t.form_name, t.jurisdiction, u.full_name AS generated_by_name
        FROM regulatory_form_exports e
        LEFT JOIN regulatory_form_templates t ON t.id = e.template_id
        LEFT JOIN users u ON u.id = e.generated_by
        ORDER BY e.generated_at DESC
        LIMIT 15
    ";
    $exports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $exports = [];
}

NavigationTracker::recordCurrentPage((int)$_SESSION['user_id']);
require_once '../includes/header.php';
?>

<div class="module-container">
    <style>
        .forms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
        }
        .form-card {
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--card);
            box-shadow: var(--shadow-sm, 0 12px 30px rgba(15,23,42,0.08));
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .template-list {
            margin-top: 24px;
        }
        .template-item {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, rgba(59,130,246,0.04), rgba(59,130,246,0.01));
        }
        .template-item h3 {
            margin: 0 0 4px;
            font-size: 18px;
        }
        .label-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            font-size: 12px;
            border-radius: 999px;
            background: rgba(37,99,235,0.12);
            color: #1d4ed8;
            margin-right: 8px;
        }
        .code-block {
            background: rgba(15,23,42,0.92);
            color: #e2e8f0;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            overflow-x: auto;
        }
        .split-layout {
            display: grid;
            grid-template-columns: minmax(320px, 360px) minmax(0, 1fr);
            gap: 24px;
            margin-top: 28px;
        }
        .textarea-mono {
            width: 100%;
            min-height: 320px;
            font-family: "Fira Code", "Courier New", monospace;
            font-size: 13px;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 14px;
            background: rgba(15, 23, 42, 0.02);
            resize: vertical;
        }
        @media (max-width: 992px) {
            .split-layout {
                grid-template-columns: 1fr;
            }
        }
        .tab-nav {
            display: inline-flex;
            gap: 12px;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 6px;
            margin-bottom: 20px;
            background: rgba(148,163,184,0.08);
        }
        .tab-nav button {
            border: none;
            background: transparent;
            padding: 8px 18px;
            border-radius: 999px;
            font-weight: 600;
            color: var(--secondary);
            cursor: pointer;
        }
        .tab-nav button.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 20px rgba(37,99,235,0.25);
        }
        .tab-panel {
            display: none;
        }
        .tab-panel.active {
            display: block;
        }
    </style>

    <div class="page-heading">
        <h1>Regulatory Form Generator</h1>
        <p class="page-subtitle">
            Design government or compliance forms with merge tags and generate them instantly from field reports, rigs, or clients.
        </p>
    </div>

    <div class="tab-nav">
        <button type="button" class="active" data-tab="templatesTab">Templates</button>
        <button type="button" data-tab="generateTab">Generate Form</button>
        <button type="button" data-tab="logsTab">Generation Log</button>
    </div>

    <div id="templatesTab" class="tab-panel active">
        <div class="split-layout">
            <div class="form-card">
                <h2>Create Template</h2>
                <p style="color: var(--secondary);">
                    Define the HTML layout and merge fields. Use the reference cheatsheet to insert values from field reports, rigs, or clients.
                </p>
                <form method="post" action="<?php echo api_url('regulatory-forms.php'); ?>" class="ajax-form">
                    <?php echo $csrfField; ?>
                    <input type="hidden" name="action" value="create_template">
                    <div class="form-group">
                        <label class="form-label">Form Name *</label>
                        <input type="text" name="form_name" class="form-control" placeholder="e.g., Ghana Borehole Completion Report" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Jurisdiction</label>
                        <input type="text" name="jurisdiction" class="form-control" placeholder="e.g., Ghana | Water Resources Commission">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Short summary of when or why this form is used."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference Type</label>
                        <select name="reference_type" class="form-control">
                            <option value="field_report">Field Report</option>
                            <option value="rig">Rig</option>
                            <option value="client">Client</option>
                            <option value="custom">Custom (manual data)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Template HTML *</label>
                        <textarea name="html_template" class="textarea-mono" placeholder="Use {{field_report.report_id}}, {{company.company_name}}, etc." required></textarea>
                        <p style="color: var(--secondary); font-size: 13px; margin-top: 8px;">
                            Tip: wrap subsections in <code>&lt;table&gt;</code> to align like a PDF form. Use <code>{{ generated_at }}</code> for timestamps.
                        </p>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Instructions</label>
                        <textarea name="instructions" class="form-control" rows="3" placeholder="Internal notes or reminders about when to use this form."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="is_active" class="form-control">
                            <option value="1" selected>Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Template</button>
                </form>
            </div>
            <div class="form-card">
                <h2>Merge Field Reference</h2>
                <p style="color: var(--secondary);">Use these merge tags inside your templates:</p>
                <div class="code-block">
                    {{ company.company_name }}<br>
                    {{ company.company_email }}<br>
                    {{ field_report.report_id }}<br>
                    {{ field_report.site_name }}<br>
                    {{ field_report.region }}<br>
                    {{ field_report.depth_total }}<br>
                    {{ field_report.total_income }}<br>
                    {{ field_report.rig_name }}<br>
                    {{ field_report.client_name }}<br>
                    {{ field_report.client_contact }}<br>
                    {{ rig.rig_name }}<br>
                    {{ rig.truck_model }}<br>
                    {{ client.client_name }}<br>
                    {{ client.address }}<br>
                    {{ generated_at }}
                </div>
                <p style="color: var(--secondary); margin-top: 12px;">
                    You can also create custom placeholders like <code>{{ context.driller_name }}</code> when generating the form.
                </p>
            </div>
        </div>

        <div class="template-list">
            <h2 style="margin-bottom:12px;">Existing Templates</h2>
            <?php if (empty($templates)): ?>
                <div class="empty-state-card">No templates yet. Create one to get started.</div>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <div class="template-item" data-template='<?php echo json_encode($template, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>'>
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                            <div>
                                <h3><?php echo htmlspecialchars($template['form_name']); ?></h3>
                                <div style="margin:6px 0;">
                                    <span class="label-pill"><?php echo htmlspecialchars(strtoupper($template['reference_type'])); ?></span>
                                    <?php if (!empty($template['jurisdiction'])): ?>
                                        <span class="label-pill" style="background:rgba(14,165,233,0.12); color:#0369a1;"><?php echo htmlspecialchars($template['jurisdiction']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!$template['is_active']): ?>
                                        <span class="label-pill" style="background: rgba(148,163,184,0.2); color:#64748b;">Inactive</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($template['description'])): ?>
                                    <p style="color:var(--secondary); margin:8px 0 0;"><?php echo nl2br(htmlspecialchars($template['description'])); ?></p>
                                <?php endif; ?>
                            </div>
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <button type="button" class="btn btn-outline btn-sm" onclick="openEditTemplate(<?php echo (int)$template['id']; ?>)">Edit</button>
                                <button type="button" class="btn btn-outline btn-sm" onclick="duplicateTemplate(<?php echo (int)$template['id']; ?>)">Duplicate</button>
                            </div>
                        </div>
                        <?php if (!empty($template['instructions'])): ?>
                            <div style="margin-top:12px; color:var(--secondary); font-size:13px;">
                                <strong>Instructions:</strong> <?php echo nl2br(htmlspecialchars($template['instructions'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div id="generateTab" class="tab-panel">
        <div class="forms-grid">
            <div class="form-card">
                <h2>Generate Form</h2>
                <p style="color:var(--secondary);">
                    Pick a template and the source record (field report, rig, or client). You can add optional context values before generating.
                </p>
                <form id="generateForm" class="form">
                    <?php echo $csrfField; ?>
                    <div class="form-group">
                        <label class="form-label">Template *</label>
                        <select name="template_id" id="genTemplate" class="form-control" required>
                            <option value="">Select template</option>
                            <?php foreach ($templates as $template): ?>
                                <?php if (!$template['is_active']) continue; ?>
                                <option value="<?php echo (int)$template['id']; ?>"
                                        data-reference="<?php echo htmlspecialchars($template['reference_type']); ?>">
                                    <?php echo htmlspecialchars($template['form_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reference Record</label>
                        <div id="referenceSelector">
                            <input type="number" name="reference_id" class="form-control" placeholder="Enter record ID (e.g., field report ID)">
                        </div>
                        <small style="color: var(--secondary); font-size: 13px;">Use the ID from ABBIS (e.g., Field Report ID). Future releases will include search pickers.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Context JSON (optional)</label>
                        <textarea name="context_json" class="textarea-mono" style="min-height: 140px;" placeholder="{&#10;  &quot;driller_name&quot;: &quot;John Doe&quot;,&#10;  &quot;inspection_date&quot;: &quot;2025-01-12&quot;&#10;}"></textarea>
                        <small style="color: var(--secondary); font-size:13px;">Add extra data accessible via <code>{{ context.variable }}</code> in the template.</small>
                    </div>
                    <button type="button" id="generateButton" class="btn btn-primary">Generate Form</button>
                </form>
            </div>
            <div class="form-card" id="previewPanel" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <h2 style="margin:0;">Preview</h2>
                    <button class="btn btn-outline btn-sm" id="downloadButton">Download HTML</button>
                </div>
                <div id="previewContent" style="margin-top:16px; border:1px solid var(--border); border-radius:12px; padding:20px; max-height:500px; overflow:auto; background:#fff;"></div>
            </div>
        </div>
    </div>

    <div id="logsTab" class="tab-panel">
        <div class="form-card">
            <h2>Recent Form Generations</h2>
            <?php if (empty($exports)): ?>
                <div class="empty-state-card">No generation history yet.</div>
            <?php else: ?>
                <table class="geo-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Template</th>
                            <th>Reference</th>
                            <th>By</th>
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exports as $export): ?>
                            <tr>
                                <td><?php echo date('Y-m-d H:i', strtotime($export['generated_at'])); ?></td>
                                <td><?php echo htmlspecialchars($export['form_name'] ?? 'Deleted Template'); ?></td>
                                <td><?php echo htmlspecialchars(strtoupper($export['reference_type'])); ?> #<?php echo (int)$export['reference_id']; ?></td>
                                <td><?php echo htmlspecialchars($export['generated_by_name'] ?: 'System'); ?></td>
                                <td>
                                    <?php if (!empty($export['output_path']) && file_exists(ROOT_PATH . '/' . ltrim($export['output_path'], '/'))): ?>
                                        <a href="<?php echo htmlspecialchars($export['output_path']); ?>" target="_blank" rel="noopener">Download</a>
                                    <?php else: ?>
                                        <span style="color: var(--secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="editTemplateModal" class="modal" style="display:none;">
    <div class="modal-content" style="max-width: 720px;">
        <div class="modal-header">
            <h2>Edit Template</h2>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editTemplateForm" method="post" action="<?php echo api_url('regulatory-forms.php'); ?>" class="ajax-form">
            <?php echo $csrfField; ?>
            <input type="hidden" name="action" value="update_template">
            <input type="hidden" name="template_id" value="">
            <div class="form-row" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap:16px;">
                <div class="form-group">
                    <label class="form-label">Form Name *</label>
                    <input type="text" name="form_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Jurisdiction</label>
                    <input type="text" name="jurisdiction" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Type</label>
                    <select name="reference_type" class="form-control">
                        <option value="field_report">Field Report</option>
                        <option value="rig">Rig</option>
                        <option value="client">Client</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="is_active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Instructions</label>
                <textarea name="instructions" class="form-control" rows="3"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Template HTML *</label>
                <textarea name="html_template" class="textarea-mono" required></textarea>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:16px;">
                <button type="button" class="btn btn-danger btn-sm" onclick="deleteTemplate()">Delete</button>
                <div style="display:flex; gap:12px;">
                    <button type="button" class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const tabs = document.querySelectorAll('.tab-nav button');
    const panels = document.querySelectorAll('.tab-panel');
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(btn => btn.classList.remove('active'));
            panels.forEach(panel => panel.classList.remove('active'));
            tab.classList.add('active');
            document.getElementById(tab.dataset.tab).classList.add('active');
        });
    });

    const generateButton = document.getElementById('generateButton');
    const generateForm = document.getElementById('generateForm');
    const previewPanel = document.getElementById('previewPanel');
    const previewContent = document.getElementById('previewContent');
    const downloadButton = document.getElementById('downloadButton');
    let lastDownloadUrl = null;

    generateButton.addEventListener('click', async () => {
        const formData = new FormData(generateForm);
        if (!formData.get('template_id')) {
            alert('Please select a template.');
            return;
        }
        generateButton.disabled = true;
        generateButton.textContent = 'Generating…';
        previewPanel.style.display = 'none';
        previewContent.innerHTML = '';
        lastDownloadUrl = null;

        try {
            const contextJson = formData.get('context_json');
            let contextData = null;
            if (contextJson) {
                try {
                    contextData = JSON.parse(contextJson);
                } catch (e) {
                    alert('Context JSON is invalid.');
                    return;
                }
            }

            const payload = {
                csrf_token: formData.get('csrf_token'),
                template_id: formData.get('template_id'),
                reference_id: formData.get('reference_id') || null,
                context: contextData
            };

            const response = await fetch('../api/regulatory-form-generate.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to generate form');
            }

            previewContent.innerHTML = result.html;
            previewPanel.style.display = 'block';
            lastDownloadUrl = result.download_url || null;

            if (lastDownloadUrl) {
                downloadButton.style.display = 'inline-flex';
            } else {
                downloadButton.style.display = 'none';
            }
        } catch (error) {
            alert(error.message || 'Unexpected error while generating form.');
        } finally {
            generateButton.disabled = false;
            generateButton.textContent = 'Generate Form';
        }
    });

    downloadButton.addEventListener('click', () => {
        if (lastDownloadUrl) {
            window.open(lastDownloadUrl, '_blank');
        }
    });
})();

const templateIndex = {};
document.querySelectorAll('.template-item').forEach(item => {
    const data = item.dataset.template ? JSON.parse(item.dataset.template) : null;
    if (data) {
        templateIndex[data.id] = data;
    }
});

const editModal = document.getElementById('editTemplateModal');
const editForm = document.getElementById('editTemplateForm');

function openEditTemplate(id) {
    const data = templateIndex[id];
    if (!data) {
        alert('Template not available.');
        return;
    }
    editForm.template_id.value = data.id;
    editForm.form_name.value = data.form_name || '';
    editForm.jurisdiction.value = data.jurisdiction || '';
    editForm.reference_type.value = data.reference_type || 'field_report';
    editForm.is_active.value = data.is_active === undefined || data.is_active === null ? '1' : String(data.is_active);
    editForm.description.value = data.description || '';
    editForm.instructions.value = data.instructions || '';
    editForm.html_template.value = data.html_template || '';
    editModal.style.display = 'flex';
}

function closeEditModal() {
    editModal.style.display = 'none';
}

function deleteTemplate() {
    if (!confirm('Delete this template? This cannot be undone.')) {
        return;
    }
    const formData = new FormData(editForm);
    formData.set('action', 'delete_template');
    fetch('../api/regulatory-forms.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json()).then(result => {
        if (!result.success) {
            throw new Error(result.message || 'Unable to delete template');
        }
        location.reload();
    }).catch(error => {
        alert(error.message);
    });
}

function duplicateTemplate(id) {
    const data = templateIndex[id];
    if (!data) {
        alert('Template not available.');
        return;
    }
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
    formData.append('action', 'duplicate_template');
    formData.append('template_id', id);
    fetch('../api/regulatory-forms.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json()).then(result => {
        if (!result.success) {
            throw new Error(result.message || 'Unable to duplicate template');
        }
        location.reload();
    }).catch(error => alert(error.message));
}

window.openEditTemplate = openEditTemplate;
window.closeEditModal = closeEditModal;
window.deleteTemplate = deleteTemplate;
window.duplicateTemplate = duplicateTemplate;
</script>

<?php require_once '../includes/footer.php'; ?>

