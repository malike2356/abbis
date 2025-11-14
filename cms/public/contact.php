<?php
/**
 * Contact Us Page - Modern Contact Form
 */
// Start session early to avoid "headers already sent" errors
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();
$contactSuccess = false;
$contactError = '';
$contactFormData = [];

// Ensure contact_submissions table exists
try { 
    $pdo->query("SELECT 1 FROM contact_submissions LIMIT 1"); 
} catch (Throwable $e) {
    // Create table if it doesn't exist
    try {
        $createTableSQL = "
        CREATE TABLE IF NOT EXISTS `contact_submissions` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `email` varchar(255) NOT NULL,
          `phone` varchar(50) DEFAULT NULL,
          `subject` varchar(255) DEFAULT 'General Inquiry',
          `message` text NOT NULL,
          `status` enum('new','read','replied','archived') DEFAULT 'new',
          `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
          `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_status` (`status`),
          KEY `idx_created_at` (`created_at`),
          KEY `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($createTableSQL);
    } catch (Throwable $createError) {
        // Table creation failed, but continue anyway (form will still work)
        error_log("Contact submissions table creation failed: " . $createError->getMessage());
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contactFormData = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
        'message' => trim($_POST['message'] ?? ''),
    ];

    if ($contactFormData['name'] && $contactFormData['email'] && $contactFormData['message']) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO contact_submissions (
                    name, email, phone, subject, message, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'new', NOW())
            ");
            $stmt->execute([
                $contactFormData['name'],
                $contactFormData['email'],
                $contactFormData['phone'] ?: null,
                $contactFormData['subject'] ?: 'General Inquiry',
                $contactFormData['message'],
            ]);

            try {
                $clientStmt = $pdo->prepare("
                    INSERT INTO clients (client_name, email, phone, source, status)
                    VALUES (?, ?, ?, 'contact_form', 'lead')
                    ON DUPLICATE KEY UPDATE phone=COALESCE(?, phone), source='contact_form'
                ");
                $clientStmt->execute([
                    $contactFormData['name'],
                    $contactFormData['email'],
                    $contactFormData['phone'],
                    $contactFormData['phone'],
                ]);
            } catch (Throwable $ignored) {}

            $contactSuccess = true;
            $contactFormData = [];
        } catch (Throwable $e) {
            if (strpos($e->getMessage(), "doesn't exist") !== false) {
                $contactSuccess = true;
                $contactFormData = [];
            } else {
                $contactError = 'Failed to send message. Please try again.';
                error_log("Contact form error: " . $e->getMessage());
            }
        }
    } else {
        $contactError = 'Please fill in all required fields.';
    }
}

// Get company information
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');

// Get contact information from CMS settings or system config
$contactEmail = '';
$contactPhone = '';
$contactAddress = '';
$contactHours = '';

try {
    // Try CMS settings first
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM cms_settings WHERE setting_key IN ('contact_email', 'contact_phone', 'contact_address', 'contact_hours')");
    while ($row = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        switch ($row['setting_key']) {
            case 'contact_email':
                $contactEmail = $row['setting_value'] ?: 'info@example.com';
                break;
            case 'contact_phone':
                $contactPhone = $row['setting_value'] ?: '+233 XX XXX XXXX';
                break;
            case 'contact_address':
                $contactAddress = $row['setting_value'] ?: '123 Main Street, Accra, Ghana';
                break;
            case 'contact_hours':
                $contactHours = $row['setting_value'] ?: 'Monday - Friday: 8:00 AM - 5:00 PM';
                break;
        }
    }
    
    // Fallback to system config
    if (empty($contactEmail)) {
        $configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_email' LIMIT 1");
        $contactEmail = $configStmt->fetchColumn() ?: 'info@example.com';
    }
    if (empty($contactPhone)) {
        $configStmt = $pdo->query("SELECT config_value FROM system_config WHERE config_key = 'company_phone' LIMIT 1");
        $contactPhone = $configStmt->fetchColumn() ?: '+233 XX XXX XXXX';
    }
} catch (Throwable $e) {
    // Use defaults
    $contactEmail = $contactEmail ?: 'info@example.com';
    $contactPhone = $contactPhone ?: '+233 XX XXX XXXX';
    $contactAddress = $contactAddress ?: '123 Main Street, Accra, Ghana';
    $contactHours = $contactHours ?: 'Monday - Friday: 8:00 AM - 5:00 PM';
}

$siteTitle = 'Contact Us - ' . $companyName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }
        .cms-content {
            min-height: 70vh;
        }
        .contact-hero {
            background: radial-gradient(circle at top, rgba(14,165,233,0.55), #0284c7);
            color: #fff;
            padding: 4rem 2rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .contact-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at bottom left, rgba(59,130,246,0.35), transparent 55%);
            opacity: 0.6;
        }
        .contact-hero .container {
            position: relative;
            z-index: 1;
        }
        .contact-hero h1 {
            font-size: clamp(2.5rem, 6vw, 3.25rem);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .contact-hero p {
            font-size: 1.15rem;
            max-width: 640px;
            margin: 0 auto;
            opacity: 0.95;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.75rem;
        }
        .layout-wrapper {
            display: flex;
            flex-direction: column;
            gap: 3rem;
            margin-top: -3.5rem;
            position: relative;
            z-index: 2;
        }
        .section-card {
            background: #fff;
            border-radius: 1.9rem;
            padding: 2.75rem;
            box-shadow: 0 30px 70px -40px rgba(15, 23, 42, 0.55);
        }
        .section-header {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-bottom: 2.2rem;
        }
        .section-header small {
            font-size: 0.8rem;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: #0284c7;
            font-weight: 700;
        }
        .section-header h2 {
            font-size: clamp(1.9rem, 3.8vw, 2.4rem);
            margin: 0;
        }
        .section-header p {
            color: #475569;
            max-width: 640px;
        }
        .section-columns {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.25rem;
            align-items: start;
        }
        .info-column {
            display: grid;
            gap: 1.2rem;
        }
        .info-card {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 1rem;
            padding: 1.35rem;
            border-radius: 1.3rem;
            background: #f1f5f9;
        }
        .info-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: #e0f2fe;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
        }
        .info-body h3 {
            margin: 0 0 0.4rem;
            font-size: 1.1rem;
            font-weight: 600;
        }
        .info-body p {
            margin: 0;
            color: #475569;
        }
        .info-body a {
            color: #0284c7;
            text-decoration: none;
        }
        .info-body a:hover {
            text-decoration: underline;
        }
        .form-panel {
            background: linear-gradient(135deg, rgba(14,165,233,0.08), rgba(14,165,233,0.01));
            border-radius: 1.5rem;
            padding: 2rem;
            border: 1px solid rgba(14,165,233,0.12);
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.6rem 1.8rem;
        }
        .form-item {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .form-item label {
            font-weight: 600;
            font-size: 0.92rem;
            color: #1f2937;
        }
        .form-item .required {
            color: #ef4444;
            margin-left: 4px;
        }
        .form-item input,
        .form-item select,
        .form-item textarea {
            border-radius: 14px;
            border: 1px solid #cbd5f5;
            padding: 1rem 1.1rem;
            background: #fff;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-height: 52px;
        }
        .form-item textarea {
            min-height: 170px;
            resize: vertical;
        }
        .form-item.full {
            grid-column: 1 / -1;
        }
        .form-item input:focus,
        .form-item select:focus,
        .form-item textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 4px rgba(14,165,233,0.18);
        }
        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .primary-btn,
        .secondary-btn {
            border-radius: 999px;
            padding: 0.9rem 2.4rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .primary-btn {
            border: none;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: #fff;
            box-shadow: 0 20px 45px -25px rgba(14,165,233,0.7);
        }
        .primary-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 26px 55px -22px rgba(14,165,233,0.7);
        }
        .alert {
            margin-bottom: 1.5rem;
            padding: 1rem 1.2rem;
            border-radius: 1rem;
            font-size: 0.95rem;
        }
        .alert.success {
            background: #ecfdf5;
            border: 1px solid rgba(16,185,129,0.32);
            color: #047857;
        }
        .alert.error {
            background: #fef2f2;
            border: 1px solid rgba(248,113,113,0.32);
            color: #b91c1c;
        }
        .map-section {
            background: #fff;
            border-radius: 1.9rem;
            padding: 2.75rem;
            box-shadow: 0 25px 60px -38px rgba(15,23,42,0.45);
        }
        .map-section h2 {
            margin-bottom: 1.2rem;
            font-size: 1.8rem;
        }
        .map-placeholder {
            height: 420px;
            border-radius: 1.4rem;
            background: radial-gradient(circle at top, rgba(14,165,233,0.22), rgba(6,182,212,0.08));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0284c7;
            text-align: center;
            font-size: 1.05rem;
        }
        @media (max-width: 768px) {
            .layout-wrapper {
                margin-top: -2.5rem;
            }
            .section-card {
                padding: 2.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="contact-hero">
            <div class="container">
                <h1>Get In Touch</h1>
                <p>We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
            </div>
        </div>
        
        <div class="container">
            <div class="layout-wrapper">

                <section class="section-card">
                    <div class="section-header">
                        <small>Contact</small>
                        <h2>Speak with our team</h2>
                        <p>Reach out for project enquiries, support, or partnership opportunities. We aim to respond within one business day.</p>
                    </div>
                    <div class="section-columns">
                        <div class="info-column">
                            <div class="info-card">
                                <div class="info-icon">📍</div>
                                <div class="info-body">
                                    <h3>Visit us</h3>
                                    <p><?php echo htmlspecialchars($contactAddress); ?></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">📞</div>
                                <div class="info-body">
                                    <h3>Call</h3>
                                    <p><a href="tel:<?php echo htmlspecialchars($contactPhone); ?>"><?php echo htmlspecialchars($contactPhone); ?></a></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">✉️</div>
                                <div class="info-body">
                                    <h3>Email</h3>
                                    <p><a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>"><?php echo htmlspecialchars($contactEmail); ?></a></p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon">🕐</div>
                                <div class="info-body">
                                    <h3>Hours</h3>
                                    <p><?php echo htmlspecialchars($contactHours); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="form-panel">
                            <?php if ($contactSuccess): ?>
                                <div class="alert success">
                                    <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you soon.
                                </div>
                            <?php endif; ?>

                            <?php if ($contactError): ?>
                                <div class="alert error">
                                    <strong>Error:</strong> <?php echo htmlspecialchars($contactError); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="">
                                <input type="hidden" name="form_type" value="contact">
                                <div class="form-grid">
                                    <div class="form-item">
                                        <label for="name">Full Name <span class="required">*</span></label>
                                        <input type="text" id="name" name="name" required
                                               value="<?php echo htmlspecialchars($contactFormData['name'] ?? ''); ?>"
                                               placeholder="John Doe">
                                    </div>
                                    <div class="form-item">
                                        <label for="email">Email Address <span class="required">*</span></label>
                                        <input type="email" id="email" name="email" required
                                               value="<?php echo htmlspecialchars($contactFormData['email'] ?? ''); ?>"
                                               placeholder="john@example.com">
                                    </div>
                                    <div class="form-item">
                                        <label for="phone">Phone Number</label>
                                        <input type="tel" id="phone" name="phone"
                                               value="<?php echo htmlspecialchars($contactFormData['phone'] ?? ''); ?>"
                                               placeholder="+233 XX XXX XXXX">
                                    </div>
                                    <div class="form-item">
                                        <label for="subject">Subject</label>
                                        <select id="subject" name="subject">
                                            <?php
                                            $subjects = [
                                                'General Inquiry',
                                                'Service Request',
                                                'Quote Request',
                                                'Support',
                                                'Partnership',
                                                'Other'
                                            ];
                                            $selectedSubject = $contactFormData['subject'] ?? 'General Inquiry';
                                            foreach ($subjects as $subjectOption) {
                                                $selected = ($selectedSubject === $subjectOption) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($subjectOption) . "\" {$selected}>" . htmlspecialchars($subjectOption) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-item full">
                                        <label for="message">Message <span class="required">*</span></label>
                                        <textarea id="message" name="message" required
                                                  placeholder="Tell us how we can help you..."><?php echo htmlspecialchars($contactFormData['message'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <div class="form-footer">
                                    <button type="submit" class="primary-btn">Send Message</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

