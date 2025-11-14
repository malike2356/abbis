# Rig Location Tracking System

## Overview

The Rig Location Tracking system allows you to monitor and track the real-time location of your drilling rigs. The system supports both manual location updates and integration with third-party GPS tracking services.

## Features

- **Real-time Location Tracking**: View rig locations on an interactive map
- **Location History**: Track movement history over time
- **Multiple Update Methods**: Manual entry, GPS devices, or third-party APIs
- **Map Integration**: Supports Google Maps and Leaflet (OpenStreetMap)
- **Directions**: Get directions to rig locations
- **Location Details**: View coordinates, accuracy, speed, heading, and address

## Setup

### 1. Database Migration

First, run the database migration to create the necessary tables:

```bash
# Option 1: Using the migration runner
php database/run_migration.php database/rig_tracking_migration.sql

# Option 2: Direct SQL import
mysql -u your_username -p abbis_3_2 < database/rig_tracking_migration.sql
```

This creates:
- `rig_locations`: Stores location history
- `rig_tracking_config`: Stores tracking configuration per rig
- Adds location fields to `rigs` table

### 2. Map Provider Configuration

Configure your map provider in **System → Integrations → Map Providers**:

- **Google Maps**: Requires an API key with Maps JavaScript API and Geocoding API enabled
- **Leaflet (OpenStreetMap)**: Free, no API key required

### 3. Accessing Rig Tracking

- Navigate to **System → Configuration → Rigs Management**
- Click the **📍 Track** button next to any rig
- Or go directly to **System → Rig Location Tracking**

## Usage

### Manual Location Update

1. Select a rig from the list
2. Click **📍 Update Location**
3. Enter coordinates manually or use your device's GPS
4. Optionally add accuracy, speed, heading, and notes
5. Click **Update Location**

### Location Sources

- **Manual Entry**: Enter coordinates manually
- **From Field Report**: Link location from a field report
- **GPS Device**: Direct GPS device input with full telemetry
- **Third-Party API**: Automated updates from tracking providers

### Viewing Locations

- **Current Location**: Shows the most recent location on the map
- **History**: Toggle to view location history (up to 100 recent points)
- **Directions**: Click **🧭 Directions** to get Google Maps directions
- **Refresh**: Click **🔄 Refresh** to update location data

## Third-Party API Integration

### Supported Providers

The system is designed to work with:
- Fleet Complete
- Samsara
- Geotab
- Custom GPS tracking services

### Setting Up Third-Party Tracking

1. **Configure Tracking Provider**:
   - Go to **System → Configuration → Rigs Management**
   - Edit a rig and configure tracking settings
   - Enter provider name, device ID, and API credentials

2. **API Integration**:
   - Edit `api/rig-tracking.php`
   - Implement the `fetchFromThirdPartyAPI()` function for your provider
   - Example structure:
   ```php
   function fetchFromThirdPartyAPI($provider, $config) {
       switch ($provider) {
           case 'fleet_complete':
               // Call Fleet Complete API
               $apiKey = $config['api_key'];
               $deviceId = $config['device_id'];
               // Make API call and return location data
               return [
                   'latitude' => $lat,
                   'longitude' => $lng,
                   'accuracy' => $accuracy,
                   'speed' => $speed,
                   'heading' => $heading
               ];
           // Add more providers...
       }
   }
   ```

3. **Automated Updates**:
   - Set up a cron job to periodically sync locations:
   ```bash
   # Run every 5 minutes
   */5 * * * * php /path/to/abbis3.2/api/sync-rig-locations.php
   ```

### Creating a Sync Script

Create `api/sync-rig-locations.php`:

```php
<?php
require_once '../config/app.php';
require_once '../includes/auth.php';

$pdo = getDBConnection();

// Get all rigs with third-party tracking enabled
$stmt = $pdo->query("
    SELECT r.id, rtc.* 
    FROM rigs r
    INNER JOIN rig_tracking_config rtc ON r.id = rtc.rig_id
    WHERE rtc.tracking_enabled = 1 
    AND rtc.tracking_method = 'third_party_api'
    AND r.status = 'active'
");

$rigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rigs as $rig) {
    // Call API to sync location
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://localhost/api/rig-tracking.php");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'action' => 'sync_third_party',
        'rig_id' => $rig['rig_id'],
        'provider' => $rig['tracking_provider'],
        'csrf_token' => 'cron_job_token' // Use a secure token for cron jobs
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
```

## API Endpoints

### Get Current Location
```
GET /api/rig-tracking.php?action=get_location&rig_id={rig_id}
```

### Get Location History
```
GET /api/rig-tracking.php?action=get_history&rig_id={rig_id}&limit=100
```

### Update Location
```
POST /api/rig-tracking.php?action=update_location
Parameters:
- rig_id (required)
- latitude (required)
- longitude (required)
- location_source (manual|gps_device|third_party_api|field_report)
- accuracy, speed, heading, altitude (optional)
- tracking_provider, device_id (optional, for third-party)
- address, notes (optional)
```

### Sync Third-Party Location
```
POST /api/rig-tracking.php?action=sync_third_party
Parameters:
- rig_id (required)
- provider (required)
```

## Database Schema

### rig_locations
- `id`: Primary key
- `rig_id`: Foreign key to rigs
- `latitude`, `longitude`: Coordinates
- `accuracy`, `speed`, `heading`, `altitude`: GPS telemetry
- `location_source`: How location was obtained
- `tracking_provider`, `device_id`: Third-party tracking info
- `address`: Reverse geocoded address
- `notes`: Additional notes
- `recorded_at`: Timestamp of location

### rig_tracking_config
- `rig_id`: Foreign key to rigs (unique)
- `tracking_enabled`: Whether tracking is active
- `tracking_method`: manual|gps_device|third_party_api
- `tracking_provider`: Provider name
- `device_id`: GPS device or vehicle ID
- `api_key`, `api_secret`: API credentials (encrypted)
- `update_frequency`: Update interval in seconds
- `last_update`: Last successful update timestamp
- `status`: active|inactive|error

## Best Practices

1. **Regular Updates**: Update locations regularly for accurate tracking
2. **GPS Accuracy**: Use GPS devices for better accuracy than manual entry
3. **Privacy**: Be mindful of location privacy and data protection
4. **API Rate Limits**: Respect third-party API rate limits
5. **Error Handling**: Monitor tracking status and handle errors gracefully
6. **Data Retention**: Consider archiving old location data periodically

## Troubleshooting

### Map Not Loading
- Check map provider configuration
- Verify API key (for Google Maps)
- Check browser console for errors

### Location Not Updating
- Verify database migration completed successfully
- Check tracking configuration for the rig
- Verify API credentials (for third-party)
- Check error messages in `rig_tracking_config.error_message`

### Coordinates Invalid
- Ensure latitude is between -90 and 90
- Ensure longitude is between -180 and 180
- Check coordinate format (decimal degrees)

## Security Considerations

- API keys and secrets should be encrypted
- Use HTTPS for all API communications
- Implement rate limiting for API endpoints
- Validate all input data
- Use CSRF tokens for all POST requests

## Future Enhancements

- Real-time WebSocket updates
- Geofencing alerts
- Route optimization
- Historical route playback
- Mobile app integration
- Offline location tracking

