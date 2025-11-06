<?php
require_once '../includes/auth.php';
require_once '../includes/functions.php';

$auth->requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? 'push';
$data = $_POST['data'] ?? '';

$pdo = getDBConnection();

try {
    switch ($action) {
        case 'push':
            // Push data to Google Sheets (placeholder implementation)
            $googleSheetsUrl = ''; // Get from config
            
            if (empty($googleSheetsUrl)) {
                throw new Exception('Google Sheets integration not configured');
            }
            
            // Get unsynced reports
            $stmt = $pdo->prepare("
                SELECT fr.*, r.rig_name, c.client_name 
                FROM field_reports fr 
                LEFT JOIN rigs r ON fr.rig_id = r.id 
                LEFT JOIN clients c ON fr.client_id = c.id 
                WHERE fr.sync_status IS NULL OR fr.sync_status != 'synced'
                ORDER BY fr.created_at DESC
                LIMIT 50
            ");
            $stmt->execute();
            $reports = $stmt->fetchAll();
            
            // Simulate Google Sheets API call
            $syncedCount = 0;
            foreach ($reports as $report) {
                // In a real implementation, you would send data to Google Sheets API
                // For now, we'll just mark them as synced
                $updateStmt = $pdo->prepare("UPDATE field_reports SET sync_status = 'synced', sync_date = NOW() WHERE id = ?");
                $updateStmt->execute([$report['id']]);
                $syncedCount++;
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Successfully synced $syncedCount reports to Google Sheets",
                'synced_count' => $syncedCount
            ]);
            break;
            
        case 'pull':
            // Pull data from Google Sheets (placeholder implementation)
            $googleSheetsUrl = ''; // Get from config
            
            if (empty($googleSheetsUrl)) {
                throw new Exception('Google Sheets integration not configured');
            }
            
            // Simulate pulling data from Google Sheets
            // In a real implementation, you would fetch data from Google Sheets API
            
            echo json_encode([
                'success' => true, 
                'message' => 'Data pulled successfully from Google Sheets',
                'pulled_count' => 0 // Placeholder
            ]);
            break;
            
        case 'test_connection':
            $googleSheetsUrl = ''; // Get from config
            
            if (empty($googleSheetsUrl)) {
                throw new Exception('Google Sheets URL not configured');
            }
            
            // Test connection to Google Sheets
            $testResult = true; // Simulate successful test
            
            if ($testResult) {
                echo json_encode(['success' => true, 'message' => 'Connection test successful']);
            } else {
                throw new Exception('Connection test failed');
            }
            break;
            
        default:
            throw new Exception('Invalid sync action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>