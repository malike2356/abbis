<?php
/**
 * Process Email Queue
 * Run this via cron or scheduled task to send queued emails
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/EmailNotification.php';

// Allow CLI execution
if (php_sapi_name() !== 'cli') {
    // Web execution - require auth
    require_once __DIR__ . '/../config/security.php';
    require_once __DIR__ . '/../includes/auth.php';
    $auth->requireAuth();
    $auth->requireRole([ROLE_ADMIN]);
}

$notification = new EmailNotification();
$result = $notification->processQueue(50); // Process up to 50 emails

if (php_sapi_name() === 'cli') {
    echo "Email Queue Processing:\n";
    echo "  Sent: {$result['sent']}\n";
    echo "  Failed: {$result['failed']}\n";
    echo "  Total: {$result['total']}\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($result);
}

