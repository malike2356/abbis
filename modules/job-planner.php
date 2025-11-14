<?php
$page_title = 'Smart Job Planner';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole([ROLE_ADMIN, ROLE_MANAGER, ROLE_SUPERVISOR]);

$pdo = getDBConnection();

/**
 * Fetch configured map provider.
 */
function getMapSettings(PDO $pdo): array
{
    $provider = 'leaflet';
    $apiKey = '';

    try {
        $stmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IN ('map_provider','map_api_key')");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['config_key'] === 'map_provider' && !empty($row['config_value'])) {
                $provider = $row['config_value'];
            }
            if ($row['config_key'] === 'map_api_key' && !empty($row['config_value'])) {
                $apiKey = $row['config_value'];
            }
        }
    } catch (PDOException $e) {
        // fall back to defaults
    }

    if ($provider !== 'google') {
        $provider = 'leaflet';
    }

    return [
        'provider' => $provider,
        'api_key' => $apiKey,
    ];
}

/**
 * Load active rigs with their last known coordinates.
 */
function loadActiveRigs(PDO $pdo): array
{
    $rigs = [];
    $rigNameById = [];

    $rows = $pdo->query("
        SELECT id, rig_name, rig_code, status, current_latitude, current_longitude, current_location_updated_at
        FROM rigs
        WHERE status = 'active'
        ORDER BY rig_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $rigNameById[(int)$row['id']] = $row['rig_name'];
        $lat = $row['current_latitude'] !== null ? (float)$row['current_latitude'] : null;
        $lng = $row['current_longitude'] !== null ? (float)$row['current_longitude'] : null;

        if ($lat === null || $lng === null) {
            $location = findRigLastKnownLocation($pdo, (int)$row['id']);
            $lat = $location['latitude'];
            $lng = $location['longitude'];
            $row['origin_source'] = $location['source'];
        } else {
            $row['origin_source'] = 'current_location';
        }

        $row['current_latitude'] = $lat;
        $row['current_longitude'] = $lng;
        $row['current_location_updated_at'] = $row['current_location_updated_at'] ?? null;
        $row['name_with_code'] = sprintf('%s (%s)', $row['rig_name'], $row['rig_code']);

        $rigs[] = $row;
    }

    return [
        'rigs' => $rigs,
        'rig_names' => $rigNameById,
    ];
}

/**
 * Find most recent field report coordinates for a rig.
 */
function findRigLastKnownLocation(PDO $pdo, int $rigId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT latitude, longitude, report_date
            FROM field_reports
            WHERE rig_id = ? AND latitude IS NOT NULL AND longitude IS NOT NULL
            ORDER BY report_date DESC
            LIMIT 1
        ");
        $stmt->execute([$rigId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return [
                'latitude' => (float)$row['latitude'],
                'longitude' => (float)$row['longitude'],
                'source' => 'last_report',
            ];
        }
    } catch (PDOException $e) {
        // ignore
    }

    return [
        'latitude' => null,
        'longitude' => null,
        'source' => 'unknown',
    ];
}

/**
 * Load pending rig requests that need scheduling.
 */
function loadRigRequests(PDO $pdo): array
{
    $sql = "
        SELECT
            rr.id,
            rr.request_number,
            rr.requester_name,
            rr.requester_email,
            rr.requester_phone,
            rr.requester_type,
            rr.company_name,
            rr.location_address,
            rr.latitude,
            rr.longitude,
            rr.region,
            rr.number_of_boreholes,
            rr.estimated_budget,
            rr.preferred_start_date,
            rr.status,
            rr.urgency,
            rr.assigned_rig_id,
            rr.created_at,
            c.client_name
        FROM rig_requests rr
        LEFT JOIN clients c ON rr.client_id = c.id
        WHERE rr.status IN ('new','under_review','negotiating','dispatched')
        ORDER BY
            FIELD(rr.urgency, 'urgent','high','medium','low'),
            rr.preferred_start_date IS NULL,
            rr.preferred_start_date ASC,
            rr.created_at ASC
    ";

    $jobs = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['latitude'] = $row['latitude'] !== null ? (float)$row['latitude'] : null;
        $row['longitude'] = $row['longitude'] !== null ? (float)$row['longitude'] : null;
        $row['assigned_rig_id'] = $row['assigned_rig_id'] !== null ? (int)$row['assigned_rig_id'] : null;
        $row['estimated_budget'] = $row['estimated_budget'] !== null ? (float)$row['estimated_budget'] : null;
        $row['preferred_start_date'] = $row['preferred_start_date'] ?? null;
        $row['urgency'] = $row['urgency'] ?? 'medium';
        $jobs[] = $row;
    }

    return $jobs;
}

function computeUrgencyWeight(string $urgency): int
{
    return match ($urgency) {
        'urgent' => 0,
        'high' => 1,
        'medium' => 2,
        'low' => 3,
        default => 2,
    };
}

function haversineDistance(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float
{
    if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) {
        return null;
    }

    $earthRadius = 6371; // km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earthRadius * $c, 2);
}

function computeJobScore(
    string $objective,
    float $distance,
    int $urgencyWeight,
    int $dayOffset,
    float $estimatedBudget,
    bool $alreadyAssignedToRig
): float {
    $assignedBonus = $alreadyAssignedToRig ? -5 : 0;

    return match ($objective) {
        'profit' => ($distance * 0.4) - ($estimatedBudget / 500) + ($urgencyWeight * 4) + ($dayOffset * 1.2) + $assignedBonus,
        'distance' => ($distance) + ($urgencyWeight * 2) + ($dayOffset) + $assignedBonus,
        default => ($distance * 0.6) + ($urgencyWeight * 6) + ($dayOffset * 2) - ($estimatedBudget / 700) + $assignedBonus,
    };
}

/**
 * Build route plans for rigs.
 *
 * @return array{plans: array<int, array>, unscheduled: array<int, array>, missing_location: array<int, array>}
 */
function buildRoutePlans(array $rigs, array $jobs, DateTime $startDate, int $horizonDays, string $objective): array
{
    $plans = [];
    $jobsMissingLocation = [];
    $jobPool = [];
    $remainingJobIds = [];
    $reservedJobs = [];

    // Determine horizon end date (inclusive)
    $horizonEnd = clone $startDate;
    $horizonEnd->modify('+' . ($horizonDays - 1) . ' days');

    foreach ($jobs as $job) {
        if ($job['latitude'] === null || $job['longitude'] === null) {
            $jobsMissingLocation[] = $job;
            continue;
        }

        // Skip jobs whose preferred start date is beyond the horizon
        if (!empty($job['preferred_start_date'])) {
            $preferred = DateTime::createFromFormat('Y-m-d', $job['preferred_start_date']);
            if ($preferred && $preferred > $horizonEnd) {
                $job['schedule_reason'] = 'Preferred start date beyond selected horizon.';
                $reservedJobs[$job['id']] = [
                    'reason' => 'Preferred start date beyond selected horizon.',
                ];
                $jobPool[$job['id']] = $job;
                $remainingJobIds[$job['id']] = $job['id'];
                continue;
            }
        }

        if (!empty($job['assigned_rig_id'])) {
            $reservedJobs[$job['id']] = [
                'reason' => 'Already assigned to rig ID ' . $job['assigned_rig_id'],
            ];
        }

        $jobPool[$job['id']] = $job;
        $remainingJobIds[$job['id']] = $job['id'];
    }

    if (empty($jobPool) && empty($jobsMissingLocation)) {
        return [
            'plans' => [],
            'unscheduled' => [],
            'missing_location' => [],
        ];
    }

    $usedJobIds = [];
    $rigNameById = [];
    foreach ($rigs as $rig) {
        $rigNameById[(int)$rig['id']] = $rig['rig_name'];
    }

    foreach ($rigs as $rig) {
        $rigId = (int)$rig['id'];
        $currentDate = clone $startDate;
        $currentLat = $rig['current_latitude'];
        $currentLng = $rig['current_longitude'];
        $rigStops = [];
        $sequence = 1;
        $totalDistance = 0.0;

        for ($day = 0; $day < $horizonDays; $day++) {
            $bestJobId = null;
            $bestScore = null;
            $bestDistance = null;

            foreach ($jobPool as $jobId => $job) {
                if (isset($usedJobIds[$jobId])) {
                    continue;
                }

                if (!empty($job['assigned_rig_id']) && $job['assigned_rig_id'] !== $rigId) {
                    continue;
                }

                $distance = haversineDistance(
                    $currentLat,
                    $currentLng,
                    $job['latitude'],
                    $job['longitude']
                );

                if ($distance === null) {
                    continue;
                }

                $urgencyWeight = computeUrgencyWeight($job['urgency']);

                $preferredDaysOffset = 0;
                if (!empty($job['preferred_start_date'])) {
                    $preferred = DateTime::createFromFormat('Y-m-d', $job['preferred_start_date']);
                    if ($preferred) {
                        $preferredDaysOffset = max(0, (int)$preferred->diff($currentDate)->format('%r%a'));
                    }
                }

                $score = computeJobScore(
                    $objective,
                    $distance,
                    $urgencyWeight,
                    $preferredDaysOffset,
                    $job['estimated_budget'] ?? 0.0,
                    !empty($job['assigned_rig_id']) && $job['assigned_rig_id'] === $rigId
                );

                if ($bestScore === null || $score < $bestScore) {
                    $bestScore = $score;
                    $bestJobId = $jobId;
                    $bestDistance = $distance;
                }
            }

            if ($bestJobId === null) {
                $currentDate->modify('+1 day');
                continue;
            }

            $job = $jobPool[$bestJobId];
            $usedJobIds[$bestJobId] = true;
            unset($remainingJobIds[$bestJobId]);

            $recommendedDate = $currentDate->format('Y-m-d');
            $travelKm = $bestDistance ?? 0.0;
            if ($sequence === 1 && ($currentLat === null || $currentLng === null)) {
                // First stop acts as origin when rig has no coordinates.
                $travelKm = 0.0;
            }

            $totalDistance += $travelKm;

            $rigStops[] = [
                'sequence' => $sequence,
                'job_id' => $job['id'],
                'request_number' => $job['request_number'],
                'client_name' => $job['client_name'] ?? $job['requester_name'],
                'location_address' => $job['location_address'],
                'region' => $job['region'],
                'recommended_date' => $recommendedDate,
                'urgency' => $job['urgency'],
                'estimated_budget' => $job['estimated_budget'],
                'travel_distance_km' => round($travelKm, 2),
                'assigned_rig_id' => $job['assigned_rig_id'],
                'latitude' => $job['latitude'],
                'longitude' => $job['longitude'],
                'preferred_start_date' => $job['preferred_start_date'],
                'status' => $job['status'],
                'requester_type' => $job['requester_type'],
            ];

            $currentLat = $job['latitude'];
            $currentLng = $job['longitude'];
            $sequence++;
            $currentDate->modify('+1 day');
        }

        if (!empty($rigStops)) {
            $plans[] = [
                'rig' => $rig,
                'stops' => $rigStops,
                'total_distance_km' => round($totalDistance, 2),
            ];
        }
    }

    $unscheduledJobs = [];
    foreach ($remainingJobIds as $jobId) {
        $job = $jobPool[$jobId];
        $reason = $reservedJobs[$jobId]['reason'] ?? 'Limited capacity within selected horizon.';
        if (!empty($job['assigned_rig_id']) && isset($rigNameById[$job['assigned_rig_id']])) {
            $reason = 'Assigned to ' . $rigNameById[$job['assigned_rig_id']];
        }
        $job['unscheduled_reason'] = $reason;
        $unscheduledJobs[] = $job;
    }

    return [
        'plans' => $plans,
        'unscheduled' => $unscheduledJobs,
        'missing_location' => $jobsMissingLocation,
    ];
}

function buildMapData(array $plans): array
{
    $colors = [
        '#2563eb',
        '#16a34a',
        '#f97316',
        '#7c3aed',
        '#0ea5e9',
        '#dc2626',
        '#059669',
        '#b45309',
    ];

    $data = [];
    foreach ($plans as $index => $plan) {
        $rig = $plan['rig'];
        $stops = [];
        foreach ($plan['stops'] as $stop) {
            if ($stop['latitude'] === null || $stop['longitude'] === null) {
                continue;
            }
            $stops[] = [
                'lat' => $stop['latitude'],
                'lng' => $stop['longitude'],
                'label' => sprintf('#%d %s', $stop['sequence'], $stop['client_name']),
                'sequence' => $stop['sequence'],
                'date' => $stop['recommended_date'],
                'request_number' => $stop['request_number'],
            ];
        }

        if (empty($stops)) {
            continue;
        }

        $startLat = $rig['current_latitude'];
        $startLng = $rig['current_longitude'];
        if ($startLat === null || $startLng === null) {
            $startLat = $stops[0]['lat'];
            $startLng = $stops[0]['lng'];
        }

        $data[] = [
            'rig' => [
                'id' => $rig['id'],
                'name' => $rig['rig_name'],
                'code' => $rig['rig_code'],
            ],
            'color' => $colors[$index % count($colors)],
            'start' => [
                'lat' => $startLat,
                'lng' => $startLng,
            ],
            'stops' => $stops,
        ];
    }

    return $data;
}

function formatDistance(?float $km): string
{
    if ($km === null) {
        return '—';
    }
    if ($km >= 1) {
        return number_format($km, 1) . ' km';
    }
    return number_format($km * 1000, 0) . ' m';
}

$mapSettings = getMapSettings($pdo);
$rigData = loadActiveRigs($pdo);
$activeRigs = $rigData['rigs'];
$rigNames = $rigData['rig_names'];

$horizonParam = $_GET['horizon'] ?? '2w';
$objectiveParam = $_GET['objective'] ?? 'balance';
$startParam = $_GET['start'] ?? date('Y-m-d');

$horizonDays = $horizonParam === '4w' ? 28 : 14;
$startDate = DateTime::createFromFormat('Y-m-d', $startParam) ?: new DateTime();
$objective = in_array($objectiveParam, ['balance', 'profit', 'distance'], true) ? $objectiveParam : 'balance';

$jobs = loadRigRequests($pdo);
$routePlans = buildRoutePlans($activeRigs, $jobs, $startDate, $horizonDays, $objective);
$plans = $routePlans['plans'];
$unscheduledJobs = $routePlans['unscheduled'];
$jobsMissingLocation = $routePlans['missing_location'];
$mapData = buildMapData($plans);

$horizonLabel = $horizonParam === '4w' ? '4 weeks' : '2 weeks';
$hasMapData = !empty($mapData);
$csrfField = CSRF::getTokenField();

require_once '../includes/header.php';
?>

<style>
    .planner-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
        gap: 18px;
    }
    .planner-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .planner-card h3 {
        margin: 0;
        font-size: 20px;
        color: var(--text);
    }
    .planner-card table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .planner-card table th,
    .planner-card table td {
        padding: 8px 10px;
        border-bottom: 1px solid var(--border);
        vertical-align: top;
        text-align: left;
    }
    .planner-card table th {
        background: var(--table-header);
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.4px;
    }
    .planner-card table tr:last-child td {
        border-bottom: none;
    }
    .schedule-map {
        width: 100%;
        min-height: 420px;
        border-radius: 12px;
        border: 1px solid var(--border);
    }
    .planner-meta {
        display: flex;
        gap: 32px;
        flex-wrap: wrap;
        color: var(--secondary);
        font-size: 13px;
    }
    .planner-meta strong {
        color: var(--text);
    }
    .unscheduled-list table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .unscheduled-list th,
    .unscheduled-list td {
        padding: 8px 10px;
        border-bottom: 1px solid var(--border);
        text-align: left;
    }
    .dispatch-status {
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 999px;
        padding: 4px 8px;
        background: var(--muted);
        color: var(--secondary);
    }
    .dispatch-status.success {
        background: rgba(34,197,94,0.15);
        color: #166534;
    }
    .dispatch-status.warning {
        background: rgba(234,179,8,0.15);
        color: #92400e;
    }
    .planner-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }
    .planner-alert {
        margin-top: 16px;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <h1>🗓️ Smart Job Planner</h1>
        <p>Optimize rig dispatch routes, minimize travel time, and lock in crew assignments.</p>
    </div>

    <div class="dashboard-card">
        <form method="get" class="planner-actions">
            <div>
                <label class="form-label">Planning horizon</label>
                <select name="horizon" class="form-control">
                    <option value="2w" <?php echo $horizonParam === '2w' ? 'selected' : ''; ?>>2 weeks</option>
                    <option value="4w" <?php echo $horizonParam === '4w' ? 'selected' : ''; ?>>4 weeks</option>
                </select>
            </div>
            <div>
                <label class="form-label">Start from</label>
                <input type="date" name="start" class="form-control" value="<?php echo e($startDate->format('Y-m-d')); ?>">
            </div>
            <div>
                <label class="form-label">Optimization goal</label>
                <select name="objective" class="form-control">
                    <option value="balance" <?php echo $objective === 'balance' ? 'selected' : ''; ?>>Balance profit & distance</option>
                    <option value="profit" <?php echo $objective === 'profit' ? 'selected' : ''; ?>>Maximize profit</option>
                    <option value="distance" <?php echo $objective === 'distance' ? 'selected' : ''; ?>>Minimize travel distance</option>
                </select>
            </div>
            <div style="align-self:flex-end;">
                <button class="btn btn-primary">Rebuild schedule</button>
            </div>
        </form>
        <div class="planner-meta" style="margin-top: 16px;">
            <div><strong>Horizon:</strong> <?php echo e($horizonLabel); ?></div>
            <div><strong>Objective:</strong> <?php echo e(ucfirst($objective)); ?></div>
            <div><strong>Start date:</strong> <?php echo e($startDate->format('j M Y')); ?></div>
            <div><strong>Pending jobs:</strong> <?php echo count($jobs); ?></div>
        </div>
    </div>

    <?php if (empty($activeRigs)): ?>
        <div class="alert alert-warning planner-alert">
            No active rigs found. Add rigs under Configuration → Rigs to start planning.
        </div>
    <?php endif; ?>

    <?php if (!empty($plans)): ?>
        <div class="dashboard-card">
            <h2>Route Map</h2>
            <?php if ($hasMapData): ?>
                <div id="scheduleMap" class="schedule-map"
                     data-provider="<?php echo e($mapSettings['provider']); ?>"
                     data-api-key="<?php echo e($mapSettings['api_key']); ?>"></div>
            <?php else: ?>
                <p style="color: var(--secondary);">Schedule ready, but map markers require valid coordinates. Add latitude/longitude for each job to visualize routes.</p>
            <?php endif; ?>
        </div>

        <div class="planner-grid">
            <?php foreach ($plans as $index => $plan): ?>
                <?php
                    $rig = $plan['rig'];
                    $color = $mapData[$index]['color'] ?? '#2563eb';
                ?>
                <div class="planner-card" style="border-top: 4px solid <?php echo $color; ?>">
                    <div>
                        <h3><?php echo e($rig['name_with_code']); ?></h3>
                        <p style="color: var(--secondary); margin: 6px 0 12px;">
                            Start point:
                            <?php if ($rig['current_latitude'] !== null && $rig['current_longitude'] !== null): ?>
                                Live coordinates updated <?php echo $rig['current_location_updated_at'] ? e(date('j M Y H:i', strtotime($rig['current_location_updated_at']))) : 'recently'; ?>
                            <?php else: ?>
                                Based on last field report
                            <?php endif; ?>
                        </p>
                        <div class="planner-meta" style="gap: 12px;">
                            <div><strong>Stops:</strong> <?php echo count($plan['stops']); ?></div>
                            <div><strong>Travel:</strong> <?php echo e(formatDistance($plan['total_distance_km'])); ?></div>
                        </div>
                    </div>

                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Job</th>
                                <th>Distance</th>
                                <th>Urgency</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($plan['stops'] as $stop): ?>
                                <tr id="job-row-<?php echo (int)$stop['job_id']; ?>">
                                    <td><?php echo (int)$stop['sequence']; ?></td>
                                    <td>
                                        <div><?php echo e(date('D, j M', strtotime($stop['recommended_date']))); ?></div>
                                        <?php if (!empty($stop['preferred_start_date']) && $stop['preferred_start_date'] !== $stop['recommended_date']): ?>
                                            <small style="color: var(--secondary);">Preferred: <?php echo e(date('j M', strtotime($stop['preferred_start_date']))); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo e($stop['client_name']); ?></strong><br>
                                        <small style="color: var(--secondary);">#<?php echo e($stop['request_number']); ?> · <?php echo e($stop['location_address']); ?></small>
                                    </td>
                                    <td><?php echo e(formatDistance($stop['travel_distance_km'])); ?></td>
                                    <td>
                                        <span class="dispatch-status <?php echo $stop['urgency'] === 'urgent' ? 'warning' : ''; ?>">
                                            <?php echo ucfirst($stop['urgency']); ?>
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <form class="dispatch-form" method="post" action="<?php echo api_url('dispatch-rig-requests.php'); ?>">
                                            <?php echo $csrfField; ?>
                                            <input type="hidden" name="rig_id" value="<?php echo (int)$rig['id']; ?>">
                                            <input type="hidden" name="job_id" value="<?php echo (int)$stop['job_id']; ?>">
                                            <input type="hidden" name="scheduled_date" value="<?php echo e($stop['recommended_date']); ?>">
                                            <input type="hidden" name="distance_km" value="<?php echo e($stop['travel_distance_km']); ?>">
                                            <input type="hidden" name="sequence" value="<?php echo (int)$stop['sequence']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <?php echo $stop['status'] === 'dispatched' ? 'Update Dispatch' : 'Dispatch Rig'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info planner-alert">
            No optimized routes yet. Add rig requests with location coordinates and try rebuilding the schedule.
        </div>
    <?php endif; ?>

    <?php if (!empty($unscheduledJobs)): ?>
        <div class="dashboard-card unscheduled-list">
            <h2>Jobs left to schedule</h2>
            <p style="color: var(--secondary);">These requests were not placed on the current plan.</p>
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Request</th>
                            <th>Location</th>
                            <th>Urgency</th>
                            <th>Preferred Date</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unscheduledJobs as $job): ?>
                            <tr>
                                <td><strong>#<?php echo e($job['request_number']); ?></strong><br><small><?php echo e($job['client_name'] ?? $job['requester_name']); ?></small></td>
                                <td><?php echo e($job['location_address']); ?></td>
                                <td><?php echo ucfirst($job['urgency']); ?></td>
                                <td><?php echo $job['preferred_start_date'] ? e(date('j M Y', strtotime($job['preferred_start_date']))) : '—'; ?></td>
                                <td><?php echo e($job['unscheduled_reason']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($jobsMissingLocation)): ?>
        <div class="alert alert-warning planner-alert">
            <strong><?php echo count($jobsMissingLocation); ?> request(s)</strong> are missing coordinates. Add latitude & longitude to rig requests so they can be routed.
        </div>
    <?php endif; ?>
</div>

<?php if ($hasMapData): ?>
    <?php if ($mapSettings['provider'] === 'leaflet'): ?>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <?php else: ?>
        <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo e($mapSettings['api_key']); ?>&libraries=geometry"></script>
    <?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const mapData = <?php echo json_encode($mapData, JSON_UNESCAPED_UNICODE); ?>;
    const mapContainer = document.getElementById('scheduleMap');
    const provider = mapContainer ? mapContainer.dataset.provider : 'leaflet';
    const dispatchForms = document.querySelectorAll('.dispatch-form');

    dispatchForms.forEach(form => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            if (!submitBtn) {
                return;
            }

            submitBtn.disabled = true;
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Saving...';

            try {
                const formData = new FormData(form);
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) {
                    throw new Error(result.message || 'Dispatch failed');
                }

                submitBtn.textContent = 'Dispatched';
                submitBtn.classList.remove('btn-primary');
                submitBtn.classList.add('btn-success');

                const row = form.closest('tr');
                if (row) {
                    row.classList.add('table-success');
                }
            } catch (error) {
                alert(error.message);
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });
    });

    if (!mapContainer || mapData.length === 0) {
        return;
    }

    if (provider === 'google' && typeof google !== 'undefined') {
        initGoogleMap(mapContainer, mapData);
    } else if (typeof L !== 'undefined') {
        initLeafletMap(mapContainer, mapData);
    }
});

function initLeafletMap(container, data) {
    const map = L.map(container).setView([5.614818, -0.205874], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const bounds = [];

    data.forEach(route => {
        const color = route.color || '#2563eb';
        const startMarker = L.marker([route.start.lat, route.start.lng], {
            title: route.rig.name + ' start'
        }).addTo(map);
        startMarker.bindPopup(`<strong>${route.rig.name}</strong><br>Start position`);
        bounds.push([route.start.lat, route.start.lng]);

        const polylinePoints = [[route.start.lat, route.start.lng]];

        route.stops.forEach(stop => {
            const marker = L.circleMarker([stop.lat, stop.lng], {
                radius: 7,
                color,
                fillColor: color,
                fillOpacity: 0.85
            }).addTo(map);
            marker.bindPopup(`<strong>${stop.label}</strong><br>${stop.date}`);
            bounds.push([stop.lat, stop.lng]);
            polylinePoints.push([stop.lat, stop.lng]);
        });

        L.polyline(polylinePoints, {color, weight: 3, opacity: 0.8}).addTo(map);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, {padding: [30, 30]});
    }
}

function initGoogleMap(container, data) {
    const map = new google.maps.Map(container, {
        zoom: 7,
        center: {lat: 6.0, lng: -1.0},
        mapTypeId: 'roadmap'
    });

    const bounds = new google.maps.LatLngBounds();

    data.forEach(route => {
        const color = route.color || '#2563eb';
        const startLatLng = new google.maps.LatLng(route.start.lat, route.start.lng);
        new google.maps.Marker({
            position: startLatLng,
            map,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 6,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 1.5,
            },
            title: `${route.rig.name} start`
        });
        bounds.extend(startLatLng);

        const path = [startLatLng];

        route.stops.forEach(stop => {
            const stopLatLng = new google.maps.LatLng(stop.lat, stop.lng);
            new google.maps.Marker({
                position: stopLatLng,
                map,
                label: String(stop.sequence),
                title: `${stop.label} (${stop.date})`
            });
            bounds.extend(stopLatLng);
            path.push(stopLatLng);
        });

        new google.maps.Polyline({
            path,
            geodesic: true,
            strokeColor: color,
            strokeOpacity: 0.8,
            strokeWeight: 3,
            map
        });
    });

    if (!bounds.isEmpty()) {
        map.fitBounds(bounds, {padding: 60});
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>

