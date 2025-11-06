<?php
/**
 * Email Notification System
 * Handles queuing and sending email notifications
 */

require_once __DIR__ . '/../config/database.php';

class EmailNotification {
    private $pdo;
    
    public function __construct() {
        $this->pdo = getDBConnection();
    }
    
    /**
     * Queue an email notification
     */
    public function queue($toEmail, $subject, $body, $type = 'general') {
        try {
            // Ensure email_queue table exists
            $this->ensureTableExists();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue (to_email, subject, body, type, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([$toEmail, $subject, $body, $type]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Email queue error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send a notification immediately (and queue if fails)
     */
    public function send($toEmail, $subject, $body, $type = 'general') {
        // Try to send immediately
        if ($this->sendEmail($toEmail, $subject, $body)) {
            return true;
        }
        
        // If sending fails, queue it
        return $this->queue($toEmail, $subject, $body, $type);
    }
    
    /**
     * Process queued emails
     */
    public function processQueue($limit = 10) {
        try {
            $this->ensureTableExists();
            
            $stmt = $this->pdo->prepare("
                SELECT id, to_email, subject, body, type, attempts
                FROM email_queue
                WHERE status = 'pending' AND attempts < 3
                ORDER BY created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sent = 0;
            $failed = 0;
            
            foreach ($emails as $email) {
                if ($this->sendEmail($email['to_email'], $email['subject'], $email['body'])) {
                    // Mark as sent
                    $updateStmt = $this->pdo->prepare("
                        UPDATE email_queue 
                        SET status = 'sent', sent_at = NOW(), attempts = attempts + 1
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$email['id']]);
                    $sent++;
                } else {
                    // Increment attempts
                    $updateStmt = $this->pdo->prepare("
                        UPDATE email_queue 
                        SET attempts = attempts + 1,
                            status = CASE WHEN attempts + 1 >= 3 THEN 'failed' ELSE 'pending' END
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$email['id']]);
                    $failed++;
                }
            }
            
            return ['sent' => $sent, 'failed' => $failed, 'total' => count($emails)];
        } catch (PDOException $e) {
            error_log("Email queue processing error: " . $e->getMessage());
            return ['sent' => 0, 'failed' => 0, 'total' => 0];
        }
    }
    
    /**
     * Send email using PHP mail() function
     * Can be enhanced to use SMTP, PHPMailer, etc.
     */
    private function sendEmail($toEmail, $subject, $body) {
        // Get email configuration
        $fromEmail = $this->getConfig('email_from', 'noreply@abbis.com');
        $fromName = $this->getConfig('company_name', 'ABBIS');
        
        $headers = [
            'From: ' . $fromName . ' <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        $headersString = implode("\r\n", $headers);
        
        // Try to send email
        try {
            $result = @mail($toEmail, $subject, $body, $headersString);
            return $result;
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get configuration value
     */
    private function getConfig($key, $default = '') {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['config_value'] : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }
    
    /**
     * Ensure email_queue table exists
     */
    private function ensureTableExists() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS `email_queue` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `to_email` varchar(255) NOT NULL,
                    `subject` varchar(255) NOT NULL,
                    `body` text NOT NULL,
                    `type` varchar(50) DEFAULT 'general',
                    `status` enum('pending','sent','failed') DEFAULT 'pending',
                    `attempts` int(11) DEFAULT '0',
                    `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
                    `sent_at` timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `status` (`status`),
                    KEY `created_at` (`created_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            // Table might already exist
        }
    }
    
    /**
     * Send debt recovery reminder
     */
    public function sendDebtReminder($debtRecord) {
        $subject = "Debt Recovery Reminder - " . $debtRecord['debt_code'];
        $body = $this->getDebtReminderTemplate($debtRecord);
        return $this->queue(
            $debtRecord['contact_email'] ?? '',
            $subject,
            $body,
            'debt_reminder'
        );
    }
    
    /**
     * Send maintenance due alert
     */
    public function sendMaintenanceAlert($maintenanceRecord) {
        $subject = "Maintenance Due Alert - " . $maintenanceRecord['maintenance_code'];
        $body = $this->getMaintenanceAlertTemplate($maintenanceRecord);
        return $this->queue(
            $this->getAdminEmail(),
            $subject,
            $body,
            'maintenance_alert'
        );
    }
    
    /**
     * Get debt reminder email template
     */
    private function getDebtReminderTemplate($debt) {
        $dueDate = $debt['due_date'] ? date('F d, Y', strtotime($debt['due_date'])) : 'N/A';
        $amount = number_format($debt['remaining_debt'], 2);
        
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Debt Recovery Reminder</h2>
            <p>Dear " . htmlspecialchars($debt['contact_person'] ?? 'Client') . ",</p>
            <p>This is a reminder regarding outstanding debt:</p>
            <ul>
                <li><strong>Debt Code:</strong> " . htmlspecialchars($debt['debt_code']) . "</li>
                <li><strong>Amount Owing:</strong> GHS " . $amount . "</li>
                <li><strong>Due Date:</strong> " . $dueDate . "</li>
                <li><strong>Status:</strong> " . ucfirst($debt['status']) . "</li>
            </ul>
            <p>Please contact us to arrange payment.</p>
            <p>Best regards,<br>ABBIS Team</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Get maintenance alert email template
     */
    private function getMaintenanceAlertTemplate($maintenance) {
        return "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Maintenance Due Alert</h2>
            <p>This is an alert that maintenance is due:</p>
            <ul>
                <li><strong>Maintenance Code:</strong> " . htmlspecialchars($maintenance['maintenance_code']) . "</li>
                <li><strong>Type:</strong> " . htmlspecialchars($maintenance['type_name'] ?? 'N/A') . "</li>
                <li><strong>Rig:</strong> " . htmlspecialchars($maintenance['rig_name'] ?? 'N/A') . "</li>
                <li><strong>Priority:</strong> " . ucfirst($maintenance['priority']) . "</li>
            </ul>
            <p>Please schedule this maintenance as soon as possible.</p>
        </body>
        </html>
        ";
    }
    
    /**
     * Get admin email from config
     */
    private function getAdminEmail() {
        try {
            $stmt = $this->pdo->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
            $user = $stmt->fetch();
            return $user ? $user['email'] : '';
        } catch (PDOException $e) {
            return '';
        }
    }
}

