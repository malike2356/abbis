<?php
/**
 * Company Information Update API
 * Clean, simple, and reliable
 */

// Clear ALL output buffers
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Start fresh
ob_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '0');

// Set JSON header FIRST
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Load dependencies
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

// Check method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Authenticate
try {
    $auth->requireAuth();
    $auth->requireRole(ROLE_ADMIN);
} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Validate CSRF
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    ob_end_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

// Clear output
ob_end_clean();

// Get database connection
try {
    $pdo = getDBConnection();
    
    if (!$pdo) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Define fields
    $fields = [
        'company_name' => $_POST['config_company_name'] ?? '',
        'company_tagline' => $_POST['config_company_tagline'] ?? '',
        'company_address' => $_POST['config_company_address'] ?? '',
        'company_contact' => $_POST['config_company_contact'] ?? '',
        'company_email' => $_POST['config_company_email'] ?? '',
        'currency' => $_POST['config_currency'] ?? '',
        'receipt_email_subject' => $_POST['receipt_email_subject'] ?? '',
        'receipt_footer' => $_POST['receipt_footer'] ?? '',
        'receipt_terms' => $_POST['receipt_terms'] ?? '',
        'receipt_show_qr' => isset($_POST['receipt_show_qr']) && $_POST['receipt_show_qr'] == '1' ? '1' : '0',
        'company_phone' => $_POST['config_company_contact'] ?? '', // Alias for company_contact
    ];
    
    // Update each field
    $updated = 0;
    $errors = [];
    
    foreach ($fields as $key => $value) {
        if ($value !== '') {
            try {
                $value = trim($value);
                $stmt = $pdo->prepare("
                    INSERT INTO system_config (config_key, config_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE config_value = ?
                ");
                
                if ($stmt->execute([$key, $value, $value])) {
                    $updated++;
                }
            } catch (PDOException $e) {
                $errors[] = $key;
                error_log("Error updating $key: " . $e->getMessage());
            }
        }
    }
    
    // Prepare response
    if ($updated > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Company information updated successfully ($updated field(s))",
            'updated' => $updated
        ]);
    } else if (!empty($errors)) {
        echo json_encode([
            'success' => false,
            'message' => 'Some fields failed to update',
            'errors' => $errors
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Company update error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

exit;
