<?php
/**
 * Menu Helper Functions
 * Functions to retrieve and render menus based on location
 */

/**
 * Get menu items for a specific location
 */
function getMenuItemsForLocation($location, $pdo) {
    // First, get the menu assigned to this location
    $locationStmt = $pdo->prepare("SELECT menu_name FROM cms_menu_locations WHERE location_name=? LIMIT 1");
    $locationStmt->execute([$location]);
    $locationData = $locationStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$locationData || empty($locationData['menu_name'])) {
        // Fallback to old system (menu_type)
        $menuStmt = $pdo->prepare("SELECT * FROM cms_menu_items WHERE menu_type=? ORDER BY menu_order, id");
        $menuStmt->execute([$location]);
        return $menuStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $menuName = $locationData['menu_name'];
    
    // Get all menu items for this menu
    $itemsStmt = $pdo->prepare("SELECT * FROM cms_menu_items WHERE menu_name=? ORDER BY menu_order, id");
    $itemsStmt->execute([$menuName]);
    return $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Render menu items as HTML
 */
function renderMenuItems($items, $parentId = null, $level = 0, $context = 'header') {
    $children = array_filter($items, function($item) use ($parentId) {
        return ($item['parent_id'] ?? null) == $parentId;
    });
    
    if (empty($children)) {
        return '';
    }
    
    $html = '';
    // Different classes for header vs footer
    if ($context === 'footer') {
        $ulClass = $level === 0 ? 'cms-footer-menu' : 'cms-footer-submenu';
        $liClass = 'cms-footer-menu-item';
    } else {
        $ulClass = $level === 0 ? 'cms-nav-menu' : 'cms-submenu';
        $liClass = $level === 0 ? 'cms-nav-item' : 'cms-submenu-item';
    }
    
    $html .= '<ul class="' . $ulClass . '">';
    
    if (!isset($baseUrl)) {
        require_once __DIR__ . '/base-url.php';
    }
    $base = rtrim($baseUrl ?? '', '/');

    foreach ($children as $item) {
        $isActive = false;
        $currentPath = $_SERVER['REQUEST_URI'] ?? '';
        $currentPath = preg_replace('#\?.*$#', '', $currentPath);
        
        if (!empty($item['url'])) {
            $itemPath = parse_url($item['url'], PHP_URL_PATH);
            if ($itemPath && strpos($currentPath, $itemPath) !== false) {
                $isActive = true;
            }
        }
        
        $classes = [$liClass];
        if ($isActive) {
            $classes[] = 'active';
        }
        if (!empty($item['css_class'])) {
            $classes[] = htmlspecialchars($item['css_class']);
        }
        if ($level > 0) {
            $classes[] = 'has-parent';
        }
        
        $html .= '<li class="' . implode(' ', $classes) . '">';
        $linkClass = $context === 'footer' ? 'cms-footer-link' : 'cms-nav-link';
        $href = normaliseMenuUrl($item['url'] ?? '', $base);
        $html .= '<a href="' . htmlspecialchars($href) . '" class="' . $linkClass . '">';
        $html .= htmlspecialchars($item['label']);
        $html .= '</a>';
        
        // Render children
        $childHtml = renderMenuItems($items, $item['id'], $level + 1, $context);
        if ($childHtml) {
            $html .= $childHtml;
        }
        
        $html .= '</li>';
    }
    
    $html .= '</ul>';
    
    return $html;
}

/**
 * Normalise menu URL to ensure absolute paths resolve correctly
 */
function normaliseMenuUrl(string $url, string $baseUrl): string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '#';
    }

    // External links (http, https, mailto, tel) should remain untouched
    if (preg_match('#^(https?:)?//#i', $trimmed) || preg_match('#^(mailto|tel):#i', $trimmed)) {
        return $trimmed;
    }

    // Anchor links should stay relative
    if ($trimmed[0] === '#') {
        return $trimmed;
    }

    // Ensure leading slash for site-relative paths
    if ($trimmed[0] !== '/') {
        $trimmed = '/' . ltrim($trimmed, '/');
    }

    if ($baseUrl !== '') {
        return $baseUrl . $trimmed;
    }

    return $trimmed;
}

