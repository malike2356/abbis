<?php
/**
 * CMS Admin - Portfolio/Gallery Management (WordPress-style)
 * Complete makeover with modern UI and thumbnail display
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once $rootPath . '/cms/includes/image-resizer.php';
require_once $rootPath . '/cms/includes/media-helper.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$user = $cmsAuth->getCurrentUser();
$imageResizer = new ImageResizer($rootPath, $pdo);

// Ensure portfolio tables exist
try {
    $pdo->query("SELECT 1 FROM cms_portfolio LIMIT 1");
} catch (PDOException $e) {
    $migrationPath = $rootPath . '/database/portfolio_migration.sql';
    if (file_exists($migrationPath)) {
        $sql = file_get_contents($migrationPath);
        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $ignored) {}
            }
        }
    }
}

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$message = $_GET['message'] ?? $_GET['error'] ?? null;
$messageType = isset($_GET['error']) ? 'error' : (isset($_GET['message']) ? 'success' : null);

// Handle AJAX image deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_delete_image'])) {
    header('Content-Type: application/json');
    $imageId = intval($_POST['image_id'] ?? 0);
    
    if (!$imageId) {
        echo json_encode(['success' => false, 'error' => 'Image ID required']);
        exit;
    }
    
    try {
        // Get image info
        $imgStmt = $pdo->prepare("SELECT image_path, portfolio_id FROM cms_portfolio_images WHERE id=?");
        $imgStmt->execute([$imageId]);
        $img = $imgStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$img) {
            echo json_encode(['success' => false, 'error' => 'Image not found']);
            exit;
        }
        
        // Delete file
        if ($img['image_path'] && file_exists($rootPath . '/' . $img['image_path'])) {
            @unlink($rootPath . '/' . $img['image_path']);
            // Delete sizes
            $imgPathInfo = pathinfo($img['image_path']);
            $imgSizesDir = $rootPath . '/' . $imgPathInfo['dirname'] . '/sizes';
            if (is_dir($imgSizesDir)) {
                $sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
                foreach ($sizes as $size) {
                    $sizePath = $imgSizesDir . '/' . $imgPathInfo['filename'] . '-' . $size . '.' . $imgPathInfo['extension'];
                    if (file_exists($sizePath)) {
                        @unlink($sizePath);
                    }
                }
            }
        }
        
        // Delete from database
        $pdo->prepare("DELETE FROM cms_portfolio_images WHERE id=?")->execute([$imageId]);
        
        echo json_encode(['success' => true, 'message' => 'Image deleted successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_portfolio'])) {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $description = $_POST['description'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $client_name = trim($_POST['client_name'] ?? '');
        $project_date = $_POST['project_date'] ?? null;
        $status = $_POST['status'] ?? 'draft';
        $display_order = intval($_POST['display_order'] ?? 0);
        
        // Auto-generate slug from title if not provided
        if (empty($slug) && !empty($title)) {
            $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
            $slug = preg_replace('/-+/', '-', $slug);
        }
        
        // Ensure unique slug
        $slugBase = $slug;
        $slugCounter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM cms_portfolio WHERE slug=? AND id!=?");
            $checkStmt->execute([$slug, $id ?: 0]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $slug = $slugBase . '-' . $slugCounter;
            $slugCounter++;
        }
        
        // Handle featured image upload
        $featured_image = $_POST['existing_featured_image'] ?? '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = $rootPath . '/uploads/portfolio/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $ext = strtolower(pathinfo($_FILES['featured_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($ext, $allowed)) {
                $uniqueFilename = uniqid() . '_' . time() . '.' . $ext;
                $filepath = $uploadDir . $uniqueFilename;
                
                if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $filepath)) {
                    // Delete old featured image if exists
                    if (!empty($featured_image) && file_exists($rootPath . '/' . $featured_image)) {
                        @unlink($rootPath . '/' . $featured_image);
                        // Delete old sizes
                        $oldPathInfo = pathinfo($featured_image);
                        $oldSizesDir = $rootPath . '/' . $oldPathInfo['dirname'] . '/sizes';
                        if (is_dir($oldSizesDir)) {
                            $oldSizes = ['thumbnail', 'medium', 'medium_large', 'large'];
                            foreach ($oldSizes as $size) {
                                $oldSizePath = $oldSizesDir . '/' . $oldPathInfo['filename'] . '-' . $size . '.' . $oldPathInfo['extension'];
                                if (file_exists($oldSizePath)) {
                                    @unlink($oldSizePath);
                                }
                            }
                        }
                    }
                    $featured_image = 'uploads/portfolio/' . $uniqueFilename;
                    // Generate image sizes
                    $imageResizer->generateSizes($featured_image);
                }
            }
        }
        
        if ($title && $slug) {
            if ($id) {
                // Update existing
                $stmt = $pdo->prepare("UPDATE cms_portfolio SET title=?, slug=?, description=?, location=?, client_name=?, project_date=?, status=?, display_order=?, featured_image=?, updated_at=NOW() WHERE id=?");
                $stmt->execute([$title, $slug, $description, $location, $client_name, $project_date ?: null, $status, $display_order, $featured_image, $id]);
                $message = 'Portfolio item updated successfully';
            } else {
                // Create new
                $stmt = $pdo->prepare("INSERT INTO cms_portfolio (title, slug, description, location, client_name, project_date, status, display_order, featured_image, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$title, $slug, $description, $location, $client_name, $project_date ?: null, $status, $display_order, $featured_image, $user['id']]);
                $id = $pdo->lastInsertId();
                $message = 'Portfolio item created successfully';
            }
            $messageType = 'success';
            
            // Handle portfolio images
            if ($id) {
                // Delete removed images
                if (isset($_POST['delete_images']) && is_array($_POST['delete_images'])) {
                    foreach ($_POST['delete_images'] as $imageId) {
                        $imgStmt = $pdo->prepare("SELECT image_path FROM cms_portfolio_images WHERE id=?");
                        $imgStmt->execute([$imageId]);
                        $img = $imgStmt->fetch(PDO::FETCH_ASSOC);
                        if ($img && file_exists($rootPath . '/' . $img['image_path'])) {
                            @unlink($rootPath . '/' . $img['image_path']);
                            // Delete sizes
                            $imgPathInfo = pathinfo($img['image_path']);
                            $imgSizesDir = $rootPath . '/' . $imgPathInfo['dirname'] . '/sizes';
                            if (is_dir($imgSizesDir)) {
                                $sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
                                foreach ($sizes as $size) {
                                    $sizePath = $imgSizesDir . '/' . $imgPathInfo['filename'] . '-' . $size . '.' . $imgPathInfo['extension'];
                                    if (file_exists($sizePath)) {
                                        @unlink($sizePath);
                                    }
                                }
                            }
                        }
                        $pdo->prepare("DELETE FROM cms_portfolio_images WHERE id=?")->execute([$imageId]);
                    }
                }
                
                // Handle selected media library images
                if (isset($_POST['selected_media_images']) && is_array($_POST['selected_media_images'])) {
                    foreach ($_POST['selected_media_images'] as $mediaIdOrPath) {
                        // Get media info from database
                        $mediaStmt = $pdo->prepare("SELECT file_path, original_name, file_type FROM cms_media WHERE id = ? OR file_path = ? LIMIT 1");
                        $mediaStmt->execute([$mediaIdOrPath, $mediaIdOrPath]);
                        $media = $mediaStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($media && file_exists($rootPath . '/' . $media['file_path'])) {
                            // Use the existing media file path
                            $imagePath = $media['file_path'];
                            $imageAlt = $media['original_name'] ?? '';
                            $imageCaption = '';
                            $imageOrder = 999;
                            
                            $imgStmt = $pdo->prepare("INSERT INTO cms_portfolio_images (portfolio_id, image_path, image_alt, image_caption, display_order) VALUES (?,?,?,?,?)");
                            $imgStmt->execute([$id, $imagePath, $imageAlt, $imageCaption, $imageOrder]);
                            
                            // Generate image sizes if not already generated
                            $imageResizer->generateSizes($imagePath, $pdo->lastInsertId());
                        }
                    }
                }
                
                // Handle new image uploads
                if (isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])) {
                    $uploadDir = $rootPath . '/uploads/portfolio/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    foreach ($_FILES['portfolio_images']['name'] as $key => $filename) {
                        if ($_FILES['portfolio_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($ext, $allowed)) {
                                $uniqueFilename = uniqid() . '_' . time() . '_' . $key . '.' . $ext;
                                $filepath = $uploadDir . $uniqueFilename;
                                
                                if (move_uploaded_file($_FILES['portfolio_images']['tmp_name'][$key], $filepath)) {
                                    $imagePath = 'uploads/portfolio/' . $uniqueFilename;
                                    $imageAlt = $_POST['image_alt'][$key] ?? '';
                                    $imageCaption = $_POST['image_caption'][$key] ?? '';
                                    $imageOrder = intval($_POST['image_order'][$key] ?? 999);
                                    
                                    $imgStmt = $pdo->prepare("INSERT INTO cms_portfolio_images (portfolio_id, image_path, image_alt, image_caption, display_order) VALUES (?,?,?,?,?)");
                                    $imgStmt->execute([$id, $imagePath, $imageAlt, $imageCaption, $imageOrder]);
                                    
                                    // Generate image sizes
                                    $imageResizer->generateSizes($imagePath, $pdo->lastInsertId());
                                }
                            }
                        }
                    }
                }
                
                // Update existing image metadata and order
                if (isset($_POST['update_images']) && is_array($_POST['update_images'])) {
                    foreach ($_POST['update_images'] as $imageId => $data) {
                        $imgStmt = $pdo->prepare("UPDATE cms_portfolio_images SET image_alt=?, image_caption=?, display_order=? WHERE id=?");
                        $imgStmt->execute([$data['alt'] ?? '', $data['caption'] ?? '', intval($data['order'] ?? 0), intval($imageId)]);
                    }
                }
            }
            
            $_SESSION['portfolio_message'] = $message;
            $_SESSION['portfolio_message_type'] = $messageType;
            header('Location: portfolio.php?action=edit&id=' . $id);
            exit;
        } else {
            $message = 'Title and slug are required';
            $messageType = 'error';
        }
    }
    
    if (isset($_POST['delete_portfolio'])) {
        // Delete portfolio images first
        $imgStmt = $pdo->prepare("SELECT image_path FROM cms_portfolio_images WHERE portfolio_id=?");
        $imgStmt->execute([$id]);
        $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($images as $img) {
            if (file_exists($rootPath . '/' . $img['image_path'])) {
                @unlink($rootPath . '/' . $img['image_path']);
                // Delete sizes
                $imgPathInfo = pathinfo($img['image_path']);
                $imgSizesDir = $rootPath . '/' . $imgPathInfo['dirname'] . '/sizes';
                if (is_dir($imgSizesDir)) {
                    $sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
                    foreach ($sizes as $size) {
                        $sizePath = $imgSizesDir . '/' . $imgPathInfo['filename'] . '-' . $size . '.' . $imgPathInfo['extension'];
                        if (file_exists($sizePath)) {
                            @unlink($sizePath);
                        }
                    }
                }
            }
        }
        
        // Delete featured image
        $portStmt = $pdo->prepare("SELECT featured_image FROM cms_portfolio WHERE id=?");
        $portStmt->execute([$id]);
        $portfolio = $portStmt->fetch(PDO::FETCH_ASSOC);
        if ($portfolio && !empty($portfolio['featured_image']) && file_exists($rootPath . '/' . $portfolio['featured_image'])) {
            @unlink($rootPath . '/' . $portfolio['featured_image']);
            // Delete sizes
            $featPathInfo = pathinfo($portfolio['featured_image']);
            $featSizesDir = $rootPath . '/' . $featPathInfo['dirname'] . '/sizes';
            if (is_dir($featSizesDir)) {
                $sizes = ['thumbnail', 'medium', 'medium_large', 'large'];
                foreach ($sizes as $size) {
                    $sizePath = $featSizesDir . '/' . $featPathInfo['filename'] . '-' . $size . '.' . $featPathInfo['extension'];
                    if (file_exists($sizePath)) {
                        @unlink($sizePath);
                    }
                }
            }
        }
        
        // Delete portfolio (cascade will delete images)
        $pdo->prepare("DELETE FROM cms_portfolio WHERE id=?")->execute([$id]);
        $_SESSION['portfolio_message'] = 'Portfolio item deleted successfully';
        $_SESSION['portfolio_message_type'] = 'success';
        header('Location: portfolio.php');
        exit;
    }
    
    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['portfolio_ids'])) {
        $portfolioIds = is_array($_POST['portfolio_ids']) ? $_POST['portfolio_ids'] : explode(',', $_POST['portfolio_ids']);
        $bulkAction = $_POST['bulk_action'];
        $processed = 0;
        
        foreach ($portfolioIds as $portfolioId) {
            $portfolioId = intval($portfolioId);
            if ($portfolioId <= 0) continue;
            
            try {
                if ($bulkAction === 'delete') {
                    // Delete portfolio (same as single delete)
                    $imgStmt = $pdo->prepare("SELECT image_path FROM cms_portfolio_images WHERE portfolio_id=?");
                    $imgStmt->execute([$portfolioId]);
                    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($images as $img) {
                        if (file_exists($rootPath . '/' . $img['image_path'])) {
                            @unlink($rootPath . '/' . $img['image_path']);
                        }
                    }
                    
                    $portStmt = $pdo->prepare("SELECT featured_image FROM cms_portfolio WHERE id=?");
                    $portStmt->execute([$portfolioId]);
                    $portfolio = $portStmt->fetch(PDO::FETCH_ASSOC);
                    if ($portfolio && !empty($portfolio['featured_image']) && file_exists($rootPath . '/' . $portfolio['featured_image'])) {
                        @unlink($rootPath . '/' . $portfolio['featured_image']);
                    }
                    
                    $pdo->prepare("DELETE FROM cms_portfolio WHERE id=?")->execute([$portfolioId]);
                    $processed++;
                } elseif ($bulkAction === 'publish') {
                    $pdo->prepare("UPDATE cms_portfolio SET status='published' WHERE id=?")->execute([$portfolioId]);
                    $processed++;
                } elseif ($bulkAction === 'draft') {
                    $pdo->prepare("UPDATE cms_portfolio SET status='draft' WHERE id=?")->execute([$portfolioId]);
                    $processed++;
                } elseif ($bulkAction === 'archive') {
                    $pdo->prepare("UPDATE cms_portfolio SET status='archived' WHERE id=?")->execute([$portfolioId]);
                    $processed++;
                }
            } catch (PDOException $e) {
                // Continue with next
            }
        }
        
        $_SESSION['portfolio_message'] = "Bulk action completed: {$processed} item(s) processed";
        $_SESSION['portfolio_message_type'] = 'success';
        header('Location: portfolio.php');
        exit;
    }
}

// Check for flash message
if (isset($_SESSION['portfolio_message'])) {
    $message = $_SESSION['portfolio_message'];
    $messageType = $_SESSION['portfolio_message_type'] ?? 'success';
    unset($_SESSION['portfolio_message']);
    unset($_SESSION['portfolio_message_type']);
}

// Get portfolio item for editing
$portfolio = null;
$portfolioImages = [];
if ($id && $action === 'edit') {
    $stmt = $pdo->prepare("SELECT * FROM cms_portfolio WHERE id=?");
    $stmt->execute([$id]);
    $portfolio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($portfolio) {
        $imgStmt = $pdo->prepare("SELECT * FROM cms_portfolio_images WHERE portfolio_id=? ORDER BY display_order, id");
        $imgStmt->execute([$id]);
        $portfolioImages = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query for portfolio list
$where = [];
$params = [];

if ($filterStatus !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}

if (!empty($search)) {
    $where[] = "(p.title LIKE ? OR p.location LIKE ? OR p.client_name LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get all portfolio items for listing with image counts and thumbnails
$portfolios = [];
if ($action === 'list') {
    $stmt = $pdo->prepare("SELECT p.*, 
        (SELECT COUNT(*) FROM cms_portfolio_images WHERE portfolio_id = p.id) as image_count,
        (SELECT image_path FROM cms_portfolio_images WHERE portfolio_id = p.id ORDER BY display_order, id LIMIT 1) as first_image
        FROM cms_portfolio p 
        {$whereClause}
        ORDER BY p.display_order, p.created_at DESC");
    $stmt->execute($params);
    $portfolios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
$stats = [];
try {
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM cms_portfolio")->fetchColumn() ?: 0;
    $stats['published'] = $pdo->query("SELECT COUNT(*) FROM cms_portfolio WHERE status='published'")->fetchColumn() ?: 0;
    $stats['draft'] = $pdo->query("SELECT COUNT(*) FROM cms_portfolio WHERE status='draft'")->fetchColumn() ?: 0;
    $stats['archived'] = $pdo->query("SELECT COUNT(*) FROM cms_portfolio WHERE status='archived'")->fetchColumn() ?: 0;
    $stats['total_images'] = $pdo->query("SELECT COUNT(*) FROM cms_portfolio_images")->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $stats = ['total' => 0, 'published' => 0, 'draft' => 0, 'archived' => 0, 'total_images' => 0];
}

// Get base URL
$baseUrl = app_url();

// Get company name
$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portfolio Management - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php 
    $currentPage = 'portfolio';
    include 'header.php'; 
    ?>
    <style>
        /* Portfolio-specific styles */
        .portfolio-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .portfolio-stat-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .portfolio-stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        
        .portfolio-stat-card.active {
            border-color: var(--admin-primary, #2563eb);
            border-width: 2px;
            background: var(--admin-primary-lighter, rgba(37, 99, 235, 0.05));
        }
        
        .portfolio-stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--admin-primary, #2563eb);
            margin: 8px 0;
        }
        
        .portfolio-stat-label {
            font-size: 13px;
            color: #646970;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .portfolio-grid-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }
        
        .portfolio-item-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            cursor: pointer;
        }
        
        .portfolio-item-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
            border-color: var(--admin-primary, #2563eb);
        }
        
        .portfolio-item-card.selected {
            border-color: var(--admin-primary, #2563eb);
            border-width: 3px;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .portfolio-item-thumbnail {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            position: relative;
            overflow: hidden;
        }
        
        .portfolio-item-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .portfolio-item-card:hover .portfolio-item-thumbnail img {
            transform: scale(1.05);
        }
        
        .portfolio-item-thumbnail-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .portfolio-item-card:hover .portfolio-item-thumbnail-overlay {
            opacity: 1;
        }
        
        .portfolio-item-thumbnail-overlay button {
            background: rgba(255,255,255,0.95);
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .portfolio-item-thumbnail-overlay button:hover {
            background: white;
            transform: scale(1.05);
        }
        
        .portfolio-item-checkbox {
            position: absolute;
            top: 12px;
            left: 12px;
            width: 24px;
            height: 24px;
            border: 2px solid white;
            border-radius: 4px;
            background: rgba(255,255,255,0.9);
            cursor: pointer;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .portfolio-item-checkbox input {
            margin: 0;
            cursor: pointer;
        }
        
        .portfolio-item-checkbox:has(input:checked) {
            background: var(--admin-primary, #2563eb);
            border-color: var(--admin-primary, #2563eb);
        }
        
        .portfolio-item-checkbox:has(input:checked)::after {
            content: '✓';
            color: white;
            font-weight: bold;
            font-size: 14px;
        }
        
        .portfolio-item-body {
            padding: 16px;
        }
        
        .portfolio-item-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 8px 0;
            color: #1d2327;
            line-height: 1.4;
        }
        
        .portfolio-item-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 12px;
            color: #646970;
        }
        
        .portfolio-item-meta-item {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .portfolio-item-status {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .portfolio-item-status.published {
            background: #d1fae5;
            color: #065f46;
        }
        
        .portfolio-item-status.draft {
            background: #fef3c7;
            color: #92400e;
        }
        
        .portfolio-item-status.archived {
            background: #e5e7eb;
            color: #374151;
        }
        
        .portfolio-item-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f1;
        }
        
        .portfolio-item-actions .admin-btn {
            flex: 1;
            padding: 8px 12px;
            font-size: 13px;
        }
        
        .portfolio-gallery-manager {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 24px;
            margin-top: 24px;
        }
        
        .portfolio-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        
        .portfolio-gallery-item {
            position: relative;
            border: 2px solid #c3c4c7;
            border-radius: 8px;
            overflow: hidden;
            background: #f6f7f7;
            cursor: move;
            transition: all 0.2s;
        }
        
        .portfolio-gallery-item:hover {
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .portfolio-gallery-item.ui-sortable-helper {
            transform: rotate(2deg);
            box-shadow: 0 8px 24px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        
        .portfolio-gallery-item img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }
        
        .portfolio-gallery-item-actions {
            position: absolute;
            top: 8px;
            right: 8px;
            display: flex;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        
        .portfolio-gallery-item:hover .portfolio-gallery-item-actions {
            opacity: 1;
        }
        
        .portfolio-gallery-item-actions button {
            background: rgba(0,0,0,0.8);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .portfolio-gallery-item-actions button:hover {
            background: rgba(0,0,0,0.95);
            transform: scale(1.1);
        }
        
        .portfolio-gallery-item-actions .delete-btn {
            background: rgba(220, 50, 50, 0.9);
        }
        
        .portfolio-gallery-item-actions .delete-btn:hover {
            background: #dc3232;
        }
        
        .portfolio-gallery-item-info {
            padding: 10px;
            background: white;
            font-size: 11px;
            color: #646970;
            border-top: 1px solid #f0f0f1;
        }
        
        .portfolio-gallery-item-info strong {
            display: block;
            margin-bottom: 4px;
            color: #1d2327;
            font-size: 12px;
        }
        
        .portfolio-gallery-item-order {
            position: absolute;
            bottom: 8px;
            left: 8px;
            background: rgba(37, 99, 235, 0.9);
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 12px;
        }
        
        .bulk-actions-bar {
            display: none;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .bulk-actions-bar.active {
            display: flex;
        }
        
        .view-toggle {
            display: flex;
            gap: 8px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 4px;
        }
        
        .view-toggle button {
            background: transparent;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.2s;
        }
        
        .view-toggle button.active {
            background: var(--admin-primary, #2563eb);
            color: white;
        }
        
        .portfolio-list-view {
            display: none;
        }
        
        .portfolio-list-view.active {
            display: block;
        }
        
        .portfolio-grid-view.active {
            display: grid;
        }
        
        .portfolio-grid-view {
            display: none;
        }
        
        @media (max-width: 768px) {
            .portfolio-grid-view {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 16px;
            }
            .portfolio-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Stellar Portfolio Editor Styles */
        .portfolio-editor-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 24px;
            margin-top: 24px;
        }
        
        .portfolio-editor-main {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .portfolio-editor-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .editor-section {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .editor-section:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .editor-section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .editor-section-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .editor-section-header .icon {
            font-size: 24px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            border-radius: 10px;
            color: white;
        }
        
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-row-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        
        .featured-image-panel {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            padding: 32px;
            min-height: 320px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .featured-image-panel.has-image {
            border-style: solid;
            border-color: #cbd5e1;
            padding: 16px;
        }
        
        .featured-image-panel img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        
        .image-actions {
            display: flex;
            gap: 10px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        
        .info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
        }
        
        .info-card h4 {
            margin: 0 0 16px 0;
            font-size: 14px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-item-label {
            color: #64748b;
            font-weight: 500;
        }
        
        .info-item-value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }
        
        .gallery-manager {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .quick-action-btn {
            padding: 14px 18px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
        }
        
        .quick-action-btn:hover {
            border-color: #2563eb;
            background: #eff6ff;
            color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
        }
        
        .quick-action-btn .icon {
            font-size: 20px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.draft {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.published {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.archived {
            background: #e5e7eb;
            color: #374151;
        }
        
        @media (max-width: 1400px) {
            .portfolio-editor-container {
                grid-template-columns: 1fr 340px;
            }
        }
        
        @media (max-width: 1200px) {
            .portfolio-editor-container {
                grid-template-columns: 1fr;
            }
            .form-row-3 {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .form-row-2,
            .form-row-3 {
                grid-template-columns: 1fr;
            }
            .portfolio-grid-view {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 16px;
            }
            .portfolio-stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        /* Save feedback styles */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .save-success-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 16px 24px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
            z-index: 10001;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease-out;
            min-width: 300px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .save-success-toast .icon {
            font-size: 24px;
        }
        
        .save-success-toast .content {
            flex: 1;
        }
        
        .save-success-toast .title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .save-success-toast .message {
            font-size: 13px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <?php if ($action === 'list'): ?>
            <!-- Portfolio List View -->
            <div class="admin-page-header">
                <h1>📸 Portfolio Management</h1>
                <p>Manage and showcase your borehole drilling projects and company achievements</p>
                <div class="admin-page-actions">
                    <a href="?action=add" class="admin-btn admin-btn-primary">
                        <span>➕</span> Add New Portfolio Item
                    </a>
                    <a href="add-portfolio-from-client.php" class="admin-btn admin-btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; background: #10b981; color: white; border: none; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s;" onmouseover="this.style.background='#059669'" onmouseout="this.style.background='#10b981'">
                        <span>📋</span> Add from ABBIS Client
                    </a>
                    <a href="<?php echo $baseUrl; ?>/cms/portfolio" target="_blank" class="admin-btn admin-btn-outline">
                        <span>👁️</span> View Public Portfolio
                    </a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="admin-notice admin-notice-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                    <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
                    <div class="admin-notice-content">
                        <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Statistics Dashboard -->
            <div class="portfolio-stats-grid">
                <a href="?status=all" class="portfolio-stat-card <?php echo $filterStatus === 'all' ? 'active' : ''; ?>">
                    <div class="portfolio-stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="portfolio-stat-label">Total Items</div>
                </a>
                <a href="?status=published" class="portfolio-stat-card <?php echo $filterStatus === 'published' ? 'active' : ''; ?>">
                    <div class="portfolio-stat-value" style="color: #10b981;"><?php echo number_format($stats['published']); ?></div>
                    <div class="portfolio-stat-label">Published</div>
                </a>
                <a href="?status=draft" class="portfolio-stat-card <?php echo $filterStatus === 'draft' ? 'active' : ''; ?>">
                    <div class="portfolio-stat-value" style="color: #f59e0b;"><?php echo number_format($stats['draft']); ?></div>
                    <div class="portfolio-stat-label">Draft</div>
                </a>
                <a href="?status=archived" class="portfolio-stat-card <?php echo $filterStatus === 'archived' ? 'active' : ''; ?>">
                    <div class="portfolio-stat-value" style="color: #6b7280;"><?php echo number_format($stats['archived']); ?></div>
                    <div class="portfolio-stat-label">Archived</div>
                </a>
                <div class="portfolio-stat-card">
                    <div class="portfolio-stat-value" style="color: #8b5cf6;"><?php echo number_format($stats['total_images']); ?></div>
                    <div class="portfolio-stat-label">Total Images</div>
                </div>
            </div>
            
            <!-- Filter and Search Bar -->
            <div class="admin-card">
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 300px;">
                        <form method="get" style="display: flex; gap: 8px;">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($filterStatus); ?>">
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="🔍 Search portfolio items..." 
                                   style="flex: 1; padding: 10px 14px; border: 2px solid #c3c4c7; border-radius: 8px; font-size: 14px;">
                            <button type="submit" class="admin-btn admin-btn-primary">Search</button>
                            <?php if ($search): ?>
                                <a href="?status=<?php echo urlencode($filterStatus); ?>" class="admin-btn admin-btn-outline">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="view-toggle">
                        <button type="button" class="view-btn-grid active" onclick="switchView('grid')" title="Grid View">⊞</button>
                        <button type="button" class="view-btn-list" onclick="switchView('list')" title="List View">☰</button>
                    </div>
                </div>
            </div>
            
            <!-- Bulk Actions Bar -->
            <div class="bulk-actions-bar" id="bulkActionsBar">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <strong id="selectedCount">0 items selected</strong>
                </div>
                <form method="post" id="bulkActionForm" style="display: flex; gap: 8px; align-items: center;">
                    <input type="hidden" name="portfolio_ids" id="bulkPortfolioIds">
                    <select name="bulk_action" class="admin-form-group" style="padding: 8px 12px; border: 2px solid #c3c4c7; border-radius: 8px;">
                        <option value="">Bulk Actions</option>
                        <option value="publish">Publish</option>
                        <option value="draft">Move to Draft</option>
                        <option value="archive">Archive</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button type="submit" class="admin-btn" onclick="return confirm('Are you sure?');">Apply</button>
                </form>
            </div>
            
            <?php if (empty($portfolios)): ?>
                <div class="admin-empty-state">
                    <div class="admin-empty-state-icon">📸</div>
                    <h3>No portfolio items found</h3>
                    <p><?php echo !empty($search) ? 'Try adjusting your search criteria.' : 'Create your first portfolio item to showcase your work.'; ?></p>
                    <a href="?action=add" class="admin-btn admin-btn-primary" style="margin-top: 16px;">Add New Portfolio Item</a>
                </div>
            <?php else: ?>
                <!-- Grid View -->
                <div class="portfolio-grid-view active" id="gridView">
                    <?php foreach ($portfolios as $item): 
                        // Determine which image to show: featured_image first, then first_image
                        $displayImage = null;
                        $imageUrl = null;
                        
                        if (!empty($item['featured_image']) && file_exists($rootPath . '/' . $item['featured_image'])) {
                            $displayImage = $item['featured_image'];
                        } elseif (!empty($item['first_image']) && file_exists($rootPath . '/' . $item['first_image'])) {
                            $displayImage = $item['first_image'];
                        }
                        
                        if ($displayImage) {
                            // Try to get thumbnail version
                            try {
                                $imageUrl = $imageResizer->getImageUrl($displayImage, 'medium', $baseUrl);
                                $thumbPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $imageUrl);
                                if (!file_exists($thumbPath) || $imageUrl === ($baseUrl . '/' . $displayImage)) {
                                    $imageUrl = $baseUrl . '/' . $displayImage;
                                }
                            } catch (Exception $e) {
                                $imageUrl = $baseUrl . '/' . $displayImage;
                            }
                        }
                    ?>
                        <div class="portfolio-item-card" data-portfolio-id="<?php echo $item['id']; ?>">
                            <div class="portfolio-item-checkbox" onclick="event.stopPropagation();">
                                <input type="checkbox" class="portfolio-checkbox" value="<?php echo $item['id']; ?>" onchange="updateBulkActions()">
                            </div>
                            <div class="portfolio-item-thumbnail" onclick="window.location='?action=edit&id=<?php echo $item['id']; ?>'">
                                <?php if ($imageUrl): ?>
                                    <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                         alt="<?php echo htmlspecialchars($item['title']); ?>"
                                         onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'400\' height=\'220\'%3E%3Crect fill=\'%23f1f5f9\' width=\'400\' height=\'220\'/%3E%3Ctext fill=\'%23cbd5e1\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'32\'%3E🖼️%3C/text%3E%3C/svg%3E';">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 48px; color: #cbd5e1;">🖼️</div>
                                <?php endif; ?>
                                <div class="portfolio-item-thumbnail-overlay">
                                    <button onclick="event.stopPropagation(); window.location='?action=edit&id=<?php echo $item['id']; ?>'">✏️ Edit</button>
                                    <button onclick="event.stopPropagation(); window.open('<?php echo $baseUrl; ?>/cms/portfolio/<?php echo htmlspecialchars($item['slug']); ?>', '_blank');">👁️ View</button>
                                </div>
                            </div>
                            <div class="portfolio-item-body">
                                <h3 class="portfolio-item-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <div class="portfolio-item-meta">
                                    <?php if ($item['location']): ?>
                                        <div class="portfolio-item-meta-item">
                                            <span>📍</span>
                                            <span><?php echo htmlspecialchars($item['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['image_count'] > 0): ?>
                                        <div class="portfolio-item-meta-item">
                                            <span>📸</span>
                                            <span><?php echo $item['image_count']; ?> image<?php echo $item['image_count'] !== 1 ? 's' : ''; ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($item['project_date']): ?>
                                        <div class="portfolio-item-meta-item">
                                            <span>📅</span>
                                            <span><?php echo date('M Y', strtotime($item['project_date'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="margin-bottom: 12px;">
                                    <span class="portfolio-item-status <?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
                                </div>
                                <div class="portfolio-item-actions">
                                    <a href="?action=edit&id=<?php echo $item['id']; ?>" class="admin-btn admin-btn-outline" style="flex: 1; text-align: center; text-decoration: none;">Edit</a>
                                    <a href="<?php echo $baseUrl; ?>/cms/portfolio/<?php echo htmlspecialchars($item['slug']); ?>" target="_blank" class="admin-btn admin-btn-outline" style="flex: 1; text-align: center; text-decoration: none;">View</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- List View -->
                <div class="portfolio-list-view" id="listView">
                    <div class="admin-table-wrapper">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                                    </th>
                                    <th style="width: 120px;">Thumbnail</th>
                                    <th>Title</th>
                                    <th>Location</th>
                                    <th>Client</th>
                                    <th>Images</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th style="width: 150px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($portfolios as $item): 
                                    $displayImage = null;
                                    $imageUrl = null;
                                    
                                    if (!empty($item['featured_image']) && file_exists($rootPath . '/' . $item['featured_image'])) {
                                        $displayImage = $item['featured_image'];
                                    } elseif (!empty($item['first_image']) && file_exists($rootPath . '/' . $item['first_image'])) {
                                        $displayImage = $item['first_image'];
                                    }
                                    
                                    if ($displayImage) {
                                        try {
                                            $imageUrl = $imageResizer->getImageUrl($displayImage, 'thumbnail', $baseUrl);
                                            $thumbPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $imageUrl);
                                            if (!file_exists($thumbPath) || $imageUrl === ($baseUrl . '/' . $displayImage)) {
                                                $imageUrl = $baseUrl . '/' . $displayImage;
                                            }
                                        } catch (Exception $e) {
                                            $imageUrl = $baseUrl . '/' . $displayImage;
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="portfolio-checkbox" value="<?php echo $item['id']; ?>" onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <?php if ($imageUrl): ?>
                                                <img src="<?php echo htmlspecialchars($imageUrl); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                     style="width: 80px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #c3c4c7;"
                                                     onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'60\'%3E%3Crect fill=\'%23f1f5f9\' width=\'80\' height=\'60\'/%3E%3Ctext fill=\'%23cbd5e1\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'20\'%3E🖼️%3C/text%3E%3C/svg%3E';">
                                            <?php else: ?>
                                                <div style="width: 80px; height: 60px; background: #f1f5f9; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #cbd5e1;">🖼️</div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['location'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['client_name'] ?? '-'); ?></td>
                                        <td>
                                            <span class="admin-badge"><?php echo $item['image_count'] ?? 0; ?> images</span>
                                        </td>
                                        <td>
                                            <span class="portfolio-item-status <?php echo $item['status']; ?>"><?php echo ucfirst($item['status']); ?></span>
                                        </td>
                                        <td><?php echo $item['project_date'] ? date('M d, Y', strtotime($item['project_date'])) : '-'; ?></td>
                                        <td>
                                            <div class="admin-actions">
                                                <a href="?action=edit&id=<?php echo $item['id']; ?>" class="admin-action-btn">Edit</a>
                                                <a href="<?php echo $baseUrl; ?>/cms/portfolio/<?php echo htmlspecialchars($item['slug']); ?>" target="_blank" class="admin-action-btn">View</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Add/Edit Form -->
            <div class="admin-page-header">
                <h1><?php echo $id ? '✏️ Edit Portfolio Item' : '➕ Add New Portfolio Item'; ?></h1>
                <p><?php echo $id ? 'Update portfolio item details and images' : 'Create a new portfolio item to showcase your work'; ?></p>
                <div class="admin-page-actions">
                    <a href="?" class="admin-btn admin-btn-outline">← Back to List</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="admin-notice admin-notice-<?php echo $messageType === 'error' ? 'error' : 'success'; ?>">
                    <span class="admin-notice-icon"><?php echo $messageType === 'error' ? '⚠' : '✓'; ?></span>
                    <div class="admin-notice-content">
                        <strong><?php echo $messageType === 'error' ? 'Error' : 'Success'; ?>!</strong>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data" id="portfolio-form" onsubmit="handleFormSubmit(event)">
                <!-- Loading overlay for save feedback -->
                <div id="save-overlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; align-items: center; justify-content: center;">
                    <div style="background: white; padding: 40px; border-radius: 16px; text-align: center; box-shadow: 0 8px 32px rgba(0,0,0,0.3); max-width: 400px;">
                        <div style="font-size: 48px; margin-bottom: 20px;">⏳</div>
                        <h3 style="margin: 0 0 12px 0; color: #1e293b; font-size: 20px;">Saving Portfolio...</h3>
                        <p style="margin: 0; color: #64748b; font-size: 14px;">Please wait while we save your changes</p>
                        <div style="margin-top: 24px; width: 100%; height: 4px; background: #e2e8f0; border-radius: 2px; overflow: hidden;">
                            <div id="save-progress" style="height: 100%; background: linear-gradient(90deg, #2563eb, #1e40af); width: 0%; transition: width 0.3s; animation: pulse 1.5s ease-in-out infinite;"></div>
                        </div>
                    </div>
                </div>
                
                <div class="portfolio-editor-container">
                    <!-- Main Editor Column -->
                    <div class="portfolio-editor-main">
                        <!-- Basic Information Section -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">📋</div>
                                <h3>Basic Information</h3>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Title *</label>
                                <input type="text" name="title" value="<?php echo htmlspecialchars($portfolio['title'] ?? ''); ?>" required class="large-text" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; transition: border-color 0.2s;" onfocus="this.style.borderColor='#2563eb';" onblur="this.style.borderColor='#e2e8f0';">
                                <div class="admin-form-help">The title of this portfolio item (e.g., "Borehole Drilling Project - Accra")</div>
                            </div>
                            
                            <div class="form-row-2">
                                <div class="admin-form-group">
                                    <label>Slug *</label>
                                    <input type="text" name="slug" value="<?php echo htmlspecialchars($portfolio['slug'] ?? ''); ?>" required class="large-text" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                    <div class="admin-form-help">URL-friendly version (auto-generated if empty)</div>
                                </div>
                                
                                <div class="admin-form-group">
                                    <label>Status</label>
                                    <select name="status" class="large-text" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                        <option value="draft" <?php echo ($portfolio['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                        <option value="published" <?php echo ($portfolio['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        <option value="archived" <?php echo ($portfolio['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Description</label>
                                <textarea name="description" rows="5" class="large-text" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px; font-family: inherit; resize: vertical;"><?php echo htmlspecialchars($portfolio['description'] ?? ''); ?></textarea>
                                <div class="admin-form-help">Detailed description of the project/work</div>
                            </div>
                            
                            <div class="form-row-3">
                                <div class="admin-form-group">
                                    <label>📍 Location</label>
                                    <input type="text" name="location" value="<?php echo htmlspecialchars($portfolio['location'] ?? ''); ?>" class="large-text" placeholder="e.g., Accra, Ghana" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                </div>
                                
                                <div class="admin-form-group">
                                    <label>👤 Client Name</label>
                                    <input type="text" name="client_name" value="<?php echo htmlspecialchars($portfolio['client_name'] ?? ''); ?>" class="large-text" placeholder="Client name (optional)" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                </div>
                                
                                <div class="admin-form-group">
                                    <label>📅 Project Date</label>
                                    <input type="date" name="project_date" value="<?php echo $portfolio['project_date'] ?? ''; ?>" class="large-text" style="width: 100%; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                </div>
                            </div>
                            
                            <div class="admin-form-group">
                                <label>Display Order</label>
                                <input type="number" name="display_order" value="<?php echo $portfolio['display_order'] ?? 0; ?>" class="large-text" style="width: 200px; padding: 12px 16px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 15px;">
                                <div class="admin-form-help">Lower numbers appear first in listings</div>
                            </div>
                        </div>
                        
                        <!-- Gallery Images Section -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">📸</div>
                                <h3>Gallery Images</h3>
                            </div>
                            <div style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                                <label for="portfolio_images" class="admin-btn admin-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer;">
                                    <span>📸</span> Upload Images
                                </label>
                                <input type="file" name="portfolio_images[]" id="portfolio_images" accept="image/*" multiple style="display: none;" onchange="previewPortfolioImages(this)">
                                <button type="button" id="select-gallery-from-media" class="admin-btn admin-btn-outline" style="display: inline-flex; align-items: center; gap: 8px;">
                                    <span>📁</span> Select from Media
                                </button>
                            </div>
                            <div class="admin-form-help" style="margin-bottom: 16px;">Select multiple images to add to the gallery. Drag and drop to reorder.</div>
                            
                            <!-- Preview of selected files (before upload) -->
                            <div id="new-images-preview" class="gallery-manager" style="margin-bottom: 24px;"></div>
                    
                            <!-- Existing images gallery with drag-and-drop -->
                            <?php if ($id && !empty($portfolioImages)): ?>
                                <div class="portfolio-gallery-grid gallery-manager" id="portfolio-gallery-grid">
                                <?php foreach ($portfolioImages as $index => $img): 
                                    $imgPath = $rootPath . '/' . $img['image_path'];
                                    $imgExists = file_exists($imgPath);
                                    if ($imgExists) {
                                        try {
                                            $thumbUrl = $imageResizer->getImageUrl($img['image_path'], 'thumbnail', $baseUrl);
                                            $thumbPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $thumbUrl);
                                            if (!file_exists($thumbPath) || $thumbUrl === ($baseUrl . '/' . $img['image_path'])) {
                                                $thumbUrl = $baseUrl . '/' . $img['image_path'];
                                            }
                                        } catch (Exception $e) {
                                            $thumbUrl = $baseUrl . '/' . $img['image_path'];
                                        }
                                    } else {
                                        $thumbUrl = null;
                                    }
                                ?>
                                    <div class="portfolio-gallery-item" data-image-id="<?php echo $img['id']; ?>">
                                        <div class="portfolio-gallery-item-order"><?php echo $index + 1; ?></div>
                                        <?php if ($thumbUrl): ?>
                                            <img src="<?php echo htmlspecialchars($thumbUrl); ?>" alt="<?php echo htmlspecialchars($img['image_alt']); ?>"
                                                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'180\' height=\'180\'%3E%3Crect fill=\'%23f1f5f9\' width=\'180\' height=\'180\'/%3E%3Ctext fill=\'%23cbd5e1\' x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' font-size=\'24\'%3E🖼️%3C/text%3E%3C/svg%3E';">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 180px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #cbd5e1;">🖼️</div>
                                        <?php endif; ?>
                                        <div class="portfolio-gallery-item-actions">
                                            <button type="button" onclick="editImageModal(<?php echo $img['id']; ?>, '<?php echo htmlspecialchars(addslashes($img['image_alt'])); ?>', '<?php echo htmlspecialchars(addslashes($img['image_caption'])); ?>', <?php echo $img['display_order']; ?>)" title="Edit">✏️</button>
                                            <button type="button" onclick="removeImage(<?php echo $img['id']; ?>)" class="delete-btn" title="Delete">🗑️</button>
                                        </div>
                                        <div class="portfolio-gallery-item-info">
                                            <strong><?php echo htmlspecialchars($img['image_alt'] ?: 'Image ' . ($index + 1)); ?></strong>
                                            <?php if (!empty($img['image_caption'])): ?>
                                                <div style="margin-top: 4px; font-size: 10px; color: #8b949e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($img['image_caption']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="update_images[<?php echo $img['id']; ?>][alt]" id="img-alt-<?php echo $img['id']; ?>" value="<?php echo htmlspecialchars($img['image_alt']); ?>">
                                        <input type="hidden" name="update_images[<?php echo $img['id']; ?>][caption]" id="img-caption-<?php echo $img['id']; ?>" value="<?php echo htmlspecialchars($img['image_caption']); ?>">
                                        <input type="hidden" name="update_images[<?php echo $img['id']; ?>][order]" id="img-order-<?php echo $img['id']; ?>" class="image-order-input" value="<?php echo $img['display_order']; ?>">
                                    </div>
                                <?php endforeach; ?>
                                </div>
                                <div class="admin-form-help" style="margin-top: 16px;">💡 <strong>Tip:</strong> Drag and drop images to reorder them. The order determines how they appear in the gallery.</div>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #94a3b8; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0;">
                                    <div style="font-size: 48px; margin-bottom: 12px;">📸</div>
                                    <p style="margin: 0; font-weight: 600; color: #64748b;">No gallery images yet</p>
                                    <p style="margin: 8px 0 0 0; font-size: 13px;">Upload images or select from media library to create a gallery</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Form Actions -->
                        <div style="display: flex; gap: 12px; margin-top: 24px; padding-top: 24px; border-top: 2px solid #f1f5f9;">
                            <button type="submit" name="save_portfolio" class="admin-btn admin-btn-primary" style="padding: 14px 28px; font-size: 16px; font-weight: 600;">
                                <?php echo $id ? '💾 Update Portfolio Item' : '➕ Create Portfolio Item'; ?>
                            </button>
                            <a href="?" class="admin-btn admin-btn-outline" style="padding: 14px 28px; font-size: 16px;">Cancel</a>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="portfolio-editor-sidebar">
                        <!-- Quick Actions -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">⚡</div>
                                <h3>Quick Actions</h3>
                            </div>
                            <div class="quick-actions">
                                <?php if ($id && $portfolio): ?>
                                    <a href="<?php echo $baseUrl; ?>/cms/portfolio/<?php echo htmlspecialchars($portfolio['slug']); ?>" target="_blank" class="quick-action-btn">
                                        <span class="icon">👁️</span>
                                        <span>View Public</span>
                                    </a>
                                    <a href="?action=clone&id=<?php echo $id; ?>" class="quick-action-btn" onclick="return confirm('Create a copy of this portfolio item?');">
                                        <span class="icon">📋</span>
                                        <span>Clone Item</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Featured Image Section -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">🖼️</div>
                                <h3>Featured Image</h3>
                            </div>
                            
                            <!-- Featured Image Preview (Large) -->
                            <div id="featured-image-thumbnail" style="margin-bottom: 16px; text-align: center; min-height: 300px; display: flex; align-items: center; justify-content: center; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0; position: relative; overflow: hidden; padding: 16px;">
                                <?php 
                                $featImgUrl = null;
                                $imagePathToUse = null;
                                
                                // First, try to use featured_image if set
                                if (!empty($portfolio['featured_image'])) {
                                    $imagePathToUse = $portfolio['featured_image'];
                                } 
                                // If no featured image, use first gallery image as fallback
                                elseif (!empty($portfolioImages) && !empty($portfolioImages[0]['image_path'])) {
                                    $imagePathToUse = $portfolioImages[0]['image_path'];
                                }
                                
                                if ($imagePathToUse): 
                                    // Try multiple path variations
                                    $pathVariations = [
                                        $rootPath . '/' . ltrim($imagePathToUse, '/'),
                                        $rootPath . '/' . $imagePathToUse,
                                        $rootPath . $imagePathToUse,
                                        $imagePathToUse, // Absolute path
                                    ];
                                    
                                    $foundPath = null;
                                    foreach ($pathVariations as $testPath) {
                                        if (file_exists($testPath)) {
                                            $foundPath = $testPath;
                                            break;
                                        }
                                    }
                                    
                                    if ($foundPath) {
                                        // Determine the correct URL path
                                        $relativePath = str_replace($rootPath . '/', '', $foundPath);
                                        $relativePath = ltrim($relativePath, '/');
                                        
                                        // Try to get optimized version first
                                        try {
                                            $optimizedUrl = $imageResizer->getImageUrl($relativePath, 'large', $baseUrl);
                                            // Check if optimized file exists
                                            $optPath = str_replace($baseUrl . '/', '', $optimizedUrl);
                                            $optFullPath = $rootPath . '/' . ltrim($optPath, '/');
                                            if (file_exists($optFullPath)) {
                                                $featImgUrl = $optimizedUrl;
                                            } else {
                                                $featImgUrl = $baseUrl . '/' . $relativePath;
                                            }
                                        } catch (Exception $e) {
                                            // Use original path
                                            $featImgUrl = $baseUrl . '/' . $relativePath;
                                        }
                                    }
                                endif;
                                ?>
                                <?php if ($featImgUrl): ?>
                                    <img id="featured-thumbnail-img" 
                                         src="<?php echo htmlspecialchars($featImgUrl); ?>" 
                                         alt="Featured Image" 
                                         style="max-width: 100%; max-height: 400px; width: auto; height: auto; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: block; cursor: pointer;"
                                         onclick="window.open('<?php echo htmlspecialchars($featImgUrl); ?>', '_blank')"
                                         title="Click to view full size"
                                         onerror="console.error('Image failed to load:', this.src); this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                                    <div style="display: none; text-align: center; color: #646970; flex-direction: column; align-items: center; justify-content: center;">
                                        <div style="font-size: 48px; margin-bottom: 12px;">🖼️</div>
                                        <p style="margin: 0; font-weight: 600; font-size: 14px;">Image failed to load</p>
                                        <p style="margin: 4px 0 0 0; font-size: 11px; color: #8b949e;">Path: <?php echo htmlspecialchars($portfolio['featured_image'] ?? 'N/A'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div style="text-align: center; color: #646970; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                        <div style="font-size: 48px; margin-bottom: 12px;">🖼️</div>
                                        <p style="margin: 0; font-weight: 600; font-size: 14px;"><?php echo !empty($portfolio['featured_image']) ? 'Image file not found' : 'No featured image'; ?></p>
                                        <?php if (!empty($portfolio['featured_image'])): ?>
                                            <p style="margin: 4px 0 0 0; font-size: 11px; color: #8b949e; word-break: break-all;">Path: <?php echo htmlspecialchars($portfolio['featured_image']); ?></p>
                                        <?php elseif (empty($portfolioImages)): ?>
                                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #8b949e;">Select or upload an image</p>
                                        <?php else: ?>
                                            <p style="margin: 8px 0 0 0; font-size: 12px; color: #8b949e;">Set a featured image or it will use the first gallery image</p>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Full Preview (hidden, used for main column) -->
                            <div id="featured-image-preview" class="featured-image-panel <?php echo !empty($portfolio['featured_image']) ? 'has-image' : ''; ?>" style="display: none;">
                                <?php if (!empty($portfolio['featured_image'])): 
                                    $featImgPath = $rootPath . '/' . $portfolio['featured_image'];
                                    $featImgExists = file_exists($featImgPath);
                                    if ($featImgExists) {
                                        try {
                                            $featImgUrl = $imageResizer->getImageUrl($portfolio['featured_image'], 'large', $baseUrl);
                                            $featThumbPath = $rootPath . '/' . str_replace($baseUrl . '/', '', $featImgUrl);
                                            if (!file_exists($featThumbPath) || $featImgUrl === ($baseUrl . '/' . $portfolio['featured_image'])) {
                                                $featImgUrl = $baseUrl . '/' . $portfolio['featured_image'];
                                            }
                                        } catch (Exception $e) {
                                            $featImgUrl = $baseUrl . '/' . $portfolio['featured_image'];
                                        }
                                    } else {
                                        $featImgUrl = null;
                                    }
                                ?>
                                    <?php if ($featImgUrl): ?>
                                        <img id="featured-preview-img" src="<?php echo htmlspecialchars($featImgUrl); ?>" alt="Featured" style="max-width: 100%; max-height: 400px; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1);" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div style="display: none; text-align: center; color: #646970;">
                                            <div style="font-size: 64px; margin-bottom: 16px;">🖼️</div>
                                            <p style="margin: 0; font-weight: 600;">Image not found</p>
                                        </div>
                                        <input type="hidden" name="existing_featured_image" id="existing-featured" value="<?php echo htmlspecialchars($portfolio['featured_image']); ?>">
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="image-actions" style="display: flex; flex-direction: column; gap: 8px;">
                                <button type="button" id="select-from-media" class="admin-btn admin-btn-primary" style="display: inline-flex; align-items: center; gap: 8px; width: 100%; justify-content: center;">
                                    <span>📁</span> Select from Media
                                </button>
                                <label for="featured_image" class="admin-btn admin-btn-outline" style="display: inline-flex; align-items: center; gap: 8px; cursor: pointer; width: 100%; justify-content: center;">
                                    <span>📤</span> Upload New
                                </label>
                                <input type="file" name="featured_image" id="featured_image" accept="image/*" style="display: none;" onchange="previewFeaturedImage(this)">
                                <?php if (!empty($portfolio['featured_image'])): ?>
                                    <button type="button" class="admin-btn admin-btn-danger" onclick="clearFeaturedImage()" id="clear-featured-btn" style="width: 100%;">Remove</button>
                                <?php endif; ?>
                            </div>
                            <div class="admin-form-help" style="margin-top: 12px; font-size: 12px;">Main image for this portfolio item. Recommended: 1200×800px</div>
                        </div>
                        
                        <!-- Portfolio Info -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">ℹ️</div>
                                <h3>Portfolio Info</h3>
                            </div>
                            <div class="info-card">
                                <div class="info-item">
                                    <span class="info-item-label">Status</span>
                                    <span class="info-item-value">
                                        <span class="status-badge <?php echo $portfolio['status'] ?? 'draft'; ?>">
                                            <?php echo ucfirst($portfolio['status'] ?? 'draft'); ?>
                                        </span>
                                    </span>
                                </div>
                                <?php if ($id && $portfolio): ?>
                                    <div class="info-item">
                                        <span class="info-item-label">Created</span>
                                        <span class="info-item-value"><?php echo $portfolio['created_at'] ? date('M d, Y', strtotime($portfolio['created_at'])) : 'N/A'; ?></span>
                                    </div>
                                    <?php if ($portfolio['updated_at']): ?>
                                        <div class="info-item">
                                            <span class="info-item-label">Last Updated</span>
                                            <span class="info-item-value"><?php echo date('M d, Y', strtotime($portfolio['updated_at'])); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <span class="info-item-label">Gallery Images</span>
                                        <span class="info-item-value"><?php echo count($portfolioImages ?? []); ?> image(s)</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Featured Image Info -->
                        <div class="editor-section">
                            <div class="editor-section-header">
                                <div class="icon">📋</div>
                                <h3>Image Info</h3>
                            </div>
                            <div class="info-card">
                                <div id="featured-image-info">
                                    <?php if (!empty($portfolio['featured_image'])): 
                                        $imgPath = $rootPath . '/' . $portfolio['featured_image'];
                                        if (file_exists($imgPath)) {
                                            $imgSize = @getimagesize($imgPath);
                                            $fileSize = filesize($imgPath);
                                    ?>
                                        <div class="info-item">
                                            <span class="info-item-label">Dimensions</span>
                                            <span class="info-item-value"><?php echo $imgSize ? $imgSize[0] . ' × ' . $imgSize[1] : 'Unknown'; ?></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-item-label">File Size</span>
                                            <span class="info-item-value"><?php echo number_format($fileSize / 1024, 2); ?> KB</span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-item-label">Format</span>
                                            <span class="info-item-value"><?php echo strtoupper(pathinfo($portfolio['featured_image'], PATHINFO_EXTENSION)); ?></span>
                                        </div>
                                    <?php 
                                        } else {
                                    ?>
                                        <div class="info-item">
                                            <span class="info-item-value" style="color: #ef4444;">Image file not found</span>
                                        </div>
                                    <?php 
                                        }
                                    else:
                                    ?>
                                        <div class="info-item">
                                            <span class="info-item-value" style="color: #94a3b8;">No featured image set</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Edit Image Details Section (in sidebar) -->
                        <div class="editor-section" id="imageEditSection" style="display: none;">
                            <div class="editor-section-header">
                                <div class="icon">✏️</div>
                                <h3>Edit Image Details</h3>
                            </div>
                            <div class="admin-form-group">
                                <label>Alt Text</label>
                                <input type="text" id="modal-image-alt" placeholder="Describe the image for accessibility" class="large-text">
                                <div class="admin-form-help">Important for SEO and accessibility</div>
                            </div>
                            <div class="admin-form-group">
                                <label>Caption</label>
                                <textarea id="modal-image-caption" rows="3" placeholder="Optional caption for the image" class="large-text"></textarea>
                            </div>
                            <div class="admin-form-group">
                                <label>Display Order</label>
                                <input type="number" id="modal-image-order" value="0" min="0" class="large-text">
                                <div class="admin-form-help">Lower numbers appear first in the gallery</div>
                            </div>
                            <div style="display: flex; gap: 8px; margin-top: 16px;">
                                <button type="button" class="admin-btn admin-btn-outline" onclick="closeImageModal()" style="flex: 1;">Cancel</button>
                                <button type="button" class="admin-btn admin-btn-primary" onclick="saveImageEdit()" style="flex: 1;">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
    <script>
        let currentEditingImageId = null;
        const baseUrl = '<?php echo $baseUrl; ?>';
        let currentView = 'grid';
        
        // View toggle
        function switchView(view) {
            currentView = view;
            if (view === 'grid') {
                document.getElementById('gridView').classList.add('active');
                document.getElementById('listView').classList.remove('active');
                document.querySelector('.view-btn-grid').classList.add('active');
                document.querySelector('.view-btn-list').classList.remove('active');
            } else {
                document.getElementById('gridView').classList.remove('active');
                document.getElementById('listView').classList.add('active');
                document.querySelector('.view-btn-grid').classList.remove('active');
                document.querySelector('.view-btn-list').classList.add('active');
            }
        }
        
        // Bulk actions
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.portfolio-checkbox:checked');
            const bulkBar = document.getElementById('bulkActionsBar');
            const selectedCount = document.getElementById('selectedCount');
            const bulkIds = document.getElementById('bulkPortfolioIds');
            
            if (checkboxes.length > 0) {
                bulkBar.classList.add('active');
                selectedCount.textContent = checkboxes.length + ' item' + (checkboxes.length !== 1 ? 's' : '') + ' selected';
                bulkIds.value = Array.from(checkboxes).map(cb => cb.value).join(',');
            } else {
                bulkBar.classList.remove('active');
            }
        }
        
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('.portfolio-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            updateBulkActions();
        }
        
        // Initialize media picker for featured image
        document.addEventListener('DOMContentLoaded', function() {
            const selectFromMediaBtn = document.getElementById('select-from-media');
            if (selectFromMediaBtn) {
                selectFromMediaBtn.addEventListener('click', function() {
                    if (typeof openMediaPicker === 'function') {
                        openMediaPicker({
                            allowedTypes: ['image'],
                            multiple: false,
                            baseUrl: baseUrl,
                            onSelect: function(media) {
                                if (media && media.length > 0) {
                                    const selectedMedia = media[0];
                                    setFeaturedImage(selectedMedia.url || (baseUrl + '/' + selectedMedia.file_path), selectedMedia.file_path, selectedMedia);
                                }
                            }
                        });
                    } else {
                        alert('Media picker not available. Please upload an image file instead.');
                    }
                });
            }
            
            // Gallery media picker
            const selectGalleryMediaBtn = document.getElementById('select-gallery-from-media');
            if (selectGalleryMediaBtn) {
                selectGalleryMediaBtn.addEventListener('click', function() {
                    if (typeof openMediaPicker === 'function') {
                        openMediaPicker({
                            allowedTypes: ['image'],
                            multiple: true,
                            baseUrl: baseUrl,
                            onSelect: function(media) {
                                if (media && media.length > 0) {
                                    // Add selected images to gallery
                                    media.forEach(function(item) {
                                        addImageToGallery(item);
                                    });
                                    
                                    // Show success message
                                    showToast('Success', `${media.length} image(s) added to gallery`);
                                }
                            }
                        });
                    } else {
                        alert('Media picker not available. Please upload image files instead.');
                    }
                });
            }
            
            // Initialize drag-and-drop for gallery images
            <?php if ($id && !empty($portfolioImages)): ?>
            if (typeof jQuery !== 'undefined' && jQuery.ui && jQuery.ui.sortable) {
                jQuery('#portfolio-gallery-grid').sortable({
                    handle: '.portfolio-gallery-item',
                    placeholder: 'portfolio-gallery-item ui-sortable-placeholder',
                    tolerance: 'pointer',
                    cursor: 'move',
                    opacity: 0.8,
                    update: function(event, ui) {
                        // Update order numbers and hidden inputs
                        jQuery('#portfolio-gallery-grid .portfolio-gallery-item').each(function(index) {
                            const $item = jQuery(this);
                            $item.find('.portfolio-gallery-item-order').text(index + 1);
                            const imageId = $item.data('image-id');
                            if (imageId) {
                                // Update the order in update_images array
                                const orderInput = jQuery('#img-order-' + imageId);
                                if (orderInput.length) {
                                    orderInput.val(index);
                                }
                            }
                        });
                    }
                });
            } else {
                console.warn('jQuery UI Sortable not available. Image reordering will not work.');
            }
            <?php endif; ?>
        });
        
        // Set featured image from media library or upload
        function setFeaturedImage(imageUrl, imagePath, mediaData = null) {
            const preview = document.getElementById('featured-image-preview');
            const thumbnail = document.getElementById('featured-image-thumbnail');
            const existingInput = document.getElementById('existing-featured');
            const clearBtn = document.getElementById('clear-featured-btn');
            const fileInput = document.getElementById('featured_image');
            
            if (fileInput) {
                fileInput.value = '';
            }
            
            if (existingInput) {
                existingInput.value = imagePath;
            }
            
            // Add has-image class
            if (preview) {
                preview.classList.add('has-image');
            }
            
            // Update thumbnail in sidebar immediately
            if (thumbnail) {
                thumbnail.innerHTML = `<img id="featured-thumbnail-img" src="${imageUrl}" alt="Featured" style="max-width: 100%; max-height: 400px; width: auto; height: auto; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: block; cursor: pointer;" onclick="window.open('${imageUrl}', '_blank')" title="Click to view full size" onerror="this.style.display='none'; if(this.nextElementSibling) this.nextElementSibling.style.display='flex';"><div style="display: none; text-align: center; color: #646970; flex-direction: column; align-items: center; justify-content: center;"><div style="font-size: 48px; margin-bottom: 12px;">🖼️</div><p style="margin: 0; font-weight: 600; font-size: 14px;">Image not found</p></div>`;
            }
            
            // Try to use thumbnail if available, otherwise use full image
            if (imagePath) {
                const pathParts = imagePath.split('/');
                const filename = pathParts.pop();
                const dir = pathParts.join('/');
                const thumbnailPath = dir + '/thumbnail/' + filename;
                const thumbnailUrl = baseUrl + '/' + thumbnailPath;
                
                // Check if thumbnail exists
                const img = new Image();
                img.onerror = function() {
                    // Thumbnail doesn't exist, use full image
                    updatePreviewImage(imageUrl, imageUrl);
                };
                img.onload = function() {
                    // Thumbnail exists, use it
                    updatePreviewImage(thumbnailUrl, imageUrl);
                };
                img.src = thumbnailUrl;
            } else {
                // No path, just use the URL directly
                if (preview) {
                    updatePreviewImage(imageUrl, imageUrl);
                }
            }
            
            function updatePreviewImage(url, fullUrl) {
                if (preview) {
                    preview.innerHTML = `
                        <img id="featured-preview-img" src="${url}" alt="Featured" style="max-width: 100%; max-height: 400px; border-radius: 12px; object-fit: contain; box-shadow: 0 8px 24px rgba(0,0,0,0.12); cursor: pointer;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" onclick="window.open('${fullUrl || url}', '_blank')" title="Click to view full size">
                        <div style="display: none; text-align: center; color: #646970;">
                            <div style="font-size: 64px; margin-bottom: 16px;">🖼️</div>
                            <p style="margin: 0; font-weight: 600;">Image not found</p>
                        </div>
                        <button type="button" id="remove-featured" onclick="clearFeaturedImage()" style="position: absolute; top: 16px; right: 16px; background: #dc3232; color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; z-index: 10; box-shadow: 0 4px 8px rgba(0,0,0,0.2);">✕ Remove</button>
                    `;
                }
            }
            
            if (clearBtn) {
                clearBtn.style.display = 'inline-block';
            }
            
            updateFeaturedImageInfo(mediaData);
        }
        
        // Update featured image info panel
        function updateFeaturedImageInfo(mediaData) {
            const infoDiv = document.getElementById('featured-image-info');
            if (!infoDiv) return;
            
            if (mediaData) {
                const fileSize = mediaData.file_size ? (mediaData.file_size / 1024).toFixed(2) + ' KB' : 'Unknown';
                const fileType = mediaData.file_type ? mediaData.file_type.split('/')[1].toUpperCase() : 'Unknown';
                
                infoDiv.innerHTML = `
                    <div class="info-item">
                        <span class="info-item-label">Dimensions</span>
                        <span class="info-item-value">Loading...</span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">File Size</span>
                        <span class="info-item-value">${fileSize}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-item-label">Format</span>
                        <span class="info-item-value">${fileType}</span>
                    </div>
                `;
                
                // Get dimensions from the image
                const img = document.getElementById('featured-preview-img');
                if (img) {
                    img.onload = function() {
                        const dimItem = infoDiv.querySelector('.info-item');
                        if (dimItem) {
                            dimItem.querySelector('.info-item-value').textContent = `${img.naturalWidth} × ${img.naturalHeight}`;
                        }
                    };
                    if (img.complete) img.onload();
                }
            } else {
                const img = document.getElementById('featured-preview-img');
                if (img && img.complete) {
                    const imgObj = new Image();
                    imgObj.onload = function() {
                        infoDiv.innerHTML = `
                            <div class="info-item">
                                <span class="info-item-label">Dimensions</span>
                                <span class="info-item-value">${imgObj.width} × ${imgObj.height}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-item-label">Status</span>
                                <span class="info-item-value">Loaded</span>
                            </div>
                        `;
                    };
                    imgObj.src = img.src;
                }
            }
        }
        
        // Featured Image Preview from file upload
        function previewFeaturedImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const file = input.files[0];
                    const preview = document.getElementById('featured-image-preview');
                    const thumbnail = document.getElementById('featured-image-thumbnail');
                    
                    if (preview) {
                        preview.classList.add('has-image');
                    }
                    
                    setFeaturedImage(e.target.result, '', {
                        original_name: file.name,
                        file_size: file.size,
                        file_type: file.type
                    });
                    
                    // Update thumbnail in sidebar
                    if (thumbnail) {
                        thumbnail.innerHTML = `<img id="featured-thumbnail-img" src="${e.target.result}" alt="Featured" style="max-width: 100%; max-height: 400px; width: auto; height: auto; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: block; cursor: pointer;" onclick="window.open('${e.target.result}', '_blank')" title="Click to view full size">`;
                    }
                    
                    const img = new Image();
                    img.onload = function() {
                        const infoDiv = document.getElementById('featured-image-info');
                        if (infoDiv) {
                            infoDiv.innerHTML = `
                                <div class="info-item">
                                    <span class="info-item-label">Dimensions</span>
                                    <span class="info-item-value">${img.width} × ${img.height}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-item-label">File Size</span>
                                    <span class="info-item-value">${(file.size / 1024).toFixed(2)} KB</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-item-label">Format</span>
                                    <span class="info-item-value">${file.type.split('/')[1].toUpperCase()}</span>
                                </div>
                                <div class="info-item">
                                    <span class="info-item-value" style="color: #f59e0b;">⚠️ Will be uploaded when you save</span>
                                </div>
                            `;
                        }
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function clearFeaturedImage() {
            const preview = document.getElementById('featured-image-preview');
            const thumbnail = document.getElementById('featured-image-thumbnail');
            const existingInput = document.getElementById('existing-featured');
            const clearBtn = document.getElementById('clear-featured-btn');
            const fileInput = document.getElementById('featured_image');
            const infoDiv = document.getElementById('featured-image-info');
            
            // Remove has-image class
            if (preview) {
                preview.classList.remove('has-image');
                preview.innerHTML = `
                    <div style="text-align: center; color: #646970;">
                        <div style="font-size: 64px; margin-bottom: 16px;">🖼️</div>
                        <p style="margin: 0; font-weight: 600;">No featured image</p>
                        <p style="margin: 12px 0 0 0; font-size: 14px; color: #8b949e;">Select from media library or upload a new image</p>
                    </div>
                `;
            }
            
            // Clear thumbnail
            if (thumbnail) {
                thumbnail.innerHTML = `
                    <div style="text-align: center; color: #646970;">
                        <div style="font-size: 48px; margin-bottom: 12px;">🖼️</div>
                        <p style="margin: 0; font-weight: 600; font-size: 14px;">No featured image</p>
                        <p style="margin: 8px 0 0 0; font-size: 12px; color: #8b949e;">Select or upload an image</p>
                    </div>
                `;
            }
            
            if (fileInput) fileInput.value = '';
            if (existingInput) existingInput.value = '';
            if (clearBtn) clearBtn.style.display = 'none';
            if (infoDiv) {
                infoDiv.innerHTML = `
                    <div class="info-item">
                        <span class="info-item-value" style="color: #94a3b8;">No featured image set</span>
                    </div>
                `;
            }
        }
        
        // Portfolio Images Preview
        function previewPortfolioImages(input) {
            const preview = document.getElementById('new-images-preview');
            preview.innerHTML = '';
            
            if (input.files && input.files.length > 0) {
                Array.from(input.files).forEach((file, index) => {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'preview-item';
                            div.style.cssText = 'position: relative; border: 2px solid #2271b1; border-radius: 8px; overflow: hidden; background: #f6f7f7;';
                            div.innerHTML = `
                                <img src="${e.target.result}" alt="Preview" style="width: 100%; height: 150px; object-fit: cover; display: block;">
                                <button type="button" class="remove-preview" onclick="removePreviewImage(${index})" style="position: absolute; top: 4px; right: 4px; background: #dc3232; color: white; border: none; width: 28px; height: 28px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; line-height: 1;" title="Remove">×</button>
                                <div style="padding: 8px; background: white; font-size: 11px; color: #646970;">
                                    <div style="font-weight: 600; margin-bottom: 4px;">${file.name}</div>
                                    <div>${(file.size / 1024).toFixed(2)} KB</div>
                                </div>
                            `;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }
        }
        
        function removePreviewImage(index) {
            const input = document.getElementById('portfolio_images');
            const dt = new DataTransfer();
            Array.from(input.files).forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });
            input.files = dt.files;
            previewPortfolioImages(input);
        }
        
        // Helper function for HTML escaping
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Add image to gallery from media library
        function addImageToGallery(mediaItem) {
            const preview = document.getElementById('new-images-preview');
            if (!preview) return;
            
            // Create a hidden form field to track selected media library images
            const form = document.querySelector('form');
            let mediaInputsContainer = document.getElementById('selected-media-images');
            if (!mediaInputsContainer) {
                mediaInputsContainer = document.createElement('div');
                mediaInputsContainer.id = 'selected-media-images';
                mediaInputsContainer.style.display = 'none';
                form.appendChild(mediaInputsContainer);
            }
            
            // Get media ID or file path (media picker may return id or file_path)
            const mediaIdentifier = mediaItem.id || mediaItem.file_path || mediaItem.filename;
            if (!mediaIdentifier) {
                console.error('Media item missing identifier:', mediaItem);
                return;
            }
            
            // Create hidden input for this image
            const imageId = 'media_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'selected_media_images[]';
            hiddenInput.value = mediaIdentifier;
            hiddenInput.dataset.imageId = imageId;
            mediaInputsContainer.appendChild(hiddenInput);
            
            // Get image URL - handle different possible URL formats
            let imageUrl = mediaItem.url || mediaItem.thumbnail_url;
            if (!imageUrl) {
                const filePath = mediaItem.file_path || mediaItem.filename || '';
                imageUrl = baseUrl + '/' + filePath;
            }
            // Ensure absolute URL
            if (imageUrl && !imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
                imageUrl = baseUrl + '/' + imageUrl;
            }
            
            const imageName = mediaItem.original_name || mediaItem.name || mediaItem.filename || 'Image';
            const imageSize = mediaItem.file_size ? (mediaItem.file_size / 1024).toFixed(2) + ' KB' : 'Unknown';
            
            // Create preview card
            const div = document.createElement('div');
            div.className = 'portfolio-gallery-item';
            div.dataset.mediaId = imageId;
            div.style.cssText = 'position: relative; border: 2px solid #2563eb; border-radius: 8px; overflow: hidden; background: #f6f7f7;';
            
            // Try to get thumbnail - use thumbnail_url if available, otherwise use main image
            const thumbUrl = mediaItem.thumbnail_url || imageUrl;
            
            div.innerHTML = `
                <img src="${thumbUrl}" alt="${escapeHtml(imageName)}" style="width: 100%; height: 180px; object-fit: cover; display: block;" onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div style="display: none; width: 100%; height: 180px; align-items: center; justify-content: center; background: #f1f5f9; color: #94a3b8; font-size: 32px;">🖼️</div>
                <div class="portfolio-gallery-item-actions" style="opacity: 1;">
                    <button type="button" onclick="removeMediaImage('${imageId}')" class="delete-btn" title="Remove">🗑️</button>
                </div>
                <div class="portfolio-gallery-item-info">
                    <strong>${escapeHtml(imageName)}</strong>
                    <div style="margin-top: 4px; font-size: 10px; color: #8b949e;">${imageSize} - From Media Library</div>
                </div>
            `;
            
            preview.appendChild(div);
            
            // Show message if preview was empty
            const emptyState = preview.querySelector('.empty-gallery-state');
            if (emptyState) {
                emptyState.remove();
            }
        }
        
        function removeMediaImage(imageId) {
            const item = document.querySelector(`[data-media-id="${imageId}"]`);
            if (item) {
                // Remove hidden input
                const hiddenInput = document.querySelector(`input[data-image-id="${imageId}"]`);
                if (hiddenInput) {
                    hiddenInput.remove();
                }
                // Remove preview
                item.remove();
            }
        }
        
        // Image Edit Modal
        function editImageModal(imageId, alt, caption, order) {
            currentEditingImageId = imageId;
            document.getElementById('modal-image-alt').value = alt || '';
            document.getElementById('modal-image-caption').value = caption || '';
            document.getElementById('modal-image-order').value = order || 0;
            
            // Show the sidebar section instead of modal
            const editSection = document.getElementById('imageEditSection');
            if (editSection) {
                editSection.style.display = 'block';
                // Scroll to the section
                editSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                // Focus on first input
                document.getElementById('modal-image-alt').focus();
            }
        }
        
        function closeImageModal() {
            const editSection = document.getElementById('imageEditSection');
            if (editSection) {
                editSection.style.display = 'none';
            }
            currentEditingImageId = null;
            // Clear form
            document.getElementById('modal-image-alt').value = '';
            document.getElementById('modal-image-caption').value = '';
            document.getElementById('modal-image-order').value = '0';
        }
        
        function saveImageEdit() {
            if (!currentEditingImageId) return;
            
            const alt = document.getElementById('modal-image-alt').value;
            const caption = document.getElementById('modal-image-caption').value;
            const order = document.getElementById('modal-image-order').value;
            
            document.getElementById(`img-alt-${currentEditingImageId}`).value = alt;
            document.getElementById(`img-caption-${currentEditingImageId}`).value = caption;
            document.getElementById(`img-order-${currentEditingImageId}`).value = order;
            
            // Update display
            const galleryItem = document.querySelector(`[data-image-id="${currentEditingImageId}"]`);
            if (galleryItem) {
                const infoDiv = galleryItem.querySelector('.portfolio-gallery-item-info');
                if (infoDiv) {
                    infoDiv.innerHTML = `
                        <strong>${alt || 'Image'}</strong>
                        ${caption ? '<div style="margin-top: 4px; font-size: 10px; color: #8b949e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + caption + '</div>' : ''}
                    `;
                }
            }
            
            closeImageModal();
        }
        
        function removeImage(imageId) {
            if (!confirm('Are you sure you want to delete this image? This action cannot be undone.')) {
                return;
            }
            
            const item = document.querySelector(`[data-image-id="${imageId}"]`);
            if (!item) {
                alert('Image element not found');
                return;
            }
            
            // Show loading state
            const deleteBtn = item.querySelector('.delete-btn');
            const originalHTML = deleteBtn ? deleteBtn.innerHTML : '';
            if (deleteBtn) {
                deleteBtn.innerHTML = '⏳';
                deleteBtn.disabled = true;
                deleteBtn.style.pointerEvents = 'none';
            }
            item.style.opacity = '0.6';
            
            // Send AJAX delete request
            const formData = new FormData();
            formData.append('ajax_delete_image', '1');
            formData.append('image_id', imageId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove item with animation
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'scale(0.8)';
                    setTimeout(() => {
                        item.remove();
                        // Update order numbers
                        document.querySelectorAll('.portfolio-gallery-item').forEach((el, index) => {
                            const orderEl = el.querySelector('.portfolio-gallery-item-order');
                            if (orderEl) {
                                orderEl.textContent = index + 1;
                            }
                        });
                    }, 300);
                } else {
                    // Restore button
                    if (deleteBtn) {
                        deleteBtn.innerHTML = originalHTML;
                        deleteBtn.disabled = false;
                        deleteBtn.style.pointerEvents = '';
                    }
                    item.style.opacity = '1';
                    alert('Error deleting image: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                // Restore button
                if (deleteBtn) {
                    deleteBtn.innerHTML = originalHTML;
                    deleteBtn.disabled = false;
                    deleteBtn.style.pointerEvents = '';
                }
                item.style.opacity = '1';
                alert('Error deleting image: ' + error.message);
            });
        }
        
        // Auto-generate slug from title
        document.querySelector('input[name="title"]')?.addEventListener('input', function() {
            const slugInput = document.querySelector('input[name="slug"]');
            if (slugInput && !slugInput.value) {
                const slug = this.value.toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                slugInput.value = slug;
            }
        });
        
        // Handle form submission with feedback
        function handleFormSubmit(event) {
            const form = event.target;
            const overlay = document.getElementById('save-overlay');
            const progressBar = document.getElementById('save-progress');
            
            // Show loading overlay
            if (overlay) {
                overlay.style.display = 'flex';
                // Animate progress bar
                let progress = 0;
                const interval = setInterval(function() {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90;
                    if (progressBar) {
                        progressBar.style.width = progress + '%';
                    }
                }, 200);
                
                // Clear interval when form actually submits (page will reload)
                setTimeout(function() {
                    clearInterval(interval);
                    if (progressBar) {
                        progressBar.style.width = '100%';
                    }
                }, 2000);
            }
            
            // Form will submit normally, overlay will be removed on page reload
            return true;
        }
        
        // Show toast notification
        function showToast(title, message) {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.save-success-toast');
            existingToasts.forEach(toast => toast.remove());
            
            const toast = document.createElement('div');
            toast.className = 'save-success-toast';
            toast.innerHTML = `
                <div class="icon">✓</div>
                <div class="content">
                    <div class="title">${title}</div>
                    <div class="message">${message}</div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(function() {
                toast.style.animation = 'slideInRight 0.3s ease-out reverse';
                setTimeout(function() {
                    toast.remove();
                }, 300);
            }, 3000);
        }
        
        // Show success message on page load if there's a flash message
        <?php if ($message && $messageType === 'success'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                showToast('Success!', '<?php echo htmlspecialchars($message, ENT_QUOTES); ?>');
            }, 500);
        });
        <?php endif; ?>
        
        // Update preview when gallery is empty
        document.addEventListener('DOMContentLoaded', function() {
            const preview = document.getElementById('new-images-preview');
            if (preview && preview.children.length === 0) {
                // Check if there are existing gallery images
                const existingGallery = document.getElementById('portfolio-gallery-grid');
                if (!existingGallery || existingGallery.children.length === 0) {
                    preview.innerHTML = `
                        <div class="empty-gallery-state" style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8; background: #f8fafc; border-radius: 12px; border: 2px dashed #e2e8f0;">
                            <div style="font-size: 48px; margin-bottom: 12px;">📸</div>
                            <p style="margin: 0; font-weight: 600; color: #64748b;">No images selected yet</p>
                            <p style="margin: 8px 0 0 0; font-size: 13px;">Select images from media library or upload new ones</p>
                        </div>
                    `;
                }
            }
        });
    </script>
</body>
</html>
