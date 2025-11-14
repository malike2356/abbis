<?php
$page_title = 'Rig Maintenance Telemetry';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';
require_once '../includes/navigation-tracker.php';
require_once __DIR__ . '/../includes/Maintenance/RigTelemetryService.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

$pdo = getDBConnection();
$telemetry = new RigTelemetryService($pdo);

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    } else {
        $action = $_POST['form_action'] ?? '';
        try {
            switch ($action) {
                case 'create_stream':
                    $rigId = (int)($_POST['rig_id'] ?? 0);
                    $streamName = trim($_POST['stream_name'] ?? '');
                    if (!$rigId || $streamName === '') {
                        throw new Exception('Rig and stream name are required.');
                    }
                    $allowedMetrics = array_filter(array_map('trim', explode(',', $_POST['allowed_metrics'] ?? '')));
                    $result = $telemetry->createStream($rigId, [
                        'stream_name' => $streamName,
                        'device_identifier' => trim($_POST['device_identifier'] ?? ''),
                        'allowed_metrics' => $allowedMetrics ?: null,
                        'status' => $_POST['status'] ?? 'active',
                    ], $_SESSION['user_id'] ?? null);
                    $messages[] = 'Telemetry stream created. Store this token securely: <code>' . htmlspecialchars($result['token']) . '</code>';
                    break;
                case 'add_threshold':
                    $rigId = (int)($_POST['rig_threshold_id'] ?? 0);
                    $metricKey = trim($_POST['metric_key'] ?? '');
                    if (!$rigId || $metricKey === '') {
                        throw new Exception('Rig and metric key are required.');
                    }
                    $stmt = $pdo->prepare("
                        INSERT INTO rig_telemetry_thresholds
                        (rig_id, metric_key, metric_label, metric_unit, threshold_type, warning_threshold, critical_threshold, duration_minutes, notes, is_active, created_by)
                        VALUES (:rig_id, :metric_key, :metric_label, :metric_unit, :threshold_type, :warning_threshold, :critical_threshold, :duration_minutes, :notes, :is_active, :created_by)
                        ON DUPLICATE KEY UPDATE
                            metric_label = VALUES(metric_label),
                            metric_unit = VALUES(metric_unit),
                            threshold_type = VALUES(threshold_type),
                            warning_threshold = VALUES(warning_threshold),
                            critical_threshold = VALUES(critical_threshold),
                            duration_minutes = VALUES(duration_minutes),
                            notes = VALUES(notes),
                            is_active = VALUES(is_active),
                            updated_at = NOW()
                    ");
                    $stmt->execute([
                        ':rig_id' => $rigId,
                        ':metric_key' => $metricKey,
                        ':metric_label' => trim($_POST['metric_label'] ?? '') ?: null,
                        ':metric_unit' => trim($_POST['metric_unit'] ?? '') ?: null,
                        ':threshold_type' => $_POST['threshold_type'] ?? 'greater_than',
                        ':warning_threshold' => $_POST['warning_threshold'] !== '' ? (float)$_POST['warning_threshold'] : null,
                        ':critical_threshold' => $_POST['critical_threshold'] !== '' ? (float)$_POST['critical_threshold'] : null,
                        ':duration_minutes' => $_POST['duration_minutes'] !== '' ? (int)$_POST['duration_minutes'] : null,
                        ':notes' => trim($_POST['threshold_notes'] ?? '') ?: null,
                        ':is_active' => isset($_POST['is_active']) ? 1 : 0,
                        ':created_by' => $_SESSION['user_id'] ?? null,
                    ]);
                    $messages[] = 'Threshold saved successfully.';
                    break;
                case 'update_stream_status':
                    $streamId = (int)($_POST['stream_id'] ?? 0);
                    $status = $_POST['status'] ?? 'active';
                    if (!$streamId) {
                        throw new Exception('Stream not found.');
                    }
                    $stmt = $pdo->prepare("UPDATE rig_maintenance_streams SET status = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$status, $streamId]);
                    $messages[] = 'Stream status updated.';
                    break;
                default:
                    $errors[] = 'Unknown action.';
                    break;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$rigs = $pdo->query("SELECT id, rig_name, rig_code FROM rigs ORDER BY rig_name")->fetchAll(PDO::FETCH_ASSOC);

$summary = $telemetry->getDashboardSummary();

$alertsStmt = $pdo->query("
    SELECT a.*, r.rig_name, r.rig_code
    FROM rig_maintenance_alerts a
    LEFT JOIN rigs r ON r.id = a.rig_id
    WHERE a.status IN ('open','acknowledged')
    ORDER BY FIELD(a.severity,'critical','warning','info'), a.triggered_at DESC
    LIMIT 20
");
$alerts = $alertsStmt->fetchAll(PDO::FETCH_ASSOC);

$eventsStmt = $pdo->query("
    SELECT e.*, r.rig_name, r.rig_code
    FROM rig_telemetry_events e
    LEFT JOIN rigs r ON r.id = e.rig_id
    ORDER BY e.recorded_at DESC
    LIMIT 20
");
$events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);

$streams = $pdo->query("
    SELECT s.*, r.rig_name, r.rig_code
    FROM rig_maintenance_streams s
    LEFT JOIN rigs r ON r.id = s.rig_id
    ORDER BY s.status DESC, s.updated_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

$thresholds = $pdo->query("
    SELECT t.*, r.rig_name, r.rig_code
    FROM rig_telemetry_thresholds t
    LEFT JOIN rigs r ON r.id = t.rig_id
    ORDER BY r.rig_name, t.metric_key
")->fetchAll(PDO::FETCH_ASSOC);

NavigationTracker::recordCurrentPage((int)$_SESSION['user_id']);
require_once '../includes/header.php';
?>

<div class="module-container">
    <style>
        .telemetry-grid { display: grid; gap: 24px; }
        .telemetry-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 16px; }
        .card {
            border-radius: 16px;
            background: var(--card);
            padding: 20px;
            box-shadow: var(--shadow-sm, 0 12px 28px rgba(15,23,42,0.08));
            border: 1px solid var(--border);
        }
        .card h3 { margin: 0 0 8px; font-size: 18px; }
        .card .value { font-size: 32px; font-weight: 700; }
        .card p { margin: 0; color: var(--secondary); }
        .card.warning { border-left: 4px solid #facc15; }
        .card.critical { border-left: 4px solid #ef4444; }
        .section { display: grid; gap: 16px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; }
        .table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }
        .table thead { background: rgba(15,23,42,0.06); text-align: left; }
        .table th, .table td { padding: 12px 16px; border-bottom: 1px solid rgba(148,163,184,0.14); }
        .badge { display: inline-flex; align-items: center; padding: 4px 10px; font-size: 12px; border-radius: 999px; background: rgba(59,130,246,0.12); color: #1d4ed8; }
        .badge.warning { background: rgba(250,204,21,0.18); color: #92400e; }
        .badge.critical { background: rgba(248,113,113,0.18); color: #b91c1c; }
        .form-card form { display: grid; gap: 12px; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px,1fr)); gap: 12px; }
        .form-control, select, textarea { width: 100%; border-radius: 10px; border: 1px solid var(--border); padding: 10px 12px; background: rgba(15,23,42,0.03); }
        .btn { display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; padding: 10px 18px; font-weight: 600; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 10px 25px rgba(37,99,235,0.25); }
        .btn-outline { background: transparent; border: 1px solid var(--border); color: var(--secondary); }
        .alert { border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; }
        .alert-success { background: rgba(34,197,94,0.15); color: #166534; }
        .alert-error { background: rgba(248,113,113,0.18); color: #991b1b; }
        .table-actions { display: flex; gap: 8px; }
    </style>

    <div class="page-heading">
        <h1>Rig Maintenance Telemetry</h1>
        <p class="page-subtitle">Monitor incoming data streams, automate maintenance alerts, and keep rigs in peak condition.</p>
    </div>

    <?php foreach ($messages as $message): ?>
        <div class="alert alert-success"><?php echo $message; ?></div>
    <?php endforeach; ?>
    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endforeach; ?>

    <div class="telemetry-grid">
        <div class="telemetry-cards">
            <div class="card">
                <h3>Active Streams</h3>
                <div class="value"><?php echo (int)$summary['streams_active']; ?></div>
                <p>Telemetry feeds currently delivering data.</p>
            </div>
            <div class="card<?php echo $summary['alerts_open'] ? ' warning' : ''; ?>">
                <h3>Open Alerts</h3>
                <div class="value"><?php echo (int)$summary['alerts_open']; ?></div>
                <p>Alerts awaiting acknowledgement or resolution.</p>
            </div>
            <div class="card critical">
                <h3>Critical Alerts</h3>
                <div class="value"><?php echo (int)$summary['alerts_critical']; ?></div>
                <p>Requires immediate attention.</p>
            </div>
            <div class="card">
                <h3>Events Today</h3>
                <div class="value"><?php echo (int)$summary['events_today']; ?></div>
                <p>Telemetry points ingested in the last 24 hours.</p>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Live Alerts</h2>
                <button class="btn btn-outline" type="button" onclick="refreshTelemetry()">Refresh</button>
            </div>
            <div class="card">
                <table class="table" id="alertsTable">
                    <thead>
                        <tr>
                            <th>Rig</th>
                            <th>Metric</th>
                            <th>Severity</th>
                            <th>Triggered</th>
                            <th>Value</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($alerts)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:20px; color: var(--secondary);">No active alerts</td></tr>
                        <?php else: ?>
                            <?php foreach ($alerts as $alert): ?>
                                <tr data-alert-id="<?php echo (int)$alert['id']; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($alert['rig_name'] ?? 'Rig #' . $alert['rig_id']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($alert['rig_code'] ?? ''); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($alert['metric_key'] ?? $alert['title']); ?></td>
                                    <td>
                                        <span class="badge <?php echo htmlspecialchars($alert['severity']); ?>">
                                            <?php echo ucfirst($alert['severity']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($alert['triggered_at'])); ?></td>
                                    <td><?php echo $alert['trigger_value'] !== null ? number_format((float)$alert['trigger_value'], 2) : '—'; ?></td>
                                    <td class="table-actions">
                                        <button class="btn btn-outline btn-sm" type="button" onclick="ackAlert(<?php echo (int)$alert['id']; ?>)">Acknowledge</button>
                                        <button class="btn btn-primary btn-sm" type="button" onclick="resolveAlert(<?php echo (int)$alert['id']; ?>)">Resolve</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Recent Telemetry</h2>
            </div>
            <div class="card">
                <table class="table" id="eventsTable">
                    <thead>
                        <tr>
                            <th>Rig</th>
                            <th>Metric</th>
                            <th>Value</th>
                            <th>Status</th>
                            <th>Recorded</th>
                            <th>Source</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($event['rig_name'] ?? 'Rig #' . $event['rig_id']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($event['rig_code'] ?? ''); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($event['metric_label'] ?: $event['metric_key']); ?></td>
                                <td><?php echo $event['metric_value'] !== null ? number_format((float)$event['metric_value'], 2) . ' ' . htmlspecialchars($event['metric_unit'] ?? '') : '—'; ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($event['status']); ?>"><?php echo ucfirst($event['status']); ?></span></td>
                                <td><?php echo date('Y-m-d H:i', strtotime($event['recorded_at'])); ?></td>
                                <td><?php echo ucfirst($event['source']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Telemetry Streams</h2>
            </div>
            <div class="card form-card">
                <h3>Create Telemetry Stream</h3>
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="form_action" value="create_stream">
                    <div class="form-row">
                        <div>
                            <label>Rig *</label>
                            <select name="rig_id" required>
                                <option value="">Select rig</option>
                                <?php foreach ($rigs as $rig): ?>
                                    <option value="<?php echo (int)$rig['id']; ?>"><?php echo htmlspecialchars($rig['rig_name']); ?> (<?php echo htmlspecialchars($rig['rig_code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Stream Name *</label>
                            <input type="text" name="stream_name" class="form-control" placeholder="e.g., Engine Telemetry" required>
                        </div>
                        <div>
                            <label>Device Identifier</label>
                            <input type="text" name="device_identifier" class="form-control" placeholder="Serial, Device ID, etc.">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Allowed Metrics (comma separated)</label>
                            <input type="text" name="allowed_metrics" class="form-control" placeholder="engine_temp,pump_pressure">
                        </div>
                        <div>
                            <label>Status</label>
                            <select name="status">
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Generate Stream Token</button>
                </form>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rig</th>
                            <th>Stream</th>
                            <th>Status</th>
                            <th>Last Event</th>
                            <th>Token Preview</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($streams)): ?>
                            <tr><td colspan="6" style="text-align:center; padding:18px;">No streams created yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($streams as $stream): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($stream['rig_name'] ?? 'Rig #' . $stream['rig_id']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($stream['rig_code'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($stream['stream_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($stream['device_identifier'] ?? '—'); ?></small>
                                    </td>
                                    <td><span class="badge <?php echo htmlspecialchars($stream['status']); ?>"><?php echo ucfirst($stream['status']); ?></span></td>
                                    <td><?php echo $stream['last_event_at'] ? date('Y-m-d H:i', strtotime($stream['last_event_at'])) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($stream['token_preview'] ?? ''); ?>…</td>
                                    <td>
                                        <form method="POST" style="display:inline-flex; gap:8px;">
                                            <?php echo CSRF::getTokenField(); ?>
                                            <input type="hidden" name="form_action" value="update_stream_status">
                                            <input type="hidden" name="stream_id" value="<?php echo (int)$stream['id']; ?>">
                                            <select name="status" class="form-control" style="max-width:150px;">
                                                <option value="active" <?php echo $stream['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="paused" <?php echo $stream['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                                                <option value="revoked" <?php echo $stream['status'] === 'revoked' ? 'selected' : ''; ?>>Revoked</option>
                                            </select>
                                            <button type="submit" class="btn btn-outline btn-sm">Update</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="section">
            <div class="section-header">
                <h2>Thresholds & Automation</h2>
            </div>
            <div class="card form-card">
                <h3>Define Alert Threshold</h3>
                <form method="POST">
                    <?php echo CSRF::getTokenField(); ?>
                    <input type="hidden" name="form_action" value="add_threshold">
                    <div class="form-row">
                        <div>
                            <label>Rig *</label>
                            <select name="rig_threshold_id" required>
                                <option value="">Select rig</option>
                                <?php foreach ($rigs as $rig): ?>
                                    <option value="<?php echo (int)$rig['id']; ?>"><?php echo htmlspecialchars($rig['rig_name']); ?> (<?php echo htmlspecialchars($rig['rig_code']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>Metric Key *</label>
                            <input type="text" name="metric_key" class="form-control" placeholder="e.g., engine_temp" required>
                        </div>
                        <div>
                            <label>Metric Label</label>
                            <input type="text" name="metric_label" class="form-control" placeholder="Displayed name">
                        </div>
                        <div>
                            <label>Unit</label>
                            <input type="text" name="metric_unit" class="form-control" placeholder="°C, PSI, etc">
                        </div>
                    </div>
                    <div class="form-row">
                        <div>
                            <label>Threshold Type</label>
                            <select name="threshold_type">
                                <option value="greater_than">Greater Than</option>
                                <option value="less_than">Less Than</option>
                                <option value="equals">Equals</option>
                                <option value="delta">Delta Change</option>
                            </select>
                        </div>
                        <div>
                            <label>Warning Threshold</label>
                            <input type="number" step="0.01" name="warning_threshold" class="form-control" placeholder="e.g., 85">
                        </div>
                        <div>
                            <label>Critical Threshold</label>
                            <input type="number" step="0.01" name="critical_threshold" class="form-control" placeholder="e.g., 95">
                        </div>
                        <div>
                            <label>Duration Minutes</label>
                            <input type="number" name="duration_minutes" class="form-control" placeholder="Optional">
                        </div>
                    </div>
                    <label style="display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>
                    <textarea name="threshold_notes" rows="3" placeholder="Notes & instructions for the maintenance team"></textarea>
                    <button type="submit" class="btn btn-primary">Save Threshold</button>
                </form>
            </div>

            <div class="card">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Rig</th>
                            <th>Metric</th>
                            <th>Warning</th>
                            <th>Critical</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($thresholds)): ?>
                            <tr><td colspan="6" style="padding:18px; text-align:center;">No thresholds configured.</td></tr>
                        <?php else: ?>
                            <?php foreach ($thresholds as $threshold): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($threshold['rig_name'] ?? 'Rig #' . $threshold['rig_id']); ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($threshold['metric_label'] ?: $threshold['metric_key']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($threshold['metric_key']); ?></small>
                                    </td>
                                    <td><?php echo $threshold['warning_threshold'] !== null ? number_format((float)$threshold['warning_threshold'], 2) : '—'; ?></td>
                                    <td><?php echo $threshold['critical_threshold'] !== null ? number_format((float)$threshold['critical_threshold'], 2) : '—'; ?></td>
                                    <td><?php echo ucwords(str_replace('_',' ', $threshold['threshold_type'])); ?></td>
                                    <td><span class="badge <?php echo $threshold['is_active'] ? 'active' : 'paused'; ?>"><?php echo $threshold['is_active'] ? 'Active' : 'Disabled'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
async function refreshTelemetry() {
    try {
        const response = await fetch('../api/rig-telemetry-dashboard.php');
        const result = await response.json();
        if (!result.success) return;
        renderAlerts(result.alerts || []);
        renderEvents(result.events || []);
    } catch (error) {
        console.error('Failed to refresh telemetry', error);
    }
}

function renderAlerts(alerts) {
    const table = document.querySelector('#alertsTable tbody');
    table.innerHTML = '';
    if (!alerts.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="6" style="text-align:center; padding:20px; color: var(--secondary);">No active alerts</td>';
        table.appendChild(tr);
        return;
    }
    alerts.forEach(alert => {
        const tr = document.createElement('tr');
        tr.dataset.alertId = alert.id;
        tr.innerHTML = `
            <td><strong>${alert.rig_name || ('Rig #' + alert.rig_id)}</strong><br><small>${alert.rig_code || ''}</small></td>
            <td>${alert.metric_key || alert.title}</td>
            <td><span class="badge ${alert.severity}">${alert.severity.charAt(0).toUpperCase() + alert.severity.slice(1)}</span></td>
            <td>${alert.triggered_at ? new Date(alert.triggered_at).toLocaleString() : ''}</td>
            <td>${alert.trigger_value !== null ? Number(alert.trigger_value).toFixed(2) : '—'}</td>
            <td class="table-actions">
                <button class="btn btn-outline btn-sm" type="button" onclick="ackAlert(${alert.id})">Acknowledge</button>
                <button class="btn btn-primary btn-sm" type="button" onclick="resolveAlert(${alert.id})">Resolve</button>
            </td>
        `;
        table.appendChild(tr);
    });
}

function renderEvents(events) {
    const table = document.querySelector('#eventsTable tbody');
    table.innerHTML = '';
    events.forEach(event => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${event.rig_name || ('Rig #' + event.rig_id)}</strong><br><small>${event.rig_code || ''}</small></td>
            <td>${event.metric_label || event.metric_key}</td>
            <td>${event.metric_value !== null ? Number(event.metric_value).toFixed(2) + ' ' + (event.metric_unit || '') : '—'}</td>
            <td><span class="badge ${event.status}">${event.status.charAt(0).toUpperCase() + event.status.slice(1)}</span></td>
            <td>${event.recorded_at ? new Date(event.recorded_at).toLocaleString() : ''}</td>
            <td>${event.source ? event.source.charAt(0).toUpperCase() + event.source.slice(1) : ''}</td>
        `;
        table.appendChild(tr);
    });
}

async function ackAlert(alertId) {
    if (!alertId) return;
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
    formData.append('alert_id', alertId);
    formData.append('action', 'acknowledge');

    const response = await fetch('../api/rig-telemetry-alerts.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        refreshTelemetry();
    }
}

async function resolveAlert(alertId) {
    if (!alertId) return;
    const formData = new FormData();
    formData.append('csrf_token', '<?php echo CSRF::getToken(); ?>');
    formData.append('alert_id', alertId);
    formData.append('action', 'resolve');

    const response = await fetch('../api/rig-telemetry-alerts.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) {
        refreshTelemetry();
    }
}

refreshTelemetry();
setInterval(refreshTelemetry, 60000);
</script>

<?php require_once '../includes/footer.php'; ?>

