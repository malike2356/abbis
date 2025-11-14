<?php
/**
 * Public Complaints Form
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';
require_once __DIR__ . '/get-site-name.php';

$pdo = getDBConnection();
$complaintSuccess = false;
$complaintError = '';
$complaintTicket = null;
$complaintFormData = [];

$complaintsTablesReady = true;
try {
    $pdo->query("SELECT 1 FROM complaints LIMIT 1");
} catch (Throwable $e) {
    $complaintsTablesReady = false;
}

$complaintCategories = [
    'service_issue' => 'Service Issue',
    'billing' => 'Billing & Payments',
    'quality' => 'Work Quality',
    'safety' => 'Health & Safety',
    'staff' => 'Staff Conduct',
    'other' => 'Other / Not Listed',
];

$complaintPriorities = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $complaintFormData = [
        'customer_name' => trim($_POST['customer_name'] ?? ''),
        'customer_email' => trim($_POST['customer_email'] ?? ''),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'customer_reference' => trim($_POST['customer_reference'] ?? ''),
        'category' => $_POST['category'] ?? 'service_issue',
        'priority' => $_POST['priority'] ?? 'medium',
        'summary' => trim($_POST['summary'] ?? ''),
        'details' => trim($_POST['details'] ?? ''),
    ];

    if (!$complaintFormData['customer_name'] || (!$complaintFormData['customer_email'] && !$complaintFormData['customer_phone']) || !$complaintFormData['summary']) {
        $complaintError = 'Name, a contact method (email or phone), and a summary are required.';
    } elseif (!$complaintsTablesReady) {
        $complaintError = 'Complaints are temporarily unavailable. Please contact support directly.';
    } else {
        $categoryKey = array_key_exists($complaintFormData['category'], $complaintCategories) ? $complaintFormData['category'] : 'other';
        $priorityKey = array_key_exists($complaintFormData['priority'], $complaintPriorities) ? $complaintFormData['priority'] : 'medium';

        try {
            $ticketCode = generatePublicComplaintCode($pdo);
            $stmt = $pdo->prepare("
                INSERT INTO complaints (
                    complaint_code, source, channel, customer_name, customer_email, customer_phone,
                    customer_reference, category, subcategory, priority, status, summary, description,
                    due_date, assigned_to, created_by
                ) VALUES (
                    ?, 'customer_portal', 'web', ?, ?, ?, ?, ?, NULL, ?, 'new', ?, ?, NULL, NULL, NULL
                )
            ");
            $stmt->execute([
                $ticketCode,
                $complaintFormData['customer_name'],
                $complaintFormData['customer_email'] ?: null,
                $complaintFormData['customer_phone'] ?: null,
                $complaintFormData['customer_reference'] ?: null,
                $categoryKey,
                $priorityKey,
                $complaintFormData['summary'],
                $complaintFormData['details'] ?: null,
            ]);

            $complaintId = $pdo->lastInsertId();

            try {
                $updateStmt = $pdo->prepare("
                    INSERT INTO complaint_updates (complaint_id, update_type, update_text, internal_only, status_before, status_after, added_by)
                    VALUES (?, 'note', ?, 0, NULL, 'new', NULL)
                ");
                $updateStmt->execute([$complaintId, 'Complaint submitted via public web form.']);
            } catch (Throwable $ignored) {}

            $complaintSuccess = true;
            $complaintTicket = $ticketCode;
            $complaintFormData = [];
        } catch (Throwable $e) {
            $complaintError = 'We were unable to log your complaint. Please try again or contact support directly.';
            error_log("Public complaint form error: " . $e->getMessage());
        }
    }
}

function generatePublicComplaintCode(PDO $pdo): string {
    $prefix = 'CMP-' . date('Ymd');
    try {
        $stmt = $pdo->prepare("SELECT complaint_code FROM complaints WHERE complaint_code LIKE ? ORDER BY complaint_code DESC LIMIT 1");
        $stmt->execute([$prefix . '%']);
        $lastCode = $stmt->fetchColumn();
        if ($lastCode) {
            $nextNumber = intval(substr($lastCode, -4)) + 1;
        } else {
            $nextNumber = 1;
        }
        return sprintf('%s-%04d', $prefix, $nextNumber);
    } catch (Throwable $e) {
        return sprintf('%s-%s', $prefix, strtoupper(bin2hex(random_bytes(2))));
    }
}

$companyName = getCMSSiteName('Our Company');
$siteTitle = 'Submit a Complaint - ' . $companyName;

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
            background: #0f172a;
            color: #0f172a;
            line-height: 1.6;
        }
        .cms-content {
            min-height: 70vh;
        }
        .complaint-hero {
            background: radial-gradient(circle at top, rgba(248, 113, 113, 0.45), rgba(239, 68, 68, 0.9));
            color: #fff;
            padding: 4rem 2rem 3rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .complaint-hero::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at bottom right, rgba(248,113,113,0.4), transparent 55%);
            opacity: 0.8;
        }
        .complaint-hero .container {
            position: relative;
            z-index: 1;
        }
        .complaint-hero h1 {
            font-size: clamp(2.4rem, 6vw, 3.2rem);
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .complaint-hero p {
            font-size: 1.1rem;
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
            padding: 3rem;
            box-shadow: 0 35px 80px -45px rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(239, 68, 68, 0.12);
        }
        .section-header {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            margin-bottom: 2.2rem;
        }
        .section-header small {
            font-size: 0.78rem;
            letter-spacing: 0.24em;
            text-transform: uppercase;
            color: rgba(220, 38, 38, 0.85);
            font-weight: 700;
        }
        .section-header h2 {
            font-size: clamp(2rem, 4vw, 2.4rem);
            margin: 0;
        }
        .section-header p {
            color: #475569;
            max-width: 640px;
        }
        .info-banner {
            background: #fef2f2;
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 18px;
            padding: 1.4rem 1.6rem;
            display: flex;
            gap: 1.2rem;
            align-items: flex-start;
            margin-bottom: 2rem;
        }
        .info-banner span {
            font-size: 1.6rem;
        }
        .info-banner p {
            margin: 0;
            color: #991b1b;
        }
        .form-panel {
            background: linear-gradient(135deg, rgba(248,113,113,0.08), rgba(248,113,113,0.02));
            border-radius: 1.5rem;
            padding: 2.4rem;
            border: 1px solid rgba(248, 113, 113, 0.18);
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
            border: 1px solid #f5d0d0;
            padding: 1rem 1.1rem;
            background: #fff;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
            min-height: 52px;
        }
        .form-item textarea {
            min-height: 180px;
            resize: vertical;
        }
        .form-item.full {
            grid-column: 1 / -1;
        }
        .form-item input:focus,
        .form-item select:focus,
        .form-item textarea:focus {
            outline: none;
            border-color: #ef4444;
            box-shadow: 0 0 0 4px rgba(248,113,113,0.18);
        }
        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        .primary-btn {
            border-radius: 999px;
            padding: 0.95rem 2.4rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s, background 0.2s;
            border: none;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: #fff;
            box-shadow: 0 20px 45px -25px rgba(220,38,38,0.7);
        }
        .primary-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 26px 55px -22px rgba(220,38,38,0.7);
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
        .legal-muted-note {
            font-size: 12px;
            color: #64748b;
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .layout-wrapper {
                margin-top: -2.5rem;
            }
            .section-card {
                padding: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <main class="cms-content">
        <div class="complaint-hero">
            <div class="container">
                <h1>Submit a Complaint</h1>
                <p>If something hasn’t gone right, let our team know. We’ll log, track, and resolve your issue transparently.</p>
            </div>
        </div>

        <div class="container">
            <div class="layout-wrapper">
                <section class="section-card">
                    <div class="section-header">
                        <small>Complaints</small>
                        <h2>Report a service issue</h2>
                        <p>Share what happened and we’ll follow up with a case reference, assigned coordinator, and resolution plan. All complaints are reviewed within one business day.</p>
                    </div>

                    <div class="info-banner">
                        <span>⚠️</span>
                        <p>If this is an emergency or safety incident that requires immediate attention, please call us on <a href="tel:<?php echo htmlspecialchars($contactPhone ?? '+233 XX XXX XXXX'); ?>"><?php echo htmlspecialchars($contactPhone ?? '+233 XX XXX XXXX'); ?></a>.</p>
                    </div>

                    <?php if (!$complaintsTablesReady): ?>
                        <div class="alert error">
                            <strong>Temporarily unavailable.</strong> We’re unable to log complaints right now. Please email <a href="mailto:<?php echo htmlspecialchars($contactEmail ?? 'support@example.com'); ?>"><?php echo htmlspecialchars($contactEmail ?? 'support@example.com'); ?></a>.
                        </div>
                    <?php else: ?>
                        <?php if ($complaintSuccess): ?>
                            <div class="alert success">
                                <strong>Complaint received.</strong> Your ticket <code><?php echo htmlspecialchars($complaintTicket); ?></code> has been logged. We’ll follow up shortly.
                            </div>
                        <?php endif; ?>

                        <?php if ($complaintError): ?>
                            <div class="alert error">
                                <strong>Error:</strong> <?php echo htmlspecialchars($complaintError); ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-panel">
                            <form method="POST" action="">
                                <div class="form-grid">
                                    <div class="form-item">
                                        <label for="complaint-name">Full Name <span class="required">*</span></label>
                                        <input type="text" id="complaint-name" name="customer_name" required
                                               value="<?php echo htmlspecialchars($complaintFormData['customer_name'] ?? ''); ?>"
                                               placeholder="Jane Doe">
                                    </div>
                                    <div class="form-item">
                                        <label for="complaint-email">Email Address <span class="required">*</span></label>
                                        <input type="email" id="complaint-email" name="customer_email" required
                                               value="<?php echo htmlspecialchars($complaintFormData['customer_email'] ?? ''); ?>"
                                               placeholder="jane@example.com">
                                    </div>
                                    <div class="form-item">
                                        <label for="complaint-phone">Phone Number</label>
                                        <input type="tel" id="complaint-phone" name="customer_phone"
                                               value="<?php echo htmlspecialchars($complaintFormData['customer_phone'] ?? ''); ?>"
                                               placeholder="+233 XX XXX XXXX">
                                    </div>
                                    <div class="form-item">
                                        <label for="complaint-reference">Project / Invoice Reference</label>
                                        <input type="text" id="complaint-reference" name="customer_reference"
                                               value="<?php echo htmlspecialchars($complaintFormData['customer_reference'] ?? ''); ?>"
                                               placeholder="Quote or project number (optional)">
                                    </div>
                                    <div class="form-item">
                                        <label for="complaint-category">Issue Category</label>
                                        <select id="complaint-category" name="category">
                                            <?php
                                            $selectedCategory = $complaintFormData['category'] ?? 'service_issue';
                                            foreach ($complaintCategories as $key => $label) {
                                                $selected = ($selectedCategory === $key) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($key) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-item">
                                        <label for="complaint-priority">Priority</label>
                                        <select id="complaint-priority" name="priority">
                                            <?php
                                            $selectedPriority = $complaintFormData['priority'] ?? 'medium';
                                            foreach ($complaintPriorities as $key => $label) {
                                                $selected = ($selectedPriority === $key) ? 'selected' : '';
                                                echo "<option value=\"" . htmlspecialchars($key) . "\" {$selected}>" . htmlspecialchars($label) . "</option>";
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="form-item full">
                                        <label for="complaint-summary">Summary <span class="required">*</span></label>
                                        <input type="text" id="complaint-summary" name="summary" required
                                               value="<?php echo htmlspecialchars($complaintFormData['summary'] ?? ''); ?>"
                                               placeholder="Brief description of the issue">
                                    </div>
                                    <div class="form-item full">
                                        <label for="complaint-details">Details</label>
                                        <textarea id="complaint-details" name="details"
                                                  placeholder="Provide as much detail as possible, including dates, locations, and people involved."><?php echo htmlspecialchars($complaintFormData['details'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                <p class="legal-muted-note">
                                    By submitting this form, you consent to us storing and processing your information for case resolution in line with our privacy policy.
                                </p>
                                <div class="form-footer">
                                    <button type="submit" class="primary-btn">Submit Complaint</button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </section>

            </div>
        </div>
    </main>
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

