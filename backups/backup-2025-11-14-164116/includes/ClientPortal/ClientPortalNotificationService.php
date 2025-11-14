<?php
/**
 * Client Portal Notification Service
 * Handles automated email notifications for client portal events
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/email.php';
require_once __DIR__ . '/../../includes/sso.php';

class ClientPortalNotificationService
{
    private $pdo;
    private $emailer;
    private $sso;
    
    public function __construct()
    {
        $this->pdo = getDBConnection();
        $this->emailer = new Email();
        $this->sso = new SSO();
    }
    
    /**
     * Send welcome email when client account is created
     * 
     * @param int $userId User ID
     * @param string $username Username
     * @param string $email Email address
     * @param string $password Plain text password (for initial email only)
     * @param int|null $clientId Client ID if linked
     * @return bool
     */
    public function sendWelcomeEmail($userId, $username, $email, $password = null, $clientId = null)
    {
        try {
            // Get client information if available
            $client = null;
            if ($clientId) {
                $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmt->execute([$clientId]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                // Try to find client by email
                $stmt = $this->pdo->prepare("SELECT * FROM clients WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
            // Get company information
            $companyName = $this->getCompanyName();
            $companyEmail = $this->getCompanyEmail();
            $portalUrl = app_url('client-portal/login.php');
            
            // Generate SSO login URL if user ID is available
            $loginUrl = $portalUrl;
            try {
                $ssoUrl = $this->sso->getClientPortalLoginURL($userId, $username, ROLE_CLIENT);
                if ($ssoUrl) {
                    $loginUrl = $ssoUrl;
                }
            } catch (Exception $e) {
                // Fallback to regular login URL
            }
            
            $clientName = $client['client_name'] ?? $client['contact_person'] ?? 'Valued Client';
            
            $subject = "Welcome to {$companyName} Client Portal";
            
            $emailBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 8px 8px; }
                        .button { display: inline-block; padding: 14px 28px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
                        .credentials { background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0; }
                        .credentials strong { color: #1a202c; }
                        .footer { text-align: center; margin-top: 30px; color: #64748b; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Welcome to {$companyName} Client Portal!</h1>
                        </div>
                        <div class='content'>
                            <h2>Dear {$clientName},</h2>
                            <p>Your client portal account has been created. You can now access your account online to:</p>
                            <ul>
                                <li>📋 View and approve quotes</li>
                                <li>💰 View invoices and payment history</li>
                                <li>💳 Make secure online payments</li>
                                <li>📄 Download quotes and invoices</li>
                                <li>🚧 Track your projects</li>
                            </ul>
                            
                            " . ($password ? "
                            <div class='credentials'>
                                <p><strong>Your Login Credentials:</strong></p>
                                <p><strong>Username:</strong> " . htmlspecialchars($username) . "</p>
                                <p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>
                                <p style='font-size: 12px; color: #ef4444; margin-top: 12px;'><strong>⚠️ Important:</strong> Please change your password after first login for security.</p>
                            </div>
                            " : "
                            <p>You can log in using your existing credentials.</p>
                            ") . "
                            
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$loginUrl}' class='button'>Access Client Portal</a>
                            </div>
                            
                            <p>Or copy this link to your browser:</p>
                            <p style='word-break: break-all; color: #667eea;'><small>{$loginUrl}</small></p>
                            
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                            
                            <p>If you have any questions or need assistance, please contact us at <a href='mailto:{$companyEmail}'>{$companyEmail}</a>.</p>
                            
                            <p>Best regards,<br><strong>{$companyName} Team</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            return $this->emailer->send($email, $subject, $emailBody, ['queue' => true]);
        } catch (Exception $e) {
            error_log('Client portal welcome email error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification when quote is sent
     * 
     * @param int $quoteId Quote ID
     * @return bool
     */
    public function sendQuoteNotification($quoteId)
    {
        try {
            // Get quote details
            $stmt = $this->pdo->prepare("
                SELECT q.*, c.client_name, c.email AS client_email, c.contact_person
                FROM client_quotes q
                JOIN clients c ON c.id = q.client_id
                WHERE q.id = ?
            ");
            $stmt->execute([$quoteId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$quote || empty($quote['client_email'])) {
                return false;
            }
            
            // Get company information
            $companyName = $this->getCompanyName();
            $companyEmail = $this->getCompanyEmail();
            
            // Get client user to generate portal link
            $clientUserId = null;
            $clientUsername = null;
            $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE client_id = ? AND role = 'client' LIMIT 1");
            $stmt->execute([$quote['client_id']]);
            $clientUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($clientUser) {
                $clientUserId = $clientUser['id'];
                $clientUsername = $clientUser['username'];
            }
            
            // Generate portal link
            $quoteUrl = app_url('client-portal/quote-detail.php?id=' . $quoteId);
            if ($clientUserId && $clientUsername) {
                try {
                    $ssoUrl = $this->sso->getClientPortalLoginURL($clientUserId, $clientUsername, ROLE_CLIENT);
                    if ($ssoUrl) {
                        $quoteUrl = $ssoUrl . '&redirect=' . urlencode('quote-detail.php?id=' . $quoteId);
                    }
                } catch (Exception $e) {
                    // Fallback to regular URL
                }
            }
            
            $quoteNumber = $quote['quote_number'] ?? 'Quote #' . $quoteId;
            $totalAmount = number_format((float)($quote['total_amount'] ?? 0), 2);
            $validUntil = $quote['valid_until'] ? date('F d, Y', strtotime($quote['valid_until'])) : 'N/A';
            $clientName = $quote['client_name'] ?? $quote['contact_person'] ?? 'Valued Client';
            
            $subject = "New Quote Available: {$quoteNumber} - {$companyName}";
            
            $emailBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 8px 8px; }
                        .quote-box { background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; padding: 20px; margin: 20px 0; }
                        .quote-box h3 { margin-top: 0; color: #1a202c; }
                        .quote-detail { margin: 10px 0; }
                        .quote-detail strong { color: #1a202c; }
                        .button { display: inline-block; padding: 14px 28px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: 600; }
                        .footer { text-align: center; margin-top: 30px; color: #64748b; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>New Quote Available</h1>
                        </div>
                        <div class='content'>
                            <h2>Dear {$clientName},</h2>
                            <p>A new quote has been prepared for you and is ready for review.</p>
                            
                            <div class='quote-box'>
                                <h3>{$quoteNumber}</h3>
                                <div class='quote-detail'><strong>Total Amount:</strong> GHS {$totalAmount}</div>
                                <div class='quote-detail'><strong>Valid Until:</strong> {$validUntil}</div>
                                <div class='quote-detail'><strong>Status:</strong> " . ucfirst($quote['status'] ?? 'sent') . "</div>
                            </div>
                            
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$quoteUrl}' class='button'>View Quote in Portal</a>
                            </div>
                            
                            <p>You can review the quote, approve it, or download a PDF copy from your client portal.</p>
                            
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                            
                            <p>If you have any questions about this quote, please contact us at <a href='mailto:{$companyEmail}'>{$companyEmail}</a>.</p>
                            
                            <p>Best regards,<br><strong>{$companyName} Team</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            return $this->emailer->send($quote['client_email'], $subject, $emailBody, ['queue' => true]);
        } catch (Exception $e) {
            error_log('Client portal quote notification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification when invoice is issued
     * 
     * @param int $invoiceId Invoice ID
     * @return bool
     */
    public function sendInvoiceNotification($invoiceId)
    {
        try {
            // Get invoice details
            $stmt = $this->pdo->prepare("
                SELECT i.*, c.client_name, c.email AS client_email, c.contact_person
                FROM client_invoices i
                JOIN clients c ON c.id = i.client_id
                WHERE i.id = ?
            ");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$invoice || empty($invoice['client_email'])) {
                return false;
            }
            
            // Get company information
            $companyName = $this->getCompanyName();
            $companyEmail = $this->getCompanyEmail();
            
            // Get client user to generate portal link
            $clientUserId = null;
            $clientUsername = null;
            $stmt = $this->pdo->prepare("SELECT id, username FROM users WHERE client_id = ? AND role = 'client' LIMIT 1");
            $stmt->execute([$invoice['client_id']]);
            $clientUser = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($clientUser) {
                $clientUserId = $clientUser['id'];
                $clientUsername = $clientUser['username'];
            }
            
            // Generate portal links
            $invoiceUrl = app_url('client-portal/invoice-detail.php?id=' . $invoiceId);
            $paymentUrl = app_url('client-portal/payments.php?invoice_id=' . $invoiceId);
            
            if ($clientUserId && $clientUsername) {
                try {
                    $ssoUrl = $this->sso->getClientPortalLoginURL($clientUserId, $clientUsername, ROLE_CLIENT);
                    if ($ssoUrl) {
                        $invoiceUrl = $ssoUrl . '&redirect=' . urlencode('invoice-detail.php?id=' . $invoiceId);
                        $paymentUrl = $ssoUrl . '&redirect=' . urlencode('payments.php?invoice_id=' . $invoiceId);
                    }
                } catch (Exception $e) {
                    // Fallback to regular URLs
                }
            }
            
            $invoiceNumber = $invoice['invoice_number'] ?? 'Invoice #' . $invoiceId;
            $totalAmount = number_format((float)($invoice['total_amount'] ?? 0), 2);
            $balanceDue = number_format((float)($invoice['balance_due'] ?? 0), 2);
            $dueDate = $invoice['due_date'] ? date('F d, Y', strtotime($invoice['due_date'])) : 'N/A';
            $clientName = $invoice['client_name'] ?? $invoice['contact_person'] ?? 'Valued Client';
            $hasBalance = (float)($invoice['balance_due'] ?? 0) > 0.01;
            
            $subject = "Invoice Issued: {$invoiceNumber} - {$companyName}";
            
            $emailBody = "
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset='UTF-8'>
                    <style>
                        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f8fafc; padding: 30px; border-radius: 0 0 8px 8px; }
                        .invoice-box { background: #fff; border: 2px solid #e2e8f0; border-radius: 6px; padding: 20px; margin: 20px 0; }
                        .invoice-box h3 { margin-top: 0; color: #1a202c; }
                        .invoice-detail { margin: 10px 0; }
                        .invoice-detail strong { color: #1a202c; }
                        .balance-due { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0; border-radius: 4px; }
                        .balance-due strong { color: #92400e; font-size: 18px; }
                        .button { display: inline-block; padding: 14px 28px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; font-weight: 600; }
                        .button-pay { background: #10b981; }
                        .footer { text-align: center; margin-top: 30px; color: #64748b; font-size: 14px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Invoice Issued</h1>
                        </div>
                        <div class='content'>
                            <h2>Dear {$clientName},</h2>
                            <p>A new invoice has been issued for your account.</p>
                            
                            <div class='invoice-box'>
                                <h3>{$invoiceNumber}</h3>
                                <div class='invoice-detail'><strong>Total Amount:</strong> GHS {$totalAmount}</div>
                                <div class='invoice-detail'><strong>Balance Due:</strong> GHS {$balanceDue}</div>
                                <div class='invoice-detail'><strong>Due Date:</strong> {$dueDate}</div>
                                <div class='invoice-detail'><strong>Status:</strong> " . ucfirst($invoice['status'] ?? 'sent') . "</div>
                            </div>
                            
                            " . ($hasBalance ? "
                            <div class='balance-due'>
                                <strong>Outstanding Balance: GHS {$balanceDue}</strong>
                                <p style='margin: 8px 0 0 0;'>Payment is due by {$dueDate}. Please make payment to avoid late fees.</p>
                            </div>
                            " : "") . "
                            
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='{$invoiceUrl}' class='button'>View Invoice</a>
                                " . ($hasBalance ? "<a href='{$paymentUrl}' class='button button-pay'>Pay Online Now</a>" : "") . "
                            </div>
                            
                            <p>You can view the invoice details, payment history, and make secure online payments from your client portal.</p>
                            
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                            
                            <p>If you have any questions about this invoice, please contact us at <a href='mailto:{$companyEmail}'>{$companyEmail}</a>.</p>
                            
                            <p>Best regards,<br><strong>{$companyName} Team</strong></p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            return $this->emailer->send($invoice['client_email'], $subject, $emailBody, ['queue' => true]);
        } catch (Exception $e) {
            error_log('Client portal invoice notification error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get company name from system config
     */
    private function getCompanyName()
    {
        try {
            $stmt = $this->pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
            $name = $stmt->fetchColumn();
            return $name ?: APP_NAME;
        } catch (Exception $e) {
            return APP_NAME;
        }
    }
    
    /**
     * Get company email from system config
     */
    private function getCompanyEmail()
    {
        try {
            $stmt = $this->pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_email' LIMIT 1");
            $email = $stmt->fetchColumn();
            return $email ?: 'info@' . strtolower(str_replace(' ', '', APP_NAME)) . '.com';
        } catch (Exception $e) {
            return 'info@abbis.com';
        }
    }
}

