<?php
/**
 * URL Router & Obfuscation System
 * 
 * Maps clean, obfuscated URLs to actual system files
 * Hides system structure and file paths from users
 */

class Router {
    private static $routes = [];
    private static $initialized = false;
    
    /**
     * Initialize routes - maps friendly URLs to actual files
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Define route mappings (clean URLs -> actual files)
        // Format: 'friendly-url' => ['file' => 'actual/file.php', 'require_auth' => true/false, 'role' => 'admin']
        
        self::$routes = [
            // Public routes
            'login' => [
                'file' => 'login.php',
                'require_auth' => false
            ],
            'logout' => [
                'file' => 'logout.php',
                'require_auth' => true
            ],
            
            // Main dashboard
            'dashboard' => [
                'file' => 'modules/dashboard.php',
                'require_auth' => true
            ],
            'home' => [
                'file' => 'index.php',
                'require_auth' => true
            ],
            
            // Reports
            'reports' => [
                'file' => 'modules/field-reports.php',
                'require_auth' => true
            ],
            'reports/new' => [
                'file' => 'modules/field-reports.php?action=new',
                'require_auth' => true
            ],
            'reports/list' => [
                'file' => 'modules/field-reports-list.php',
                'require_auth' => true
            ],
            
            // Materials
            'materials' => [
                'file' => 'modules/materials.php',
                'require_auth' => true
            ],
            
            // Payroll
            'payroll' => [
                'file' => 'modules/payroll.php',
                'require_auth' => true
            ],
            
            // Finance
            'finance' => [
                'file' => 'modules/finance.php',
                'require_auth' => true
            ],
            
            // Loans
            'loans' => [
                'file' => 'modules/loans.php',
                'require_auth' => true
            ],
            
            // Clients
            'clients' => [
                'file' => 'modules/crm.php',
                'action' => 'clients',
                'require_auth' => true
            ],
            
            // System Management (Admin only)
            'system' => [
                'file' => 'modules/system.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/config' => [
                'file' => 'modules/config.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/data' => [
                'file' => 'modules/data-management.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/keys' => [
                'file' => 'modules/api-keys.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/users' => [
                'file' => 'modules/users.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/zoho' => [
                'file' => 'modules/zoho-integration.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/looker' => [
                'file' => 'modules/looker-studio-integration.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            'system/elk' => [
                'file' => 'modules/elk-integration.php',
                'require_auth' => true,
                'role' => 'admin'
            ],
            
            // Help
            'help' => [
                'file' => 'modules/help.php',
                'require_auth' => true
            ],
            
            // Reports/Receipts (with ID obfuscation)
            'receipt' => [
                'file' => 'modules/receipt.php',
                'require_auth' => true,
                'needs_id' => true
            ],
            'technical' => [
                'file' => 'modules/technical-report.php',
                'require_auth' => true,
                'needs_id' => true
            ],
        ];
        
        self::$initialized = true;
    }
    
    /**
     * Get route by friendly URL
     */
    public static function getRoute($url) {
        self::init();
        
        // Clean the URL
        $url = trim($url, '/');
        if (empty($url)) {
            $url = 'home';
        }
        
        // Check if route exists
        if (isset(self::$routes[$url])) {
            return self::$routes[$url];
        }
        
        // Handle dynamic routes (e.g., receipt/12345, technical/12345)
        $parts = explode('/', $url);
        if (count($parts) === 2) {
            $baseRoute = $parts[0];
            $id = $parts[1];
            
            if (isset(self::$routes[$baseRoute]) && isset(self::$routes[$baseRoute]['needs_id'])) {
                $route = self::$routes[$baseRoute];
                $route['id'] = self::decodeId($id);
                return $route;
            }
        }
        
        return null;
    }
    
    /**
     * Generate friendly URL from file path
     */
    public static function getUrl($file, $params = []) {
        self::init();
        
        // Find the route for this file
        foreach (self::$routes as $friendlyUrl => $route) {
            if ($route['file'] === $file || strpos($route['file'], $file) !== false) {
                $url = '/' . $friendlyUrl;
                
                // Add parameters if needed
                if (!empty($params)) {
                    if (isset($route['needs_id']) && isset($params['id'])) {
                        $encodedId = self::encodeId($params['id']);
                        $url .= '/' . $encodedId;
                    } else {
                        $url .= '?' . http_build_query($params);
                    }
                }
                
                return $url;
            }
        }
        
        // Fallback to original path
        return '/' . $file . (!empty($params) ? '?' . http_build_query($params) : '');
    }
    
    /**
     * Encode ID for URL obfuscation
     */
    public static function encodeId($id) {
        // Get secret key from config or generate one
        if (defined('APP_SECRET_KEY')) {
            $salt = APP_SECRET_KEY;
        } elseif (file_exists(__DIR__ . '/../config/secret.key')) {
            $salt = trim(file_get_contents(__DIR__ . '/../config/secret.key'));
        } else {
            // Generate a secret key if it doesn't exist
            $salt = hash('sha256', 'abbis-' . __DIR__ . '-2024-' . date('Y'));
            // Optionally save it for future use
            if (!file_exists(__DIR__ . '/../config')) {
                mkdir(__DIR__ . '/../config', 0755, true);
            }
            file_put_contents(__DIR__ . '/../config/secret.key', $salt);
        }
        
        $encoded = base64_encode($id . '|' . hash_hmac('sha256', $id, $salt));
        // Remove base64 padding and make URL-safe
        $encoded = rtrim(strtr($encoded, '+/', '-_'), '=');
        return $encoded;
    }
    
    /**
     * Decode obfuscated ID from URL
     */
    public static function decodeId($encoded) {
        try {
            // Get secret key
            if (defined('APP_SECRET_KEY')) {
                $salt = APP_SECRET_KEY;
            } elseif (file_exists(__DIR__ . '/../config/secret.key')) {
                $salt = trim(file_get_contents(__DIR__ . '/../config/secret.key'));
            } else {
                return null;
            }
            
            // Make URL-safe base64 back to standard base64
            $encoded = strtr($encoded, '-_', '+/');
            // Add padding if needed
            $padding = 4 - (strlen($encoded) % 4);
            if ($padding !== 4) {
                $encoded .= str_repeat('=', $padding);
            }
            
            $decoded = base64_decode($encoded, true);
            if ($decoded === false) {
                return null;
            }
            
            $parts = explode('|', $decoded);
            if (count($parts) !== 2) {
                return null;
            }
            
            $id = $parts[0];
            $hash = $parts[1];
            
            // Verify hash using HMAC
            if (hash_equals($hash, hash_hmac('sha256', $id, $salt))) {
                return $id;
            }
            
            return null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Dispatch route - load the actual file
     */
    public static function dispatch($url = null) {
        if ($url === null) {
            $url = $_SERVER['REQUEST_URI'] ?? '/';
            // Remove query string
            $url = strtok($url, '?');
            // Remove base path
            $basePath = dirname($_SERVER['SCRIPT_NAME']);
            if ($basePath !== '/' && strpos($url, $basePath) === 0) {
                $url = substr($url, strlen($basePath));
            }
        }
        
        $route = self::getRoute($url);
        
        if (!$route) {
            http_response_code(404);
            die('Page not found');
        }
        
        // Check authentication
        if (isset($route['require_auth']) && $route['require_auth']) {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /login?redirect=' . urlencode($url));
                exit;
            }
            
            // Check role requirement
            if (isset($route['role'])) {
                require_once __DIR__ . '/auth.php';
                global $auth;
                if (!isset($auth)) {
                    $auth = new Auth();
                }
                
                $userRole = $auth->getUserRole();
                if ($userRole !== $route['role'] && $userRole !== 'admin') {
                    http_response_code(403);
                    die('Access denied');
                }
            }
        }
        
        // Handle file path
        $file = $route['file'];
        
        // If file has query string, parse it
        if (strpos($file, '?') !== false) {
            list($file, $query) = explode('?', $file, 2);
            parse_str($query, $queryParams);
            $_GET = array_merge($_GET, $queryParams);
        }
        
        // Add ID to GET if it was decoded from URL
        if (isset($route['id'])) {
            $_GET['id'] = $route['id'];
            $_REQUEST['id'] = $route['id'];
        }
        
        // Include the actual file
        if (file_exists(__DIR__ . '/../' . $file)) {
            require_once __DIR__ . '/../' . $file;
        } else {
            http_response_code(404);
            die('File not found');
        }
    }
    
    /**
     * Get all available routes (for debugging/admin)
     */
    public static function getAllRoutes() {
        self::init();
        return self::$routes;
    }
}

