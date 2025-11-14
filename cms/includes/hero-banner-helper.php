<?php
/**
 * Hero Banner Display Helper
 * Checks if hero banner should be displayed on current page
 */

/**
 * Check if hero banner should be displayed on current page
 * @param array $cmsSettings CMS settings array
 * @param string $currentPageType Current page type (homepage, blog, shop, about, services, contact, portfolio, quote, or page slug)
 * @return bool True if hero should be displayed
 */
function shouldDisplayHeroBanner($cmsSettings, $currentPageType = 'homepage') {
    // Check if hero is enabled
    $heroEnabled = $cmsSettings['hero_enabled'] ?? '1';
    if ($heroEnabled !== '1') {
        return false;
    }
    
    // Get display locations
    $displayLocations = !empty($cmsSettings['hero_display_locations']) 
        ? explode(',', $cmsSettings['hero_display_locations']) 
        : ['homepage']; // Default to homepage if not set
    
    // If "all_pages" is selected, show everywhere
    if (in_array('all_pages', $displayLocations)) {
        return true;
    }
    
    // Check if current page type is in display locations
    if (in_array($currentPageType, $displayLocations)) {
        return true;
    }
    
    // Special handling for homepage
    if ($currentPageType === 'homepage' && in_array('homepage', $displayLocations)) {
        return true;
    }
    
    // Check page slug matches (for custom pages)
    if (isset($_GET['slug'])) {
        $slug = $_GET['slug'];
        // Map common slugs to location keys
        $slugMap = [
            'about' => 'about',
            'services' => 'services',
            'contact' => 'contact',
            'portfolio' => 'portfolio',
            'quote' => 'quote',
            'shop' => 'shop',
            'products' => 'shop',
            'blog' => 'blog'
        ];
        
        if (isset($slugMap[$slug]) && in_array($slugMap[$slug], $displayLocations)) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get current page type from request
 * @return string Page type identifier
 */
function getCurrentPageType() {
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    $requestUri = strtok($requestUri, '?');
    $requestUri = preg_replace('#^/abbis3.2#', '', $requestUri);
    $requestUri = trim($requestUri, '/');
    
    // Homepage
    if (empty($requestUri) || $requestUri === 'cms' || $requestUri === 'cms/') {
        return 'homepage';
    }
    
    // Extract path segments
    $parts = explode('/', $requestUri);
    
    // Check for specific routes
    if (in_array('blog', $parts) || in_array('post', $parts)) {
        return 'blog';
    }
    
    if (in_array('shop', $parts) || in_array('products', $parts) || in_array('product', $parts)) {
        return 'shop';
    }
    
    if (in_array('portfolio', $parts)) {
        return 'portfolio';
    }
    
    if (in_array('quote', $parts) || in_array('request', $parts)) {
        return 'quote';
    }
    
    // Check slug from GET parameter
    if (isset($_GET['slug'])) {
        $slug = $_GET['slug'];
        $slugMap = [
            'about' => 'about',
            'services' => 'services',
            'contact' => 'contact',
            'portfolio' => 'portfolio',
            'quote' => 'quote',
            'shop' => 'shop',
            'products' => 'shop',
            'blog' => 'blog'
        ];
        
        if (isset($slugMap[$slug])) {
            return $slugMap[$slug];
        }
    }
    
    // Check last segment
    $lastSegment = end($parts);
    $slugMap = [
        'about' => 'about',
        'services' => 'services',
        'contact' => 'contact',
        'portfolio' => 'portfolio',
        'quote' => 'quote',
        'shop' => 'shop',
        'products' => 'shop',
        'blog' => 'blog'
    ];
    
    if (isset($slugMap[$lastSegment])) {
        return $slugMap[$lastSegment];
    }
    
    // Default to homepage for index pages
    return 'homepage';
}

