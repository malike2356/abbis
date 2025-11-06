<?php
/**
 * Quote Request Form - Links to ABBIS CRM
 */
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/base-url.php';

$pdo = getDBConnection();
$success = false;
$error = '';

// Ensure tables exist
try { $pdo->query("SELECT 1 FROM cms_quote_requests LIMIT 1"); }
catch (Throwable $e) {
    @include_once '../../database/run-sql.php';
    @run_sql_file(__DIR__ . '/../../database/cms_migration.sql');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $serviceType = trim($_POST['service_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if ($name && $email) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cms_quote_requests (name, email, phone, location, service_type, description, status)
                VALUES (?,?,?,?,?,?,'new')
            ");
            $stmt->execute([$name, $email, $phone, $location, $serviceType, $description]);
            
            // Try to create/update in CRM clients table
            try {
                $clientStmt = $pdo->prepare("
                    INSERT INTO clients (name, email, phone, address, source, status)
                    VALUES (?,?,?,?,'website','active')
                    ON DUPLICATE KEY UPDATE phone=?, address=?
                ");
                $clientStmt->execute([$name, $email, $phone, $location, $phone, $location]);
                
                $clientId = $pdo->lastInsertId();
                if (!$clientId) {
                    $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE email=?");
                    $checkStmt->execute([$email]);
                    $clientId = $checkStmt->fetchColumn();
                }
                
                if ($clientId) {
                    $pdo->prepare("UPDATE cms_quote_requests SET converted_to_client_id=? WHERE email=? AND converted_to_client_id IS NULL")
                        ->execute([$clientId, $email]);
                }
            } catch (Throwable $ignored) {}
            
            // Try to create CRM follow-up
            try {
                $followStmt = $pdo->prepare("
                    INSERT INTO client_followups (client_id, followup_type, scheduled_date, priority, notes, status)
                    SELECT id, 'quote_request', DATE_ADD(NOW(), INTERVAL 1 DAY), 'high', ?, 'scheduled'
                    FROM clients WHERE email=? LIMIT 1
                ");
                $followStmt->execute([$description, $email]);
            } catch (Throwable $ignored) {}
            
            $success = true;
        } catch (Throwable $e) {
            $error = 'Failed to submit request. Please try again.';
        }
    } else {
        $error = 'Please fill in required fields.';
    }
}

// Get company name - use consistent helper
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');
$siteTitle = 'Request Quote - ' . $companyName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        /* Enhanced Quote Page Styling - Beautiful WordPress-like design */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .quote-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .quote-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .quote-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
        }
        .container { max-width: 900px; margin: 0 auto; padding: 0 2rem; }
        .form-card {
            background: white;
            padding: 3rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }
        .form-group { margin-bottom: 2rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #1e293b;
            font-size: 1rem;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .form-group textarea { min-height: 150px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }
        .btn-submit {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(14,165,233,0.3);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(14,165,233,0.4);
        }
        .alert {
            padding: 1.25rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .alert-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .alert-error {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .success-message {
            text-align: center;
            padding: 4rem 2rem;
        }
        .success-message h2 {
            color: #10b981;
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        .success-message p {
            color: #475569;
            font-size: 1.125rem;
            margin: 1rem 0;
        }
        @media (max-width: 768px) {
            .quote-hero h1 { font-size: 2.5rem; }
            .form-card { padding: 2rem 1.5rem; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="quote-hero">
            <div class="container">
                <h1>Request a Quote</h1>
                <p>Get a free, no-obligation quote for your project</p>
            </div>
        </div>
        
        <div class="container">
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="success-message">
                        <h2>✓ Request Submitted!</h2>
                        <p>Thank you for your interest. We'll contact you within 24 hours.</p>
                        <a href="<?php echo $baseUrl; ?>/" style="display:inline-block; margin-top:1rem; padding: 0.875rem 1.75rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Return Home</a>
                    </div>
            <?php else: ?>
                <h1 style="margin-bottom:1.5rem;">Request a Quote</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" placeholder="City, Region" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Service Type</label>
                        <select name="service_type">
                            <option value="">Select Service</option>
                            <option value="drilling" <?php echo ($_POST['service_type'] ?? '') === 'drilling' ? 'selected' : ''; ?>>Borehole Drilling</option>
                            <option value="survey" <?php echo ($_POST['service_type'] ?? '') === 'survey' ? 'selected' : ''; ?>>Geophysical Survey</option>
                            <option value="mechanization" <?php echo ($_POST['service_type'] ?? '') === 'mechanization' ? 'selected' : ''; ?>>Pump Installation</option>
                            <option value="treatment" <?php echo ($_POST['service_type'] ?? '') === 'treatment' ? 'selected' : ''; ?>>Water Treatment</option>
                            <option value="maintenance" <?php echo ($_POST['service_type'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="equipment" <?php echo ($_POST['service_type'] ?? '') === 'equipment' ? 'selected' : ''; ?>>Equipment Purchase</option>
                            <option value="other" <?php echo ($_POST['service_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Project Description</label>
                        <textarea name="description" placeholder="Tell us about your project needs..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Submit Request</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

