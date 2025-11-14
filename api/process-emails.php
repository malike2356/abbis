<?php
/**
 * Process Email Queue
 * Run this via cron: *\/5 * * * * php /path/to/api/process-emails.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/EmailNotification.php';

try {
    $emailSystem = new EmailNotification();
    $result = $emailSystem->processQueue(20);
    
    if (php_sapi_name() === 'cli') {
        echo "Processed {$result['sent']} emails, {$result['failed']} failed\n";
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'result' => $result]);
    }
    exit(0);
} catch (Exception $e) {
    if (php_sapi_name() === 'cli') {
        echo "Error processing emails: " . $e->getMessage() . "\n";
    } else {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    error_log("process-emails.php error: " . $e->getMessage());
    exit(1);
}
?>

