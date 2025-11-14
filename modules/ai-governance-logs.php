<?php
/**
 * AI Usage Logs - Separate page for viewing AI usage history
 * This file is included by ai-governance.php when action=logs
 */

// Initialize errors and messages arrays if not already set
if (!isset($errors)) {
    $errors = [];
}
if (!isset($messages)) {
    $messages = [];
}

$filters = [
    'provider' => trim($_GET['provider'] ?? ''),
    'feature' => trim($_GET['feature'] ?? ''), // Changed from 'action' to 'feature' to avoid conflict with page action
    'status' => $_GET['status'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'user' => trim($_GET['user'] ?? ''),
];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = min(max((int) ($_GET['per_page'] ?? 25), 10), 100);
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($filters['provider'] !== '') {
    $where[] = 'provider = :provider';
    $params[':provider'] = $filters['provider'];
}

if ($filters['feature'] !== '') {
    $where[] = 'action = :feature';
    $params[':feature'] = $filters['feature'];
}

if ($filters['status'] === 'success') {
    $where[] = 'is_success = 1';
} elseif ($filters['status'] === 'error') {
    $where[] = 'is_success = 0';
}

if ($filters['user'] !== '') {
    $where[] = '(role LIKE :user OR user_id IN (SELECT id FROM users WHERE username LIKE :user))';
    $params[':user'] = '%' . $filters['user'] . '%';
}

if ($filters['date_from'] !== '') {
    $where[] = 'created_at >= :date_from';
    $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
}

if ($filters['date_to'] !== '') {
    $where[] = 'created_at <= :date_to';
    $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
}

$whereSql = $where ? implode(' AND ', $where) : '1=1';

// Check if table exists
$tableExists = false;
try {
    $checkStmt = $pdo->query("SHOW TABLES LIKE 'ai_usage_logs'");
    $tableExists = $checkStmt->rowCount() > 0;
} catch (PDOException $e) {
    $tableExists = false;
}

$logs = [];
$totalRows = 0;
$stats = [
    'total_requests' => 0,
    'success_count' => 0,
    'error_count' => 0,
    'total_tokens' => 0,
    'total_cost_estimate' => 0,
    'avg_latency' => 0,
];

if ($tableExists) {
    try {
        // Get statistics
        $statsStmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END) as success_count,
                SUM(CASE WHEN is_success = 0 THEN 1 ELSE 0 END) as error_count,
                SUM(total_tokens) as total_tokens,
                AVG(latency_ms) as avg_latency
            FROM ai_usage_logs
            WHERE {$whereSql}
        ");
        foreach ($params as $key => $value) {
            $statsStmt->bindValue($key, $value);
        }
        $statsStmt->execute();
        $statsRow = $statsStmt->fetch(PDO::FETCH_ASSOC);
        if ($statsRow) {
            $stats['total_requests'] = (int)($statsRow['total_requests'] ?? 0);
            $stats['success_count'] = (int)($statsRow['success_count'] ?? 0);
            $stats['error_count'] = (int)($statsRow['error_count'] ?? 0);
            $stats['total_tokens'] = (int)($statsRow['total_tokens'] ?? 0);
            $stats['avg_latency'] = round((float)($statsRow['avg_latency'] ?? 0), 2);
        }
        
        // Estimate cost (rough: $0.002 per 1K tokens for GPT-4, $0.0002 for GPT-3.5)
        // Using average of $0.001 per 1K tokens as estimate
        $stats['total_cost_estimate'] = round(($stats['total_tokens'] / 1000) * 0.001, 4);
        
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ai_usage_logs WHERE {$whereSql}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }
        $countStmt->execute();
        $totalRows = (int) $countStmt->fetchColumn();

        $logStmt = $pdo->prepare("
            SELECT l.*, u.username, u.full_name
            FROM ai_usage_logs l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE {$whereSql}
            ORDER BY l.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $logStmt->bindValue($key, $value);
        }
        $logStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $logStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $logStmt->execute();
        $logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errors[] = 'Failed to load usage logs: ' . $e->getMessage();
        error_log('AI Governance Logs Error: ' . $e->getMessage());
    }
} else {
    $errors[] = 'AI usage logs table does not exist. Please run the AI migration: database/migrations/phase5/001_create_ai_tables.sql';
}

$totalPages = max(1, (int) ceil($totalRows / $perPage));

// buildPageLink function is defined in ai-governance.php
if (!function_exists('buildPageLink')) {
    function buildPageLink(int $page): string
    {
        $params = $_GET;
        $params['page'] = $page;
        return 'ai-governance.php?' . http_build_query($params);
    }
}
?>

<div class="card" style="padding: 24px; margin-top: 0;">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom: 24px;">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo e($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($messages)): ?>
        <div class="alert alert-success" style="margin-bottom: 24px;">
            <ul style="margin:0; padding-left: 20px;">
                <?php foreach ($messages as $msg): ?>
                    <li><?php echo e($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="card-header" style="margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="ai-governance.php" class="btn btn-outline" style="text-decoration: none;">← Back to Settings</a>
            <div>
                <h2 style="margin: 0;">📋 AI Usage History & Logs</h2>
                <p class="text-muted" style="margin: 4px 0 0 0; font-size: 13px;">
                    Monitor AI feature usage across the system
                </p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <div class="help-trigger">
                <span class="help-icon" onclick="toggleHelp('usage-logs')" title="Click for help">?</span>
                <div class="help-tooltip" id="help-usage-logs">
                    <strong>AI Usage Logs</strong>
                    Monitor who used AI features, when, and whether it succeeded. Use this to track costs, debug issues, and analyze AI usage patterns. These logs are specific to AI functionality only.
                </div>
            </div>
            <button onclick="showDeleteOptions()" class="btn btn-sm btn-danger" style="white-space: nowrap;">
                🗑️ Delete Logs
            </button>
        </div>
    </div>

    <div style="background: var(--bg); padding: 20px; border-radius: 10px; margin-bottom: 24px; border: 1px solid var(--border);">
        <h3 style="font-size: 16px; margin-top: 0; margin-bottom: 18px; font-weight: 600; color: var(--text);">🔍 Search & Filter Logs</h3>
        <form method="get" class="form-grid-compact">
            <input type="hidden" name="action" value="logs">
            <div class="form-group">
                <label class="form-label"><small>AI Provider</small></label>
                <input type="text" name="provider" class="form-control" value="<?php echo e($filters['provider']); ?>" placeholder="e.g. openai, deepseek">
            </div>
            <div class="form-group">
                <label class="form-label"><small>Feature Used</small></label>
                <input type="text" name="feature" class="form-control" value="<?php echo e($filters['feature']); ?>" placeholder="e.g. forecast_cashflow">
            </div>
            <div class="form-group">
                <label class="form-label"><small>Result</small></label>
                <select name="status" class="form-control">
                    <option value="">All Results</option>
                    <option value="success" <?php echo $filters['status'] === 'success' ? 'selected' : ''; ?>>✅ Success</option>
                    <option value="error" <?php echo $filters['status'] === 'error' ? 'selected' : ''; ?>>❌ Error</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label"><small>User or Role</small></label>
                <input type="text" name="user" class="form-control" value="<?php echo e($filters['user']); ?>" placeholder="Search by username">
            </div>
            <div class="form-group">
                <label class="form-label"><small>From Date</small></label>
                <input type="date" name="date_from" class="form-control" value="<?php echo e($filters['date_from']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><small>To Date</small></label>
                <input type="date" name="date_to" class="form-control" value="<?php echo e($filters['date_to']); ?>">
            </div>
            <div class="form-group">
                <label class="form-label"><small>Results Per Page</small></label>
                <input type="number" name="per_page" class="form-control" min="10" max="100" value="<?php echo $perPage; ?>">
            </div>
            <div class="form-group full-width" style="align-self: flex-end; margin-top: 8px;">
                <button type="submit" class="btn btn-primary">🔍 Search</button>
                <a href="ai-governance.php?action=logs" class="btn btn-outline">🔄 Clear Filters</a>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table" id="logsTable">
            <thead>
                <tr>
                    <th style="width: 40px;">
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)" title="Select All">
                    </th>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Provider</th>
                    <th>Tokens</th>
                    <th>Latency</th>
                    <th>Status</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="10" style="text-align:center; color: var(--secondary); padding: 40px;">
                            No AI usage records found for the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="log_ids[]" value="<?php echo (int) $log['id']; ?>" class="log-checkbox">
                            </td>
                            <td><?php echo e($log['created_at']); ?></td>
                            <td><?php echo e($log['full_name'] ?: $log['username'] ?: 'N/A'); ?></td>
                            <td><?php echo e($log['role'] ?: '—'); ?></td>
                            <td><?php echo e($log['action']); ?></td>
                            <td><?php echo strtoupper(e($log['provider'] ?: 'n/a')); ?></td>
                            <td><?php echo (int) $log['total_tokens']; ?></td>
                            <td><?php echo $log['latency_ms'] !== null ? (int) $log['latency_ms'] . ' ms' : '—'; ?></td>
                            <td>
                                <?php if ((int) $log['is_success'] === 1): ?>
                                    <span class="badge badge-success">Success</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">
                                        Error<?php echo $log['error_code'] ? ': ' . e($log['error_code']) : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($log['metadata_json'])): ?>
                                    <details>
                                        <summary style="cursor: pointer; color: var(--primary);">View</summary>
                                        <pre style="max-width:320px; white-space: pre-wrap; margin-top: 8px; padding: 8px; background: var(--bg); border-radius: 4px;"><?php echo e(json_encode(json_decode($log['metadata_json'], true), JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination" style="margin-top: 24px;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i === $page): ?>
                    <span class="active"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="<?php echo e(buildPageLink($i)); ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Logs Modal -->
<div id="deleteLogsModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: var(--card); border-radius: 12px; max-width: 500px; width: 90%; padding: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
        <h3 style="margin: 0 0 16px 0; color: var(--text);">🗑️ Delete Logs</h3>
        <form method="post" id="deleteLogsForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="delete_logs" value="1">
            <input type="hidden" name="delete_type" id="deleteType" value="selected">
            <input type="hidden" name="confirm_delete_all" id="confirmDeleteAll" value="">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); cursor: pointer;">
                    <input type="radio" name="delete_option" value="selected" checked onchange="updateDeleteType('selected')" style="margin-right: 8px;">
                    Delete Selected Logs
                </label>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); cursor: pointer;">
                    <input type="radio" name="delete_option" value="old" onchange="updateDeleteType('old')" style="margin-right: 8px;">
                    Delete Logs Older Than 30 Days
                </label>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text); cursor: pointer;">
                    <input type="radio" name="delete_option" value="zero_tokens" onchange="updateDeleteType('zero_tokens')" style="margin-right: 8px;">
                    Delete Logs with Zero Tokens
                </label>
                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--danger); cursor: pointer;">
                    <input type="radio" name="delete_option" value="all" onchange="updateDeleteType('all')" style="margin-right: 8px;">
                    Delete All Logs
                </label>
            </div>
            
            <div id="selectedCount" style="margin-bottom: 16px; padding: 12px; background: var(--bg); border-radius: 8px; color: var(--text);">
                <strong>0</strong> log(s) selected
            </div>
            
            <div id="oldLogsInfo" style="display: none; margin-bottom: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); border-radius: 8px; color: var(--text);">
                This will delete all logs older than 30 days. This action cannot be undone.
            </div>
            
            <div id="zeroTokensInfo" style="display: none; margin-bottom: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); border-radius: 8px; color: var(--text);">
                This will delete all logs with zero tokens (failed or incomplete requests). This action cannot be undone.
            </div>
            
            <div id="allLogsWarning" style="display: none; margin-bottom: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-left: 4px solid var(--danger); border-radius: 8px; color: var(--text);">
                <strong>⚠️ Warning:</strong> This will delete ALL logs permanently. This action cannot be undone.
                <div style="margin-top: 8px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="confirmAllCheckbox" onchange="document.getElementById('confirmDeleteAll').value = this.checked ? 'yes' : ''">
                        I understand this will delete all logs
                    </label>
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeDeleteModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-danger" id="deleteSubmitBtn">Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function showDeleteOptions() {
    const modal = document.getElementById('deleteLogsModal');
    if (modal) {
        modal.style.display = 'flex';
        updateSelectedCount();
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteLogsModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function updateDeleteType(type) {
    document.getElementById('deleteType').value = type;
    document.getElementById('selectedCount').style.display = type === 'selected' ? 'block' : 'none';
    document.getElementById('oldLogsInfo').style.display = type === 'old' ? 'block' : 'none';
    document.getElementById('zeroTokensInfo').style.display = type === 'zero_tokens' ? 'block' : 'none';
    document.getElementById('allLogsWarning').style.display = type === 'all' ? 'block' : 'none';
    
    const submitBtn = document.getElementById('deleteSubmitBtn');
    if (type === 'all') {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Confirm to Delete All';
    } else {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Delete';
    }
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.log-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.log-checkbox:checked').length;
    const countEl = document.getElementById('selectedCount');
    if (countEl) {
        countEl.innerHTML = '<strong>' + checked + '</strong> log(s) selected';
    }
}

// Update count when checkboxes change
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.log-checkbox');
    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectedCount);
    });
    
    // Close modal on outside click
    const modal = document.getElementById('deleteLogsModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeDeleteModal();
            }
        });
    }
    
    // Handle form submission
    const form = document.getElementById('deleteLogsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const deleteType = document.getElementById('deleteType').value;
            
            if (deleteType === 'selected') {
                const checked = document.querySelectorAll('.log-checkbox:checked');
                if (checked.length === 0) {
                    e.preventDefault();
                    alert('Please select at least one log to delete.');
                    return false;
                }
                
                // Add selected IDs to form
                checked.forEach(cb => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'log_ids[]';
                    input.value = cb.value;
                    form.appendChild(input);
                });
            } else if (deleteType === 'all') {
                const confirmed = document.getElementById('confirmDeleteAll').value === 'yes';
                if (!confirmed) {
                    e.preventDefault();
                    alert('Please confirm deletion of all logs by checking the checkbox.');
                    return false;
                }
                
                if (!confirm('Are you absolutely sure you want to delete ALL logs? This cannot be undone!')) {
                    e.preventDefault();
                    return false;
                }
            } else if (deleteType === 'old') {
                if (!confirm('Delete all logs older than 30 days? This cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            } else if (deleteType === 'zero_tokens') {
                if (!confirm('Delete all logs with zero tokens? This will remove failed or incomplete requests. This cannot be undone.')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Update delete button state when confirm checkbox changes
    const confirmCheckbox = document.getElementById('confirmAllCheckbox');
    if (confirmCheckbox) {
        confirmCheckbox.addEventListener('change', function() {
            const submitBtn = document.getElementById('deleteSubmitBtn');
            if (this.checked) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Delete All';
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Confirm to Delete All';
            }
        });
    }
});
</script>

