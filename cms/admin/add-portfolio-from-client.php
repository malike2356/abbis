<?php
/**
 * Add Portfolio Item from ABBIS Client Data
 * Quick script to create a portfolio item from client and field report data
 */
session_start();
$rootPath = dirname(dirname(__DIR__));
require_once $rootPath . '/config/app.php';
require_once $rootPath . '/config/security.php';
require_once $rootPath . '/includes/functions.php';
require_once __DIR__ . '/auth.php';

$cmsAuth = new CMSAuth();
if (!$cmsAuth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$pdo = getDBConnection();
$user = $cmsAuth->getCurrentUser();
$baseUrl = '/abbis3.2';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_portfolio'])) {
    $clientId = intval($_POST['client_id'] ?? 0);
    $reportId = intval($_POST['report_id'] ?? 0);
    
    if (!$reportId) {
        header('Location: add-portfolio-from-client.php?error=' . urlencode('Please select a field report'));
        exit;
    }
    
    try {
        // Get field report first
        $reportStmt = $pdo->prepare("
            SELECT fr.*, r.rig_name
            FROM field_reports fr
            LEFT JOIN rigs r ON fr.rig_id = r.id
            WHERE fr.id = ?
        ");
        $reportStmt->execute([$reportId]);
        $report = $reportStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$report) {
            header('Location: add-portfolio-from-client.php?error=' . urlencode('Field report not found'));
            exit;
        }
        
        // Get client data - use report's client_id if available, otherwise use form client_id
        $actualClientId = $report['client_id'] ?? $clientId;
        $clientData = null;
        
        if ($actualClientId) {
            $clientStmt = $pdo->prepare("SELECT id, client_name, email, contact_number, address FROM clients WHERE id = ?");
            $clientStmt->execute([$actualClientId]);
            $clientData = $clientStmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // If no client found, use report's client_contact as client name
        if (!$clientData) {
            $clientData = [
                'id' => null,
                'client_name' => $report['client_contact'] ?: 'Unknown Client',
                'email' => null,
                'contact_number' => null,
                'address' => null
            ];
        }
        
        // Prepare portfolio data
        $title = $clientData['client_name'] . ' - Borehole Project';
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Ensure portfolio table exists
        try {
            $pdo->query("SELECT 1 FROM cms_portfolio LIMIT 1");
        } catch (PDOException $e) {
            // Create table if it doesn't exist
            $migrationPath = $rootPath . '/database/portfolio_migration.sql';
            if (file_exists($migrationPath)) {
                $sql = file_get_contents($migrationPath);
                foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt) {
                        try {
                            $pdo->exec($stmt);
                        } catch (PDOException $ignored) {}
                    }
                }
            }
        }
        
        // Ensure unique slug
        $slugBase = $slug;
        $slugCounter = 1;
        while (true) {
            $checkStmt = $pdo->prepare("SELECT id FROM cms_portfolio WHERE slug=?");
            $checkStmt->execute([$slug]);
            if (!$checkStmt->fetch()) {
                break;
            }
            $slug = $slugBase . '-' . $slugCounter;
            $slugCounter++;
        }
        
        // Build description from field report data
        $description = '';
        if ($report) {
            $description = '<div style="line-height: 1.8;">';
            $description .= '<p><strong>Project Overview:</strong></p>';
            $description .= '<p>Successfully completed a professional borehole drilling project for ' . htmlspecialchars($clientData['client_name']) . '.</p>';
            
            if ($report['site_name']) {
                $description .= '<p><strong>Location:</strong> ' . htmlspecialchars($report['site_name']);
                if ($report['region']) {
                    $description .= ', ' . htmlspecialchars($report['region']);
                }
                $description .= '</p>';
            }
            
            if ($report['total_depth']) {
                $description .= '<p><strong>Total Depth:</strong> ' . number_format($report['total_depth'], 2) . ' meters</p>';
            }
            
            if ($report['rig_name']) {
                $description .= '<p><strong>Rig Used:</strong> ' . htmlspecialchars($report['rig_name']) . '</p>';
            }
            
            if ($report['report_date']) {
                $description .= '<p><strong>Project Date:</strong> ' . date('F j, Y', strtotime($report['report_date'])) . '</p>';
            }
            
            if ($report['total_depth']) {
                $description .= '<p><strong>Construction:</strong> ';
                $constructionDetails = [];
                if ($report['screen_pipes_used'] > 0) {
                    $constructionDetails[] = $report['screen_pipes_used'] . ' screen pipes';
                }
                if ($report['plain_pipes_used'] > 0) {
                    $constructionDetails[] = $report['plain_pipes_used'] . ' plain pipes';
                }
                if ($report['gravel_used'] > 0) {
                    $constructionDetails[] = $report['gravel_used'] . ' bags of gravel';
                }
                $description .= !empty($constructionDetails) ? implode(', ', $constructionDetails) : 'Standard construction';
                $description .= '</p>';
            }
            
            if ($report['remarks']) {
                $description .= '<p><strong>Project Notes:</strong> ' . nl2br(htmlspecialchars($report['remarks'])) . '</p>';
            }
            
            $description .= '</div>';
        } else {
            $description = '<p>Professional borehole drilling project completed for ' . htmlspecialchars($clientData['client_name']) . '.</p>';
            if ($clientData['address']) {
                $description .= '<p><strong>Location:</strong> ' . htmlspecialchars($clientData['address']) . '</p>';
            }
        }
        
        $location = '';
        if ($report && $report['site_name']) {
            $location = $report['site_name'];
            if ($report['region']) {
                $location .= ', ' . $report['region'];
            }
        } elseif ($clientData['address']) {
            $location = $clientData['address'];
        }
        
        $client_name = $clientData['client_name'];
        $project_date = $report ? $report['report_date'] : date('Y-m-d');
        
        // Insert portfolio
        $insertStmt = $pdo->prepare("
            INSERT INTO cms_portfolio 
            (title, slug, description, location, client_name, project_date, status, display_order, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, 'published', 0, ?)
        ");
        
        $insertStmt->execute([
            $title,
            $slug,
            $description,
            $location,
            $client_name,
            $project_date,
            $user['id']
        ]);
        
        $portfolioId = $pdo->lastInsertId();
        
        // Redirect to edit page
        header('Location: portfolio.php?action=edit&id=' . $portfolioId . '&message=Portfolio created from client data');
        exit;
    } catch (Exception $e) {
        header('Location: add-portfolio-from-client.php?error=' . urlencode('Error creating portfolio: ' . $e->getMessage()));
        exit;
    }
}

// Display selection page
$error = $_GET['error'] ?? null;
$message = $_GET['message'] ?? null;

// Check if status column exists
$hasStatusColumn = false;
try {
    $checkStmt = $pdo->query("SELECT status FROM clients LIMIT 1");
    $hasStatusColumn = true;
} catch (PDOException $e) {
    $hasStatusColumn = false;
}

// Get clients with field reports
// Use INNER JOIN to get only clients that have field reports
$statusCondition = $hasStatusColumn ? "AND (c.status = 'active' OR c.status IS NULL)" : "";
$clientQuery = "
    SELECT DISTINCT c.id, c.client_name, c.email, c.contact_number, c.address,
           COUNT(fr.id) as report_count,
           MAX(fr.report_date) as latest_report_date
    FROM clients c
    INNER JOIN field_reports fr ON c.id = fr.client_id
    WHERE fr.client_id IS NOT NULL $statusCondition
    GROUP BY c.id, c.client_name, c.email, c.contact_number, c.address
    HAVING report_count > 0
    ORDER BY latest_report_date DESC, report_count DESC
";

$clients = [];
try {
    $stmt = $pdo->query($clientQuery);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching clients: " . $e->getMessage());
}

// Also check for field reports that might have client info but NULL client_id
$reportsWithoutClientQuery = "
    SELECT fr.id, fr.report_id, fr.report_date, fr.site_name, fr.region,
           fr.client_contact, fr.total_depth, fr.total_income,
           r.rig_name
    FROM field_reports fr
    LEFT JOIN rigs r ON fr.rig_id = r.id
    WHERE fr.client_id IS NULL
    AND fr.client_contact IS NOT NULL
    ORDER BY fr.report_date DESC
    LIMIT 50
";

$reportsWithoutClient = [];
try {
    $stmt = $pdo->query($reportsWithoutClientQuery);
    $reportsWithoutClient = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching reports without client: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Portfolio from ABBIS Client - CMS</title>
    <?php 
    $currentPage = 'portfolio';
    include __DIR__ . '/header.php'; 
    ?>
    <style>
        .client-selection {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .client-card {
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .client-card:hover {
            border-color: var(--admin-primary, #2563eb);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .client-card.selected {
            border-color: var(--admin-primary, #2563eb);
            border-width: 2px;
            background: #f0f7ff;
        }
        .client-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .client-name {
            font-size: 18px;
            font-weight: 600;
            color: #1d2327;
        }
        .report-count {
            background: var(--admin-primary, #2563eb);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .reports-list {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #f0f0f1;
        }
        .report-item {
            padding: 8px 12px;
            background: #f9f9f9;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .report-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
            gap: 8px;
        }
        .report-info {
            flex: 1;
        }
        .report-date {
            color: #646970;
            font-size: 12px;
        }
        .form-actions {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 2px solid #f0f0f1;
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }
        .no-clients {
            text-align: center;
            padding: 40px;
            background: white;
            border: 1px solid #c3c4c7;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <?php include 'footer.php'; ?>
    
    <div class="wrap">
        <div class="admin-page-header">
            <h1>📋 Add Portfolio from ABBIS Client</h1>
            <p>Select a client and field report to create a portfolio item</p>
        </div>

        <?php if ($error): ?>
            <div class="admin-notice admin-notice-error">
                <span class="admin-notice-icon">⚠</span>
                <div class="admin-notice-content">
                    <strong>Error!</strong>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="admin-notice admin-notice-success">
                <span class="admin-notice-icon">✓</span>
                <div class="admin-notice-content">
                    <strong>Success!</strong>
                    <p><?php echo htmlspecialchars($message); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($clients) && empty($reportsWithoutClient)): ?>
            <div class="no-clients">
                <h2>No Clients with Field Reports Found</h2>
                <p>There are no clients with field reports in the system.</p>
                <p><a href="portfolio.php?action=add" class="admin-btn admin-btn-primary">Create Portfolio Manually</a></p>
            </div>
        <?php else: ?>
            <form method="POST" action="" class="client-selection">
                <div class="admin-card">
                    <h2>Select Client and Field Report</h2>
                    
                    <?php foreach ($clients as $client): ?>
                        <div class="client-card">
                            <div class="client-header">
                                <div class="client-name"><?php echo htmlspecialchars($client['client_name']); ?></div>
                                <div class="report-count"><?php echo $client['report_count']; ?> report(s)</div>
                            </div>
                            
                            <?php if ($client['email']): ?>
                                <div style="color: #646970; font-size: 14px; margin-bottom: 8px;"><?php echo htmlspecialchars($client['email']); ?></div>
                            <?php endif; ?>
                            
                            <?php
                            // Get reports for this client
                            $reportsStmt = $pdo->prepare("
                                SELECT fr.id, fr.report_id, fr.report_date, fr.site_name, fr.region, fr.total_depth, r.rig_name
                                FROM field_reports fr
                                LEFT JOIN rigs r ON fr.rig_id = r.id
                                WHERE fr.client_id = ?
                                ORDER BY fr.report_date DESC
                            ");
                            $reportsStmt->execute([$client['id']]);
                            $clientReports = $reportsStmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="reports-list">
                                <strong style="display: block; margin-bottom: 8px; font-size: 13px;">Select a field report:</strong>
                                <?php foreach ($clientReports as $report): ?>
                                    <div class="report-item">
                                        <label>
                                            <input type="radio" name="report_id" value="<?php echo $report['id']; ?>" 
                                                   data-client-id="<?php echo $client['id']; ?>" required>
                                            <div class="report-info">
                                                <div><strong><?php echo htmlspecialchars($report['report_id']); ?></strong></div>
                                                <div class="report-date">
                                                    <?php echo htmlspecialchars($report['site_name']); ?>
                                                    <?php if ($report['region']): ?>
                                                        , <?php echo htmlspecialchars($report['region']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($report['report_date']): ?>
                                                        - <?php echo date('M j, Y', strtotime($report['report_date'])); ?>
                                                    <?php endif; ?>
                                                    <?php if ($report['total_depth']): ?>
                                                        - <?php echo number_format($report['total_depth'], 2); ?>m
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!empty($reportsWithoutClient)): ?>
                        <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid #f0f0f1;">
                            <h3>Field Reports Without Client Association</h3>
                            <p style="color: #646970; margin-bottom: 16px;">These reports don't have a client assigned. You can create a portfolio item using the client contact information.</p>
                            
                            <?php foreach ($reportsWithoutClient as $report): ?>
                                <div class="client-card">
                                    <div class="client-header">
                                        <div class="client-name"><?php echo htmlspecialchars($report['client_contact'] ?: 'Unknown Client'); ?></div>
                                        <div class="report-count">No Client ID</div>
                                    </div>
                                    
                                    <div class="report-item">
                                        <label>
                                            <input type="radio" name="report_id" value="<?php echo $report['id']; ?>" 
                                                   data-client-id="0" required>
                                            <div class="report-info">
                                                <div><strong><?php echo htmlspecialchars($report['report_id']); ?></strong></div>
                                                <div class="report-date">
                                                    <?php echo htmlspecialchars($report['site_name']); ?>
                                                    <?php if ($report['region']): ?>
                                                        , <?php echo htmlspecialchars($report['region']); ?>
                                                    <?php endif; ?>
                                                    <?php if ($report['report_date']): ?>
                                                        - <?php echo date('M j, Y', strtotime($report['report_date'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="form-actions">
                        <a href="portfolio.php" class="admin-btn admin-btn-outline">Cancel</a>
                        <button type="submit" name="create_portfolio" class="admin-btn admin-btn-primary">Create Portfolio</button>
                    </div>
                </div>
            </form>

            <script>
                // Handle report selection - set hidden client_id field
                document.querySelectorAll('input[type="radio"][name="report_id"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const clientId = this.getAttribute('data-client-id') || '0';
                        // Set hidden client_id field
                        let hiddenInput = document.querySelector('input[name="client_id"][type="hidden"]');
                        if (!hiddenInput) {
                            hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = 'client_id';
                            document.querySelector('form').appendChild(hiddenInput);
                        }
                        hiddenInput.value = clientId;
                    });
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>

