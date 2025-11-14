<?php
/**
 * Worker Analytics API
 * Returns worker job statistics and activity data
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$auth->requireAuth();

$pdo = getDBConnection();
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'worker_stats':
            $workerId = intval($_GET['worker_id'] ?? 0);
            if ($workerId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Invalid worker ID'], 400);
            }
            
            $stmt = $pdo->prepare("SELECT * FROM worker_statistics WHERE worker_id = ?");
            $stmt->execute([$workerId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$stats) {
                jsonResponse(['success' => false, 'message' => 'Worker not found'], 404);
            }
            
            jsonResponse(['success' => true, 'data' => $stats]);
            break;
            
        case 'worker_jobs':
            $workerId = intval($_GET['worker_id'] ?? 0);
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';
            
            if ($workerId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Invalid worker ID'], 400);
            }
            
            $sql = "
                SELECT 
                    fr.id,
                    fr.report_id,
                    fr.report_date,
                    fr.site_name,
                    r.rig_name,
                    r.rig_code,
                    c.client_name,
                    fr.job_type,
                    pe.amount as wage_amount,
                    pe.paid_today,
                    fr.total_rpm,
                    fr.total_depth,
                    pe.role as job_role
                FROM payroll_entries pe
                INNER JOIN field_reports fr ON pe.report_id = fr.id
                LEFT JOIN rigs r ON fr.rig_id = r.id
                LEFT JOIN clients c ON fr.client_id = c.id
                WHERE pe.worker_id = ?
            ";
            
            $params = [$workerId];
            
            if ($startDate) {
                $sql .= " AND fr.report_date >= ?";
                $params[] = $startDate;
            }
            
            if ($endDate) {
                $sql .= " AND fr.report_date <= ?";
                $params[] = $endDate;
            }
            
            $sql .= " ORDER BY fr.report_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['success' => true, 'data' => $jobs, 'count' => count($jobs)]);
            break;
            
        case 'weekly_jobs':
            $workerId = intval($_GET['worker_id'] ?? 0);
            $year = intval($_GET['year'] ?? date('Y'));
            
            if ($workerId <= 0) {
                jsonResponse(['success' => false, 'message' => 'Invalid worker ID'], 400);
            }
            
            $sql = "SELECT * FROM worker_weekly_jobs WHERE worker_id = ? AND year = ? ORDER BY week DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$workerId, $year]);
            $weekly = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['success' => true, 'data' => $weekly, 'count' => count($weekly)]);
            break;
            
        case 'all_workers_stats':
            $stmt = $pdo->query("SELECT * FROM worker_statistics ORDER BY total_jobs DESC, worker_name ASC");
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['success' => true, 'data' => $stats, 'count' => count($stats)]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (PDOException $e) {
    jsonResponse(['success' => false, 'message' => 'Database error: ' . $e->getMessage()], 500);
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
}

