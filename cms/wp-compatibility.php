<?php
/**
 * WordPress Compatibility Layer
 * 
 * This file provides WordPress function compatibility for integrating
 * WordPress themes and plugins into this CMS system.
 * 
 * Usage: Include this file in your WordPress theme files:
 * require_once __DIR__ . '/wp-compatibility.php';
 */

// Prevent direct access
if (!defined('CMS_WP_COMPAT')) {
    define('CMS_WP_COMPAT', true);
}

// Global variables (WordPress-style)
global $post, $posts, $wp_query, $pdo;

// Initialize database connection if not set
if (!isset($pdo)) {
    $rootPath = dirname(dirname(__DIR__));
    require_once $rootPath . '/config/app.php';
    require_once $rootPath . '/includes/functions.php';
    $pdo = getDBConnection();
}

// WordPress Query Object
class WP_Query {
    public $posts = [];
    public $post_count = 0;
    public $found_posts = 0;
    
    public function __construct($query = []) {
        global $pdo;
        
        $post_type = $query['post_type'] ?? 'post';
        $posts_per_page = $query['posts_per_page'] ?? 10;
        
        if ($post_type === 'page') {
            $stmt = $pdo->query("SELECT * FROM cms_pages WHERE status='published' ORDER BY created_at DESC LIMIT $posts_per_page");
            $this->posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM cms_posts WHERE status='published' ORDER BY published_at DESC LIMIT $posts_per_page");
            $this->posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        $this->post_count = count($this->posts);
        $this->found_posts = $this->post_count;
    }
}

// Initialize global query
$wp_query = new WP_Query();

// ==========================================
// TEMPLATE FUNCTIONS
// ==========================================

/**
 * Get header template
 */
function get_header($name = null) {
    $template = $name ? "header-{$name}.php" : "header.php";
    $themePath = __DIR__ . '/themes/active/' . $template;
    if (file_exists($themePath)) {
        include $themePath;
    } else {
        include __DIR__ . '/public/header.php';
    }
}

/**
 * Get footer template
 */
function get_footer($name = null) {
    $template = $name ? "footer-{$name}.php" : "footer.php";
    $themePath = __DIR__ . '/themes/active/' . $template;
    if (file_exists($themePath)) {
        include $themePath;
    } else {
        include __DIR__ . '/public/footer.php';
    }
}

/**
 * Display the title
 */
function the_title($before = '', $after = '', $echo = true) {
    global $post;
    $title = $post['title'] ?? '';
    $output = $before . $title . $after;
    if ($echo) {
        echo $output;
    }
    return $output;
}

/**
 * Display the content
 */
function the_content($more_link_text = null, $strip_teaser = false) {
    global $post;
    $content = $post['content'] ?? '';
    echo apply_filters('the_content', $content);
}

/**
 * Get the permalink
 */
function get_permalink($post_id = null) {
    global $post, $baseUrl;
    if (!$baseUrl) {
        $baseUrl = '/abbis3.2';
    }
    
    if ($post_id) {
        // Get post by ID
        global $pdo;
        $stmt = $pdo->prepare("SELECT slug FROM cms_posts WHERE id=? LIMIT 1");
        $stmt->execute([$post_id]);
        $slug = $stmt->fetchColumn();
        return $baseUrl . '/cms/post/' . $slug;
    }
    
    $slug = $post['slug'] ?? '';
    return $baseUrl . '/cms/post/' . $slug;
}

/**
 * Display permalink
 */
function the_permalink() {
    echo get_permalink();
}

// ==========================================
// NAVIGATION FUNCTIONS
// ==========================================

/**
 * Display navigation menu
 */
function wp_nav_menu($args = []) {
    global $pdo;
    require_once __DIR__ . '/public/menu-functions.php';
    
    $location = $args['theme_location'] ?? 'primary';
    $items = getMenuItemsForLocation($location, $pdo);
    
    echo renderMenuItems($items);
}

// ==========================================
// OPTIONS FUNCTIONS
// ==========================================

/**
 * Get option value
 */
function get_option($option, $default = false) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1");
        $stmt->execute([$option]);
        $value = $stmt->fetchColumn();
        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Update option value
 */
function update_option($option, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO cms_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?");
        $stmt->execute([$option, $value, $value]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Delete option
 */
function delete_option($option) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM cms_settings WHERE setting_key=?");
        $stmt->execute([$option]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ==========================================
// BLOGINFO FUNCTIONS
// ==========================================

/**
 * Get blog information
 */
function get_bloginfo($show = '', $filter = 'raw') {
    switch ($show) {
        case 'name':
        case 'blogname':
            return get_option('site_title', 'CMS Site');
        case 'description':
        case 'blogdescription':
            return get_option('site_tagline', '');
        case 'url':
        case 'siteurl':
            return get_option('site_url', '/');
        case 'charset':
            return 'UTF-8';
        default:
            return '';
    }
}

/**
 * Display blog information
 */
function bloginfo($show = '') {
    echo get_bloginfo($show);
}

// ==========================================
// HOOK SYSTEM (Actions & Filters)
// ==========================================

global $wp_actions, $wp_filters;
$wp_actions = [];
$wp_filters = [];

/**
 * Add action hook
 */
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
    global $wp_actions;
    if (!isset($wp_actions[$hook])) {
        $wp_actions[$hook] = [];
    }
    if (!isset($wp_actions[$hook][$priority])) {
        $wp_actions[$hook][$priority] = [];
    }
    $wp_actions[$hook][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args
    ];
}

/**
 * Execute action hook
 */
function do_action($hook, $arg = '') {
    global $wp_actions;
    if (isset($wp_actions[$hook])) {
        ksort($wp_actions[$hook]);
        foreach ($wp_actions[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback_data) {
                $callback = $callback_data['callback'];
                $accepted_args = $callback_data['accepted_args'];
                $args = func_get_args();
                array_shift($args); // Remove $hook
                $args = array_slice($args, 0, $accepted_args);
                call_user_func_array($callback, $args);
            }
        }
    }
}

/**
 * Add filter hook
 */
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {
    global $wp_filters;
    if (!isset($wp_filters[$hook])) {
        $wp_filters[$hook] = [];
    }
    if (!isset($wp_filters[$hook][$priority])) {
        $wp_filters[$hook][$priority] = [];
    }
    $wp_filters[$hook][$priority][] = [
        'callback' => $callback,
        'accepted_args' => $accepted_args
    ];
}

/**
 * Apply filter hook
 */
function apply_filters($hook, $value, ...$args) {
    global $wp_filters;
    if (isset($wp_filters[$hook])) {
        ksort($wp_filters[$hook]);
        foreach ($wp_filters[$hook] as $priority => $callbacks) {
            foreach ($callbacks as $callback_data) {
                $callback = $callback_data['callback'];
                $value = call_user_func($callback, $value, ...$args);
            }
        }
    }
    return $value;
}

// ==========================================
// POST META FUNCTIONS
// ==========================================

/**
 * Get post meta
 */
function get_post_meta($post_id, $key = '', $single = false) {
    global $pdo;
    try {
        // Check if meta table exists
        $pdo->query("SELECT 1 FROM cms_post_meta LIMIT 1");
    } catch (PDOException $e) {
        // Create meta table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS cms_post_meta (
            id INT AUTO_INCREMENT PRIMARY KEY,
            post_id INT NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            INDEX idx_post (post_id),
            INDEX idx_key (meta_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    if ($key) {
        $stmt = $pdo->prepare("SELECT meta_value FROM cms_post_meta WHERE post_id=? AND meta_key=? LIMIT 1");
        $stmt->execute([$post_id, $key]);
        $value = $stmt->fetchColumn();
        return $single ? $value : [$value];
    } else {
        $stmt = $pdo->prepare("SELECT meta_key, meta_value FROM cms_post_meta WHERE post_id=?");
        $stmt->execute([$post_id]);
        $meta = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $meta[$row['meta_key']] = $row['meta_value'];
        }
        return $meta;
    }
}

/**
 * Update post meta
 */
function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO cms_post_meta (post_id, meta_key, meta_value) VALUES (?,?,?) ON DUPLICATE KEY UPDATE meta_value=?");
        $stmt->execute([$post_id, $meta_key, $meta_value, $meta_value]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// ==========================================
// UTILITY FUNCTIONS
// ==========================================

/**
 * Language attributes
 */
function language_attributes($doctype = 'html') {
    echo 'lang="en"';
}

/**
 * Body classes
 */
function body_class($class = '') {
    $classes = ['cms-body'];
    if ($class) {
        $classes[] = $class;
    }
    echo 'class="' . implode(' ', $classes) . '"';
}

/**
 * WP Head action (for theme/plugin scripts)
 */
function wp_head() {
    do_action('wp_head');
}

/**
 * WP Footer action
 */
function wp_footer() {
    do_action('wp_footer');
}

// Initialize common hooks
add_action('wp_head', function() {
    // Add default meta tags, styles, etc.
}, 1);

add_action('wp_footer', function() {
    // Add default footer scripts
}, 1);

