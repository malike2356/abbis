<?php
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn() || !$cmsAuth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$baseUrl = app_base_path();

// Run menu enhancement migration if needed
$columnsToAdd = [
    'menu_name' => "VARCHAR(100) DEFAULT NULL AFTER id",
    'object_type' => "ENUM('page','post','category','custom','product','shop','blog','quote','rig-request','contact','home','portfolio','vacancies','help','complaint') DEFAULT 'custom' AFTER url",
    'object_id' => "INT DEFAULT NULL AFTER object_type",
    'menu_location' => "VARCHAR(50) DEFAULT NULL AFTER menu_type",
    'css_class' => "VARCHAR(255) DEFAULT NULL AFTER icon"
];

// Update object_type ENUM to include 'portfolio' and other types
try {
    $enumStmt = $pdo->query("SHOW COLUMNS FROM cms_menu_items WHERE Field = 'object_type'");
    $enumData = $enumStmt->fetch(PDO::FETCH_ASSOC);
    if ($enumData) {
        $currentEnum = $enumData['Type'];
        // Check if 'portfolio' or other types are missing from the ENUM
        $needsUpdate = false;
        $requiredTypes = ['portfolio', 'rig-request', 'quote', 'contact', 'vacancies', 'help', 'complaint'];
        foreach ($requiredTypes as $type) {
            if (strpos($currentEnum, $type) === false) {
                $needsUpdate = true;
                break;
            }
        }
        if ($needsUpdate) {
            // Alter the ENUM to include all supported types
            $pdo->exec("ALTER TABLE cms_menu_items MODIFY COLUMN object_type ENUM('page','post','category','custom','product','shop','blog','quote','rig-request','contact','home','portfolio','vacancies','help','complaint') DEFAULT 'custom'");
        }
    }
} catch (PDOException $e) {
    // Column might not exist yet, will be created by columnsToAdd logic below
}

$existingColumns = [];
try {
    $colsStmt = $pdo->query("SHOW COLUMNS FROM cms_menu_items");
    while ($col = $colsStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[] = $col['Field'];
    }
} catch (PDOException $e) {}

foreach ($columnsToAdd as $colName => $colDef) {
    if (!in_array($colName, $existingColumns)) {
        try {
            $pdo->exec("ALTER TABLE cms_menu_items ADD COLUMN {$colName} {$colDef}");
        } catch (PDOException $e) {}
    }
}

$indexesToAdd = [
    'idx_menu_name' => 'menu_name',
    'idx_menu_location' => 'menu_location',
    'idx_object' => 'object_type,object_id'
];

try {
    $indexesStmt = $pdo->query("SHOW INDEXES FROM cms_menu_items");
    $existingIndexes = [];
    while ($idx = $indexesStmt->fetch(PDO::FETCH_ASSOC)) {
        $existingIndexes[] = $idx['Key_name'];
    }
    
    foreach ($indexesToAdd as $idxName => $idxCols) {
        if (!in_array($idxName, $existingIndexes)) {
            try {
                if (strpos($idxCols, ',') !== false) {
                    $cols = '`' . str_replace(',', '`, `', $idxCols) . '`';
                } else {
                    $cols = "`{$idxCols}`";
                }
                $pdo->exec("ALTER TABLE cms_menu_items ADD INDEX {$idxName} ({$cols})");
            } catch (PDOException $e) {}
        }
    }
} catch (PDOException $e) {}

try {
    $pdo->query("SELECT 1 FROM cms_menu_locations LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_menu_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        location_name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        menu_name VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_location (location_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    
    try {
        $pdo->exec("INSERT IGNORE INTO cms_menu_locations (location_name, display_name, description) VALUES
            ('primary', 'Primary Menu', 'Main navigation menu displayed in the header'),
            ('footer', 'Footer Menu', 'Menu displayed in the footer'),
            ('sidebar', 'Sidebar Menu', 'Menu displayed in the sidebar (if theme supports it)')");
    } catch (PDOException $e2) {}
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_menu_order':
            $items = json_decode($_POST['items'] ?? '[]', true);
            foreach ($items as $order => $item) {
                $parentId = isset($item['parent_id']) && $item['parent_id'] ? (int)$item['parent_id'] : null;
                $stmt = $pdo->prepare("UPDATE cms_menu_items SET menu_order=?, parent_id=? WHERE id=?");
                $stmt->execute([$order, $parentId, (int)$item['id']]);
            }
            echo json_encode(['success' => true]);
            exit;
            
        case 'create_menu':
            $menuName = trim($_POST['menu_name'] ?? '');
            if (empty($menuName)) {
                echo json_encode(['success' => false, 'error' => 'Menu name is required']);
                exit;
            }
            echo json_encode(['success' => true, 'menu_name' => $menuName]);
            exit;
            
        case 'add_menu_items_bulk':
            // Handle bulk adding multiple items at once (WordPress-style)
            $menuName = $_POST['menu_name'] ?? '';
            $items = json_decode($_POST['items'] ?? '[]', true);
            
            if (empty($menuName) || empty($items)) {
                echo json_encode(['success' => false, 'error' => 'Menu name and items are required']);
                exit;
            }
            
            // Use global $baseUrl for building URLs during reordering
            $addedItems = 0;
            $skippedItems = 0;
            
            foreach ($items as $item) {
                $objectType = $item['object_type'] ?? 'custom';
                $objectId = isset($item['object_id']) && $item['object_id'] !== '' && $item['object_id'] !== null ? $item['object_id'] : null;
                $label = trim($item['label'] ?? '');
                $url = $item['url'] ?? '';
                
                // Process URL and label based on type (same logic as single add)
                if ($objectType === 'page' && $objectId) {
                    if ($objectId === 'home') {
                        $url = $baseUrl . '/';
                        $label = $label ?: 'Home';
                        $objectId = null;
                    } else {
                        $pageStmt = $pdo->prepare("SELECT slug, title FROM cms_pages WHERE id=?");
                        $pageStmt->execute([$objectId]);
                        $page = $pageStmt->fetch(PDO::FETCH_ASSOC);
                        if ($page) {
                            $url = $baseUrl . '/cms/' . $page['slug'];
                            $label = $label ?: $page['title'];
                        }
                        $objectId = (int)$objectId;
                    }
                } elseif ($objectType === 'post' && $objectId) {
                    $objectId = (int)$objectId;
                    $postStmt = $pdo->prepare("SELECT slug, title FROM cms_posts WHERE id=?");
                    $postStmt->execute([$objectId]);
                    $post = $postStmt->fetch(PDO::FETCH_ASSOC);
                    if ($post) {
                        $url = $baseUrl . '/cms/post/' . $post['slug'];
                        $label = $label ?: $post['title'];
                    }
                } elseif ($objectType === 'category' && $objectId) {
                    $objectId = (int)$objectId;
                    $catStmt = $pdo->prepare("SELECT slug, name FROM cms_categories WHERE id=?");
                    $catStmt->execute([$objectId]);
                    $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
                    if ($cat) {
                        $url = $baseUrl . '/cms/blog?category=' . $cat['slug'];
                        $label = $label ?: $cat['name'];
                    }
                } elseif ($objectType === 'portfolio' && $objectId) {
                    $objectId = (int)$objectId;
                    $portfolioStmt = $pdo->prepare("SELECT slug, title FROM cms_portfolio WHERE id=?");
                    $portfolioStmt->execute([$objectId]);
                    $portfolio = $portfolioStmt->fetch(PDO::FETCH_ASSOC);
                    if ($portfolio) {
                        $url = $baseUrl . '/cms/portfolio/' . $portfolio['slug'];
                        $label = $label ?: $portfolio['title'];
                    }
                } elseif ($objectType === 'shop') {
                    $url = $baseUrl . '/cms/shop';
                    $label = $label ?: 'Shop';
                    $objectId = null;
                } elseif ($objectType === 'blog') {
                    $url = $baseUrl . '/cms/blog';
                    $label = $label ?: 'Blog';
                    $objectId = null;
                } elseif ($objectType === 'quote') {
                    $url = $baseUrl . '/cms/quote';
                    $label = $label ?: 'Estimates';
                    $objectId = null;
                } elseif ($objectType === 'rig-request') {
                    $url = $baseUrl . '/cms/rig-request';
                    $label = $label ?: 'Request Rig';
                    $objectId = null;
                } elseif ($objectType === 'contact') {
                    $url = $baseUrl . '/cms/contact';
                    $label = $label ?: 'Contact Us';
                    $objectId = null;
                } elseif ($objectType === 'complaint') {
                    $url = $baseUrl . '/cms/complaints.php';
                    $label = $label ?: 'Complaints';
                    $objectId = null;
                } elseif ($objectType === 'portfolio' && !$objectId) {
                    // Portfolio main page (all items)
                    $url = $baseUrl . '/cms/portfolio';
                    $label = $label ?: 'Portfolio';
                    $objectId = null;
                } elseif ($objectType === 'vacancies') {
                    $url = $baseUrl . '/cms/vacancies';
                    $label = $label ?: 'Vacancies';
                    $objectId = null;
                } elseif ($objectType === 'help') {
                    $url = $baseUrl . '/modules/help.php';
                    $label = $label ?: 'Help Center';
                    $objectId = null;
                } elseif ($objectType === 'home') {
                    $url = $baseUrl . '/';
                    $label = $label ?: 'Home';
                    $objectId = null;
                }
                
                if (empty($label)) {
                    $label = 'Untitled';
                }
                
                $maxOrderStmt = $pdo->prepare("SELECT COALESCE(MAX(menu_order), -1) + 1 FROM cms_menu_items WHERE menu_name=?");
                $maxOrderStmt->execute([$menuName]);
                $menuOrder = $maxOrderStmt->fetchColumn();
                
                try {
                    if ($objectId !== null && !is_numeric($objectId)) {
                        $objectId = null;
                    } elseif ($objectId !== null) {
                        $objectId = (int)$objectId;
                    }
                    
                    $stmt = $pdo->prepare("INSERT INTO cms_menu_items (menu_name, label, url, object_type, object_id, parent_id, menu_order, menu_type, menu_location) VALUES (?, ?, ?, ?, ?, ?, ?, 'primary', NULL)");
                    $stmt->execute([$menuName, $label, $url, $objectType, $objectId, null, $menuOrder]);
                    $addedItems++;
                } catch (PDOException $e) {
                    $skippedItems++;
                }
            }
            
            echo json_encode(['success' => true, 'added' => $addedItems, 'skipped' => $skippedItems]);
            exit;
            
        case 'add_menu_item':
            $menuName = $_POST['menu_name'] ?? '';
            $label = trim($_POST['label'] ?? '');
            $objectType = $_POST['object_type'] ?? 'custom';
            $objectId = isset($_POST['object_id']) && $_POST['object_id'] !== '' && $_POST['object_id'] !== null ? $_POST['object_id'] : null;
            $url = $_POST['url'] ?? '';
            $parentId = isset($_POST['parent_id']) && $_POST['parent_id'] ? (int)$_POST['parent_id'] : null;
            
            if (empty($menuName)) {
                echo json_encode(['success' => false, 'error' => 'Menu name is required']);
                exit;
            }
            
            $baseUrl = app_base_path();
            
            if ($objectType === 'page' && $objectId) {
                // Handle homepage specially
                if ($objectId === 'home') {
                    $url = $baseUrl . '/';
                    $label = $label ?: 'Home';
                    $objectId = null; // Set to NULL for database since object_id is INT
                } else {
                    $pageStmt = $pdo->prepare("SELECT slug, title FROM cms_pages WHERE id=?");
                    $pageStmt->execute([$objectId]);
                    $page = $pageStmt->fetch(PDO::FETCH_ASSOC);
                    if ($page) {
                        $url = $baseUrl . '/cms/' . $page['slug'];
                        $label = $label ?: $page['title'];
                    }
                    // Ensure objectId is an integer for database
                    $objectId = (int)$objectId;
                }
            } elseif ($objectType === 'post' && $objectId) {
                // Ensure objectId is an integer for database
                $objectId = (int)$objectId;
                $postStmt = $pdo->prepare("SELECT slug, title FROM cms_posts WHERE id=?");
                $postStmt->execute([$objectId]);
                $post = $postStmt->fetch(PDO::FETCH_ASSOC);
                if ($post) {
                    $url = $baseUrl . '/cms/post/' . $post['slug'];
                    $label = $label ?: $post['title'];
                }
            } elseif ($objectType === 'category' && $objectId) {
                // Ensure objectId is an integer for database
                $objectId = (int)$objectId;
                $catStmt = $pdo->prepare("SELECT slug, name FROM cms_categories WHERE id=?");
                $catStmt->execute([$objectId]);
                $cat = $catStmt->fetch(PDO::FETCH_ASSOC);
                if ($cat) {
                    $url = $baseUrl . '/cms/blog?category=' . $cat['slug'];
                    $label = $label ?: $cat['name'];
                }
            } elseif ($objectType === 'shop') {
                $url = $baseUrl . '/cms/shop';
                $label = $label ?: 'Shop';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'blog') {
                $url = $baseUrl . '/cms/blog';
                $label = $label ?: 'Blog';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'quote') {
                $url = $baseUrl . '/cms/quote';
                $label = $label ?: 'Estimates';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'rig-request') {
                $url = $baseUrl . '/cms/rig-request';
                $label = $label ?: 'Request Rig';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'contact') {
                $url = $baseUrl . '/cms/contact';
                $label = $label ?: 'Contact Us';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'complaint') {
                $url = $baseUrl . '/cms/complaints.php';
                $label = $label ?: 'Complaints';
                $objectId = null;
            } elseif ($objectType === 'portfolio' && !$objectId) {
                // Portfolio main page (all items)
                $url = $baseUrl . '/cms/portfolio';
                $label = $label ?: 'Portfolio';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'vacancies') {
                $url = $baseUrl . '/cms/vacancies';
                $label = $label ?: 'Vacancies';
                $objectId = null;
            } elseif ($objectType === 'help') {
                $url = $baseUrl . '/modules/help.php';
                $label = $label ?: 'Help Center';
                $objectId = null;
            } elseif ($objectType === 'home') {
                $url = $baseUrl . '/';
                $label = $label ?: 'Home';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'portfolio' && $objectId) {
                // Ensure objectId is an integer for database
                $objectId = (int)$objectId;
                $portfolioStmt = $pdo->prepare("SELECT slug, title FROM cms_portfolio WHERE id=?");
                $portfolioStmt->execute([$objectId]);
                $portfolio = $portfolioStmt->fetch(PDO::FETCH_ASSOC);
                if ($portfolio) {
                    $url = $baseUrl . '/cms/portfolio/' . $portfolio['slug'];
                    $label = $label ?: $portfolio['title'];
                }
            }
            
            if (empty($label)) {
                $label = 'Untitled';
            }
            
            $maxOrderStmt = $pdo->prepare("SELECT COALESCE(MAX(menu_order), -1) + 1 FROM cms_menu_items WHERE menu_name=?");
            $maxOrderStmt->execute([$menuName]);
            $menuOrder = $maxOrderStmt->fetchColumn();
            
            try {
                // Ensure object_id is either NULL or a valid integer
                if ($objectId !== null && !is_numeric($objectId)) {
                    $objectId = null;
                } elseif ($objectId !== null) {
                    $objectId = (int)$objectId;
                }
                
                $stmt = $pdo->prepare("INSERT INTO cms_menu_items (menu_name, label, url, object_type, object_id, parent_id, menu_order, menu_type, menu_location) VALUES (?, ?, ?, ?, ?, ?, ?, 'primary', NULL)");
                $stmt->execute([$menuName, $label, $url, $objectType, $objectId, $parentId, $menuOrder]);
                
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
            }
            exit;
            
        case 'delete_menu_item':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM cms_menu_items WHERE id=? OR parent_id=?");
            $stmt->execute([$itemId, $itemId]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'update_menu_item':
            $itemId = (int)($_POST['item_id'] ?? 0);
            $label = trim($_POST['label'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $cssClass = trim($_POST['css_class'] ?? '');
            
            $stmt = $pdo->prepare("UPDATE cms_menu_items SET label=?, url=?, css_class=? WHERE id=?");
            $stmt->execute([$label, $url, $cssClass, $itemId]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'assign_menu_location':
            $menuName = $_POST['menu_name'] ?? '';
            $location = $_POST['location'] ?? '';
            
            $stmt = $pdo->prepare("UPDATE cms_menu_locations SET menu_name=? WHERE location_name=?");
            $stmt->execute([$menuName, $location]);
            
            $stmt2 = $pdo->prepare("UPDATE cms_menu_items SET menu_location=? WHERE menu_name=?");
            $stmt2->execute([$location, $menuName]);
            
            echo json_encode(['success' => true]);
            exit;
    }
}

// Get all menus
$menusStmt = $pdo->query("SELECT DISTINCT menu_name FROM cms_menu_items WHERE menu_name IS NOT NULL AND menu_name != '' ORDER BY menu_name");
$menus = $menusStmt->fetchAll(PDO::FETCH_COLUMN);

// Get current menu
$currentMenu = $_GET['menu'] ?? ($menus[0] ?? '');
$menuItems = [];
if ($currentMenu) {
    $itemsStmt = $pdo->prepare("SELECT * FROM cms_menu_items WHERE menu_name=? ORDER BY menu_order, id");
    $itemsStmt->execute([$currentMenu]);
    $menuItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all pages, posts, categories (including homepage)
$pages = $pdo->query("SELECT id, title, slug FROM cms_pages WHERE status='published' ORDER BY CASE WHEN slug='home' THEN 0 ELSE 1 END, title")->fetchAll();

// Check if homepage exists, if not add it as a virtual entry
$homepageExists = false;
foreach ($pages as $p) {
    if ($p['slug'] === 'home') {
        $homepageExists = true;
        break;
    }
}
if (!$homepageExists) {
    array_unshift($pages, [
        'id' => 'home',
        'title' => 'Homepage',
        'slug' => 'home'
    ]);
}

$posts = $pdo->query("SELECT id, title, slug FROM cms_posts WHERE status='published' ORDER BY title")->fetchAll();
// Get categories - handle case where table might not exist
$categories = [];
try {
    $categories = $pdo->query("SELECT id, name, slug FROM cms_categories ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    // Table might not exist, create it if needed
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            description TEXT,
            parent_id INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $categories = $pdo->query("SELECT id, name, slug FROM cms_categories ORDER BY name")->fetchAll();
    } catch (PDOException $e2) {
        error_log("Failed to create/fetch categories: " . $e2->getMessage());
        $categories = [];
    }
}

// Get portfolio items
$portfolioItems = [];
try {
    $portfolioItems = $pdo->query("SELECT id, title, slug FROM cms_portfolio WHERE status='published' ORDER BY title")->fetchAll();
} catch (PDOException $e) {
    // Portfolio table might not exist
}

// Get menu locations
$locationsStmt = $pdo->query("SELECT * FROM cms_menu_locations ORDER BY id");
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

$locationMenus = [];
foreach ($locations as $loc) {
    $locationMenus[$loc['location_name']] = $loc['menu_name'];
}

require_once dirname(__DIR__) . '/public/get-site-name.php';
$companyName = getCMSSiteName('CMS Admin');
$baseUrl = app_url();
$currentPage = 'menus';

$globalSearchData = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menus - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php include 'header.php'; ?>
    <style>
        /* Menu-specific enhancements */
        .menu-management { 
            display: grid; 
            grid-template-columns: 1fr 380px; 
            gap: 24px; 
            margin-top: 24px; 
        }
        
        .menu-item { 
            background: white;
            border: 2px solid #c3c4c7; 
            padding: 14px 16px; 
            margin: 8px 0; 
            cursor: move; 
            position: relative; 
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .menu-item:hover { 
            border-color: #2563eb; 
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05) 0%, rgba(37, 99, 235, 0.02) 100%);
            transform: translateX(4px);
            box-shadow: 0 4px 8px rgba(37, 99, 235, 0.15);
        }
        
        .menu-item.ui-sortable-helper {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            transform: rotate(2deg);
            z-index: 1000;
        }
        
        .menu-item .menu-item-title { 
            font-weight: 600; 
            color: #1d2327; 
            margin-bottom: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .menu-item .menu-item-icon {
            font-size: 16px;
            opacity: 0.7;
        }
        
        .menu-item .menu-item-url { 
            font-size: 12px; 
            color: #646970;
            font-family: 'Courier New', monospace;
            background: #f6f7f7;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 4px;
        }
        
        .menu-item .menu-item-actions { 
            position: absolute; 
            right: 12px; 
            top: 12px;
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        
        .menu-item .menu-item-actions a { 
            color: #646970;
            text-decoration: none; 
            font-size: 12px; 
            padding: 6px 10px;
            border-radius: 6px;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .menu-item .menu-item-actions a.move-btn {
            background: rgba(15, 23, 42, 0.06);
            font-size: 11px;
            padding: 5px 8px;
        }
        
        .menu-item .menu-item-actions a.move-btn:hover {
            background: rgba(37, 99, 235, 0.15);
            color: #2563eb;
        }
        
        .menu-item .menu-item-actions a:hover { 
            background: #2563eb; 
            color: white;
            transform: translateY(-1px);
        }
        
        .menu-item-sub { 
            margin-left: 32px; 
            border-left: 3px solid #2563eb; 
            padding-left: 16px;
            position: relative;
        }
        
        .menu-item-sub::before {
            content: '└';
            position: absolute;
            left: -3px;
            top: -8px;
            color: #2563eb;
            font-size: 20px;
            line-height: 1;
        }
        
        .menu-item-placeholder { 
            background: rgba(37, 99, 235, 0.1); 
            border: 2px dashed #2563eb; 
            height: 60px; 
            margin: 8px 0; 
            border-radius: 8px;
            position: relative;
        }
        
        .menu-item-placeholder::after {
            content: 'Drop here';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #2563eb;
            font-weight: 600;
            font-size: 12px;
        }
        
        .menu-items-list:empty {
            min-height: 36px;
            border: 2px dashed rgba(37, 99, 235, 0.25);
            border-radius: 8px;
            margin: 6px 0 12px;
            position: relative;
        }
        
        .menu-items-list:empty::after {
            content: 'Drop items here';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 12px;
            color: rgba(37, 99, 235, 0.65);
        }
        
        .menu-items-list {
            list-style: none;
            margin: 0;
            padding: 0;
        }
        
        .quick-add { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .quick-add-btn { 
            padding: 12px 16px; 
            background: white;
            border: 2px solid #c3c4c7; 
            border-radius: 8px; 
            cursor: pointer; 
            text-align: center; 
            color: #1d2327; 
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }
        
        .quick-add-btn:hover { 
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
            color: white;
            border-color: #2563eb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }
        
        .quick-add-btn-icon {
            font-size: 24px;
        }
        
        .location-item { 
            padding: 16px; 
            background: white;
            border: 2px solid #c3c4c7; 
            border-radius: 8px;
            margin-bottom: 12px;
            transition: all 0.3s ease;
        }
        
        .location-item:hover {
            border-color: #2563eb;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.1);
        }
        
        .location-item label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1d2327;
            font-size: 14px;
        }
        
        .location-item select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.2s ease;
        }
        
        .location-item select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .location-item-description {
            font-size: 12px;
            color: #646970;
            margin-top: 8px;
            font-style: italic;
        }
        
        .menu-selector-section {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .menu-selector-controls {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .menu-selector-controls select {
            flex: 1;
            min-width: 200px;
            padding: 10px 12px;
            border: 2px solid #c3c4c7;
            border-radius: 6px;
            font-size: 14px;
        }
        
        @media (max-width: 1200px) {
            .menu-management {
                grid-template-columns: 1fr;
            }
        }
        
        /* WordPress-style tabs */
        .menu-item-tabs {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
        }
        
        .menu-tab-btn {
            padding: 10px 16px;
            background: transparent;
            border: none;
            border-bottom: 3px solid transparent;
            color: #646970;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: -2px;
        }
        
        .menu-tab-btn:hover {
            color: #2563eb;
            background: rgba(37, 99, 235, 0.05);
        }
        
        .menu-tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
            background: rgba(37, 99, 235, 0.05);
        }
        
        .menu-item-checkbox:hover {
            background: #f6f7f7;
            border-color: #2563eb;
        }
        
        .menu-item-checkbox input[type="checkbox"]:checked + span {
            color: #2563eb;
            font-weight: 600;
        }
        
        .menu-toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            align-items: flex-end;
        }
        
        .menu-toast {
            min-width: 260px;
            max-width: 360px;
            padding: 12px 16px;
            border-radius: 10px;
            box-shadow: 0 18px 38px rgba(15, 23, 42, 0.15);
            background: #2563eb;
            color: #fff;
            font-size: 13px;
            font-weight: 500;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.22s ease, transform 0.22s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .menu-toast.visible {
            opacity: 1;
            transform: translateY(0);
        }
        
        .menu-toast.menu-toast-success { background: #16a34a; }
        .menu-toast.menu-toast-error { background: #dc2626; }
        .menu-toast.menu-toast-warning { background: #d97706; }
        .menu-toast.menu-toast-info { background: #2563eb; }
        
        .menu-toast button {
            background: transparent;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }
        
        .menu-toast button:hover {
            opacity: 1;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <!-- Page Header -->
        <div class="admin-page-header">
            <h1>📋 Menu Management</h1>
            <p>
                Create and manage navigation menus for your website. Add pages, posts, categories, or custom links. 
                Organize items with drag-and-drop, create submenus, and assign menus to different locations (header, footer, sidebar).
            </p>
        </div>
        
        <!-- Quick Guide -->
        <div class="admin-notice admin-notice-success" style="margin-bottom: 24px;">
            <div class="admin-notice-icon">💡</div>
            <div class="admin-notice-content">
                <strong>Quick Guide:</strong>
                <ol style="margin: 8px 0 0 20px; padding: 0;">
                    <li>Create a menu or select an existing one</li>
                    <li>Add items using the quick buttons or form</li>
                    <li>Drag items to reorder or create submenus</li>
                    <li>Assign the menu to a location (Primary, Footer, Sidebar)</li>
                    <li>Click "Save Menu" to apply changes</li>
                </ol>
            </div>
        </div>
        
        <!-- Menu Selector -->
        <div class="menu-selector-section">
            <div class="menu-selector-controls">
                <label for="menu-select" style="font-weight: 600; color: #1d2327; display: flex; align-items: center; gap: 8px;">
                    <span>📋</span> Select Menu:
                </label>
                <select id="menu-select">
                    <?php if (empty($menus)): ?>
                        <option value="">No menus yet</option>
                    <?php else: ?>
                        <?php foreach ($menus as $menu): ?>
                            <option value="<?php echo htmlspecialchars($menu); ?>" <?php echo $menu === $currentMenu ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($menu); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="button" id="create-menu-btn" class="admin-btn admin-btn-primary">
                    <span>➕</span> Create New Menu
                </button>
            </div>
        </div>
        
        <div class="menu-management">
            <!-- Left: Menu Structure -->
            <div class="admin-card">
                <?php if ($currentMenu): ?>
                    <div class="admin-card-header">
                        <h2>Menu Structure: <?php echo htmlspecialchars($currentMenu); ?></h2>
                    </div>
                    
                    <div id="menu-items-container" style="min-height: 200px;">
                        <?php if (empty($menuItems)): ?>
                            <div class="admin-empty-state">
                                <div class="admin-empty-state-icon">📋</div>
                                <h3>Your menu is empty</h3>
                                <p>Add items from the right panel to get started. Use the quick buttons or the form below.</p>
                            </div>
                        <?php else: ?>
                            <?php
                            function renderMenuItemsAdmin($items, $parentId = null) {
                                $children = array_filter($items, function($item) use ($parentId) {
                                    return ($item['parent_id'] ?? null) == $parentId;
                                });

                                $dataParent = $parentId ?: '0';
                                echo '<ul class="menu-items-list" data-parent-id="' . $dataParent . '">';

                                foreach ($children as $item) {
                                    $isSub = $parentId !== null;
                                    $objectType = $item['object_type'] ?? 'custom';

                                    $icons = [
                                        'home' => '🏠',
                                        'page' => '📄',
                                        'post' => '✏️',
                                        'category' => '📁',
                                        'shop' => '🛍️',
                                        'blog' => '📝',
                                        'quote' => '💬',
                                        'rig-request' => '🚛',
                                        'contact' => '📧',
                                        'vacancies' => '💼',
                                        'help' => '❓',
                                        'complaint' => '📢',
                                        'custom' => '🔗',
                                        'product' => '🛒',
                                        'portfolio' => '📸'
                                    ];
                                    $icon = $icons[$objectType] ?? '🔗';

                                    echo '<li class="menu-item' . ($isSub ? ' menu-item-sub' : '') . '" data-id="' . $item['id'] . '">';
                                    echo '<div class="menu-item-title">';
                                    echo '<span class="menu-item-icon">' . $icon . '</span>';
                                    echo '<span>' . htmlspecialchars($item['label']) . '</span>';
                                    echo '</div>';
                                    echo '<div class="menu-item-url">' . htmlspecialchars($item['url']) . '</div>';
                                    echo '<div class="menu-item-actions">';
                                    echo '<a href="#" class="move-btn move-up" data-direction="up" title="Move up">⬆</a>';
                                    echo '<a href="#" class="move-btn move-down" data-direction="down" title="Move down">⬇</a>';
                                    echo '<a href="#" class="move-btn move-in" data-direction="in" title="Make child">↳</a>';
                                    echo '<a href="#" class="move-btn move-out" data-direction="out" title="Move out">↰</a>';
                                    echo '<a href="#" class="edit-item" data-id="' . $item['id'] . '" title="Edit">✏️ Edit</a>';
                                    echo '<a href="#" class="delete-item" data-id="' . $item['id'] . '" title="Delete">🗑️ Delete</a>';
                                    echo '</div>';

                                    // Render child container (allows dropping to create submenus)
                                    renderMenuItemsAdmin($items, $item['id']);

                                    echo '</li>';
                                }

                                echo '</ul>';
                            }
                            renderMenuItemsAdmin($menuItems, null);
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #c3c4c7; display: flex; gap: 12px; flex-wrap: wrap;">
                        <button type="button" id="save-menu-order" class="admin-btn admin-btn-primary">
                            <span>💾</span> Save Menu
                        </button>
                        <span style="color: #646970; font-size: 13px; display: flex; align-items: center; margin-left: auto;">
                            💡 Drag items to reorder or create submenus
                        </span>
                    </div>
                <?php else: ?>
                    <div class="admin-empty-state">
                        <div class="admin-empty-state-icon">📋</div>
                        <h3>No menu selected</h3>
                        <p>Create a new menu or select an existing one to start managing menu items.</p>
                        <button type="button" id="create-menu-btn-empty" class="admin-btn admin-btn-primary" style="margin-top: 16px;">
                            <span>➕</span> Create New Menu
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Right: Add Items & Locations -->
            <div style="display: flex; flex-direction: column; gap: 24px;">
                <!-- Add Menu Items - WordPress Style -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>➕ Add Menu Items</h2>
                    </div>
                    
                    <p style="color: #646970; font-size: 13px; margin-bottom: 16px;">
                        Select the type of items you want to add, then check the items and click "Add to Menu"
                    </p>
                    
                    <!-- Tabs for different item types -->
                    <div class="menu-item-tabs" style="border-bottom: 2px solid #c3c4c7; margin-bottom: 20px;">
                        <button class="menu-tab-btn active" data-tab="pages">📄 Pages</button>
                        <button class="menu-tab-btn" data-tab="posts">✏️ Posts</button>
                        <button class="menu-tab-btn" data-tab="categories">📁 Categories</button>
                        <button class="menu-tab-btn" data-tab="portfolio">📸 Portfolio</button>
                        <button class="menu-tab-btn" data-tab="links">🔗 Links</button>
                    </div>
                    
                    <!-- Tab Content: Pages -->
                    <div class="menu-tab-content active" data-content="pages">
                        <div style="margin-bottom: 16px;">
                            <input type="text" id="search-pages" placeholder="🔍 Search pages..." style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div class="menu-items-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 6px; padding: 12px;">
                            <?php if (empty($pages)): ?>
                                <p style="color: #646970; text-align: center; padding: 20px;">No pages available</p>
                            <?php else: ?>
                                <label style="display: block; margin-bottom: 12px; padding: 8px; background: #f6f7f7; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" class="select-all-pages" style="margin-right: 8px;">
                                    <strong>Select All</strong>
                                </label>
                                <?php foreach ($pages as $page): ?>
                                    <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;" data-search-text="<?php echo htmlspecialchars(strtolower($page['title'])); ?>">
                                        <input type="checkbox" name="menu-items" value="<?php echo htmlspecialchars($page['id']); ?>" data-type="page" data-label="<?php echo htmlspecialchars($page['title']); ?>" style="margin-right: 10px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($page['title']); ?></span>
                                        <span style="color: #646970; font-size: 12px; margin-left: 8px;">(<?php echo htmlspecialchars($page['slug']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-selected-items admin-btn admin-btn-primary" style="width: 100%; margin-top: 16px;" data-type="page" data-tab="pages">
                            <span>➕</span> Add Selected to Menu
                        </button>
                    </div>
                    
                    <!-- Tab Content: Posts -->
                    <div class="menu-tab-content" data-content="posts" style="display: none;">
                        <div style="margin-bottom: 16px;">
                            <input type="text" id="search-posts" placeholder="🔍 Search posts..." style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div class="menu-items-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 6px; padding: 12px;">
                            <?php if (empty($posts)): ?>
                                <p style="color: #646970; text-align: center; padding: 20px;">No posts available</p>
                            <?php else: ?>
                                <label style="display: block; margin-bottom: 12px; padding: 8px; background: #f6f7f7; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" class="select-all-posts" style="margin-right: 8px;">
                                    <strong>Select All</strong>
                                </label>
                                <?php foreach ($posts as $post): ?>
                                    <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;" data-search-text="<?php echo htmlspecialchars(strtolower($post['title'])); ?>">
                                        <input type="checkbox" name="menu-items" value="<?php echo htmlspecialchars($post['id']); ?>" data-type="post" data-label="<?php echo htmlspecialchars($post['title']); ?>" style="margin-right: 10px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($post['title']); ?></span>
                                        <span style="color: #646970; font-size: 12px; margin-left: 8px;">(<?php echo htmlspecialchars($post['slug']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-selected-items admin-btn admin-btn-primary" style="width: 100%; margin-top: 16px;" data-type="post" data-tab="posts">
                            <span>➕</span> Add Selected to Menu
                        </button>
                    </div>
                    
                    <!-- Tab Content: Categories -->
                    <div class="menu-tab-content" data-content="categories" style="display: none;">
                        <div style="margin-bottom: 16px;">
                            <input type="text" id="search-categories" placeholder="🔍 Search categories..." style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div class="menu-items-list-container" id="categories-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 6px; padding: 12px;">
                            <?php if (empty($categories)): ?>
                                <div style="text-align: center; padding: 40px 20px;">
                                    <div style="font-size: 48px; margin-bottom: 16px;">📁</div>
                                    <p style="color: #646970; font-size: 14px; margin-bottom: 8px;">No categories available</p>
                                    <p style="color: #646970; font-size: 12px;">Create categories in the <a href="categories.php" style="color: #2563eb; text-decoration: none;">Categories</a> section first.</p>
                                </div>
                            <?php else: ?>
                                <label style="display: block; margin-bottom: 12px; padding: 8px; background: #f6f7f7; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" class="select-all-categories" style="margin-right: 8px;">
                                    <strong>Select All (<?php echo count($categories); ?> categories)</strong>
                                </label>
                                <?php foreach ($categories as $cat): ?>
                                    <label class="menu-item-checkbox category-item" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;" data-search-text="<?php echo htmlspecialchars(strtolower($cat['name'] . ' ' . $cat['slug'])); ?>">
                                        <input type="checkbox" name="menu-items" value="<?php echo htmlspecialchars($cat['id']); ?>" data-type="category" data-label="<?php echo htmlspecialchars($cat['name']); ?>" style="margin-right: 10px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($cat['name']); ?></span>
                                        <span style="color: #646970; font-size: 12px; margin-left: 8px;">(<?php echo htmlspecialchars($cat['slug']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-selected-items admin-btn admin-btn-primary" style="width: 100%; margin-top: 16px;" data-type="category" data-tab="categories">
                            <span>➕</span> Add Selected to Menu
                        </button>
                    </div>
                    
                    <!-- Tab Content: Portfolio -->
                    <div class="menu-tab-content" data-content="portfolio" style="display: none;">
                        <div style="margin-bottom: 16px;">
                            <input type="text" id="search-portfolio" placeholder="🔍 Search portfolio..." style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px; font-size: 14px;">
                        </div>
                        <div class="menu-items-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 6px; padding: 12px;">
                            <?php if (empty($portfolioItems)): ?>
                                <p style="color: #646970; text-align: center; padding: 20px;">No portfolio items available</p>
                            <?php else: ?>
                                <label style="display: block; margin-bottom: 12px; padding: 8px; background: #f6f7f7; border-radius: 4px; cursor: pointer;">
                                    <input type="checkbox" class="select-all-portfolio" style="margin-right: 8px;">
                                    <strong>Select All</strong>
                                </label>
                                <?php foreach ($portfolioItems as $portfolio): ?>
                                    <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;" data-search-text="<?php echo htmlspecialchars(strtolower($portfolio['title'])); ?>">
                                        <input type="checkbox" name="menu-items" value="<?php echo htmlspecialchars($portfolio['id']); ?>" data-type="portfolio" data-label="<?php echo htmlspecialchars($portfolio['title']); ?>" style="margin-right: 10px;">
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($portfolio['title']); ?></span>
                                        <span style="color: #646970; font-size: 12px; margin-left: 8px;">(<?php echo htmlspecialchars($portfolio['slug']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="add-selected-items admin-btn admin-btn-primary" style="width: 100%; margin-top: 16px;" data-type="portfolio" data-tab="portfolio">
                            <span>➕</span> Add Selected to Menu
                        </button>
                    </div>
                    
                    <!-- Tab Content: Links (Special Pages) -->
                    <div class="menu-tab-content" data-content="links" style="display: none;">
                        <div class="menu-items-list-container" style="max-height: 400px; overflow-y: auto; border: 1px solid #c3c4c7; border-radius: 6px; padding: 12px;">
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="home" data-type="home" data-label="Home" style="margin-right: 10px;">
                                <span style="font-weight: 500;">🏠 Home</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="shop" data-type="shop" data-label="Shop" style="margin-right: 10px;">
                                <span style="font-weight: 500;">🛍️ Shop</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="blog" data-type="blog" data-label="Blog" style="margin-right: 10px;">
                                <span style="font-weight: 500;">📝 Blog</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="quote" data-type="quote" data-label="Estimates" style="margin-right: 10px;">
                                <span style="font-weight: 500;">📋 Estimates</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="rig-request" data-type="rig-request" data-label="Request Rig" style="margin-right: 10px;">
                                <span style="font-weight: 500;">🚛 Request Rig</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="contact" data-type="contact" data-label="Contact Us" style="margin-right: 10px;">
                                <span style="font-weight: 500;">📧 Contact Us</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="complaints" data-type="complaint" data-label="Complaints" style="margin-right: 10px;">
                                <span style="font-weight: 500;">📢 Complaints</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="portfolio" data-type="portfolio" data-label="Portfolio" style="margin-right: 10px;">
                                <span style="font-weight: 500;">📸 Portfolio</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="vacancies" data-type="vacancies" data-label="Vacancies" style="margin-right: 10px;">
                                <span style="font-weight: 500;">💼 Vacancies</span>
                            </label>
                            <label class="menu-item-checkbox" style="display: block; padding: 10px; margin-bottom: 8px; border: 1px solid #e0e0e0; border-radius: 4px; cursor: pointer; transition: all 0.2s;">
                                <input type="checkbox" name="menu-items" value="help" data-type="help" data-label="Help Center" style="margin-right: 10px;">
                                <span style="font-weight: 500;">❓ Help Center</span>
                            </label>
                        </div>
                        <button type="button" class="add-selected-items admin-btn admin-btn-primary" style="width: 100%; margin-top: 16px;" data-type="links" data-tab="links">
                            <span>➕</span> Add Selected to Menu
                        </button>
                        
                        <!-- Custom Link Form -->
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #c3c4c7;">
                            <h3 style="font-size: 16px; margin-bottom: 16px;">🔗 Custom Link</h3>
                            <div class="admin-form-group">
                                <label>Link Text *</label>
                                <input type="text" id="custom-link-label" placeholder="e.g., About Us" style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px;">
                            </div>
                            <div class="admin-form-group">
                                <label>URL *</label>
                                <input type="text" id="custom-link-url" placeholder="https://example.com or /path/to/page" style="width: 100%; padding: 10px; border: 2px solid #c3c4c7; border-radius: 6px;">
                            </div>
                            <button type="button" id="add-custom-link-btn" class="admin-btn admin-btn-primary" style="width: 100%;">
                                <span>➕</span> Add Custom Link to Menu
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Menu Locations -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <h2>📍 Menu Locations</h2>
                    </div>
                    <p style="color: #646970; font-size: 13px; margin-bottom: 20px;">
                        Assign menus to appear in different areas of your website. Each location can have one menu assigned.
                    </p>
                    <?php foreach ($locations as $location): ?>
                        <div class="location-item">
                            <label>
                                <?php echo htmlspecialchars($location['display_name']); ?>
                            </label>
                            <select class="menu-location-select" data-location="<?php echo htmlspecialchars($location['location_name']); ?>">
                                <option value="">— Select Menu —</option>
                                <?php foreach ($menus as $menu): ?>
                                    <option value="<?php echo htmlspecialchars($menu); ?>" 
                                        <?php echo ($locationMenus[$location['location_name']] ?? '') === $menu ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($menu); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($location['description']): ?>
                                <div class="location-item-description">
                                    <?php echo htmlspecialchars($location['description']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
    <script>
    jQuery(document).ready(function($) {
        var currentMenu = '<?php echo htmlspecialchars($currentMenu); ?>';
        var menuAjaxUrl = "<?php echo app_url('/cms/admin/menus.php'); ?>";
        
        function getToastContainer() {
            var $container = $('#menu-toast-container');
            if ($container.length === 0) {
                $container = $('<div id="menu-toast-container" class="menu-toast-container"></div>').appendTo('body');
            }
            return $container;
        }
        
        function showMenuToast(message, variant) {
            variant = variant || 'info';
            var $container = getToastContainer();
            var $toast = $('<div class="menu-toast menu-toast-' + variant + '"><span>' + message + '</span><button type="button" aria-label="Dismiss">×</button></div>');
            $container.append($toast);
            setTimeout(function() {
                $toast.addClass('visible');
            }, 10);
            setTimeout(function() {
                $toast.removeClass('visible');
                setTimeout(function() { $toast.remove(); }, 220);
            }, 3600);
            $toast.find('button').on('click', function() {
                $toast.removeClass('visible');
                setTimeout(function() { $toast.remove(); }, 180);
            });
        }
        
        function queueMenuToast(message, variant) {
            try {
                window.localStorage.setItem('cmsMenuToast', JSON.stringify({ message: message, variant: variant || 'info', time: Date.now() }));
            } catch (e) {
                console.warn('Unable to persist menu toast:', e);
            }
        }
        
        (function displayQueuedToast() {
            try {
                var queued = window.localStorage.getItem('cmsMenuToast');
                if (queued) {
                    var data = JSON.parse(queued);
                    if (data && data.message) {
                        showMenuToast(data.message, data.variant || 'info');
                    }
                    window.localStorage.removeItem('cmsMenuToast');
                }
            } catch (e) {
                console.warn('Unable to read queued menu toast:', e);
            }
        })();
        
        // Tab switching
        $('.menu-tab-btn').on('click', function() {
            var tab = $(this).data('tab');
            $('.menu-tab-btn').removeClass('active');
            $(this).addClass('active');
            $('.menu-tab-content').removeClass('active').hide();
            var $targetTab = $('.menu-tab-content[data-content="' + tab + '"]');
            $targetTab.addClass('active').show();
        });
        
        // Initialize first tab as active
        $('.menu-tab-btn.active').trigger('click');
        
        // Real-time search functionality with improved filtering
        function filterItems(searchInput, tabContent) {
            var search = $(searchInput).val().toLowerCase().trim();
            var $container = $(tabContent).find('.menu-items-list-container');
            var $items = $(tabContent).find('.menu-item-checkbox');
            var $selectAll = $(tabContent).find('.select-all-pages, .select-all-posts, .select-all-categories, .select-all-portfolio');
            var visibleCount = 0;
            
            if (search === '') {
                // Show all items when search is empty
                $items.show();
                $selectAll.closest('label').show();
                return;
            }
            
            // Hide "Select All" when searching
            $selectAll.closest('label').hide();
            
            // Filter items
            $items.each(function() {
                var $item = $(this);
                var searchText = $item.data('search-text') || '';
                var itemText = $item.text().toLowerCase();
                
                // Search in both data attribute and visible text
                if (searchText.indexOf(search) !== -1 || itemText.indexOf(search) !== -1) {
                    $item.show();
                    visibleCount++;
                } else {
                    $item.hide();
                }
            });
            
            // Show message if no results
            var $noResults = $container.find('.no-search-results');
            if (visibleCount === 0) {
                if ($noResults.length === 0) {
                    $container.append('<div class="no-search-results" style="text-align: center; padding: 40px 20px; color: #646970;"><div style="font-size: 48px; margin-bottom: 16px;">🔍</div><p style="font-size: 14px; margin-bottom: 8px;">No items found matching "' + search + '"</p><p style="font-size: 12px; color: #94a3b8;">Try a different search term</p></div>');
                } else {
                    $noResults.show();
                }
            } else {
                $noResults.remove();
            }
        }
        
        // Debounce function for better performance
        function debounce(func, wait) {
            var timeout;
            return function() {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        }
        
        // Real-time search with debounce (150ms delay for smoother experience)
        var debouncedFilterPages = debounce(function() {
            filterItems('#search-pages', '.menu-tab-content[data-content="pages"]');
        }, 150);
        
        var debouncedFilterPosts = debounce(function() {
            filterItems('#search-posts', '.menu-tab-content[data-content="posts"]');
        }, 150);
        
        var debouncedFilterCategories = debounce(function() {
            filterItems('#search-categories', '.menu-tab-content[data-content="categories"]');
        }, 150);
        
        var debouncedFilterPortfolio = debounce(function() {
            filterItems('#search-portfolio', '.menu-tab-content[data-content="portfolio"]');
        }, 150);
        
        // Bind search events - trigger on keyup, input, and paste
        $('#search-pages').on('keyup input paste', debouncedFilterPages);
        $('#search-posts').on('keyup input paste', debouncedFilterPosts);
        $('#search-categories').on('keyup input paste', debouncedFilterCategories);
        $('#search-portfolio').on('keyup input paste', debouncedFilterPortfolio);
        
        // Clear search when switching tabs
        $('.menu-tab-btn').on('click', function() {
            $('#search-pages, #search-posts, #search-categories, #search-portfolio').val('');
            $('.menu-item-checkbox').show();
            $('.select-all-pages, .select-all-posts, .select-all-categories, .select-all-portfolio').closest('label').show();
            $('.no-search-results').remove();
        });
        
        // Select All functionality
        $('.select-all-pages').on('change', function() {
            $('.menu-tab-content[data-content="pages"] input[name="menu-items"]').prop('checked', $(this).prop('checked'));
        });
        
        $('.select-all-posts').on('change', function() {
            $('.menu-tab-content[data-content="posts"] input[name="menu-items"]').prop('checked', $(this).prop('checked'));
        });
        
        $('.select-all-categories').on('change', function() {
            $('.menu-tab-content[data-content="categories"] input[name="menu-items"]').prop('checked', $(this).prop('checked'));
        });
        
        $('.select-all-portfolio').on('change', function() {
            $('.menu-tab-content[data-content="portfolio"] input[name="menu-items"]').prop('checked', $(this).prop('checked'));
        });
        
        // Bulk add selected items
        $('.add-selected-items').on('click', function() {
            if (!currentMenu) {
                showMenuToast('Please select or create a menu first.', 'warning');
                return;
            }
            
            var type = $(this).data('type');
            var tabName = $(this).data('tab') || type;
            
            // Find the correct tab content by data-content attribute
            var $targetTab = $('.menu-tab-content[data-content="' + tabName + '"]');
            
            // Fallback: try to find active or visible tab
            if ($targetTab.length === 0) {
                $targetTab = $('.menu-tab-content.active');
            }
            if ($targetTab.length === 0) {
                $targetTab = $('.menu-tab-content:visible');
            }
            
            var selectedItems = [];
            
            if (type === 'links') {
                // For links tab, get selected special pages
                $targetTab.find('input[name="menu-items"]:checked').each(function() {
                    var $input = $(this);
                    var objectType = $input.data('type');
                    var value = $input.val();
                    var objectId = null;

                    if (objectType === 'home') {
                        objectId = 'home';
                    } else if (objectType === 'help') {
                        objectId = 'help';
                    }

                    selectedItems.push({
                        object_type: objectType,
                        object_id: objectId,
                        label: $input.data('label')
                    });
                });
            } else {
                // For other tabs (pages, posts, categories, portfolio), get selected items with IDs
                $targetTab.find('input[name="menu-items"]:checked').each(function() {
                    var $input = $(this);
                    var objectId = $input.val();
                    var objectType = $input.data('type');
                    
                    // Ensure object_id is valid
                    if (objectId && objectId !== 'null' && objectId !== '' && objectId !== undefined) {
                        selectedItems.push({
                            object_type: objectType,
                            object_id: objectId,
                            label: $input.data('label')
                        });
                    }
                });
            }
            
            if (selectedItems.length === 0) {
                showMenuToast('Please select at least one item to add.', 'warning');
                return;
            }
            
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span>⏳</span> Adding...');
            
            // Debug: log what we're sending
            console.log('Adding items:', selectedItems);
            
            $.post(menuAjaxUrl, {
                action: 'add_menu_items_bulk',
                menu_name: currentMenu,
                items: JSON.stringify(selectedItems)
            }, function(response) {
                if (response && response.success) {
                    queueMenuToast('Added ' + (response.added || selectedItems.length) + ' menu item(s).', 'success');
                    location.reload();
                } else {
                    $btn.prop('disabled', false).html(originalText);
                    var errorMsg = response && response.error ? response.error : 'Failed to add items.';
                    showMenuToast(errorMsg, 'error');
                }
            }, 'json').fail(function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalText);
                console.error('AJAX Error:', status, error, xhr.responseText);
                showMenuToast('Failed to add items. Please check the console for details.', 'error');
            });
        });
        
        // Add custom link
        $('#add-custom-link-btn').on('click', function() {
            if (!currentMenu) {
                showMenuToast('Please select or create a menu first.', 'warning');
                return;
            }
            
            var label = $('#custom-link-label').val().trim();
            var url = $('#custom-link-url').val().trim();
            
            if (!label || !url) {
                showMenuToast('Please enter both link text and URL.', 'warning');
                return;
            }
            
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span>⏳</span> Adding...');
            
            $.post(menuAjaxUrl, {
                action: 'add_menu_item',
                menu_name: currentMenu,
                object_type: 'custom',
                object_id: null,
                label: label,
                url: url
            }, function(response) {
                if (response && response.success) {
                    queueMenuToast('Custom link added to menu.', 'success');
                    location.reload();
                } else {
                    $btn.prop('disabled', false).html(originalText);
                    var errorMsg = response && response.error ? response.error : 'Failed to add custom link.';
                    showMenuToast(errorMsg, 'error');
                }
            }, 'json').fail(function(xhr, status, error) {
                $btn.prop('disabled', false).html(originalText);
                console.error('AJAX Error:', status, error, xhr.responseText);
                showMenuToast('Failed to add custom link. Please check the console for details.', 'error');
            });
        });
        
        function initSortable() {
            $('.menu-items-list').sortable({
                items: 'li.menu-item',
                placeholder: 'menu-item-placeholder',
                tolerance: 'pointer',
                cursor: 'move',
                connectWith: '.menu-items-list',
                opacity: 0.85,
                helper: 'clone',
                handle: '.menu-item-title',
                greedy: true,
                revert: 150,
                start: function(e, ui) {
                    ui.placeholder.height(ui.item.outerHeight());
                },
                receive: function() { refreshMenuClasses(); },
                update: function() { refreshMenuClasses(); },
                stop: function() { refreshMenuClasses(); }
            }).disableSelection();
        }
        initSortable();

        function ensureChildList($item) {
            let $childList = $item.children('ul.menu-items-list');
            if ($childList.length === 0) {
                $childList = $('<ul class="menu-items-list" data-parent-id="' + $item.data('id') + '"></ul>');
                $item.append($childList);
                initSortable();
            } else {
                $childList.attr('data-parent-id', $item.data('id'));
            }
            return $childList;
        }

        function refreshMenuClasses() {
            $('.menu-items-list').each(function() {
                const $list = $(this);
                const parentId = $list.data('parent-id');
                const hasParent = parentId && parentId !== '0';

                $list.children('li.menu-item').each(function() {
                    const $item = $(this);
                    if (hasParent) {
                        $item.addClass('menu-item-sub');
                    } else {
                        $item.removeClass('menu-item-sub');
                    }
                    const $child = $item.children('ul.menu-items-list');
                    if ($child.length) {
                        $child.attr('data-parent-id', $item.data('id'));
                    }
                });

                if ($list.children('li.menu-item').length === 0) {
                    if (hasParent) {
                        $list.remove();
                    } else {
                        $list.addClass('empty');
                    }
                } else {
                    $list.removeClass('empty');
                }
            });
        }
        refreshMenuClasses();

        $('#menu-items-container').on('click', '.move-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const direction = $btn.data('direction');
            const $item = $btn.closest('li.menu-item');
            if (!direction || !$item.length) return;

            if (direction === 'up') {
                const $prev = $item.prev('.menu-item');
                if ($prev.length) {
                    $prev.before($item);
                }
            } else if (direction === 'down') {
                const $next = $item.next('.menu-item');
                if ($next.length) {
                    $next.after($item);
                }
            } else if (direction === 'in') {
                const $prev = $item.prev('.menu-item');
                if ($prev.length) {
                    const $childList = ensureChildList($prev);
                    $childList.append($item);
                }
            } else if (direction === 'out') {
                const $parentList = $item.parent('.menu-items-list');
                const parentId = $parentList.data('parent-id');
                if (parentId && parentId !== '0') {
                    const $parentItem = $parentList.closest('li.menu-item');
                    const $grandList = $parentItem.parent('.menu-items-list');
                    $parentItem.after($item);
                }
            }

            refreshMenuClasses();
            initSortable();
        });
        
        $('#menu-select').on('change', function() {
            window.location.href = '?menu=' + encodeURIComponent($(this).val());
        });
        
        function createMenu() {
            var menuName = prompt('Enter menu name:');
            if (menuName && menuName.trim()) {
                $.post(menuAjaxUrl, {
                    action: 'create_menu',
                    menu_name: menuName.trim()
                }, function(response) {
                    if (response.success) {
                        queueMenuToast('Menu "' + menuName.trim() + '" created.', 'success');
                        window.location.href = '?menu=' + encodeURIComponent(menuName.trim());
                    } else {
                        showMenuToast(response.error || 'Failed to create menu.', 'error');
                    }
                }, 'json').fail(function() {
                    showMenuToast('Failed to create menu. Please try again.', 'error');
                });
            }
        }
        
        $('#create-menu-btn, #create-menu-btn-empty').on('click', createMenu);
        
        $('#save-menu-order').on('click', function() {
            var $btn = $(this);
            var originalText = $btn.html();
            $btn.prop('disabled', true).html('<span class="admin-loading"></span> Saving...');
            
            var items = [];
            var order = 0;
            
            function processMenuItems($container, parentId) {
                $container.find('> li.menu-item').each(function() {
                    var $item = $(this);
                    var itemId = $item.data('id');
                    items.push({id: itemId, parent_id: parentId, order: order++});
                    
                    var $subContainer = $item.find('> ul.menu-items-list');
                    if ($subContainer.length) {
                        processMenuItems($subContainer, itemId);
                    }
                });
            }
            
            var $topLevel = $('#menu-items-container > ul.menu-items-list[data-parent-id="0"]');
            if ($topLevel.length) {
                processMenuItems($topLevel, null);
            } else {
                $('#menu-items-container > .menu-item').each(function() {
                    var $item = $(this);
                    var itemId = $item.data('id');
                    items.push({id: itemId, parent_id: null, order: order++});
                });
            }
            
            $.post(menuAjaxUrl, {
                action: 'save_menu_order',
                menu_name: currentMenu,
                items: JSON.stringify(items)
            }, function(response) {
                if (response.success) {
                    queueMenuToast('Menu order saved.', 'success');
                    location.reload();
                } else {
                    $btn.prop('disabled', false).html(originalText);
                    showMenuToast('Error saving menu. Please try again.', 'error');
                }
            }, 'json').fail(function() {
                $btn.prop('disabled', false).html(originalText);
                showMenuToast('Failed to save menu. Please try again.', 'error');
            });
        });
        
        $(document).on('click', '.delete-item', function(e) {
            e.preventDefault();
            var itemId = $(this).data('id');
            var $item = $(this).closest('.menu-item');
            var label = $item.find('.menu-item-title span:last-child').text().trim();
            
            if (!confirm('Are you sure you want to delete "' + label + '"?\n\nThis will also delete any submenu items under it.')) {
                return;
            }
            
            var $btn = $(this);
            $btn.html('⏳ Deleting...').css('pointer-events', 'none');
            
            $.post(menuAjaxUrl, {
                action: 'delete_menu_item',
                item_id: itemId
            }, function(response) {
                if (response.success) {
                    queueMenuToast('Menu item "' + label + '" removed.', 'success');
                    $item.fadeOut(300, function() {
                        location.reload();
                    });
                } else {
                    $btn.html('🗑️ Delete').css('pointer-events', 'auto');
                    showMenuToast('Error deleting menu item. Please try again.', 'error');
                }
            }, 'json').fail(function() {
                $btn.html('🗑️ Delete').css('pointer-events', 'auto');
                showMenuToast('Failed to delete menu item. Please try again.', 'error');
            });
        });
        
        $(document).on('click', '.edit-item', function(e) {
            e.preventDefault();
            var itemId = $(this).data('id');
            var $item = $(this).closest('.menu-item');
            var label = $item.find('.menu-item-title span:last-child').text().trim();
            var url = $item.find('.menu-item-url').text().trim();
            
            // Create a modal-like form with proper admin styles
            var editForm = '<div class="admin-card" style="max-width: 500px; margin: 0; position: relative; z-index: 10001;">' +
                '<div class="admin-card-header">' +
                '<h2>✏️ Edit Menu Item</h2>' +
                '</div>' +
                '<div class="admin-form-group">' +
                '<label>Menu Label *</label>' +
                '<input type="text" id="edit-label" value="' + label.replace(/"/g, '&quot;').replace(/'/g, '&#39;') + '" required>' +
                '</div>' +
                '<div class="admin-form-group">' +
                '<label>URL</label>' +
                '<input type="text" id="edit-url" value="' + url.replace(/"/g, '&quot;').replace(/'/g, '&#39;') + '" placeholder="https://example.com or /path">' +
                '</div>' +
                '<div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; padding-top: 20px; border-top: 2px solid #f0f0f1;">' +
                '<button type="button" id="cancel-edit" class="admin-btn admin-btn-outline">Cancel</button>' +
                '<button type="button" id="save-edit" class="admin-btn admin-btn-primary">💾 Save Changes</button>' +
                '</div>' +
                '</div>';
            
            var $modal = $('<div>').html(editForm).css({
                position: 'fixed',
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                background: 'rgba(0,0,0,0.5)',
                zIndex: 10000,
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center'
            }).appendTo('body');
            
            $('#cancel-edit').on('click', function() {
                $modal.remove();
            });
            
            $('#save-edit').on('click', function() {
                var newLabel = $('#edit-label').val().trim();
                var newUrl = $('#edit-url').val().trim();
                
                if (!newLabel) {
                    showMenuToast('Menu label is required.', 'warning');
                    return;
                }
                
                $.post(menuAjaxUrl, {
                    action: 'update_menu_item',
                    item_id: itemId,
                    label: newLabel,
                    url: newUrl
                }, function(response) {
                    if (response.success) {
                        $modal.remove();
                        queueMenuToast('Menu item updated.', 'success');
                        location.reload();
                    } else {
                        showMenuToast('Error updating menu item. Please try again.', 'error');
                    }
                }, 'json').fail(function() {
                    showMenuToast('Failed to update menu item. Please try again.', 'error');
                });
            });
            
            // Close on background click
            $modal.on('click', function(e) {
                if (e.target === $modal[0]) {
                    $modal.remove();
                }
            });
        });
        
        $('.menu-location-select').on('change', function() {
            var $select = $(this);
            var location = $select.data('location');
            var menuName = $select.val();
            var originalValue = $select.data('original-value') || '';
            
            // Store original value if not set
            if (!$select.data('original-value')) {
                $select.data('original-value', originalValue || menuName);
            }
            
            $.post(menuAjaxUrl, {
                action: 'assign_menu_location',
                location: location,
                menu_name: menuName
            }, function(response) {
                if (response.success) {
                    $select.data('original-value', menuName);
                    // Visual feedback
                    $select.css('border-color', '#00a32a');
                    showMenuToast('Menu location updated.', 'success');
                    setTimeout(function() {
                        $select.css('border-color', '');
                    }, 2000);
                } else {
                    // Revert on error
                    $select.val(originalValue);
                    showMenuToast('Failed to assign menu location. Please try again.', 'error');
                }
            }, 'json').fail(function() {
                // Revert on error
                $select.val(originalValue);
                showMenuToast('Failed to assign menu location. Please try again.', 'error');
            });
        });
    });
    </script>
</body>
</html>
