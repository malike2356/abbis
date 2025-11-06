<?php
/**
 * Search API - Quick search endpoint
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth->requireAuth();

$query = sanitizeInput($_GET['q'] ?? '');
$limit = intval($_GET['limit'] ?? 10);

if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Query required']);
    exit;
}

$pdo = getDBConnection();
$results = [];

try {
    // Quick search across key tables
    $sql = "
        (SELECT 'field_report' as type, id, report_id as title, report_date as date, 
                CONCAT('modules/field-reports-list.php?id=', id) as url
         FROM field_reports 
         WHERE report_id LIKE ? OR site_name LIKE ? 
         LIMIT 5)
        UNION
        (SELECT 'client' as type, id, client_name as title, created_at as date,
                CONCAT('modules/crm.php?action=clients&client_id=', id) as url
         FROM clients 
         WHERE client_name LIKE ? OR contact_person LIKE ? 
         LIMIT 5)
        UNION
        (SELECT 'worker' as type, id, worker_name as title, created_at as date,
                CONCAT('modules/config.php#workers') as url
         FROM workers 
         WHERE worker_name LIKE ? OR role LIKE ? 
         LIMIT 5)
        ORDER BY date DESC
        LIMIT ?
    ";
    
    $searchParam = "%$query%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $searchParam, $searchParam,
        $searchParam, $searchParam,
        $searchParam, $searchParam,
        $limit
    ]);
    
    $results = $stmt->fetchAll();
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Search error']);
    exit;
}

echo json_encode([
    'success' => true,
    'results' => $results,
    'count' => count($results)
]);

