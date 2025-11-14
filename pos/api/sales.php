<?php
/**
 * POS Sales API - Comprehensive Error Handling
 * This endpoint processes POS sales with full error recovery
 */

// Set error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display errors, log them instead
ini_set('log_errors', '1');

// Start output buffering to catch any unexpected output
ob_start();

try {
    require_once __DIR__ . '/../../config/app.php';
    require_once __DIR__ . '/../../config/security.php';
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/helpers.php';
    require_once __DIR__ . '/../../includes/pos/PosRepository.php';
    require_once __DIR__ . '/../../includes/pos/PosValidator.php';
} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    header('Content-Type: application/json');
    error_log('[POS Sale] Bootstrap error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'System initialization failed. Please contact support.',
        'error_code' => 'BOOTSTRAP_ERROR'
    ]);
    exit;
}

// Clear any output from includes
ob_end_clean();

// Set JSON header
header('Content-Type: application/json');

// Authentication
try {
    $auth->requireAuth();
    if (!$auth->userHasPermission('pos.sales.process') && !$auth->userHasPermission('pos.access')) {
        $auth->requirePermission('pos.sales.process');
    }
} catch (Throwable $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Authentication failed: ' . $e->getMessage(),
        'error_code' => 'AUTH_ERROR'
    ]);
    exit;
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use POST.',
        'error_code' => 'METHOD_ERROR'
    ]);
    exit;
}

// Read and parse payload
$rawInput = file_get_contents('php://input');
$payload = null;

if (empty($rawInput)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Empty request body. Please provide sale data.',
        'error_code' => 'EMPTY_PAYLOAD'
    ]);
    exit;
}

$payload = json_decode($rawInput, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON: ' . json_last_error_msg(),
        'error_code' => 'JSON_ERROR'
    ]);
    exit;
}

if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payload must be a JSON object.',
        'error_code' => 'INVALID_PAYLOAD'
    ]);
    exit;
}

// Main processing
try {
    // Ensure cashier_id is set from session
    $cashierId = (int)($_SESSION['user_id'] ?? $payload['cashier_id'] ?? 0);
    if ($cashierId <= 0) {
        throw new InvalidArgumentException('Unable to determine the cashier account for this sale. Please re-login.');
    }
    $payload['cashier_id'] = $cashierId;

    // Validate payload
    $validated = PosValidator::validateSalePayload($payload);
    
    // Double-check critical fields
    if (empty($validated['cashier_id']) || $validated['cashier_id'] <= 0) {
        throw new InvalidArgumentException('Invalid cashier ID. Please re-login.');
    }
    
    if (empty($validated['store_id']) || $validated['store_id'] <= 0) {
        throw new InvalidArgumentException('Invalid store ID. Please select a valid store.');
    }
    
    if (empty($validated['items']) || !is_array($validated['items']) || count($validated['items']) === 0) {
        throw new InvalidArgumentException('Sale must contain at least one item.');
    }
    
    if (empty($validated['payments']) || !is_array($validated['payments']) || count($validated['payments']) === 0) {
        throw new InvalidArgumentException('Sale must have at least one payment method.');
    }

    // Create repository and process sale
    $repo = new PosRepository();
    $result = $repo->createSale($validated);

    // Success response
    echo json_encode([
        'success' => true,
        'data' => $result
    ]);
    
} catch (InvalidArgumentException $e) {
    // Validation errors - 422 Unprocessable Entity
    http_response_code(422);
    $message = $e->getMessage();
    error_log('[POS Sale] Validation error: ' . $message);
    error_log('[POS Sale] Payload keys: ' . implode(', ', array_keys($payload ?? [])));
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error_code' => 'VALIDATION_ERROR'
    ]);
    
} catch (PDOException $e) {
    // Database errors - 500 Internal Server Error
    http_response_code(500);
    $errorMsg = $e->getMessage();
    $errorCode = $e->getCode();
    
    error_log('[POS Sale] Database error: ' . $errorMsg);
    error_log('[POS Sale] SQL State: ' . $errorCode);
    error_log('[POS Sale] Payload: ' . json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    
    // User-friendly messages for common database errors
    $userMessage = 'Database error occurred while processing the sale.';
    
    if (strpos($errorMsg, 'foreign key constraint') !== false) {
        $userMessage = 'Invalid reference (store, product, or cashier). Please verify your selections.';
    } elseif (strpos($errorMsg, 'Duplicate entry') !== false || strpos($errorMsg, 'Duplicate') !== false) {
        $userMessage = 'A sale with this number already exists. Please try again.';
    } elseif (strpos($errorMsg, "doesn't exist") !== false || strpos($errorMsg, 'Table') !== false) {
        $userMessage = 'Database table missing. Please run database migrations.';
    } elseif (strpos($errorMsg, 'Column') !== false && strpos($errorMsg, "doesn't exist") !== false) {
        $userMessage = 'Database schema outdated. Please run database migrations.';
    }
    
    echo json_encode([
        'success' => false,
        'message' => $userMessage,
        'error_code' => 'DATABASE_ERROR',
        'debug_info' => (defined('APP_ENV') && APP_ENV === 'development') ? $errorMsg : null
    ]);
    
} catch (Throwable $e) {
    // Any other errors - 500 Internal Server Error
    http_response_code(500);
    $message = $e->getMessage();
    $file = $e->getFile();
    $line = $e->getLine();
    
    error_log('[POS Sale] Fatal error: ' . $message);
    error_log('[POS Sale] File: ' . $file . ' Line: ' . $line);
    error_log('[POS Sale] Trace: ' . $e->getTraceAsString());
    
    // Write detailed debug log
    $debugLine = sprintf(
        "[%s] POS Sale fatal: %s\nFile: %s:%d\nPayload: %s\nTrace: %s\n\n",
        date('c'),
        $message,
        $file,
        $line,
        json_encode($payload ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $e->getTraceAsString()
    );
    
    $logDir = __DIR__ . '/../../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    @file_put_contents($logDir . '/pos-sales-debug.log', $debugLine, FILE_APPEND);
    
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        @file_put_contents(LOG_PATH . '/pos-sales-errors.log', $debugLine, FILE_APPEND);
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Sale processing failed. Please try again or contact support if the problem persists.',
        'error_code' => 'FATAL_ERROR',
        'debug_info' => (defined('APP_ENV') && APP_ENV === 'development') ? $message : null
    ]);
}
