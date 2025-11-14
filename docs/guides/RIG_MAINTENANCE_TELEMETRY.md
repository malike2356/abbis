# Rig Maintenance Telemetry Guide

## Overview
The Rig Maintenance Telemetry module ingests live data from rig-mounted sensors and uses threshold rules to generate proactive maintenance alerts. Streams are associated with rigs, and each incoming event is logged for traceability, analytics, and compliance.

## 1. Database Setup
Run the migration to create telemetry tables:

```bash
php scripts/setup-rig-maintenance-telemetry.php
```

Tables created:
- `rig_maintenance_streams` – registered data sources and API tokens
- `rig_telemetry_events` – raw sensor readings
- `rig_telemetry_thresholds` – automation rules per metric
- `rig_maintenance_alerts` – active alerts awaiting resolution

## 2. Creating a Telemetry Stream
1. Navigate to `Resources → Rig Telemetry`.
2. Use the **Create Telemetry Stream** form:
   - Pick the rig to monitor.
   - Provide a stream name (e.g., “Compressor Sensors”).
   - Optionally record the device identifier and allowed metrics (comma separated).
3. Submit the form and copy the generated token. The token is shown once and should be stored securely (IoT device, middleware service, etc.).
4. Streams can be paused or revoked from the Streams table.

### API Token Format
- Header: `X-Stream-Token: <token>` (preferred) or `Authorization: Bearer <token>`
- The token maps to the stream and rig automatically; the payload does not need to specify credentials.

## 3. Sending Telemetry Data
POST to `/api/rig-telemetry-ingest.php` with JSON:

```json
{
  "events": [
    {
      "metric": "engine_temp",
      "value": 93.6,
      "unit": "°C",
      "label": "Engine Temperature",
      "recorded_at": "2025-11-13T08:15:00Z",
      "payload": {
        "pressure": 1.2,
        "rpm": 1550
      }
    }
  ],
  "heartbeat": true,
  "heartbeat_payload": {
    "firmware": "1.0.4"
  }
}
```

**Notes**
- `metric` (or `metric_key`) and `value` are required for each event.
- `recorded_at` defaults to current server time if omitted.
- `payload` may contain arbitrary JSON with supporting data.
- Setting `heartbeat` to `true` updates the stream’s last heartbeat timestamp even if no events are sent.

Successful responses include the evaluation status (normal/warning/critical) for each metric.

## 4. Configuring Thresholds
Thresholds convert telemetry into actionable alerts:

1. Open the **Thresholds & Automation** form.
2. Choose the rig and metric key (e.g., `engine_temp`).
3. Select the comparison type:
   - **Greater Than** – triggers when value ≥ threshold
   - **Less Than** – triggers when value ≤ threshold
   - **Equals** – triggers on exact match
   - **Delta** – triggers when change from last reading ≥ threshold
4. Provide warning and/or critical thresholds (optional to set either or both).
5. Optional duration (minutes) ensures the value stays over the threshold before alerting.
6. Save. Re-submitting the same rig/metric updates the existing rule.

Thresholds drive automatic creation of `rig_maintenance_alerts`. Warnings and critical alerts appear in the dashboard and the maintenance analytics.

## 5. Managing Alerts
- Alerts are shown in the **Live Alerts** table. New telemetry inserts automatically update the table (auto-refresh every minute or manual Refresh button).
- Actions:
  - **Acknowledge** – sets status to `acknowledged` (assigned to the user who clicked).
  - **Resolve** – closes the alert, optionally linking it to a maintenance record.
- REST endpoint: `POST /api/rig-telemetry-alerts.php` with `action` (`acknowledge` or `resolve`), `alert_id`, and CSRF token (when using browser session).

## 6. Dashboard & Analytics
`/api/rig-telemetry-dashboard.php` returns JSON consumption metrics for single-page apps or BI tooling:
- Summary counters (active streams, open/critical alerts, events today)
- Current alerts (rig, severity, values)
- Latest telemetry events

The Rig Telemetry module itself refreshes using this endpoint. You can consume it in custom dashboards with authenticated requests.

## 7. Security Considerations
- API tokens are hashed at rest (`ingest_token_hash`) and displayed only once.
- Revoke tokens immediately if a device is compromised.
- All authenticated UI actions require CSRF tokens and `resources.access` permission.
- Consider placing the ingest endpoint behind HTTPS and network restrictions when deploying.

## 8. Troubleshooting
- **401 Invalid stream token**: Confirm the token copied during stream creation; tokens are case sensitive.
- **No events recorded**: Check network access to the server and that the payload is valid JSON with a numeric `value`.
- **Alerts not auto-closing**: Alerts stay open until resolved manually or via API so that maintenance is documented.
- **Token lost?** Revoking the stream and creating a new one is safer than trying to recover the old token.

## 9. Change Log
- **v1.0** – Initial telemetry ingestion, threshold automation, and UI (ABBIS v3.2).

