<?php
/**
 * CMS Client Synchronization Service
 * Automatically creates ABBIS clients and user accounts from CMS customers
 */

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/email.php';

class CmsClientSync {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Sync CMS customer to ABBIS client and create portal access
     * Called when CMS order is placed or quote is submitted
     */
    public function syncCmsCustomerToAbbis($customerData) {
        $name = $customerData['name'] ?? '';
        $email = $customerData['email'] ?? '';
        $phone = $customerData['phone'] ?? '';
        $address = $customerData['address'] ?? '';
        $source = $customerData['source'] ?? 'CMS Order';
        
        if (empty($name) || empty($email)) {
            return ['success' => false, 'message' => 'Name and email are required'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Check if client already exists
            $stmt = $this->pdo->prepare("SELECT id FROM clients WHERE email = ? OR client_name = ? LIMIT 1");
            $stmt->execute([$email, $name]);
            $existingClient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $clientId = null;
            $isNewClient = false;
            
            if ($existingClient) {
                // Update existing client
                $clientId = $existingClient['id'];
                $updateStmt = $this->pdo->prepare("
                    UPDATE clients 
                    SET contact_number = COALESCE(?, contact_number),
                        address = COALESCE(?, address),
                        source = COALESCE(?, source),
                        status = CASE WHEN status = 'lead' THEN 'customer' ELSE status END,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([$phone ?: null, $address ?: null, $source, $clientId]);
            } else {
                // Create new client
                $insertStmt = $this->pdo->prepare("
                    INSERT INTO clients (
                        client_name, email, contact_number, address, source, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'customer', NOW())
                ");
                $insertStmt->execute([$name, $email, $phone ?: null, $address ?: null, $source]);
                $clientId = $this->pdo->lastInsertId();
                $isNewClient = true;
            }
            
            // Check if user account exists
            $userStmt = $this->pdo->prepare("SELECT id, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            $userStmt->execute([$email, $email]);
            $existingUser = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            $userId = null;
            $isNewUser = false;
            $passwordGenerated = null;
            
            if ($existingUser) {
                // Update existing user to link to client
                $userId = $existingUser['id'];
                $updateUserStmt = $this->pdo->prepare("
                    UPDATE users 
                    SET client_id = ?,
                        role = CASE 
                            WHEN role = ? THEN role 
                            ELSE ?
                        END,
                        email = COALESCE(?, email)
                    WHERE id = ?
                ");
                $updateUserStmt->execute([
                    $clientId,
                    ROLE_CLIENT,
                    ROLE_CLIENT,
                    $email,
                    $userId
                ]);
            } else {
                // Create new user account with ROLE_CLIENT
                $passwordGenerated = $this->generateTemporaryPassword();
                $passwordHash = password_hash($passwordGenerated, PASSWORD_DEFAULT);
                
                $insertUserStmt = $this->pdo->prepare("
                    INSERT INTO users (
                        username, email, password_hash, role, client_id, is_active, created_at
                    ) VALUES (?, ?, ?, ?, ?, 1, NOW())
                ");
                $insertUserStmt->execute([
                    $email,
                    $email,
                    $passwordHash,
                    ROLE_CLIENT,
                    $clientId
                ]);
                $userId = $this->pdo->lastInsertId();
                $isNewUser = true;
            }
            
            $this->pdo->commit();
            
            // Send welcome email if new user was created
            if ($isNewUser && $passwordGenerated) {
                $this->sendWelcomeEmail($email, $name, $passwordGenerated);
            }
            
            return [
                'success' => true,
                'client_id' => $clientId,
                'user_id' => $userId,
                'is_new_client' => $isNewClient,
                'is_new_user' => $isNewUser,
                'password_generated' => $isNewUser ? $passwordGenerated : null
            ];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log('CMS Client Sync Error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to sync client: ' . $e->getMessage()];
        }
    }
    
    /**
     * Generate temporary password for new client
     */
    private function generateTemporaryPassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }
    
    /**
     * Send welcome email with portal access credentials
     */
    private function sendWelcomeEmail($email, $name, $password) {
        try {
            $portalUrl = client_portal_url('login.php');
            $companyName = getSystemConfig('company_name', 'ABBIS');
            
            $subject = "Welcome to {$companyName} Client Portal";
            $message = "
                <html>
                <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                        <h2 style='color: #667eea;'>Welcome to {$companyName} Client Portal!</h2>
                        
                        <p>Dear {$name},</p>
                        
                        <p>Your client portal account has been created. You can now access your account to:</p>
                        <ul>
                            <li>View and approve quotes</li>
                            <li>Track invoices and payments</li>
                            <li>View project status</li>
                            <li>Download documents</li>
                        </ul>
                        
                        <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <p><strong>Portal URL:</strong> <a href='{$portalUrl}'>{$portalUrl}</a></p>
                            <p><strong>Username:</strong> {$email}</p>
                            <p><strong>Temporary Password:</strong> {$password}</p>
                        </div>
                        
                        <p style='color: #d32f2f;'><strong>Important:</strong> Please change your password after first login.</p>
                        
                        <p>If you have any questions, please contact us.</p>
                        
                        <p>Best regards,<br>{$companyName} Team</p>
                    </div>
                </body>
                </html>
            ";
            
            // Use existing email system
            if (function_exists('sendEmail')) {
                sendEmail($email, $subject, $message);
            } else {
                // Fallback to basic mail
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: {$companyName} <noreply@" . parse_url(APP_URL, PHP_URL_HOST) . ">\r\n";
                mail($email, $subject, $message, $headers);
            }
            
        } catch (Exception $e) {
            error_log('Welcome email error: ' . $e->getMessage());
            // Don't fail the sync if email fails
        }
    }
    
    /**
     * Sync from CMS quote request
     */
    public function syncFromQuoteRequest($quoteData) {
        return $this->syncCmsCustomerToAbbis([
            'name' => $quoteData['name'] ?? '',
            'email' => $quoteData['email'] ?? '',
            'phone' => $quoteData['phone'] ?? '',
            'address' => $quoteData['address'] ?? $quoteData['location'] ?? '',
            'source' => 'CMS Quote Request'
        ]);
    }
    
    /**
     * Sync from CMS order
     */
    public function syncFromOrder($orderData) {
        return $this->syncCmsCustomerToAbbis([
            'name' => $orderData['customer_name'] ?? '',
            'email' => $orderData['customer_email'] ?? '',
            'phone' => $orderData['customer_phone'] ?? '',
            'address' => $orderData['customer_address'] ?? '',
            'source' => 'CMS Order'
        ]);
    }
}

