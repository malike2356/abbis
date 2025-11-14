<?php
/**
 * Rig Tracking API
 * Handles location updates and retrieval for rig tracking
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/RigTracking/ThirdPartyTrackingClient.php';

$auth->requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDBConnection();
    
    switch ($method) {
        case 'GET':
            handleGet($pdo, $action);
            break;
        case 'POST':
            handlePost($pdo, $action);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function handleGet($pdo, $action) {
    switch ($action) {
        case 'get_location':
            $rigId = intval($_GET['rig_id'] ?? 0);
            if (!$rigId) {
                throw new Exception('Rig ID is required');
            }

            $forceSync = filter_var($_GET['force_sync'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $syncError = null;
            $config = getRigTrackingConfig($pdo, $rigId);

            if (
                $forceSync &&
                $config &&
                $config['tracking_enabled'] &&
                $config['tracking_method'] === 'third_party_api' &&
                !empty($config['tracking_provider'])
            ) {
                try {
                    syncThirdPartyProvider($pdo, $rigId, $config['tracking_provider'], $config);
                    // Reload config to ensure freshness (status, last_update, etc.)
                    $config = getRigTrackingConfig($pdo, $rigId);
                } catch (Exception $e) {
                    $syncError = $e->getMessage();
                }
            }

            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        latitude,
                        longitude,
                        accuracy,
                        speed,
                        heading,
                        altitude,
                        location_source,
                        tracking_provider,
                        device_id,
                        address,
                        notes,
                        recorded_at
                    FROM rig_locations
                    WHERE rig_id = ?
                    ORDER BY recorded_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$rigId]);
                $location = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($location) {
                    echo json_encode([
                        'success' => true,
                        'location' => $location,
                        'provider' => $config ? buildProviderMetadata($config) : null,
                        'sync_error' => $syncError,
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'No location data found for this rig',
                        'provider' => $config ? buildProviderMetadata($config) : null,
                        'sync_error' => $syncError,
                    ]);
                }
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tracking system not initialized. Please run the migration first.',
                    'sync_error' => $syncError,
                    'provider' => $config ? buildProviderMetadata($config) : null,
                ]);
            }
            break;
            
        case 'get_history':
            $rigId = intval($_GET['rig_id'] ?? 0);
            $limit = intval($_GET['limit'] ?? 100);
            
            if (!$rigId) {
                throw new Exception('Rig ID is required');
            }
            
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        id,
                        latitude,
                        longitude,
                        accuracy,
                        speed,
                        heading,
                        altitude,
                        location_source,
                        address,
                        notes,
                        recorded_at
                    FROM rig_locations
                    WHERE rig_id = ?
                    ORDER BY recorded_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$rigId, $limit]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'history' => $history
                ]);
            } catch (PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Tracking system not initialized'
                ]);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function handlePost($pdo, $action) {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        throw new Exception('Invalid security token');
    }
    
    switch ($action) {
        case 'update_location':
            $rigId = intval($_POST['rig_id'] ?? 0);
            $latitude = floatval($_POST['latitude'] ?? 0);
            $longitude = floatval($_POST['longitude'] ?? 0);
            $locationSource = $_POST['location_source'] ?? 'manual';
            $accuracy = !empty($_POST['accuracy']) ? floatval($_POST['accuracy']) : null;
            $speed = !empty($_POST['speed']) ? floatval($_POST['speed']) : null;
            $heading = !empty($_POST['heading']) ? floatval($_POST['heading']) : null;
            $altitude = !empty($_POST['altitude']) ? floatval($_POST['altitude']) : null;
            $trackingProvider = trim($_POST['tracking_provider'] ?? '');
            $deviceId = trim($_POST['device_id'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
            if (!$rigId) {
                throw new Exception('Rig ID is required');
            }
            
            if (!$latitude || !$longitude) {
                throw new Exception('Latitude and longitude are required');
            }
            
            // Validate coordinates
            if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
                throw new Exception('Invalid coordinates');
            }
            
            // Reverse geocode if address not provided
            if (empty($address)) {
                $address = reverseGeocode($latitude, $longitude);
            }
            
            $result = saveRigLocation($pdo, [
                'rig_id' => $rigId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'speed' => $speed,
                'heading' => $heading,
                'altitude' => $altitude,
                'location_source' => $locationSource,
                'tracking_provider' => $trackingProvider ?: null,
                'device_id' => $deviceId ?: null,
                'address' => $address ?: null,
                'notes' => $notes ?: null,
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Location updated successfully',
                'location_id' => $result['location_id'] ?? null,
                'recorded_at' => $result['recorded_at'] ?? null,
            ]);
            break;
            
        case 'sync_third_party':
            // Sync locations from third-party tracking API
            $rigId = intval($_POST['rig_id'] ?? 0);
            $provider = trim($_POST['provider'] ?? '');
            
            if (!$rigId || !$provider) {
                throw new Exception('Rig ID and provider are required');
            }
            
            $config = getRigTrackingConfig($pdo, $rigId, $provider);
            if (!$config) {
                throw new Exception('Tracking configuration not found for this rig and provider');
            }

            if (!$config['tracking_enabled']) {
                throw new Exception('Tracking is disabled for this rig.');
            }

            $location = syncThirdPartyProvider($pdo, $rigId, $provider, $config);

            echo json_encode([
                'success' => true,
                'message' => 'Location synced successfully',
                'location' => $location,
                'provider' => buildProviderMetadata(getRigTrackingConfig($pdo, $rigId, $provider)),
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
}

function reverseGeocode($latitude, $longitude) {
    // Try to get address from coordinates
    // This is a simple implementation - you can enhance with Google Geocoding API or similar
    try {
        $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat={$latitude}&lon={$longitude}&zoom=18&addressdetails=1";
        $context = stream_context_create([
            'http' => [
                'user_agent' => 'ABBIS Rig Tracking System',
                'timeout' => 5
            ]
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['display_name'])) {
                return $data['display_name'];
            }
        }
    } catch (Exception $e) {
        // Silently fail - address is optional
    }
    return '';
}

function fetchFromThirdPartyAPI($provider, $config) {
    if (!isset($config['tracking_provider']) || !$config['tracking_provider']) {
        $config['tracking_provider'] = $provider;
    }

    return ThirdPartyTrackingClient::fetchLatestLocation($config);
}

function saveRigLocation($pdo, array $data): array {
    $rigId = intval($data['rig_id'] ?? 0);
    $latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;

    if (!$rigId || $latitude === null || $longitude === null) {
        throw new Exception('Rig ID, latitude, and longitude are required to save a location.');
    }

    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid coordinates provided.');
    }

    $recordedAt = null;
    if (!empty($data['recorded_at'])) {
        try {
            $recordedAt = (new DateTime($data['recorded_at']))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $recordedAt = date('Y-m-d H:i:s');
        }
    } else {
        $recordedAt = date('Y-m-d H:i:s');
    }

    $locationSource = $data['location_source'] ?? 'manual';
    $trackingProvider = $data['tracking_provider'] ?? null;
    $deviceId = $data['device_id'] ?? null;
    $address = $data['address'] ?? null;
    $notes = $data['notes'] ?? null;

    if (empty($address)) {
        $address = reverseGeocode($latitude, $longitude) ?: null;
    }

    $accuracy = isset($data['accuracy']) ? floatval($data['accuracy']) : null;
    $speed = isset($data['speed']) ? floatval($data['speed']) : null;
    $heading = isset($data['heading']) ? floatval($data['heading']) : null;
    $altitude = isset($data['altitude']) ? floatval($data['altitude']) : null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO rig_locations (
                rig_id, latitude, longitude, accuracy, speed, heading, altitude,
                location_source, tracking_provider, device_id, address, notes, recorded_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $rigId,
            $latitude,
            $longitude,
            $accuracy,
            $speed,
            $heading,
            $altitude,
            $locationSource,
            $trackingProvider ?: null,
            $deviceId ?: null,
            $address,
            $notes,
            $recordedAt
        ]);

        $updateStmt = $pdo->prepare("
            UPDATE rigs 
            SET 
                current_latitude = ?,
                current_longitude = ?,
                current_location_updated_at = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$latitude, $longitude, $recordedAt, $rigId]);

        $trackingMethod = $locationSource === 'third_party_api'
            ? 'third_party_api'
            : ($locationSource === 'gps_device' ? 'gps_device' : 'manual');

        $configStmt = $pdo->prepare("
            INSERT INTO rig_tracking_config (
                rig_id, tracking_enabled, tracking_method, tracking_provider, device_id,
                last_update, last_latitude, last_longitude, status, error_message, last_error_at
            ) VALUES (?, 1, ?, ?, ?, ?, ?, ?, 'active', NULL, NULL)
            ON DUPLICATE KEY UPDATE
                tracking_enabled = VALUES(tracking_enabled),
                tracking_method = VALUES(tracking_method),
                tracking_provider = VALUES(tracking_provider),
                device_id = VALUES(device_id),
                last_update = VALUES(last_update),
                last_latitude = VALUES(last_latitude),
                last_longitude = VALUES(last_longitude),
                status = 'active',
                error_message = NULL,
                last_error_at = NULL
        ");
        $configStmt->execute([
            $rigId,
            $trackingMethod,
            $trackingProvider ?: null,
            $deviceId ?: null,
            $recordedAt,
            $latitude,
            $longitude
        ]);

        $locationId = $pdo->lastInsertId();

        $pdo->commit();

        return [
            'location_id' => $locationId,
            'recorded_at' => $recordedAt,
        ];
    } catch (PDOException $e) {
        $pdo->rollBack();

        if (strpos($e->getMessage(), "doesn't exist") !== false) {
            throw new Exception('Tracking system not initialized. Please run the migration: database/rig_tracking_migration.sql');
        }

        throw $e;
    }
}

function syncThirdPartyProvider($pdo, $rigId, $provider, $config = null): array {
    if (!$config) {
        $config = getRigTrackingConfig($pdo, $rigId, $provider);
    }

    if (!$config) {
        throw new Exception('Tracking configuration not found for this rig.');
    }

    try {
        $location = fetchFromThirdPartyAPI($provider, $config);

        if (!$location || !isset($location['latitude']) || !isset($location['longitude'])) {
            throw new Exception('Provider did not return location coordinates.');
        }

        $result = saveRigLocation($pdo, [
            'rig_id' => $rigId,
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'accuracy' => $location['accuracy'] ?? null,
            'speed' => $location['speed'] ?? null,
            'heading' => $location['heading'] ?? null,
            'altitude' => $location['altitude'] ?? null,
            'location_source' => 'third_party_api',
            'tracking_provider' => $provider,
            'device_id' => $config['device_id'] ?? null,
            'address' => $location['address'] ?? null,
            'recorded_at' => $location['recorded_at'] ?? null,
        ]);

        return array_merge($location, $result);
    } catch (Exception $e) {
        $updateStmt = $pdo->prepare("
            UPDATE rig_tracking_config
            SET status = 'error',
                error_message = ?,
                last_error_at = NOW()
            WHERE rig_id = ? AND LOWER(tracking_provider) = LOWER(?)
        ");
        $updateStmt->execute([
            $e->getMessage(),
            $rigId,
            $provider
        ]);

        throw $e;
    }
}

function getRigTrackingConfig($pdo, $rigId, $provider = null) {
    if (!$rigId) {
        return null;
    }

    if ($provider) {
        $stmt = $pdo->prepare("
            SELECT * FROM rig_tracking_config
            WHERE rig_id = ? AND LOWER(tracking_provider) = LOWER(?)
            LIMIT 1
        ");
        $stmt->execute([$rigId, $provider]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM rig_tracking_config
            WHERE rig_id = ?
            LIMIT 1
        ");
        $stmt->execute([$rigId]);
    }

    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    return $config ?: null;
}

function buildProviderMetadata(?array $config): ?array {
    if (!$config) {
        return null;
    }

    return [
        'provider' => $config['tracking_provider'] ?? null,
        'method' => $config['tracking_method'] ?? null,
        'status' => $config['status'] ?? null,
        'device_id' => $config['device_id'] ?? null,
        'last_update' => $config['last_update'] ?? null,
        'last_latitude' => $config['last_latitude'] ?? null,
        'last_longitude' => $config['last_longitude'] ?? null,
        'last_error_at' => $config['last_error_at'] ?? null,
        'error_message' => $config['error_message'] ?? null,
        'update_frequency' => $config['update_frequency'] ?? null,
    ];
}

