<?php
/**
 * Rig Request Form - For agents and contractors to request rig rental
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
$success = false;
$error = '';

// Ensure tables exist
try { 
    $pdo->query("SELECT 1 FROM rig_requests LIMIT 1"); 
} catch (Throwable $e) {
    @include_once '../../database/run-sql.php';
    @run_sql_file(__DIR__ . '/../../database/requests_migration.sql');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requesterName = trim($_POST['requester_name'] ?? '');
    $requesterEmail = trim($_POST['requester_email'] ?? '');
    $requesterPhone = trim($_POST['requester_phone'] ?? '');
    $requesterType = trim($_POST['requester_type'] ?? 'contractor');
    $companyName = trim($_POST['company_name'] ?? '');
    $locationAddress = trim($_POST['location_address'] ?? '');
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $region = trim($_POST['region'] ?? '');
    $numberOfBoreholes = intval($_POST['number_of_boreholes'] ?? 1);
    $estimatedBudget = !empty($_POST['estimated_budget']) ? floatval($_POST['estimated_budget']) : null;
    $preferredStartDate = !empty($_POST['preferred_start_date']) ? $_POST['preferred_start_date'] : null;
    $urgency = trim($_POST['urgency'] ?? 'medium');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($requesterName && $requesterEmail && $locationAddress) {
        try {
            // Check if requester is an existing client
            $clientId = null;
            try {
                $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? LIMIT 1");
                $checkStmt->execute([$requesterEmail]);
                $clientId = $checkStmt->fetchColumn();
            } catch (Throwable $ignored) {}
            
            // Insert rig request
            $stmt = $pdo->prepare("
                INSERT INTO rig_requests (
                    requester_name, requester_email, requester_phone, requester_type,
                    company_name, location_address, latitude, longitude, region,
                    number_of_boreholes, estimated_budget, preferred_start_date,
                    urgency, notes, client_id, status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'new')
            ");
            $stmt->execute([
                $requesterName, $requesterEmail, $requesterPhone, $requesterType,
                $companyName, $locationAddress, $latitude, $longitude, $region,
                $numberOfBoreholes, $estimatedBudget, $preferredStartDate,
                $urgency, $notes, $clientId
            ]);
            
            $rigRequestId = $pdo->lastInsertId();
            
            // If not an existing client, try to create one
            if (!$clientId) {
                try {
                    $clientStmt = $pdo->prepare("
                        INSERT INTO clients (client_name, email, phone, address, source, status)
                        VALUES (?,?,?,?,'rig_request','lead')
                        ON DUPLICATE KEY UPDATE phone=?, address=?
                    ");
                    $clientStmt->execute([
                        $requesterName, $requesterEmail, $requesterPhone, $locationAddress,
                        $requesterPhone, $locationAddress
                    ]);
                    
                    $newClientId = $pdo->lastInsertId();
                    if (!$newClientId) {
                        $checkStmt = $pdo->prepare("SELECT id FROM clients WHERE email=?");
                        $checkStmt->execute([$requesterEmail]);
                        $newClientId = $checkStmt->fetchColumn();
                    }
                    
                    if ($newClientId) {
                        $pdo->prepare("UPDATE rig_requests SET client_id=? WHERE id=?")
                            ->execute([$newClientId, $rigRequestId]);
                    }
                } catch (Throwable $ignored) {}
            }
            
            // Create CRM follow-up
            try {
                $followStmt = $pdo->prepare("
                    INSERT INTO client_followups (
                        client_id, type, subject, description, scheduled_date, priority, status
                    )
                    SELECT 
                        COALESCE(?, (SELECT id FROM clients WHERE email=? LIMIT 1)),
                        'call',
                        'Rig Request Follow-up',
                        ?,
                        DATE_ADD(NOW(), INTERVAL 1 DAY),
                        ?,
                        'scheduled'
                    FROM DUAL
                ");
                $followStmt->execute([
                    $clientId, $requesterEmail,
                    "Rig request for {$numberOfBoreholes} borehole(s) at {$locationAddress}",
                    $urgency
                ]);
            } catch (Throwable $ignored) {}
            
            $success = true;
        } catch (Throwable $e) {
            $error = 'Failed to submit request. Please try again.';
            error_log("Rig request error: " . $e->getMessage());
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Get company name
require_once __DIR__ . '/get-site-name.php';
$companyName = getCMSSiteName('Our Company');

// Check if this is a quote request (via type parameter)
$requestType = $_GET['type'] ?? 'rig'; // 'quote' or 'rig'
$isQuoteRequest = ($requestType === 'quote');

if ($isQuoteRequest) {
    // Redirect to quote request page
    header('Location: ' . $baseUrl . '/cms/quote');
    exit;
}

$siteTitle = 'Request Rig Rental - ' . $companyName;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
        }
        .rig-hero {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            padding: 5rem 2rem;
            text-align: center;
            margin-bottom: 3rem;
        }
        .rig-hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .rig-hero p {
            font-size: 1.25rem;
            opacity: 0.95;
        }
        .container { max-width: 1000px; margin: 0 auto; padding: 0 2rem; }
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
        .form-group textarea { min-height: 120px; resize: vertical; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5,150,105,0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .location-search {
            margin-bottom: 1rem;
        }
        .location-search input {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-submit {
            width: 100%;
            padding: 1.25rem;
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.125rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(5,150,105,0.3);
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(5,150,105,0.4);
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
            .rig-hero h1 { font-size: 2.5rem; }
            .form-card { padding: 2rem 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content">
        <div class="rig-hero">
            <div class="container">
                <h1>🚛 Request Rig Rental</h1>
                <p>For agents and contractors - Rent our drilling rigs for your projects</p>
            </div>
        </div>
        
        <div class="container">
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="success-message">
                        <h2>✓ Request Submitted!</h2>
                        <p>Thank you for your rig rental request. We'll contact you within 24 hours to discuss availability and pricing.</p>
                        <a href="<?php echo $baseUrl; ?>/" style="display:inline-block; margin-top:1rem; padding: 0.875rem 1.75rem; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Return Home</a>
                    </div>
                <?php else: ?>
                    <h1 style="margin-bottom:1.5rem;">Rig Rental Request Form</h1>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="post" id="rigRequestForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Your Name *</label>
                                <input type="text" name="requester_name" required value="<?php echo htmlspecialchars($_POST['requester_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="requester_email" required value="<?php echo htmlspecialchars($_POST['requester_email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="requester_phone" required value="<?php echo htmlspecialchars($_POST['requester_phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label>You are a *</label>
                                <select name="requester_type" required>
                                    <option value="contractor" <?php echo ($_POST['requester_type'] ?? 'contractor') === 'contractor' ? 'selected' : ''; ?>>Contractor</option>
                                    <option value="agent" <?php echo ($_POST['requester_type'] ?? '') === 'agent' ? 'selected' : ''; ?>>Agent</option>
                                    <option value="client" <?php echo ($_POST['requester_type'] ?? '') === 'client' ? 'selected' : ''; ?>>Existing Client</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Company Name (if applicable)</label>
                            <input type="text" name="company_name" value="<?php echo htmlspecialchars($_POST['company_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label>Project Location *</label>
                            <div class="location-search">
                                <input type="text" name="location_address" id="location_address" required value="<?php echo htmlspecialchars($_POST['location_address'] ?? ''); ?>" placeholder="Enter the full site address or description">
                            </div>
                            <small style="display: block; margin-bottom: 0.75rem; color: #64748b;">
                                If you know the approximate GPS coordinates you can add them below (decimal degrees). Otherwise leave blank and we’ll capture them later.
                            </small>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Latitude (optional)</label>
                                    <input type="text" name="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>" placeholder="e.g. 5.6037">
                                </div>
                                <div class="form-group">
                                    <label>Longitude (optional)</label>
                                    <input type="text" name="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>" placeholder="e.g. -0.1870">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Region</label>
                                <input type="text" name="region" value="<?php echo htmlspecialchars($_POST['region'] ?? ''); ?>" placeholder="e.g., Greater Accra">
                            </div>
                            <div class="form-group">
                                <label>Number of Boreholes *</label>
                                <input type="number" name="number_of_boreholes" min="1" required value="<?php echo htmlspecialchars($_POST['number_of_boreholes'] ?? '1'); ?>">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Estimated Budget (GHS)</label>
                                <input type="number" name="estimated_budget" min="0" step="0.01" value="<?php echo htmlspecialchars($_POST['estimated_budget'] ?? ''); ?>" placeholder="Optional">
                            </div>
                            <div class="form-group">
                                <label>Preferred Start Date</label>
                                <input type="date" name="preferred_start_date" value="<?php echo htmlspecialchars($_POST['preferred_start_date'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Urgency</label>
                            <select name="urgency">
                                <option value="low" <?php echo ($_POST['urgency'] ?? 'medium') === 'low' ? 'selected' : ''; ?>>Low - Flexible timing</option>
                                <option value="medium" <?php echo ($_POST['urgency'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium - Within 2-4 weeks</option>
                                <option value="high" <?php echo ($_POST['urgency'] ?? '') === 'high' ? 'selected' : ''; ?>>High - Within 1-2 weeks</option>
                                <option value="urgent" <?php echo ($_POST['urgency'] ?? '') === 'urgent' ? 'selected' : ''; ?>>Urgent - As soon as possible</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Additional Notes</label>
                            <textarea name="notes" placeholder="Any additional information about your project..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn-submit">Submit Rig Request</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
</body>
</html>

