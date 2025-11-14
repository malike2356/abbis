<?php
/**
 * Module Router
 * Handles action routing for modules to eliminate repetitive switch statements
 */

class ModuleRouter {
    /**
     * Route action to appropriate view file
     * 
     * @param string $module Module name (for error messages)
     * @param array $routes Array of routes: ['action' => 'view-file.php', ...]
     * @param string $default Default action if not specified or invalid
     * @param string $paramName URL parameter name for action (default: 'action')
     * @param array $vars Optional array of variables to make available to included file
     * @return void Includes the appropriate view file
     */
    public static function route($module, $routes, $default = 'dashboard', $paramName = 'action', $vars = []) {
        $action = $_GET[$paramName] ?? $default;
        
        // Validate action exists in routes
        if (!isset($routes[$action])) {
            $action = $default;
        }
        
        $viewFile = $routes[$action];
        
        // Ensure view file exists
        if (!file_exists($viewFile)) {
            throw new Exception("View file not found for $module action '$action': $viewFile");
        }
        
        // Extract variables to make them available in included file
        // This allows parent scope variables (like $auth, $pdo) to be passed
        if (!empty($vars)) {
            extract($vars);
        }
        
        // Include the view file
        include $viewFile;
    }
    
    /**
     * Route with directory-based view files
     * Assumes view files are in same directory as module
     * 
     * @param string $moduleName Module name (e.g., 'crm', 'accounting')
     * @param array $views Array of views: ['action' => 'view-name', ...]
     * @param string $baseDir Base directory for view files (default: __DIR__ . '/../modules')
     * @param string $default Default action
     * @param string $paramName URL parameter name
     * @return void Includes the appropriate view file
     */
    public static function routeViews($moduleName, $views, $baseDir = null, $default = 'dashboard', $paramName = 'action') {
        if ($baseDir === null) {
            $baseDir = __DIR__ . '/../modules';
        }
        
        $action = $_GET[$paramName] ?? $default;
        
        // Validate action exists
        if (!isset($views[$action])) {
            $action = $default;
        }
        
        $viewName = $views[$action];
        $viewFile = $baseDir . '/' . $moduleName . '-' . $viewName . '.php';
        
        // Fallback: try without prefix if file doesn't exist
        if (!file_exists($viewFile)) {
            $viewFile = $baseDir . '/' . $viewName . '.php';
        }
        
        // Ensure view file exists
        if (!file_exists($viewFile)) {
            throw new Exception("View file not found for $moduleName action '$action': $viewFile");
        }
        
        // Include the view file
        try {
            include $viewFile;
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error loading view: ' . htmlspecialchars($e->getMessage()) . '</div>';
            error_log("ModuleRouter error: " . $e->getMessage());
        }
    }
    
    /**
     * Get current action
     * 
     * @param string $default Default action
     * @param string $paramName Parameter name
     * @return string Current action
     */
    public static function getCurrentAction($default = 'dashboard', $paramName = 'action') {
        return $_GET[$paramName] ?? $default;
    }
}
