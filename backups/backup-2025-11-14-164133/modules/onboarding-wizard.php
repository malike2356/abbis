<?php
/**
 * ABBIS Onboarding Wizard - guided CSV imports for key datasets.
 */
$page_title = 'Onboarding Wizard';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../includes/Import/ImportManager.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$importManager = new ImportManager();
$definitions = ImportManager::getDefinitions();

// Sanitise definitions for frontend consumption.
$clientDefinitions = [];
foreach ($definitions as $key => $definition) {
    $fields = [];
    foreach ($definition['fields'] as $fieldKey => $field) {
        $fields[$fieldKey] = [
            'label' => $field['label'] ?? $fieldKey,
            'required' => !empty($field['required']),
            'type' => $field['type'] ?? 'string',
            'allowed_values' => $field['allowed_values'] ?? [],
        ];
    }

    $clientDefinitions[$key] = [
        'label' => $definition['label'] ?? ucfirst($key),
        'icon' => $definition['icon'] ?? '📄',
        'description' => $definition['description'] ?? '',
        'fields' => $fields,
    ];
}

require_once '../includes/header.php';
?>

<style>
    .wizard-container {
        max-width: 1100px;
        margin: 0 auto;
    }
    .wizard-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin: 20px 0 30px 0;
    }
    .wizard-step {
        background: var(--card);
        border: 2px solid var(--border);
        border-radius: 10px;
        padding: 16px;
        text-align: center;
        font-weight: 600;
        color: var(--secondary);
        transition: all 0.2s;
    }
    .wizard-step.active {
        border-color: var(--primary);
        color: var(--text);
        box-shadow: 0 6px 14px rgba(0, 0, 0, 0.08);
    }
    .dataset-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
    }
    .dataset-card {
        background: var(--card);
        border: 2px solid var(--border);
        border-radius: 12px;
        padding: 22px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .dataset-card:hover {
        transform: translateY(-2px);
        border-color: var(--primary);
        box-shadow: 0 8px 18px rgba(0, 0, 0, 0.12);
    }
    .dataset-card.selected {
        border-color: var(--primary);
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.12), rgba(14, 165, 233, 0.02));
    }
    .dataset-card .icon {
        font-size: 40px;
        margin-bottom: 12px;
    }
    .step-panel {
        display: none;
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 24px;
        margin-top: 20px;
    }
    .step-panel.active {
        display: block;
    }
    .mapping-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .mapping-table th, .mapping-table td {
        padding: 12px;
        border: 1px solid var(--border);
        vertical-align: top;
    }
    .mapping-table th {
        background: var(--table-header);
        text-align: left;
        font-size: 14px;
    }
    .mapping-table td select {
        width: 100%;
        padding: 8px;
    }
    .mapping-required {
        color: #dc2626;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    .preview-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
        font-size: 13px;
    }
    .preview-table th, .preview-table td {
        padding: 8px 10px;
        border: 1px solid var(--border);
        overflow-wrap: anywhere;
    }
    .wizard-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
    }
    .result-summary {
        background: linear-gradient(135deg, rgba(34, 197, 94, 0.12), rgba(34, 197, 94, 0.02));
        border: 1px solid rgba(34, 197, 94, 0.3);
        border-radius: 12px;
        padding: 20px;
    }
    .result-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: 16px;
        margin-top: 12px;
    }
    .result-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px 16px;
        text-align: center;
    }
    .result-card h3 {
        margin: 0;
        font-size: 28px;
        color: var(--primary);
    }
    .result-card span {
        display: block;
        margin-top: 4px;
        font-size: 13px;
        color: var(--secondary);
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }
    .error-list {
        margin-top: 18px;
        border-left: 4px solid #dc2626;
        padding: 12px 16px;
        background: #fef2f2;
        color: #7f1d1d;
    }
    .delimiter-select {
        max-width: 200px;
    }
    .toggle-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--muted);
        border-radius: 999px;
        padding: 6px 12px;
        font-size: 13px;
    }
    .toggle-pill input {
        accent-color: var(--primary);
    }
</style>

<div class="wizard-container">
    <div class="page-header">
        <div>
            <h1>🚀 ABBIS Onboarding Wizard</h1>
            <p>Import your legacy data for clients, rigs, workers, and catalog items. The wizard guides you through preview, column mapping, and final import.</p>
        </div>
    </div>

    <div class="wizard-steps">
        <div class="wizard-step active" data-step-label="1">Step 1 · Upload CSV</div>
        <div class="wizard-step" data-step-label="2">Step 2 · Map Columns</div>
        <div class="wizard-step" data-step-label="3">Step 3 · Review & Import</div>
    </div>

    <div class="dashboard-card">
        <h2>1️⃣ Choose dataset</h2>
        <p style="color: var(--secondary); margin-bottom: 18px;">Each dataset has its own required fields. Hover to learn more, then click to select.</p>
        <div class="dataset-grid" id="datasetGrid">
            <?php foreach ($clientDefinitions as $key => $definition): ?>
                <div class="dataset-card" data-dataset="<?php echo htmlspecialchars($key); ?>">
                    <div class="icon"><?php echo htmlspecialchars($definition['icon']); ?></div>
                    <h3 style="margin-bottom: 6px;"><?php echo htmlspecialchars($definition['label']); ?></h3>
                    <p style="color: var(--secondary); font-size: 14px; line-height: 1.5;"><?php echo htmlspecialchars($definition['description']); ?></p>
                    <div style="margin-top: 12px; font-size: 13px; color: var(--secondary);">
                        <strong>Required fields:</strong>
                        <?php
                        $requiredFields = array_filter($definition['fields'], fn($field) => !empty($field['required']));
                        echo htmlspecialchars(implode(', ', array_map(fn($field) => $field['label'], $requiredFields)));
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="step-panel active" id="stepPanel1">
        <form id="uploadForm" class="form" enctype="multipart/form-data" method="POST">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="dataset" id="datasetInput">
            <div class="form-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div class="form-group">
                    <label for="csvFileInput" class="form-label">CSV File</label>
                    <input type="file" id="csvFileInput" name="csv_file" class="form-control" accept=".csv,.txt" required>
                    <small class="form-text">CSV with headers in the first row. Maximum size 10MB.</small>
                </div>
                <div class="form-group">
                    <label for="delimiterSelect" class="form-label">Delimiter</label>
                    <select id="delimiterSelect" name="delimiter" class="form-control delimiter-select">
                        <option value=",">Comma (,)</option>
                        <option value=";" >Semicolon (;)</option>
                        <option value="\t">Tab</option>
                        <option value="|">Pipe (|)</option>
                    </select>
                    <small class="form-text">Pick the separated used in your file.</small>
                </div>
            </div>

            <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-top: 12px;">
                <label class="toggle-pill">
                    <input type="checkbox" id="updateExistingToggle" checked>
                    <span>Update existing records when matches are found</span>
                </label>
                <label class="toggle-pill">
                    <input type="checkbox" id="skipBlankToggle" checked>
                    <span>Ignore blank cells when updating existing data</span>
                </label>
            </div>

            <div class="wizard-actions" style="margin-top: 24px;">
                <button type="submit" class="btn btn-primary" id="previewButton" disabled>
                    📤 Upload & Preview
                </button>
            </div>
        </form>
        <div id="step1Feedback" style="margin-top: 16px;"></div>
    </div>

    <div class="step-panel" id="stepPanel2">
        <h2>2️⃣ Map CSV columns</h2>
        <p style="color: var(--secondary);">Match each ABBIS field to the corresponding column in your file. Required fields must be mapped before proceeding.</p>

        <div id="mappingContainer"></div>
        <div id="previewContainer" style="margin-top: 24px;"></div>

        <div class="wizard-actions">
            <button type="button" class="btn btn-outline" id="backToUpload">
                ← Back
            </button>
            <button type="button" class="btn btn-primary" id="proceedToImport">
                Continue →
            </button>
        </div>
        <div id="step2Feedback" style="margin-top: 16px;"></div>
    </div>

    <div class="step-panel" id="stepPanel3">
        <h2>3️⃣ Review & Import</h2>
        <p style="color: var(--secondary);">Review the summary below, then run the import. We will show results and any errors for quick fixes.</p>
        <div class="wizard-actions" style="justify-content: flex-start;">
            <button type="button" class="btn btn-outline" id="editMapping">
                ← Adjust Mapping
            </button>
            <button type="button" class="btn btn-primary" id="runImportButton">
                ✅ Run Import
            </button>
        </div>
        <div id="importSummary" style="margin-top: 24px;"></div>
    </div>
</div>

<script>
(() => {
    const definitions = <?php echo json_encode($clientDefinitions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>;
    const wizardSteps = document.querySelectorAll('.wizard-step');
    const datasetCards = document.querySelectorAll('.dataset-card');
    const datasetInput = document.getElementById('datasetInput');
    const uploadForm = document.getElementById('uploadForm');
    const previewButton = document.getElementById('previewButton');
    const stepPanels = {
        1: document.getElementById('stepPanel1'),
        2: document.getElementById('stepPanel2'),
        3: document.getElementById('stepPanel3'),
    };
    const step1Feedback = document.getElementById('step1Feedback');
    const step2Feedback = document.getElementById('step2Feedback');
    const mappingContainer = document.getElementById('mappingContainer');
    const previewContainer = document.getElementById('previewContainer');
    const proceedToImportBtn = document.getElementById('proceedToImport');
    const runImportButton = document.getElementById('runImportButton');
    const editMappingButton = document.getElementById('editMapping');
    const backToUploadButton = document.getElementById('backToUpload');
    const importSummary = document.getElementById('importSummary');
    const updateExistingToggle = document.getElementById('updateExistingToggle');
    const skipBlankToggle = document.getElementById('skipBlankToggle');
    const delimiterSelect = document.getElementById('delimiterSelect');
    const csvFileInput = document.getElementById('csvFileInput');

    let selectedDataset = null;
    let currentToken = null;
    let currentPreview = null;
    let currentMapping = {};

    const csrfToken = uploadForm.querySelector('input[name="csrf_token"]').value;

    function setActiveStep(stepNumber) {
        wizardSteps.forEach(step => {
            const label = step.getAttribute('data-step-label');
            if (label === String(stepNumber)) {
                step.classList.add('active');
            } else {
                step.classList.remove('active');
            }
        });
        Object.entries(stepPanels).forEach(([key, panel]) => {
            panel.classList.toggle('active', parseInt(key, 10) === stepNumber);
        });
    }

    function resetAfterDatasetChange() {
        currentToken = null;
        currentPreview = null;
        currentMapping = {};
        step1Feedback.innerHTML = '';
        step2Feedback.innerHTML = '';
        mappingContainer.innerHTML = '';
        previewContainer.innerHTML = '';
        importSummary.innerHTML = '';
        csvFileInput.value = '';
        previewButton.disabled = !selectedDataset;
        setActiveStep(1);
    }

    datasetCards.forEach(card => {
        card.addEventListener('click', () => {
            datasetCards.forEach(c => c.classList.remove('selected'));
            card.classList.add('selected');
            selectedDataset = card.getAttribute('data-dataset');
            datasetInput.value = selectedDataset;
            resetAfterDatasetChange();
        });
    });

    csvFileInput.addEventListener('change', () => {
        if (!selectedDataset) {
            previewButton.disabled = true;
            return;
        }
        previewButton.disabled = csvFileInput.files.length === 0;
    });

    uploadForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        step1Feedback.innerHTML = '';

        if (!selectedDataset) {
            step1Feedback.innerHTML = '<div class="alert alert-danger">Select a dataset before uploading.</div>';
            return;
        }

        if (csvFileInput.files.length === 0) {
            step1Feedback.innerHTML = '<div class="alert alert-danger">Choose a CSV file to upload.</div>';
            return;
        }

        const formData = new FormData(uploadForm);
        formData.set('dataset', selectedDataset);
        formData.set('delimiter', delimiterSelect.value);

        previewButton.disabled = true;
        previewButton.innerHTML = '⏳ Uploading...';

        try {
            const response = await fetch('../api/import-preview.php', {
                method: 'POST',
                body: formData,
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Preview failed');
            }

            currentToken = result.token;
            currentPreview = result.preview;
            currentMapping = result.preview.suggested_mapping || {};

            renderMappingTable();
            renderPreviewTable();

            step1Feedback.innerHTML = `<div class="alert alert-success">✅ CSV uploaded successfully. ${currentPreview.total_rows} row(s) detected.</div>`;
            setActiveStep(2);
        } catch (error) {
            console.error(error);
            step1Feedback.innerHTML = `<div class="alert alert-danger">❌ ${error.message}</div>`;
        } finally {
            previewButton.disabled = false;
            previewButton.innerHTML = '📤 Upload & Preview';
        }
    });

    function renderMappingTable() {
        if (!selectedDataset || !currentPreview) {
            mappingContainer.innerHTML = '';
            return;
        }

        const definition = definitions[selectedDataset];
        const headers = currentPreview.headers || [];
        const suggestedMapping = currentPreview.suggested_mapping || {};

        let tableHtml = '<table class="mapping-table">';
        tableHtml += '<tr><th style="width: 260px;">ABBIS Field</th><th>CSV Column</th><th style="width: 220px;">Details</th></tr>';

        Object.entries(definition.fields).forEach(([fieldKey, field]) => {
            const selectedValue = currentMapping[fieldKey] || suggestedMapping[fieldKey] || '';
            tableHtml += '<tr>';
            tableHtml += `<td><strong>${field.label}</strong><br>`;
            if (field.required) {
                tableHtml += `<span class="mapping-required">Required</span>`;
            } else {
                tableHtml += `<span style="color: var(--secondary); font-size: 12px;">Optional</span>`;
            }
            tableHtml += '</td>';

            tableHtml += `<td><select class="form-control mapping-select" data-field="${fieldKey}">`;
            tableHtml += '<option value="">-- Select column --</option>';
            headers.forEach(header => {
                const isSelected = selectedValue === header ? 'selected' : '';
                tableHtml += `<option value="${header.replace(/"/g, '&quot;')}" ${isSelected}>${header}</option>`;
            });
            tableHtml += '<option value="__skip__">Skip column</option>';
            tableHtml += '</select></td>';

            tableHtml += '<td>';
            tableHtml += `<div style="font-size: 12px; color: var(--secondary); line-height: 1.6;">Type: <strong>${field.type}</strong>`;
            if (field.allowed_values && field.allowed_values.length > 0) {
                tableHtml += `<br>Allowed: ${field.allowed_values.join(', ')}`;
            }
            tableHtml += '</div></td>';
            tableHtml += '</tr>';
        });

        tableHtml += '</table>';
        mappingContainer.innerHTML = tableHtml;

        document.querySelectorAll('.mapping-select').forEach(select => {
            select.addEventListener('change', () => {
                currentMapping[select.dataset.field] = select.value;
            });
        });
    }

    function renderPreviewTable() {
        if (!currentPreview || !currentPreview.sample_rows || currentPreview.sample_rows.length === 0) {
            previewContainer.innerHTML = '';
            return;
        }

        const rows = currentPreview.sample_rows;
        const headers = currentPreview.headers;

        let html = '<h3 style="margin-top: 20px;">Sample Preview</h3>';
        html += '<p style="color: var(--secondary); font-size: 13px;">Showing up to ' + rows.length + ' row(s). We detected ' + (currentPreview.total_rows || rows.length) + ' total rows.</p>';
        html += '<div style="overflow-x: auto;"><table class="preview-table">';
        html += '<tr>';
        headers.forEach(header => {
            html += `<th>${header}</th>`;
        });
        html += '</tr>';

        rows.forEach(row => {
            html += '<tr>';
            headers.forEach(header => {
                html += `<td>${row[header] !== null && row[header] !== undefined ? row[header] : ''}</td>`;
            });
            html += '</tr>';
        });

        html += '</table></div>';
        previewContainer.innerHTML = html;
    }

    proceedToImportBtn.addEventListener('click', () => {
        step2Feedback.innerHTML = '';
        if (!validateMapping()) {
            return;
        }
        buildSummarySection();
        setActiveStep(3);
    });

    editMappingButton.addEventListener('click', () => {
        setActiveStep(2);
    });

    backToUploadButton.addEventListener('click', () => {
        setActiveStep(1);
    });

    function validateMapping() {
        if (!selectedDataset || !currentPreview) {
            step2Feedback.innerHTML = '<div class="alert alert-danger">Upload a CSV file first.</div>';
            return false;
        }

        const definition = definitions[selectedDataset];
        const requiredFields = Object.entries(definition.fields).filter(([, field]) => field.required).map(([key]) => key);

        const missing = requiredFields.filter(fieldKey => {
            const value = currentMapping[fieldKey];
            return !value || value === '__skip__';
        });

        if (missing.length > 0) {
            const missingLabels = missing.map(fieldKey => definition.fields[fieldKey].label);
            step2Feedback.innerHTML = `<div class="alert alert-danger">Map required field(s): ${missingLabels.join(', ')}</div>`;
            return false;
        }

        return true;
    }

    function buildSummarySection() {
        const definition = definitions[selectedDataset];
        const mappedFields = Object.entries(currentMapping)
            .filter(([, column]) => column && column !== '__skip__')
            .map(([fieldKey, column]) => ({
                field: definition.fields[fieldKey]?.label || fieldKey,
                column,
            }));

        let html = '<div class="result-summary">';
        html += `<p><strong>Dataset:</strong> ${definition.label}</p>`;
        html += `<p><strong>Rows detected:</strong> ${currentPreview.total_rows}</p>`;

        html += '<h4 style="margin-top: 16px;">Field Mapping</h4>';
        html += '<ul style="columns: 2; column-gap: 16px; list-style: none; padding: 0; margin: 0;">';
        mappedFields.forEach(item => {
            html += `<li style="margin-bottom: 6px;"><strong>${item.field}</strong> → <span style="color: var(--secondary);">${item.column}</span></li>`;
        });
        html += '</ul>';

        html += '<div style="margin-top: 16px; font-size: 13px; color: var(--secondary);">';
        html += updateExistingToggle.checked ? 'Existing records with matching IDs/keys will be updated.' : 'Existing records will be left untouched.';
        if (updateExistingToggle.checked) {
            html += '<br>';
            html += skipBlankToggle.checked ? 'Blank cells will be ignored during updates.' : 'Blank cells will overwrite existing values.';
        }
        html += '</div>';
        html += '</div>';

        importSummary.innerHTML = html;
    }

    runImportButton.addEventListener('click', async () => {
        if (!validateMapping()) {
            setActiveStep(2);
            return;
        }

        runImportButton.disabled = true;
        runImportButton.innerHTML = '⏳ Importing...';
        importSummary.innerHTML += '<p style="margin-top: 16px; color: var(--secondary);">Processing rows. This may take a moment…</p>';

        try {
            const response = await fetch('../api/import-run.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    dataset: selectedDataset,
                    token: currentToken,
                    delimiter: delimiterSelect.value,
                    mapping: currentMapping,
                    update_existing: updateExistingToggle.checked,
                    skip_blank_updates: skipBlankToggle.checked,
                }),
            });
            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message || 'Import failed');
            }

            const summary = result.summary || {};
            let html = '<div class="result-summary">';
            html += '<h3 style="margin-top: 0;">Import Complete</h3>';
            html += '<div class="result-grid">';
            html += `<div class="result-card"><h3>${summary.inserted || 0}</h3><span>Inserted</span></div>`;
            html += `<div class="result-card"><h3>${summary.updated || 0}</h3><span>Updated</span></div>`;
            html += `<div class="result-card"><h3>${summary.skipped || 0}</h3><span>Skipped</span></div>`;
            html += `<div class="result-card"><h3>${summary.total_rows || 0}</h3><span>Processed Rows</span></div>`;
            html += '</div>';

            if (summary.errors && summary.errors.length > 0) {
                html += '<div class="error-list"><strong>Errors:</strong><ul style="margin: 8px 0 0 18px;">';
                summary.errors.slice(0, 10).forEach(error => {
                    html += `<li>Row ${error.row}: ${error.message}</li>`;
                });
                if (summary.errors.length > 10) {
                    html += `<li>…and ${summary.errors.length - 10} more.</li>`;
                }
                html += '</ul></div>';
            } else {
                html += '<p style="margin-top: 16px; color: #166534;">All rows imported without errors.</p>';
            }
            html += '</div>';

            importSummary.innerHTML = html;
        } catch (error) {
            console.error(error);
            importSummary.innerHTML = `<div class="alert alert-danger">❌ ${error.message}</div>`;
        } finally {
            runImportButton.disabled = false;
            runImportButton.innerHTML = '✅ Run Import';
        }
    });
})();
</script>

<?php require_once '../includes/footer.php'; ?>


