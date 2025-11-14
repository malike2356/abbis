<?php
/**
 * Client Portal Notification API
 * Handles sending notifications when quotes/invoices are created or updated
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalNotificationService.php';

header('Content-Type: application/json');

$pdo = getDBConnection();
$notificationService = new ClientPortalNotificationService();

// Handle different notification types
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'quote_sent':
        // Triggered when quote status changes to 'sent'
        $quoteId = intval($_GET['quote_id'] ?? $_POST['quote_id'] ?? 0);
        if ($quoteId > 0) {
            $result = $notificationService->sendQuoteNotification($quoteId);
            jsonResponse([
                'success' => $result,
                'message' => $result ? 'Quote notification sent successfully' : 'Failed to send quote notification'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Quote ID required'], 400);
        }
        break;
        
    case 'invoice_sent':
        // Triggered when invoice status changes to 'sent'
        $invoiceId = intval($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
        if ($invoiceId > 0) {
            $result = $notificationService->sendInvoiceNotification($invoiceId);
            jsonResponse([
                'success' => $result,
                'message' => $result ? 'Invoice notification sent successfully' : 'Failed to send invoice notification'
            ]);
        } else {
            jsonResponse(['success' => false, 'message' => 'Invoice ID required'], 400);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
}

