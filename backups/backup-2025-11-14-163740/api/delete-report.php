<?php
/**
 * Delete Report API
 * Deletes a field report from the database
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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$reportId = intval($input['id'] ?? 0);

if (!$reportId) {
    jsonResponse(['success' => false, 'message' => 'Report ID required'], 400);
}

try {
    $pdo = getDBConnection();
    
    // First, check if report exists
    $stmt = $pdo->prepare("SELECT id, report_id FROM field_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$report) {
        jsonResponse(['success' => false, 'message' => 'Report not found'], 404);
    }
    
    // Delete the report
    $stmt = $pdo->prepare("DELETE FROM field_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    
    // Check if deletion was successful
    if ($stmt->rowCount() > 0) {
        jsonResponse([
            'success' => true,
            'message' => 'Report deleted successfully'
        ]);
    } else {
        jsonResponse(['success' => false, 'message' => 'Failed to delete report'], 500);
    }
    
} catch (PDOException $e) {
    error_log("Error deleting report: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Error deleting report: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred'], 500);
}
