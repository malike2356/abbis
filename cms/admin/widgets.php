<?php
/**
 * CMS Admin - Widget Management (WordPress-like)
 */
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

// Ensure widget tables exist
try {
    $pdo->query("SELECT * FROM cms_widget_areas LIMIT 1");
} catch (PDOException $e) {
    $migrationSQL = file_get_contents($rootPath . '/database/cms_widgets.sql');
    $statements = explode(';', $migrationSQL);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e2) {}
        }
    }
}

$areaId = $_GET['area'] ?? null;
$action = $_GET['action'] ?? 'list';
$widgetId = $_GET['id'] ?? null;
$message = null;

// Widget types available
$widgetTypes = [
    'text' => 'Text Widget',
    'html' => 'Custom HTML',
    'recent_posts' => 'Recent Posts',
    'categories' => 'Categories',
    'search' => 'Search',
    'calendar' => 'Calendar',
    'tag_cloud' => 'Tag Cloud',
    'pages' => 'Pages',
    'archives' => 'Archives',
    'meta' => 'Meta',
    'rss' => 'RSS Feed'
];

// Handle widget save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_widget'])) {
    $widgetAreaId = $_POST['widget_area_id'];
    $widgetType = $_POST['widget_type'];
    $widgetTitle = trim($_POST['widget_title'] ?? '');
    $widgetOrder = intval($_POST['widget_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    
    // Collect widget data based on type
    $widgetData = [];
    if ($widgetType === 'text' || $widgetType === 'html') {
        $widgetData['content'] = $_POST['content'] ?? '';
    } elseif ($widgetType === 'rss') {
        $widgetData['url'] = $_POST['rss_url'] ?? '';
        $widgetData['items'] = intval($_POST['rss_items'] ?? 5);
    } elseif ($widgetType === 'recent_posts') {
        $widgetData['number'] = intval($_POST['posts_number'] ?? 5);
    }
    
    if ($widgetId) {
        $stmt = $pdo->prepare("UPDATE cms_widgets SET widget_area_id=?, widget_type=?, widget_title=?, widget_order=?, widget_data=?, is_active=? WHERE id=?");
        $stmt->execute([$widgetAreaId, $widgetType, $widgetTitle, $widgetOrder, json_encode($widgetData), $isActive, $widgetId]);
        $message = 'Widget updated';
    } else {
        $stmt = $pdo->prepare("INSERT INTO cms_widgets (widget_area_id, widget_type, widget_title, widget_order, widget_data, is_active) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$widgetAreaId, $widgetType, $widgetTitle, $widgetOrder, json_encode($widgetData), $isActive]);
        $message = 'Widget added';
        $widgetId = $pdo->lastInsertId();
    }
    $areaId = $widgetAreaId;
}

// Handle widget delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_widget'])) {
    $deleteId = $_POST['id'];
    $pdo->prepare("DELETE FROM cms_widgets WHERE id=?")->execute([$deleteId]);
    $message = 'Widget deleted';
}

// Handle widget order update (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_widget_order'])) {
    $orders = json_decode($_POST['orders'], true);
    foreach ($orders as $order) {
        $pdo->prepare("UPDATE cms_widgets SET widget_order=? WHERE id=?")->execute([$order['order'], $order['id']]);
    }
    echo json_encode(['success' => true]);
    exit;
}

$widgetAreas = $pdo->query("SELECT * FROM cms_widget_areas ORDER BY location, name")->fetchAll();
$widget = null;
$widgets = [];

if ($areaId && $action === 'edit' && $widgetId) {
    $stmt = $pdo->prepare("SELECT * FROM cms_widgets WHERE id=?");
    $stmt->execute([$widgetId]);
    $widget = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($widget) {
        $widget['widget_data'] = json_decode($widget['widget_data'] ?? '{}', true);
    }
}

if ($areaId) {
    $stmt = $pdo->prepare("SELECT * FROM cms_widgets WHERE widget_area_id=? ORDER BY widget_order ASC");
    $stmt->execute([$areaId]);
    $widgets = $stmt->fetchAll();
    foreach ($widgets as &$w) {
        $w['widget_data'] = json_decode($w['widget_data'] ?? '{}', true);
    }
}

$configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key='company_name'");
$companyName = $configStmt->fetchColumn() ?: 'CMS Admin';
$baseUrl = '/abbis3.2';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    $baseUrl = $parsed['path'] ?? '/abbis3.2';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Widgets - <?php echo htmlspecialchars($companyName); ?> CMS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jquery-ui-dist@1.13.2/jquery-ui.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <?php 
    $currentPage = 'widgets';
    include 'header.php'; 
    ?>
    <style>
        .widget-areas {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;
        }
        .widget-area-card {
            background: white; border: 1px solid #c3c4c7; padding: 20px; border-radius: 4px; cursor: pointer;
            transition: border-color 0.2s;
        }
        .widget-area-card:hover {
            border-color: #2271b1;
        }
        .widget-area-card.active {
            border-color: #2271b1; border-width: 2px;
        }
        .widget-list {
            margin-top: 20px;
        }
        .widget-item {
            background: white; border: 1px solid #c3c4c7; padding: 15px; margin-bottom: 10px; border-radius: 4px;
            cursor: move;
        }
        .widget-item:hover {
            border-color: #2271b1;
        }
        .widget-item-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;
        }
        .widget-item-title {
            font-weight: 600; color: #1e293b;
        }
        .widget-item-actions a {
            margin-left: 10px; color: #2271b1; text-decoration: none;
        }
        .widget-form {
            background: white; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px; border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <h1>Widgets</h1>
        
        <div class="nav-tab-wrapper" style="margin-bottom: 20px; border-bottom: 1px solid #c3c4c7;">
            <a href="appearance.php" class="nav-tab">Themes</a>
            <a href="menus.php" class="nav-tab">Menus</a>
            <a href="widgets.php" class="nav-tab nav-tab-active">Widgets</a>
        </div>
        
        <?php if ($message): ?>
            <div class="notice notice-success"><p><?php echo htmlspecialchars($message); ?></p></div>
        <?php endif; ?>
        
        <?php if (!$areaId): ?>
            <h2>Widget Areas</h2>
            <p>Select a widget area to manage widgets or add new widgets.</p>
            
            <div class="notice notice-info" style="margin-bottom: 20px; padding: 12px;">
                <p><strong>💡 Note:</strong> If you see "0 widgets" in footer areas, your site is currently showing <strong>default footer content</strong> (About, Quick Links, Legal, Contact). Click on a footer column to add widgets, and they will replace the default content.</p>
            </div>
            
            <div class="widget-areas">
                <?php foreach ($widgetAreas as $area): ?>
                    <div class="widget-area-card" onclick="window.location='?area=<?php echo $area['id']; ?>'">
                        <h3><?php echo htmlspecialchars($area['name']); ?></h3>
                        <p style="color: #646970; font-size: 13px;"><?php echo htmlspecialchars($area['description']); ?></p>
                        <p style="margin-top: 10px;">
                            <strong><?php 
                                $count = $pdo->prepare("SELECT COUNT(*) FROM cms_widgets WHERE widget_area_id=? AND is_active=1");
                                $count->execute([$area['id']]);
                                $widgetCount = $count->fetchColumn();
                                echo $widgetCount;
                            ?> widget<?php echo $widgetCount != 1 ? 's' : ''; ?></strong>
                            <?php if ($widgetCount == 0 && strpos($area['slug'], 'footer') !== false): ?>
                                <br><span style="color: #646970; font-size: 11px; font-weight: normal;">(Default footer content will show until you add widgets)</span>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <?php
            $currentArea = $pdo->prepare("SELECT * FROM cms_widget_areas WHERE id=?");
            $currentArea->execute([$areaId]);
            $currentArea = $currentArea->fetch(PDO::FETCH_ASSOC);
            ?>
            <div style="margin-bottom: 20px;">
                <a href="widgets.php" class="button">← Back to Widget Areas</a>
                <h2 style="margin-top: 20px;"><?php echo htmlspecialchars($currentArea['name']); ?></h2>
                <p><?php echo htmlspecialchars($currentArea['description']); ?></p>
            </div>
            
            <?php if ($action === 'add' || ($action === 'edit' && $widget)): ?>
                <div class="widget-form">
                    <h3><?php echo $action === 'edit' ? 'Edit Widget' : 'Add Widget'; ?></h3>
                    <form method="post">
                        <input type="hidden" name="widget_area_id" value="<?php echo $areaId; ?>">
                        <?php if ($widgetId): ?>
                            <input type="hidden" name="id" value="<?php echo $widgetId; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>Widget Type <span style="color: red;">*</span></label>
                            <select name="widget_type" id="widget_type" required onchange="updateWidgetForm()">
                                <option value="">Select Widget Type</option>
                                <?php foreach ($widgetTypes as $type => $name): ?>
                                    <option value="<?php echo $type; ?>" <?php echo ($widget['widget_type'] ?? '') === $type ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Widget Title</label>
                            <input type="text" name="widget_title" value="<?php echo htmlspecialchars($widget['widget_title'] ?? ''); ?>" class="regular-text">
                            <p class="description">Leave empty to hide the title.</p>
                        </div>
                        
                        <div id="widget-content-fields" style="display: none;">
                            <!-- Fields will be populated by JavaScript based on widget type -->
                        </div>
                        
                        <div class="form-group">
                            <label>Widget Order</label>
                            <input type="number" name="widget_order" value="<?php echo htmlspecialchars($widget['widget_order'] ?? 0); ?>" min="0" class="small-text">
                            <p class="description">Lower numbers appear first.</p>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_active" value="1" <?php echo ($widget['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                Active
                            </label>
                        </div>
                        
                        <p class="submit">
                            <input type="submit" name="save_widget" class="button button-primary" value="Save Widget">
                            <a href="?area=<?php echo $areaId; ?>" class="button">Cancel</a>
                        </p>
                    </form>
                </div>
            <?php else: ?>
                <a href="?area=<?php echo $areaId; ?>&action=add" class="page-title-action">Add Widget</a>
                
                <div class="widget-list" id="widget-list">
                    <?php if (empty($widgets)): ?>
                        <div style="background: #f0f9ff; border-left: 4px solid #2271b1; padding: 15px; margin-bottom: 20px;">
                            <p style="margin: 0 0 10px 0;"><strong>No widgets added yet</strong></p>
                            <p style="margin: 0 0 15px 0; color: #646970;">
                                <?php if (strpos($currentArea['slug'], 'footer') !== false): ?>
                                    Your footer is currently showing <strong>default content</strong> (About, Quick Links, Legal, Contact). 
                                    Add widgets here to replace it with your custom content.
                                <?php else: ?>
                                    This widget area is empty. Add widgets to display content here.
                                <?php endif; ?>
                            </p>
                            <a href="?area=<?php echo $areaId; ?>&action=add" class="button button-primary">Add Your First Widget</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($widgets as $w): ?>
                            <div class="widget-item" data-widget-id="<?php echo $w['id']; ?>">
                                <div class="widget-item-header">
                                    <div>
                                        <span class="widget-item-title"><?php echo htmlspecialchars($w['widget_title'] ?: $widgetTypes[$w['widget_type']] ?? $w['widget_type']); ?></span>
                                        <span style="color: #646970; font-size: 12px; margin-left: 10px;">
                                            <?php echo htmlspecialchars($widgetTypes[$w['widget_type']] ?? $w['widget_type']); ?>
                                        </span>
                                    </div>
                                    <div class="widget-item-actions">
                                        <a href="?area=<?php echo $areaId; ?>&action=edit&id=<?php echo $w['id']; ?>">Edit</a>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Delete this widget?');">
                                            <input type="hidden" name="id" value="<?php echo $w['id']; ?>">
                                            <input type="submit" name="delete_widget" value="Delete" style="background: none; border: none; color: #d63638; cursor: pointer; text-decoration: underline; padding: 0;">
                                        </form>
                                    </div>
                                </div>
                                <?php if (!$w['is_active']): ?>
                                    <p style="color: #646970; font-size: 12px; margin: 0;">Inactive</p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <script>
        // Make widget list sortable
        $(function() {
            $("#widget-list").sortable({
                handle: '.widget-item',
                update: function(event, ui) {
                    var orders = [];
                    $("#widget-list .widget-item").each(function(index) {
                        orders.push({
                            id: $(this).data('widget-id'),
                            order: index
                        });
                    });
                    $.post('widgets.php', {
                        update_widget_order: true,
                        orders: JSON.stringify(orders)
                    });
                }
            });
            $("#widget-list").disableSelection();
        });
        
        function updateWidgetForm() {
            const type = document.getElementById('widget_type').value;
            const container = document.getElementById('widget-content-fields');
            
            if (!type) {
                container.style.display = 'none';
                return;
            }
            
            container.style.display = 'block';
            let html = '';
            
            if (type === 'text' || type === 'html') {
                html = `
                    <div class="form-group">
                        <label>Content <span style="color: red;">*</span></label>
                        <textarea name="content" rows="10" class="large-text" required>${type === 'html' ? '<?php echo htmlspecialchars($widget['widget_data']['content'] ?? ''); ?>' : '<?php echo htmlspecialchars($widget['widget_data']['content'] ?? ''); ?>'}</textarea>
                        <p class="description">${type === 'html' ? 'Enter HTML code' : 'Enter text content'}</p>
                    </div>
                `;
            } else if (type === 'rss') {
                html = `
                    <div class="form-group">
                        <label>RSS Feed URL <span style="color: red;">*</span></label>
                        <input type="url" name="rss_url" value="<?php echo htmlspecialchars($widget['widget_data']['url'] ?? ''); ?>" class="large-text" required>
                    </div>
                    <div class="form-group">
                        <label>Number of Items</label>
                        <input type="number" name="rss_items" value="<?php echo htmlspecialchars($widget['widget_data']['items'] ?? 5); ?>" min="1" max="20" class="small-text">
                    </div>
                `;
            } else if (type === 'recent_posts') {
                html = `
                    <div class="form-group">
                        <label>Number of Posts</label>
                        <input type="number" name="posts_number" value="<?php echo htmlspecialchars($widget['widget_data']['number'] ?? 5); ?>" min="1" max="20" class="small-text">
                    </div>
                `;
            }
            
            container.innerHTML = html;
        }
        
        <?php if ($widget): ?>
        updateWidgetForm();
        <?php endif; ?>
    </script>
</body>
</html>

