<?php
/**
 * Looker Studio Integration Management
 */
$page_title = 'Looker Studio Integration';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>📊 Looker Studio Integration</h1>
        <p>Connect ABBIS data to Google Looker Studio for advanced visualization and reporting</p>
    </div>
    
    <div class="dashboard-grid">
        <!-- Connection Guide -->
        <div class="dashboard-card">
            <h2>🔗 Connection Guide</h2>
            <ol style="line-height: 2;">
                <li><strong>Open Looker Studio:</strong>
                    <ul>
                        <li>Go to <a href="https://lookerstudio.google.com/" target="_blank">lookerstudio.google.com</a></li>
                        <li>Click "Create" → "Data Source"</li>
                    </ul>
                </li>
                <li><strong>Add Connector:</strong>
                    <ul>
                        <li>Search for "Community Connectors"</li>
                        <li>Or use "Universal Data Connector"</li>
                    </ul>
                </li>
                <li><strong>Configure URL:</strong>
                    <ul>
                        <li>Use: <code><?php 
                            $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
                            echo $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/api/looker-studio-api.php';
                        ?></code></li>
                    </ul>
                </li>
                <li><strong>Authentication:</strong>
                    <ul>
                        <li>Use API Key or Session-based auth</li>
                        <li>API Key: Add as URL parameter <code>?api_key=your_key</code></li>
                    </ul>
                </li>
            </ol>
        </div>
        
        <!-- Available Data Sources -->
        <div class="dashboard-card">
            <h2>📊 Available Data Sources</h2>
            <div style="margin-top: 20px;">
                <h3>Field Reports</h3>
                <code>?action=data&data_source=field_reports</code>
                <p style="margin-top: 8px; font-size: 14px; color: var(--secondary);">
                    Complete field report data with financial and operational metrics
                </p>
                
                <h3 style="margin-top: 20px;">Financial Data</h3>
                <code>?action=data&data_source=financial</code>
                <p style="margin-top: 8px; font-size: 14px; color: var(--secondary);">
                    Revenue, expenses, profit by time period
                </p>
                
                <h3 style="margin-top: 20px;">Clients</h3>
                <code>?action=data&data_source=clients</code>
                <p style="margin-top: 8px; font-size: 14px; color: var(--secondary);">
                    Client information with transaction history
                </p>
                
                <h3 style="margin-top: 20px;">Workers/Payroll</h3>
                <code>?action=data&data_source=workers</code>
                <p style="margin-top: 8px; font-size: 14px; color: var(--secondary);">
                    Worker earnings and payroll data
                </p>
                
                <h3 style="margin-top: 20px;">Materials/Inventory</h3>
                <code>?action=data&data_source=materials</code>
                <p style="margin-top: 8px; font-size: 14px; color: var(--secondary);">
                    Material inventory levels and values
                </p>
            </div>
        </div>
        
        <!-- API Endpoints -->
        <div class="dashboard-card">
            <h2>🔌 API Endpoints</h2>
            <div style="margin-top: 20px;">
                <h3>Get Data</h3>
                <pre style="background: var(--bg); padding: 12px; border-radius: 6px; overflow-x: auto;">
GET <?php 
    $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
    echo $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/api/looker-studio-api.php?action=data&data_source=field_reports';
?></pre>
                
                <h3 style="margin-top: 20px;">Get Schema</h3>
                <pre style="background: var(--bg); padding: 12px; border-radius: 6px; overflow-x: auto;">
GET <?php 
    echo $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/api/looker-studio-api.php?action=schema&data_source=field_reports';
?></pre>
                
                <h3 style="margin-top: 20px;">Get Metrics</h3>
                <pre style="background: var(--bg); padding: 12px; border-radius: 6px; overflow-x: auto;">
GET <?php 
    echo $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['PHP_SELF'])) . '/api/looker-studio-api.php?action=metrics';
?></pre>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="dashboard-card">
            <h2>🔍 Filters & Parameters</h2>
            <div style="margin-top: 20px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Description</th>
                            <th>Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>start_date</code></td>
                            <td>Start date filter (YYYY-MM-DD)</td>
                            <td><code>?start_date=2024-01-01</code></td>
                        </tr>
                        <tr>
                            <td><code>end_date</code></td>
                            <td>End date filter (YYYY-MM-DD)</td>
                            <td><code>?end_date=2024-12-31</code></td>
                        </tr>
                        <tr>
                            <td><code>data_source</code></td>
                            <td>Data source type</td>
                            <td><code>?data_source=financial</code></td>
                        </tr>
                        <tr>
                            <td><code>api_key</code></td>
                            <td>API key for authentication</td>
                            <td><code>?api_key=your_key</code></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Test Connection -->
    <div class="dashboard-card" style="margin-top: 30px;">
        <h2>🧪 Test Connection</h2>
        <button onclick="testConnection()" class="btn btn-primary">
            Test API Connection
        </button>
        <div id="testResult" style="margin-top: 16px;"></div>
    </div>
</div>

<script>
function testConnection() {
    const resultDiv = document.getElementById('testResult');
    resultDiv.innerHTML = '<p>Testing connection...</p>';
    
    fetch('../api/looker-studio-api.php?action=metrics')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>✓ Connection Successful!</strong><br>
                        <pre>${JSON.stringify(data, null, 2)}</pre>
                    </div>
                `;
            } else {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>✗ Connection Failed</strong><br>
                        ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            resultDiv.innerHTML = `
                <div class="alert alert-danger">
                    <strong>✗ Error</strong><br>
                    ${error.message}
                </div>
            `;
        });
}
</script>

<?php require_once '../includes/footer.php'; ?>

