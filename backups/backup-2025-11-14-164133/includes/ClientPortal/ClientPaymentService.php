<?php
/**
 * Client Portal Payment Service
 */

require_once __DIR__ . '/../AccountingAutoTracker.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../EmailNotification.php';

class ClientPaymentService
{
    private PDO $pdo;
    private AccountingAutoTracker $accounting;
    private EmailNotification $mailer;

    /** @var array<string,string> */
    private array $providerMethodMap = [
        'paystack' => 'card_online',
        'flutterwave' => 'card_online',
        'mobile_money' => 'mobile_money',
        'momo' => 'mobile_money',
        'bank_transfer' => 'bank_transfer',
        'bank' => 'bank_transfer',
        'cash' => 'cash',
    ];

    /** @var string[] */
    private array $gatewayProviders = ['paystack', 'flutterwave'];

    /** @var string[] */
    private array $supportedProviders = ['paystack', 'flutterwave', 'mobile_money', 'bank_transfer', 'cash'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->accounting = new AccountingAutoTracker($pdo);
        $this->mailer = new EmailNotification();
    }

    /**
     * Initiate a payment against an invoice
     *
     * @return array{status:string,message?:string,redirect?:string,payment_id?:int,provider?:string}
     */
    public function initiatePayment(int $clientId, int $invoiceId, float $amount, int $paymentMethodId, string $notes = '', ?int $userId = null, string $portalBaseUrl = ''): array
    {
        $invoice = $this->getInvoice($clientId, $invoiceId);
        if (!$invoice) {
            return ['status' => 'error', 'message' => 'Invoice not found.'];
        }

        $balanceDue = max(0.0, floatval($invoice['balance_due'] ?? 0));
        if ($balanceDue <= 0) {
            return ['status' => 'error', 'message' => 'This invoice is already settled.'];
        }

        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Payment amount must be greater than zero.'];
        }

        if ($amount > $balanceDue) {
            $amount = $balanceDue;
        }

        $paymentMethod = $this->getPaymentMethod($paymentMethodId);
        if (!$paymentMethod) {
            return ['status' => 'error', 'message' => 'Invalid payment method selected.'];
        }

        $providerKey = strtolower($paymentMethod['provider'] ?? '');
        if (!in_array($providerKey, $this->supportedProviders, true)) {
            return ['status' => 'error', 'message' => 'This payment method is not available in the client portal.'];
        }

        $paymentMethodType = $this->providerMethodMap[$providerKey] ?? 'other';
        if ($paymentMethodType === 'other') {
            return ['status' => 'error', 'message' => 'Unsupported payment method selected.'];
        }

        $client = $this->getClient($clientId);
        if (!$client) {
            return ['status' => 'error', 'message' => 'Client record not found.'];
        }

        $isGateway = in_array($providerKey, $this->gatewayProviders, true);
        $paymentStatus = $isGateway ? 'processing' : 'pending';
        $reference = $this->generateReference($invoice['invoice_number'] ?? (string)$invoiceId);

        $this->pdo->beginTransaction();
        try {
            $insert = $this->pdo->prepare('
                INSERT INTO client_payments (
                    payment_reference, client_id, invoice_id, amount, payment_method_id,
                    payment_method, payment_status, gateway_provider, notes, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $insert->execute([
                $reference,
                $clientId,
                $invoiceId,
                $amount,
                $paymentMethodId,
                $paymentMethodType,
                $paymentStatus,
                $providerKey,
                $notes ?: null,
                $userId,
            ]);

            $paymentId = (int)$this->pdo->lastInsertId();

            $this->logActivity($clientId, $userId, 'payment_initiated', sprintf('Payment %s initiated for invoice %s (%.2f)', $reference, $invoice['invoice_number'], $amount));

            if (!$isGateway) {
                $this->pdo->commit();
                $this->notifyPaymentInitiated($invoice, $client, $amount, $this->formatPaymentMethodLabel($paymentMethod, $paymentMethodType), false, $reference);
                return [
                    'status' => 'pending',
                    'message' => 'Payment logged. Our team will confirm once it is verified.',
                    'payment_id' => $paymentId,
                ];
            }

            // Gateway payment
            $gatewayData = $this->buildGatewayPayload(
                $providerKey,
                $paymentMethod,
                $invoice,
                $client,
                $amount,
                $reference,
                $paymentId,
                $portalBaseUrl
            );

            if (isset($gatewayData['error'])) {
                $this->pdo->rollBack();
                return ['status' => 'error', 'message' => $gatewayData['error']];
            }

            $updateGateway = $this->pdo->prepare('
                UPDATE client_payments
                SET gateway_transaction_id = ?, gateway_response = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $updateGateway->execute([
                $gatewayData['reference'] ?? $gatewayData['tx_ref'] ?? null,
                json_encode($gatewayData),
                $paymentId,
            ]);

            $this->pdo->commit();
            $this->notifyPaymentInitiated($invoice, $client, $amount, $this->formatPaymentMethodLabel($paymentMethod, $paymentMethodType), true, $reference);

            return [
                'status' => 'gateway',
                'provider' => $providerKey,
                'payment_id' => $paymentId,
                'redirect' => app_url('client-portal/payment-gateway.php?payment=' . $paymentId),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('Client payment initiation failed: ' . $e->getMessage());
            return ['status' => 'error', 'message' => 'Unable to initiate payment.'];
        }
    }

    /**
     * Complete a gateway payment after verification
     */
    public function completeGatewayPayment(int $paymentId, string $provider, string $transactionReference, array $verificationData, ?int $userId = null): bool
    {
        $provider = strtolower($provider);
        $payment = $this->getPaymentById($paymentId, true);
        if (!$payment) {
            throw new RuntimeException('Payment record not found.');
        }

        if ($payment['payment_status'] === 'completed') {
            return true; // Already processed
        }

        $invoice = $this->getInvoice((int)$payment['client_id'], (int)$payment['invoice_id']);
        if (!$invoice) {
            throw new RuntimeException('Invoice not found for payment.');
        }

        $client = $this->getClient((int)$payment['client_id']) ?? [
            'client_name' => $payment['client_name'] ?? '',
            'email' => $payment['client_email'] ?? ''
        ];

        $amountPaid = floatval($verificationData['amount'] ?? $payment['amount']);
        if ($provider === 'paystack') {
            // Paystack returns amount in major currency units via verification data
            $amountPaid = floatval($verificationData['amount'] ?? 0.0);
        }

        if ($amountPaid <= 0) {
            $amountPaid = floatval($payment['amount']);
        }

        $balanceDue = max(0.0, floatval($invoice['balance_due']));
        $amountToApply = min($amountPaid, $balanceDue);

        $this->pdo->beginTransaction();
        try {
            $updatePayment = $this->pdo->prepare('
                UPDATE client_payments
                SET payment_status = ?, gateway_transaction_id = ?, gateway_response = ?, payment_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ');
            $updatePayment->execute([
                'completed',
                $transactionReference,
                json_encode($verificationData),
                $paymentId,
            ]);

            $this->applyPaymentToInvoice($invoice, $amountToApply, $userId);

            $this->logActivity((int)$payment['client_id'], $userId, 'payment_completed', sprintf('Payment %s confirmed (%.2f)', $payment['payment_reference'], $amountToApply));

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // Reload invoice to reflect updated balances
        $invoiceUpdated = $this->getInvoice((int)$payment['client_id'], (int)$payment['invoice_id']);

        // Record accounting entry
        try {
            $this->accounting->trackClientInvoicePayment($paymentId, [
                'amount' => $amountToApply,
                'payment_method' => $payment['payment_method'],
                'payment_date' => date('Y-m-d'),
                'invoice_number' => $invoiceUpdated['invoice_number'] ?? $invoice['invoice_number'] ?? '',
                'created_by' => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('Failed to track client payment in accounting: ' . $e->getMessage());
        }

        $this->notifyPaymentCompleted($invoiceUpdated ?? $invoice, $client, $amountToApply, $payment['payment_method'], $transactionReference);

        return true;
    }

    /**
     * Mark payment as failed
     */
    public function markGatewayPaymentFailed(int $paymentId, string $reason): void
    {
        $stmt = $this->pdo->prepare('UPDATE client_payments SET payment_status=?, notes=CONCAT(COALESCE(notes, ""), ?) WHERE id=?');
        $stmt->execute(['failed', "\nGateway failure: " . $reason, $paymentId]);
    }

    private function getInvoice(int $clientId, int $invoiceId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM client_invoices WHERE id = ? AND client_id = ? LIMIT 1');
        $stmt->execute([$invoiceId, $clientId]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        return $invoice ?: null;
    }

    private function getClient(int $clientId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        return $client ?: null;
    }

    private function getPaymentMethod(int $methodId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, provider, config FROM cms_payment_methods WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$methodId]);
        $method = $stmt->fetch(PDO::FETCH_ASSOC);
        return $method ?: null;
    }

    private function getPaymentById(int $paymentId, bool $forUpdate = false): ?array
    {
        $sql = 'SELECT * FROM client_payments WHERE id = ? LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        return $payment ?: null;
    }

    private function generateReference(string $invoiceNumber): string
    {
        $suffix = strtoupper(bin2hex(random_bytes(3)));
        $cleanInvoice = preg_replace('/[^A-Za-z0-9]/', '', $invoiceNumber);
        return 'CPAY-' . $cleanInvoice . '-' . date('YmdHis') . '-' . $suffix;
    }

    private function buildGatewayPayload(string $provider, array $paymentMethod, array $invoice, array $client, float $amount, string $reference, int $paymentId, string $portalBaseUrl): array
    {
        require_once __DIR__ . '/../../cms/public/payment-gateways.php';

        $customerEmail = $client['email'] ?? '';
        if (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            $sessionEmail = $_SESSION['username'] ?? '';
            if (filter_var($sessionEmail, FILTER_VALIDATE_EMAIL)) {
                $customerEmail = $sessionEmail;
            }
        }

        $order = [
            'order_number' => $invoice['invoice_number'] ?? ('INV-' . $invoice['id']),
            'id' => $paymentId,
            'total_amount' => $amount,
            'customer_email' => $customerEmail,
            'customer_name' => $client['client_name'] ?? '',
            'customer_phone' => $client['contact_number'] ?? '',
        ];

        if (empty($order['customer_email'])) {
            return ['error' => 'Client email address is required for online payment.'];
        }

        $baseUrl = rtrim($portalBaseUrl ?: app_url('client-portal'), '/');

        if ($provider === 'paystack') {
            $payload = initPaystackPayment($order, $paymentMethod, $baseUrl, $this->pdo);
            if (isset($payload['error'])) {
                return $payload;
            }
            $payload['callback'] = $baseUrl . '/payment-callback.php?gateway=paystack&payment=' . $paymentId;
            $payload['reference'] = $payload['reference'] ?? $reference;
            return $payload;
        }

        if ($provider === 'flutterwave') {
            $payload = initFlutterwavePayment($order, $paymentMethod, $baseUrl, $this->pdo);
            if (isset($payload['error'])) {
                return $payload;
            }
            $payload['callback'] = $baseUrl . '/payment-callback.php?gateway=flutterwave&payment=' . $paymentId;
            return $payload;
        }

        return ['error' => 'Unsupported gateway provider.'];
    }

    private function applyPaymentToInvoice(array $invoice, float $amount, ?int $userId = null): void
    {
        $invoiceId = (int)$invoice['id'];
        $amountPaid = max(0.0, floatval($invoice['amount_paid']));
        $balanceDue = max(0.0, floatval($invoice['balance_due']));

        $amountToApply = min($amount, $balanceDue);
        $newAmountPaid = $amountPaid + $amountToApply;
        $newBalance = max(0.0, ($balanceDue - $amountToApply));

        $newStatus = $invoice['status'];
        if ($newBalance <= 0.01) {
            $newStatus = 'paid';
        } elseif (in_array($invoice['status'], ['draft', 'sent'], true)) {
            $newStatus = 'partial';
        } elseif ($invoice['status'] === 'overdue' && $newBalance <= 0) {
            $newStatus = 'paid';
        } else {
            $newStatus = 'partial';
        }

        $update = $this->pdo->prepare('
            UPDATE client_invoices
            SET amount_paid = ?, balance_due = ?, status = ?, paid_date = CASE WHEN ? <= 0 THEN NOW() ELSE paid_date END,
                updated_at = NOW()
            WHERE id = ?
        ');
        $update->execute([
            $newAmountPaid,
            $newBalance,
            $newStatus,
            $newBalance,
            $invoiceId,
        ]);
    }

    private function logActivity(int $clientId, ?int $userId, string $type, string $description): void
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO client_portal_activities (client_id, user_id, activity_type, activity_description, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $clientId,
                $userId,
                $type,
                $description,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (PDOException $e) {
            error_log('Client portal activity log failed: ' . $e->getMessage());
        }
    }

    private function notifyPaymentInitiated(array $invoice, array $client, float $amount, string $methodLabel, bool $isGateway, string $reference): void
    {
        $adminEmail = $this->getAdminEmail();
        $companyName = $this->getCompanyName();
        $clientEmail = $client['email'] ?? '';
        $invoiceNumber = $invoice['invoice_number'] ?? ('Invoice #' . $invoice['id']);
        $amountFormatted = number_format($amount, 2);

        if ($adminEmail) {
            $subject = "[Client Portal] Payment Initiated for {$invoiceNumber}";
            $body = $this->renderEmailTemplate("
                <p>A client has initiated a payment from the portal.</p>
                <ul>
                    <li><strong>Client:</strong> " . htmlspecialchars($client['client_name'] ?? '') . "</li>
                    <li><strong>Invoice:</strong> " . htmlspecialchars($invoiceNumber) . "</li>
                    <li><strong>Amount:</strong> GHS {$amountFormatted}</li>
                    <li><strong>Method:</strong> " . htmlspecialchars($methodLabel) . "</li>
                    <li><strong>Reference:</strong> " . htmlspecialchars($reference) . "</li>
                    <li><strong>Status:</strong> " . ($isGateway ? 'Processing via gateway' : 'Pending verification') . "</li>
                </ul>
                <p>Please review and reconcile if required.</p>
            ", $companyName);
            $this->mailer->queue($adminEmail, $subject, $body, 'client_portal_payment');
        }

        if ($clientEmail) {
            $subject = "We received your payment request for {$invoiceNumber}";
            $body = $this->renderEmailTemplate("
                <p>Hi " . htmlspecialchars($client['client_name'] ?? 'Client') . ",</p>
                <p>Thanks for submitting a payment for <strong>" . htmlspecialchars($invoiceNumber) . "</strong>.</p>
                <ul>
                    <li><strong>Amount:</strong> GHS {$amountFormatted}</li>
                    <li><strong>Method:</strong> " . htmlspecialchars($methodLabel) . "</li>
                    <li><strong>Reference:</strong> " . htmlspecialchars($reference) . "</li>
                </ul>
                <p>" . ($isGateway ? "You will receive another email once the transaction is confirmed." : "Our finance team will confirm the payment and update your invoice shortly.") . "</p>
            ", $companyName);
            $this->mailer->queue($clientEmail, $subject, $body, 'client_portal_payment');
        }
    }

    private function notifyPaymentCompleted(array $invoice, array $client, float $amount, string $methodKey, string $transactionReference): void
    {
        $adminEmail = $this->getAdminEmail();
        $companyName = $this->getCompanyName();
        $clientEmail = $client['email'] ?? '';
        $invoiceNumber = $invoice['invoice_number'] ?? ('Invoice #' . $invoice['id']);
        $amountFormatted = number_format($amount, 2);
        $methodLabel = ucfirst(str_replace('_', ' ', $methodKey));

        if ($adminEmail) {
            $subject = "[Client Portal] Payment Completed for {$invoiceNumber}";
            $body = $this->renderEmailTemplate("
                <p>The following payment has been confirmed:</p>
                <ul>
                    <li><strong>Client:</strong> " . htmlspecialchars($client['client_name'] ?? '') . "</li>
                    <li><strong>Invoice:</strong> " . htmlspecialchars($invoiceNumber) . "</li>
                    <li><strong>Amount:</strong> GHS {$amountFormatted}</li>
                    <li><strong>Method:</strong> " . htmlspecialchars($methodLabel) . "</li>
                    <li><strong>Transaction Reference:</strong> " . htmlspecialchars($transactionReference) . "</li>
                </ul>
                <p>The journal entry has been generated automatically.</p>
            ", $companyName);
            $this->mailer->queue($adminEmail, $subject, $body, 'client_portal_payment');
        }

        if ($clientEmail) {
            $subject = "Payment receipt for {$invoiceNumber}";
            $body = $this->renderEmailTemplate("
                <p>Hi " . htmlspecialchars($client['client_name'] ?? 'Client') . ",</p>
                <p>We’ve recorded your payment for <strong>" . htmlspecialchars($invoiceNumber) . "</strong>.</p>
                <ul>
                    <li><strong>Amount:</strong> GHS {$amountFormatted}</li>
                    <li><strong>Method:</strong> " . htmlspecialchars($methodLabel) . "</li>
                    <li><strong>Transaction Reference:</strong> " . htmlspecialchars($transactionReference) . "</li>
                </ul>
                <p>Your invoice balance has been updated in the client portal. Thank you!</p>
            ", $companyName);
            $this->mailer->queue($clientEmail, $subject, $body, 'client_portal_payment');
        }
    }

    private function formatPaymentMethodLabel(array $paymentMethod, string $methodType): string
    {
        $label = $paymentMethod['name'] ?? '';
        if (!$label) {
            $label = ucfirst(str_replace('_', ' ', $methodType));
        }

        $provider = $paymentMethod['provider'] ?? '';
        if ($provider && !stripos($label, $provider)) {
            $label .= ' (' . ucfirst($provider) . ')';
        }

        return $label;
    }

    private function getAdminEmail(): string
    {
        try {
            $stmt = $this->pdo->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row['email'] ?? '';
        } catch (PDOException $e) {
            return '';
        }
    }

    private function getCompanyName(): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1");
            $stmt->execute();
            $value = $stmt->fetch(PDO::FETCH_ASSOC);
            return $value['config_value'] ?? APP_NAME;
        } catch (PDOException $e) {
            return APP_NAME;
        }
    }

    private function renderEmailTemplate(string $content, string $companyName): string
    {
        return "
        <html>
        <body style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background:#f8fafc; padding:24px;\">
            <div style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 10px 25px rgba(15,23,42,0.08);\">
                <h2 style=\"margin-top:0;color:#1e293b;\">{$companyName}</h2>
                {$content}
                <p style=\"color:#475569;margin-top:32px;\">Kind regards,<br>{$companyName} Team</p>
            </div>
        </body>
        </html>";
    }
}
