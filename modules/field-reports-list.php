<?php
/**
 * Field Reports List with Pagination and Advanced Search
 */
$page_title = 'Field Reports';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/pagination.php';

$auth->requireAuth();

// Handle search and filters
$search = sanitizeInput($_GET['search'] ?? '');
$rig_id = intval($_GET['rig_id'] ?? 0);
$client_id = intval($_GET['client_id'] ?? 0);
$job_type = sanitizeInput($_GET['job_type'] ?? '');
$start_date = sanitizeInput($_GET['start_date'] ?? '');
$end_date = sanitizeInput($_GET['end_date'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;

// Build query
$pdo = getDBConnection();
$query = "SELECT fr.*, r.rig_name, c.client_name 
          FROM field_reports fr 
          LEFT JOIN rigs r ON fr.rig_id = r.id 
          LEFT JOIN clients c ON fr.client_id = c.id 
          WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total
               FROM field_reports fr 
               LEFT JOIN rigs r ON fr.rig_id = r.id 
               LEFT JOIN clients c ON fr.client_id = c.id 
               WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (fr.site_name LIKE ? OR fr.report_id LIKE ? OR c.client_name LIKE ?)";
    $countQuery .= " AND (fr.site_name LIKE ? OR fr.report_id LIKE ? OR c.client_name LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($rig_id)) {
    $query .= " AND fr.rig_id = ?";
    $countQuery .= " AND fr.rig_id = ?";
    $params[] = $rig_id;
}

if (!empty($client_id)) {
    $query .= " AND fr.client_id = ?";
    $countQuery .= " AND fr.client_id = ?";
    $params[] = $client_id;
}

if (!empty($job_type)) {
    $query .= " AND fr.job_type = ?";
    $countQuery .= " AND fr.job_type = ?";
    $params[] = $job_type;
}

if (!empty($start_date)) {
    $query .= " AND fr.report_date >= ?";
    $countQuery .= " AND fr.report_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $query .= " AND fr.report_date <= ?";
    $countQuery .= " AND fr.report_date <= ?";
    $params[] = $end_date;
}

// Get total count
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];

// Add pagination
$pagination = new Pagination($totalItems, $perPage, $page, 'field-reports-list.php');
$query .= " ORDER BY fr.created_at DESC LIMIT " . $pagination->getLimit() . " OFFSET " . $pagination->getOffset();

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get rigs and clients for filters
$rigs = $pdo->query("SELECT * FROM rigs WHERE status = 'active' ORDER BY rig_name")->fetchAll();
$clients = $pdo->query("SELECT * FROM clients ORDER BY client_name LIMIT 100")->fetchAll();

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>Field Reports</h1>
            <p>View and manage all field operation reports</p>
        </div>
        <div style="display: flex; gap: 10px; align-items: center;">
            <a href="field-reports.php" class="btn btn-primary">+ New Report</a>
        </div>
    </div>

            <!-- Advanced Search and Filters -->
            <div class="dashboard-card">
                <h2>Search & Filter</h2>
                <form method="GET" class="form-grid form-grid-compact">
                    <div class="form-group">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo e($search); ?>" 
                               placeholder="Search by site name, report ID, or client...">
                    </div>
                    <div class="form-group">
                        <label for="rig_id" class="form-label">Rig</label>
                        <select id="rig_id" name="rig_id" class="form-control">
                            <option value="">All Rigs</option>
                            <?php foreach ($rigs as $rig): ?>
                                <option value="<?php echo $rig['id']; ?>" <?php echo $rig_id == $rig['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($rig['rig_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client_id" class="form-label">Client</label>
                        <select id="client_id" name="client_id" class="form-control">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $client_id == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($client['client_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="job_type" class="form-label">Job Type</label>
                        <select id="job_type" name="job_type" class="form-control">
                            <option value="">All Types</option>
                            <option value="direct" <?php echo $job_type == 'direct' ? 'selected' : ''; ?>>Direct</option>
                            <option value="subcontract" <?php echo $job_type == 'subcontract' ? 'selected' : ''; ?>>Subcontract</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" id="start_date" name="start_date" class="form-control" 
                               value="<?php echo e($start_date); ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" id="end_date" name="end_date" class="form-control" 
                               value="<?php echo e($end_date); ?>">
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="field-reports-list.php" class="btn btn-outline">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Reports List -->
            <div class="dashboard-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                    <h2 style="margin: 0;">Reports (<?php echo number_format($totalItems); ?>)</h2>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <small style="margin-right: 8px;">Showing <?php echo count($reports); ?> of <?php echo number_format($totalItems); ?></small>
                        <div class="export-buttons" style="display: flex; gap: 6px;">
                            <a href="export.php?module=reports&format=csv<?php echo !empty($rig_id) ? '&rig_id=' . $rig_id : ''; ?><?php echo !empty($start_date) ? '&date_from=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&date_to=' . urlencode($end_date) : ''; ?>" 
                               class="btn btn-sm btn-outline" title="Export as CSV">
                                📥 CSV
                            </a>
                            <a href="export.php?module=reports&format=excel<?php echo !empty($rig_id) ? '&rig_id=' . $rig_id : ''; ?><?php echo !empty($start_date) ? '&date_from=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&date_to=' . urlencode($end_date) : ''; ?>" 
                               class="btn btn-sm btn-outline" title="Export as Excel">
                                📊 Excel
                            </a>
                            <a href="export.php?module=reports&format=pdf<?php echo !empty($rig_id) ? '&rig_id=' . $rig_id : ''; ?><?php echo !empty($start_date) ? '&date_from=' . urlencode($start_date) : ''; ?><?php echo !empty($end_date) ? '&date_to=' . urlencode($end_date) : ''; ?>" 
                               class="btn btn-sm btn-outline" title="Export as PDF" target="_blank">
                                📄 PDF
                            </a>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Date</th>
                                <th>Site Name</th>
                                <th>Rig</th>
                                <th>Client</th>
                                <th>Job Type</th>
                                <th>Total Depth</th>
                                <th>Net Profit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No reports found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                <tr>
                                    <td><code><?php echo e($report['report_id']); ?></code></td>
                                    <td><?php echo formatDate($report['report_date']); ?></td>
                                    <td><?php echo e($report['site_name']); ?></td>
                                    <td><?php echo e($report['rig_name']); ?></td>
                                    <td><?php echo e($report['client_name'] ?? 'N/A'); ?></td>
                                    <td><span class="badge"><?php echo ucfirst(e($report['job_type'])); ?></span></td>
                                    <td><?php echo e($report['total_depth'] ?? 0); ?>m</td>
                                    <td class="<?php echo $report['net_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                        <?php echo formatCurrency($report['net_profit']); ?>
                                    </td>
                                    <td>
                                        <div class="action-dropdown">
                                            <button class="btn btn-sm btn-outline action-dropdown-toggle" onclick="toggleActionDropdown(<?php echo $report['id']; ?>)">
                                                Actions <span style="margin-left: 5px;">▼</span>
                                            </button>
                                            <div id="action-menu-<?php echo $report['id']; ?>" class="action-dropdown-menu" style="display: none;">
                                                <a href="#" onclick="viewReportDetails(<?php echo $report['id']; ?>); return false;" class="action-menu-item">
                                                    <span style="margin-right: 8px;">👁️</span> View Report
                                                </a>
                                                <a href="receipt.php?report_id=<?php echo $report['id']; ?>" target="_blank" class="action-menu-item">
                                                    <span style="margin-right: 8px;">💰</span> Generate Receipt
                                                </a>
                                                <a href="technical-report.php?report_id=<?php echo $report['id']; ?>" target="_blank" class="action-menu-item">
                                                    <span style="margin-right: 8px;">📄</span> Generate Report
                                                </a>
                                                <a href="#" onclick="editReportDetails(<?php echo $report['id']; ?>); return false;" class="action-menu-item">
                                                    <span style="margin-right: 8px;">✏️</span> Edit Report
                                                </a>
                                                <a href="#" onclick="deleteReport(event, <?php echo $report['id']; ?>, '<?php echo e($report['report_id']); ?>'); return false;" class="action-menu-item action-menu-item-danger">
                                                    <span style="margin-right: 8px;">🗑️</span> Delete Report
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php 
                $queryParams = array_filter([
                    'search' => $search,
                    'rig_id' => $rig_id,
                    'client_id' => $client_id,
                    'job_type' => $job_type,
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ]);
                echo $pagination->render($queryParams);
                ?>
            </div>

            <style>
                .pagination {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    gap: 12px;
                    margin-top: 20px;
                    padding: 20px;
                }
                .pagination-info {
                    padding: 8px 16px;
                    color: var(--secondary);
                }
            </style>
        </div>
    </div>
</div>

<!-- Report Details Modal -->
<div id="reportModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; overflow-y: auto;">
    <div style="max-width: 900px; margin: 30px auto; background: white; border-radius: 8px; padding: 0; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: #f8f9fa; border-radius: 8px 8px 0 0;">
            <h2 style="margin: 0; color: #1e293b;">Report Details</h2>
            <button onclick="closeReportModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #64748b; padding: 0; width: 32px; height: 32px; line-height: 1;">&times;</button>
        </div>
        <div id="reportModalContent" style="padding: 30px; max-height: calc(100vh - 150px); overflow-y: auto;">
            <div style="text-align: center; padding: 40px;">
                <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
                <p style="margin-top: 15px; color: #64748b;">Loading report details...</p>
            </div>
        </div>
    </div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.report-detail-section {
    margin-bottom: 25px;
    padding-bottom: 20px;
    border-bottom: 1px solid #e5e7eb;
}

.report-detail-section:last-child {
    border-bottom: none;
}

.report-detail-section h3 {
    margin: 0 0 15px 0;
    color: #1e293b;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.report-detail-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.report-detail-item {
    display: flex;
    flex-direction: column;
}

.report-detail-item strong {
    color: #64748b;
    font-size: 13px;
    margin-bottom: 4px;
    font-weight: 500;
}

.report-detail-item span {
    color: #1e293b;
    font-size: 15px;
    font-weight: 500;
}

.report-detail-item code {
    background: #f1f5f9;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 14px;
    color: #0ea5e9;
}

.report-detail-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.report-detail-table th {
    background: #f8f9fa;
    padding: 10px;
    text-align: left;
    border-bottom: 2px solid #e5e7eb;
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.report-detail-table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
    color: #1e293b;
}

.report-detail-table tr:last-child td {
    border-bottom: none;
}

/* Action Dropdown Styles */
.action-dropdown {
    position: relative;
    display: inline-block;
}

.action-dropdown-toggle {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    font-size: 13px;
    white-space: nowrap;
}

.action-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    min-width: 200px;
    z-index: 1000;
    margin-top: 5px;
    overflow: hidden;
}

.action-menu-item {
    display: block;
    padding: 10px 15px;
    color: #1e293b;
    text-decoration: none;
    font-size: 14px;
    border-bottom: 1px solid #f1f5f9;
    transition: background 0.2s;
    cursor: pointer;
}

.action-menu-item:last-child {
    border-bottom: none;
}

.action-menu-item:hover {
    background: #f8f9fa;
    color: #0ea5e9;
}

.action-menu-item-danger {
    color: #ef4444;
}

.action-menu-item-danger:hover {
    background: #fef2f2;
    color: #dc2626;
}

.action-menu-item span {
    display: inline-block;
    width: 20px;
}

.form-control {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
    font-family: inherit;
    width: 100%;
}

.form-control:focus {
    outline: none;
    border-color: #0ea5e9;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}
</style>

<script>
let currentReportId = null;
let currentEditMode = false;

function viewReportDetails(reportId, editMode = false) {
    currentReportId = reportId;
    currentEditMode = editMode;
    const modal = document.getElementById('reportModal');
    const content = document.getElementById('reportModalContent');
    
    // Update modal title
    const modalTitle = modal.querySelector('h2');
    if (modalTitle) {
        modalTitle.textContent = editMode ? 'Edit Report' : 'Report Details';
    }
    
    // Show modal
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Reset content
    content.innerHTML = `
        <div style="text-align: center; padding: 40px;">
            <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            <p style="margin-top: 15px; color: #64748b;">Loading report details...</p>
        </div>
    `;
    
    // Fetch report details - use absolute path based on current location
    const apiUrl = '../api/get-report-details.php?id=' + reportId;
    
    fetch(apiUrl, {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const contentType = response.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                throw new Error("Server did not return JSON");
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (editMode) {
                    content.innerHTML = formatReportDetailsEditable(data.report);
                } else {
                    content.innerHTML = formatReportDetails(data.report);
                }
            } else {
                content.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #ef4444;">
                        <p style="font-size: 16px;">Error loading report details</p>
                        <p style="color: #64748b; font-size: 14px; margin-top: 10px;">${data.message || 'Unknown error'}</p>
                        <button onclick="closeReportModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            console.error('API URL:', apiUrl);
            content.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #ef4444;">
                    <p style="font-size: 16px;">Error loading report details</p>
                    <p style="color: #64748b; font-size: 14px; margin-top: 10px;">${error.message || 'Network error. Please try again.'}</p>
                    <p style="color: #94a3b8; font-size: 12px; margin-top: 10px;">Check browser console (F12) for more details.</p>
                    <button onclick="closeReportModal()" class="btn btn-primary" style="margin-top: 20px;">Close</button>
                </div>
            `;
        });
}

function closeReportModal() {
    document.getElementById('reportModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal when clicking outside
document.getElementById('reportModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReportModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReportModal();
    }
});

function formatReportDetails(report) {
    // Escape HTML to prevent XSS
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const formatCurrency = (val) => {
        return val ? 'GHS ' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'GHS 0.00';
    };
    
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };
    
    const formatTime = (timeStr) => {
        if (!timeStr) return 'N/A';
        return escapeHtml(timeStr.substring(0, 5));
    };
    
    let html = `
        <div class="report-detail-section">
            <h3>📋 Basic Information</h3>
            <div class="report-detail-grid">
                                 <div class="report-detail-item">
                     <strong>Report ID</strong>
                     <span><code>${escapeHtml(report.report_id || 'N/A')}</code></span>
                 </div>
                 <div class="report-detail-item">
                     <strong>Report Date</strong>
                     <span>${formatDate(report.report_date)}</span>
                 </div>
                 <div class="report-detail-item">
                     <strong>Job Type</strong>
                     <span><span class="badge">${escapeHtml((report.job_type || '').charAt(0).toUpperCase() + (report.job_type || '').slice(1))}</span></span>
                 </div>
                 <div class="report-detail-item">
                     <strong>Rig</strong>
                     <span>${escapeHtml(report.rig_name || 'N/A')} ${report.rig_code ? '(' + escapeHtml(report.rig_code) + ')' : ''}</span>
                 </div>
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>📍 Site Information</h3>
            <div class="report-detail-grid">
                                 <div class="report-detail-item">
                     <strong>Site Name</strong>
                     <span>${escapeHtml(report.site_name || 'N/A')}</span>
                 </div>
                 <div class="report-detail-item">
                     <strong>Region</strong>
                     <span>${escapeHtml(report.region || 'N/A')}</span>
                 </div>
                 ${report.latitude && report.longitude ? `
                 <div class="report-detail-item">
                     <strong>Coordinates</strong>
                     <span>${escapeHtml(report.latitude)}, ${escapeHtml(report.longitude)}</span>
                 </div>
                 ` : ''}
                 ${report.plus_code ? `
                 <div class="report-detail-item">
                     <strong>Plus Code</strong>
                     <span><code>${escapeHtml(report.plus_code)}</code></span>
                 </div>
                 ` : ''}
            </div>
                         ${report.location_description ? `
             <div style="margin-top: 15px;">
                 <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">Location Description</strong>
                 <p style="color: #1e293b; margin: 0; line-height: 1.6;">${escapeHtml(report.location_description || '').replace(/\n/g, '<br>')}</p>
             </div>
             ` : ''}
        </div>
        
        <div class="report-detail-section">
            <h3>👥 Client Information</h3>
            <div class="report-detail-grid">
                                 <div class="report-detail-item">
                     <strong>Client Name</strong>
                     <span>${escapeHtml(report.client_name || 'N/A')}</span>
                 </div>
                 ${report.contact_person ? `
                 <div class="report-detail-item">
                     <strong>Contact Person</strong>
                     <span>${escapeHtml(report.contact_person)}</span>
                 </div>
                 ` : ''}
                 ${report.contact_number ? `
                 <div class="report-detail-item">
                     <strong>Contact Number</strong>
                     <span>${escapeHtml(report.contact_number)}</span>
                 </div>
                 ` : ''}
                 ${report.email ? `
                 <div class="report-detail-item">
                     <strong>Email</strong>
                     <span>${escapeHtml(report.email)}</span>
                 </div>
                 ` : ''}
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>⛏️ Drilling Operations</h3>
            <div class="report-detail-grid">
                                 <div class="report-detail-item">
                     <strong>Supervisor</strong>
                     <span>${escapeHtml(report.supervisor || 'N/A')}</span>
                 </div>
                <div class="report-detail-item">
                    <strong>Total Workers</strong>
                    <span>${report.total_workers || 0} personnel</span>
                </div>
                <div class="report-detail-item">
                    <strong>Total Depth</strong>
                    <span>${report.total_depth || 0} meters</span>
                </div>
                <div class="report-detail-item">
                    <strong>Rod Length</strong>
                    <span>${report.rod_length || 0} meters</span>
                </div>
                <div class="report-detail-item">
                    <strong>Rods Used</strong>
                    <span>${report.rods_used || 0} rods</span>
                </div>
                ${report.start_time || report.finish_time ? `
                <div class="report-detail-item">
                    <strong>Start Time</strong>
                    <span>${formatTime(report.start_time)}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Finish Time</strong>
                    <span>${formatTime(report.finish_time)}</span>
                </div>
                ` : ''}
                ${report.total_duration ? `
                <div class="report-detail-item">
                    <strong>Duration</strong>
                    <span>${Math.floor(report.total_duration / 60)}h ${report.total_duration % 60}m</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>📦 Materials Used</h3>
            <div class="report-detail-grid">
                <div class="report-detail-item">
                    <strong>Screen Pipes</strong>
                    <span>${report.screen_pipes_used || 0}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Plain Pipes</strong>
                    <span>${report.plain_pipes_used || 0}</span>
                </div>
                <div class="report-detail-item">
                    <strong>Gravel</strong>
                    <span>${report.gravel_used || 0}</span>
                </div>
                ${report.construction_depth ? `
                <div class="report-detail-item">
                    <strong>Construction Depth</strong>
                    <span>${report.construction_depth} meters</span>
                </div>
                ` : ''}
                ${report.materials_provided_by ? `
                <div class="report-detail-item">
                    <strong>Materials Provided By</strong>
                    <span>${report.materials_provided_by.charAt(0).toUpperCase() + report.materials_provided_by.slice(1)}</span>
                </div>
                ` : ''}
            </div>
        </div>
        
        <div class="report-detail-section">
            <h3>💰 Financial Summary</h3>
            <table class="report-detail-table">
                <tr>
                    <th>Item</th>
                    <th style="text-align: right;">Amount (GHS)</th>
                </tr>
                <tr>
                    <td>Contract Sum</td>
                    <td style="text-align: right;">${formatCurrency(report.contract_sum)}</td>
                </tr>
                <tr>
                    <td>Rig Fee Charged</td>
                    <td style="text-align: right;">${formatCurrency(report.rig_fee_charged)}</td>
                </tr>
                <tr>
                    <td>Rig Fee Collected</td>
                    <td style="text-align: right;">${formatCurrency(report.rig_fee_collected)}</td>
                </tr>
                <tr>
                    <td>Cash Received</td>
                    <td style="text-align: right;">${formatCurrency(report.cash_received)}</td>
                </tr>
                <tr>
                    <td>Materials Income</td>
                    <td style="text-align: right;">${formatCurrency(report.materials_income)}</td>
                </tr>
                <tr>
                    <td><strong>Total Income</strong></td>
                    <td style="text-align: right;"><strong>${formatCurrency(report.total_income)}</strong></td>
                </tr>
                <tr style="border-top: 2px solid #e5e7eb;">
                    <td>Materials Cost</td>
                    <td style="text-align: right;">${formatCurrency(report.materials_cost)}</td>
                </tr>
                <tr>
                    <td>Total Wages</td>
                    <td style="text-align: right;">${formatCurrency(report.total_wages)}</td>
                </tr>
                <tr>
                    <td>Cash Given</td>
                    <td style="text-align: right;">${formatCurrency(report.cash_given)}</td>
                </tr>
                <tr>
                    <td>Momo Transfer</td>
                    <td style="text-align: right;">${formatCurrency(report.momo_transfer)}</td>
                </tr>
                <tr>
                    <td>Bank Deposit</td>
                    <td style="text-align: right;">${formatCurrency(report.bank_deposit)}</td>
                </tr>
                <tr>
                    <td><strong>Total Expenses</strong></td>
                    <td style="text-align: right;"><strong>${formatCurrency(report.total_expenses)}</strong></td>
                </tr>
                <tr style="background: #f8f9fa; border-top: 2px solid #1e293b;">
                    <td><strong>Net Profit</strong></td>
                    <td style="text-align: right; color: ${parseFloat(report.net_profit || 0) >= 0 ? '#10b981' : '#ef4444'};">
                        <strong>${formatCurrency(report.net_profit)}</strong>
                    </td>
                </tr>
            </table>
        </div>
    `;
    
    // Add notes sections if available
    if (report.remarks || report.incident_log || report.solution_log || report.recommendation_log) {
        html += `<div class="report-detail-section">
            <h3>📝 Notes & Observations</h3>`;
        
                         if (report.remarks) {
             html += `
             <div style="margin-bottom: 15px;">
                 <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">General Remarks</strong>
                 <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.remarks || '').replace(/\n/g, '<br>')}</p>
             </div>`;
         }
         
         if (report.incident_log) {
             html += `
             <div style="margin-bottom: 15px;">
                 <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">⚠️ Incidents Encountered</strong>
                 <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.incident_log || '').replace(/\n/g, '<br>')}</p>
             </div>`;
         }
         
         if (report.solution_log) {
             html += `
             <div style="margin-bottom: 15px;">
                 <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">✅ Solutions Applied</strong>
                 <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.solution_log || '').replace(/\n/g, '<br>')}</p>
             </div>`;
         }
         
         if (report.recommendation_log) {
             html += `
             <div style="margin-bottom: 15px;">
                 <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">💡 Recommendations</strong>
                 <p style="color: #1e293b; margin: 0; line-height: 1.6; white-space: pre-wrap;">${escapeHtml(report.recommendation_log || '').replace(/\n/g, '<br>')}</p>
             </div>`;
         }
        
        html += `</div>`;
    }
    
    // Add action buttons
    html += `
        <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
            <a href="receipt.php?report_id=${report.id}" target="_blank" class="btn btn-primary">💰 Receipt</a>
            <a href="technical-report.php?report_id=${report.id}" target="_blank" class="btn btn-success">📄 Technical Report</a>
            <button onclick="closeReportModal()" class="btn btn-outline">Close</button>
        </div>
    `;
    
    return html;
}

// Format report details for editing - shows full report with editable fields
function formatReportDetailsEditable(report) {
    const escapeHtml = (text) => {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    };
    
    const formatCurrency = (val) => {
        return val ? 'GHS ' + parseFloat(val).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 'GHS 0.00';
    };
    
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };
    
    const formatTime = (timeStr) => {
        if (!timeStr) return 'N/A';
        return escapeHtml(timeStr.substring(0, 5));
    };
    
    let html = `
        <form id="editReportForm" onsubmit="updateReport(event); return false;">
            <div class="report-detail-section">
                <h3>📋 Basic Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Report ID</strong>
                        <span><code>${escapeHtml(report.report_id || 'N/A')}</code></span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Report Date</strong>
                        <span>${formatDate(report.report_date)}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Job Type</strong>
                        <span><span class="badge">${escapeHtml((report.job_type || '').charAt(0).toUpperCase() + (report.job_type || '').slice(1))}</span></span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Rig</strong>
                        <span>${escapeHtml(report.rig_name || 'N/A')} ${report.rig_code ? '(' + escapeHtml(report.rig_code) + ')' : ''}</span>
                    </div>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>📍 Site Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Site Name</strong>
                        <input type="text" name="site_name" value="${escapeHtml(report.site_name || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="report-detail-item">
                        <strong>Region</strong>
                        <input type="text" name="region" value="${escapeHtml(report.region || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    ${report.latitude && report.longitude ? `
                    <div class="report-detail-item">
                        <strong>Coordinates</strong>
                        <span>${escapeHtml(report.latitude)}, ${escapeHtml(report.longitude)}</span>
                    </div>
                    ` : ''}
                </div>
                <div style="margin-top: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">Location Description</strong>
                    <textarea name="location_description" class="form-control" rows="3" style="width: 100%;">${escapeHtml(report.location_description || '')}</textarea>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>👥 Client Information</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Client Name</strong>
                        <span>${escapeHtml(report.client_name || 'N/A')}</span>
                    </div>
                    ${report.contact_person ? `
                    <div class="report-detail-item">
                        <strong>Contact Person</strong>
                        <span>${escapeHtml(report.contact_person)}</span>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>⛏️ Drilling Operations</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Supervisor</strong>
                        <input type="text" name="supervisor" value="${escapeHtml(report.supervisor || '')}" class="form-control" style="width: 100%; margin-top: 5px;">
                    </div>
                    <div class="report-detail-item">
                        <strong>Total Workers</strong>
                        <span>${report.total_workers || 0} personnel</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Total Depth</strong>
                        <span>${report.total_depth || 0} meters</span>
                    </div>
                    ${report.total_duration ? `
                    <div class="report-detail-item">
                        <strong>Duration</strong>
                        <span>${Math.floor(report.total_duration / 60)}h ${report.total_duration % 60}m</span>
                    </div>
                    ` : ''}
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>📦 Materials Used</h3>
                <div class="report-detail-grid">
                    <div class="report-detail-item">
                        <strong>Screen Pipes</strong>
                        <span>${report.screen_pipes_used || 0}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Plain Pipes</strong>
                        <span>${report.plain_pipes_used || 0}</span>
                    </div>
                    <div class="report-detail-item">
                        <strong>Gravel</strong>
                        <span>${report.gravel_used || 0}</span>
                    </div>
                </div>
            </div>
            
            <div class="report-detail-section">
                <h3>💰 Financial Summary</h3>
                <table class="report-detail-table">
                    <tr>
                        <td>Contract Sum</td>
                        <td style="text-align: right;">${formatCurrency(report.contract_sum)}</td>
                    </tr>
                    <tr>
                        <td><strong>Total Income</strong></td>
                        <td style="text-align: right;"><strong>${formatCurrency(report.total_income)}</strong></td>
                    </tr>
                    <tr>
                        <td><strong>Total Expenses</strong></td>
                        <td style="text-align: right;"><strong>${formatCurrency(report.total_expenses)}</strong></td>
                    </tr>
                    <tr style="background: #f8f9fa; border-top: 2px solid #1e293b;">
                        <td><strong>Net Profit</strong></td>
                        <td style="text-align: right; color: ${parseFloat(report.net_profit || 0) >= 0 ? '#10b981' : '#ef4444'};">
                            <strong>${formatCurrency(report.net_profit)}</strong>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="report-detail-section">
                <h3>📝 Notes & Observations</h3>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">General Remarks</strong>
                    <textarea name="remarks" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.remarks || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">⚠️ Incidents Encountered</strong>
                    <textarea name="incident_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.incident_log || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">✅ Solutions Applied</strong>
                    <textarea name="solution_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.solution_log || '')}</textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <strong style="color: #64748b; font-size: 13px; display: block; margin-bottom: 5px;">💡 Recommendations</strong>
                    <textarea name="recommendation_log" class="form-control" rows="4" style="width: 100%;">${escapeHtml(report.recommendation_log || '')}</textarea>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e5e7eb; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeReportModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Report</button>
            </div>
            <input type="hidden" name="id" value="${report.id}">
        </form>
    `;
    
    return html;
}

function editReportDetails(reportId) {
    viewReportDetails(reportId, true);
}

// Update report function
function updateReport(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    const data = {};
    
    // Convert FormData to object
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    
    // Get the submit button
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;
    
    // Send update request
    fetch('../api/update-report.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showNotification('Report updated successfully', 'success');
            // Close modal and refresh the view
            setTimeout(() => {
                closeReportModal();
                location.reload();
            }, 1000);
        } else {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
            alert('Error updating report: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        submitBtn.textContent = originalText;
        submitBtn.disabled = false;
        console.error('Update error:', error);
        alert('Error updating report. Please try again.');
    });
}

// Toggle action dropdown
function toggleActionDropdown(reportId) {
    const menu = document.getElementById('action-menu-' + reportId);
    const allMenus = document.querySelectorAll('.action-dropdown-menu');
    
    // Close all other dropdowns
    allMenus.forEach(m => {
        if (m.id !== 'action-menu-' + reportId) {
            m.style.display = 'none';
        }
    });
    
    // Toggle current dropdown
    if (menu.style.display === 'none' || menu.style.display === '') {
        menu.style.display = 'block';
    } else {
        menu.style.display = 'none';
    }
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.action-dropdown')) {
        document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
            menu.style.display = 'none';
        });
    }
});

// Delete report function
function deleteReport(event, reportId, reportIdText) {
    if (!confirm(`Are you sure you want to delete report "${reportIdText}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const button = event.target.closest('.action-menu-item');
    const originalText = button.innerHTML;
    button.innerHTML = '<span style="margin-right: 8px;">⏳</span> Deleting...';
    button.style.pointerEvents = 'none';
    
    // Find the table row
    const row = event.target.closest('tr');
    
    // Close dropdown
    document.querySelectorAll('.action-dropdown-menu').forEach(menu => {
        menu.style.display = 'none';
    });
    
    // Send delete request
    fetch('../api/delete-report.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            id: reportId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the row from the table
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    
                    // Show success message
                    showNotification('Report deleted successfully', 'success');
                    
                    // Reload page if no reports left or refresh the list
                    const remainingRows = document.querySelectorAll('tbody tr:not(.text-center)');
                    if (remainingRows.length === 0) {
                        setTimeout(() => location.reload(), 500);
                    }
                }, 300);
            } else {
                // If row not found, just reload
                showNotification('Report deleted successfully', 'success');
                setTimeout(() => location.reload(), 500);
            }
        } else {
            button.innerHTML = originalText;
            button.style.pointerEvents = 'auto';
            alert('Error deleting report: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        button.innerHTML = originalText;
        button.style.pointerEvents = 'auto';
        console.error('Delete error:', error);
        alert('Error deleting report. Please try again.');
    });
}

// Simple notification function
function showNotification(message, type) {
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#10b981' : '#ef4444'};
        color: white;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        z-index: 10001;
        font-size: 14px;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations for notifications
if (!document.getElementById('notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);
}
</script>

<?php require_once '../includes/footer.php'; ?>
