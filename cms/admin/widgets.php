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

$areaWidgetCounts = [];
$totalWidgetCount = 0;
$activeWidgetCount = 0;
if (!empty($widgetAreas)) {
    $countsStmt = $pdo->query("SELECT widget_area_id, COUNT(*) AS total, SUM(is_active = 1) AS active_count FROM cms_widgets GROUP BY widget_area_id");
    foreach ($countsStmt as $row) {
        $areaWidgetCounts[(int)$row['widget_area_id']] = [
            'total' => (int)$row['total'],
            'active' => (int)($row['active_count'] ?? 0),
        ];
        $totalWidgetCount += (int)$row['total'];
        $activeWidgetCount += (int)($row['active_count'] ?? 0);
    }
}
$totalAreas = count($widgetAreas);
$emptyAreaCount = 0;
$locationsRepresented = 0;
if (!empty($widgetAreas)) {
    $locations = [];
    foreach ($widgetAreas as $area) {
        $counts = $areaWidgetCounts[$area['id']] ?? ['total' => 0, 'active' => 0];
        if ($counts['total'] === 0) {
            $emptyAreaCount++;
        }
        if (!empty($area['location'])) {
            $locations[] = $area['location'];
        }
    }
    $locationsRepresented = count(array_unique($locations));
}

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

$widgetDataForScript = [];
$widgetTypeForScript = '';
if (is_array($widget)) {
    $widgetDataForScript = $widget['widget_data'] ?? [];
    $widgetTypeForScript = $widget['widget_type'] ?? '';
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
$baseUrl = app_url();
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
        .widgets-shell {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }
        .widgets-hero {
            position: relative;
            background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 55%, #0f172a 100%);
            color: #e2e8f0;
            padding: 36px 38px;
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 30px 60px rgba(15, 23, 42, 0.35);
        }
        .widgets-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 55%),
                        radial-gradient(circle at bottom left, rgba(59,130,246,0.25), transparent 60%);
            pointer-events: none;
        }
        .widgets-hero-content {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .widgets-hero-kicker {
            font-size: 12px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.75);
            margin: 0;
        }
        .widgets-hero h1 {
            margin: 0;
            font-size: 30px;
            font-weight: 700;
        }
        .widgets-hero p {
            margin: 0;
            max-width: 620px;
            color: rgba(226, 232, 240, 0.85);
        }
        .widgets-hero-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }
        .widgets-metric {
            background: rgba(15, 23, 42, 0.45);
            border-radius: 18px;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            backdrop-filter: blur(8px);
        }
        .widgets-metric span:first-child {
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: rgba(226, 232, 240, 0.7);
        }
        .widgets-metric span:last-child {
            font-size: 26px;
            font-weight: 700;
            color: #ffffff;
        }
        .widget-area-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .widget-section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }
        .widget-section-header h2 {
            margin: 0;
            font-size: 22px;
            color: #0f172a;
        }
        .widget-section-header p {
            margin: 4px 0 0 0;
            color: #475569;
            font-size: 14px;
        }
        .widget-tip {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 18px;
            padding: 18px 24px;
            color: #0f172a;
            font-size: 13px;
        }
        .widget-tip strong {
            color: #0ea5e9;
        }
        .widget-area-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 22px;
        }
        .widget-area-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 22px;
            padding: 24px 26px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }
        .widget-area-card:hover {
            transform: translateY(-4px);
            border-color: #2563eb;
            box-shadow: 0 22px 42px rgba(37, 99, 235, 0.18);
        }
        .widget-area-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
        }
        .widget-area-pill {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            background: #eef2ff;
            color: #312e81;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 600;
        }
        .widget-area-pill.empty {
            background: #fef3c7;
            color: #92400e;
        }
        .widget-area-card h3 {
            margin: 0;
            font-size: 20px;
            color: #0f172a;
        }
        .widget-area-desc {
            margin: 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.6;
        }
        .widget-area-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 12px;
            color: #475569;
        }
        .widget-area-stat {
            background: #f8fafc;
            border-radius: 12px;
            padding: 6px 12px;
            font-weight: 600;
        }
        .widget-area-card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: auto;
        }
        .widget-area-card-actions span {
            font-size: 12px;
            color: #64748b;
        }
        .widget-area-manage {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 16px 30px rgba(37, 99, 235, 0.25);
            transition: transform 0.2s ease;
        }
        .widget-area-manage:hover {
            transform: translateY(-2px);
        }
        .widget-area-hero {
            position: relative;
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #0f172a 100%);
            color: #e2e8f0;
            padding: 30px 34px;
            border-radius: 26px;
            margin-bottom: 28px;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: space-between;
            box-shadow: 0 32px 58px rgba(15, 23, 42, 0.35);
            overflow: hidden;
        }
        .widget-area-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.12), transparent 55%);
            pointer-events: none;
        }
        .widget-area-hero-main {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
            max-width: 640px;
        }
        .widget-back-link {
            color: rgba(226, 232, 240, 0.85);
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .widget-area-hero h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }
        .widget-area-hero p {
            margin: 0;
            color: rgba(226, 232, 240, 0.85);
            font-size: 14px;
        }
        .widget-area-hero-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .widget-area-tag {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(8px);
            font-size: 11px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .widget-area-hero-side {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: flex-end;
        }
        .widget-area-hero-side .widgets-hero-metrics {
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .widget-hero-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 14px;
            background: #22c55e;
            color: #0f172a;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 16px 30px rgba(34, 197, 94, 0.25);
            transition: transform 0.2s ease;
        }
        .widget-hero-button:hover {
            transform: translateY(-2px);
        }
        .widget-area-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 24px;
            align-items: flex-start;
        }
        .widget-area-main {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .widget-card-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .widget-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }
        .widget-card.inactive {
            opacity: 0.85;
            border-style: dashed;
        }
        .widget-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        .widget-card-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .widget-drag-handle {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            background: #eef2ff;
            color: #312e81;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: grab;
        }
        .widget-card-title h3 {
            margin: 0;
            font-size: 16px;
            color: #0f172a;
        }
        .widget-card-title p {
            margin: 4px 0 0 0;
            color: #64748b;
            font-size: 12px;
        }
        .widget-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 12px;
            color: #475569;
        }
        .widget-card-meta span {
            background: #f8fafc;
            border-radius: 999px;
            padding: 5px 12px;
            font-weight: 600;
        }
        .widget-card-snippet {
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
            background: #f8fafc;
            border-radius: 14px;
            padding: 12px 14px;
        }
        .widget-card-actions {
            display: flex;
            gap: 10px;
        }
        .widget-card-actions a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .widget-card-actions form {
            margin: 0;
        }
        .widget-card-actions button {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        .widget-empty {
            background: #f1f5f9;
            border: 1px dashed #cbd5f5;
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            color: #475569;
        }
        .widget-empty h3 {
            margin: 0 0 8px 0;
            color: #0f172a;
        }
        .widget-empty a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            padding: 9px 18px;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            font-weight: 600;
            text-decoration: none;
        }
        .widget-area-side {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .widget-form-card,
        .widget-side-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 22px 24px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }
        .widget-form-card h3,
        .widget-side-card h3 {
            margin: 0 0 8px 0;
            font-size: 18px;
            color: #0f172a;
        }
        .widget-form-card p,
        .widget-side-card p {
            margin: 0 0 16px 0;
            color: #64748b;
            font-size: 13px;
        }
        .widget-field-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .widget-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .widget-field label {
            font-weight: 600;
            font-size: 13px;
            color: #0f172a;
        }
        .widget-field input,
        .widget-field select,
        .widget-field textarea {
            border: 1px solid #cbd5f5;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
            color: #1e293b;
            background: #fff;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .widget-field textarea {
            resize: vertical;
            min-height: 120px;
        }
        .widget-field input:focus,
        .widget-field select:focus,
        .widget-field textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        .widget-field .description {
            font-size: 12px;
            color: #94a3b8;
        }
        .widget-dynamic-fields {
            display: grid;
            gap: 16px;
            margin-top: 4px;
        }
        .widget-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .widget-btn-primary,
        .widget-btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            border: none;
        }
        .widget-btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #fff;
            box-shadow: 0 16px 28px rgba(37, 99, 235, 0.28);
        }
        .widget-btn-secondary {
            background: #f8fafc;
            color: #1d4ed8;
            border: 1px solid #cbd5f5;
        }
        .widget-side-card ul {
            margin: 0;
            padding-left: 18px;
            color: #475569;
            font-size: 13px;
            display: grid;
            gap: 6px;
        }
        .widget-side-card small {
            display: block;
            margin-top: 12px;
            color: #94a3b8;
            font-size: 12px;
        }
        @media (max-width: 1100px) {
            .widget-area-layout {
                grid-template-columns: 1fr;
            }
            .widget-area-hero {
                padding: 24px;
            }
        }
        @media (max-width: 768px) {
            .widgets-hero {
                padding: 26px;
            }
            .widget-area-grid {
                grid-template-columns: 1fr;
            }
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
            <div class="widgets-shell">
                <section class="widgets-hero">
                    <div class="widgets-hero-content">
                        <div>
                            <p class="widgets-hero-kicker">Appearance ▸ Widgets</p>
                            <h1>Widget Builder</h1>
                            <p>Curate dynamic content blocks for your header, sidebar, and footer without touching code.</p>
                        </div>
                        <div class="widgets-hero-metrics">
                            <div class="widgets-metric">
                                <span>Widget Areas</span>
                                <span><?php echo $totalAreas; ?></span>
                            </div>
                            <div class="widgets-metric">
                                <span>Total Widgets</span>
                                <span><?php echo $totalWidgetCount; ?></span>
                            </div>
                            <div class="widgets-metric">
                                <span>Active Widgets</span>
                                <span><?php echo $activeWidgetCount; ?></span>
                            </div>
                            <div class="widgets-metric">
                                <span>Empty Areas</span>
                                <span><?php echo $emptyAreaCount; ?></span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="widget-area-section">
                    <div class="widget-section-header">
                        <div>
                            <h2>Widget Areas</h2>
                            <p><?php echo $totalAreas ? $locationsRepresented . ' layout location' . ($locationsRepresented === 1 ? '' : 's') . ' ready to customise.' : 'Define widget areas in your theme to start placing widgets.'; ?></p>
                        </div>
                    </div>

                    <div class="widget-tip">
                        <span style="font-size: 20px;">💡</span>
                        <div>
                            <strong>Note:</strong> Footer columns show default copy until you place widgets. Add a widget and the defaults disappear instantly.
                        </div>
                    </div>

                    <div class="widget-area-grid">
                        <?php foreach ($widgetAreas as $area): 
                            $counts = $areaWidgetCounts[$area['id']] ?? ['total' => 0, 'active' => 0];
                            $areaTotal = $counts['total'];
                            $areaActive = $counts['active'];
                            $empty = $areaTotal === 0;
                        ?>
                            <article class="widget-area-card">
                                <div class="widget-area-meta">
                                    <span class="widget-area-pill"><?php echo htmlspecialchars(ucfirst($area['location'] ?? 'general')); ?> area</span>
                                    <?php if ($empty): ?>
                                        <span class="widget-area-pill empty">No widgets yet</span>
                                    <?php endif; ?>
                                </div>
                                <h3><?php echo htmlspecialchars($area['name']); ?></h3>
                                <p class="widget-area-desc"><?php echo htmlspecialchars($area['description']); ?></p>
                                <div class="widget-area-stats">
                                    <span class="widget-area-stat"><?php echo $areaTotal; ?> widget<?php echo $areaTotal === 1 ? '' : 's'; ?></span>
                                    <span class="widget-area-stat"><?php echo $areaActive; ?> active</span>
                                </div>
                                <div class="widget-area-card-actions">
                                    <span><?php echo $empty ? 'Currently showing default content' : 'Drag &amp; drop to reorder widgets'; ?></span>
                                    <a class="widget-area-manage" href="?area=<?php echo $area['id']; ?>">Manage widgets</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        <?php else: ?>
            <?php
            $currentArea = $pdo->prepare("SELECT * FROM cms_widget_areas WHERE id=?");
            $currentArea->execute([$areaId]);
            $currentArea = $currentArea->fetch(PDO::FETCH_ASSOC);
            $currentCounts = $areaWidgetCounts[$areaId] ?? ['total' => 0, 'active' => 0];
            $currentTotal = $currentCounts['total'];
            $currentActive = $currentCounts['active'];
            $currentInactive = max(0, $currentTotal - $currentActive);
            ?>
            <div class="widgets-shell">
                <section class="widget-area-hero">
                    <div class="widget-area-hero-main">
                        <a href="widgets.php" class="widget-back-link">← Widget Areas</a>
                        <h1><?php echo htmlspecialchars($currentArea['name']); ?></h1>
                        <p><?php echo htmlspecialchars($currentArea['description']); ?></p>
                        <div class="widget-area-hero-tags">
                            <span class="widget-area-tag">Location: <?php echo htmlspecialchars(ucfirst($currentArea['location'] ?? 'general')); ?></span>
                            <span class="widget-area-tag"><?php echo $currentTotal; ?> widget<?php echo $currentTotal === 1 ? '' : 's'; ?></span>
                            <?php if ($currentTotal === 0): ?>
                                <span class="widget-area-tag">Default content visible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="widget-area-hero-side">
                        <div class="widgets-hero-metrics">
                            <div class="widgets-metric"><span>Total</span><span><?php echo $currentTotal; ?></span></div>
                            <div class="widgets-metric"><span>Active</span><span><?php echo $currentActive; ?></span></div>
                            <div class="widgets-metric"><span>Inactive</span><span><?php echo $currentInactive; ?></span></div>
                        </div>
                        <a class="widget-hero-button" href="?area=<?php echo $areaId; ?>&amp;action=add">➕ Add widget</a>
                    </div>
                </section>

                <div class="widget-area-layout">
                    <div class="widget-area-main">
                        <?php if (empty($widgets)): ?>
                            <div class="widget-empty">
                                <h3>This area doesn’t have widgets yet</h3>
                                <p><?php echo strpos($currentArea['slug'], 'footer') !== false ? 'Footer defaults (About, Quick Links, Legal, Contact) are visible.' : 'Add widgets to start serving content here.'; ?></p>
                                <a href="?area=<?php echo $areaId; ?>&amp;action=add">Start with your first widget</a>
                            </div>
                        <?php else: ?>
                            <div class="widget-card-list" id="widget-list">
                                <?php foreach ($widgets as $w):
                                    $data = $w['widget_data'] ?? [];
                                    $snippet = '';
                                    if (!empty($data['content'])) {
                                        $snippet = strip_tags($data['content']);
                                        if (function_exists('mb_strlen')) {
                                            if (mb_strlen($snippet) > 160) {
                                                $snippet = mb_substr($snippet, 0, 157) . '…';
                                            }
                                        } else {
                                            if (strlen($snippet) > 160) {
                                                $snippet = substr($snippet, 0, 157) . '…';
                                            }
                                        }
                                    }
                                ?>
                                    <article class="widget-card<?php echo $w['is_active'] ? '' : ' inactive'; ?>" data-widget-id="<?php echo $w['id']; ?>">
                                        <div class="widget-card-header">
                                            <div class="widget-card-title">
                                                <div class="widget-drag-handle" title="Drag to reorder">
                                                    <svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="6" r="1"></circle><circle cx="5" cy="12" r="1"></circle><circle cx="5" cy="18" r="1"></circle><circle cx="12" cy="6" r="1"></circle><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="18" r="1"></circle><circle cx="19" cy="6" r="1"></circle><circle cx="19" cy="12" r="1"></circle><circle cx="19" cy="18" r="1"></circle></svg>
                                                </div>
                                                <div>
                                                    <h3><?php echo htmlspecialchars($w['widget_title'] ?: ($widgetTypes[$w['widget_type']] ?? ucfirst($w['widget_type']))); ?></h3>
                                                    <p><?php echo htmlspecialchars($widgetTypes[$w['widget_type']] ?? ucfirst($w['widget_type'])); ?></p>
                                                </div>
                                            </div>
                                            <div class="widget-card-actions">
                                                <a href="?area=<?php echo $areaId; ?>&amp;action=edit&amp;id=<?php echo $w['id']; ?>">Edit</a>
                                                <form method="post" onsubmit="return confirm('Delete this widget?');">
                                                    <input type="hidden" name="id" value="<?php echo $w['id']; ?>">
                                                    <button type="submit" name="delete_widget">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                        <div class="widget-card-meta">
                                            <span>Order #<?php echo (int)$w['widget_order']; ?></span>
                                            <span><?php echo $w['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                        </div>
                                        <?php if ($snippet): ?>
                                            <div class="widget-card-snippet"><?php echo htmlspecialchars($snippet); ?></div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <aside class="widget-area-side">
                        <?php if ($action === 'add' || ($action === 'edit' && $widget)): ?>
                            <div class="widget-form-card">
                                <h3><?php echo $action === 'edit' ? 'Edit widget' : 'Add widget'; ?></h3>
                                <p>Configure the widget content that will appear inside <strong><?php echo htmlspecialchars($currentArea['name']); ?></strong>.</p>
                                <form method="post">
                                    <input type="hidden" name="widget_area_id" value="<?php echo $areaId; ?>">
                                    <?php if ($widgetId): ?>
                                        <input type="hidden" name="id" value="<?php echo $widgetId; ?>">
                                    <?php endif; ?>

                                    <div class="widget-field-grid">
                                        <div class="widget-field">
                                            <label>Widget type <span style="color:#dc2626;">*</span></label>
                                            <select name="widget_type" id="widget_type" required>
                                                <option value="">Select a widget</option>
                                                <?php foreach ($widgetTypes as $type => $name): ?>
                                                    <option value="<?php echo $type; ?>" <?php echo ($widget['widget_type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($name); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="widget-field">
                                            <label>Widget title</label>
                                            <input type="text" name="widget_title" value="<?php echo htmlspecialchars($widget['widget_title'] ?? ''); ?>" placeholder="Optional heading">
                                            <span class="description">Leave blank to hide the title.</span>
                                        </div>
                                    </div>

                                    <div id="widget-content-fields" class="widget-dynamic-fields" style="display:none;"></div>

                                    <div class="widget-field-grid">
                                        <div class="widget-field">
                                            <label>Widget order</label>
                                            <input type="number" name="widget_order" value="<?php echo htmlspecialchars($widget['widget_order'] ?? 0); ?>" min="0">
                                            <span class="description">Lower numbers appear first.</span>
                                        </div>
                                        <div class="widget-field">
                                            <label>Status</label>
                                            <label style="display:flex; align-items:center; gap:10px; font-size:13px; color:#475569;">
                                                <input type="checkbox" name="is_active" value="1" <?php echo ($widget['is_active'] ?? 1) ? 'checked' : ''; ?>>
                                                <span>Widget is active</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="widget-form-actions">
                                        <a href="?area=<?php echo $areaId; ?>" class="widget-btn-secondary">Cancel</a>
                                        <button type="submit" name="save_widget" class="widget-btn-primary">Save widget</button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div class="widget-side-card">
                                <h3>Widget ideas</h3>
                                <p>Popular choices for this spot:</p>
                                <ul>
                                    <li>Text or HTML for custom copy.</li>
                                    <li>Recent posts to highlight fresh content.</li>
                                    <li>Contact or address details in footer columns.</li>
                                </ul>
                                <small>Need something advanced? Install a plugin widget and it will appear in the list.</small>
                            </div>
                        <?php endif; ?>
                    </aside>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        const widgetInitialData = <?php echo json_encode((object)$widgetDataForScript); ?>;
        const widgetTypeInitial = <?php echo json_encode($widgetTypeForScript); ?>;

        $(function() {
            const $widgetList = $('#widget-list');
            if ($widgetList.length) {
                $widgetList.sortable({
                    handle: '.widget-drag-handle',
                    update: function () {
                        const orders = [];
                        $widgetList.find('.widget-card').each(function (index) {
                            orders.push({
                                id: $(this).data('widget-id'),
                                order: index
                            });
                        });
                        if (orders.length) {
                            $.post('widgets.php', {
                                update_widget_order: true,
                                orders: JSON.stringify(orders)
                            });
                        }
                    }
                });
            }

            const typeSelect = document.getElementById('widget_type');
            window.widgetInitialData = widgetInitialData || {};

            function renderWidgetFields() {
                const container = document.getElementById('widget-content-fields');
                if (!container || !typeSelect) return;
                const type = typeSelect.value;
                container.innerHTML = '';

                if (!type) {
                    container.style.display = 'none';
                    return;
                }

                const initialData = (type === widgetTypeInitial) ? (window.widgetInitialData || {}) : {};
                const fragment = document.createDocumentFragment();

                if (type === 'text' || type === 'html') {
                    const field = document.createElement('div');
                    field.className = 'widget-field';
                    const label = document.createElement('label');
                    label.textContent = type === 'html' ? 'HTML content' : 'Text content';
                    field.appendChild(label);
                    const textarea = document.createElement('textarea');
                    textarea.name = 'content';
                    textarea.rows = 8;
                    textarea.value = initialData.content || '';
                    field.appendChild(textarea);
                    const hint = document.createElement('span');
                    hint.className = 'description';
                    hint.textContent = type === 'html' ? 'Supports full HTML markup.' : 'Plain text with optional basic HTML.';
                    field.appendChild(hint);
                    fragment.appendChild(field);
                } else if (type === 'rss') {
                    const urlField = document.createElement('div');
                    urlField.className = 'widget-field';
                    const urlLabel = document.createElement('label');
                    urlLabel.textContent = 'RSS feed URL';
                    urlField.appendChild(urlLabel);
                    const urlInput = document.createElement('input');
                    urlInput.type = 'url';
                    urlInput.name = 'rss_url';
                    urlInput.required = true;
                    urlInput.placeholder = 'https://example.com/feed';
                    urlInput.value = initialData.url || '';
                    urlField.appendChild(urlInput);
                    fragment.appendChild(urlField);

                    const itemsField = document.createElement('div');
                    itemsField.className = 'widget-field';
                    const itemsLabel = document.createElement('label');
                    itemsLabel.textContent = 'Number of items';
                    itemsField.appendChild(itemsLabel);
                    const itemsInput = document.createElement('input');
                    itemsInput.type = 'number';
                    itemsInput.name = 'rss_items';
                    itemsInput.min = '1';
                    itemsInput.max = '20';
                    itemsInput.value = initialData.items != null ? initialData.items : 5;
                    itemsField.appendChild(itemsInput);
                    fragment.appendChild(itemsField);
                } else if (type === 'recent_posts') {
                    const field = document.createElement('div');
                    field.className = 'widget-field';
                    const label = document.createElement('label');
                    label.textContent = 'Number of posts';
                    field.appendChild(label);
                    const input = document.createElement('input');
                    input.type = 'number';
                    input.name = 'posts_number';
                    input.min = '1';
                    input.max = '20';
                    input.value = initialData.number != null ? initialData.number : 5;
                    field.appendChild(input);
                    fragment.appendChild(field);
                } else {
                    const hintField = document.createElement('div');
                    hintField.className = 'widget-field';
                    const hint = document.createElement('span');
                    hint.className = 'description';
                    hint.textContent = 'This widget does not require extra configuration.';
                    hintField.appendChild(hint);
                    fragment.appendChild(hintField);
                }

                container.appendChild(fragment);
                container.style.display = container.children.length ? 'grid' : 'none';
            }

            if (typeSelect) {
                typeSelect.addEventListener('change', renderWidgetFields);
                if (widgetTypeInitial) {
                    typeSelect.value = widgetTypeInitial;
                }
                renderWidgetFields();
            }
        });
    </script>
</body>
</html>

