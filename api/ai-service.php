<?php

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/AI/bootstrap.php';
require_once __DIR__ . '/../includes/AI/Governance/UsageLimiter.php';
require_once __DIR__ . '/../includes/AI/Governance/AuditLogger.php';

$auth->requireAuth();
$auth->requirePermission(AI_PERMISSION_KEY);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!in_array($action, ['forecast_cashflow', 'forecast_materials', 'lead_score'], true)) {
    require __DIR__ . '/ai-insights.php';
    exit;
}

$pdo = getDBConnection();
$limiter = new UsageLimiter();
$audit = new AIAuditLogger();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? null;

try {
    $limiter->assertWithinLimits($userId, $action);

    switch ($action) {
        case 'forecast_cashflow':
            $data = handleForecastCashflow($pdo);
            break;

        case 'forecast_materials':
            $data = handleForecastMaterials($pdo);
            break;

        case 'lead_score':
            $clientId = isset($_GET['client_id']) ? (int) $_GET['client_id'] : 0;
            if ($clientId <= 0) {
                jsonResponse([
                    'success' => false,
                    'message' => 'client_id required',
                ], 422);
            }
            $data = handleLeadScore($pdo, $clientId);
            break;

        default:
            jsonResponse([
                'success' => false,
                'message' => 'Unknown action',
            ], 400);
    }

    $audit->log([
        'user_id' => $userId,
        'role' => $userRole,
        'action' => $action,
        'provider' => 'heuristic',
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
        'latency_ms' => null,
        'input_hash' => hash('sha256', json_encode(['action' => $action, 'params' => $_REQUEST], JSON_UNESCAPED_SLASHES)),
        'context_summary' => substr($action, 0, 255),
        'is_success' => 1,
        'metadata' => ['source' => 'legacy_ai_service'],
    ]);

    jsonResponse([
        'success' => true,
        'data' => $data,
    ]);
} catch (AIProviderException $e) {
    $audit->log([
        'user_id' => $userId,
        'role' => $userRole,
        'action' => $action,
        'provider' => 'heuristic',
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
        'latency_ms' => null,
        'input_hash' => hash('sha256', json_encode(['action' => $action, 'params' => $_REQUEST], JSON_UNESCAPED_SLASHES)),
        'context_summary' => substr($action, 0, 255),
        'is_success' => 0,
        'error_code' => $e->getCategory(),
        'metadata' => ['error' => $e->getMessage()],
    ]);

    jsonResponse([
        'success' => false,
        'message' => $e->getMessage(),
    ], 429);
} catch (Throwable $e) {
    $audit->log([
        'user_id' => $userId,
        'role' => $userRole,
        'action' => $action,
        'provider' => 'heuristic',
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
        'latency_ms' => null,
        'input_hash' => hash('sha256', json_encode(['action' => $action, 'params' => $_REQUEST], JSON_UNESCAPED_SLASHES)),
        'context_summary' => substr($action, 0, 255),
        'is_success' => 0,
        'error_code' => 'exception',
        'metadata' => ['exception' => $e->getMessage()],
    ]);

    jsonResponse([
        'success' => false,
        'message' => 'Server error: ' . ($e->getMessage()),
    ], 500);
}

function handleForecastCashflow(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT 
            COALESCE(AVG(total_income),0) AS avg_income,
            COALESCE(AVG(total_expenses),0) AS avg_expenses
        FROM (
            SELECT total_income, total_expenses
            FROM field_reports
            WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ) t
    ");

    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    $income = (float) ($row['avg_income'] ?? 0);
    $expenses = (float) ($row['avg_expenses'] ?? 0);

    $horizons = [];
    foreach ([30, 60, 90] as $days) {
        $horizons[] = [
            'horizon' => $days . 'd',
            'inflow' => $income * $days,
            'outflow' => $expenses * $days,
            'net' => ($income - $expenses) * $days,
        ];
    }

    return [
        'horizons' => $horizons,
        'baseline_average' => [
            'income_daily' => $income,
            'expenses_daily' => $expenses,
        ],
    ];
}

function handleForecastMaterials(PDO $pdo): array
{
    $sql = "
        SELECT 'screen_pipes' AS material_key, COALESCE(AVG(screen_pipes_used),0) AS avg_qty
        FROM field_reports
        WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 'plain_pipes', COALESCE(AVG(plain_pipes_used),0)
        FROM field_reports
        WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        UNION ALL
        SELECT 'gravel', COALESCE(AVG(gravel_used),0)
        FROM field_reports
        WHERE report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";

    $rows = [];
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // ignore and fall back to empty set
    }

    $forecasts = [];
    foreach ($rows as $row) {
        $avg = (float) ($row['avg_qty'] ?? 0);
        foreach ([30, 60, 90] as $days) {
            $forecasts[] = [
                'material_key' => $row['material_key'],
                'horizon' => $days . 'd',
                'demand_qty' => $avg * $days,
            ];
        }
    }

    return [
        'forecasts' => $forecasts,
    ];
}

function handleLeadScore(PDO $pdo, int $clientId): array
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS jobs, COALESCE(AVG(net_profit),0) AS avg_profit
        FROM field_reports
        WHERE client_id = ?
    ");
    $stmt->execute([$clientId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $jobs = (int) ($row['jobs'] ?? 0);
    $avgProfit = (float) ($row['avg_profit'] ?? 0);

    $score = ($jobs * 5);
    if ($avgProfit > 0) {
        $score += min(50, ($avgProfit / 1000) * 50);
    }
    $score = max(0, min(100, $score));

    return [
        'client_id' => $clientId,
        'score' => round($score, 1),
        'driver' => [
            'jobs_count' => $jobs,
            'average_profit' => $avgProfit,
        ],
    ];
}
