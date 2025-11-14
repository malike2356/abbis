<?php
/**
 * Email Service
 * - Configurable drivers (native mail, SMTP, HTTP API)
 * - Template rendering and delivery logging
 * - Queue integration for async processing
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

class Email
{
    private const DEFAULT_DRIVER = 'native';
    private const SUPPORTED_DRIVERS = ['native', 'smtp', 'api', 'log'];

    private $pdo;
    private $config = [];
    private $fromEmail = 'noreply@abbis.africa';
    private $fromName = 'ABBIS System';
    private $databaseDriver = 'mysql';

    public function __construct(array $overrideConfig = [])
    {
        $this->pdo = getDBConnection();
        $this->databaseDriver = defined('DB_CONNECTION') ? DB_CONNECTION : 'mysql';
        $this->loadConfig($overrideConfig);
        $this->ensureInfrastructure();
    }

    /**
     * Send an email immediately or queue it for later processing.
     *
     * @param string|array $to
     * @param string $subject
     * @param string $message
     * @param array $options
     * @return bool|int True on success, queue ID when queued, false on failure
     */
    public function send($to, string $subject, string $message, array $options = [])
    {
        $recipients = $this->normalizeRecipients($to);
        if (empty($recipients)) {
            return false;
        }

        if (!empty($options['queue']) && $options['queue'] === true) {
            return $this->queue($recipients, $subject, $message, $options);
        }

        $payload = $this->buildPayload($recipients, $subject, $message, $options);

        $driver = $payload['driver'];
        $result = false;
        $errorMessage = null;
        $responseMeta = [];

        try {
            if ($driver === 'smtp') {
                $result = $this->sendViaSmtp($payload, $responseMeta);
            } elseif ($driver === 'api') {
                $result = $this->sendViaApi($payload, $responseMeta);
            } elseif ($driver === 'log') {
                $result = $this->sendViaLog($payload, $responseMeta);
            } else {
                $result = $this->sendViaNative($payload, $responseMeta);
                $driver = 'native';
            }
        } catch (Throwable $e) {
            $result = false;
            $errorMessage = $e->getMessage();
        }

        if (!$result && $driver !== 'native') {
            try {
                $payload['driver'] = 'native';
                $driver = 'native';
                $result = $this->sendViaNative($payload, $responseMeta);
            } catch (Throwable $fallbackException) {
                $result = false;
                $errorMessage = $errorMessage
                    ? $errorMessage . ' | Fallback failed: ' . $fallbackException->getMessage()
                    : $fallbackException->getMessage();
            }
        }

        $this->logDelivery($payload, $result ? 'sent' : 'failed', $errorMessage, $responseMeta);

        return $result;
    }

    /**
     * Send an email using a named template.
     *
     * @param string|array $to
     * @param string|int $templateName
     * @param array $variables
     * @param array $options
     * @return bool|int
     */
    public function sendTemplate($to, $templateName, array $variables = [], array $options = [])
    {
        $template = $this->loadTemplate($templateName);
        if (!$template) {
            return false;
        }

        $subject = $this->replaceVariables($template['subject'], $variables);
        $body = $this->replaceVariables($template['body'], $variables);

        $options['template'] = is_numeric($templateName) ? (int)$templateName : (string)$templateName;
        $options['template_label'] = $template['name'];

        return $this->send($to, $subject, $body, $options);
    }

    /**
     * Queue an email for async delivery.
     *
     * @param array $recipients
     * @param string $subject
     * @param string $message
     * @param array $options
     * @return int|false Queue ID on success, false on failure
     */
    public function queue(array $recipients, string $subject, string $message, array $options = [])
    {
        $payload = $this->buildPayload($recipients, $subject, $message, $options);
        $optionsJson = json_encode($payload['options'] ?? [], JSON_UNESCAPED_UNICODE);

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_queue (
                    to_email, subject, body, type, status, driver,
                    cc, bcc, options_json, attempts, created_at
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, 0, ?)
            ");

            $primaryRecipient = $payload['to'][0] ?? '';
            $cc = !empty($payload['cc']) ? implode(',', $payload['cc']) : null;
            $bcc = !empty($payload['bcc']) ? implode(',', $payload['bcc']) : null;
            $createdAt = $this->currentTimestamp();

            $stmt->execute([
                $primaryRecipient,
                $payload['subject'],
                $payload['body'],
                $payload['options']['type'] ?? 'general',
                $payload['driver'],
                $cc,
                $bcc,
                $optionsJson,
                $createdAt,
            ]);

            $queueId = (int)$this->pdo->lastInsertId();
            $this->logDelivery($payload, 'queued', null, ['queue_id' => $queueId]);

            return $queueId;
        } catch (PDOException $e) {
            error_log('Email queue error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process a queued email payload (used by EmailNotification).
     */
    public function deliverQueuedPayload(array $queueRecord): bool
    {
        $options = [];
        if (!empty($queueRecord['options_json'])) {
            $decoded = json_decode($queueRecord['options_json'], true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        $payload = $this->buildPayload(
            [$queueRecord['to_email']],
            $queueRecord['subject'],
            $queueRecord['body'],
            array_merge($options, [
                'driver' => $queueRecord['driver'] ?? $this->config['driver'],
                'cc' => $this->splitList($queueRecord['cc'] ?? ''),
                'bcc' => $this->splitList($queueRecord['bcc'] ?? ''),
            ])
        );

        $responseMeta = [];
        $errorMessage = null;
        $driver = $payload['driver'];
        $result = false;

        try {
            if ($driver === 'smtp') {
                $result = $this->sendViaSmtp($payload, $responseMeta);
            } elseif ($driver === 'api') {
                $result = $this->sendViaApi($payload, $responseMeta);
            } elseif ($driver === 'log') {
                $result = $this->sendViaLog($payload, $responseMeta);
            } else {
                $result = $this->sendViaNative($payload, $responseMeta);
                $driver = 'native';
            }
        } catch (Throwable $e) {
            $result = false;
            $errorMessage = $e->getMessage();
        }

        if (!$result && $driver !== 'native') {
            try {
                $payload['driver'] = 'native';
                $result = $this->sendViaNative($payload, $responseMeta);
                $driver = 'native';
            } catch (Throwable $fallbackException) {
                $result = false;
                $errorMessage = $errorMessage
                    ? $errorMessage . ' | Fallback failed: ' . $fallbackException->getMessage()
                    : $fallbackException->getMessage();
            }
        }

        $this->logDelivery(
            array_merge($payload, ['queue_id' => $queueRecord['id'] ?? null]),
            $result ? 'sent' : 'failed',
            $errorMessage,
            $responseMeta
        );

        return $result;
    }

    /**
     * Load an email template by name or ID.
     */
    private function loadTemplate($templateNameOrId): ?array
    {
        $column = is_numeric($templateNameOrId) ? 'id' : 'name';
        $stmt = $this->pdo->prepare("
            SELECT id, name, subject, body
            FROM email_templates
            WHERE {$column} = ? AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$templateNameOrId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Replace template variables inside text.
     */
    private function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $value = is_scalar($value) ? (string)$value : json_encode($value);
            $text = str_replace('{{' . $key . '}}', $value, $text);
            $text = str_replace('{$' . $key . '}', $value, $text);
        }
        return $text;
    }

    /**
     * Build the payload array with normalized structure.
     */
    private function buildPayload(array $recipients, string $subject, string $message, array $options): array
    {
        $driver = strtolower($options['driver'] ?? $this->config['driver'] ?? self::DEFAULT_DRIVER);
        if (!in_array($driver, self::SUPPORTED_DRIVERS, true)) {
            $driver = self::DEFAULT_DRIVER;
        }

        $wrap = $options['wrap'] ?? true;
        $htmlBody = $wrap ? $this->wrapEmailTemplate($subject, $message) : $message;
        $plainBody = $options['plain_body'] ?? trim(strip_tags($message));
        $cc = $this->normalizeRecipients($options['cc'] ?? []);
        $bcc = $this->normalizeRecipients($options['bcc'] ?? []);

        $attachments = [];
        if (!empty($options['attachments']) && is_array($options['attachments'])) {
            foreach ($options['attachments'] as $attachment) {
                if (!empty($attachment['path']) && file_exists($attachment['path'])) {
                    $attachments[] = [
                        'path' => $attachment['path'],
                        'name' => $attachment['name'] ?? basename($attachment['path']),
                        'mime' => $attachment['mime'] ?? mime_content_type($attachment['path']),
                    ];
                }
            }
        }

        if (!empty($options['attachment']) && is_array($options['attachment'])) {
            $singleAttachment = $options['attachment'];
            if (!empty($singleAttachment['path']) && file_exists($singleAttachment['path'])) {
                $attachments[] = [
                    'path' => $singleAttachment['path'],
                    'name' => $singleAttachment['name'] ?? basename($singleAttachment['path']),
                    'mime' => $singleAttachment['mime'] ?? mime_content_type($singleAttachment['path']),
                ];
            }
        }

        $payloadOptions = $options;
        unset(
            $payloadOptions['driver'],
            $payloadOptions['wrap'],
            $payloadOptions['plain_body'],
            $payloadOptions['attachments'],
            $payloadOptions['attachment'],
            $payloadOptions['queue'],
            $payloadOptions['cc'],
            $payloadOptions['bcc']
        );

        return [
            'driver' => $driver,
            'to' => $recipients,
            'cc' => $cc,
            'bcc' => $bcc,
            'subject' => $subject,
            'body' => $htmlBody,
            'plain_body' => $plainBody,
            'from_email' => $options['from_email'] ?? $this->fromEmail,
            'from_name' => $options['from_name'] ?? $this->fromName,
            'reply_to' => $options['reply_to'] ?? $this->config['reply_to'] ?? $this->fromEmail,
            'attachments' => $attachments,
            'options' => $payloadOptions,
        ];
    }

    /**
     * Send via PHP native mail().
     */
    private function sendViaNative(array $payload, array &$responseMeta): bool
    {
        $headers = $this->buildHeaders($payload, false);
        $to = implode(', ', $payload['to']);
        $headersString = implode("\r\n", $headers);

        if (!empty($payload['attachments'])) {
            return $this->sendMultipartMail($payload, $headersString, $responseMeta);
        }

        $result = @mail($to, $payload['subject'], $payload['body'], $headersString);
        $responseMeta['transport'] = 'mail()';

        return $result;
    }

    /**
     * Send via direct SMTP socket.
     */
    private function sendViaSmtp(array $payload, array &$responseMeta): bool
    {
        $host = $this->config['smtp_host'] ?? 'localhost';
        $port = (int)($this->config['smtp_port'] ?? 587);
        $encryption = strtolower($this->config['smtp_encryption'] ?? 'tls');
        $username = $this->config['smtp_user'] ?? '';
        $password = $this->config['smtp_pass'] ?? '';
        $timeout = (int)($this->config['smtp_timeout'] ?? 30);

        $remoteHost = $host;
        if ($encryption === 'ssl') {
            $remoteHost = 'ssl://' . $host;
        }

        $socket = @stream_socket_client(
            $remoteHost . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new RuntimeException("SMTP connection failed: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);
        $this->smtpRead($socket);

        $hostname = gethostname() ?: 'localhost';
        $this->smtpCommand($socket, "EHLO {$hostname}");

        if ($encryption === 'tls') {
            $this->smtpCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new RuntimeException('Failed to enable TLS encryption for SMTP');
            }
            $this->smtpCommand($socket, "EHLO {$hostname}");
        }

        if (!empty($username)) {
            $this->smtpCommand($socket, 'AUTH LOGIN');
            $this->smtpCommand($socket, base64_encode($username));
            $this->smtpCommand($socket, base64_encode($password));
        }

        $fromEmail = $payload['from_email'];
        $this->smtpCommand($socket, "MAIL FROM:<{$fromEmail}>");
        foreach ($payload['to'] as $recipient) {
            $this->smtpCommand($socket, "RCPT TO:<{$recipient}>");
        }
        foreach ($payload['cc'] as $recipient) {
            $this->smtpCommand($socket, "RCPT TO:<{$recipient}>");
        }
        foreach ($payload['bcc'] as $recipient) {
            $this->smtpCommand($socket, "RCPT TO:<{$recipient}>");
        }

        $this->smtpCommand($socket, 'DATA');

        $headers = $this->buildHeaders($payload, true);
        $fullMessage = implode("\r\n", $headers) . "\r\n\r\n";
        $fullMessage .= $this->buildMessageBody($payload);
        $fullMessage .= "\r\n.";

        fwrite($socket, $fullMessage . "\r\n");
        $this->smtpRead($socket);

        $this->smtpCommand($socket, 'QUIT');
        fclose($socket);

        $responseMeta['transport'] = 'smtp';
        $responseMeta['host'] = $host;
        $responseMeta['port'] = $port;

        return true;
    }

    /**
     * Send via configured HTTP API endpoint.
     */
    private function sendViaApi(array $payload, array &$responseMeta): bool
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required for API email delivery');
        }

        $endpoint = $this->config['email_api_endpoint'] ?? '';
        $method = strtoupper($this->config['email_api_method'] ?? 'POST');
        $apiKey = $this->config['email_api_key'] ?? '';
        $headersConfig = $this->config['email_api_headers'] ?? '';
        $payloadTemplate = $this->config['email_api_payload_template'] ?? '';

        if (empty($endpoint)) {
            throw new RuntimeException('Email API endpoint is not configured');
        }

        $headers = [];
        if (!empty($headersConfig)) {
            foreach (explode("\n", $headersConfig) as $headerLine) {
                $headerLine = trim($headerLine);
                if ($headerLine !== '') {
                    $headers[] = $headerLine;
                }
            }
        }

        if (!empty($apiKey) && !$this->headerContains($headers, 'Authorization')) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        $body = $this->buildApiPayload($payload, $payloadTemplate);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, (int)($this->config['email_api_timeout'] ?? 30));
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array_merge(
            ['Content-Type: application/json'],
            $headers
        ));
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        $responseMeta['transport'] = 'api';
        $responseMeta['endpoint'] = $endpoint;
        $responseMeta['status_code'] = $statusCode;
        $responseMeta['response_body'] = $response;

        if ($response === false) {
            throw new RuntimeException('Email API request failed: ' . $curlError);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('Email API returned HTTP ' . $statusCode . ' Response: ' . $response);
        }

        return true;
    }

    private function sendViaLog(array $payload, array &$responseMeta): bool
    {
        $responseMeta['transport'] = 'log';
        $responseMeta['note'] = 'Email logged locally without external delivery.';
        return true;
    }

    /**
     * Create a standardized log entry for each delivery attempt.
     */
    private function logDelivery(array $payload, string $status, ?string $errorMessage, array $responseMeta = []): void
    {
        if (($this->config['log_emails'] ?? '1') === '0') {
            return;
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO email_logs (
                    driver, subject, to_email, cc, bcc, status,
                    template_reference, template_name, error_message,
                    meta_json, payload_hash, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $templateReference = $payload['options']['template'] ?? null;
            $templateLabel = $payload['options']['template_label'] ?? null;

            $meta = array_merge($responseMeta, [
                'from' => [
                    'email' => $payload['from_email'],
                    'name' => $payload['from_name'],
                ],
                'options' => $payload['options'],
                'queue_id' => $payload['queue_id'] ?? null,
            ]);

            $stmt->execute([
                $payload['driver'],
                $payload['subject'],
                implode(',', $payload['to']),
                !empty($payload['cc']) ? implode(',', $payload['cc']) : null,
                !empty($payload['bcc']) ? implode(',', $payload['bcc']) : null,
                $status,
                $templateReference,
                $templateLabel,
                $errorMessage,
                json_encode($meta, JSON_UNESCAPED_UNICODE),
                sha1($payload['subject'] . $payload['body'] . implode(',', $payload['to'])),
                $this->currentTimestamp(),
            ]);
        } catch (PDOException $e) {
            error_log('Failed to log email delivery: ' . $e->getMessage());
        }
    }

    /**
     * Ensure supporting tables/columns exist.
     */
    private function ensureInfrastructure(): void
    {
        if ($this->isSqlite()) {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS email_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    driver TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    to_email TEXT NOT NULL,
                    cc TEXT DEFAULT NULL,
                    bcc TEXT DEFAULT NULL,
                    status TEXT DEFAULT 'sent',
                    template_reference TEXT DEFAULT NULL,
                    template_name TEXT DEFAULT NULL,
                    error_message TEXT DEFAULT NULL,
                    meta_json TEXT DEFAULT NULL,
                    payload_hash TEXT DEFAULT NULL,
                    created_at TEXT DEFAULT (datetime('now'))
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs (status)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_created_at ON email_logs (created_at)");

            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS email_queue (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    to_email TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    body TEXT NOT NULL,
                    type TEXT DEFAULT 'general',
                    status TEXT DEFAULT 'pending',
                    driver TEXT DEFAULT 'native',
                    cc TEXT DEFAULT NULL,
                    bcc TEXT DEFAULT NULL,
                    options_json TEXT DEFAULT NULL,
                    attempts INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT (datetime('now')),
                    sent_at TEXT DEFAULT NULL
                )
            ");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue (status)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_queue_created_at ON email_queue (created_at)");
            return;
        }

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                driver VARCHAR(20) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                to_email TEXT NOT NULL,
                cc TEXT DEFAULT NULL,
                bcc TEXT DEFAULT NULL,
                status ENUM('sent','failed','queued') NOT NULL DEFAULT 'sent',
                template_reference VARCHAR(120) DEFAULT NULL,
                template_name VARCHAR(120) DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                meta_json LONGTEXT DEFAULT NULL,
                payload_hash CHAR(40) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS email_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body LONGTEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'general',
                status ENUM('pending','sent','failed') DEFAULT 'pending',
                driver VARCHAR(20) DEFAULT 'native',
                cc TEXT DEFAULT NULL,
                bcc TEXT DEFAULT NULL,
                options_json LONGTEXT DEFAULT NULL,
                attempts INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                sent_at TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_status (status),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Load configuration from system_config and cms_settings.
     */
    private function loadConfig(array $overrideConfig = []): void
    {
        $defaults = [
            'driver' => self::DEFAULT_DRIVER,
            'smtp_host' => 'localhost',
            'smtp_port' => 587,
            'smtp_user' => '',
            'smtp_pass' => '',
            'smtp_encryption' => 'tls',
            'smtp_timeout' => 30,
            'reply_to' => null,
            'email_api_endpoint' => '',
            'email_api_method' => 'POST',
            'email_api_key' => '',
            'email_api_headers' => '',
            'email_api_payload_template' => '',
            'email_api_timeout' => 30,
            'log_emails' => '1',
            'email_from' => 'noreply@abbis.africa',
            'email_from_name' => 'ABBIS System',
        ];

        try {
            $systemConfig = $this->loadKeyValueConfig('system_config', 'config_key', 'config_value', array_keys($defaults));
        } catch (Throwable $e) {
            $systemConfig = [];
        }

        try {
            $cmsSettings = $this->loadKeyValueConfig('cms_settings', 'setting_key', 'setting_value', array_keys($defaults));
        } catch (Throwable $e) {
            $cmsSettings = [];
        }

        $config = array_merge($defaults, $systemConfig, $cmsSettings, $overrideConfig);

        $envMailDriver = getenv('MAIL_DRIVER') ?: getenv('EMAIL_DRIVER');
        if ($envMailDriver) {
            $config['driver'] = strtolower(trim($envMailDriver));
        }
        if (!in_array($config['driver'], self::SUPPORTED_DRIVERS, true)) {
            $config['driver'] = self::DEFAULT_DRIVER;
        }

        $this->config = $config;
        $this->fromEmail = $config['email_from'] ?: $defaults['email_from'];
        $this->fromName = $config['email_from_name'] ?: $defaults['email_from_name'];
    }

    private function loadKeyValueConfig(string $table, string $keyColumn, string $valueColumn, array $keys): array
    {
        if (empty($keys)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $this->pdo->prepare("
            SELECT {$keyColumn} AS config_key, {$valueColumn} AS config_value
            FROM {$table}
            WHERE {$keyColumn} IN ({$placeholders})
        ");
        $stmt->execute($keys);

        $config = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $config[$row['config_key']] = $row['config_value'];
        }

        return $config;
    }

    /**
     * Normalize recipients to array of email strings.
     *
     * @param string|array $recipients
     */
    private function normalizeRecipients($recipients): array
    {
        if (empty($recipients)) {
            return [];
        }

        if (is_string($recipients)) {
            $recipients = preg_split('/[,;]/', $recipients);
        }

        $normalized = [];
        foreach ((array)$recipients as $recipient) {
            $recipient = trim((string)$recipient);
            if ($recipient !== '') {
                $normalized[] = $recipient;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function splitList(string $list): array
    {
        return $this->normalizeRecipients($list);
    }

    private function buildHeaders(array $payload, bool $forSmtp): array
    {
        $headers = [];
        $headers[] = 'From: ' . $this->formatAddress($payload['from_email'], $payload['from_name']);
        $headers[] = 'Reply-To: ' . $payload['reply_to'];
        $headers[] = 'MIME-Version: 1.0';

        if (!empty($payload['cc'])) {
            $headers[] = 'Cc: ' . implode(', ', $payload['cc']);
        }

        if (!empty($payload['bcc']) && !$forSmtp) {
            $headers[] = 'Bcc: ' . implode(', ', $payload['bcc']);
        }

        if (!empty($payload['attachments'])) {
            $boundary = $this->generateBoundary();
            $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
        }

        $headers[] = 'X-Mailer: ABBIS Email Service';

        return $headers;
    }

    private function buildMessageBody(array $payload): string
    {
        if (empty($payload['attachments'])) {
            return $payload['body'];
        }

        $boundary = $this->generateBoundary();
        $body = "This is a multi-part message in MIME format.\r\n\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $payload['body'] . "\r\n\r\n";

        foreach ($payload['attachments'] as $attachment) {
            $content = chunk_split(base64_encode(file_get_contents($attachment['path'])));
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $content . "\r\n\r\n";
        }

        $body .= "--{$boundary}--";

        return $body;
    }

    private function sendMultipartMail(array $payload, string $headersString, array &$responseMeta): bool
    {
        $boundary = $this->extractBoundaryFromHeaders($headersString);
        if (!$boundary) {
            $boundary = $this->generateBoundary();
            $headersString .= "\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"";
        }

        $body = "This is a multi-part message in MIME format.\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $body .= $payload['body'] . "\r\n\r\n";

        foreach ($payload['attachments'] as $attachment) {
            $content = chunk_split(base64_encode(file_get_contents($attachment['path'])));
            $body .= "--{$boundary}\r\n";
            $body .= 'Content-Type: ' . $attachment['mime'] . '; name="' . $attachment['name'] . '"' . "\r\n";
            $body .= 'Content-Disposition: attachment; filename="' . $attachment['name'] . '"' . "\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $content . "\r\n\r\n";
        }

        $body .= "--{$boundary}--";

        $to = implode(', ', $payload['to']);
        $result = @mail($to, $payload['subject'], $body, $headersString);
        $responseMeta['transport'] = 'mail(multipart)';

        return $result;
    }

    private function extractBoundaryFromHeaders(string $headers): ?string
    {
        if (preg_match('/boundary="([^"]+)"/i', $headers, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function generateBoundary(): string
    {
        return '==Multipart_Boundary_x' . md5((string)microtime(true)) . 'x';
    }

    private function formatAddress(string $email, ?string $name = null): string
    {
        if ($name) {
            return sprintf('%s <%s>', $name, $email);
        }
        return $email;
    }

    private function smtpCommand($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
        $this->smtpRead($socket);
    }

    private function smtpRead($socket): string
    {
        $data = '';
        while ($str = fgets($socket, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) === ' ') {
                break;
            }
        }
        $code = substr($data, 0, 3);
        if ((int)$code >= 400) {
            throw new RuntimeException('SMTP Error: ' . trim($data));
        }
        return $data;
    }

    private function headerContains(array $headers, string $needle): bool
    {
        foreach ($headers as $header) {
            if (stripos($header, $needle . ':') === 0) {
                return true;
            }
        }
        return false;
    }

    private function buildApiPayload(array $payload, string $template = ''): array
    {
        $base = [
            'from' => [
                'email' => $payload['from_email'],
                'name' => $payload['from_name'],
            ],
            'to' => $payload['to'],
            'subject' => $payload['subject'],
            'html' => $payload['body'],
            'text' => $payload['plain_body'],
        ];

        if (!empty($payload['cc'])) {
            $base['cc'] = $payload['cc'];
        }
        if (!empty($payload['bcc'])) {
            $base['bcc'] = $payload['bcc'];
        }

        if (empty($template)) {
            return $base;
        }

        $prepared = $this->replaceVariables($template, [
            'from_email' => $payload['from_email'],
            'from_name' => $payload['from_name'],
            'to' => json_encode($payload['to']),
            'subject' => $payload['subject'],
            'html' => $payload['body'],
            'text' => $payload['plain_body'],
            'cc' => json_encode($payload['cc']),
            'bcc' => json_encode($payload['bcc']),
        ]);

        $decoded = json_decode($prepared, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return $base;
    }

    private function isSqlite(): bool
    {
        return strtolower($this->databaseDriver) === 'sqlite';
    }

    private function currentTimestamp(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function wrapEmailTemplate(string $subject, string $message): string
    {
        $stmt = $this->pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
        $companyName = $stmt->fetchColumn() ?: 'ABBIS';

        $isHtml = strip_tags($message) !== $message;
        $formattedMessage = $isHtml ? $message : nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

        return '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #1f2937; background: #f8fafc; padding: 0; margin: 0; }
        .container { max-width: 640px; margin: 0 auto; padding: 24px; background: #ffffff; }
        .header { background: linear-gradient(135deg, #0ea5e9, #0369a1); color: #fff; padding: 24px; text-align: center; border-radius: 12px 12px 0 0; }
        .content { padding: 32px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px; background: #ffffff; }
        .footer { text-align: center; color: #94a3b8; font-size: 12px; margin-top: 24px; }
        @media (max-width: 600px) { .content { padding: 20px; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2 style="margin:0; font-size:24px;">' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '</h2>
        </div>
        <div class="content">
            ' . $formattedMessage . '
        </div>
        <div class="footer">
            <p>This email was sent from ' . htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') . '.</p>
        </div>
    </div>
</body>
</html>';
    }
}
