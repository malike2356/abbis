<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAuth();

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$pdo = getDBConnection();

try {
    switch ($type) {
        case 'report':
            if (empty($id)) {
                throw new Exception('Report ID is required');
            }
            
            $stmt = $pdo->prepare("
                SELECT fr.*, r.rig_name, c.client_name 
                FROM field_reports fr 
                LEFT JOIN rigs r ON fr.rig_id = r.id 
                LEFT JOIN clients c ON fr.client_id = c.id 
                WHERE fr.id = ?
            ");
            $stmt->execute([$id]);
            $report = $stmt->fetch();
            
            if (!$report) {
                throw new Exception('Report not found');
            }
            
            // Get payroll entries
            $payrollStmt = $pdo->prepare("SELECT * FROM payroll_entries WHERE report_id = ?");
            $payrollStmt->execute([$id]);
            $report['payroll_entries'] = $payrollStmt->fetchAll();
            
            // Get expense entries
            $expenseStmt = $pdo->prepare("SELECT * FROM expense_entries WHERE report_id = ?");
            $expenseStmt->execute([$id]);
            $report['expense_entries'] = $expenseStmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $report]);
            break;
            
        case 'reports_summary':
            $query = "SELECT 
                COUNT(*) as total_reports,
                COALESCE(SUM(total_income), 0) as total_income,
                COALESCE(SUM(total_expenses), 0) as total_expenses,
                COALESCE(SUM(net_profit), 0) as total_profit,
                COALESCE(SUM(total_depth), 0) as total_depth,
                COALESCE(AVG(net_profit), 0) as avg_profit_per_job
            FROM field_reports WHERE 1=1";
            
            $params = [];
            
            if (!empty($startDate)) {
                $query .= " AND report_date >= ?";
                $params[] = $startDate;
            }
            
            if (!empty($endDate)) {
                $query .= " AND report_date <= ?";
                $params[] = $endDate;
            }
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $summary = $stmt->fetch();
            
            echo json_encode(['success' => true, 'data' => $summary]);
            break;
            
        case 'monthly_profits':
            $stmt = $pdo->prepare("
                SELECT 
                    DATE_FORMAT(report_date, '%Y-%m') as month,
                    SUM(net_profit) as total_profit,
                    COUNT(*) as job_count
                FROM field_reports 
                WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(report_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 12
            ");
            $stmt->execute();
            $monthlyData = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $monthlyData]);
            break;
            
        case 'rig_performance':
            $stmt = $pdo->prepare("
                SELECT 
                    r.rig_name,
                    COUNT(fr.id) as job_count,
                    COALESCE(SUM(fr.net_profit), 0) as total_profit,
                    COALESCE(AVG(fr.net_profit), 0) as avg_profit,
                    COALESCE(SUM(fr.total_depth), 0) as total_depth
                FROM rigs r
                LEFT JOIN field_reports fr ON r.id = fr.rig_id
                WHERE fr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY r.id, r.rig_name
                ORDER BY total_profit DESC
            ");
            $stmt->execute();
            $rigPerformance = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $rigPerformance]);
            break;
            
        case 'worker_earnings':
            $stmt = $pdo->prepare("
                SELECT 
                    worker_name,
                    role,
                    SUM(amount) as total_earnings,
                    COUNT(*) as jobs_worked,
                    AVG(amount) as avg_earnings_per_job
                FROM payroll_entries 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                GROUP BY worker_name, role
                ORDER BY total_earnings DESC
            ");
            $stmt->execute();
            $workerEarnings = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $workerEarnings]);
            break;
            
        case 'materials_usage':
            $stmt = $pdo->prepare("
                SELECT 
                    material_type,
                    SUM(quantity_used) as total_used,
                    SUM(quantity_remaining) as total_remaining,
                    AVG(unit_cost) as avg_unit_cost
                FROM materials_inventory 
                GROUP BY material_type
            ");
            $stmt->execute();
            $materialsUsage = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $materialsUsage]);
            break;
            
        default:
            throw new Exception('Invalid data type requested');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>