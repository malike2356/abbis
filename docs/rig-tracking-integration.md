# Rig Tracking Integration Guide

This document explains how to enable live rig tracking in ABBIS by connecting to third-party telematics providers (e.g. Fleetsmart, Rewire Security GPSLive, UK Telematics / Radius). It covers database updates, configuration, API contracts, UI behaviour, and operational best practices.

---

## 1. Overview

The rig tracking module (`modules/rig-tracking.php`) now supports live location updates sourced from external providers through a flexible metadata-driven client. Each rig can poll a provider’s API, persist the latest coordinates, draw historical markers, and surface provider health data inline on the map.

---

## 2. Prerequisites

1. **Database migration** – apply `database/migrations/20251109_rig_tracking_api_config.sql`.
2. **Provider account** – obtain API credentials, base URL, and device identifiers from your telematics vendor.
3. **Tracked rigs** – ensure `rigs` table entries exist and are marked `status = 'active'`.
4. **Map provider key** – set `map_provider` and `map_api_key` in `system_config` (Google Maps or Leaflet fallback).

---

## 3. Database Schema Changes

Running the migration adds the following columns to `rig_tracking_config`:

- `api_base_url` – base REST endpoint for the provider.
- `auth_method` – one of `none`, `bearer_token`, `api_key_header`, `query_param`, `basic_auth`.
- `config_payload` – JSON metadata describing endpoint paths, request templates, and response extraction.
- `last_error_at` – timestamp of the most recent sync failure.

Existing columns (`api_key`, `api_secret`, `device_id`, `update_frequency`, `status`, etc.) continue to be used.

---

## 4. Configuration Workflow

1. **Insert / update rig configuration**
   ```sql
   INSERT INTO rig_tracking_config (
       rig_id,
       tracking_enabled,
       tracking_method,
       tracking_provider,
       device_id,
       api_key,
       api_secret,
       api_base_url,
       auth_method,
       update_frequency,
       config_payload
   ) VALUES (
       4,                         -- rig_id
       1,                         -- tracking_enabled
       'third_party_api',         -- tracking_method
       'fleetsmart',              -- provider (case-insensitive)
       'ASSET-123',               -- device_id from provider
       'your-api-key',            -- token or username
       NULL,                      -- secret/password (if required)
       'https://api.fleetsmart.co.uk',
       'bearer_token',
       300,                       -- seconds between background syncs
       '{
         "location_endpoint": "/api/v1/assets/{{device_id}}/latest-location",
         "http_method": "GET",
         "lat_path": "data.location.latitude",
         "lng_path": "data.location.longitude",
         "speed_path": "data.telemetry.speed",
         "heading_path": "data.telemetry.heading",
         "timestamp_path": "data.timestamp",
         "headers": ["Accept: application/json"]
       }'
   )
   ON DUPLICATE KEY UPDATE ...;
   ```

2. **Available template variables**
   - `{{device_id}}`, `{{api_key}}`, `{{api_secret}}`
   - Any custom variables defined under `config_payload.variables`

3. **`config_payload` keys**
   | Key | Purpose | Example |
   | --- | --- | --- |
   | `location_endpoint` | Endpoint (relative or absolute) | `/devices/{{device_id}}/location` |
   | `http_method` | `GET`, `POST`, etc. | `GET` |
   | `headers[]` | Extra headers with templates | `["X-Tenant: abc"]` |
   | `query` | Map of querystring parameters | `{ "key": "{{api_key}}" }` |
   | `body` | JSON payload (object or template string) | `{ "id": "{{device_id}}" }` |
   | `lat_path`, `lng_path` | Dot paths to JSON values | `data.location.lat` |
   | `speed_path`, `heading_path`, `accuracy_path`, `altitude_path` | Optional metrics |  |
   | `timestamp_path` | Recorded timestamp | `data.timestamp` |
   | `timeout` | cURL timeout in seconds (default 10) | `15` |

---

## 5. API Endpoints

### `GET /api/rig-tracking.php?action=get_location&rig_id=:id[&force_sync=1]`

- When `force_sync=1` and the rig has a third-party provider, the API will fetch live data, persist it, and return the latest coordinates.
- Response payload:
  ```json
  {
    "success": true,
    "location": {
      "id": 1234,
      "latitude": 51.5074,
      "longitude": -0.1278,
      "speed": 32.5,
      "recorded_at": "2025-11-09 16:25:30"
    },
    "provider": {
      "provider": "fleetsmart",
      "status": "active",
      "last_update": "2025-11-09 16:25:31",
      "error_message": null,
      "update_frequency": 300
    },
    "sync_error": null
  }
  ```

### `POST /api/rig-tracking.php`

#### Manual update (`action=update_location`)
- Body fields: `rig_id`, `latitude`, `longitude`, `location_source`, `accuracy`, `speed`, `tracking_provider`, `device_id`, `address`, `notes`, `csrf_token`.
- Persists location, updates `rigs.current_latitude`/`current_longitude`, and refreshes `rig_tracking_config`.

#### Provider sync (`action=sync_third_party`)
- Body fields: `rig_id`, `provider`, `csrf_token`.
- Calls the configured provider using `ThirdPartyTrackingClient`, saves the location, and updates provider metadata. Useful for cron/queue integrations.

---

## 6. UI Behaviour (`modules/rig-tracking.php`)

- **Map rendering** – automatically initialises Google Maps or Leaflet based on `system_config`.
- **Refresh button** – calls `get_location` with `force_sync=1`, updates the marker, coordinates, and provider card without a page reload.
- **History toggle** – toggles up to 20 recent historical markers.
- **Provider status card** – shows provider name, method, device ID, last sync time, configured frequency, and latest error message (if any).
- **Manual update modal** – still available for manual entry or emergency overrides.

---

## 7. Background Sync (Recommended)

Implement a scheduler that periodically calls the sync endpoint for each active rig:

```php
// Example pseudo-cron script
$rigs = $pdo->query("
    SELECT rig_id, tracking_provider
    FROM rig_tracking_config
    WHERE tracking_enabled = 1
      AND tracking_method = 'third_party_api'
      AND status != 'error'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rigs as $rig) {
    $payload = http_build_query([
        'action' => 'sync_third_party',
        'rig_id' => $rig['rig_id'],
        'provider' => $rig['tracking_provider'],
        'csrf_token' => CRON_TOKEN
    ]);
    // POST to /api/rig-tracking.php
}
```

Schedule via cron (example every 5 minutes):
```
*/5 * * * * php /opt/lampp/htdocs/abbis3.2/scripts/sync-rig-locations.php
```

Ensure the cron token is validated within your script to bypass CSRF checking safely.

---

## 8. Error Handling & Monitoring

- Failed sync attempts set `status = 'error'`, store the message in `error_message`, and timestamp in `last_error_at`.
- The map UI surfaces the error text under the provider card.
- Restoring a successful sync clears `error_message` and resets status to `active`.
- Consider adding dashboard alerts for rigs stuck in `error` status beyond a defined SLA.

---

## 9. Troubleshooting Checklist

| Symptom | Likely Cause | Resolution |
| --- | --- | --- |
| “Provider API returned HTTP 401” | Invalid `api_key` / `api_secret` or expired token | Regenerate credentials, verify `auth_method`, headers, and query parameters |
| Refresh button shows “Failed to refresh location” | Sync error or missing config | Check provider card error message, verify `config_payload` JSON |
| Map loads without markers | No location data returned | Confirm provider is sending coordinates; inspect `rig_locations` table |
| Address always empty | Reverse geocode fails | Provide `address` in provider response or configure Google Geocoding |
| Historical markers missing | Only one record in `rig_locations` | Allow multiple updates or lower cron interval |

---

## 10. Extending to New Providers

1. Review provider API docs and note authentication flow, endpoint paths, and JSON schema.
2. Populate `config_payload` with the required headers/queries/body and dot-paths to lat/lng.
3. If the provider requires complex response parsing (arrays, nested objects), adjust paths (e.g. `locations.0.lat`).
4. Test with the Refresh button to validate the response mapping.

---

## 11. Security Considerations

- Store API secrets securely; consider database encryption or integrating with a secrets manager.
- Restrict cron job scripts and API endpoints with token-based authentication when bypassing CSRF.
- Log provider response errors for audit trails but avoid storing raw payloads containing PII unless required.

---

## 12. Related Files

- API controller: `api/rig-tracking.php`
- Third-party client: `includes/RigTracking/ThirdPartyTrackingClient.php`
- UI module: `modules/rig-tracking.php`
- Migration: `database/migrations/20251109_rig_tracking_api_config.sql`

Use this document as the canonical reference when onboarding new rigs or providers into ABBIS.

