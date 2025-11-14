<?php
/**
 * Advanced Search System
 * Global search across all database tables
 */
$page_title = 'Advanced Search';

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$query = sanitizeInput($_GET['q'] ?? '');
$searchType = sanitizeInput($_GET['type'] ?? 'all');
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$status = $_GET['status'] ?? '';
$clientId = intval($_GET['client_id'] ?? 0);

$results = [];
$resultCount = 0;

if (!empty($query)) {
    // Search across multiple tables
    $searchResults = [];
    
    // 1. Field Reports
    try {
        $sql = "SELECT 'field_report' as type, id, report_id as title, report_date as date, 
                       site_name as subtitle, CONCAT('Field Report: ', site_name) as description,
                       CONCAT('modules/field-reports-list.php?id=', id) as url
                FROM field_reports 
                WHERE report_id LIKE ? OR site_name LIKE ? OR client_contact LIKE ? OR region LIKE ?";
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults['field_reports'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $searchResults['field_reports'] = [];
    }
    
    // 2. Clients
    try {
        $sql = "SELECT 'client' as type, id, client_name as title, created_at as date,
                       contact_person as subtitle, CONCAT('Client: ', contact_person, ' - ', COALESCE(email, '')) as description,
                       CONCAT('modules/crm.php?action=clients&client_id=', id) as url
                FROM clients 
                WHERE client_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR contact_number LIKE ?";
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults['clients'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $searchResults['clients'] = [];
    }
    
    // 3. Workers
    try {
        $sql = "SELECT 
                    'worker' as type, 
                    id, 
                    CONCAT_WS(' • ', NULLIF(employee_code, ''), worker_name) as title, 
                    created_at as date,
                    CONCAT_WS(' • ', NULLIF(role, ''), NULLIF(employee_code, '')) as subtitle, 
                    CONCAT_WS(' • ',
                        CONCAT('Staff ID: ', COALESCE(NULLIF(employee_code, ''), 'Pending assignment')),
                        CONCAT('Role: ', COALESCE(NULLIF(role, ''), 'Unassigned')),
                        CONCAT('Phone: ', COALESCE(NULLIF(contact_number, ''), 'N/A'))
                    ) as description,
                    CONCAT('modules/config.php#workers') as url
                FROM workers 
                WHERE worker_name LIKE ? 
                   OR role LIKE ? 
                   OR contact_number LIKE ? 
                   OR employee_code LIKE ?";
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults['workers'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $searchResults['workers'] = [];
    }
    
    // 4. Maintenance Records (if table exists)
    try {
        $sql = "SELECT 'maintenance' as type, id, maintenance_code as title, scheduled_date as date,
                       CONCAT(asset_id, ' - ', status) as subtitle, description,
                       CONCAT('modules/maintenance.php?action=record-detail&id=', id) as url
                FROM maintenance_records 
                WHERE maintenance_code LIKE ? OR description LIKE ? OR work_performed LIKE ?";
        $params = ["%$query%", "%$query%", "%$query%"];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults['maintenance'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $searchResults['maintenance'] = [];
    }
    
    // 5. Assets (if table exists)
    try {
        $sql = "SELECT 'asset' as type, id, asset_name as title, purchase_date as date,
                       asset_type as subtitle, CONCAT('Asset: ', asset_type, ' - ', COALESCE(location, '')) as description,
                       CONCAT('modules/assets.php?action=asset-detail&asset_id=', id) as url
                FROM assets 
                WHERE asset_name LIKE ? OR asset_code LIKE ? OR brand LIKE ? OR model LIKE ?";
        $params = ["%$query%", "%$query%", "%$query%", "%$query%"];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $searchResults['assets'] = $stmt->fetchAll();
    } catch (PDOException $e) {
        $searchResults['assets'] = [];
    }
    
    // Combine all results
    foreach ($searchResults as $type => $items) {
        if ($searchType === 'all' || $searchType === $type || ($searchType === 'worker' && $type === 'worker_job')) {
            $results = array_merge($results, $items);
        }
    }
    
    $resultCount = count($results);
}

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="page-header">
        <h1>🔍 Advanced Search</h1>
        <p>Search across all system data - Field Reports, Clients, Workers, Maintenance, Assets, and more</p>
    </div>
    
    <!-- Search Form -->
    <div class="dashboard-card" style="margin-bottom: 30px;">
        <form method="GET" action="search.php">
            <div style="display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end;">
                <div>
                    <label class="form-label">Search Query</label>
                    <input type="text" 
                           name="q" 
                           class="form-control" 
                           value="<?php echo e($query); ?>"
                           placeholder="Enter search term..."
                           autofocus>
                </div>
                
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" class="form-control">
                        <option value="all" <?php echo $searchType === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="field_report" <?php echo $searchType === 'field_report' ? 'selected' : ''; ?>>Field Reports</option>
                        <option value="client" <?php echo $searchType === 'client' ? 'selected' : ''; ?>>Clients</option>
                        <option value="worker" <?php echo $searchType === 'worker' ? 'selected' : ''; ?>>Workers</option>
                        <option value="worker_job" <?php echo $searchType === 'worker_job' ? 'selected' : ''; ?>>Worker Jobs</option>
                        <option value="maintenance" <?php echo $searchType === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="asset" <?php echo $searchType === 'asset' ? 'selected' : ''; ?>>Assets</option>
                    </select>
                </div>
                
                <div>
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo e($dateFrom); ?>">
                </div>
                
                <div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">🔍 Search</button>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                <div>
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo e($dateTo); ?>">
                </div>
                
                <div>
                    <label class="form-label">Client (for Field Reports)</label>
                    <select name="client_id" class="form-control">
                        <option value="">All Clients</option>
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT id, client_name FROM clients ORDER BY client_name");
                            while ($client = $stmt->fetch()): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $clientId === $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($client['client_name']); ?>
                                </option>
                            <?php endwhile;
                        } catch (PDOException $e) {}
                        ?>
                    </select>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Search Results -->
    <?php if (!empty($query)): ?>
        <div class="dashboard-card">
            <h2 style="margin-bottom: 20px;">
                Search Results 
                <span style="color: var(--secondary); font-weight: normal; font-size: 16px;">
                    (<?php echo number_format($resultCount); ?> found)
                </span>
            </h2>
            
            <?php if (empty($results)): ?>
                <div style="text-align: center; padding: 40px; color: var(--secondary);">
                    <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
                    <p>No results found for "<strong><?php echo e($query); ?></strong>"</p>
                    <p style="font-size: 13px; margin-top: 8px;">Try different keywords or check your filters</p>
                </div>
            <?php else: ?>
                <div style="display: grid; gap: 12px;">
                    <?php foreach ($results as $result): ?>
                        <a href="<?php echo e($result['url']); ?>" 
                           style="
                               display: block;
                               padding: 16px;
                               border: 1px solid var(--border);
                               border-radius: 8px;
                               background: var(--card);
                               text-decoration: none;
                               color: inherit;
                               transition: all 0.2s ease;
                           "
                           onmouseover="this.style.borderColor='var(--primary)'; this.style.boxShadow='0 2px 8px rgba(14,165,233,0.1)';"
                           onmouseout="this.style.borderColor='var(--border)'; this.style.boxShadow='none';">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                        <span style="
                                            padding: 4px 8px;
                                            background: var(--primary);
                                            color: white;
                                            border-radius: 4px;
                                            font-size: 10px;
                                            font-weight: 600;
                                            text-transform: uppercase;
                                        ">
                                            <?php echo ucfirst(str_replace('_', ' ', $result['type'])); ?>
                                        </span>
                                        <strong style="color: var(--text); font-size: 16px;">
                                            <?php echo e($result['title']); ?>
                                        </strong>
                                    </div>
                                    <?php if (!empty($result['subtitle'])): ?>
                                        <div style="color: var(--secondary); font-size: 13px; margin-bottom: 4px;">
                                            <?php echo e($result['subtitle']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['description'])): ?>
                                        <div style="color: var(--text); font-size: 14px; line-height: 1.5;">
                                            <?php echo e(substr($result['description'], 0, 150)); ?>
                                            <?php echo strlen($result['description']) > 150 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($result['date'])): ?>
                                        <div style="color: var(--secondary); font-size: 12px; margin-top: 8px;">
                                            📅 <?php echo date('M j, Y', strtotime($result['date'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="color: var(--primary); font-size: 20px; margin-left: 16px;">
                                    →
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>

