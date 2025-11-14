<?php
/**
 * Get Report Details API
 * Returns detailed report information in JSON format
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
try {
    $auth->requireAuth();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'message' => 'Authentication required: ' . $e->getMessage()], 401);
}

header('Content-Type: application/json');

$reportId = intval($_GET['id'] ?? 0);

if (!$reportId) {
    jsonResponse(['success' => false, 'message' => 'Report ID required'], 400);
}

try {
    $pdo = getDBConnection();
    
    // Fetch report with all related data
    $stmt = $pdo->prepare("
        SELECT 
            fr.*,
            r.rig_name,
            r.rig_code,
            c.client_name,
            c.contact_person,
            c.contact_number,
            c.email,
            c.address
        FROM field_reports fr
        LEFT JOIN rigs r ON fr.rig_id = r.id
        LEFT JOIN clients c ON fr.client_id = c.id
        WHERE fr.id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        jsonResponse(['success' => false, 'message' => 'Report not found'], 404);
    }
    
    jsonResponse([
        'success' => true,
        'report' => $report
    ]);
    
} catch (PDOException $e) {
    error_log("Error fetching report details: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Error fetching report details: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred'], 500);
}
