<?php
/**
 * Rig Location Tracking
 * Track and view rig locations on a map
 */
$page_title = 'Rig Location Tracking';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();

// Get selected rig
$selectedRigId = intval($_GET['rig_id'] ?? 0);
$viewMode = $_GET['view'] ?? 'current'; // 'current' or 'history'

// Get all active rigs
try {
    $rigs = $pdo->query("
        SELECT 
            r.id,
            r.rig_name,
            r.rig_code,
            r.status,
            r.current_latitude,
            r.current_longitude,
            r.current_location_updated_at,
            r.tracking_enabled,
            rtc.tracking_method,
            rtc.tracking_provider,
            rtc.device_id,
            rtc.status as tracking_status
        FROM rigs r
        LEFT JOIN rig_tracking_config rtc ON r.id = rtc.rig_id
        WHERE r.status = 'active'
        ORDER BY r.rig_name
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Tables might not exist yet
    $rigs = $pdo->query("
        SELECT 
            id,
            rig_name,
            rig_code,
            status,
            NULL as current_latitude,
            NULL as current_longitude,
            NULL as current_location_updated_at,
            0 as tracking_enabled
        FROM rigs
        WHERE status = 'active'
        ORDER BY rig_name
    ")->fetchAll(PDO::FETCH_ASSOC);
}

// Get selected rig details
$selectedRig = null;
$rigLocations = [];
$locationHistory = [];
$selectedRigConfig = null;

if ($selectedRigId > 0) {
    foreach ($rigs as $rig) {
        if ($rig['id'] == $selectedRigId) {
            $selectedRig = $rig;
            break;
        }
    }
    if ($selectedRig) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM rig_tracking_config WHERE rig_id = ?");
            $stmt->execute([$selectedRigId]);
            $selectedRigConfig = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            $selectedRigConfig = null;
        }
        
        // Get location history
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
                    address,
                    notes,
                    recorded_at
                FROM rig_locations
                WHERE rig_id = ?
                ORDER BY recorded_at DESC
                LIMIT 100
            ");
            $stmt->execute([$selectedRigId]);
            $locationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Current location is the most recent
            if (!empty($locationHistory)) {
                $selectedRig['current_latitude'] = $locationHistory[0]['latitude'];
                $selectedRig['current_longitude'] = $locationHistory[0]['longitude'];
                $selectedRig['current_location_updated_at'] = $locationHistory[0]['recorded_at'];
            }
        } catch (PDOException $e) {
            // Table might not exist yet
            $locationHistory = [];
        }
    }
}

// Get map provider settings
$mapProvider = 'google';
$mapApiKey = '';
try {
    $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'map_provider'");
    $result = $stmt->fetch();
    if ($result) {
        $mapProvider = $result['config_value'] ?? 'google';
    }
    
    $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'map_api_key'");
    $result = $stmt->fetch();
    if ($result) {
        $mapApiKey = $result['config_value'] ?? '';
    }
} catch (PDOException $e) {
    // Use defaults
}

require_once '../includes/header.php';
?>

<style>
    .tracking-container {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        height: calc(100vh - 200px);
        min-height: 600px;
    }
    
    @media (max-width: 1024px) {
        .tracking-container {
            grid-template-columns: 1fr;
            height: auto;
        }
    }
    
    .rig-selector-panel {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 20px;
        overflow-y: auto;
    }
    
    .rig-item {
        padding: 12px;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .rig-item:hover {
        background: var(--bg);
        border-color: var(--primary);
    }
    
    .rig-item.selected {
        background: color-mix(in srgb, var(--primary) 10%, transparent);
        border-color: var(--primary);
    }
    
    .rig-item h4 {
        margin: 0 0 4px 0;
        color: var(--text);
    }
    
    .rig-item .rig-code {
        font-size: 12px;
        color: var(--secondary);
        margin-bottom: 8px;
    }
    
    .rig-item .rig-status {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 12px;
    }
    
    .rig-item .tracking-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
        margin-top: 6px;
    }
    
    .tracking-badge.enabled {
        background: color-mix(in srgb, var(--success) 20%, transparent);
        color: var(--success);
    }
    
    .tracking-badge.disabled {
        background: color-mix(in srgb, var(--secondary) 20%, transparent);
        color: var(--secondary);
    }
    
    .map-container {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        position: relative;
        overflow: hidden;
    }
    
    #rigTrackingMap {
        width: 100%;
        height: 100%;
        min-height: 600px;
    }
    
    .map-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .map-control-btn {
        background: white;
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 8px 12px;
        cursor: pointer;
        font-size: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        transition: all 0.2s;
    }
    
    .map-control-btn:hover {
        background: var(--bg);
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .location-info {
        position: absolute;
        bottom: 10px;
        left: 10px;
        right: 10px;
        background: white;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 16px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        max-height: 200px;
        overflow-y: auto;
    }
    
    .location-info h4 {
        margin: 0 0 12px 0;
        color: var(--primary);
    }
    
    .location-details {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
        font-size: 13px;
    }
    
    .location-detail-item {
        display: flex;
        justify-content: space-between;
    }
    
    .location-detail-label {
        color: var(--secondary);
        font-weight: 500;
    }
    
    .location-detail-value {
        color: var(--text);
        font-weight: 600;
    }
    
    .provider-status {
        margin-top: 16px;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        display: none;
        font-size: 12px;
        gap: 8px;
    }
    
    .provider-status.active {
        display: grid;
    }
    
    .provider-status-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .provider-status-label {
        color: var(--secondary);
        font-weight: 500;
    }
    
    .provider-status-value {
        color: var(--text);
        font-weight: 600;
    }
    
    .provider-status-error {
        color: var(--danger, #d9534f);
        font-weight: 500;
        font-size: 12px;
        margin-top: 4px;
    }
    
    .no-rig-selected {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        color: var(--secondary);
        font-size: 16px;
        text-align: center;
        padding: 40px;
    }
    
    .update-location-btn {
        margin-top: 12px;
        width: 100%;
    }
</style>

<div class="container-fluid">
    <div class="page-header">
        <div>
            <h1>📍 Rig Location Tracking</h1>
            <p>Track and monitor the real-time location of your drilling rigs</p>
        </div>
        <div>
            <a href="config.php" class="btn btn-outline">← Back to Configuration</a>
        </div>
    </div>
    
    <div class="tracking-container">
        <!-- Rig Selector Panel -->
        <div class="rig-selector-panel">
            <h3 style="margin-top: 0; margin-bottom: 16px;">Select Rig to Track</h3>
            
            <?php if (empty($rigs)): ?>
                <div style="text-align: center; padding: 40px; color: var(--secondary);">
                    <p>No active rigs found.</p>
                    <a href="config.php" class="btn btn-primary">Add Rigs</a>
                </div>
            <?php else: ?>
                <?php foreach ($rigs as $rig): ?>
                    <div class="rig-item <?php echo ($selectedRigId == $rig['id']) ? 'selected' : ''; ?>" 
                         onclick="selectRig(<?php echo $rig['id']; ?>)">
                        <h4><?php echo e($rig['rig_name']); ?></h4>
                        <div class="rig-code"><?php echo e($rig['rig_code']); ?></div>
                        <div class="rig-status">
                            <span style="color: <?php echo $rig['status'] === 'active' ? 'var(--success)' : 'var(--secondary)'; ?>;">
                                ●
                            </span>
                            <?php echo ucfirst($rig['status']); ?>
                        </div>
                        <?php if ($rig['tracking_enabled'] || (isset($rig['tracking_method']) && $rig['tracking_method'] !== 'manual')): ?>
                            <span class="tracking-badge enabled">📍 Tracking Enabled</span>
                        <?php else: ?>
                            <span class="tracking-badge disabled">Tracking Disabled</span>
                        <?php endif; ?>
                        <?php if ($rig['current_latitude'] && $rig['current_longitude']): ?>
                            <div style="margin-top: 8px; font-size: 11px; color: var(--success);">
                                ✓ Location available
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 8px; font-size: 11px; color: var(--secondary);">
                                No location data
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <!-- Map Container -->
        <div class="map-container">
            <?php if ($selectedRig && $selectedRig['current_latitude'] && $selectedRig['current_longitude']): ?>
                <div id="rigTrackingMap"></div>
                
                <div class="map-controls">
                    <button class="map-control-btn" id="refreshLocationBtn" onclick="refreshLocation()" title="Refresh Location">
                        🔄 Refresh
                    </button>
                    <button class="map-control-btn" onclick="centerOnRig()" title="Center on Rig">
                        🎯 Center
                    </button>
                    <button class="map-control-btn" id="historyToggleButton" onclick="toggleHistory()" title="Toggle History">
                        📜 History
                    </button>
                    <button class="map-control-btn" onclick="getDirections()" title="Get Directions">
                        🧭 Directions
                    </button>
                </div>
                
                <div class="location-info" id="locationInfo">
                    <h4><?php echo e($selectedRig['rig_name']); ?> - Current Location</h4>
                    <div class="location-details">
                        <div class="location-detail-item">
                            <span class="location-detail-label">Coordinates:</span>
                            <span class="location-detail-value" id="coordinates">
                                <?php echo number_format($selectedRig['current_latitude'], 6); ?>, 
                                <?php echo number_format($selectedRig['current_longitude'], 6); ?>
                            </span>
                        </div>
                        <?php if ($selectedRig['current_location_updated_at']): ?>
                            <div class="location-detail-item">
                                <span class="location-detail-label">Last Updated:</span>
                                <span class="location-detail-value" id="lastUpdated">
                                    <?php echo date('Y-m-d H:i:s', strtotime($selectedRig['current_location_updated_at'])); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($locationHistory[0]['accuracy'])): ?>
                            <div class="location-detail-item">
                                <span class="location-detail-label">Accuracy:</span>
                                <span class="location-detail-value" id="accuracyValue">
                                    <?php echo number_format($locationHistory[0]['accuracy'], 1); ?>m
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($locationHistory[0]['speed'])): ?>
                            <div class="location-detail-item">
                                <span class="location-detail-label">Speed:</span>
                                <span class="location-detail-value" id="speedValue">
                                    <?php echo number_format($locationHistory[0]['speed'], 1); ?> km/h
                                </span>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($locationHistory[0]['address'])): ?>
                            <div class="location-detail-item" style="grid-column: 1 / -1;">
                                <span class="location-detail-label">Address:</span>
                                <span class="location-detail-value" id="addressValue">
                                    <?php echo e($locationHistory[0]['address']); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="provider-status <?php echo ($selectedRigConfig && $selectedRigConfig['tracking_provider']) ? 'active' : ''; ?>" id="providerStatusBlock">
                        <div class="provider-status-row">
                            <span class="provider-status-label">Provider:</span>
                            <span class="provider-status-value" id="providerName">
                                <?php echo e($selectedRigConfig['tracking_provider'] ?? ''); ?>
                            </span>
                        </div>
                        <div class="provider-status-row">
                            <span class="provider-status-label">Method:</span>
                            <span class="provider-status-value" id="providerMethod">
                                <?php echo $selectedRigConfig && $selectedRigConfig['tracking_method'] ? ucfirst(str_replace('_', ' ', $selectedRigConfig['tracking_method'])) : ''; ?>
                            </span>
                        </div>
                        <div class="provider-status-row">
                            <span class="provider-status-label">Status:</span>
                            <span class="provider-status-value" id="providerStatus">
                                <?php echo $selectedRigConfig && $selectedRigConfig['status'] ? ucfirst($selectedRigConfig['status']) : ''; ?>
                            </span>
                        </div>
                        <div class="provider-status-row">
                            <span class="provider-status-label">Last Sync:</span>
                            <span class="provider-status-value" id="providerLastUpdate">
                                <?php echo $selectedRigConfig && $selectedRigConfig['last_update'] ? date('Y-m-d H:i:s', strtotime($selectedRigConfig['last_update'])) : ''; ?>
                            </span>
                        </div>
                        <div class="provider-status-row">
                            <span class="provider-status-label">Update Frequency:</span>
                            <span class="provider-status-value" id="providerFrequency">
                                <?php echo $selectedRigConfig && $selectedRigConfig['update_frequency'] ? intval($selectedRigConfig['update_frequency']) . ' s' : ''; ?>
                            </span>
                        </div>
                        <div class="provider-status-row">
                            <span class="provider-status-label">Device ID:</span>
                            <span class="provider-status-value" id="providerDeviceId">
                                <?php echo e($selectedRigConfig['device_id'] ?? ''); ?>
                            </span>
                        </div>
                        <div class="provider-status-error" id="providerErrorMessage" style="<?php echo ($selectedRigConfig && !empty($selectedRigConfig['error_message'])) ? '' : 'display:none;'; ?>">
                            <?php echo e($selectedRigConfig['error_message'] ?? ''); ?>
                        </div>
                    </div>
                    <button class="btn btn-primary update-location-btn" onclick="showUpdateLocationModal()">
                        📍 Update Location
                    </button>
                </div>
            <?php else: ?>
                <div class="no-rig-selected">
                    <div>
                        <div style="font-size: 64px; margin-bottom: 20px;">📍</div>
                        <h3 style="margin-bottom: 12px;">No Rig Selected</h3>
                        <p style="color: var(--secondary); margin-bottom: 20px;">
                            <?php if ($selectedRigId > 0 && $selectedRig): ?>
                                This rig has no location data yet. Click "Update Location" to add the first location.
                            <?php else: ?>
                                Select a rig from the list to view its location on the map.
                            <?php endif; ?>
                        </p>
                        <?php if ($selectedRigId > 0 && $selectedRig): ?>
                            <button class="btn btn-primary" onclick="showUpdateLocationModal()">
                                📍 Add Location
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Update Location Modal -->
<div id="updateLocationModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>Update Rig Location</h2>
            <button class="modal-close" onclick="closeUpdateLocationModal()">&times;</button>
        </div>
        <form id="updateLocationForm" onsubmit="updateLocation(event)">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="rig_id" id="updateRigId" value="<?php echo $selectedRigId; ?>">
            
            <div class="form-group">
                <label class="form-label">Location Source</label>
                <select name="location_source" id="locationSource" class="form-control" onchange="toggleLocationInputs()">
                    <option value="manual">Manual Entry</option>
                    <option value="field_report">From Field Report</option>
                    <option value="gps_device">GPS Device</option>
                    <option value="third_party_api">Third-Party API</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Latitude *</label>
                <input type="number" name="latitude" id="updateLatitude" class="form-control" 
                       step="0.000001" required placeholder="e.g., 5.603717">
            </div>
            
            <div class="form-group">
                <label class="form-label">Longitude *</label>
                <input type="number" name="longitude" id="updateLongitude" class="form-control" 
                       step="0.000001" required placeholder="e.g., -0.186964">
            </div>
            
            <div id="gpsFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Accuracy (meters)</label>
                    <input type="number" name="accuracy" class="form-control" step="0.1" placeholder="GPS accuracy">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Speed (km/h)</label>
                    <input type="number" name="speed" class="form-control" step="0.1" placeholder="Current speed">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Heading (degrees)</label>
                    <input type="number" name="heading" class="form-control" step="0.1" min="0" max="360" placeholder="Direction">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Altitude (meters)</label>
                    <input type="number" name="altitude" class="form-control" step="0.1" placeholder="Altitude">
                </div>
            </div>
            
            <div id="apiFields" style="display: none;">
                <div class="form-group">
                    <label class="form-label">Tracking Provider</label>
                    <input type="text" name="tracking_provider" class="form-control" placeholder="e.g., Fleet Complete, Samsara">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Device ID</label>
                    <input type="text" name="device_id" class="form-control" placeholder="GPS device or vehicle ID">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Address (optional)</label>
                <input type="text" name="address" id="updateAddress" class="form-control" placeholder="Will be auto-filled if coordinates are valid">
            </div>
            
            <div class="form-group">
                <label class="form-label">Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this location"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeUpdateLocationModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Location</button>
            </div>
        </form>
    </div>
</div>

<!-- Load Map Libraries -->
<?php if ($mapProvider === 'google'): ?>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo e($mapApiKey); ?>&libraries=places,geometry"></script>
<?php else: ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php endif; ?>

<script>
let map;
let rigMarker;
let historyMarkers = [];
let showHistory = false;
const selectedRigId = <?php echo $selectedRigId; ?>;
let selectedRig = <?php echo json_encode($selectedRig); ?>;
let locationHistory = <?php echo json_encode($locationHistory); ?>;
let providerMeta = <?php echo json_encode($selectedRigConfig); ?>;
const mapProvider = '<?php echo $mapProvider; ?>';

function initMap() {
    if (!selectedRig || !selectedRig.current_latitude || !selectedRig.current_longitude) {
        return;
    }

    const lat = parseFloat(selectedRig.current_latitude);
    const lng = parseFloat(selectedRig.current_longitude);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return;
    }

    if (mapProvider === 'google') {
        map = new google.maps.Map(document.getElementById('rigTrackingMap'), {
            center: { lat, lng },
            zoom: 15,
            mapTypeControl: true,
            streetViewControl: true,
            fullscreenControl: true
        });

        rigMarker = new google.maps.Marker({
            position: { lat, lng },
            map,
            title: selectedRig.rig_name,
            icon: {
                url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
                scaledSize: new google.maps.Size(40, 40)
            }
        });

        const infoWindow = new google.maps.InfoWindow({
            content: `
                <div style="padding: 8px;">
                    <strong>${escapeHtml(selectedRig.rig_name || '')}</strong><br>
                    ${selectedRig.rig_code || ''}<br>
                    <small>Last updated: ${selectedRig.current_location_updated_at || 'Unknown'}</small>
                </div>
            `
        });

        rigMarker.addListener('click', () => {
            infoWindow.open(map, rigMarker);
        });
    } else {
        map = L.map('rigTrackingMap').setView([lat, lng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        rigMarker = L.marker([lat, lng], {
            title: selectedRig.rig_name
        }).addTo(map);

        rigMarker.bindPopup(`
            <strong>${escapeHtml(selectedRig.rig_name || '')}</strong><br>
            ${selectedRig.rig_code || ''}<br>
            <small>Last updated: ${selectedRig.current_location_updated_at || 'Unknown'}</small>
        `);
    }

    renderHistoryMarkers();

    if (Array.isArray(locationHistory) && locationHistory.length > 0) {
        updateLocationInfoUI(locationHistory[0]);
    }
}

function clearHistoryMarkers() {
    if (!historyMarkers.length) {
        return;
    }

    historyMarkers.forEach(marker => {
        if (!marker) {
            return;
        }
        if (mapProvider === 'google') {
            marker.setMap(null);
        } else if (marker.remove) {
            marker.remove();
        }
    });

    historyMarkers = [];
}

function renderHistoryMarkers() {
    if (!map) {
        return;
    }

    clearHistoryMarkers();

    if (!Array.isArray(locationHistory) || locationHistory.length <= 1) {
        updateHistoryMarkerVisibility();
        return;
    }

    const recentHistory = locationHistory.slice(1, 20);

    recentHistory.forEach((location, index) => {
        if (!location || location.latitude === undefined || location.longitude === undefined) {
            return;
        }

        const lat = parseFloat(location.latitude);
        const lng = parseFloat(location.longitude);

        if (Number.isNaN(lat) || Number.isNaN(lng)) {
            return;
        }

        if (mapProvider === 'google') {
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: showHistory ? map : null,
                title: `Location ${index + 1}`,
                icon: {
                    url: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png',
                    scaledSize: new google.maps.Size(20, 20)
                }
            });
            historyMarkers.push(marker);
        } else {
            const marker = L.marker([lat, lng], {
                title: `Location ${index + 1}`
            });
            if (showHistory && map) {
                marker.addTo(map);
            }
            historyMarkers.push(marker);
        }
    });

    updateHistoryMarkerVisibility();
}

function updateHistoryMarkerVisibility() {
    if (!historyMarkers.length) {
        const historyBtn = document.getElementById('historyToggleButton');
        if (historyBtn) {
            historyBtn.textContent = showHistory ? '📍 Current' : '📜 History';
        }
        return;
    }

    historyMarkers.forEach(marker => {
        if (!marker) {
            return;
        }
        if (mapProvider === 'google') {
            marker.setMap(showHistory ? map : null);
        } else if (marker.addTo) {
            if (showHistory) {
                marker.addTo(map);
            } else if (marker.remove) {
                marker.remove();
            }
        }
    });

    const historyBtn = document.getElementById('historyToggleButton');
    if (historyBtn) {
        historyBtn.textContent = showHistory ? '📍 Current' : '📜 History';
    }
}

function updateMapMarker(location) {
    if (!location) {
        return;
    }

    const lat = parseFloat(location.latitude);
    const lng = parseFloat(location.longitude);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return;
    }

    selectedRig = selectedRig || {};
    selectedRig.current_latitude = lat;
    selectedRig.current_longitude = lng;
    selectedRig.current_location_updated_at = location.recorded_at || selectedRig.current_location_updated_at;

    if (!map) {
        initMap();
        return;
    }

    if (mapProvider === 'google') {
        if (!rigMarker) {
            rigMarker = new google.maps.Marker({
                position: { lat, lng },
                map,
                title: selectedRig.rig_name,
                icon: {
                    url: 'http://maps.google.com/mapfiles/ms/icons/red-dot.png',
                    scaledSize: new google.maps.Size(40, 40)
                }
            });
        } else {
            rigMarker.setPosition({ lat, lng });
        }
    } else {
        if (!rigMarker) {
            rigMarker = L.marker([lat, lng], { title: selectedRig.rig_name }).addTo(map);
        } else {
            rigMarker.setLatLng([lat, lng]);
        }
    }
}

function selectRig(rigId) {
    window.location.href = 'rig-tracking.php?rig_id=' + rigId;
}

function refreshLocation() {
    if (!selectedRigId) {
        return;
    }

    const refreshBtn = document.getElementById('refreshLocationBtn');
    const originalLabel = refreshBtn ? refreshBtn.textContent : null;

    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.dataset.originalLabel = originalLabel || '';
        refreshBtn.textContent = '⏳ Refreshing';
    }

    fetch(`../api/rig-tracking.php?action=get_location&rig_id=${selectedRigId}&force_sync=1`)
        .then(response => response.json())
        .then(data => {
            if (data.provider) {
                providerMeta = data.provider;
            }

            updateProviderInfo(providerMeta, data.sync_error || null);

            if (data.success && data.location) {
                const location = data.location;
                updateMapMarker(location);

                if (!Array.isArray(locationHistory)) {
                    locationHistory = [];
                }
                locationHistory = locationHistory.filter(entry => entry.id !== location.id);
                locationHistory.unshift(location);
                locationHistory = locationHistory.slice(0, 100);

                renderHistoryMarkers();
                updateLocationInfoUI(location);
            } else {
                const errorMessage = data.message || data.sync_error || 'Failed to refresh location';
                alert(errorMessage);
            }
        })
        .catch(error => {
            console.error('Error refreshing location:', error);
            alert('Error refreshing location');
        })
        .finally(() => {
            if (refreshBtn) {
                refreshBtn.disabled = false;
                refreshBtn.textContent = refreshBtn.dataset.originalLabel || '🔄 Refresh';
            }
        });
}

function centerOnRig() {
    if (!map || !selectedRig || !selectedRig.current_latitude || !selectedRig.current_longitude) {
        return;
    }

    const lat = parseFloat(selectedRig.current_latitude);
    const lng = parseFloat(selectedRig.current_longitude);

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return;
    }

    if (mapProvider === 'google') {
        map.setCenter({ lat, lng });
        map.setZoom(15);
    } else {
        map.setView([lat, lng], 15);
    }
}

function toggleHistory() {
    showHistory = !showHistory;

    if (!map) {
        return;
    }

    if (!historyMarkers.length) {
        renderHistoryMarkers();
    } else {
        updateHistoryMarkerVisibility();
    }
}

function getDirections() {
    if (selectedRig && selectedRig.current_latitude && selectedRig.current_longitude) {
        const lat = selectedRig.current_latitude;
        const lng = selectedRig.current_longitude;
        window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
    }
}

function showUpdateLocationModal() {
    document.getElementById('updateLocationModal').style.display = 'flex';

    if (selectedRig && selectedRig.current_latitude && selectedRig.current_longitude) {
        document.getElementById('updateLatitude').value = selectedRig.current_latitude;
        document.getElementById('updateLongitude').value = selectedRig.current_longitude;
    } else if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            position => {
                document.getElementById('updateLatitude').value = position.coords.latitude.toFixed(6);
                document.getElementById('updateLongitude').value = position.coords.longitude.toFixed(6);
                if (position.coords.accuracy) {
                    document.querySelector('[name="accuracy"]').value = position.coords.accuracy.toFixed(1);
                }
            },
            error => {
                console.log('Could not get current location:', error);
            }
        );
    }
}

function closeUpdateLocationModal() {
    document.getElementById('updateLocationModal').style.display = 'none';
    document.getElementById('updateLocationForm').reset();
}

function toggleLocationInputs() {
    const source = document.getElementById('locationSource').value;
    const gpsFields = document.getElementById('gpsFields');
    const apiFields = document.getElementById('apiFields');

    gpsFields.style.display = (source === 'gps_device' || source === 'third_party_api') ? 'block' : 'none';
    apiFields.style.display = (source === 'third_party_api') ? 'block' : 'none';
}

function updateLocation(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.textContent = 'Updating...';
    submitBtn.disabled = true;

    fetch('../api/rig-tracking.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Location updated successfully!');
                closeUpdateLocationModal();
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to update location'));
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error updating location:', error);
            alert('Error updating location: ' + error.message);
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        });
}

function updateLocationInfoUI(location) {
    if (!location) {
        return;
    }

    if (location.latitude !== undefined && location.longitude !== undefined) {
        const coordsEl = document.getElementById('coordinates');
        if (coordsEl) {
            coordsEl.textContent = formatCoordinates(location.latitude, location.longitude);
        }
    }

    updateDetailValue('lastUpdated', location.recorded_at ? formatDateTime(location.recorded_at) : null, '—');

    if (location.accuracy !== undefined) {
        const accuracy = location.accuracy !== null ? `${parseFloat(location.accuracy).toFixed(1)}m` : null;
        updateDetailValue('accuracyValue', accuracy, '—');
    }

    if (location.speed !== undefined) {
        const speed = location.speed !== null ? `${parseFloat(location.speed).toFixed(1)} km/h` : null;
        updateDetailValue('speedValue', speed, '—');
    }

    if (location.address !== undefined) {
        updateDetailValue('addressValue', location.address, '—');
    }
}

function updateProviderInfo(provider, syncError) {
    const block = document.getElementById('providerStatusBlock');
    if (!block) {
        return;
    }

    const hasProvider = provider && provider.provider;

    if (!hasProvider) {
        block.classList.remove('active');
        return;
    }

    block.classList.add('active');

    updateDetailValue('providerName', provider.provider || '', '');
    updateDetailValue('providerMethod', formatTitleCase(provider.method || ''), '');
    updateDetailValue('providerStatus', formatTitleCase(provider.status || ''), '');
    updateDetailValue('providerLastUpdate', provider.last_update ? formatDateTime(provider.last_update) : '', '');
    updateDetailValue('providerFrequency', provider.update_frequency ? `${provider.update_frequency} s` : '', '');
    updateDetailValue('providerDeviceId', provider.device_id || '', '');

    const errorEl = document.getElementById('providerErrorMessage');
    if (errorEl) {
        const message = syncError || provider.error_message || '';
        if (message) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        } else {
            errorEl.textContent = '';
            errorEl.style.display = 'none';
        }
    }
}

function updateDetailValue(id, value, fallback = '—') {
    const el = document.getElementById(id);
    if (!el) {
        return;
    }
    if (value === null || value === undefined || value === '') {
        el.textContent = fallback;
    } else {
        el.textContent = value;
    }
}

function formatCoordinates(lat, lng) {
    const latitude = parseFloat(lat);
    const longitude = parseFloat(lng);
    if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
        return '';
    }
    return `${latitude.toFixed(6)}, ${longitude.toFixed(6)}`;
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }
    const normalised = typeof value === 'string' ? value.replace(' ', 'T') : value;
    const date = new Date(normalised);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString();
}

function formatTitleCase(value) {
    if (!value) {
        return '';
    }
    return value
        .toString()
        .split(/[\s_]+/)
        .filter(Boolean)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

if (selectedRig && selectedRig.current_latitude && selectedRig.current_longitude) {
    if (mapProvider === 'google') {
        if (typeof google !== 'undefined' && google.maps) {
            initMap();
        } else {
            window.addEventListener('load', initMap);
        }
    } else {
        if (typeof L !== 'undefined') {
            initMap();
        } else {
            window.addEventListener('load', initMap);
        }
    }
}

updateProviderInfo(providerMeta, providerMeta && providerMeta.error_message ? providerMeta.error_message : null);

window.onclick = function(event) {
    const modal = document.getElementById('updateLocationModal');
    if (event.target === modal) {
        closeUpdateLocationModal();
    }
};
</script>

<?php require_once '../includes/footer.php'; ?>

