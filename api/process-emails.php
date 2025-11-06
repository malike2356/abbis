<?php
/**
 * Process Email Queue
 * Run this via cron: */5 * * * * php /path/to/api/process-emails.php
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/email.php';

$emailSystem = new EmailNotification();
$sent = $emailSystem->processQueue(20);

echo "Processed $sent emails\n";
exit(0);
?>

