<?php
require_once '../config/app.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function json_ok($data) { echo json_encode(['success'=>true,'data'=>$data]); exit; }
function json_err($msg) { http_response_code(400); echo json_encode(['success'=>false,'message'=>$msg]); exit; }

try {
    switch ($action) {
        case 'forecast_cashflow':
            // Baseline: moving average of last 30 days income/expenses from field_reports
            $row = $pdo->query("SELECT 
                COALESCE(AVG(total_income),0) as avg_income,
                COALESCE(AVG(total_expenses),0) as avg_expenses
                FROM (
                    SELECT total_income, total_expenses
                    FROM field_reports
                    WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                ) t")->fetch();
            $in = (float)($row['avg_income'] ?? 0);
            $out = (float)($row['avg_expenses'] ?? 0);
            json_ok([
                'horizons' => [
                    ['horizon'=>'30d','inflow'=>$in*30,'outflow'=>$out*30,'net'=>($in-$out)*30],
                    ['horizon'=>'60d','inflow'=>$in*60,'outflow'=>$out*60,'net'=>($in-$out)*60],
                    ['horizon'=>'90d','inflow'=>$in*90,'outflow'=>$out*90,'net'=>($in-$out)*90],
                ]
            ]);
            break;

        case 'forecast_materials':
            // Baseline: demand approximated from average materials_used per report in last 30 days
            $rows = [];
            try {
                $rows = $pdo->query("SELECT 'screen_pipes' as material_key, COALESCE(AVG(screen_pipes_used),0) as avg_qty FROM field_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    UNION ALL
                    SELECT 'plain_pipes', COALESCE(AVG(plain_pipes_used),0) FROM field_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                    UNION ALL
                    SELECT 'gravel', COALESCE(AVG(gravel_used),0) FROM field_reports WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")
                    ->fetchAll();
            } catch (PDOException $e) { $rows = []; }
            $out = [];
            foreach ($rows as $r) {
                $avg = (float)$r['avg_qty'];
                $out[] = ['material_key'=>$r['material_key'],'horizon'=>'30d','demand_qty'=>$avg*30];
                $out[] = ['material_key'=>$r['material_key'],'horizon'=>'60d','demand_qty'=>$avg*60];
                $out[] = ['material_key'=>$r['material_key'],'horizon'=>'90d','demand_qty'=>$avg*90];
            }
            json_ok($out);
            break;

        case 'lead_score':
            // Simple heuristic: more reports + higher avg profit -> higher score
            $clientId = intval($_GET['client_id'] ?? 0);
            if ($clientId <= 0) json_err('client_id required');
            $row = $pdo->prepare("SELECT COUNT(*) jobs, COALESCE(AVG(net_profit),0) avg_profit FROM field_reports WHERE client_id = ?");
            $row->execute([$clientId]);
            $d = $row->fetch();
            $jobs = (int)$d['jobs']; $avgp = (float)$d['avg_profit'];
            $score = max(0, min(100, ($jobs*5) + ($avgp>0 ? min(50, $avgp/1000*50) : 0)));
            json_ok(['client_id'=>$clientId,'score'=>round($score,1)]);
            break;

        default:
            json_err('Unknown action');
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}


