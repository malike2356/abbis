<?php
require_once '../config/app.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'list_accounts':
            $rows = $pdo->query("SELECT id, account_code, account_name, account_type FROM chart_of_accounts ORDER BY account_code")->fetchAll();
            echo json_encode(['success'=>true,'data'=>$rows]);
            break;
        case 'create_journal':
            // Placeholder: accept JSON with entry and lines, validate and insert
            echo json_encode(['success'=>false,'message'=>'Not implemented']);
            break;
        case 'export_qb':
        case 'export_zoho':
            // Redirect to accounting-export.php
            require_once 'accounting-export.php';
            break;
        default:
            echo json_encode(['success'=>false,'message'=>'Unknown action']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}


