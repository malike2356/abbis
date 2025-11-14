<?php
/**
 * Centralized URL Management System
 * Single Source of Truth for All URLs in ABBIS
 * 
 * This ensures easy deployment - just change APP_URL in environment.php
 * and all URLs throughout the system will update automatically.
 */

if (!function_exists('base_url')) {
    /**
     * Get base URL (without trailing slash)
     * @return string
     */
    function base_url(): string {
        return rtrim(APP_URL, '/');
    }
}

if (!function_exists('site_url')) {
    /**
     * Generate full URL for any path
     * @param string $path Path relative to site root (e.g., 'modules/dashboard.php')
     * @return string Full URL
     */
    function site_url(string $path = ''): string {
        $base = base_url();
        if (empty($path)) {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_url')) {
    /**
     * Generate URL for assets (CSS, JS, images)
     * @param string $path Asset path (e.g., 'assets/css/style.css')
     * @return string Full asset URL
     */
    function asset_url(string $path): string {
        return site_url($path);
    }
}

if (!function_exists('api_url')) {
    /**
     * Generate URL for API endpoints
     * @param string $endpoint API endpoint (e.g., 'export.php' or 'api/export.php')
     * @param array $params Query parameters
     * @return string Full API URL
     */
    function api_url(string $endpoint, array $params = []): string {
        // Ensure endpoint starts with 'api/' if not already
        if (strpos($endpoint, 'api/') !== 0 && strpos($endpoint, '/api/') !== 0) {
            $endpoint = 'api/' . ltrim($endpoint, '/');
        }
        
        $url = site_url($endpoint);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('module_url')) {
    /**
     * Generate URL for module pages
     * @param string $module Module file (e.g., 'dashboard.php' or 'field-reports.php')
     * @param array $params Query parameters
     * @return string Full module URL
     */
    function module_url(string $module, array $params = []): string {
        // Remove .php extension if present
        $module = preg_replace('/\.php$/', '', $module);
        
        // Ensure it starts with 'modules/'
        if (strpos($module, 'modules/') !== 0) {
            $module = 'modules/' . ltrim($module, '/');
        }
        
        $url = site_url($module . '.php');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('cms_url')) {
    /**
     * Generate URL for CMS pages
     * @param string $path CMS path (e.g., 'admin/index.php' or 'public/shop.php')
     * @param array $params Query parameters
     * @return string Full CMS URL
     */
    function cms_url(string $path, array $params = []): string {
        // Ensure it starts with 'cms/'
        if (strpos($path, 'cms/') !== 0) {
            $path = 'cms/' . ltrim($path, '/');
        }
        
        $url = site_url($path);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('client_portal_url')) {
    /**
     * Generate URL for client portal pages
     * @param string $page Page file (e.g., 'dashboard.php' or 'login.php')
     * @param array $params Query parameters
     * @return string Full client portal URL
     */
    function client_portal_url(string $page = 'dashboard.php', array $params = []): string {
        $url = site_url('client-portal/' . ltrim($page, '/'));
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('pos_url')) {
    /**
     * Generate URL for POS pages
     * @param string $path POS path (e.g., 'index.php' or 'api/sales.php')
     * @param array $params Query parameters
     * @return string Full POS URL
     */
    function pos_url(string $path = '', array $params = []): string {
        if (empty($path)) {
            $path = 'pos/index.php';
        } elseif (strpos($path, 'pos/') !== 0) {
            $path = 'pos/' . ltrim($path, '/');
        }
        
        $url = site_url($path);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('upload_url')) {
    /**
     * Generate URL for uploaded files
     * @param string $file Uploaded file path (relative to uploads/)
     * @return string Full upload URL
     */
    function upload_url(string $file): string {
        return site_url('uploads/' . ltrim($file, '/'));
    }
}

if (!function_exists('redirect_url')) {
    /**
     * Generate redirect URL (for header('Location: ...'))
     * @param string $path Path to redirect to
     * @param array $params Query parameters
     * @return string Full redirect URL
     */
    function redirect_url(string $path, array $params = []): string {
        $url = site_url($path);
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('current_url')) {
    /**
     * Get current page URL
     * @param bool $includeQuery Include query string
     * @return string Current URL
     */
    function current_url(bool $includeQuery = true): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        
        if (!$includeQuery) {
            $uri = strtok($uri, '?');
        }
        
        return $scheme . '://' . $host . $uri;
    }
}

if (!function_exists('relative_url')) {
    /**
     * Generate relative URL (for use in same directory context)
     * @param string $path Relative path
     * @return string Relative URL
     */
    function relative_url(string $path): string {
        return ltrim($path, '/');
    }
}

// Note: app_url() is already defined in config/environment.php
// We use site_url() as an alias that works the same way
// Both functions use APP_URL as the single source of truth

/**
 * JavaScript URL Helper
 * Outputs JavaScript object with URL helper functions for frontend use
 */
if (!function_exists('url_js_helper')) {
    function url_js_helper(): string {
        $baseUrl = base_url();
        return "
        <script>
        if (typeof ABBIS_URLS === 'undefined') {
            var ABBIS_URLS = {
                base: '{$baseUrl}',
                site: function(path) {
                    path = path || '';
                    if (path && !path.startsWith('/')) path = '/' + path;
                    return this.base + path;
                },
                api: function(endpoint, params) {
                    var url = this.base + '/api/' + endpoint.replace(/^api\\//, '');
                    if (params) {
                        var query = Object.keys(params).map(function(k) {
                            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                        }).join('&');
                        url += '?' + query;
                    }
                    return url;
                },
                module: function(module, params) {
                    var url = this.base + '/modules/' + module.replace(/\\.php$/, '') + '.php';
                    if (params) {
                        var query = Object.keys(params).map(function(k) {
                            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                        }).join('&');
                        url += '?' + query;
                    }
                    return url;
                },
                asset: function(path) {
                    return this.base + '/' + path.replace(/^\\//, '');
                },
                upload: function(file) {
                    return this.base + '/uploads/' + file.replace(/^\\//, '');
                },
                clientPortal: function(page, params) {
                    var url = this.base + '/client-portal/' + (page || 'dashboard.php');
                    if (params) {
                        var query = Object.keys(params).map(function(k) {
                            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                        }).join('&');
                        url += '?' + query;
                    }
                    return url;
                },
                pos: function(path, params) {
                    var url = this.base + '/pos/' + (path || 'index.php');
                    if (params) {
                        var query = Object.keys(params).map(function(k) {
                            return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
                        }).join('&');
                        url += '?' + query;
                    }
                    return url;
                }
            };
        }
        </script>";
    }
}

