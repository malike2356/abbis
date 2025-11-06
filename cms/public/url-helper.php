<?php
/**
 * CMS URL Helper Functions
 */

/**
 * Get the base URL for CMS links
 */
function getCMSBaseUrl() {
    // Detect base path from current request
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = dirname($scriptName);
    
    // Remove trailing slash if present (except for root)
    if ($basePath !== '/' && substr($basePath, -1) === '/') {
        $basePath = rtrim($basePath, '/');
    }
    
    // For CMS files in /cms/public/, go up two levels
    if (strpos($scriptName, '/cms/public/') !== false) {
        $basePath = dirname(dirname(dirname($scriptName)));
    }
    
    return $basePath;
}

/**
 * Generate a CMS URL
 */
function cms_url($path = '') {
    $base = getCMSBaseUrl();
    
    // Remove leading slash from path
    $path = ltrim($path, '/');
    
    // Handle special CMS routes
    $routes = [
        'shop' => $base . '/cms/public/shop.php',
        'quote' => $base . '/cms/public/quote.php',
        'cart' => $base . '/cms/public/cart.php',
        'checkout' => $base . '/cms/public/cart.php',
        'blog' => $base . '/cms/public/blog.php',
    ];
    
    if (isset($routes[$path])) {
        return $routes[$path];
    }
    
    // Check if mod_rewrite is likely working (clean URLs)
    // For now, use direct file paths as fallback
    if (strpos($path, 'post/') === 0) {
        $slug = str_replace('post/', '', $path);
        return $base . '/cms/public/post.php?slug=' . urlencode($slug);
    }
    
    // Default to page.php for custom pages
    if ($path) {
        return $base . '/cms/public/page.php?slug=' . urlencode($path);
    }
    
    return $base . '/';
}

