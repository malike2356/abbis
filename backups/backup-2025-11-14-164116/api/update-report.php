<?php
/**
 * Update Report API
 * Updates field report data in the database
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
    
    // Check if report exists
    $stmt = $pdo->prepare("SELECT id FROM field_reports WHERE id = ?");
    $stmt->execute([$reportId]);
    if (!$stmt->fetch()) {
        jsonResponse(['success' => false, 'message' => 'Report not found'], 404);
    }
    
    // Fields that can be updated (text fields primarily)
    $updatableFields = [
        'site_name',
        'location_description',
        'region',
        'supervisor',
        'remarks',
        'incident_log',
        'solution_log',
        'recommendation_log'
    ];
    
    $updates = [];
    $params = [];
    
    foreach ($updatableFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "`$field` = ?";
            $params[] = sanitizeInput($input[$field]);
        }
    }
    
    if (empty($updates)) {
        jsonResponse(['success' => false, 'message' => 'No valid fields to update'], 400);
    }
    
    // Add updated_at timestamp
    $updates[] = "`updated_at` = NOW()";
    
    // Add report ID to params
    $params[] = $reportId;
    
    // Build and execute update query
    $sql = "UPDATE field_reports SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    jsonResponse([
        'success' => true,
        'message' => 'Report updated successfully'
    ]);
    
} catch (PDOException $e) {
    error_log("Error updating report: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Database error occurred'], 500);
} catch (Exception $e) {
    error_log("Error updating report: " . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'An error occurred'], 500);
}
