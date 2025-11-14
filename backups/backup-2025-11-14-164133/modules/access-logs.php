<?php
/**
 * Access Control Logs Viewer
 */
$page_title = 'Access Control Logs';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

$pdo = getDBConnection();
$accessControl = $auth->getAccessControl();
$roleLabels = $accessControl->getRoleLabels();
$permissionMeta = $accessControl->getPermissions();

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'role' => trim($_GET['role'] ?? ''),
    'permission' => trim($_GET['permission'] ?? ''),
    'allowed' => $_GET['allowed'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

$page = max(1, intval($_GET['page'] ?? 1));
$perPage = min(max(intval($_GET['per_page'] ?? 25), 10), 100);
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if ($filters['role'] !== '') {
    $where[] = 'acl.role = :role';
    $params[':role'] = $filters['role'];
}

if ($filters['permission'] !== '') {
    $where[] = 'acl.permission_key = :permission';
    $params[':permission'] = $filters['permission'];
}

if ($filters['allowed'] === 'allowed') {
    $where[] = 'acl.is_allowed = 1';
} elseif ($filters['allowed'] === 'denied') {
    $where[] = 'acl.is_allowed = 0';
}

if ($filters['q'] !== '') {
    $where[] = '(acl.username LIKE :query OR acl.permission_key LIKE :query OR acl.context_details LIKE :query OR acl.page LIKE :query)';
    $params[':query'] = '%' . $filters['q'] . '%';
}

if ($filters['date_from'] !== '') {
    $where[] = 'acl.created_at >= :date_from';
    $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
}

if ($filters['date_to'] !== '') {
    $where[] = 'acl.created_at <= :date_to';
    $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
}

$whereSql = $where ? implode(' AND ', $where) : '1=1';

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM access_control_logs acl WHERE {$whereSql}");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalRows = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $perPage));

// Fetch logs
$sql = "
    SELECT acl.*, u.full_name 
    FROM access_control_logs acl
    LEFT JOIN users u ON acl.user_id = u.id
    WHERE {$whereSql}
    ORDER BY acl.created_at DESC
    LIMIT :limit OFFSET :offset
";
$logStmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $logStmt->bindValue($key, $value);
}
$logStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logStmt->execute();
$logs = $logStmt->fetchAll(PDO::FETCH_ASSOC);

require_once '../includes/header.php';
?>

<style>
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 20px;
    }
    @media (max-width: 1400px) {
        .filter-grid {
            grid-template-columns: repeat(3, minmax(160px, 1fr));
        }
    }
    @media (max-width: 900px) {
        .filter-grid {
            grid-template-columns: repeat(2, minmax(160px, 1fr));
        }
    }
    @media (max-width: 600px) {
        .filter-grid {
            grid-template-columns: minmax(160px, 1fr);
        }
    }
    .log-table table {
        width: 100%;
        border-collapse: collapse;
    }
    .log-table th, .log-table td {
        padding: 10px 12px;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
        font-size: 13px;
    }
    .log-table th {
        background: var(--table-header);
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 600;
    }
    .badge-success {
        background: #dcfce7;
        color: #166534;
    }
    .badge-danger {
        background: #fee2e2;
        color: #991b1b;
    }
    .pagination {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 16px;
    }
    .pagination a, .pagination span {
        padding: 6px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        text-decoration: none;
        font-size: 13px;
    }
    .pagination .active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .context-meta {
        color: var(--secondary);
        font-size: 12px;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔐 Access Control Logs</h1>
        <p>Audit trail of access decisions across ABBIS (successful and denied requests).</p>
    </div>

    <form class="filter-grid dashboard-card" method="get" autocomplete="off">
        <div>
            <label class="form-label">Search</label>
            <input type="text" name="q" class="form-control" placeholder="User, permission, context" value="<?php echo e($filters['q']); ?>">
        </div>
        <div>
            <label class="form-label">Role</label>
            <select name="role" class="form-control">
                <option value="">All roles</option>
                <?php foreach ($roleLabels as $roleKey => $label): ?>
                    <option value="<?php echo e($roleKey); ?>" <?php echo $filters['role'] === $roleKey ? 'selected' : ''; ?>>
                        <?php echo e($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Permission</label>
            <select name="permission" class="form-control">
                <option value="">All permissions</option>
                <?php foreach ($permissionMeta as $permissionKey => $meta): ?>
                    <option value="<?php echo e($permissionKey); ?>" <?php echo $filters['permission'] === $permissionKey ? 'selected' : ''; ?>>
                        <?php echo e($permissionKey); ?> – <?php echo e($meta['label'] ?? ''); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label">Outcome</label>
            <select name="allowed" class="form-control">
                <option value="">Allowed &amp; Denied</option>
                <option value="allowed" <?php echo $filters['allowed'] === 'allowed' ? 'selected' : ''; ?>>Allowed</option>
                <option value="denied" <?php echo $filters['allowed'] === 'denied' ? 'selected' : ''; ?>>Denied</option>
            </select>
        </div>
        <div>
            <label class="form-label">From</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo e($filters['date_from']); ?>">
        </div>
        <div>
            <label class="form-label">To</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo e($filters['date_to']); ?>">
        </div>
        <div style="grid-column: 1 / -1; display: flex; gap: 8px; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="access-logs.php" class="btn btn-outline">Reset</a>
        </div>
    </form>

    <div class="dashboard-card log-table" style="overflow: auto; max-height: 65vh;">
        <table>
            <thead>
                <tr>
                    <th style="min-width: 150px;">Timestamp</th>
                    <th style="min-width: 120px;">User</th>
                    <th style="min-width: 100px;">Role</th>
                    <th style="min-width: 160px;">Permission</th>
                    <th style="min-width: 140px;">Page / Context</th>
                    <th style="min-width: 90px;">Outcome</th>
                    <th style="min-width: 140px;">IP Address</th>
                    <th style="min-width: 200px;">User Agent</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px 0; color: var(--secondary);">
                            No access control events match the current filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <?php
                            $allowed = (int) ($log['is_allowed'] ?? 0) === 1;
                            $permissionKey = $log['permission_key'] ?? '';
                            $permissionLabel = $permissionKey && isset($permissionMeta[$permissionKey]['label'])
                                ? $permissionMeta[$permissionKey]['label']
                                : '';
                        ?>
                        <tr>
                            <td>
                                <?php echo e(date('Y-m-d H:i:s', strtotime($log['created_at']))); ?>
                            </td>
                            <td>
                                <strong><?php echo e($log['username'] ?? '—'); ?></strong><br>
                                <span class="context-meta"><?php echo e($log['full_name'] ?? ''); ?></span>
                            </td>
                            <td><?php echo e($roleLabels[$log['role']] ?? $log['role'] ?? '—'); ?></td>
                            <td>
                                <?php echo e($permissionKey ?: '—'); ?><br>
                                <?php if ($permissionLabel): ?>
                                    <span class="context-meta"><?php echo e($permissionLabel); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo e($log['page'] ?: '—'); ?>
                                <?php if (!empty($log['context_details'])): ?>
                                    <div class="context-meta"><?php echo e($log['context_details']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $allowed ? 'badge-success' : 'badge-danger'; ?>">
                                    <?php echo $allowed ? 'Allowed' : 'Denied'; ?>
                                </span>
                            </td>
                            <td><?php echo e($log['ip_address'] ?: '—'); ?></td>
                            <td><?php echo e($log['user_agent'] ? substr($log['user_agent'], 0, 160) : '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span>Showing <?php echo min($totalRows, $offset + 1); ?> – <?php echo min($totalRows, $offset + $perPage); ?> of <?php echo $totalRows; ?></span>
        <?php if ($page > 1): ?>
            <a href="<?php echo e(buildPageLink($filters, $page - 1, $perPage)); ?>">&larr; Previous</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
            <a href="<?php echo e(buildPageLink($filters, $page + 1, $perPage)); ?>">Next &rarr;</a>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<?php
function buildPageLink(array $filters, int $page, int $perPage): string {
    $params = array_merge($filters, ['page' => $page, 'per_page' => $perPage]);
    return 'access-logs.php?' . http_build_query(array_filter($params, fn($value) => $value !== '' && $value !== null));
}

