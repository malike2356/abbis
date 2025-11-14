<?php
/**
 * Handle Company Logo Upload
 * Merged and improved version - handles all logo upload scenarios
 */
// Suppress warnings/notices for cleaner JSON responses
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
ini_set('display_errors', '0');

// Prevent any output before JSON
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

try {
    $auth->requireAuth();
    $auth->requireRole(ROLE_ADMIN);
} catch (Exception $e) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    jsonResponse(['success' => false, 'message' => 'Authentication failed: ' . $e->getMessage()], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

// Clear output buffer
while (ob_get_level()) {
    ob_end_clean();
}

try {
    if (!isset($_FILES['company_logo']) || $_FILES['company_logo']['error'] !== UPLOAD_ERR_OK) {
        $errorCode = $_FILES['company_logo']['error'] ?? UPLOAD_ERR_NO_FILE;
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
        ];
        $errorMsg = $errorMessages[$errorCode] ?? 'Unknown upload error';
        jsonResponse(['success' => false, 'message' => 'Upload error: ' . $errorMsg], 400);
    }

    $file = $_FILES['company_logo'];
    $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB

    // Validate file size
    if ($file['size'] > $maxSize) {
        jsonResponse(['success' => false, 'message' => 'File size exceeds 2MB limit'], 400);
    }

    // Validate file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) {
        jsonResponse(['success' => false, 'message' => 'Unable to verify file type'], 500);
    }
    
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        jsonResponse(['success' => false, 'message' => 'Invalid file type. Only PNG, JPG, GIF, and SVG are allowed.'], 400);
    }

    // Create upload directory
    $uploadDir = __DIR__ . '/../uploads/logos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            jsonResponse(['success' => false, 'message' => 'Unable to create upload directory. Please check permissions.'], 500);
        }
    }
    
    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        jsonResponse(['success' => false, 'message' => 'Upload directory is not writable. Please check permissions.'], 500);
    }

    // Delete old logo if exists
    $pdo = getDBConnection();
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_logo'");
        $stmt->execute();
        $oldLogo = $stmt->fetch();
        if ($oldLogo && !empty($oldLogo['config_value'])) {
            $oldPath = __DIR__ . '/../uploads/logos/' . basename($oldLogo['config_value']);
            if (file_exists($oldPath) && is_writable($oldPath)) {
                @unlink($oldPath);
            }
        }
    } catch (PDOException $e) {
        // Ignore if config table doesn't exist - log but continue
        error_log('Error deleting old logo: ' . $e->getMessage());
    }

    // Generate unique filename
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'company_logo_' . time() . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        $lastError = error_get_last();
        $errorMsg = 'Failed to save uploaded file.';
        if ($lastError) {
            $errorMsg .= ' Error: ' . $lastError['message'];
        }
        jsonResponse(['success' => false, 'message' => $errorMsg . ' Please check directory permissions.'], 500);
    }

    // Set file permissions
    @chmod($filePath, 0644);

    // Save logo path to database
    $relativePath = 'uploads/logos/' . $filename;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value, config_type, description) 
            VALUES ('company_logo', ?, 'string', 'Company logo file path')
            ON DUPLICATE KEY UPDATE config_value = ?
        ");
        $stmt->execute([$relativePath, $relativePath]);

        // Create favicon copy (if PNG/JPG)
        if (in_array($extension, ['png', 'jpg', 'jpeg'])) {
            $faviconDir = __DIR__ . '/../assets/images/';
            if (!is_dir($faviconDir)) {
                @mkdir($faviconDir, 0755, true);
            }
            if (is_dir($faviconDir) && is_writable($faviconDir)) {
                $faviconPath = $faviconDir . 'favicon.' . $extension;
                if (copy($filePath, $faviconPath)) {
                    @chmod($faviconPath, 0644);
                }
            }
        }

        while (ob_get_level()) {
            ob_end_clean();
        }
        
        jsonResponse([
            'success' => true, 
            'message' => 'Logo uploaded successfully',
            'logo_url' => '../' . $relativePath . '?t=' . time()
        ]);
        
    } catch (PDOException $e) {
        error_log('Logo upload DB error: ' . $e->getMessage());
        // File was uploaded but database save failed - clean up
        @unlink($filePath);
        jsonResponse(['success' => false, 'message' => 'Database error: Failed to save logo path. ' . $e->getMessage()], 500);
    }
    
} catch (Exception $e) {
    error_log('Logo upload error: ' . $e->getMessage());
    while (ob_get_level()) {
        ob_end_clean();
    }
    jsonResponse(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
}

