<?php
/**
 * Get Configuration Data for Forms (Rigs, Workers, Materials, etc.)
 * Returns JSON for dynamic form population
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/config-manager.php';
require_once '../includes/helpers.php';

$auth->requireAuth();

header('Content-Type: application/json');

try {
    $type = $_GET['type'] ?? 'all';
    
    $data = [];
    
    switch ($type) {
        case 'rigs':
            $data['rigs'] = $configManager->getRigs('active');
            break;
            
        case 'workers':
            $data['workers'] = $configManager->getWorkers('active');
            $data['roles'] = $configManager->getWorkerRoles();
            break;
            
        case 'materials':
            $data['materials'] = $configManager->getMaterials();
            break;
            
        case 'rod_lengths':
            $data['rod_lengths'] = $configManager->getRodLengths();
            break;
            
        case 'clients':
            $pdo = getDBConnection();
            $data['clients'] = $pdo->query("SELECT * FROM clients ORDER BY client_name")->fetchAll();
            break;
            
        case 'all':
        default:
            $data['rigs'] = $configManager->getRigs('active');
            $data['workers'] = $configManager->getWorkers('active');
            $data['roles'] = $configManager->getWorkerRoles();
            $data['materials'] = $configManager->getMaterials();
            $data['rod_lengths'] = $configManager->getRodLengths();
            $pdo = getDBConnection();
            $data['clients'] = $pdo->query("SELECT * FROM clients ORDER BY client_name")->fetchAll();
            break;
    }
    
    jsonResponse(['success' => true, 'data' => $data]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
}
?>

