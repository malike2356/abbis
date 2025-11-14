<?php
/**
 * Handle client approvals for quotes
 */
require_once __DIR__ . '/auth-check.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/EmailNotification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('quotes.php');
}

if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['client_quote_error'] = 'Security token expired. Please try again.';
    redirect('quotes.php');
}

$quoteId = (int)($_POST['quote_id'] ?? 0);
$action = $_POST['action'] ?? '';
$note = trim($_POST['client_note'] ?? '');
$signature = trim($_POST['signature_name'] ?? '');

if ($quoteId <= 0 || !in_array($action, ['accept', 'decline'], true)) {
    $_SESSION['client_quote_error'] = 'Invalid request.';
    redirect('quotes.php');
}

try {
    $stmt = $pdo->prepare("SELECT * FROM client_quotes WHERE id = ? AND client_id = ? LIMIT 1");
    $stmt->execute([$quoteId, $clientId]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $quote = null;
}

if (!$quote) {
    $_SESSION['client_quote_error'] = 'Quote not found.';
    redirect('quotes.php');
}

if (($quote['client_response'] ?? 'pending') !== 'pending') {
    $_SESSION['client_quote_error'] = 'You have already responded to this quote.';
    redirect('quote-detail.php?id=' . $quoteId);
}

if ($signature === '') {
    $_SESSION['client_quote_error'] = 'Please type your name to sign.';
    redirect('quote-detail.php?id=' . $quoteId);
}

$responseValue = $action === 'accept' ? 'accepted' : 'declined';
$newStatus = $action === 'accept' ? 'accepted' : 'rejected';

try {
    $pdo->beginTransaction();
    $update = $pdo->prepare("
        UPDATE client_quotes
        SET client_response = ?,
            client_response_note = ?,
            client_response_at = NOW(),
            client_response_ip = ?,
            client_signature_name = ?,
            status = ?
        WHERE id = ? AND client_id = ?
    ");
    $update->execute([
        $responseValue,
        $note ?: null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $signature,
        $newStatus,
        $quoteId,
        $clientId
    ]);

    $pdo->prepare("
        INSERT INTO client_portal_activities (client_id, user_id, activity_type, activity_description, ip_address, user_agent)
        VALUES (?, ?, 'quote_response', ?, ?, ?)
    ")->execute([
        $clientId,
        $userId,
        sprintf('Client %s quote %s', $responseValue, $quote['quote_number'] ?? $quote['id']),
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Client quote approval error: ' . $e->getMessage());
    $_SESSION['client_quote_error'] = 'Unable to record your response. Please try again.';
    redirect('quote-detail.php?id=' . $quoteId);
}

// Notify parties
try {
    $mailer = new EmailNotification();

    $client = $pdo->prepare("SELECT client_name, email FROM clients WHERE id = ? LIMIT 1");
    $client->execute([$clientId]);
    $clientInfo = $client->fetch(PDO::FETCH_ASSOC) ?: ['client_name' => '', 'email' => ''];

    $invoiceLink = app_url('client-portal/quote-detail.php?id=' . $quoteId);
    $quoteNumber = $quote['quote_number'] ?? ('Quote #' . $quoteId);
    $companyName = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_name' LIMIT 1")
        ->fetchColumn() ?: APP_NAME;

    $clientName = $clientInfo['client_name'] ?: 'Client';
    $clientEmail = $clientInfo['email'] ?? '';

    $adminEmail = $pdo->query("SELECT email FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id ASC LIMIT 1")
        ->fetchColumn();

    $statusSentence = $action === 'accept'
        ? 'accepted the quote'
        : 'declined the quote';

    $bodyAdmin = "
        <p>The client has {$statusSentence}: <strong>" . htmlspecialchars($quoteNumber) . "</strong>.</p>
        <ul>
            <li><strong>Client:</strong> " . htmlspecialchars($clientName) . "</li>
            <li><strong>Response:</strong> " . ucfirst($responseValue) . "</li>
            <li><strong>Note:</strong> " . ($note ? htmlspecialchars($note) : '—') . "</li>
            <li><strong>Signature:</strong> " . htmlspecialchars($signature) . "</li>
        </ul>
        <p><a href=\"" . htmlspecialchars(app_url('modules/crm.php?action=client-detail&id=' . $clientId)) . "\">Open client record in ABBIS</a></p>
    ";

    $bodyClient = "
        <p>Hi " . htmlspecialchars($clientName) . ",</p>
        <p>Thanks for your response to <strong>" . htmlspecialchars($quoteNumber) . "</strong>. We have recorded that you have <strong>" . ucfirst($responseValue) . "</strong> the quote.</p>
        <p>You can review the quote anytime from the client portal: <a href=\"" . htmlspecialchars($invoiceLink) . "\">View quote</a></p>
        <p>We will contact you shortly to confirm the next steps.</p>
    ";

    if ($adminEmail) {
        $mailer->queue(
            $adminEmail,
            "[Client Portal] Quote {$responseValue}: {$quoteNumber}",
            wrapEmailTemplate($bodyAdmin, $companyName),
            'client_portal_quote'
        );
    }

    if ($clientEmail) {
        $mailer->queue(
            $clientEmail,
            "We recorded your response for {$quoteNumber}",
            wrapEmailTemplate($bodyClient, $companyName),
            'client_portal_quote'
        );
    }
} catch (Throwable $e) {
    error_log('Quote approval email error: ' . $e->getMessage());
}

$_SESSION['client_quote_notice'] = $action === 'accept'
    ? 'Thanks! We have logged your approval. Our team will reach out to schedule next steps.'
    : 'Your feedback has been received. We will contact you to discuss any changes.';

redirect('quote-detail.php?id=' . $quoteId);

function wrapEmailTemplate(string $content, string $companyName): string {
    return "
    <html>
    <body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#f8fafc;padding:24px;\">
        <div style=\"max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e2e8f0;padding:24px;box-shadow:0 10px 25px rgba(15,23,42,0.08);\">
            <h2 style=\"margin-top:0;color:#1e293b;\">{$companyName}</h2>
            {$content}
            <p style=\"color:#475569;margin-top:32px;\">Kind regards,<br>{$companyName} Team</p>
        </div>
    </body>
    </html>";
}

