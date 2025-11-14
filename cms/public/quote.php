<?php
/**
 * Estimate Request Form - Links to ABBIS CRM
 */
// Start session before any output
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
    
    // Service options
    $includeDrilling = isset($_POST['include_drilling']) ? 1 : 0;
    $includeConstruction = isset($_POST['include_construction']) ? 1 : 0;
    $includeMechanization = isset($_POST['include_mechanization']) ? 1 : 0;
    $includeYieldTest = isset($_POST['include_yield_test']) ? 1 : 0;
    $includeChemicalTest = isset($_POST['include_chemical_test']) ? 1 : 0;
    $includePolytankStand = isset($_POST['include_polytank_stand']) ? 1 : 0;
    
    // Pump preferences (JSON array of catalog item IDs)
    $pumpPreferences = [];
    if (!empty($_POST['pump_preferences']) && is_array($_POST['pump_preferences'])) {
        $pumpPreferences = array_map('intval', $_POST['pump_preferences']);
    }
    $pumpPreferencesJson = !empty($pumpPreferences) ? json_encode($pumpPreferences) : null;
    
    // Location coordinates
    $latitude = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
    $longitude = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
    $address = trim($_POST['address'] ?? $location);
    $estimatedBudget = !empty($_POST['estimated_budget']) ? floatval($_POST['estimated_budget']) : null;
    
    if ($name && $email) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cms_quote_requests (
                    name, email, phone, location, service_type, description, status,
                    include_drilling, include_construction, include_mechanization,
                    include_yield_test, include_chemical_test, include_polytank_stand,
                    pump_preferences, latitude, longitude, address, estimated_budget
                ) VALUES (?,?,?,?,?,?,'new',?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $name, $email, $phone, $location, $serviceType, $description,
                $includeDrilling, $includeConstruction, $includeMechanization,
                $includeYieldTest, $includeChemicalTest, $includePolytankStand,
                $pumpPreferencesJson, $latitude, $longitude, $address, $estimatedBudget
            ]);
            
            // Try to create/update in CRM clients table
            try {
                $clientStmt = $pdo->prepare("
                    INSERT INTO clients (client_name, email, contact_number, address, source, status)
                    VALUES (?,?,?,?,'website','lead')
                    ON DUPLICATE KEY UPDATE contact_number=?, address=?
                ");
                $clientStmt->execute([$name, $email, $phone, $address ?: $location, $phone, $address ?: $location]);
                
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

// Check if this is a rig request (via type parameter)
$requestType = $_GET['type'] ?? 'quote'; // 'quote' or 'rig'
$isRigRequest = ($requestType === 'rig');

if ($isRigRequest) {
    // Redirect to rig request page
    header('Location: ' . $baseUrl . '/cms/rig-request');
    exit;
}

$siteTitle = 'Estimate Request - ' . $companyName;
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
        html, body { 
            width: 100%;
            overflow-x: hidden;
        }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; 
            background: #f8fafc; 
            width: 100%;
        }
        .cms-content {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 0;
            min-height: 70vh;
            width: 100%;
            overflow-x: hidden;
        }
        .quote-hero {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            color: white;
            padding: 5rem 0;
            text-align: center;
            margin-top: 20px; /* Additional spacing - body already has padding-top: 80px */
            margin-bottom: 3rem;
            width: 100%;
            box-sizing: border-box;
        }
        .quote-hero .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 0 24px;
            width: 100%;
            box-sizing: border-box;
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
        .container { 
            max-width: 900px; 
            margin: 0 auto; 
            padding: 0 24px; 
            width: 100%;
            box-sizing: border-box;
        }
        .form-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            border: 1px solid rgba(0,0,0,0.05);
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
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
            max-width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s;
            box-sizing: border-box;
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
            .cms-content {
                padding: 2rem 0;
            }
            .quote-hero {
                padding: 3rem 0;
            }
            .quote-hero .container {
                padding: 0 16px;
            }
            .quote-hero h1 { 
                font-size: 2.5rem; 
            }
            .quote-hero p {
                font-size: 1.125rem;
            }
            .container { 
                padding: 0 16px !important; 
            }
            .form-card { 
                padding: 1.5rem; 
            }
            .form-group { 
                margin-bottom: 1.5rem; 
            }
            .form-group label[style*="flex: 1 1 calc(33.333%"] {
                flex: 1 1 100% !important;
                min-width: 100% !important;
                padding: 0.875rem !important;
            }
        }
        
        @media (max-width: 1024px) and (min-width: 769px) {
            .container {
                padding: 0 20px !important;
            }
            .quote-hero .container {
                padding: 0 20px;
            }
            .form-card {
                padding: 2rem;
            }
            .form-group label[style*="flex: 1 1 calc(33.333%"] {
                flex: 1 1 calc(50% - 1rem) !important;
                min-width: calc(50% - 1rem) !important;
            }
        }
        
        @media (min-width: 1025px) {
            .container {
                padding: 0 32px !important;
            }
            .quote-hero .container {
                padding: 0 32px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    
    <main class="cms-content" style="width: 100%; overflow-x: hidden;">
        <div class="quote-hero">
            <div class="container">
                <h1>📋 Estimate Request</h1>
                <p>Get a free, no-obligation quote for your complete borehole project</p>
            </div>
        </div>
        
        <div class="container" style="width: 100%; max-width: 900px; margin: 0 auto; padding: 0 24px; box-sizing: border-box;">
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="success-message">
                        <h2>✓ Request Submitted!</h2>
                        <p>Thank you for your interest. We'll contact you within 24 hours.</p>
                        <a href="<?php echo $baseUrl; ?>/" style="display:inline-block; margin-top:1rem; padding: 0.875rem 1.75rem; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600;">Return Home</a>
                    </div>
            <?php else: ?>
                <h1 style="margin-bottom:1.5rem;">Estimate Request</h1>
                
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
                        <label>Project Location</label>
                        <input type="text" name="location" placeholder="City, Region" value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                        <input type="hidden" name="address" id="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
                        <input type="hidden" name="latitude" id="latitude" value="<?php echo htmlspecialchars($_POST['latitude'] ?? ''); ?>">
                        <input type="hidden" name="longitude" id="longitude" value="<?php echo htmlspecialchars($_POST['longitude'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Service Type</label>
                        <select name="service_type">
                            <option value="">Select Service</option>
                            <option value="full_borehole" <?php echo ($_POST['service_type'] ?? '') === 'full_borehole' ? 'selected' : ''; ?>>Complete Borehole Service</option>
                            <option value="drilling" <?php echo ($_POST['service_type'] ?? '') === 'drilling' ? 'selected' : ''; ?>>Borehole Drilling Only</option>
                            <option value="construction" <?php echo ($_POST['service_type'] ?? '') === 'construction' ? 'selected' : ''; ?>>Construction Only</option>
                            <option value="mechanization" <?php echo ($_POST['service_type'] ?? '') === 'mechanization' ? 'selected' : ''; ?>>Mechanization Only</option>
                            <option value="other" <?php echo ($_POST['service_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label style="margin-bottom: 1rem; display: block;">Services Required *</label>
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; width: 100%; box-sizing: border-box;">
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;" 
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_drilling" value="1" <?php echo isset($_POST['include_drilling']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Drilling</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Borehole drilling with our drilling machine</small>
                                </div>
                            </label>
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;" 
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_construction" value="1" <?php echo isset($_POST['include_construction']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Construction</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Installation of screen pipe, plain pipe, and gravels</small>
                                </div>
                            </label>
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;" 
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_mechanization" value="1" id="include_mechanization" <?php echo isset($_POST['include_mechanization']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Mechanization</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Pump installation and accessories</small>
                                </div>
                            </label>
                            <div id="pump_selection" style="width: 100%; margin-top: 0.5rem; padding: 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-sizing: border-box; <?php echo isset($_POST['include_mechanization']) ? '' : 'display: none;'; ?>">
                                <label style="font-size: 0.9rem; color: #64748b; font-weight: 500; display: block; margin-bottom: 0.5rem;">Preferred Pumps (optional):</label>
                                <select name="pump_preferences[]" multiple class="form-control" style="width: 100%; min-height: 100px; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                                    <?php
                                    // Get pumps from catalog
                                    try {
                                        $pumpsStmt = $pdo->query("
                                            SELECT id, name, sku 
                                            FROM catalog_items 
                                            WHERE is_active = 1 
                                            AND (name LIKE '%pump%' OR name LIKE '%motor%' OR category_id IN (
                                                SELECT id FROM catalog_categories WHERE name LIKE '%pump%' OR name LIKE '%motor%'
                                            ))
                                            ORDER BY name
                                        ");
                                        $pumps = $pumpsStmt->fetchAll();
                                        foreach ($pumps as $pump) {
                                            $selected = !empty($_POST['pump_preferences']) && in_array($pump['id'], $_POST['pump_preferences']) ? 'selected' : '';
                                            echo '<option value="' . $pump['id'] . '" ' . $selected . '>' . htmlspecialchars($pump['name']) . ($pump['sku'] ? ' (' . htmlspecialchars($pump['sku']) . ')' : '') . '</option>';
                                        }
                                    } catch (Throwable $e) {
                                        echo '<option disabled>Unable to load pumps</option>';
                                    }
                                    ?>
                                </select>
                                <small style="display: block; margin-top: 0.5rem; color: #64748b; font-size: 0.875rem;">Hold Ctrl/Cmd to select multiple pumps</small>
                            </div>
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;" 
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_yield_test" value="1" <?php echo isset($_POST['include_yield_test']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Yield Test</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Water yield testing with all details</small>
                                </div>
                            </label>
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;"
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_chemical_test" value="1" <?php echo isset($_POST['include_chemical_test']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Chemical Test</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Laboratory water quality testing</small>
                                </div>
                            </label>
                            <label style="display: flex; align-items: flex-start; cursor: pointer; font-weight: normal; flex: 1 1 calc(33.333% - 0.67rem); min-width: 200px; max-width: 100%; padding: 1rem; border: 2px solid #e2e8f0; border-radius: 8px; transition: all 0.2s; background: #f9fafb; box-sizing: border-box;"
                                   onmouseover="this.style.borderColor='#0ea5e9'; this.style.background='#f0f9ff';" 
                                   onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f9fafb';">
                                <input type="checkbox" name="include_polytank_stand" value="1" <?php echo isset($_POST['include_polytank_stand']) ? 'checked' : ''; ?> style="margin-right: 0.75rem; width: auto; margin-top: 2px; flex-shrink: 0;" 
                                       onchange="this.closest('label').style.borderColor = this.checked ? '#0ea5e9' : '#e2e8f0'; this.closest('label').style.background = this.checked ? '#f0f9ff' : '#f9fafb';">
                                <div style="flex: 1; min-width: 0; word-wrap: break-word;">
                                    <strong style="display: block; margin-bottom: 0.25rem; color: #1e293b;">Polytank Stand Construction</strong>
                                    <small style="display: block; color: #64748b; font-size: 0.875rem; line-height: 1.4;">Construction of polytank stand (optional)</small>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Estimated Budget (GHS)</label>
                        <input type="number" name="estimated_budget" min="0" step="0.01" placeholder="Optional" value="<?php echo htmlspecialchars($_POST['estimated_budget'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label>Project Description</label>
                        <textarea name="description" placeholder="Tell us about your project needs, timeline, or any special requirements..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Submit Request</button>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include __DIR__ . '/footer.php'; ?>
    
    <script>
        // Show/hide pump selection based on mechanization checkbox
        document.getElementById('include_mechanization').addEventListener('change', function() {
            const pumpSelection = document.getElementById('pump_selection');
            if (this.checked) {
                pumpSelection.style.display = 'block';
            } else {
                pumpSelection.style.display = 'none';
            }
        });
        
        // Initialize checkbox visual states on page load
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="include_"]');
            checkboxes.forEach(function(checkbox) {
                const label = checkbox.closest('label');
                if (checkbox.checked) {
                    label.style.borderColor = '#0ea5e9';
                    label.style.background = '#f0f9ff';
                }
            });
        });
        
        // Validate at least one service is selected
        document.querySelector('form').addEventListener('submit', function(e) {
            const checkboxes = [
                'include_drilling',
                'include_construction',
                'include_mechanization',
                'include_yield_test',
                'include_chemical_test',
                'include_polytank_stand'
            ];
            
            const atLeastOneChecked = checkboxes.some(name => {
                return document.querySelector(`input[name="${name}"]`).checked;
            });
            
            if (!atLeastOneChecked) {
                e.preventDefault();
                alert('Please select at least one service required.');
                return false;
            }
        });
    </script>
</body>
</html>

