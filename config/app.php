<?php
/**
 * Application Configuration
 */

// Environment
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // Set to 'development' for local testing
define('DEBUG', APP_ENV === 'development');

// Error Reporting - Enable for development
error_reporting(E_ALL);
// Show errors in development, hide in production
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');

// Timezone
date_default_timezone_set('Africa/Accra');

// Application Info (only define if not already defined)
if (!defined('APP_NAME')) {
    define('APP_NAME', 'ABBIS');
}
if (!defined('APP_VERSION')) {
    define('APP_VERSION', '3.2.0');
}
if (!defined('APP_URL')) {
    define('APP_URL', 'http://localhost:8080/abbis3.2');  // Use port 8080 if Apache uses it
}

// Paths (only define if not already defined)
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__ . '/..');
}
if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', ROOT_PATH . '/uploads');
}
if (!defined('LOG_PATH')) {
    define('LOG_PATH', ROOT_PATH . '/logs');
}

// Create necessary directories
if (!file_exists(UPLOAD_PATH)) {
    mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(LOG_PATH)) {
    mkdir(LOG_PATH, 0755, true);
}

// Security
define('SESSION_LIFETIME', 7200); // 2 hours
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hour

?>

