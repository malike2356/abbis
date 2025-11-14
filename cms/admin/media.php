<?php
/**
 * CMS Admin - Media Library (WordPress-style)
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/constants.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/cms/includes/media-helper.php';
require_once $rootPath . '/cms/includes/image-resizer.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$user = $cmsAuth->getCurrentUser();

// Get base URL
$baseUrl = app_url();

$message = null;
$messageType = null;


// Handle media scan (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_media_ajax'])) {
    header('Content-Type: application/json');
    
    // Suppress errors for clean JSON output
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Enable output buffering for progress
    ob_start();
    
    try {
        $result = scanAndRegisterMedia($rootPath, true); // Pass true for verbose output
        
        $output = ob_get_clean();
        
        // Ensure arrays are properly formatted
        $response = [
            'success' => true,
            'registered' => (int)($result['registered'] ?? 0),
            'skipped' => (int)($result['skipped'] ?? 0),
            'errors' => is_array($result['errors'] ?? null) ? $result['errors'] : [],
            'scanned_paths' => is_array($result['scanned_paths'] ?? null) ? $result['scanned_paths'] : [],
            'found_files' => is_array($result['found_files'] ?? null) ? $result['found_files'] : [],
            'total_found' => (int)($result['total_found'] ?? count($result['found_files'] ?? [])),
            'output' => $output ?: ''
        ];
        
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'registered' => 0,
            'skipped' => 0,
            'errors' => [$e->getMessage()],
            'scanned_paths' => [],
            'found_files' => [],
            'total_found' => 0,
            'output' => 'Error: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Handle media scan (regular POST - fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scan_media'])) {
    $result = scanAndRegisterMedia($rootPath);
    $message = "Scan complete! Found and registered {$result['registered']} new file(s), skipped {$result['skipped']} existing file(s).";
    if (!empty($result['errors'])) {
        $message .= " " . count($result['errors']) . " error(s) occurred.";
    }
    if (isset($result['scanned_paths']) && !empty($result['scanned_paths'])) {
        $message .= " Scanned " . count($result['scanned_paths']) . " directory(ies).";
    }
    $messageType = 'success';
    $_SESSION['media_message'] = $message;
    $_SESSION['media_message_type'] = $messageType;
    header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
    exit;
}


// Handle file upload with image processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_media'])) {
    // Check if files were actually uploaded
    if (!isset($_FILES['media_files']) || empty($_FILES['media_files']['name'][0])) {
        $_SESSION['media_message'] = 'No files selected for upload. Please select at least one file.';
        $_SESSION['media_message_type'] = 'error';
        header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
        exit;
    }
    
    if (isset($_FILES['media_files']) && !empty($_FILES['media_files']['name'][0])) {
        $uploadDir = $rootPath . '/uploads/media/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Initialize image processor
        $imageResizer = new ImageResizer($rootPath, $pdo);
        
        $uploadedCount = 0;
        $processedCount = 0;
        $errors = [];
        
        foreach ($_FILES['media_files']['name'] as $key => $filename) {
            // Check upload error
            $uploadError = $_FILES['media_files']['error'][$key] ?? UPLOAD_ERR_NO_FILE;
            
            if ($uploadError === UPLOAD_ERR_OK) {
                $file = [
                    'name' => $_FILES['media_files']['name'][$key],
                    'type' => $_FILES['media_files']['type'][$key],
                    'tmp_name' => $_FILES['media_files']['tmp_name'][$key],
                    'size' => $_FILES['media_files']['size'][$key]
                ];
                
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3'];
                
                if (in_array($ext, $allowed) && $file['size'] <= 10000000) { // 10MB
                    // Check if it's an image
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    
                    if ($isImage && $imageResizer->isAvailable()) {
                        // Process image through image engine
                        $result = $imageResizer->processAndRegister(
                            $file['tmp_name'],
                            $file['name'],
                            $file['type'],
                            $file['size'],
                            $user['id']
                        );
                        
                        if ($result['success']) {
                            $uploadedCount++;
                            if (!empty($result['sizes'])) {
                                $processedCount++;
                            }
                        } else {
                            $errors[] = $file['name'] . ': ' . ($result['error'] ?? 'Processing failed');
                        }
                    } else {
                        // Non-image file or GD not available - use standard upload
                        $uniqueFilename = uniqid() . '_' . time() . '.' . $ext;
                        $filepath = $uploadDir . $uniqueFilename;
                        
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            // Save to database - use registerMediaFile to ensure proper duplicate checking
                            try {
                                $relativePath = 'uploads/media/' . $uniqueFilename;
                                if (registerMediaFile($relativePath, $file['name'], $file['type'], $file['size'], $user['id'])) {
                                    $uploadedCount++;
                                } else {
                                    // File might already exist, but upload was successful
                                    $uploadedCount++;
                                }
                            } catch (PDOException $e) {
                                // Only delete file if it's a real error (not a duplicate)
                                if (strpos($e->getMessage(), 'Duplicate') === false && strpos($e->getMessage(), 'UNIQUE') === false) {
                                    if (file_exists($filepath)) {
                                        @unlink($filepath);
                                    }
                                    $errors[] = $file['name'] . ': Database error - ' . $e->getMessage();
                                } else {
                                    // Duplicate entry - file is already registered, that's okay
                                    $uploadedCount++;
                                }
                            }
                        } else {
                            $errors[] = $file['name'] . ': Upload failed - could not move file';
                        }
                    }
                } else {
                    $errors[] = $file['name'] . ': Invalid file type or size too large';
                }
            } else {
                // Handle upload errors
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                ];
                $errorMsg = $errorMessages[$uploadError] ?? 'Unknown upload error (' . $uploadError . ')';
                $errors[] = $file['name'] . ': ' . $errorMsg;
            }
        }
        
        if ($uploadedCount > 0) {
            $message = "✅ Successfully uploaded {$uploadedCount} file(s)";
            if ($processedCount > 0) {
                $message .= " ({$processedCount} image(s) processed and optimized)";
            }
            if (!empty($errors)) {
                $message .= ". " . count($errors) . " file(s) failed to upload.";
            }
            $_SESSION['media_message'] = $message;
            $_SESSION['media_message_type'] = 'success';
        } else {
            $errorMsg = !empty($errors) ? implode(', ', array_slice($errors, 0, 3)) : 'No files were uploaded';
            if (count($errors) > 3) {
                $errorMsg .= ' and ' . (count($errors) - 3) . ' more';
            }
            $_SESSION['media_message'] = '❌ Upload failed: ' . $errorMsg;
            $_SESSION['media_message_type'] = 'error';
        }
        header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
        exit;
    }
}

// Handle regenerate image sizes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regenerate_sizes'])) {
    header('Content-Type: application/json');
    
    $mediaId = intval($_POST['media_id'] ?? 0);
    if (!$mediaId) {
        echo json_encode(['success' => false, 'error' => 'Media ID required']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT id, file_path FROM cms_media WHERE id = ?");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$media) {
            echo json_encode(['success' => false, 'error' => 'Media not found']);
            exit;
        }
        
        $fullPath = $rootPath . '/' . $media['file_path'];
        if (!file_exists($fullPath)) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        // Check if it's an image
        $imageInfo = @getimagesize($fullPath);
        if (!$imageInfo) {
            echo json_encode(['success' => false, 'error' => 'File is not an image']);
            exit;
        }
        
        // Initialize image processor
        $imageResizer = new ImageResizer($rootPath, $pdo);
        
        if (!$imageResizer->isAvailable()) {
            echo json_encode(['success' => false, 'error' => 'Image processing not available']);
            exit;
        }
        
        // Generate sizes
        $sizes = $imageResizer->generateSizes($media['file_path'], $mediaId);
        
        echo json_encode([
            'success' => true,
            'sizes' => $sizes,
            'message' => 'Image sizes regenerated successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle bulk regenerate sizes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_regenerate_sizes'])) {
    $mediaIdsStr = $_POST['media_ids'] ?? '';
    $mediaIds = !empty($mediaIdsStr) ? explode(',', $mediaIdsStr) : [];
    $processedCount = 0;
    $errors = [];
    
    $imageResizer = new ImageResizer($rootPath, $pdo);
    
    foreach ($mediaIds as $mediaId) {
        $mediaId = intval(trim($mediaId));
        if ($mediaId <= 0) continue;
        
        try {
            $stmt = $pdo->prepare("SELECT id, file_path FROM cms_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($media) {
                $fullPath = $rootPath . '/' . $media['file_path'];
                if (file_exists($fullPath)) {
                    $imageInfo = @getimagesize($fullPath);
                    if ($imageInfo && $imageResizer->isAvailable()) {
                        $imageResizer->generateSizes($media['file_path'], $mediaId);
                        $processedCount++;
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = "Media ID {$mediaId}: " . $e->getMessage();
        }
    }
    
    $_SESSION['media_message'] = "Regenerated sizes for {$processedCount} image(s)";
    if (!empty($errors)) {
        $_SESSION['media_message'] .= '. Errors: ' . implode(', ', $errors);
    }
    $_SESSION['media_message_type'] = 'success';
    header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
    exit;
}

// Handle file deletion (also delete generated sizes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_media'])) {
    $mediaId = intval($_POST['media_id']);
    try {
        $stmt = $pdo->prepare("SELECT file_path FROM cms_media WHERE id = ?");
        $stmt->execute([$mediaId]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($media) {
            $filepath = $rootPath . '/' . $media['file_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            
            // Delete generated sizes
            try {
                $sizeStmt = $pdo->prepare("SELECT file_path FROM cms_media_sizes WHERE media_id = ?");
                $sizeStmt->execute([$mediaId]);
                while ($size = $sizeStmt->fetch(PDO::FETCH_ASSOC)) {
                    $sizePath = $rootPath . '/' . $size['file_path'];
                    if (file_exists($sizePath)) {
                        unlink($sizePath);
                    }
                }
                // Delete size records
                $pdo->prepare("DELETE FROM cms_media_sizes WHERE media_id = ?")->execute([$mediaId]);
            } catch (PDOException $e) {
                // Size table might not exist, that's okay
            }
            
            $stmt = $pdo->prepare("DELETE FROM cms_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $_SESSION['media_message'] = 'File deleted successfully';
            $_SESSION['media_message_type'] = 'success';
        }
    } catch (PDOException $e) {
        $_SESSION['media_message'] = 'Error deleting file';
        $_SESSION['media_message_type'] = 'error';
    }
    header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
    exit;
}

// Handle bulk delete (also delete generated sizes)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'])) {
    $mediaIdsStr = $_POST['media_ids'] ?? '';
    $mediaIds = !empty($mediaIdsStr) ? explode(',', $mediaIdsStr) : [];
    $deletedCount = 0;
    
    foreach ($mediaIds as $mediaId) {
        $mediaId = intval(trim($mediaId));
        if ($mediaId <= 0) continue;
        
        try {
            $stmt = $pdo->prepare("SELECT file_path FROM cms_media WHERE id = ?");
            $stmt->execute([$mediaId]);
            $media = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($media) {
                $filepath = $rootPath . '/' . $media['file_path'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Delete generated sizes
                try {
                    $sizeStmt = $pdo->prepare("SELECT file_path FROM cms_media_sizes WHERE media_id = ?");
                    $sizeStmt->execute([$mediaId]);
                    while ($size = $sizeStmt->fetch(PDO::FETCH_ASSOC)) {
                        $sizePath = $rootPath . '/' . $size['file_path'];
                        if (file_exists($sizePath)) {
                            unlink($sizePath);
                        }
                    }
                    $pdo->prepare("DELETE FROM cms_media_sizes WHERE media_id = ?")->execute([$mediaId]);
                } catch (PDOException $e) {
                    // Size table might not exist
                }
                
                $stmt = $pdo->prepare("DELETE FROM cms_media WHERE id = ?");
                $stmt->execute([$mediaId]);
                $deletedCount++;
            }
        } catch (PDOException $e) {
            // Continue with next file
        }
    }
    
    $_SESSION['media_message'] = "Successfully deleted {$deletedCount} file(s)";
    $_SESSION['media_message_type'] = 'success';
    header('Location: media.php' . ($_GET['type'] ? '?type=' . urlencode($_GET['type']) : ''));
    exit;
}

// Create media table if it doesn't exist and ensure all columns exist
try {
    $pdo->query("SELECT 1 FROM cms_media LIMIT 1");
    
    // Check and add missing columns (especially original_name which is required)
    $columnsToCheck = [
        'original_name' => "VARCHAR(255) DEFAULT NULL",
    ];
    
    // Get existing columns
    $existingColumns = [];
    try {
        $colsStmt = $pdo->query("SHOW COLUMNS FROM cms_media");
        while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $col['Field'];
        }
    } catch (PDOException $e) {
        // Table might not exist, will be created below
    }
    
    // Add missing columns
    foreach ($columnsToCheck as $column => $definition) {
        if (!in_array($column, $existingColumns)) {
            try {
                // Try to add after filename if it exists, otherwise just add
                if (in_array('filename', $existingColumns)) {
                    $pdo->exec("ALTER TABLE cms_media ADD COLUMN {$column} {$definition} AFTER filename");
                } else {
                    $pdo->exec("ALTER TABLE cms_media ADD COLUMN {$column} {$definition}");
                }
            } catch (PDOException $e2) {
                // Column might exist or there's a structure issue
                error_log("Failed to add column {$column}: " . $e2->getMessage());
            }
        }
    }
    
    // Check if unique constraint exists, add it if not
    try {
        $pdo->query("SELECT file_path FROM cms_media WHERE file_path = 'test' LIMIT 1");
    } catch (PDOException $e) {
        // Try to add unique constraint if it doesn't exist
        try {
            $pdo->exec("ALTER TABLE cms_media ADD UNIQUE KEY unique_file_path (file_path)");
        } catch (PDOException $e2) {
            // Constraint might already exist or table structure is different
        }
    }
} catch (PDOException $e) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            uploaded_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_uploaded_by (uploaded_by),
            INDEX idx_file_type (file_type),
            INDEX idx_created_at (created_at),
            UNIQUE KEY unique_file_path (file_path)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (PDOException $e2) {
        // Table creation failed, but continue anyway
    }
}

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where = [];
$params = [];

if ($filterType !== 'all') {
    if ($filterType === 'image') {
        $where[] = "file_type LIKE ?";
        $params[] = 'image/%';
    } elseif ($filterType === 'document') {
        $where[] = "(file_type LIKE ? OR file_type LIKE ? OR file_type LIKE ?)";
        $params[] = 'application/pdf';
        $params[] = 'application/msword';
        $params[] = 'application/vnd.openxmlformats-officedocument%';
    } elseif ($filterType === 'video') {
        $where[] = "file_type LIKE ?";
        $params[] = 'video/%';
    } elseif ($filterType === 'audio') {
        $where[] = "file_type LIKE ?";
        $params[] = 'audio/%';
    }
}

if (!empty($search)) {
    $where[] = "(original_name LIKE ? OR filename LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// Get total count for pagination
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_media m {$whereClause}");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn() ?: 0;
    $totalPages = ceil($totalCount / $perPage);
} catch (PDOException $e) {
    $totalCount = 0;
    $totalPages = 0;
}

// Get media files with thumbnail information
try {
    $stmt = $pdo->prepare("SELECT m.*, u.username as uploaded_by_name FROM cms_media m LEFT JOIN cms_users u ON m.uploaded_by = u.id {$whereClause} ORDER BY m.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $mediaFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get thumbnails for images
    $imageResizer = new ImageResizer($rootPath, $pdo);
    foreach ($mediaFiles as &$file) {
        $fileType = !empty($file['file_type']) ? $file['file_type'] : '';
        $isImage = strpos($fileType, 'image/') === 0;
        
        if ($isImage && !empty($file['file_path'])) {
            // Check if thumbnail exists
            try {
                $thumbStmt = $pdo->prepare("SELECT file_path FROM cms_media_sizes WHERE media_id = ? AND size_name = 'thumbnail' LIMIT 1");
                $thumbStmt->execute([$file['id']]);
                $thumb = $thumbStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($thumb && !empty($thumb['file_path'])) {
                    $file['thumbnail_path'] = $thumb['file_path'];
                } else {
                    // Try to get thumbnail URL using image resizer
                    $file['thumbnail_path'] = null;
                }
            } catch (PDOException $e) {
                $file['thumbnail_path'] = null;
            }
        } else {
            $file['thumbnail_path'] = null;
        }
    }
    unset($file); // Break reference
} catch (PDOException $e) {
    $mediaFiles = [];
}

// Get stats
try {
    $totalFiles = $pdo->query("SELECT COUNT(*) FROM cms_media")->fetchColumn() ?: 0;
    $totalSize = $pdo->query("SELECT COALESCE(SUM(file_size), 0) FROM cms_media")->fetchColumn() ?: 0;
    $imageCount = $pdo->query("SELECT COUNT(*) FROM cms_media WHERE file_type LIKE 'image/%'")->fetchColumn() ?: 0;
    $documentCount = $pdo->query("SELECT COUNT(*) FROM cms_media WHERE file_type LIKE 'application/%' OR file_type LIKE 'text/%'")->fetchColumn() ?: 0;
    $videoCount = $pdo->query("SELECT COUNT(*) FROM cms_media WHERE file_type LIKE 'video/%'")->fetchColumn() ?: 0;
    $audioCount = $pdo->query("SELECT COUNT(*) FROM cms_media WHERE file_type LIKE 'audio/%'")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $totalFiles = 0;
    $totalSize = 0;
    $imageCount = 0;
    $documentCount = 0;
    $videoCount = 0;
    $audioCount = 0;
}

// Check for flash message
$message = null;
$messageType = null;
if (isset($_SESSION['media_message'])) {
    $message = $_SESSION['media_message'];
    $messageType = $_SESSION['media_message_type'] ?? 'success';
    unset($_SESSION['media_message']);
    unset($_SESSION['media_message_type']);
}

// Get company name
$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Library - <?php echo htmlspecialchars($companyName); ?> CMS Admin</title>
    <style>
        /* Enhanced Media Library Styles */
        .media-item {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 0;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            cursor: pointer;
            box-shadow: 0 10px 32px rgba(15, 23, 42, 0.04);
        }
        .media-item:hover {
            transform: translateY(-6px);
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 18px 44px rgba(37, 99, 235, 0.18);
        }
        .media-item.selected {
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 0 0 3px var(--admin-primary-lighter, rgba(37, 99, 235, 0.22));
        }
        .media-item.selected::after {
            content: '✓';
            position: absolute;
            top: 8px;
            right: 8px;
            background: var(--admin-primary, #2563eb);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 16px;
            z-index: 10;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .media-thumbnail-container {
            position: relative;
            width: 100%;
            aspect-ratio: 4 / 3;
            max-height: 180px;
            overflow: hidden;
            background: #f6f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .media-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            background: #f6f7f7;
            display: block;
        }
        .media-thumbnail.loading {
            opacity: 0.5;
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        .media-item:hover .media-thumbnail {
            transform: scale(1.05);
        }
        .media-thumbnail-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            background: linear-gradient(135deg, #f6f7f7 0%, #e5e7eb 100%);
            color: #9ca3af;
        }
        .media-thumbnail-placeholder.has-icon {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }
        .media-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .media-item:hover .media-overlay {
            opacity: 1;
        }
        .media-overlay-btn {
            padding: 8px 16px;
            background: white;
            color: #1d2327;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .media-overlay-btn:hover {
            background: var(--admin-primary, #2563eb);
            color: white;
            transform: scale(1.05);
        }
        .media-info {
            padding: 14px 16px 12px;
        }
        .media-name {
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 8px;
            word-break: break-word;
            font-size: 12px;
            line-height: 1.35;
        }
        .media-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #64748b;
            margin-top: 8px;
        }
        .media-actions-bar {
            display: flex;
            gap: 4px;
            padding: 8px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
        }
        .media-action-btn {
            flex: 1;
            padding: 6px 8px;
            font-size: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        .media-action-btn-view {
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
            color: var(--admin-primary, #2563eb);
        }
        .media-action-btn-view:hover {
            background: var(--admin-primary, #2563eb);
            color: white;
        }
        .media-action-btn-copy {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        .media-action-btn-copy:hover {
            background: #10b981;
            color: white;
        }
        .media-action-btn-delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .media-action-btn-delete:hover {
            background: #ef4444;
            color: white;
        }
        
        .upload-area {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 3px dashed rgba(255,255,255,0.5);
            border-radius: 16px;
            padding: 50px 40px;
            text-align: center;
            margin-bottom: 24px;
            transition: all 0.3s ease;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .upload-area::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 300"><rect fill="%23ffffff" opacity="0.05" width="400" height="300"/></svg>');
        }
        .upload-area:hover {
            border-color: rgba(255,255,255,0.8);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
        }
        .upload-area.dragover {
            border-color: white;
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: scale(1.02);
        }
        .upload-icon {
            font-size: 64px;
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }
        .upload-area h3 {
            color: white;
            margin-bottom: 8px;
            font-size: 20px;
            position: relative;
            z-index: 1;
        }
        .upload-area p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 20px;
            font-size: 14px;
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .stat-card.active {
            border-color: var(--admin-primary, #2563eb);
            border-width: 2px;
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.05));
        }
        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--admin-primary, #2563eb);
            margin: 8px 0;
        }
        .stat-card-label {
            font-size: 12px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .filter-bar {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-tab {
            padding: 8px 16px;
            border: 2px solid transparent;
            border-radius: 8px;
            background: #f6f7f7;
            color: #646970;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .filter-tab:hover {
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
            color: var(--admin-primary, #2563eb);
        }
        .filter-tab.active {
            background: var(--admin-primary, #2563eb);
            color: white;
            border-color: var(--admin-primary, #2563eb);
        }
        .search-input-group {
            flex: 1;
            display: flex;
            gap: 8px;
            min-width: 300px;
        }
        .search-input-group input {
            flex: 1;
            padding: 10px 16px;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            font-size: 14px;
        }
        .search-input-group input:focus {
            outline: none;
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 0 0 3px var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
        }
        
        .bulk-actions {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: none;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .bulk-actions.active {
            display: flex;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }
        
        .media-list {
            display: none;
            margin-top: 24px;
        }
        
        .media-list.active {
            display: block;
        }
        
        .media-grid.active {
            display: grid;
        }
        
        .media-grid:not(.active) {
            display: none;
        }
        
        /* List View Styles */
        .media-list-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .media-list-item:hover {
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .media-list-item.selected {
            border-color: var(--admin-primary, #2563eb);
            border-width: 2px;
            background: rgba(37, 99, 235, 0.05);
        }
        
        .media-list-thumbnail {
            width: 80px;
            height: 80px;
            flex-shrink: 0;
            border-radius: 6px;
            overflow: hidden;
            background: #f6f7f7;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .media-list-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .media-list-thumbnail .placeholder {
            font-size: 32px;
            color: #9ca3af;
        }
        
        .media-list-info {
            flex: 1;
            min-width: 0;
        }
        
        .media-list-name {
            font-weight: 600;
            color: #1d2327;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .media-list-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #646970;
        }
        
        .media-list-meta span {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .media-list-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .media-list-actions .admin-btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        /* View Toggle */
        .view-toggle {
            display: flex;
            gap: 4px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 6px;
            padding: 4px;
        }
        
        .view-btn {
            padding: 8px 16px;
            border: none;
            background: transparent;
            color: #646970;
            cursor: pointer;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .view-btn:hover {
            background: #f6f7f7;
            color: #1d2327;
        }
        
        .view-btn.active {
            background: var(--admin-primary, #2563eb);
            color: white;
        }
        
        .media-header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        @media (min-width: 1200px) {
            .media-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        @media (min-width: 1600px) {
            .media-grid {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .media-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .media-list-item {
                flex-wrap: wrap;
            }
            
            .media-list-thumbnail {
                width: 60px;
                height: 60px;
            }
            
            .media-list-meta {
                flex-direction: column;
                gap: 4px;
            }
        }
        
        /* WordPress-like image thumbnails - Square aspect ratio */
        .media-item[data-is-image="true"] .media-thumbnail-container {
            aspect-ratio: 1 / 1;
            height: auto;
            min-height: 120px;
        }
        
        .media-item[data-is-image="true"] .media-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }
        
        /* Better image quality */
        .media-thumbnail {
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }
        
        /* Image type indicator */
        .media-item[data-is-image="true"]::before {
            content: '🖼️';
            position: absolute;
            top: 8px;
            left: 8px;
            background: rgba(0,0,0,0.6);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            z-index: 5;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .media-item[data-is-image="true"]:hover::before {
            opacity: 1;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 16px;
            max-width: 90vw;
            max-height: 90vh;
            overflow: auto;
            position: relative;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 2px solid #f0f0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #1d2327;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #646970;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        .modal-close:hover {
            background: #f6f7f7;
            color: #1d2327;
        }
        .modal-body {
            padding: 24px;
        }
        .modal-image {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .modal-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .modal-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .modal-info-label {
            font-size: 12px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .modal-info-value {
            font-size: 14px;
            color: #1d2327;
            font-weight: 500;
            word-break: break-all;
        }
        .url-copy-box {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        .url-copy-box input {
            flex: 1;
            padding: 10px 12px;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 32px;
            padding: 20px;
        }
        .pagination a, .pagination span {
            padding: 10px 16px;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            text-decoration: none;
            color: #1d2327;
            font-weight: 600;
            transition: all 0.2s;
        }
        .pagination a:hover {
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.1));
            border-color: var(--admin-primary, #2563eb);
            color: var(--admin-primary, #2563eb);
        }
        .pagination .current {
            background: var(--admin-primary, #2563eb);
            color: white;
            border-color: var(--admin-primary, #2563eb);
        }
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            .upload-area {
                padding: 30px 20px !important;
            }
            .upload-area h3 {
                font-size: 18px !important;
            }
        }
        .upload-areas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 968px) {
            .upload-areas-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php 
    $currentPage = 'media';
    include 'header.php'; 
    ?>
</head>
<body>
    <?php include 'footer.php'; ?>
    <div class="wrap">
        <div class="admin-page-header">
            <h1>🖼️ Media Library</h1>
            <p>Upload, organize, and manage all your media files in one place.</p>
        </div>

        <?php if ($message): ?>
            <div class="admin-notice admin-notice-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>" id="uploadMessage" style="margin-bottom: 24px; animation: slideDown 0.3s ease-out;">
                <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
                <div class="admin-notice-content">
                    <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
            <script>
                // Auto-dismiss success messages after 5 seconds
                <?php if ($messageType === 'success'): ?>
                setTimeout(function() {
                    const msg = document.getElementById('uploadMessage');
                    if (msg) {
                        msg.style.opacity = '0';
                        msg.style.transition = 'opacity 0.5s ease-out';
                        setTimeout(function() {
                            msg.remove();
                        }, 500);
                    }
                }, 5000);
                <?php endif; ?>
            </script>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="?type=all" class="stat-card <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                <div class="stat-card-value"><?php echo number_format($totalFiles); ?></div>
                <div class="stat-card-label">Total Files</div>
            </a>
            <a href="?type=image" class="stat-card <?php echo $filterType === 'image' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #8b5cf6;"><?php echo number_format($imageCount); ?></div>
                <div class="stat-card-label">Images</div>
            </a>
            <a href="?type=document" class="stat-card <?php echo $filterType === 'document' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #f59e0b;"><?php echo number_format($documentCount); ?></div>
                <div class="stat-card-label">Documents</div>
            </a>
            <a href="?type=video" class="stat-card <?php echo $filterType === 'video' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #ef4444;"><?php echo number_format($videoCount); ?></div>
                <div class="stat-card-label">Videos</div>
            </a>
            <a href="?type=audio" class="stat-card <?php echo $filterType === 'audio' ? 'active' : ''; ?>">
                <div class="stat-card-value" style="color: #10b981;"><?php echo number_format($audioCount); ?></div>
                <div class="stat-card-label">Audio</div>
            </a>
            <div class="stat-card">
                <div class="stat-card-value" style="color: #64748b;"><?php echo number_format($totalSize / 1024 / 1024, 1); ?> MB</div>
                <div class="stat-card-label">Total Size</div>
            </div>
        </div>

        <!-- Upload Area -->
        <div style="margin-bottom: 24px;">
            <form method="post" enctype="multipart/form-data" id="uploadForm">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📤</div>
                    <h3>Drop files here or click to upload</h3>
                    <p>Supports: Images, PDFs, Documents, Videos, Audio (Max 10MB per file)</p>
                    <div style="position: relative; z-index: 1;">
                        <input type="file" name="media_files[]" id="mediaFiles" multiple accept="image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.*,video/*,audio/*" style="display: none;" onchange="handleFileSelect()">
                        <button type="button" class="admin-btn" style="background: white; color: #667eea; border: none; font-weight: 700; padding: 12px 24px; margin-right: 8px;" onclick="document.getElementById('mediaFiles').click()">Select Files</button>
                        <button type="submit" name="upload_media" id="uploadBtn" class="admin-btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; font-weight: 700; padding: 12px 24px; opacity: 0.5; cursor: not-allowed;" disabled>Upload Selected</button>
                    </div>
                    <div id="selectedFilesInfo" style="margin-top: 12px; font-size: 13px; color: rgba(255,255,255,0.9); display: none;">
                        <span id="selectedFilesCount">0</span> file(s) selected
                    </div>
                    <!-- Upload Progress -->
                    <div id="uploadProgress" style="display: none; margin-top: 16px; background: rgba(255,255,255,0.1); padding: 12px; border-radius: 8px;">
                        <div style="font-weight: 600; margin-bottom: 8px; color: white;">Uploading files...</div>
                        <div style="background: rgba(255,255,255,0.2); border-radius: 4px; height: 8px; overflow: hidden;">
                            <div id="uploadProgressBar" style="background: white; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                        <div id="uploadStatus" style="margin-top: 8px; font-size: 12px; color: rgba(255,255,255,0.9);"></div>
                    </div>
                </div>
            </form>
            
            <!-- Scan Media Area -->
            <div class="upload-area" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <div class="upload-icon">🔍</div>
                <h3>Auto-Scan System for Media Files</h3>
                <p>Automatically finds and registers all media files system-wide. Scans run automatically every 5 minutes or when you visit this page.</p>
                <div style="position: relative; z-index: 1;">
                    <button type="button" id="scanMediaBtn" class="admin-btn" style="background: white; color: #10b981; border: none; font-weight: 700; padding: 12px 24px; width: 100%;">
                        🔍 Scan Now (Auto-scan on page load)
                    </button>
                </div>
                <p style="font-size: 12px; margin-top: 12px; opacity: 0.9;">Scans: uploads/*, assets/images, cms/uploads, and all subdirectories</p>
                <p style="font-size: 11px; margin-top: 8px; opacity: 0.8; font-style: italic;">💡 Tip: The system automatically scans when you visit this page. All found images will be available system-wide.</p>
                
                <!-- Scan Progress -->
                <div id="scanProgress" style="display: none; margin-top: 20px; background: rgba(255,255,255,0.1); padding: 16px; border-radius: 8px; max-height: 300px; overflow-y: auto;">
                    <div style="font-weight: 600; margin-bottom: 12px; color: white;">Scanning in progress...</div>
                    <div id="scanOutput" style="font-family: monospace; font-size: 12px; color: rgba(255,255,255,0.9); line-height: 1.6;"></div>
                </div>
                
                <!-- Scan Results -->
                <div id="scanResults" style="display: none; margin-top: 20px; background: rgba(255,255,255,0.15); padding: 16px; border-radius: 8px; backdrop-filter: blur(10px);">
                    <div style="font-weight: 700; margin-bottom: 12px; color: white; font-size: 18px;">✓ Scan Complete!</div>
                    <div id="scanSummary" style="color: rgba(255,255,255,0.95); line-height: 1.8;"></div>
                </div>
            </div>
        </div>

        <!-- Bulk Actions Bar -->
        <div class="bulk-actions" id="bulkActions">
            <div style="display: flex; align-items: center; gap: 12px;">
                <strong id="selectedCount">0 files selected</strong>
            </div>
            <div style="display: flex; gap: 8px;">
                <form method="post" id="bulkRegenerateForm" style="display: inline;">
                    <input type="hidden" name="media_ids" id="bulkMediaIds">
                    <button type="submit" name="bulk_regenerate_sizes" class="admin-btn" style="background: #10b981; color: white;" onclick="return confirm('Regenerate image sizes for selected images?');">Regenerate Sizes</button>
                </form>
                <form method="post" id="bulkDeleteForm" onsubmit="return confirm('Are you sure you want to delete the selected files?');" style="display: inline;">
                    <input type="hidden" name="media_ids" id="bulkMediaIdsDelete">
                    <button type="submit" name="bulk_delete" class="admin-btn admin-btn-danger">Delete Selected</button>
                </form>
            </div>
        </div>

        <!-- Header with View Toggle -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding: 16px; background: white; border-radius: 8px; border: 1px solid #c3c4c7;">
            <div>
                <h2 style="margin: 0; font-size: 18px; color: #1d2327;">Media Files</h2>
                <p style="margin: 4px 0 0 0; font-size: 13px; color: #646970;"><?php echo number_format($totalCount); ?> file<?php echo $totalCount !== 1 ? 's' : ''; ?> found</p>
            </div>
            <div class="media-header-actions">
                <div class="view-toggle">
                    <button type="button" class="view-btn active" onclick="setView('grid')" data-view="grid" id="viewBtnGrid">🔲 Grid</button>
                    <button type="button" class="view-btn" onclick="setView('list')" data-view="list" id="viewBtnList">📋 List</button>
                </div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-tabs">
                <a href="?type=all<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterType === 'all' ? 'active' : ''; ?>">
                    <span>📋</span> All
                </a>
                <a href="?type=image<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterType === 'image' ? 'active' : ''; ?>">
                    <span>🖼️</span> Images
                </a>
                <a href="?type=document<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterType === 'document' ? 'active' : ''; ?>">
                    <span>📄</span> Documents
                </a>
                <a href="?type=video<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterType === 'video' ? 'active' : ''; ?>">
                    <span>🎥</span> Videos
                </a>
                <a href="?type=audio<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo $filterType === 'audio' ? 'active' : ''; ?>">
                    <span>🎵</span> Audio
                </a>
            </div>
            <form method="get" class="search-input-group">
                <input type="hidden" name="type" value="<?php echo htmlspecialchars($filterType); ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="🔍 Search files by name...">
                <button type="submit" class="admin-btn admin-btn-primary">Search</button>
                <?php if ($search): ?>
                    <a href="?type=<?php echo urlencode($filterType); ?>" class="admin-btn admin-btn-outline">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Media Grid -->
        <?php if (empty($mediaFiles)): ?>
            <div class="admin-empty-state">
                <div class="admin-empty-state-icon">📁</div>
                <h3>No media files found</h3>
                <p><?php echo !empty($search) ? 'Try adjusting your search criteria.' : 'Upload your first file to get started.'; ?></p>
            </div>
        <?php else: ?>
            <!-- Grid View -->
            <div class="media-grid active" id="mediaGridView">
                <?php foreach ($mediaFiles as $file): 
                    $originalName = !empty($file['original_name']) ? $file['original_name'] : (!empty($file['filename']) ? $file['filename'] : 'Unknown');
                    $fileType = !empty($file['file_type']) ? $file['file_type'] : '';
                    $fileSize = isset($file['file_size']) ? intval($file['file_size']) : 0;
                    $createdAt = !empty($file['created_at']) ? $file['created_at'] : date('Y-m-d H:i:s');
                    $filePath = !empty($file['file_path']) ? $file['file_path'] : '';
                    $fileId = isset($file['id']) ? intval($file['id']) : 0;
                    $isImage = !empty($fileType) && strpos($fileType, 'image/') === 0;
                    $fileUrl = $baseUrl . '/' . $filePath;
                    $fileIcon = '📎';
                    if (!empty($fileType)) {
                        if (strpos($fileType, 'pdf') !== false) $fileIcon = '📄';
                        elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false) $fileIcon = '📝';
                        elseif (strpos($fileType, 'excel') !== false || strpos($fileType, 'spreadsheet') !== false) $fileIcon = '📊';
                        elseif (strpos($fileType, 'video') !== false) $fileIcon = '🎥';
                        elseif (strpos($fileType, 'audio') !== false) $fileIcon = '🎵';
                        elseif (strpos($fileType, 'zip') !== false) $fileIcon = '📦';
                    }
                ?>
                    <div class="media-item" data-media-id="<?php echo $fileId; ?>" data-is-image="<?php echo $isImage ? 'true' : 'false'; ?>" onclick="toggleSelect(this, event)">
                        <div class="media-thumbnail-container">
                            <?php if ($isImage && !empty($filePath)): 
                                // Use thumbnail if available, otherwise use full image
                                $thumbnailUrl = null;
                                $fullImagePath = $rootPath . '/' . $filePath;
                                
                                if (!empty($file['thumbnail_path']) && file_exists($rootPath . '/' . $file['thumbnail_path'])) {
                                    // Use existing thumbnail from database
                                    $thumbnailUrl = $baseUrl . '/' . $file['thumbnail_path'];
                                } else {
                                    // Try to get or generate thumbnail
                                    try {
                                        $imageResizer = new ImageResizer($rootPath, $pdo);
                                        if ($imageResizer->isAvailable() && file_exists($fullImagePath)) {
                                            // Check if thumbnail exists in file system
                                            $thumbPath = $imageResizer->getImageUrl($filePath, 'thumbnail', '');
                                            $thumbPath = str_replace($baseUrl . '/', '', $thumbPath);
                                            $fullThumbPath = $rootPath . '/' . $thumbPath;
                                            
                                            if (file_exists($fullThumbPath)) {
                                                $thumbnailUrl = $baseUrl . '/' . $thumbPath;
                                            } else {
                                                // Try to generate thumbnail on-the-fly (async, but show full image for now)
                                                $thumbnailUrl = $fileUrl; // Use full image, thumbnail will be generated in background
                                            }
                                        } else {
                                            $thumbnailUrl = $fileUrl;
                                        }
                                    } catch (Exception $e) {
                                        $thumbnailUrl = $fileUrl;
                                    }
                                }
                                
                                // Fallback to full image if thumbnail not available
                                if (empty($thumbnailUrl)) {
                                    $thumbnailUrl = $fileUrl;
                                }
                            ?>
                                <img src="<?php echo htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>" 
                                     class="media-thumbnail loading"
                                     loading="lazy"
                                     data-full-url="<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                     data-media-id="<?php echo $fileId; ?>"
                                     onload="this.classList.remove('loading');"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                     onclick="event.stopPropagation(); showImageModal('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>');">
                                <div class="media-thumbnail-placeholder" style="display: none;">🖼️</div>
                            <?php else: ?>
                                <div class="media-thumbnail-placeholder has-icon"><?php echo $fileIcon; ?></div>
                            <?php endif; ?>
                            <div class="media-overlay">
                                <button type="button" class="media-overlay-btn" onclick="event.stopPropagation(); window.open('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank');">View</button>
                                <button type="button" class="media-overlay-btn" onclick="event.stopPropagation(); copyFileUrl('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>');">Copy URL</button>
                            </div>
                        </div>
                        <div class="media-info">
                            <div class="media-name" title="<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php 
                                $displayName = !empty($originalName) && strlen($originalName) > 25 ? substr($originalName, 0, 25) . '...' : $originalName;
                                echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); 
                                ?>
                            </div>
                            <div class="media-meta">
                                <span><?php echo number_format($fileSize / 1024, 1); ?> KB</span>
                                <span><?php echo date('M d', strtotime($createdAt)); ?></span>
                            </div>
                        </div>
                        <?php if ($isImage && !empty($file['sizes'])): ?>
                            <div class="media-sizes-info" style="padding: 8px 12px; background: #f8f9fa; border-top: 1px solid #e2e8f0; font-size: 11px; color: #64748b;">
                                <div style="font-weight: 600; margin-bottom: 4px;">📐 Generated Sizes:</div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    <?php foreach ($file['sizes'] as $sizeName => $sizeData): ?>
                                        <span style="background: white; padding: 2px 6px; border-radius: 4px; border: 1px solid #cbd5e1;">
                                            <?php echo htmlspecialchars($sizeName); ?>: <?php echo $sizeData['width']; ?>×<?php echo $sizeData['height']; ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="media-actions-bar">
                            <button type="button" class="media-action-btn media-action-btn-view" onclick="event.stopPropagation(); window.open('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank');">View</button>
                            <button type="button" class="media-action-btn media-action-btn-copy" onclick="event.stopPropagation(); copyFileUrl('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>');">Copy</button>
                            <?php if ($isImage): ?>
                                <button type="button" class="media-action-btn" style="background: #10b981; color: white;" onclick="event.stopPropagation(); regenerateSizes(<?php echo $fileId; ?>);">Regenerate</button>
                            <?php endif; ?>
                            <form method="post" style="display: inline; flex: 1;" onsubmit="event.stopPropagation(); return confirm('Are you sure you want to delete this file?');">
                                <input type="hidden" name="media_id" value="<?php echo $fileId; ?>">
                                <button type="submit" name="delete_media" class="media-action-btn media-action-btn-delete" style="width: 100%;">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- List View -->
            <div class="media-list" id="mediaListView">
                <?php foreach ($mediaFiles as $file): 
                    $originalName = !empty($file['original_name']) ? $file['original_name'] : (!empty($file['filename']) ? $file['filename'] : 'Unknown');
                    $fileType = !empty($file['file_type']) ? $file['file_type'] : '';
                    $fileSize = isset($file['file_size']) ? intval($file['file_size']) : 0;
                    $createdAt = !empty($file['created_at']) ? $file['created_at'] : date('Y-m-d H:i:s');
                    $filePath = !empty($file['file_path']) ? $file['file_path'] : '';
                    $fileId = isset($file['id']) ? intval($file['id']) : 0;
                    $isImage = !empty($fileType) && strpos($fileType, 'image/') === 0;
                    $fileUrl = $baseUrl . '/' . $filePath;
                    $fileIcon = '📎';
                    if (!empty($fileType)) {
                        if (strpos($fileType, 'pdf') !== false) $fileIcon = '📄';
                        elseif (strpos($fileType, 'word') !== false || strpos($fileType, 'document') !== false) $fileIcon = '📝';
                        elseif (strpos($fileType, 'excel') !== false || strpos($fileType, 'spreadsheet') !== false) $fileIcon = '📊';
                        elseif (strpos($fileType, 'video') !== false) $fileIcon = '🎥';
                        elseif (strpos($fileType, 'audio') !== false) $fileIcon = '🎵';
                        elseif (strpos($fileType, 'zip') !== false) $fileIcon = '📦';
                    }
                    
                    // Get thumbnail for list view
                    $thumbnailUrl = null;
                    if ($isImage && !empty($filePath)) {
                        $fullImagePath = $rootPath . '/' . $filePath;
                        if (!empty($file['thumbnail_path']) && file_exists($rootPath . '/' . $file['thumbnail_path'])) {
                            $thumbnailUrl = $baseUrl . '/' . $file['thumbnail_path'];
                        } else {
                            try {
                                $imageResizer = new ImageResizer($rootPath, $pdo);
                                if ($imageResizer->isAvailable() && file_exists($fullImagePath)) {
                                    $thumbPath = $imageResizer->getImageUrl($filePath, 'thumbnail', '');
                                    $thumbPath = str_replace($baseUrl . '/', '', $thumbPath);
                                    $fullThumbPath = $rootPath . '/' . $thumbPath;
                                    if (file_exists($fullThumbPath)) {
                                        $thumbnailUrl = $baseUrl . '/' . $thumbPath;
                                    } else {
                                        $thumbnailUrl = $fileUrl;
                                    }
                                } else {
                                    $thumbnailUrl = $fileUrl;
                                }
                            } catch (Exception $e) {
                                $thumbnailUrl = $fileUrl;
                            }
                        }
                        if (empty($thumbnailUrl)) {
                            $thumbnailUrl = $fileUrl;
                        }
                    }
                ?>
                    <div class="media-list-item" data-media-id="<?php echo $fileId; ?>" onclick="toggleSelect(this, event)">
                        <div class="media-list-thumbnail">
                            <?php if ($isImage && !empty($thumbnailUrl)): ?>
                                <img src="<?php echo htmlspecialchars($thumbnailUrl, ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="placeholder" style="display: none;">🖼️</div>
                            <?php else: ?>
                                <div class="placeholder"><?php echo $fileIcon; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="media-list-info">
                            <div class="media-list-name" title="<?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                            <div class="media-list-meta">
                                <span>📏 <?php echo number_format($fileSize / 1024, 1); ?> KB</span>
                                <span>📅 <?php echo date('M d, Y', strtotime($createdAt)); ?></span>
                                <?php if (!empty($file['uploaded_by_name'])): ?>
                                    <span>👤 <?php echo htmlspecialchars($file['uploaded_by_name']); ?></span>
                                <?php endif; ?>
                                <?php if ($isImage): ?>
                                    <span>🖼️ Image</span>
                                <?php else: ?>
                                    <span>📎 <?php echo strtoupper(pathinfo($filePath, PATHINFO_EXTENSION)); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="media-list-actions" onclick="event.stopPropagation();">
                            <button type="button" class="admin-btn admin-btn-outline" onclick="window.open('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>', '_blank');">View</button>
                            <button type="button" class="admin-btn admin-btn-outline" onclick="copyFileUrl('<?php echo htmlspecialchars($fileUrl, ENT_QUOTES, 'UTF-8'); ?>');">Copy URL</button>
                            <?php if ($isImage): ?>
                                <button type="button" class="admin-btn admin-btn-outline" onclick="regenerateSizes(<?php echo $fileId; ?>);">Regenerate</button>
                            <?php endif; ?>
                            <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this file?');">
                                <input type="hidden" name="media_id" value="<?php echo $fileId; ?>">
                                <button type="submit" name="delete_media" class="admin-btn admin-btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?type=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">← Previous</a>
                    <?php else: ?>
                        <span class="disabled">← Previous</span>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?type=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?type=<?php echo urlencode($filterType); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">Next →</a>
                    <?php else: ?>
                        <span class="disabled">Next →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Image Preview Modal -->
        <div class="modal" id="imageModal" onclick="if(event.target === this) closeImageModal();">
            <div class="modal-content" onclick="event.stopPropagation();">
                <div class="modal-header">
                    <h3 id="modalFileName">Image Preview</h3>
                    <button type="button" class="modal-close" onclick="closeImageModal();">×</button>
                </div>
                <div class="modal-body">
                    <img src="" alt="" class="modal-image" id="modalImage">
                    <div class="url-copy-box">
                        <input type="text" id="modalFileUrl" readonly value="">
                        <button type="button" class="admin-btn admin-btn-primary" onclick="copyToClipboard(document.getElementById('modalFileUrl'));">Copy URL</button>
                    </div>
                    <div class="modal-info" id="modalInfo"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('mediaFiles');

        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            if (e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                handleFileSelect();
            }
        });

        // Don't trigger file input on click if clicking buttons
        uploadArea.addEventListener('click', (e) => {
            if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'INPUT') {
                fileInput.click();
            }
        });
        
        // Handle file select
        function handleFileSelect() {
            const files = fileInput.files;
            const selectedFilesInfo = document.getElementById('selectedFilesInfo');
            const selectedFilesCount = document.getElementById('selectedFilesCount');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (files.length > 0) {
                // Show selected files count
                selectedFilesInfo.style.display = 'block';
                selectedFilesCount.textContent = files.length;
                uploadBtn.disabled = false;
                uploadBtn.style.opacity = '1';
                uploadBtn.style.cursor = 'pointer';
            } else {
                selectedFilesInfo.style.display = 'none';
                uploadBtn.disabled = true;
                uploadBtn.style.opacity = '0.5';
                uploadBtn.style.cursor = 'not-allowed';
            }
        }
        
        // Prevent form submission if no files selected and show feedback
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const files = fileInput.files;
            if (!files || files.length === 0) {
                e.preventDefault();
                showToast('Please select at least one file to upload');
                return false;
            }
            
            // Show upload progress
            const uploadBtn = document.getElementById('uploadBtn');
            const uploadProgress = document.getElementById('uploadProgress');
            const uploadProgressBar = document.getElementById('uploadProgressBar');
            const uploadStatus = document.getElementById('uploadStatus');
            
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';
            uploadProgress.style.display = 'block';
            uploadProgressBar.style.width = '0%';
            uploadStatus.textContent = `Uploading ${files.length} file(s)...`;
            
            // Simulate progress (actual progress handled by server)
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += 10;
                if (progress <= 90) {
                    uploadProgressBar.style.width = progress + '%';
                }
            }, 200);
            
            // Clear interval after form submission
            setTimeout(() => {
                clearInterval(progressInterval);
            }, 5000);
        });
        
        // Toggle selection for bulk operations
        let selectedFiles = new Set();
        
        function toggleSelect(element, event) {
            if (event.target.tagName === 'BUTTON' || event.target.tagName === 'INPUT' || event.target.closest('form')) {
                return;
            }
            
            const mediaId = element.getAttribute('data-media-id');
            if (selectedFiles.has(mediaId)) {
                selectedFiles.delete(mediaId);
                element.classList.remove('selected');
            } else {
                selectedFiles.add(mediaId);
                element.classList.add('selected');
            }
            
            updateBulkActions();
        }
        
        function updateBulkActions() {
            const bulkActions = document.getElementById('bulkActions');
            const selectedCount = document.getElementById('selectedCount');
            const bulkMediaIds = document.getElementById('bulkMediaIds');
            const bulkMediaIdsDelete = document.getElementById('bulkMediaIdsDelete');
            
            if (selectedFiles.size > 0) {
                bulkActions.classList.add('active');
                selectedCount.textContent = selectedFiles.size + ' file' + (selectedFiles.size !== 1 ? 's' : '') + ' selected';
                const ids = Array.from(selectedFiles).join(',');
                if (bulkMediaIds) bulkMediaIds.value = ids;
                if (bulkMediaIdsDelete) bulkMediaIdsDelete.value = ids;
            } else {
                bulkActions.classList.remove('active');
            }
        }
        
        // Regenerate image sizes
        function regenerateSizes(mediaId) {
            if (!confirm('Regenerate all image sizes for this image?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('regenerate_sizes', '1');
            formData.append('media_id', mediaId);
            
            fetch('media.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Image sizes regenerated successfully!');
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Error: ' + (data.error || 'Failed to regenerate sizes'));
                }
            })
            .catch(error => {
                showToast('Error: ' + error.message);
            });
        }
        
        // Generate thumbnails for images that don't have them
        function generateMissingThumbnails() {
            const imageThumbnails = document.querySelectorAll('.media-thumbnail[data-media-id]');
            let generated = 0;
            
            imageThumbnails.forEach(img => {
                const mediaId = img.getAttribute('data-media-id');
                const fullUrl = img.getAttribute('data-full-url');
                const currentSrc = img.src;
                
                // If using full image as thumbnail, try to generate thumbnail
                if (currentSrc === fullUrl && mediaId) {
                    // Check if thumbnail exists by trying to load it
                    const thumbUrl = currentSrc.replace(/\/uploads\/media\//, '/uploads/media/thumbnail/');
                    const testImg = new Image();
                    testImg.onload = function() {
                        // Thumbnail exists, update src
                        img.src = thumbUrl;
                        img.classList.remove('loading');
                    };
                    testImg.onerror = function() {
                        // Thumbnail doesn't exist, try to generate it
                        const formData = new FormData();
                        formData.append('regenerate_sizes', '1');
                        formData.append('media_id', mediaId);
                        
                        fetch('media.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Reload the image with new thumbnail
                                const newThumbUrl = currentSrc.replace(/\/uploads\/media\//, '/uploads/media/thumbnail/');
                                img.src = newThumbUrl + '?t=' + Date.now();
                                generated++;
                            }
                        })
                        .catch(() => {
                            // Silently fail
                        });
                    };
                    testImg.src = thumbUrl;
                }
            });
        }
        
        // Generate thumbnails on page load (lazy)
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit before generating thumbnails to not slow down page load
            setTimeout(generateMissingThumbnails, 2000);
            
            // Initialize view from localStorage
            const savedView = localStorage.getItem('media_view') || 'grid';
            setView(savedView, false);
        });
        
        // View Toggle Function
        function setView(view, save = true) {
            const gridView = document.getElementById('mediaGridView');
            const listView = document.getElementById('mediaListView');
            const gridBtn = document.getElementById('viewBtnGrid');
            const listBtn = document.getElementById('viewBtnList');
            
            if (!gridView || !listView || !gridBtn || !listBtn) {
                return; // Elements not loaded yet
            }
            
            if (view === 'list') {
                gridView.classList.remove('active');
                listView.classList.add('active');
                gridBtn.classList.remove('active');
                listBtn.classList.add('active');
            } else {
                gridView.classList.add('active');
                listView.classList.remove('active');
                gridBtn.classList.add('active');
                listBtn.classList.remove('active');
            }
            
            if (save) {
                localStorage.setItem('media_view', view);
            }
        }
        
        // Copy file URL to clipboard
        function copyFileUrl(url) {
            copyToClipboard(url);
            showToast('File URL copied to clipboard!');
        }
        
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Copied to clipboard!');
                });
            } else {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!');
            }
        }
        
        // Show image modal
        function showImageModal(imageUrl, fileName, fileUrl) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const modalFileName = document.getElementById('modalFileName');
            const modalFileUrl = document.getElementById('modalFileUrl');
            
            modalImage.src = imageUrl;
            modalFileName.textContent = fileName;
            modalFileUrl.value = fileUrl;
            modal.classList.add('active');
        }
        
        function closeImageModal() {
            document.getElementById('imageModal').classList.remove('active');
        }
        
        // Toast notification
        function showToast(message) {
            const toast = document.createElement('div');
            toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: #1d2327; color: white; padding: 12px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10001; font-weight: 600;';
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transition = 'opacity 0.3s';
                setTimeout(() => toast.remove(), 300);
            }, 2000);
        }
        
        // Close modal on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });
        
        // Auto-scan on page load (after a short delay to not slow down initial load)
        let autoScanEnabled = true; // Can be toggled via settings
        const lastScanTime = localStorage.getItem('media_last_scan_time');
        const now = Date.now();
        const scanInterval = 5 * 60 * 1000; // 5 minutes in milliseconds
        
        // Auto-scan if:
        // 1. Never scanned before, OR
        // 2. Last scan was more than 5 minutes ago
        if (autoScanEnabled && (!lastScanTime || (now - parseInt(lastScanTime)) > scanInterval)) {
            setTimeout(() => {
                const scanBtn = document.getElementById('scanMediaBtn');
                if (scanBtn && !scanBtn.disabled) {
                    console.log('Auto-scanning media files...');
                    scanBtn.click();
                }
            }, 2000); // Wait 2 seconds after page load
        }
        
        // Media Scan Functionality
        const scanBtn = document.getElementById('scanMediaBtn');
        const scanProgress = document.getElementById('scanProgress');
        const scanOutput = document.getElementById('scanOutput');
        const scanResults = document.getElementById('scanResults');
        const scanSummary = document.getElementById('scanSummary');
        
        if (scanBtn) {
            scanBtn.addEventListener('click', async function() {
                this.disabled = true;
                this.textContent = 'Scanning...';
                scanProgress.style.display = 'block';
                scanResults.style.display = 'none';
                scanOutput.innerHTML = '<div style="color: rgba(255,255,255,0.8);">Starting scan...</div>';
                
                try {
                    const formData = new FormData();
                    formData.append('scan_media_ajax', '1');
                    
                    const response = await fetch('media.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    let data;
                    try {
                        const text = await response.text();
                        data = JSON.parse(text);
                    } catch (parseError) {
                        throw new Error('Invalid JSON response: ' + parseError.message);
                    }
                    
                    if (data.success) {
                        // Show progress output if available
                        if (data.output) {
                            scanOutput.innerHTML = '<pre style="white-space: pre-wrap; word-wrap: break-word; color: rgba(255,255,255,0.9);">' + 
                                data.output.replace(/\n/g, '<br>').replace(/Error:/g, '<span style="color: #fee2e2;">Error:</span>')
                                .replace(/Warning:/g, '<span style="color: #fef3c7;">Warning:</span>')
                                .replace(/Registered:/g, '<span style="color: #d1fae5;">Registered:</span>')
                                .replace(/Scanning:/g, '<span style="color: #dbeafe;">Scanning:</span>') + 
                                '</pre>';
                        }
                        
                        // Show results
                        scanProgress.style.display = 'none';
                        scanResults.style.display = 'block';
                        
                        let summary = `<div style="margin-bottom: 8px;"><strong>📁 Directories Scanned:</strong> ${data.scanned_paths ? data.scanned_paths.length : 0}</div>`;
                        summary += `<div style="margin-bottom: 8px;"><strong>📄 Total Files Found:</strong> ${data.total_found || (data.found_files ? data.found_files.length : 0)}</div>`;
                        summary += `<div style="margin-bottom: 8px; color: #d1fae5;"><strong>✅ New Files Registered:</strong> ${data.registered || 0}</div>`;
                        summary += `<div style="margin-bottom: 8px;"><strong>⏭️ Files Skipped (already registered):</strong> ${data.skipped || 0}</div>`;
                        
                        if (data.errors && data.errors.length > 0) {
                            summary += `<div style="margin-bottom: 8px; color: #fee2e2;"><strong>❌ Errors:</strong> ${data.errors.length}</div>`;
                            summary += `<details style="margin-top: 8px; font-size: 11px; opacity: 0.8;" open><summary style="cursor: pointer; font-weight: 600;">Show error details (${data.errors.length} errors)</summary><ul style="margin: 8px 0 0 20px; max-height: 200px; overflow-y: auto; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 4px;">`;
                            data.errors.forEach((error, index) => {
                                summary += `<li style="margin-bottom: 4px; word-break: break-all;">${index + 1}. ${error}</li>`;
                            });
                            summary += `</ul></details>`;
                        }
                        
                        // Show scanned paths
                        if (data.scanned_paths && data.scanned_paths.length > 0) {
                            summary += `<div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);"><strong>📂 Scanned Directories:</strong><ul style="margin: 8px 0 0 20px; font-size: 11px; opacity: 0.9; max-height: 150px; overflow-y: auto;">`;
                            data.scanned_paths.forEach(path => {
                                summary += `<li>${path}</li>`;
                            });
                            summary += `</ul></div>`;
                        }
                        
                        scanSummary.innerHTML = summary;
                        
                        // Save scan time to localStorage
                        localStorage.setItem('media_last_scan_time', Date.now().toString());
                        
                        // Reload page after 3 seconds to show new media
                        setTimeout(() => {
                            window.location.reload();
                        }, 3000);
                    } else {
                        scanProgress.style.display = 'none';
                        scanResults.style.display = 'block';
                        scanSummary.innerHTML = `<div style="color: #fee2e2;"><strong>❌ Scan Failed</strong><br>${data.error || 'Unknown error occurred'}</div>`;
                        scanBtn.disabled = false;
                        scanBtn.textContent = '🔍 Scan for Media Files';
                    }
                } catch (error) {
                    scanProgress.style.display = 'none';
                    scanResults.style.display = 'block';
                    scanSummary.innerHTML = `<div style="color: #fee2e2;"><strong>❌ Error</strong><br>${error.message}</div>`;
                    scanBtn.disabled = false;
                    scanBtn.textContent = '🔍 Scan for Media Files';
                }
            });
        }
        
    </script>
</body>
</html>


