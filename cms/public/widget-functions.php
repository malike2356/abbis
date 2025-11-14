<?php
/**
 * Widget Rendering Functions for Frontend
 * WordPress-like widget system
 */

/**
 * Get widgets for a specific widget area
 */
function getWidgetsForArea($slug, $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, wa.name as area_name, wa.location
            FROM cms_widgets w
            INNER JOIN cms_widget_areas wa ON w.widget_area_id = wa.id
            WHERE wa.slug = ? AND w.is_active = 1
            ORDER BY w.widget_order ASC
        ");
        $stmt->execute([$slug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Render a single widget
 */
function renderWidget($widget, $pdo) {
    $data = json_decode($widget['widget_data'] ?? '{}', true);
    $title = $widget['widget_title'];
    $type = $widget['widget_type'];
    $baseUrl = app_base_path();

    ob_start();
    ?>
    <div class="widget widget-<?php echo htmlspecialchars($type); ?>">
        <?php if ($title): ?>
            <h3 class="widget-title"><?php echo htmlspecialchars($title); ?></h3>
        <?php endif; ?>
        <div class="widget-content">
            <?php
            switch ($type) {
                case 'text':
                    echo nl2br(htmlspecialchars($data['content'] ?? ''));
                    break;
                    
                case 'html':
                    echo $data['content'] ?? '';
                    break;
                    
                case 'recent_posts':
                    $number = $data['number'] ?? 5;
                    try {
                        $posts = $pdo->prepare("SELECT * FROM cms_posts WHERE status='published' ORDER BY published_at DESC LIMIT ?");
                        $posts->execute([$number]);
                        $posts = $posts->fetchAll();
                        if (!empty($posts)) {
                            echo '<ul>';
                            foreach ($posts as $post) {
                                echo '<li><a href="' . $baseUrl . '/cms/post/' . htmlspecialchars($post['slug']) . '">' . htmlspecialchars($post['title']) . '</a></li>';
                            }
                            echo '</ul>';
                        }
                    } catch (Exception $e) {}
                    break;
                    
                case 'categories':
                    try {
                        $categories = $pdo->query("SELECT * FROM cms_categories ORDER BY name");
                        echo '<ul>';
                        while ($cat = $categories->fetch()) {
                            echo '<li><a href="' . $baseUrl . '/cms/category/' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</a></li>';
                        }
                        echo '</ul>';
                    } catch (Exception $e) {}
                    break;
                    
                case 'search':
                    echo '<form method="get" action="' . $baseUrl . '/cms/search"><input type="search" name="q" placeholder="Search..."><button type="submit">Search</button></form>';
                    break;
                    
                case 'pages':
                    try {
                        $pages = $pdo->query("SELECT * FROM cms_pages WHERE status='published' ORDER BY title");
                        echo '<ul>';
                        while ($page = $pages->fetch()) {
                            echo '<li><a href="' . $baseUrl . '/cms/page/' . htmlspecialchars($page['slug']) . '">' . htmlspecialchars($page['title']) . '</a></li>';
                        }
                        echo '</ul>';
                    } catch (Exception $e) {}
                    break;
                    
                case 'rss':
                    if (!empty($data['url'])) {
                        $items = $data['items'] ?? 5;
                        $rss = @simplexml_load_file($data['url']);
                        if ($rss) {
                            echo '<ul>';
                            $count = 0;
                            foreach ($rss->channel->item as $item) {
                                if ($count++ >= $items) break;
                                echo '<li><a href="' . htmlspecialchars($item->link) . '" target="_blank">' . htmlspecialchars($item->title) . '</a></li>';
                            }
                            echo '</ul>';
                        }
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render all widgets in an area
 */
function renderWidgetArea($slug, $pdo) {
    $widgets = getWidgetsForArea($slug, $pdo);
    if (empty($widgets)) {
        return '';
    }
    
    ob_start();
    foreach ($widgets as $widget) {
        echo renderWidget($widget, $pdo);
    }
    return ob_get_clean();
}

