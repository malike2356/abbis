<?php
/**
 * Email System for CRM Communications
 * Supports SMTP, PHPMailer, or simple mail()
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class Email {
    private $pdo;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpEncryption;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->pdo = getDBConnection();
        $this->loadConfig();
    }
    
    private function loadConfig() {
        $stmt = $this->pdo->query("
            SELECT config_key, config_value 
            FROM system_config 
            WHERE config_key IN ('smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'smtp_encryption', 'email_from', 'email_from_name')
        ");
        $config = [];
        while ($row = $stmt->fetch()) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        $this->smtpHost = $config['smtp_host'] ?? 'localhost';
        $this->smtpPort = $config['smtp_port'] ?? 587;
        $this->smtpUser = $config['smtp_user'] ?? '';
        $this->smtpPass = $config['smtp_pass'] ?? '';
        $this->smtpEncryption = $config['smtp_encryption'] ?? 'tls';
        $this->fromEmail = $config['email_from'] ?? 'noreply@abbis.africa';
        $this->fromName = $config['email_from_name'] ?? 'ABBIS System';
    }
    
    /**
     * Send email to client
     */
    public function send($to, $subject, $message, $options = []) {
        $attachment = $options['attachment'] ?? null;
        
        // If there's an attachment, use multipart email
        if ($attachment && file_exists($attachment['path'])) {
            return $this->sendWithAttachment($to, $subject, $message, $attachment, $options);
        }
        
        // Regular email without attachment
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . ($options['reply_to'] ?? $this->fromEmail);
        
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }
        
        $fullMessage = $this->wrapEmailTemplate($subject, $message);
        
        // Use mail() for now (can be upgraded to PHPMailer)
        $result = @mail($to, $subject, $fullMessage, implode("\r\n", $headers));
        
        return $result;
    }
    
    /**
     * Send email with attachment
     */
    private function sendWithAttachment($to, $subject, $message, $attachment, $options = []) {
        // Generate a boundary
        $boundary = md5(time());
        $attachmentName = $attachment['name'] ?? basename($attachment['path']);
        $attachmentMime = $attachment['mime'] ?? 'application/octet-stream';
        
        // Read attachment file
        $attachmentContent = file_get_contents($attachment['path']);
        $attachmentContent = chunk_split(base64_encode($attachmentContent));
        
        // Headers
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'From: ' . $this->fromName . ' <' . $this->fromEmail . '>';
        $headers[] = 'Reply-To: ' . ($options['reply_to'] ?? $this->fromEmail);
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        
        if (!empty($options['cc'])) {
            $headers[] = 'Cc: ' . $options['cc'];
        }
        
        if (!empty($options['bcc'])) {
            $headers[] = 'Bcc: ' . $options['bcc'];
        }
        
        // Message body
        $fullMessage = $this->wrapEmailTemplate($subject, $message);
        
        // Build multipart message
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $fullMessage . "\r\n";
        
        // Attachment
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: {$attachmentMime}; name=\"{$attachmentName}\"\r\n";
        $body .= "Content-Disposition: attachment; filename=\"{$attachmentName}\"\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= $attachmentContent . "\r\n";
        $body .= "--{$boundary}--\r\n";
        
        // Send email
        $result = @mail($to, $subject, $body, implode("\r\n", $headers));
        
        return $result;
    }
    
    /**
     * Send email using template
     */
    public function sendTemplate($to, $templateName, $variables = []) {
        $stmt = $this->pdo->prepare("
            SELECT subject, body, variables 
            FROM email_templates 
            WHERE name = ? AND is_active = 1
        ");
        $stmt->execute([$templateName]);
        $template = $stmt->fetch();
        
        if (!$template) {
            return false;
        }
        
        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);
        
        return $this->send($to, $subject, $body);
    }
    
    /**
     * Replace template variables
     */
    private function replaceVariables($text, $variables) {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
            $text = str_replace('{$' . $key . '}', $value, $text);
        }
        return $text;
    }
    
    /**
     * Wrap message in email template
     */
    private function wrapEmailTemplate($subject, $message) {
        $pdo = getDBConnection();
        $stmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name'");
        $companyName = $stmt->fetchColumn() ?: 'ABBIS';
        
        // Check if message is already HTML (contains HTML tags)
        $isHtml = strip_tags($message) !== $message;
        $formattedMessage = $isHtml ? $message : nl2br(htmlspecialchars($message));
        
        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #0ea5e9; color: white; padding: 20px; text-align: center; }
        .content { background: #f9fafb; padding: 30px; }
        .footer { background: #e5e7eb; padding: 15px; text-align: center; font-size: 12px; color: #64748b; }
        .button { display: inline-block; padding: 12px 24px; background: #0ea5e9; color: white; text-decoration: none; border-radius: 6px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>' . htmlspecialchars($companyName) . '</h2>
        </div>
        <div class="content">
            ' . $formattedMessage . '
        </div>
        <div class="footer">
            <p>This email was sent from ' . htmlspecialchars($companyName) . ' system.</p>
            <p>Please do not reply to this email if you did not request it.</p>
        </div>
    </div>
</body>
</html>';
    }
}
