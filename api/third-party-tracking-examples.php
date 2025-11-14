<?php
/**
 * Third-Party Tracking API Integration Examples
 * 
 * This file contains example implementations for integrating with
 * popular GPS tracking providers. Copy the relevant function to
 * api/rig-tracking.php and customize for your provider.
 * 
 * To use:
 * 1. Copy the function for your provider to api/rig-tracking.php
 * 2. Update fetchFromThirdPartyAPI() to call your provider
 * 3. Configure API credentials in rig_tracking_config table
 */

/**
 * Example: Fleet Complete API Integration
 * Documentation: https://www.fleetcomplete.com/api/
 */
function fetchFromFleetComplete($config) {
    $apiKey = $config['api_key'];
    $deviceId = $config['device_id'];
    
    // Fleet Complete API endpoint
    $url = "https://api.fleetcomplete.com/v1/devices/{$deviceId}/location";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Fleet Complete API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['latitude']) || !isset($data['longitude'])) {
        throw new Exception("Invalid response from Fleet Complete API");
    }
    
    return [
        'latitude' => floatval($data['latitude']),
        'longitude' => floatval($data['longitude']),
        'accuracy' => isset($data['accuracy']) ? floatval($data['accuracy']) : null,
        'speed' => isset($data['speed']) ? floatval($data['speed']) : null,
        'heading' => isset($data['heading']) ? floatval($data['heading']) : null,
        'altitude' => isset($data['altitude']) ? floatval($data['altitude']) : null
    ];
}

/**
 * Example: Samsara API Integration
 * Documentation: https://developers.samsara.com/
 */
function fetchFromSamsara($config) {
    $apiKey = $config['api_key'];
    $deviceId = $config['device_id'];
    
    // Samsara API endpoint
    $url = "https://api.samsara.com/v1/fleet/vehicles/{$deviceId}/locations";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Samsara API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    
    // Samsara returns locations array, get the latest
    if (!isset($data['data']) || empty($data['data'])) {
        throw new Exception("No location data from Samsara");
    }
    
    $location = $data['data'][0];
    
    return [
        'latitude' => floatval($location['latitude']),
        'longitude' => floatval($location['longitude']),
        'accuracy' => isset($location['accuracy']) ? floatval($location['accuracy']) : null,
        'speed' => isset($location['speedMilesPerHour']) ? floatval($location['speedMilesPerHour']) * 1.60934 : null, // Convert to km/h
        'heading' => isset($location['heading']) ? floatval($location['heading']) : null,
        'altitude' => isset($location['altitude']) ? floatval($location['altitude']) : null
    ];
}

/**
 * Example: Geotab API Integration
 * Documentation: https://developer.geotab.com/
 */
function fetchFromGeotab($config) {
    $username = $config['api_key']; // Geotab uses username
    $password = $config['api_secret']; // Geotab uses password
    $database = $config['device_id']; // Geotab uses database name
    $deviceSerial = $config['device_id']; // Device serial number
    
    // Geotab requires authentication first
    $authUrl = "https://my.geotab.com/apiv1/Authenticate";
    $authData = [
        'userName' => $username,
        'password' => $password,
        'database' => $database
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $authUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($authData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $authResponse = curl_exec($ch);
    $authData = json_decode($authResponse, true);
    curl_close($ch);
    
    if (!isset($authData['result']['credentials']['sessionId'])) {
        throw new Exception("Geotab authentication failed");
    }
    
    $sessionId = $authData['result']['credentials']['sessionId'];
    
    // Get device location
    $url = "https://my.geotab.com/apiv1/Get";
    $requestData = [
        'typeName' => 'StatusData',
        'credentials' => [
            'userName' => $username,
            'sessionId' => $sessionId,
            'database' => $database
        ],
        'search' => [
            'deviceSearch' => [
                'id' => $deviceSerial
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Geotab API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    
    if (!isset($data['result']) || empty($data['result'])) {
        throw new Exception("No location data from Geotab");
    }
    
    $location = $data['result'][0];
    
    return [
        'latitude' => floatval($location['latitude']),
        'longitude' => floatval($location['longitude']),
        'accuracy' => isset($location['accuracy']) ? floatval($location['accuracy']) : null,
        'speed' => isset($location['speed']) ? floatval($location['speed']) : null,
        'heading' => isset($location['bearing']) ? floatval($location['bearing']) : null,
        'altitude' => isset($location['altitude']) ? floatval($location['altitude']) : null
    ];
}

/**
 * Example: Custom GPS Device via HTTP API
 * For custom GPS devices that provide REST API
 */
function fetchFromCustomAPI($config) {
    $apiUrl = $config['api_key']; // Store API URL in api_key field
    $deviceId = $config['device_id'];
    $apiSecret = $config['api_secret']; // API token/secret
    
    $url = rtrim($apiUrl, '/') . "/devices/{$deviceId}/location";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiSecret,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Custom API error: HTTP {$httpCode}");
    }
    
    $data = json_decode($response, true);
    
    // Adjust these based on your API response structure
    return [
        'latitude' => floatval($data['lat'] ?? $data['latitude'] ?? 0),
        'longitude' => floatval($data['lng'] ?? $data['longitude'] ?? 0),
        'accuracy' => isset($data['accuracy']) ? floatval($data['accuracy']) : null,
        'speed' => isset($data['speed']) ? floatval($data['speed']) : null,
        'heading' => isset($data['heading'] ?? $data['bearing']) ? floatval($data['heading'] ?? $data['bearing']) : null,
        'altitude' => isset($data['altitude']) ? floatval($data['altitude']) : null
    ];
}

/**
 * HOW TO INTEGRATE:
 * 
 * 1. Copy the function for your provider to api/rig-tracking.php
 * 
 * 2. Update fetchFromThirdPartyAPI() in api/rig-tracking.php:
 * 
 * function fetchFromThirdPartyAPI($provider, $config) {
 *     switch ($provider) {
 *         case 'fleet_complete':
 *             return fetchFromFleetComplete($config);
 *             
 *         case 'samsara':
 *             return fetchFromSamsara($config);
 *             
 *         case 'geotab':
 *             return fetchFromGeotab($config);
 *             
 *         case 'custom':
 *             return fetchFromCustomAPI($config);
 *             
 *         default:
 *             throw new Exception("Unknown provider: {$provider}");
 *     }
 * }
 * 
 * 3. Configure rig tracking in database:
 * 
 * INSERT INTO rig_tracking_config (
 *     rig_id, 
 *     tracking_enabled, 
 *     tracking_method, 
 *     tracking_provider, 
 *     device_id, 
 *     api_key, 
 *     api_secret
 * ) VALUES (
 *     1,  -- rig_id
 *     1,  -- tracking_enabled
 *     'third_party_api',  -- tracking_method
 *     'fleet_complete',  -- tracking_provider
 *     'DEVICE123',  -- device_id
 *     'your_api_key_here',  -- api_key
 *     'your_api_secret_here'  -- api_secret (if needed)
 * );
 * 
 * 4. Set up automated sync (cron job):
 * 
 * Create: api/sync-rig-locations.php
 * 
 * <?php
 * require_once '../config/app.php';
 * 
 * $pdo = getDBConnection();
 * 
 * $rigs = $pdo->query("
 *     SELECT r.id, rtc.* 
 *     FROM rigs r
 *     INNER JOIN rig_tracking_config rtc ON r.id = rtc.rig_id
 *     WHERE rtc.tracking_enabled = 1 
 *     AND rtc.tracking_method = 'third_party_api'
 *     AND r.status = 'active'
 * ")->fetchAll(PDO::FETCH_ASSOC);
 * 
 * foreach ($rigs as $rig) {
 *     $ch = curl_init();
 *     curl_setopt($ch, CURLOPT_URL, "http://localhost/api/rig-tracking.php");
 *     curl_setopt($ch, CURLOPT_POST, 1);
 *     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
 *         'action' => 'sync_third_party',
 *         'rig_id' => $rig['rig_id'],
 *         'provider' => $rig['tracking_provider'],
 *         'csrf_token' => 'CRON_TOKEN' // Use secure token
 *     ]));
 *     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 *     curl_exec($ch);
 *     curl_close($ch);
 * }
 * 
 * Add to crontab (every 5 minutes):
 * */5 * * * * php /path/to/abbis3.2/api/sync-rig-locations.php
 */

