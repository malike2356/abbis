<?php
$page_title = 'Environmental Sampling Workflow';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/navigation-tracker.php';
require_once __DIR__ . '/../includes/Environmental/EnvironmentalSamplingService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

$service = new EnvironmentalSamplingService();
$page = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status' => $_GET['status'] ?? null,
    'client_id' => $_GET['client_id'] ?? null,
    'search' => $_GET['search'] ?? null,
];
$projects = $service->listProjects($page, 20, $filters);
$clients = getAllClients();

NavigationTracker::recordCurrentPage((int)$_SESSION['user_id']);
require_once '../includes/header.php';
?>

<div class="module-container">
    <style>
        .sampling-grid { display: grid; gap: 24px; }
        .sampling-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 10px 25px rgba(37,99,235,0.25); }
        .btn-outline { background: transparent; color: var(--secondary); border: 1px solid var(--border); }
        .card {
            border-radius: 16px;
            background: var(--card);
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm, 0 12px 28px rgba(15,23,42,0.08));
        }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-weight: 600; color: var(--text); }
        .form-control, select, textarea {
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 10px 12px;
            background: rgba(15,23,42,0.03);
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            text-transform: capitalize;
        }
        .status-pill.draft { background: rgba(148,163,184,0.18); color: #475569; }
        .status-pill.scheduled { background: rgba(96,165,250,0.18); color: #1d4ed8; }
        .status-pill.in_progress { background: rgba(251,191,36,0.2); color: #92400e; }
        .status-pill.submitted { background: rgba(34,197,94,0.2); color: #166534; }
        .status-pill.completed { background: rgba(45,212,191,0.2); color: #0f766e; }
        .status-pill.archived { background: rgba(148,163,184,0.24); color: #475569; }
        .table { width: 100%; border-collapse: collapse; }
        .table thead { background: rgba(15,23,42,0.06); }
        .table th, .table td { padding: 12px 14px; border-bottom: 1px solid rgba(148,163,184,0.14); text-align: left; }
        .project-detail { display: grid; gap: 18px; }
        .project-detail__meta { display: grid; grid-template-columns: repeat(auto-fit,minmax(240px,1fr)); gap: 12px; }
        .badge { display: inline-flex; align-items: center; padding: 4px 8px; border-radius: 8px; font-size: 12px; background: rgba(59,130,246,0.12); color: #1d4ed8; }
        .split { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .section-heading { display: flex; justify-content: space-between; align-items: center; }
        .section-heading h3 { margin: 0; font-size: 18px; }
        .timeline { display: grid; gap: 12px; }
        .timeline-item { border-left: 3px solid var(--primary); padding: 12px 16px; position: relative; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 14px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }
        #projectModal, #sampleModal, #chainModal, #resultModal {
            position: fixed;
            inset: 0;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.55);
            z-index: 2000;
        }
        .modal-content {
            width: min(780px, 90vw);
            background: var(--card);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 25px 60px rgba(15,23,42,0.25);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
        .modal-close { background: none; border: none; font-size: 28px; cursor: pointer; }
        @media (max-width: 768px) {
            .sampling-header { flex-direction: column; align-items: stretch; }
        }
    </style>

    <div class="sampling-grid">
        <div class="sampling-header">
            <div>
                <h1>Environmental Sampling</h1>
                <p class="page-subtitle">Plan sampling projects, maintain chain-of-custody, and capture lab results.</p>
            </div>
            <div style="display:flex; gap:10px;">
                <button class="btn btn-outline" type="button" onclick="refreshProjects()">Refresh</button>
                <button class="btn btn-primary" type="button" onclick="openProjectModal()">New Project</button>
            </div>
        </div>

        <div class="card">
            <form method="GET" class="form-row" style="margin-bottom: 12px;">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All statuses</option>
                        <?php
                        $statuses = ['draft','scheduled','in_progress','submitted','completed','archived'];
                        foreach ($statuses as $status):
                            $selected = ($filters['status'] ?? '') === $status ? 'selected' : '';
                        ?>
                            <option value="<?php echo $status; ?>" <?php echo $selected; ?>><?php echo ucwords(str_replace('_',' ', $status)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Client</label>
                    <select name="client_id">
                        <option value="">All clients</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo (int)$client['id']; ?>" <?php echo ((int)($filters['client_id'] ?? 0) === (int)$client['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['client_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Project code, site name, etc.">
                </div>
                <div class="form-group" style="align-self:flex-end;">
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                </div>
            </form>

            <div style="overflow-x:auto;">
                <table class="table" id="projectsTable">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Sampling Type</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Samples</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($projects['items'])): ?>
                            <tr><td colspan="7" style="text-align:center; padding:22px;">No sampling projects found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($projects['items'] as $project): ?>
                                <tr data-project-id="<?php echo (int)$project['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($project['project_code']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($project['project_name'] ?? $project['site_name'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($project['client_name'] ?? '—'); ?></td>
                                    <td><span class="badge"><?php echo ucwords(str_replace('_',' ', $project['sampling_type'])); ?></span></td>
                                    <td>
                                        <?php if (!empty($project['scheduled_date'])): ?>
                                            Scheduled: <?php echo htmlspecialchars($project['scheduled_date']); ?><br>
                                        <?php endif; ?>
                                        <?php if (!empty($project['collected_date'])): ?>
                                            Collected: <?php echo htmlspecialchars($project['collected_date']); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="status-pill <?php echo htmlspecialchars($project['status']); ?>"><?php echo ucwords(str_replace('_',' ', $project['status'])); ?></span></td>
                                    <td><?php echo (int)($project['sample_count'] ?? 0); ?></td>
                                    <td style="text-align:right;">
                                        <button class="btn btn-outline btn-sm" type="button" onclick="viewProject(<?php echo (int)$project['id']; ?>)">View</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="projectDetail" class="card" style="display:none;">
            <div class="section-heading" style="margin-bottom:16px;">
                <h2 id="projectTitle">Sampling Project</h2>
                <div style="display:flex; gap:10px;">
                    <select id="projectStatusSelect" class="form-control" style="max-width:180px;">
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="submitted">Submitted to Lab</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                    <button class="btn btn-outline btn-sm" type="button" onclick="updateProjectStatus()">Update Status</button>
                    <button class="btn btn-primary btn-sm" type="button" onclick="openSampleModal()">Add Sample</button>
                </div>
            </div>

            <div class="project-detail">
                <div class="project-detail__meta" id="projectMeta"></div>

                <div class="split">
                    <div class="card" style="background: rgba(15,23,42,0.03); border: 1px solid rgba(148,163,184,0.2);">
                        <div class="section-heading">
                            <h3>Samples</h3>
                            <button class="btn btn-outline btn-sm" type="button" onclick="openSampleModal()">New Sample</button>
                        </div>
                        <div id="projectSamples" style="margin-top:12px;"></div>
                    </div>
                    <div class="card" style="background: rgba(15,23,42,0.03); border: 1px solid rgba(148,163,184,0.2);">
                        <div class="section-heading">
                            <h3>Chain of Custody</h3>
                            <button class="btn btn-outline btn-sm" type="button" onclick="openChainModal()">Log Transfer</button>
                        </div>
                        <div id="chainTimeline" class="timeline" style="margin-top:12px;"></div>
                    </div>
                </div>

                <div class="card" style="background: rgba(15,23,42,0.03); border: 1px solid rgba(148,163,184,0.2);">
                    <div class="section-heading">
                        <h3>Lab Results</h3>
                        <button class="btn btn-outline btn-sm" type="button" onclick="openResultModal()">Add Result</button>
                    </div>
                    <div id="labResults" style="margin-top:12px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Project Modal -->
<div id="projectModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="projectModalTitle">New Sampling Project</h2>
            <button class="modal-close" type="button" onclick="closeModal('projectModal')">&times;</button>
        </div>
        <form id="projectForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="save_project">
            <input type="hidden" name="project_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Project Code</label>
                    <input type="text" name="project_code" class="form-control" placeholder="Auto generated if empty">
                </div>
                <div class="form-group">
                    <label>Project Name</label>
                    <input type="text" name="project_name" class="form-control" placeholder="Optional descriptive name">
                </div>
                <div class="form-group">
                    <label>Sampling Type</label>
                    <select name="sampling_type" class="form-control">
                        <option value="water">Water</option>
                        <option value="soil">Soil</option>
                        <option value="air">Air</option>
                        <option value="geological">Geological</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Client</label>
                    <select name="client_id" class="form-control">
                        <option value="">Select client</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?php echo (int)$client['id']; ?>"><?php echo htmlspecialchars($client['client_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Field Report ID</label>
                    <input type="number" name="field_report_id" class="form-control" placeholder="Optional link">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="draft">Draft</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="in_progress">In Progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Site / Project Location</label>
                    <input type="text" name="site_name" class="form-control" placeholder="e.g., Borehole BH-08">
                </div>
                <div class="form-group">
                    <label>Address / Description</label>
                    <input type="text" name="location_address" class="form-control" placeholder="Town, district, landmarks">
                </div>
                <div class="form-group">
                    <label>Latitude</label>
                    <input type="text" name="latitude" class="form-control" placeholder="Decimal degrees">
                </div>
                <div class="form-group">
                    <label>Longitude</label>
                    <input type="text" name="longitude" class="form-control" placeholder="Decimal degrees">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="date" name="scheduled_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Collection Date</label>
                    <input type="date" name="collected_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>Submitted to Lab At</label>
                    <input type="datetime-local" name="submitted_to_lab_at" class="form-control">
                </div>
                <div class="form-group">
                    <label>Completed At</label>
                    <input type="datetime-local" name="completed_at" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" class="form-control" placeholder="Sampling objectives, QA/QC instructions, etc."></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:12px;">
                <button class="btn btn-outline" type="button" onclick="closeModal('projectModal')">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Sample Modal -->
<div id="sampleModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="sampleModalTitle">Add Sample</h2>
            <button class="modal-close" type="button" onclick="closeModal('sampleModal')">&times;</button>
        </div>
        <form id="sampleForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="save_sample">
            <input type="hidden" name="project_id" value="">
            <input type="hidden" name="sample_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Sample Code</label>
                    <input type="text" name="sample_code" class="form-control" placeholder="Auto generated if empty">
                </div>
                <div class="form-group">
                    <label>Sample Type</label>
                    <input type="text" name="sample_type" class="form-control" placeholder="e.g., Borehole, Surface Water">
                </div>
                <div class="form-group">
                    <label>Matrix</label>
                    <select name="matrix" class="form-control">
                        <option value="water">Water</option>
                        <option value="soil">Soil</option>
                        <option value="air">Air</option>
                        <option value="rock">Rock</option>
                        <option value="sediment">Sediment</option>
                        <option value="waste">Waste</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Collection Method</label>
                    <input type="text" name="collection_method" class="form-control" placeholder="e.g., Grab sample, Composite">
                </div>
                <div class="form-group">
                    <label>Container Type</label>
                    <input type="text" name="container_type" class="form-control" placeholder="e.g., 1L HDPE Bottle">
                </div>
                <div class="form-group">
                    <label>Preservative</label>
                    <input type="text" name="preservative" class="form-control" placeholder="e.g., HNO3, Ice">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Collected By</label>
                    <input type="text" name="collected_by" class="form-control" placeholder="Technician name">
                </div>
                <div class="form-group">
                    <label>Collected At</label>
                    <input type="datetime-local" name="collected_at" class="form-control">
                </div>
                <div class="form-group">
                    <label>Field Temperature (°C)</label>
                    <input type="number" step="0.1" name="temperature_c" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Weather / Field Observations</label>
                <textarea name="field_observations" rows="3" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" class="form-control">
                    <option value="pending">Pending</option>
                    <option value="in_cooler">In Cooler</option>
                    <option value="in_transit">In Transit</option>
                    <option value="at_lab">At Lab</option>
                    <option value="analyzed">Analyzed</option>
                    <option value="disposed">Disposed</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-outline" type="button" onclick="closeModal('sampleModal')">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Sample</button>
            </div>
        </form>
    </div>
</div>

<!-- Chain Modal -->
<div id="chainModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Log Chain of Custody</h2>
            <button class="modal-close" type="button" onclick="closeModal('chainModal')">&times;</button>
        </div>
        <form id="chainForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_chain_entry">
            <input type="hidden" name="project_id" value="">
            <input type="hidden" name="sample_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Sample</label>
                    <select name="chain_sample_id" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Transfer Action</label>
                    <select name="transfer_action" class="form-control">
                        <option value="collected">Collected</option>
                        <option value="sealed">Sealed</option>
                        <option value="transferred">Transferred</option>
                        <option value="received">Received</option>
                        <option value="stored">Stored</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="disposed">Disposed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Transfer Time</label>
                    <input type="datetime-local" name="transfer_at" class="form-control" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Handler Name</label>
                    <input type="text" name="handler_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Handler Role</label>
                    <input type="text" name="handler_role" class="form-control" placeholder="Technician, Courier, Lab Intake, etc.">
                </div>
                <div class="form-group">
                    <label>Received by Lab?</label>
                    <select name="received_by_lab" class="form-control">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Temperature (°C)</label>
                    <input type="number" step="0.1" name="temperature_c" class="form-control">
                </div>
                <div class="form-group">
                    <label>Custody Step</label>
                    <input type="number" name="custody_step" class="form-control" placeholder="Auto increments">
                </div>
            </div>
            <div class="form-group">
                <label>Condition / Notes</label>
                <textarea name="condition_notes" rows="3" class="form-control"></textarea>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-outline" type="button" onclick="closeModal('chainModal')">Cancel</button>
                <button class="btn btn-primary" type="submit">Log Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- Lab Result Modal -->
<div id="resultModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Lab Result</h2>
            <button class="modal-close" type="button" onclick="closeModal('resultModal')">&times;</button>
        </div>
        <form id="resultForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="add_lab_result">
            <input type="hidden" name="project_id" value="">
            <input type="hidden" name="sample_id" value="">
            <div class="form-row">
                <div class="form-group">
                    <label>Sample</label>
                    <select name="result_sample_id" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Parameter</label>
                    <input type="text" name="parameter_name" class="form-control" placeholder="e.g., pH, Iron" required>
                </div>
                <div class="form-group">
                    <label>Parameter Group</label>
                    <input type="text" name="parameter_group" class="form-control" placeholder="Metals, Microbiology, etc.">
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="parameter_unit" class="form-control" placeholder="mg/L, NTU, etc.">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Result Value</label>
                    <input type="number" step="0.0001" name="result_value" class="form-control">
                </div>
                <div class="form-group">
                    <label>Detection Limit</label>
                    <input type="number" step="0.0001" name="detection_limit" class="form-control">
                </div>
                <div class="form-group">
                    <label>QA/QC Flag</label>
                    <select name="qa_qc_flag" class="form-control">
                        <option value="pass">Pass</option>
                        <option value="review">Review</option>
                        <option value="fail">Fail</option>
                        <option value="not_applicable">Not Applicable</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Method Reference</label>
                    <input type="text" name="method_reference" class="form-control" placeholder="Standard Method ID">
                </div>
                <div class="form-group">
                    <label>Analyst Name</label>
                    <input type="text" name="analyst_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>Analyzed At</label>
                    <input type="datetime-local" name="analyzed_at" class="form-control">
                </div>
            </div>
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" rows="3" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Attachment Path</label>
                <input type="text" name="attachment_path" class="form-control" placeholder="/storage/... or external link">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-outline" type="button" onclick="closeModal('resultModal')">Cancel</button>
                <button class="btn btn-primary" type="submit">Save Result</button>
            </div>
        </form>
    </div>
</div>

<script>
let currentProject = null;

async function refreshProjects() {
    try {
        const response = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
        if (response.ok) {
            window.location.reload();
        }
    } catch (error) {
        console.error(error);
    }
}

async function viewProject(projectId) {
    const response = await fetch('../api/environmental-sampling-view.php?id=' + projectId);
    if (!response.ok) {
        alert('Unable to load project');
        return;
    }
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to load project');
        return;
    }
    renderProjectDetails(result.project);
}

function renderProjectDetails(project) {
    currentProject = project;
    document.getElementById('projectDetail').style.display = 'block';
    document.getElementById('projectTitle').textContent = project.project_code + (project.project_name ? ' · ' + project.project_name : '');
    document.getElementById('projectStatusSelect').value = project.status;

    const meta = document.getElementById('projectMeta');
    meta.innerHTML = `
        <div>
            <strong>Client</strong><br>
            ${project.client_name || '—'}
        </div>
        <div>
            <strong>Site</strong><br>
            ${project.site_name || '—'}
        </div>
        <div>
            <strong>Location</strong><br>
            ${project.location_address || '—'}
        </div>
        <div>
            <strong>Coordinates</strong><br>
            ${project.latitude || '—'}, ${project.longitude || '—'}
        </div>
        <div>
            <strong>Scheduled</strong><br>
            ${project.scheduled_date || '—'}
        </div>
        <div>
            <strong>Collected</strong><br>
            ${project.collected_date || '—'}
        </div>
        <div>
            <strong>Submitted to Lab</strong><br>
            ${project.submitted_to_lab_at || '—'}
        </div>
        <div>
            <strong>Completed</strong><br>
            ${project.completed_at || '—'}
        </div>
    `;

    renderSamples(project.samples || []);
    renderChain(project.samples || []);
    renderResults(project.samples || []);

    // Populate sample dropdowns for modals
    const sampleOptions = (project.samples || []).map(sample => `<option value="${sample.id}">${sample.sample_code} - ${sample.sample_type || ''}</option>`).join('');
    document.querySelector('#chainForm select[name="chain_sample_id"]').innerHTML = sampleOptions;
    document.querySelector('#resultForm select[name="result_sample_id"]').innerHTML = sampleOptions;
    document.querySelector('#chainForm input[name="project_id"]').value = project.id;
    document.querySelector('#resultForm input[name="project_id"]').value = project.id;
    document.querySelector('#sampleForm input[name="project_id"]').value = project.id;
    document.querySelector('#projectForm input[name="project_id"]').value = project.id;
}

function renderSamples(samples) {
    const container = document.getElementById('projectSamples');
    if (!samples.length) {
        container.innerHTML = '<p style="color: var(--secondary);">No samples yet.</p>';
        return;
    }

    const rows = samples.map(sample => `
        <div class="card" style="margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:12px;">
                <div>
                    <strong>${sample.sample_code}</strong>
                    <span class="badge" style="margin-left:6px;">${sample.matrix}</span>
                    <div style="color: var(--secondary); font-size:13px; margin-top:4px;">
                        ${sample.sample_type || ''} · ${sample.collection_method || ''}
                    </div>
                    <div style="margin-top:6px; color: var(--secondary); font-size: 13px;">
                        Collected: ${sample.collected_at || '—'}<br>
                        Status: <span class="status-pill ${sample.status}">${sample.status.replace('_',' ')}</span>
                    </div>
                </div>
                <div>
                    <button class="btn btn-outline btn-sm" type="button" onclick="editSample(${sample.id})">Edit</button>
                </div>
            </div>
        </div>
    `).join('');

    container.innerHTML = rows;
}

function renderChain(samples) {
    const container = document.getElementById('chainTimeline');
    let entries = [];
    samples.forEach(sample => {
        (sample.chain || []).forEach(entry => entries.push({ sample_code: sample.sample_code, ...entry }));
    });
    if (!entries.length) {
        container.innerHTML = '<p style="color: var(--secondary);">No chain-of-custody entries recorded.</p>';
        return;
    }
    entries.sort((a,b) => new Date(a.transfer_at) - new Date(b.transfer_at));
    container.innerHTML = entries.map(entry => `
        <div class="timeline-item">
            <div style="display:flex; justify-content:space-between;">
                <strong>${entry.sample_code}</strong>
                <span class="badge">${entry.transfer_action}</span>
            </div>
            <div style="color: var(--secondary); font-size:13px; margin-top:6px;">
                ${new Date(entry.transfer_at).toLocaleString()} · ${entry.handler_name} (${entry.handler_role || 'Handler'})
            </div>
            ${entry.condition_notes ? `<div style="margin-top:6px; font-size:13px;">${entry.condition_notes}</div>` : ''}
            <div style="margin-top:6px; font-size:12px; color: var(--secondary);">
                Temperature: ${entry.temperature_c !== null ? entry.temperature_c + ' °C' : '—'} · Received by lab: ${entry.received_by_lab ? 'Yes' : 'No'}
            </div>
        </div>
    `).join('');
}

function renderResults(samples) {
    const container = document.getElementById('labResults');
    let results = [];
    samples.forEach(sample => {
        (sample.results || []).forEach(result => results.push({ sample_code: sample.sample_code, ...result }));
    });
    if (!results.length) {
        container.innerHTML = '<p style="color: var(--secondary);">No lab results recorded.</p>';
        return;
    }

    const table = `
        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Sample</th>
                        <th>Parameter</th>
                        <th>Value</th>
                        <th>Method</th>
                        <th>QA/QC</th>
                        <th>Analyst</th>
                    </tr>
                </thead>
                <tbody>
                    ${results.map(r => `
                        <tr>
                            <td>${r.sample_code}</td>
                            <td>
                                <strong>${r.parameter_name}</strong><br>
                                <small>${r.parameter_group || ''}</small>
                            </td>
                            <td>${r.result_value !== null ? Number(r.result_value).toFixed(3) : '—'} ${r.parameter_unit || ''}</td>
                            <td>${r.method_reference || '—'}</td>
                            <td><span class="badge">${r.qa_qc_flag}</span></td>
                            <td>${r.analyst_name || '—'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>
    `;
    container.innerHTML = table;
}

function openProjectModal(project) {
    const form = document.getElementById('projectForm');
    form.reset();
    if (project) {
        document.getElementById('projectModalTitle').textContent = 'Edit Sampling Project';
        Object.entries(project).forEach(([key, value]) => {
            if (form.elements[key]) {
                form.elements[key].value = value ?? '';
            }
        });
    } else {
        document.getElementById('projectModalTitle').textContent = 'New Sampling Project';
    }
    document.getElementById('projectModal').style.display = 'flex';
}

function openSampleModal(sample) {
    if (!currentProject) {
        alert('Select a project first.');
        return;
    }
    const form = document.getElementById('sampleForm');
    form.reset();
    form.elements.project_id.value = currentProject.id;
    if (sample) {
        document.getElementById('sampleModalTitle').textContent = 'Edit Sample';
        Object.entries(sample).forEach(([key, value]) => {
            if (form.elements[key]) {
                form.elements[key].value = value ?? '';
            }
        });
    } else {
        document.getElementById('sampleModalTitle').textContent = 'Add Sample';
    }
    document.getElementById('sampleModal').style.display = 'flex';
}

function openChainModal() {
    if (!currentProject) {
        alert('Select a project first.');
        return;
    }
    const form = document.getElementById('chainForm');
    form.reset();
    form.elements.project_id.value = currentProject.id;
    document.getElementById('chainModal').style.display = 'flex';
}

function openResultModal() {
    if (!currentProject) {
        alert('Select a project first.');
        return;
    }
    const form = document.getElementById('resultForm');
    form.reset();
    form.elements.project_id.value = currentProject.id;
    document.getElementById('resultModal').style.display = 'flex';
}

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

document.getElementById('projectForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const response = await fetch('../api/environmental-sampling.php', {
        method: 'POST',
        body: new FormData(form)
    });
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to save project');
        return;
    }
    closeModal('projectModal');
    renderProjectDetails(result.project);
    refreshProjects();
});

document.getElementById('sampleForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const response = await fetch('../api/environmental-sampling.php', {
        method: 'POST',
        body: new FormData(form)
    });
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to save sample');
        return;
    }
    closeModal('sampleModal');
    renderProjectDetails(result.project);
});

document.getElementById('chainForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.set('sample_id', formData.get('chain_sample_id'));
    const response = await fetch('../api/environmental-sampling.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to log chain entry');
        return;
    }
    closeModal('chainModal');
    if (currentProject) {
        viewProject(currentProject.id);
    }
});

document.getElementById('resultForm').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.set('sample_id', formData.get('result_sample_id'));
    const response = await fetch('../api/environmental-sampling.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to save lab result');
        return;
    }
    closeModal('resultModal');
    if (currentProject) {
        viewProject(currentProject.id);
    }
});

async function updateProjectStatus() {
    if (!currentProject) return;
    const status = document.getElementById('projectStatusSelect').value;
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
    formData.append('action', 'update_status');
    formData.append('project_id', currentProject.id);
    formData.append('status', status);

    const response = await fetch('../api/environmental-sampling.php', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    if (!result.success) {
        alert(result.message || 'Unable to update status');
        return;
    }
    alert('Status updated');
    refreshProjects();
}

async function editSample(sampleId) {
    if (!currentProject) return;
    const sample = currentProject.samples.find(s => Number(s.id) === Number(sampleId));
    if (!sample) {
        alert('Sample not found');
        return;
    }
    openSampleModal(sample);
}
</script>

<?php require_once '../includes/footer.php'; ?>

