<?php
$page_title = 'Geology Estimator';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requirePermission('resources.access');

$csrfField = CSRF::getTokenField();
$csrfToken = CSRF::getToken();

require_once '../includes/header.php';
?>

<div class="module-container">
    <style>
        .geo-layout {
            display: grid;
            grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
            gap: 28px;
            margin-top: 32px;
        }
        .geo-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--shadow-sm, 0 10px 25px rgba(15,23,42,0.08));
            padding: 24px;
        }
        .geo-card h2 {
            margin: 0 0 12px;
            font-size: 22px;
            font-weight: 600;
        }
        .geo-map {
            width: 100%;
            height: 320px;
            border-radius: 14px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 16px;
        }
        .geo-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        .geo-results {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .geo-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            border-radius: 999px;
            padding: 4px 10px;
            background: rgba(37, 99, 235, 0.08);
            color: #1d4ed8;
            border: 1px solid rgba(37, 99, 235, 0.14);
        }
        .geo-insights {
            margin-top: 24px;
            background: rgba(15, 23, 42, 0.03);
            border-radius: 14px;
            padding: 18px 22px;
            border: 1px solid var(--border);
        }
        .geo-insights ul {
            margin: 12px 0 0;
            padding-left: 16px;
            color: var(--text-light, #475569);
        }
        .geo-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
        }
        .geo-table th,
        .geo-table td {
            padding: 12px 14px;
            border-top: 1px solid var(--border);
            font-size: 14px;
        }
        .geo-table th {
            text-transform: uppercase;
            font-size: 12px;
            color: var(--text-light);
            letter-spacing: 0.08em;
            background: rgba(15, 23, 42, 0.02);
        }
        .geo-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(34, 197, 94, 0.12);
            color: #047857;
            font-size: 12px;
            font-weight: 600;
        }
        @media (max-width: 960px) {
            .geo-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-heading">
        <h1>Geology Estimator</h1>
        <p class="page-subtitle">
            Predict drilling depth, water levels, and aquifer characteristics anywhere you drop a pin.
            Import historical well logs for higher accuracy.
        </p>
        <div style="margin-top:16px;">
            <a href="data-management.php#datasets" class="btn-link">🗂️ Manage geology datasets</a>
            <span class="text-muted" style="margin:0 8px;">•</span>
            <a href="<?php echo api_url('geology-estimate.php'); ?>" class="btn-link" target="_blank" rel="noopener">API reference</a>
        </div>
    </div>

    <div class="geo-layout">
        <div class="geo-card">
            <h2>Set Location</h2>
            <p style="color:var(--text-light); margin-bottom:16px;">
                Click on the map or enter coordinates. Adjust the search radius to control how far we look for comparisons.
            </p>
            <div id="geologyMap" class="geo-map"></div>
            <form id="geologyForm" class="form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="csrf_token_value" id="geoCsrfToken" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <div class="form-group">
                    <label class="form-label">Latitude</label>
                    <input type="number" step="0.000001" name="latitude" id="geoLatitude" class="form-control" placeholder="e.g. 5.603716" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Longitude</label>
                    <input type="number" step="0.000001" name="longitude" id="geoLongitude" class="form-control" placeholder="e.g. -0.187000" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Search Radius (km)</label>
                    <input type="number" step="1" min="5" max="100" name="radius_km" id="geoRadius" class="form-control" value="15">
                </div>
                <div class="geo-actions">
                    <button type="button" id="geoEstimateButton" class="btn-primary" style="flex:1;">
                        🔍 Generate Estimate
                    </button>
                    <button type="button" id="geoResetButton" class="btn-secondary">
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <div class="geo-card">
            <div id="geoResultsEmpty" style="text-align:center; color:var(--text-light); padding:40px 20px;">
                <p style="font-size:16px; margin-bottom:8px;">Drop a pin and hit “Generate Estimate”</p>
                <p style="font-size:14px;">We’ll analyse historical wells near your location and give you depth, yield, and lithology insights.</p>
            </div>

            <div id="geoResults" style="display:none;">
                <div class="geo-results" id="geoHeadlineCards"></div>
                <div class="geo-insights" id="geoInsights" style="display:none;">
                    <span class="geo-pill">Key Insights</span>
                    <ul id="geoInsightsList"></ul>
                </div>
                <div id="geoLithology" style="margin-top:24px; display:none;">
                    <span class="geo-pill">Lithology & Aquifer</span>
                    <p id="geoLithologyText" style="margin:12px 0 0; color:var(--text-light);"></p>
                </div>
                <div style="margin-top:28px;">
                    <h3 style="margin:0 0 12px; font-size:18px;">Nearby Wells</h3>
                    <table class="geo-table" id="geoNeighborsTable">
                        <thead>
                            <tr>
                                <th>Ref</th>
                                <th>Depth (m)</th>
                                <th>Distance (km)</th>
                                <th>Yield (m³/hr)</th>
                                <th>Aquifer</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-o9N1j7kG6G8GuI3p3VbK2s9O+Vx0SlhKpQtJ6Ck0Pzk=" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-vI8sNcdxYvxZQ74dNwxLPp1kX3VzJ4IcQP+NlV0z0XQ=" crossorigin=""></script>
<script>
(function() {
    const mapElement = document.getElementById('geologyMap');
    const latInput = document.getElementById('geoLatitude');
    const lngInput = document.getElementById('geoLongitude');
    const radiusInput = document.getElementById('geoRadius');
    const estimateButton = document.getElementById('geoEstimateButton');
    const resetButton = document.getElementById('geoResetButton');
    const resultsWrapper = document.getElementById('geoResults');
    const emptyState = document.getElementById('geoResultsEmpty');
    const cardsContainer = document.getElementById('geoHeadlineCards');
    const insightsSection = document.getElementById('geoInsights');
    const insightsList = document.getElementById('geoInsightsList');
    const lithologySection = document.getElementById('geoLithology');
    const lithologyText = document.getElementById('geoLithologyText');
    const neighborsTableBody = document.querySelector('#geoNeighborsTable tbody');
    const csrfToken = document.getElementById('geoCsrfToken').value;

    let map;
    let marker;

    function initMap() {
        map = L.map(mapElement).setView([5.6037, -0.1870], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        marker = L.marker([5.6037, -0.1870], { draggable: true }).addTo(map);

        marker.on('dragend', function (e) {
            const { lat, lng } = e.target.getLatLng();
            updateLatLng(lat, lng);
        });

        map.on('click', function (e) {
            marker.setLatLng(e.latlng);
            updateLatLng(e.latlng.lat, e.latlng.lng);
        });
    }

    function updateLatLng(lat, lng) {
        latInput.value = lat.toFixed(6);
        lngInput.value = lng.toFixed(6);
    }

    function resetForm() {
        updateLatLng(5.6037, -0.1870);
        radiusInput.value = 15;
        cardsContainer.innerHTML = '';
        insightsList.innerHTML = '';
        neighborsTableBody.innerHTML = '';
        lithologyText.textContent = '';
        resultsWrapper.style.display = 'none';
        insightsSection.style.display = 'none';
        lithologySection.style.display = 'none';
        emptyState.style.display = 'block';
    }

    function renderCards(prediction) {
        const items = [
            {
                title: 'Predicted Depth',
                value: prediction.depth_avg_m ? `${prediction.depth_avg_m.toFixed(1)} m` : '—',
                detail: `Range: ${prediction.depth_min_m.toFixed(1)} – ${prediction.depth_max_m.toFixed(1)} m`
            },
            {
                title: 'Recommended Casing',
                value: prediction.recommended_casing_depth_m ? `${prediction.recommended_casing_depth_m.toFixed(1)} m` : '—',
                detail: 'Target 75% of predicted depth'
            },
            {
                title: 'Static Water Level',
                value: prediction.static_water_level_avg_m !== null ? `${prediction.static_water_level_avg_m.toFixed(1)} m` : '—',
                detail: 'Average of nearby wells'
            },
            {
                title: 'Confidence',
                value: prediction.confidence_score !== null ? `${Math.round(prediction.confidence_score * 100)}%` : '—',
                detail: 'More local data → higher confidence'
            }
        ];

        cardsContainer.innerHTML = items.map(item => `
            <div class="geo-card" style="padding:18px;">
                <h3 style="margin:0; font-size:16px;">${item.title}</h3>
                <div style="font-size:26px; font-weight:700; margin:10px 0;">${item.value}</div>
                <div style="color:var(--text-light); font-size:13px;">${item.detail}</div>
            </div>
        `).join('');
    }

    function renderInsights(insights) {
        if (!insights || insights.length === 0) {
            insightsSection.style.display = 'none';
            return;
        }
        insightsList.innerHTML = insights.map(item => `<li>${item}</li>`).join('');
        insightsSection.style.display = 'block';
    }

    function renderNeighbors(neighbors) {
        neighborsTableBody.innerHTML = (neighbors || []).slice(0, 8).map(neighbor => `
            <tr>
                <td>${neighbor.reference_code ? neighbor.reference_code : (neighbor.id ? '#' + neighbor.id : '—')}</td>
                <td>${neighbor.depth_m !== null ? neighbor.depth_m.toFixed(1) : '—'}</td>
                <td>${neighbor.distance_km !== null ? neighbor.distance_km.toFixed(2) : '—'}</td>
                <td>${neighbor.yield_m3_per_hr !== null ? neighbor.yield_m3_per_hr.toFixed(1) : '—'}</td>
                <td>${neighbor.aquifer_type ? neighbor.aquifer_type : '—'}</td>
            </tr>
        `).join('');
    }

    function showError(message) {
        emptyState.style.display = 'block';
        emptyState.innerHTML = `<p style="color:#b91c1c;">${message}</p>`;
        resultsWrapper.style.display = 'none';
    }

    estimateButton.addEventListener('click', async function () {
        if (!latInput.value || !lngInput.value) {
            showError('Please provide latitude and longitude.');
            return;
        }

        estimateButton.disabled = true;
        estimateButton.textContent = 'Analysing…';

        try {
            const response = await fetch('../api/geology-estimate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken,
                    latitude: parseFloat(latInput.value),
                    longitude: parseFloat(lngInput.value),
                    radius_km: parseFloat(radiusInput.value) || 15
                })
            });

            const payload = await response.json();

            if (!response.ok || !payload.success) {
                throw new Error(payload.message || 'Unable to generate estimate.');
            }

            const data = payload.data;
            if (!data || !data.success) {
                throw new Error(data.message || 'Estimator returned no data.');
            }

            const prediction = data.prediction;

            renderCards(prediction);
            renderInsights(data.insights || []);

            if (data.lithology_summary || data.aquifer_summary) {
                lithologyText.textContent = `${data.lithology_summary || '—'} • ${data.aquifer_summary || ''}`.trim();
                lithologySection.style.display = 'block';
            } else {
                lithologySection.style.display = 'none';
            }

            renderNeighbors(data.neighbors);

            emptyState.style.display = 'none';
            resultsWrapper.style.display = 'block';
        } catch (error) {
            console.error(error);
            showError(error.message || 'An unexpected error occurred.');
        } finally {
            estimateButton.disabled = false;
            estimateButton.textContent = '🔍 Generate Estimate';
        }
    });

    resetButton.addEventListener('click', function () {
        resetForm();
        marker.setLatLng([5.6037, -0.1870]);
        map.setView([5.6037, -0.1870], 7);
    });

    initMap();
    resetForm();
})();
</script>

<?php require_once '../includes/footer.php'; ?>

