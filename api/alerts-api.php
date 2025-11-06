<?php
/**
 * Smart Alerts API
 * Provides alert data for dashboard and notifications
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';

$auth->requireAuth();

header('Content-Type: application/json');

$pdo = getDBConnection();
$alerts = [];

// Debt Recovery Alerts - REMOVED: Already shown in dedicated alert cards on dashboard
// This prevents duplication with the alert cards section

try {
    // Maintenance Due Alerts
    $maintenanceAlerts = $pdo->query("
        SELECT 
            COUNT(*) as count,
            SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
            SUM(CASE WHEN scheduled_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND scheduled_date >= CURDATE() THEN 1 ELSE 0 END) as due_soon_count
        FROM maintenance_records
        WHERE status IN ('scheduled', 'logged') AND scheduled_date IS NOT NULL
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($maintenanceAlerts['count'] > 0) {
        $alerts[] = [
            'type' => 'maintenance',
            'priority' => $maintenanceAlerts['urgent_count'] > 0 ? 'high' : 'medium',
            'title' => 'Maintenance Due',
            'message' => "{$maintenanceAlerts['count']} maintenance tasks scheduled",
            'count' => $maintenanceAlerts['count'],
            'urgent' => $maintenanceAlerts['urgent_count'],
            'due_soon' => $maintenanceAlerts['due_soon_count'],
            'url' => 'resources.php?action=maintenance',
            'icon' => '🔧'
        ];
    }
} catch (PDOException $e) {
    // Table might not exist
}

try {
    // Low Materials Inventory Alerts
    $lowInventory = $pdo->query("
        SELECT 
            COUNT(*) as count,
            SUM(CASE WHEN quantity_remaining < 10 THEN 1 ELSE 0 END) as critical_count
        FROM materials_inventory
        WHERE quantity_remaining < 50 AND status = 'active'
    ")->fetch(PDO::FETCH_ASSOC);
    
    if ($lowInventory['count'] > 0) {
        $alerts[] = [
            'type' => 'inventory',
            'priority' => $lowInventory['critical_count'] > 0 ? 'high' : 'medium',
            'title' => 'Low Inventory',
            'message' => "{$lowInventory['count']} materials running low",
            'count' => $lowInventory['count'],
            'critical' => $lowInventory['critical_count'],
            'url' => 'resources.php?action=materials',
            'icon' => '📦'
        ];
    }
} catch (PDOException $e) {
    // Table might not exist
}

// Unpaid Rig Fees - REMOVED: Already shown in dedicated alert cards on dashboard
// This prevents duplication with the alert cards section

// Sort by priority
usort($alerts, function($a, $b) {
    $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
    return ($priorityOrder[$b['priority']] ?? 0) - ($priorityOrder[$a['priority']] ?? 0);
});

echo json_encode([
    'success' => true,
    'alerts' => $alerts,
    'count' => count($alerts)
]);

