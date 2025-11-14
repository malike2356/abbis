<?php
/**
 * Client Portal Hooks
 * Helper functions to trigger notifications when quotes/invoices are created or updated
 */

require_once __DIR__ . '/ClientPortalNotificationService.php';

/**
 * Send notification when quote status changes to 'sent'
 * Call this after updating quote status in database
 * 
 * @param int $quoteId Quote ID
 * @return bool
 */
function notifyQuoteSent($quoteId)
{
    try {
        $service = new ClientPortalNotificationService();
        return $service->sendQuoteNotification($quoteId);
    } catch (Exception $e) {
        error_log('Quote notification hook error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Send notification when invoice status changes to 'sent'
 * Call this after updating invoice status in database
 * 
 * @param int $invoiceId Invoice ID
 * @return bool
 */
function notifyInvoiceSent($invoiceId)
{
    try {
        $service = new ClientPortalNotificationService();
        return $service->sendInvoiceNotification($invoiceId);
    } catch (Exception $e) {
        error_log('Invoice notification hook error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if quote status changed to 'sent' and send notification
 * Call this after updating client_quotes table
 * 
 * @param int $quoteId Quote ID
 * @param string|null $oldStatus Previous status (optional)
 * @param string|null $newStatus New status (optional, will query if not provided)
 * @return bool
 */
function checkAndNotifyQuoteStatus($quoteId, $oldStatus = null, $newStatus = null)
{
    try {
        $pdo = getDBConnection();
        
        // If new status not provided, query it
        if ($newStatus === null) {
            $stmt = $pdo->prepare("SELECT status FROM client_quotes WHERE id = ?");
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$quote) {
                return false;
            }
            $newStatus = $quote['status'];
        }
        
        // Only send notification if status changed to 'sent'
        if ($newStatus === 'sent' && $oldStatus !== 'sent') {
            return notifyQuoteSent($quoteId);
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Check quote status error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if invoice status changed to 'sent' and send notification
 * Call this after updating client_invoices table
 * 
 * @param int $invoiceId Invoice ID
 * @param string|null $oldStatus Previous status (optional)
 * @param string|null $newStatus New status (optional, will query if not provided)
 * @return bool
 */
function checkAndNotifyInvoiceStatus($invoiceId, $oldStatus = null, $newStatus = null)
{
    try {
        $pdo = getDBConnection();
        
        // If new status not provided, query it
        if ($newStatus === null) {
            $stmt = $pdo->prepare("SELECT status FROM client_invoices WHERE id = ?");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$invoice) {
                return false;
            }
            $newStatus = $invoice['status'];
        }
        
        // Only send notification if status changed to 'sent'
        if ($newStatus === 'sent' && $oldStatus !== 'sent') {
            return notifyInvoiceSent($invoiceId);
        }
        
        return false;
    } catch (Exception $e) {
        error_log('Check invoice status error: ' . $e->getMessage());
        return false;
    }
}

