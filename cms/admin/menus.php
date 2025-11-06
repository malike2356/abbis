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

// Run menu enhancement migration if needed
$columnsToAdd = [
    'menu_name' => "VARCHAR(100) DEFAULT NULL AFTER id",
    'object_type' => "ENUM('page','post','category','custom','product','shop','blog','quote','home') DEFAULT 'custom' AFTER url",
    'object_id' => "INT DEFAULT NULL AFTER object_type",
    'menu_location' => "VARCHAR(50) DEFAULT NULL AFTER menu_type",
    'css_class' => "VARCHAR(255) DEFAULT NULL AFTER icon"
];

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
            
            $baseUrl = '/abbis3.2';
            if (defined('APP_URL')) {
                $parsed = parse_url(APP_URL);
                $baseUrl = $parsed['path'] ?? '/abbis3.2';
            }
            
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
                $label = $label ?: 'Request Quote';
                $objectId = null; // Special types don't need object_id
            } elseif ($objectType === 'home') {
                $url = $baseUrl . '/';
                $label = $label ?: 'Home';
                $objectId = null; // Special types don't need object_id
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
$categories = $pdo->query("SELECT id, name, slug FROM cms_categories ORDER BY name")->fetchAll();

// Get menu locations
$locationsStmt = $pdo->query("SELECT * FROM cms_menu_locations ORDER BY id");
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

$locationMenus = [];
foreach ($locations as $loc) {
    $locationMenus[$loc['location_name']] = $loc['menu_name'];
}

require_once dirname(__DIR__) . '/public/get-site-name.php';
$companyName = getCMSSiteName('CMS Admin');
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
$currentPage = 'menus';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menus - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <?php include 'header.php'; ?>
    <style>
        .menu-help { background: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 1rem; margin-bottom: 1.5rem; border-radius: 4px; }
        .menu-help h3 { margin: 0 0 0.5rem 0; color: #0ea5e9; }
        .menu-help ol { margin: 0.5rem 0 0 1.5rem; }
        .menu-help li { margin: 0.25rem 0; color: #475569; }
        .menu-management { display: grid; grid-template-columns: 1fr 350px; gap: 2rem; margin-top: 1.5rem; }
        .menu-left { background: white; padding: 1.5rem; border: 1px solid #c3c4c7; border-radius: 4px; }
        .menu-right { display: flex; flex-direction: column; gap: 1.5rem; }
        .menu-right > div { background: white; padding: 1.5rem; border: 1px solid #c3c4c7; border-radius: 4px; }
        .menu-item { background: #f6f7f7; border: 1px solid #dcdcde; padding: 1rem; margin: 0.5rem 0; cursor: move; position: relative; border-radius: 4px; }
        .menu-item:hover { border-color: #2271b1; background: #f0f9ff; }
        .menu-item .menu-item-title { font-weight: 600; color: #1e293b; margin-bottom: 0.25rem; }
        .menu-item .menu-item-url { font-size: 0.75rem; color: #64748b; }
        .menu-item .menu-item-actions { position: absolute; right: 0.75rem; top: 0.75rem; }
        .menu-item .menu-item-actions a { margin-left: 0.5rem; color: #2271b1; text-decoration: none; font-size: 0.75rem; padding: 0.25rem 0.5rem; }
        .menu-item .menu-item-actions a:hover { background: #2271b1; color: white; border-radius: 3px; }
        .menu-item-sub { margin-left: 2rem; border-left: 3px solid #2271b1; padding-left: 1rem; }
        .menu-item-placeholder { background: #f0f0f1; border: 2px dashed #2271b1; height: 60px; margin: 0.5rem 0; border-radius: 4px; }
        .add-menu-item select, .add-menu-item input { width: 100%; padding: 0.5rem; margin-bottom: 0.75rem; border: 1px solid #c3c4c7; border-radius: 4px; }
        .location-item { padding: 1rem; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; }
        .location-item select { width: 100%; padding: 0.5rem; border: 1px solid #c3c4c7; border-radius: 4px; }
        .menu-empty { text-align: center; padding: 3rem; color: #64748b; }
        .menu-empty-icon { font-size: 3rem; margin-bottom: 1rem; }
        .quick-add { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
        .quick-add-btn { padding: 0.75rem; background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 4px; cursor: pointer; text-align: center; color: #0ea5e9; font-weight: 500; }
        .quick-add-btn:hover { background: #0ea5e9; color: white; }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Menus</h1>
        
        <div class="menu-help">
            <h3>📋 Quick Guide: How Menus Work</h3>
            <ol>
                <li><strong>Create a Menu:</strong> Click "Create New Menu" and give it a name (e.g., "Main Menu")</li>
                <li><strong>Add Items:</strong> Select items from the right panel (Pages, Posts, Categories, or Custom Links)</li>
                <li><strong>Organize:</strong> Drag items to reorder. Drag under another item to create submenus</li>
                <li><strong>Assign Location:</strong> In the right panel, assign your menu to "Primary Menu" (header) or "Footer Menu"</li>
                <li><strong>Save:</strong> Click "Save Menu" to apply changes</li>
            </ol>
        </div>
        
        <div class="menu-management">
            <div class="menu-left">
                <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem; align-items: center;">
                    <label for="menu-select"><strong>Menu:</strong></label>
                    <select id="menu-select" style="padding: 0.5rem; border: 1px solid #c3c4c7; border-radius: 4px; min-width: 200px;">
                        <?php foreach ($menus as $menu): ?>
                            <option value="<?php echo htmlspecialchars($menu); ?>" <?php echo $menu === $currentMenu ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($menu); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="create-menu-btn" class="button button-primary">Create New Menu</button>
                </div>
                
                <?php if ($currentMenu): ?>
                    <h2 style="margin-bottom: 1rem;">Menu Structure: <?php echo htmlspecialchars($currentMenu); ?></h2>
                    <div id="menu-items-container">
                        <?php if (empty($menuItems)): ?>
                            <div class="menu-empty">
                                <div class="menu-empty-icon">📋</div>
                                <p><strong>Your menu is empty</strong></p>
                                <p style="font-size: 0.875rem;">Add items from the right panel to get started</p>
                            </div>
                        <?php else: ?>
                            <?php
                            function renderMenuItemsAdmin($items, $parentId = null) {
                                $children = array_filter($items, function($item) use ($parentId) {
                                    return ($item['parent_id'] ?? null) == $parentId;
                                });
                                if (empty($children)) return;
                                
                                echo '<ul class="menu-items-list" data-parent-id="' . ($parentId ?: '0') . '">';
                                foreach ($children as $item) {
                                    $isSub = $parentId !== null;
                                    echo '<li class="menu-item' . ($isSub ? ' menu-item-sub' : '') . '" data-id="' . $item['id'] . '">';
                                    echo '<div class="menu-item-title">' . htmlspecialchars($item['label']) . '</div>';
                                    echo '<div class="menu-item-url">' . htmlspecialchars($item['url']) . '</div>';
                                    echo '<div class="menu-item-actions">';
                                    echo '<a href="#" class="edit-item" data-id="' . $item['id'] . '">Edit</a>';
                                    echo '<a href="#" class="delete-item" data-id="' . $item['id'] . '">Delete</a>';
                                    echo '</div>';
                                    renderMenuItemsAdmin($items, $item['id']);
                                    echo '</li>';
                                }
                                echo '</ul>';
                            }
                            renderMenuItemsAdmin($menuItems, null);
                            ?>
                        <?php endif; ?>
                    </div>
                    
                    <div style="margin-top: 1.5rem;">
                        <button type="button" id="save-menu-order" class="button button-primary">Save Menu</button>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="menu-right">
                <div class="add-menu-item">
                    <h3>Add Menu Items</h3>
                    
                    <div class="quick-add">
                        <div class="quick-add-btn" data-type="home">🏠 Home</div>
                        <div class="quick-add-btn" data-type="page">📄 Pages</div>
                        <div class="quick-add-btn" data-type="post">✏️ Posts</div>
                        <div class="quick-add-btn" data-type="category">📁 Categories</div>
                        <div class="quick-add-btn" data-type="shop">🛍️ Shop</div>
                        <div class="quick-add-btn" data-type="blog">📝 Blog</div>
                        <div class="quick-add-btn" data-type="quote">💬 Quote</div>
                        <div class="quick-add-btn" data-type="custom">🔗 Custom Link</div>
                    </div>
                    
                    <div id="add-item-form" style="display: none; margin-top: 1rem;">
                        <select id="add-item-type" style="width: 100%; padding: 0.5rem; margin-bottom: 0.75rem;">
                            <option value="home">Home</option>
                            <option value="page">Pages</option>
                            <option value="post">Posts</option>
                            <option value="category">Categories</option>
                            <option value="shop">Shop</option>
                            <option value="blog">Blog</option>
                            <option value="quote">Request Quote</option>
                            <option value="custom">Custom Link</option>
                        </select>
                        
                        <div id="add-item-selector">
                            <select id="add-item-object" style="width: 100%; padding: 0.5rem; margin-bottom: 0.75rem;">
                                <option value="">Select an item...</option>
                            </select>
                            <p id="add-item-help" style="color: #64748b; font-size: 0.875rem; margin-top: 0.5rem; padding: 0.5rem; background: #f0f9ff; border-radius: 4px; display: none;">
                                Select a type above to see available items
                            </p>
                        </div>
                        
                        <div id="add-custom-link" style="display: none;">
                            <input type="text" id="custom-label" placeholder="Link Text" style="width: 100%; padding: 0.5rem; margin-bottom: 0.75rem;">
                            <input type="text" id="custom-url" placeholder="URL (e.g., https://example.com)" style="width: 100%; padding: 0.5rem; margin-bottom: 0.75rem;">
                        </div>
                        
                        <button type="button" id="add-to-menu" class="button button-primary" style="width: 100%;">Add to Menu</button>
                    </div>
                </div>
                
                <div class="menu-locations">
                    <h3>Menu Locations</h3>
                    <p style="font-size: 0.875rem; color: #64748b; margin-bottom: 1rem;">
                        Assign menus to appear in different areas of your site
                    </p>
                    <?php foreach ($locations as $location): ?>
                        <div class="location-item">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">
                                <?php echo htmlspecialchars($location['display_name']); ?>
                            </label>
                            <select class="menu-location-select" data-location="<?php echo htmlspecialchars($location['location_name']); ?>">
                                <option value="">— Select —</option>
                                <?php foreach ($menus as $menu): ?>
                                    <option value="<?php echo htmlspecialchars($menu); ?>" 
                                        <?php echo ($locationMenus[$location['location_name']] ?? '') === $menu ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($menu); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($location['description']): ?>
                                <p style="font-size: 0.75rem; color: #64748b; margin-top: 0.5rem;"><?php echo htmlspecialchars($location['description']); ?></p>
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
        
        // Quick add buttons
        $('.quick-add-btn').on('click', function() {
            var type = $(this).data('type');
            $('#add-item-form').show();
            $('#add-item-type').val(type === 'custom' ? 'custom' : type);
            $('#add-item-type').trigger('change');
        });
        
        // Make menu items sortable
        $('.menu-items-list').sortable({
            items: 'li.menu-item',
            placeholder: 'menu-item-placeholder',
            tolerance: 'pointer',
            cursor: 'move',
            connectWith: '.menu-items-list',
        });
        
        $('#menu-select').on('change', function() {
            window.location.href = '?menu=' + encodeURIComponent($(this).val());
        });
        
        $('#create-menu-btn').on('click', function() {
            var menuName = prompt('Enter menu name:');
            if (menuName && menuName.trim()) {
                $.post('', {
                    action: 'create_menu',
                    menu_name: menuName.trim()
                }, function(response) {
                    if (response.success) {
                        window.location.href = '?menu=' + encodeURIComponent(menuName.trim());
                    }
                }, 'json');
            }
        });
        
        $('#add-item-type').on('change', function() {
            var type = $(this).val();
            var $selector = $('#add-item-selector');
            var $custom = $('#add-custom-link');
            var $select = $('#add-item-object');
            var $help = $('#add-item-help');
            
            if (type === 'custom') {
                $selector.hide();
                $custom.show();
            } else if (type === 'home') {
                // Home doesn't need selector
                $custom.hide();
                $selector.show();
                $select.hide();
                $help.text('Home page will be added to menu').show();
            } else if (['shop', 'blog', 'quote'].indexOf(type) !== -1) {
                // These don't need object selection
                $custom.hide();
                $selector.show();
                $select.hide();
                $help.text(type.charAt(0).toUpperCase() + type.slice(1) + ' page will be added to menu').show();
            } else {
                $custom.hide();
                $selector.show();
                $select.show();
                $help.hide();
                
                var items = [];
                <?php
                echo "var pages = " . json_encode($pages) . ";\n";
                echo "var posts = " . json_encode($posts) . ";\n";
                echo "var categories = " . json_encode($categories) . ";\n";
                ?>
                
                if (type === 'page') items = pages;
                else if (type === 'post') items = posts;
                else if (type === 'category') items = categories;
                
                $select.empty().append('<option value="">Select an item...</option>');
                
                if (items && items.length > 0) {
                    items.forEach(function(item) {
                        var label = item.title || item.name;
                        var value = item.id;
                        $select.append('<option value="' + value + '">' + label + '</option>');
                    });
                } else {
                    $select.append('<option value="">No items available</option>');
                }
            }
        });
        
        // Trigger change on page load if type is already selected
        if ($('#add-item-type').val()) {
            $('#add-item-type').trigger('change');
        }
        
        $('#add-to-menu').on('click', function() {
            console.log('Add to menu button clicked');
            
            if (!currentMenu) {
                alert('Please select or create a menu first.');
                return;
            }
            
            var type = $('#add-item-type').val();
            console.log('Selected type:', type);
            
            if (!type) {
                alert('Please select an item type first.');
                return;
            }
            
            var objectId = null;
            var label = '';
            var url = '';
            
            if (type === 'custom') {
                label = $('#custom-label').val().trim();
                url = $('#custom-url').val().trim();
                if (!label || !url) {
                    alert('Please enter both link text and URL.');
                    return;
                }
            } else if (type === 'home') {
                // Home is automatic, set objectId to 'home' for consistency
                objectId = 'home';
                label = 'Home';
                url = '';
            } else if (['shop', 'blog', 'quote'].indexOf(type) !== -1) {
                // These don't need object selection - backend will set label/url
                objectId = null;
                label = '';
                url = '';
            } else {
                // For page, post, category - need to get selected item
                objectId = $('#add-item-object').val();
                if (!objectId || objectId === '' || objectId === null) {
                    alert('Please select an item from the dropdown.');
                    return;
                }
            }
            
            var postData = {
                action: 'add_menu_item',
                menu_name: currentMenu,
                object_type: type,
                object_id: objectId,
                label: label,
                url: url
            };
            
            console.log('Sending POST data:', postData);
            
            $.post('', postData, function(response) {
                console.log('Response received:', response);
                if (response && response.success) {
                    location.reload();
                } else {
                    var errorMsg = response && response.error ? response.error : 'Failed to add item. Please check the console for details.';
                    alert('Error: ' + errorMsg);
                    console.error('Add menu item error:', response);
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.error('Response text:', xhr.responseText);
                console.error('Status code:', xhr.status);
                alert('Failed to add item: ' + error + '. Please check the browser console (F12) for details.');
            });
        });
        
        $('#save-menu-order').on('click', function() {
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
            
            $.post('', {
                action: 'save_menu_order',
                items: JSON.stringify(items)
            }, function(response) {
                if (response.success) {
                    alert('Menu saved successfully!');
                    location.reload();
                }
            }, 'json');
        });
        
        $(document).on('click', '.delete-item', function(e) {
            e.preventDefault();
            if (!confirm('Are you sure you want to delete this menu item?')) return;
            
            var itemId = $(this).data('id');
            $.post('', {
                action: 'delete_menu_item',
                item_id: itemId
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            }, 'json');
        });
        
        $(document).on('click', '.edit-item', function(e) {
            e.preventDefault();
            var itemId = $(this).data('id');
            var $item = $(this).closest('.menu-item');
            var label = $item.find('.menu-item-title').text();
            var url = $item.find('.menu-item-url').text();
            
            var newLabel = prompt('Menu Label:', label);
            var newUrl = prompt('URL:', url);
            
            if (newLabel !== null && newUrl !== null) {
                $.post('', {
                    action: 'update_menu_item',
                    item_id: itemId,
                    label: newLabel,
                    url: newUrl
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    }
                }, 'json');
            }
        });
        
        $('.menu-location-select').on('change', function() {
            var location = $(this).data('location');
            var menuName = $(this).val();
            
            $.post('', {
                action: 'assign_menu_location',
                location: location,
                menu_name: menuName
            }, function(response) {
                if (response.success) {
                    // Success
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
