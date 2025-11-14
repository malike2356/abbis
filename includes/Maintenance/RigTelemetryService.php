<?php

declare(strict_types=1);

class RigTelemetryService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
    }

    /**
     * Generate a secure ingest token.
     */
    public function generateToken(int $length = 48): string
    {
        return bin2hex(random_bytes(max(16, (int)ceil($length / 2))));
    }

    /**
     * Create a telemetry stream for a rig.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function createStream(int $rigId, array $data, int $userId = null): array
    {
        $token = $this->generateToken();
        $hash = hash('sha256', $token);
        $tokenPreview = substr($token, 0, 10);

        $stmt = $this->pdo->prepare("
            INSERT INTO rig_maintenance_streams
            (rig_id, stream_name, device_identifier, ingest_token_hash, token_preview, allowed_metrics, status, created_by)
            VALUES (:rig_id, :stream_name, :device_identifier, :ingest_token_hash, :token_preview, :allowed_metrics, :status, :created_by)
        ");

        $stmt->execute([
            ':rig_id' => $rigId,
            ':stream_name' => $data['stream_name'] ?? 'Rig Stream',
            ':device_identifier' => $data['device_identifier'] ?? null,
            ':ingest_token_hash' => $hash,
            ':token_preview' => $tokenPreview,
            ':allowed_metrics' => isset($data['allowed_metrics']) ? json_encode($data['allowed_metrics']) : null,
            ':status' => $data['status'] ?? 'active',
            ':created_by' => $userId,
        ]);

        $streamId = (int)$this->pdo->lastInsertId();

        return [
            'id' => $streamId,
            'token' => $token,
            'token_preview' => $tokenPreview,
        ];
    }

    /**
     * Resolve stream by raw token.
     *
     * @return array<string,mixed>|null
     */
    public function findStreamByToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $stmt = $this->pdo->prepare("SELECT * FROM rig_maintenance_streams WHERE ingest_token_hash = ? AND status != 'revoked' LIMIT 1");
        $stmt->execute([$hash]);
        $stream = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $stream ?: null;
    }

    /**
     * Record telemetry event and return metadata.
     *
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function recordEvent(int $rigId, string $metricKey, $value, array $options = []): array
    {
        $streamId = isset($options['stream_id']) ? (int)$options['stream_id'] : null;
        $metricValue = is_numeric($value) ? (float)$value : null;
        $metricLabel = $options['metric_label'] ?? null;
        $metricUnit = $options['metric_unit'] ?? null;
        $source = $options['source'] ?? 'telemetry';
        $payload = $options['payload'] ?? null;
        $recordedAt = isset($options['recorded_at']) ? date('Y-m-d H:i:s', strtotime((string)$options['recorded_at'])) : date('Y-m-d H:i:s');

        $status = 'normal';
        $threshold = null;

        if ($metricValue !== null) {
            $threshold = $this->evaluateThreshold($rigId, $metricKey, $metricValue, $recordedAt);
            if ($threshold) {
                $status = $threshold['status'];
            }
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO rig_telemetry_events
            (rig_id, stream_id, metric_key, metric_label, metric_value, metric_unit, status, source, recorded_at, payload)
            VALUES (:rig_id, :stream_id, :metric_key, :metric_label, :metric_value, :metric_unit, :status, :source, :recorded_at, :payload)
        ");

        $stmt->execute([
            ':rig_id' => $rigId,
            ':stream_id' => $streamId,
            ':metric_key' => $metricKey,
            ':metric_label' => $metricLabel,
            ':metric_value' => $metricValue,
            ':metric_unit' => $metricUnit,
            ':status' => $status,
            ':source' => $source,
            ':recorded_at' => $recordedAt,
            ':payload' => $payload ? json_encode($payload) : null,
        ]);

        if ($streamId) {
            $updateStmt = $this->pdo->prepare("
                UPDATE rig_maintenance_streams
                SET last_event_at = :last_event_at,
                    last_payload = :payload,
                    updated_at = NOW()
                WHERE id = :stream_id
            ");
            $updateStmt->execute([
                ':last_event_at' => $recordedAt,
                ':payload' => $payload ? json_encode($payload) : null,
                ':stream_id' => $streamId,
            ]);
        }

        if ($threshold && in_array($threshold['status'], ['warning', 'critical'], true)) {
            $this->createOrUpdateAlert($rigId, $streamId, $metricKey, $threshold, $metricValue, $recordedAt, $payload);
        }

        return [
            'status' => $status,
            'threshold' => $threshold,
        ];
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array<string,mixed>|null
     */
    private function createOrUpdateAlert(
        int $rigId,
        ?int $streamId,
        string $metricKey,
        array $threshold,
        float $value,
        string $recordedAt,
        ?array $payload = null
    ): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM rig_maintenance_alerts
            WHERE rig_id = ? AND metric_key = ? AND status IN ('open','acknowledged')
            ORDER BY triggered_at DESC
            LIMIT 1
        ");
        $stmt->execute([$rigId, $metricKey]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $title = $threshold['status'] === 'critical'
            ? sprintf('Critical %s alert', $threshold['metric_label'])
            : sprintf('Warning: %s approaching limit', $threshold['metric_label']);

        $message = sprintf(
            '%s reading of %s%s breached %s threshold (%s%s).',
            $threshold['metric_label'],
            number_format($value, 2),
            $threshold['metric_unit'],
            $threshold['status'],
            number_format((float)$threshold['trigger_threshold'], 2),
            $threshold['metric_unit']
        );

        if ($existing) {
            $update = $this->pdo->prepare("
                UPDATE rig_maintenance_alerts
                SET severity = :severity,
                    message = :message,
                    trigger_value = :trigger_value,
                    threshold_value = :threshold_value,
                    triggered_at = :triggered_at,
                    context_payload = :payload,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute([
                ':severity' => $threshold['status'],
                ':message' => $message,
                ':trigger_value' => $value,
                ':threshold_value' => $threshold['trigger_threshold'],
                ':triggered_at' => $recordedAt,
                ':payload' => $payload ? json_encode($payload) : null,
                ':id' => $existing['id'],
            ]);

            return $this->getAlertById((int)$existing['id']);
        }

        $insert = $this->pdo->prepare("
            INSERT INTO rig_maintenance_alerts
            (rig_id, stream_id, metric_key, alert_type, severity, title, message, status,
             trigger_value, threshold_value, triggered_at, context_payload)
            VALUES (:rig_id, :stream_id, :metric_key, 'threshold', :severity, :title, :message, 'open',
                    :trigger_value, :threshold_value, :triggered_at, :payload)
        ");

        $insert->execute([
            ':rig_id' => $rigId,
            ':stream_id' => $streamId,
            ':metric_key' => $metricKey,
            ':severity' => $threshold['status'],
            ':title' => $title,
            ':message' => $message,
            ':trigger_value' => $value,
            ':threshold_value' => $threshold['trigger_threshold'],
            ':triggered_at' => $recordedAt,
            ':payload' => $payload ? json_encode($payload) : null,
        ]);

        return $this->getAlertById((int)$this->pdo->lastInsertId());
    }

    /**
     * @return array<string,mixed>|null
     */
    private function evaluateThreshold(int $rigId, string $metricKey, float $value, string $recordedAt): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM rig_telemetry_thresholds
            WHERE rig_id = ? AND metric_key = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$rigId, $metricKey]);
        $threshold = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$threshold) {
            return null;
        }

        $status = 'normal';
        $trigger = null;
        $warning = $threshold['warning_threshold'] !== null ? (float)$threshold['warning_threshold'] : null;
        $critical = $threshold['critical_threshold'] !== null ? (float)$threshold['critical_threshold'] : null;

        switch ($threshold['threshold_type']) {
            case 'less_than':
                if ($critical !== null && $value <= $critical) {
                    $status = 'critical';
                    $trigger = $critical;
                } elseif ($warning !== null && $value <= $warning) {
                    $status = 'warning';
                    $trigger = $warning;
                }
                break;
            case 'equals':
                if ($critical !== null && abs($value - $critical) < 0.0001) {
                    $status = 'critical';
                    $trigger = $critical;
                } elseif ($warning !== null && abs($value - $warning) < 0.0001) {
                    $status = 'warning';
                    $trigger = $warning;
                }
                break;
            case 'delta':
                $prevStmt = $this->pdo->prepare("
                    SELECT metric_value FROM rig_telemetry_events
                    WHERE rig_id = ? AND metric_key = ? AND recorded_at <= ?
                    ORDER BY recorded_at DESC
                    LIMIT 1 OFFSET 1
                ");
                $prevStmt->execute([$rigId, $metricKey, $recordedAt]);
                $prevValue = $prevStmt->fetchColumn();
                if ($prevValue !== false) {
                    $delta = abs($value - (float)$prevValue);
                    if ($critical !== null && $delta >= $critical) {
                        $status = 'critical';
                        $trigger = $critical;
                    } elseif ($warning !== null && $delta >= $warning) {
                        $status = 'warning';
                        $trigger = $warning;
                    }
                }
                break;
            case 'greater_than':
            default:
                if ($critical !== null && $value >= $critical) {
                    $status = 'critical';
                    $trigger = $critical;
                } elseif ($warning !== null && $value >= $warning) {
                    $status = 'warning';
                    $trigger = $warning;
                }
                break;
        }

        if ($status === 'normal') {
            return null;
        }

        return [
            'status' => $status,
            'trigger_threshold' => $trigger,
            'warning_threshold' => $warning,
            'critical_threshold' => $critical,
            'threshold_type' => $threshold['threshold_type'],
            'metric_label' => $threshold['metric_label'] ?: ucwords(str_replace('_', ' ', $metricKey)),
            'metric_unit' => $threshold['metric_unit'] ?? '',
        ];
    }

    /**
     * Acknowledge alert.
     */
    public function acknowledgeAlert(int $alertId, int $userId): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE rig_maintenance_alerts
            SET status = 'acknowledged', acknowledged_at = NOW(), acknowledged_by = ?
            WHERE id = ? AND status = 'open'
        ");
        return $stmt->execute([$userId, $alertId]);
    }

    /**
     * Resolve alert.
     */
    public function resolveAlert(int $alertId, int $userId, ?int $maintenanceRecordId = null): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE rig_maintenance_alerts
            SET status = 'resolved',
                resolved_at = NOW(),
                resolved_by = ?,
                maintenance_record_id = COALESCE(maintenance_record_id, ?)
            WHERE id = ? AND status IN ('open','acknowledged')
        ");
        return $stmt->execute([$userId, $maintenanceRecordId, $alertId]);
    }

    /**
     * Store heartbeat from device.
     *
     * @param array<string,mixed>|null $payload
     */
    public function logHeartbeat(int $streamId, ?array $payload = null): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE rig_maintenance_streams
            SET last_heartbeat_at = NOW(),
                last_payload = COALESCE(:payload, last_payload),
                updated_at = NOW()
            WHERE id = :stream_id
        ");
        $stmt->execute([
            ':payload' => $payload ? json_encode($payload) : null,
            ':stream_id' => $streamId,
        ]);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getAlertById(int $alertId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM rig_maintenance_alerts WHERE id = ? LIMIT 1");
        $stmt->execute([$alertId]);
        $alert = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $alert ?: null;
    }

    /**
     * Fetch telemetry summary for dashboards.
     *
     * @return array<string,mixed>
     */
    public function getDashboardSummary(int $rigId = null): array
    {
        $summary = [
            'alerts_open' => 0,
            'alerts_critical' => 0,
            'events_today' => 0,
            'streams_active' => 0,
        ];

        $whereRig = $rigId ? 'WHERE rig_id = :rig_id' : '';
        $params = $rigId ? [':rig_id' => $rigId] : [];

        $alerts = $this->pdo->prepare("
            SELECT
                SUM(status IN ('open','acknowledged')) AS open_count,
                SUM(status IN ('open','acknowledged') AND severity = 'critical') AS critical_count
            FROM rig_maintenance_alerts
            $whereRig
        ");
        $alerts->execute($params);
        $row = $alerts->fetch(PDO::FETCH_ASSOC) ?: [];
        $summary['alerts_open'] = (int)($row['open_count'] ?? 0);
        $summary['alerts_critical'] = (int)($row['critical_count'] ?? 0);

        $eventsQuery = $this->pdo->prepare("
            SELECT COUNT(*) FROM rig_telemetry_events
            WHERE DATE(recorded_at) = CURDATE() " . ($rigId ? "AND rig_id = :rig_id" : '')
        );
        $eventsQuery->execute($params);
        $summary['events_today'] = (int)$eventsQuery->fetchColumn();

        $streamsQuery = $this->pdo->prepare("
            SELECT COUNT(*) FROM rig_maintenance_streams
            WHERE status = 'active' " . ($rigId ? "AND rig_id = :rig_id" : '')
        );
        $streamsQuery->execute($params);
        $summary['streams_active'] = (int)$streamsQuery->fetchColumn();

        return $summary;
    }
}

