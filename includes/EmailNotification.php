<?php
/**
 * Email Notification System
 * Bridges legacy notification helpers with the new Email service
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/email.php';

class EmailNotification {
    private $pdo;
    private $emailService;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->emailService = new Email();
    }
    
    /**
     * Queue an email notification
     */
    public function queue($toEmail, $subject, $body, $type = 'general') {
        return $this->emailService->queue(
            [$toEmail],
            $subject,
            $body,
            ['type' => $type]
        );
    }
    
    /**
     * Send a notification immediately (and queue if fails)
     */
    public function send($toEmail, $subject, $body, $type = 'general') {
        $result = $this->emailService->send(
            [$toEmail],
            $subject,
            $body,
            ['type' => $type]
        );

        if ($result) {
            return true;
        }

        return $this->queue($toEmail, $subject, $body, $type);
    }
    
    /**
     * Process queued emails
     */
    public function processQueue($limit = 10) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT id, to_email, subject, body, type, attempts, driver, cc, bcc, options_json
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
                if ($this->emailService->deliverQueuedPayload($email)) {
                    // Mark as sent
                    $updateStmt = $this->pdo->prepare("
                        UPDATE email_queue 
                        SET status = 'sent', sent_at = ?, attempts = attempts + 1
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$this->currentTimestamp(), $email['id']]);
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
     * Ensure email_queue table exists (legacy compatibility)
     */
    private function ensureTableExists() {
        // Email service ensures the queue schema; kept for backwards compatibility.
        $this->emailService = $this->emailService ?: new Email();
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

    private function currentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }
}

