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
 * Get currency symbol/code from system configuration
 * Defaults to 'GHS' if not configured
 */
function getCurrency() {
    static $currency = null;
    
    if ($currency === null) {
        try {
            $pdo = getDBConnection();
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'currency' LIMIT 1");
                $stmt->execute();
                $result = $stmt->fetchColumn();
                $currency = $result ? trim($result) : 'GHS';
            } else {
                $currency = 'GHS';
            }
        } catch (Exception $e) {
            error_log("Error getting currency: " . $e->getMessage());
            $currency = 'GHS';
        }
    }
    
    return $currency;
}

/**
 * Format currency - always 2 decimal places
 * Uses configured currency from system_config
 */
function formatCurrency($amount) {
    $currency = getCurrency();
    return $currency . ' ' . number_format((float)$amount, 2, '.', ',');
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
    if (!isset($auth)) {
        return false;
    }

    $userRole = $auth->getUserRole();
    if (!$userRole) {
        return false;
    }

    if ($userRole === ROLE_ADMIN) {
        return true;
    }

    $requiredRoles = is_array($requiredRole) ? $requiredRole : [$requiredRole];
    return in_array($userRole, $requiredRoles, true);
}

/**
 * Permission helper proxy
 */
function userCan(string $permissionKey): bool {
    global $auth;
    if (!isset($auth)) {
        return false;
    }
    return $auth->userHasPermission($permissionKey);
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

/**
 * Generate a sequential staff identifier (e.g. EMP-00001).
 *
 * @throws Exception when a unique identifier cannot be generated
 */
function generateStaffIdentifier(PDO $pdo, string $prefix = 'EMP', int $padLength = 5): string {
    $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix));
    if ($prefix === '') {
        $prefix = 'EMP';
    }

    $padLength = max(3, min(10, (int) $padLength));
    $prefixWithDash = $prefix . '-';
    $numericStart = strlen($prefixWithDash) + 1;

    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING(employee_code, ?, 10) AS UNSIGNED))
        FROM workers
        WHERE employee_code LIKE ?
    ");
    $stmt->execute([$numericStart, $prefixWithDash . '%']);
    $maxSeq = (int) $stmt->fetchColumn();
    $candidateSeq = $maxSeq + 1;

    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM workers WHERE employee_code = ?");
    $attempts = 0;
    while ($attempts < 25) {
        $candidate = sprintf('%s-%0' . $padLength . 'd', $prefix, $candidateSeq);
        $checkStmt->execute([$candidate]);
        if ((int) $checkStmt->fetchColumn() === 0) {
            return $candidate;
        }
        $candidateSeq++;
        $attempts++;
    }

    // Fallback to random tokens if sequential generation fails repeatedly
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM workers WHERE employee_code = ?");
    do {
        try {
            $randomToken = strtoupper(bin2hex(random_bytes(3)));
        } catch (Throwable $e) {
            $randomToken = strtoupper(dechex(mt_rand(0x10000, 0xFFFFF)));
        }
        $candidate = sprintf('%s-%s', $prefix, $randomToken);
        $checkStmt->execute([$candidate]);
    } while ((int) $checkStmt->fetchColumn() !== 0);

    return $candidate;
}

/**
 * Ensure that a worker has a staff identifier assigned.
 *
 * @throws InvalidArgumentException if the worker ID is invalid
 */
function ensureWorkerHasStaffIdentifier(PDO $pdo, int $workerId, string $prefix = 'EMP', int $padLength = 5): string {
    if ($workerId <= 0) {
        throw new InvalidArgumentException('Invalid worker ID supplied when ensuring staff identifier.');
    }

    $stmt = $pdo->prepare("SELECT employee_code FROM workers WHERE id = ? LIMIT 1");
    $stmt->execute([$workerId]);
    $existing = $stmt->fetchColumn();

    if ($existing && trim($existing) !== '') {
        return trim($existing);
    }

    $identifier = generateStaffIdentifier($pdo, $prefix, $padLength);
    $update = $pdo->prepare("
        UPDATE workers
        SET employee_code = ?, updated_at = COALESCE(updated_at, NOW())
        WHERE id = ?
    ");
    $update->execute([$identifier, $workerId]);

    return $identifier;
}

/**
 * Backfill staff identifiers for any workers missing one.
 *
 * @return int Number of workers updated
 */
function ensureAllWorkersHaveStaffIdentifiers(PDO $pdo, string $prefix = 'EMP', int $padLength = 5): int {
    $stmt = $pdo->query("SELECT id FROM workers WHERE employee_code IS NULL OR employee_code = ''");
    $updated = 0;

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        try {
            ensureWorkerHasStaffIdentifier($pdo, (int) $row['id'], $prefix, $padLength);
            $updated++;
        } catch (Throwable $e) {
            error_log('Failed to assign staff identifier to worker ' . (int) $row['id'] . ': ' . $e->getMessage());
        }
    }

    return $updated;
}

?>

