<?php
/**
 * Media Library Picker API
 * Returns media files as JSON for the media picker
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$pdo = getDBConnection();

// Get base URL
$baseUrl = app_url();

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

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

// Get total count
try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_media m {$whereClause}");
    $countStmt->execute($params);
    $totalCount = $countStmt->fetchColumn() ?: 0;
    $totalPages = ceil($totalCount / $perPage);
} catch (PDOException $e) {
    $totalCount = 0;
    $totalPages = 0;
}

// Get media files
try {
    $stmt = $pdo->prepare("SELECT m.*, u.username as uploaded_by_name FROM cms_media m LEFT JOIN cms_users u ON m.uploaded_by = u.id {$whereClause} ORDER BY m.created_at DESC LIMIT {$perPage} OFFSET {$offset}");
    $stmt->execute($params);
    $mediaFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $response = [
        'success' => true,
        'data' => [],
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages
        ]
    ];
    
    foreach ($mediaFiles as $file) {
        $fileUrl = $baseUrl . '/' . $file['file_path'];
        $isImage = strpos($file['file_type'], 'image/') === 0;
        
        $response['data'][] = [
            'id' => $file['id'],
            'filename' => $file['filename'],
            'original_name' => $file['original_name'],
            'url' => $fileUrl,
            'file_path' => $file['file_path'],
            'file_type' => $file['file_type'],
            'file_size' => $file['file_size'],
            'is_image' => $isImage,
            'created_at' => $file['created_at']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

