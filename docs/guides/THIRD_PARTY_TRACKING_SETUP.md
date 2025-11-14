# Third-Party GPS Tracking Integration Setup

## ✅ API Integration Framework is Ready!

The system has a **complete framework** for integrating with third-party GPS tracking providers. The infrastructure is in place, but you need to implement the specific API calls for your provider.

## Current Status

✅ **Framework Complete:**
- Database tables ready (`rig_tracking_config`)
- API endpoint ready (`api/rig-tracking.php` → `sync_third_party` action)
- Integration function structure ready (`fetchFromThirdPartyAPI()`)
- Example implementations provided

⚠️ **Needs Implementation:**
- Provider-specific API calls (examples provided)
- API credentials configuration
- Automated sync setup (cron job)

## Supported Providers (Examples Provided)

1. **Fleet Complete** - Example code provided
2. **Samsara** - Example code provided
3. **Geotab** - Example code provided
4. **Custom API** - Template provided

## How to Integrate Your Provider

### Step 1: Get Example Code

Open `api/third-party-tracking-examples.php` - this file contains ready-to-use example implementations for:
- Fleet Complete
- Samsara
- Geotab
- Custom API

### Step 2: Copy to Main API File

1. Open `api/third-party-tracking-examples.php`
2. Find the function for your provider (e.g., `fetchFromFleetComplete()`)
3. Copy the function to `api/rig-tracking.php`
4. Update `fetchFromThirdPartyAPI()` to call your function:

```php
function fetchFromThirdPartyAPI($provider, $config) {
    switch (strtolower($provider)) {
        case 'fleet_complete':
            return fetchFromFleetComplete($config);  // Add this line
            
        case 'samsara':
            return fetchFromSamsara($config);  // Add this line
            
        // ... etc
    }
}
```

### Step 3: Configure Rig Tracking

Add tracking configuration to database:

```sql
INSERT INTO rig_tracking_config (
    rig_id, 
    tracking_enabled, 
    tracking_method, 
    tracking_provider, 
    device_id, 
    api_key, 
    api_secret,
    update_frequency
) VALUES (
    1,                          -- rig_id
    1,                          -- tracking_enabled (1 = yes)
    'third_party_api',          -- tracking_method
    'fleet_complete',           -- tracking_provider name
    'DEVICE123',                -- device_id from provider
    'your_api_key_here',        -- API key
    'your_api_secret_here',     -- API secret (if needed)
    300                         -- update_frequency (seconds)
);
```

### Step 4: Test Integration

Test the integration manually:

```bash
curl -X POST http://localhost/api/rig-tracking.php \
  -d "action=sync_third_party" \
  -d "rig_id=1" \
  -d "provider=fleet_complete" \
  -d "csrf_token=YOUR_TOKEN"
```

### Step 5: Set Up Automated Sync (Optional)

Create `api/sync-rig-locations.php`:

```php
<?php
require_once '../config/app.php';
require_once '../includes/auth.php';

$pdo = getDBConnection();

// Get all rigs with third-party tracking enabled
$rigs = $pdo->query("
    SELECT r.id, rtc.* 
    FROM rigs r
    INNER JOIN rig_tracking_config rtc ON r.id = rtc.rig_id
    WHERE rtc.tracking_enabled = 1 
    AND rtc.tracking_method = 'third_party_api'
    AND r.status = 'active'
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rigs as $rig) {
    // Call sync endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/api/rig-tracking.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'sync_third_party',
        'rig_id' => $rig['rig_id'],
        'provider' => $rig['tracking_provider'],
        'csrf_token' => 'CRON_SECURE_TOKEN' // Use a secure token
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    // Log response
    error_log("Synced rig {$rig['rig_id']}: {$response}");
}
```

Add to crontab (every 5 minutes):
```bash
*/5 * * * * php /opt/lampp/htdocs/abbis3.2/api/sync-rig-locations.php
```

## Files Reference

- **Main API**: `api/rig-tracking.php` - Contains `fetchFromThirdPartyAPI()` function
- **Examples**: `api/third-party-tracking-examples.php` - Ready-to-use code for popular providers
- **Database**: `rig_tracking_config` table stores provider credentials

## API Response Format

Your integration function must return:

```php
[
    'latitude' => 5.603717,      // Required: float
    'longitude' => -0.186964,     // Required: float
    'accuracy' => 10.5,           // Optional: meters
    'speed' => 45.2,              // Optional: km/h
    'heading' => 180.0,           // Optional: degrees (0-360)
    'altitude' => 50.0            // Optional: meters
]
```

## Security Notes

⚠️ **Important:**
- Store API keys securely (consider encryption)
- Use HTTPS for all API calls
- Implement rate limiting
- Validate all API responses
- Handle API errors gracefully
- Log API failures for debugging

## Troubleshooting

**Error: "API credentials not configured"**
- Check `rig_tracking_config` table has `api_key` and `device_id`

**Error: "Unknown provider"**
- Check `tracking_provider` field matches your switch case

**Error: "Integration not yet implemented"**
- Copy example code from `api/third-party-tracking-examples.php`
- Update `fetchFromThirdPartyAPI()` function

**Locations not updating:**
- Check API credentials are correct
- Verify device_id matches your provider's device ID
- Check API response format matches expected structure
- Review error logs in `rig_tracking_config.error_message`

## Next Steps

1. ✅ Review `api/third-party-tracking-examples.php`
2. ✅ Choose your provider
3. ✅ Copy example code to `api/rig-tracking.php`
4. ✅ Configure credentials in database
5. ✅ Test integration
6. ✅ Set up automated sync (optional)

---

**Status**: Framework ready, provider implementation needed
**Documentation**: See `api/third-party-tracking-examples.php` for code examples

