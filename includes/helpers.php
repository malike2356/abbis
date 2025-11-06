<?php
/**
 * Helper Functions
 */

/**
 * Escape output for HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency - always 2 decimal places
 */
function formatCurrency($amount) {
    return 'GHS ' . number_format((float)$amount, 2, '.', ',');
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

/**
 * Format date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Redirect helper
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * JSON response helper
 */
function jsonResponse($data, $statusCode = 200) {
    // Clear ALL output buffers completely - CRITICAL for JSON responses
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Suppress any output that might have been sent
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Send headers before any output
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate, no-store');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        header('Pragma: no-cache');
    }
    
    // Ensure data is an array
    if (!is_array($data)) {
        $data = ['success' => false, 'message' => 'Invalid response data format'];
    }
    
    // Ensure success field exists
    if (!isset($data['success'])) {
        $data['success'] = ($statusCode >= 200 && $statusCode < 300);
    }
    
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    
    if ($json === false) {
        // JSON encoding failed - return error as JSON
        $error = json_last_error_msg();
        $errorData = [
            'success' => false, 
            'message' => 'JSON encoding error: ' . $error,
            'data_type' => gettype($data),
            'json_error_code' => json_last_error()
        ];
        
        // Try to encode error message
        $errorJson = @json_encode($errorData, JSON_UNESCAPED_UNICODE);
        if ($errorJson === false) {
            // Last resort - simple error message
            $errorJson = '{"success":false,"message":"Server error occurred"}';
        }
        
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo $errorJson;
    } else {
        echo $json;
    }
    
    // Flush output and exit immediately
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
    exit;
}

/**
 * Flash message helper
 */
function flash($type, $message) {
    $_SESSION['flash_type'] = $type;
    $_SESSION['flash_message'] = $message;
}

function getFlash() {
    if (isset($_SESSION['flash_message'])) {
        $type = $_SESSION['flash_type'] ?? 'info';
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_type']);
        unset($_SESSION['flash_message']);
        return ['type' => $type, 'message' => $message];
    }
    return null;
}

/**
 * Check if user has role
 */
function hasRole($requiredRole) {
    global $auth;
    return $auth->getUserRole() === $requiredRole || $auth->getUserRole() === ROLE_ADMIN;
}

/**
 * Sanitize input (string)
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return trim($data ?? '');
}

/**
 * Sanitize input array
 */
function sanitizeArray($data) {
    if (is_array($data)) {
        return array_map('sanitizeArray', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 */
function validateRequired($data, $required) {
    $errors = [];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

?>

