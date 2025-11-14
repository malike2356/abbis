# WordPress Theme & Plugin Integration Guide

## Overview

Yes, it is **possible** to integrate WordPress themes and plugins into this CMS system, but it requires a compatibility layer that translates WordPress functions to our system's functions.

## How It Works

### Current System Architecture

Our CMS uses:
- **Database Structure**: Similar to WordPress (posts, pages, menus, settings)
- **PHP-based**: Like WordPress
- **Theme System**: Located in `cms/themes/`
- **Plugin System**: Framework exists in `cms/admin/plugins.php`

### WordPress Compatibility Challenges

WordPress uses thousands of functions like:
- `get_header()`, `get_footer()`
- `wp_nav_menu()`, `the_title()`, `the_content()`
- `get_option()`, `get_post_meta()`
- Action hooks: `add_action()`, `do_action()`
- Filter hooks: `add_filter()`, `apply_filters()`

## Integration Approaches

### Approach 1: Compatibility Layer (Recommended)

Create a WordPress compatibility layer that translates WordPress functions to our system:

**File: `cms/wp-compatibility.php`**

```php
<?php
/**
 * WordPress Compatibility Layer
 * Translates WordPress functions to our CMS functions
 */

// WordPress functions
function get_header($name = null) {
    include __DIR__ . '/../themes/active/header.php';
}

function get_footer($name = null) {
    include __DIR__ . '/../themes/active/footer.php';
}

function wp_nav_menu($args = []) {
    global $pdo;
    require_once __DIR__ . '/menu-functions.php';
    $location = $args['theme_location'] ?? 'primary';
    $items = getMenuItemsForLocation($location, $pdo);
    echo renderMenuItems($items);
}

function get_option($option, $default = false) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1");
    $stmt->execute([$option]);
    $value = $stmt->fetchColumn();
    return $value !== false ? $value : $default;
}

function get_post_meta($post_id, $key = '', $single = false) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT meta_value FROM cms_post_meta WHERE post_id=? AND meta_key=? LIMIT 1");
    $stmt->execute([$post_id, $key]);
    $value = $stmt->fetchColumn();
    return $single ? $value : [$value];
}

function the_title($before = '', $after = '', $echo = true) {
    global $post;
    $title = $post['title'] ?? '';
    $output = $before . $title . $after;
    if ($echo) echo $output;
    return $output;
}

function the_content($more_link_text = null, $strip_teaser = false) {
    global $post;
    echo $post['content'] ?? '';
}

// Add more WordPress functions as needed...
```

### Approach 2: Theme Converter

Create a tool that converts WordPress themes to our format:

1. **Parse WordPress theme files** (header.php, footer.php, index.php, etc.)
2. **Replace WordPress functions** with our equivalents
3. **Convert template tags** (`<?php the_title(); ?>` → `<?php echo $page['title']; ?>`)
4. **Adapt WordPress hooks** to our event system

### Approach 3: Direct Theme Support

Support WordPress-style themes directly:

1. Create `wp-config.php` compatibility file
2. Load WordPress compatibility layer before theme
3. Map WordPress database tables to our tables
4. Support WordPress template hierarchy

## Implementation Steps

### Step 1: Create Compatibility Layer

```bash
# Create compatibility file
touch cms/wp-compatibility.php
```

### Step 2: Add WordPress Functions

Start with the most common WordPress functions:
- Template tags: `the_title()`, `the_content()`, `the_permalink()`
- Navigation: `wp_nav_menu()`, `wp_list_pages()`
- Options: `get_option()`, `update_option()`
- Hooks: `add_action()`, `do_action()`, `add_filter()`

### Step 3: Database Mapping

Map WordPress tables to our tables:

| WordPress | Our CMS | Notes |
|-----------|---------|-------|
| `wp_posts` | `cms_posts` | Similar structure |
| `wp_pages` | `cms_pages` | Similar structure |
| `wp_options` | `cms_settings` | Key-value pairs |
| `wp_postmeta` | `cms_post_meta` | Need to create |
| `wp_terms` | `cms_categories` | Similar structure |

### Step 4: Hook System

Implement WordPress hooks:

```php
// In wp-compatibility.php
$wp_actions = [];
$wp_filters = [];

function add_action($hook, $callback, $priority = 10) {
    global $wp_actions;
    $wp_actions[$hook][$priority][] = $callback;
}

function do_action($hook, $arg = '') {
    global $wp_actions;
    if (isset($wp_actions[$hook])) {
        ksort($wp_actions[$hook]);
        foreach ($wp_actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                call_user_func($callback, $arg);
            }
        }
    }
}
```

### Step 5: Theme Support

Enable WordPress theme support:

1. Create `cms/themes/wordpress-theme-name/` directory
2. Copy WordPress theme files
3. Include compatibility layer at the top
4. Test and fix compatibility issues

## Plugin Integration

### WordPress Plugin Structure

WordPress plugins typically:
- Use WordPress hooks (`add_action`, `add_filter`)
- Access WordPress functions (`get_option`, `wp_insert_post`)
- Use WordPress database (`$wpdb`)

### Plugin Compatibility

To support WordPress plugins:

1. **Implement WordPress Database API**
   ```php
   global $wpdb;
   $wpdb->prefix = 'cms_';
   $wpdb->get_results("SELECT * FROM cms_posts");
   ```

2. **Support WordPress Hooks**
   - Implement `add_action()` and `do_action()`
   - Implement `add_filter()` and `apply_filters()`

3. **WordPress Admin Compatibility**
   - Map WordPress admin pages to our admin
   - Support WordPress admin hooks

## Example: Converting a WordPress Theme

### Original WordPress Theme (header.php)

```php
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php wp_title('|', true, 'right'); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
    <header>
        <?php wp_nav_menu(['theme_location' => 'primary']); ?>
    </header>
```

### Converted for Our CMS

```php
<?php
require_once __DIR__ . '/../../wp-compatibility.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $page['title'] ?? 'Site Title'; ?></title>
    <?php do_action('wp_head'); ?>
</head>
<body>
    <header>
        <?php wp_nav_menu(['theme_location' => 'primary']); ?>
    </header>
```

## Limitations

1. **Full Compatibility**: Not all WordPress functions will work
2. **Plugin Compatibility**: Complex plugins may need significant modifications
3. **Performance**: Compatibility layer adds overhead
4. **Updates**: WordPress theme/plugin updates may break compatibility

## Recommended Approach

**For Themes:**
- Start with simpler themes (minimal hooks, standard functions)
- Gradually add compatibility functions as needed
- Test thoroughly before using in production

**For Plugins:**
- Focus on compatibility for popular plugins
- Create wrapper functions for plugin APIs
- Consider rewriting plugins for our system instead

## Getting Started

1. **Create compatibility file**: `cms/wp-compatibility.php`
2. **Add basic functions**: Start with 20-30 most common functions
3. **Test with simple theme**: Try a minimal WordPress theme
4. **Iterate**: Add more functions as needed

## Conclusion

WordPress integration is **feasible** but requires:
- Significant development effort
- Ongoing maintenance
- Testing and compatibility fixes
- Potentially better to adapt themes/plugins rather than full compatibility

**Recommendation**: Create a compatibility layer for basic WordPress themes, but consider developing native themes/plugins for better performance and features.

