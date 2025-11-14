<?php
/**
 * Sync Offline Reports API
 * Handles syncing of offline field reports to the server
 */

require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/validation.php';
require_once '../includes/helpers.php';
require_once '../includes/MaintenanceExtractor.php';
require_once '../includes/AccountingAutoTracker.php';
require_once '../includes/pos/FieldReportPosIntegrator.php';

// Initialize ABBIS functions object
$abbis = new ABBISFunctions();

header('Content-Type: application/json');

// Allow CORS for offline reports (restrict in production with proper origin)
require_once '../includes/url-manager.php';
$appUrl = parse_url(APP_URL, PHP_URL_SCHEME) . '://' . parse_url(APP_URL, PHP_URL_HOST);
$appPort = parse_url(APP_URL, PHP_URL_PORT);
if ($appPort) {
    $appUrl .= ':' . $appPort;
}
$allowedOrigins = [$appUrl, str_replace(':8080', '', $appUrl), APP_URL];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || strpos($origin, parse_url(APP_URL, PHP_URL_HOST)) !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
} else {
    header('Access-Control-Allow-Origin: *');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    header('Access-Control-Max-Age: 86400');
    http_response_code(200);
    exit;
}

// For offline sync, we need to handle authentication flexibly
// Since offline reports may sync when user is logged in, check session first
$authenticated = false;

// Check for session auth (primary method - user logged into ABBIS)
try {
    if (isset($auth) && $auth) {
        $auth->requireAuth();
        $authenticated = true;
    }
} catch (Exception $e) {
    // Session auth failed, try API key
}

// Check for API key in header (fallback for offline sync)
if (!$authenticated) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? $_POST['api_key'] ?? '';
    if (!empty($apiKey)) {
        // Validate API key (implement your API key validation)
        // For now, allow if API key is provided (implement proper validation in production)
        $authenticated = true;
    }
}

if (!$authenticated) {
    jsonResponse(['success' => false, 'message' => 'Authentication required. Please log in to ABBIS or provide a valid API key.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'sync';
    $report = $input['report'] ?? [];
    
    if (empty($report)) {
        jsonResponse(['success' => false, 'message' => 'No report data provided'], 400);
    }
    
    $pdo = getDBConnection();
    
    switch ($action) {
        case 'sync':
            $result = syncOfflineReport($pdo, $report);
            jsonResponse($result);
            break;
            
        case 'check_conflict':
            $result = checkConflict($pdo, $report);
            jsonResponse($result);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log('Offline sync error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Sync failed: ' . $e->getMessage()
    ], 500);
}

/**
 * Sync offline report to database
 */
function syncOfflineReport($pdo, $report) {
    global $abbis;
    
    try {
        $pdo->beginTransaction();
        
        // Check if report was already synced (prevent duplicate syncs)
        if (!empty($report['server_id'])) {
            $existingStmt = $pdo->prepare("SELECT id, report_id FROM field_reports WHERE report_id = ? OR id = ? LIMIT 1");
            $existingStmt->execute([$report['server_id'], $report['server_id']]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $pdo->rollBack();
                return [
                    'success' => true,
                    'message' => 'Report already exists on server (duplicate sync prevented)',
                    'report_id' => $existing['report_id'] ?? $existing['id'],
                    'already_synced' => true
                ];
            }
        }
        
        // Check for conflicts (duplicate reports) - only for new reports
        $conflict = checkConflict($pdo, $report);
        if ($conflict['has_conflict'] && !($report['force_sync'] ?? false)) {
            $pdo->rollBack();
            return [
                'success' => false,
                'conflict' => true,
                'message' => 'Conflict detected with existing report',
                'server_data' => $conflict['server_report']
            ];
        }
        
        // Extract and save client
        $clientId = null;
        if (!empty($report['client_name'])) {
            $clientId = $abbis->extractAndSaveClient([
                'client_name' => $report['client_name'] ?? '',
                'contact_person' => $report['client_contact_person'] ?? '',
                'client_contact' => $report['client_contact'] ?? '',
                'email' => $report['client_email'] ?? ''
            ]);
        }
        
        // Get rig ID from rig name/code
        $rigId = null;
        $rigCode = null;
        if (!empty($report['rig_id'])) {
            // Use rig_id if provided
            $rigId = intval($report['rig_id']);
            $rigStmt = $pdo->prepare("SELECT rig_code FROM rigs WHERE id = ?");
            $rigStmt->execute([$rigId]);
            $rig = $rigStmt->fetch();
            if ($rig) {
                $rigCode = $rig['rig_code'];
            }
        } elseif (!empty($report['rig_name'])) {
            // Try to find rig by name/code
            $rigStmt = $pdo->prepare("SELECT id, rig_code FROM rigs WHERE rig_name LIKE ? OR rig_code LIKE ? LIMIT 1");
            $rigSearch = '%' . $report['rig_name'] . '%';
            $rigStmt->execute([$rigSearch, $rigSearch]);
            $rig = $rigStmt->fetch();
            if ($rig) {
                $rigId = $rig['id'];
                $rigCode = $rig['rig_code'];
            }
        }
        
        // Generate report ID
        $reportId = $abbis->generateReportId($rigCode);
        
        // Calculate construction depth
        $screenPipesUsed = intval($report['screen_pipes_used'] ?? 0);
        $plainPipesUsed = intval($report['plain_pipes_used'] ?? 0);
        $constructionDepth = $abbis->calculateConstructionDepth($screenPipesUsed, $plainPipesUsed);
        
        // Calculate financial totals using ABBIS function
        $financialData = [
            'balance_bf' => $report['balance_bf'] ?? 0,
            'contract_sum' => $report['contract_sum'] ?? $report['full_contract_sum'] ?? 0,
            'rig_fee_collected' => $report['rig_fee_collected'] ?? 0,
            'cash_received' => $report['cash_received'] ?? 0,
            'materials_income' => $report['materials_income'] ?? 0,
            'materials_purchased' => $report['materials_purchased'] ?? 0,
            'daily_expenses' => $report['daily_expenses'] ?? 0,
            'loan_reclaims' => $report['loan_reclaims'] ?? 0,
            'workers' => $report['workers'] ?? []
        ];
        
        $totals = $abbis->calculateFinancialTotals($financialData);
        
        $totalIncome = $totals['total_income'] ?? 0;
        $totalExpenses = $totals['total_expenses'] ?? 0;
        $totalWages = $totals['total_wages'] ?? 0;
        $netProfit = $totals['net_profit'] ?? 0;
        
        // Build columns and values matching save-report.php structure
        $storeId = !empty($report['materials_store_id']) ? intval($report['materials_store_id']) : null;

        $columns = [
            'report_id', 'report_date', 'rig_id', 'job_type', 'site_name', 'plus_code', 'latitude', 'longitude',
            'location_description', 'region', 'client_id', 'client_contact', 'start_time', 'finish_time',
            'total_duration', 'start_rpm', 'finish_rpm', 'total_rpm', 'rod_length', 'rods_used', 'total_depth',
            'screen_pipes_used', 'plain_pipes_used', 'gravel_used', 'construction_depth', 'materials_provided_by', 'materials_store_id',
            'supervisor', 'total_workers', 'remarks', 'incident_log', 'solution_log', 'recommendation_log',
            'balance_bf', 'contract_sum', 'rig_fee_charged', 'rig_fee_collected', 'cash_received', 'materials_income',
            'materials_cost', 'momo_transfer', 'cash_given', 'bank_deposit', 'total_income', 'total_expenses',
            'total_wages', 'net_profit', 'total_money_banked', 'days_balance', 'outstanding_rig_fee', 'created_by'
        ];
        
        $values = [
            $reportId,
            $report['report_date'] ?? date('Y-m-d'),
            $rigId,
            $report['job_type'] ?? 'direct',
            $report['site_name'] ?? '',
            $report['plus_code'] ?? null,
            !empty($report['latitude']) ? floatval($report['latitude']) : null,
            !empty($report['longitude']) ? floatval($report['longitude']) : null,
            $report['location_description'] ?? '',
            $report['region'] ?? '',
            $clientId,
            $report['client_contact'] ?? '',
            $report['start_time'] ?? null,
            $report['finish_time'] ?? null,
            $report['total_duration'] ?? null,
            !empty($report['start_rpm']) ? floatval($report['start_rpm']) : null,
            !empty($report['finish_rpm']) ? floatval($report['finish_rpm']) : null,
            !empty($report['total_rpm']) ? floatval($report['total_rpm']) : null,
            $report['rod_length'] ?? null,
            intval($report['rods_used'] ?? 0),
            !empty($report['total_depth']) ? floatval($report['total_depth']) : null,
            $screenPipesUsed,
            $plainPipesUsed,
            intval($report['gravel_used'] ?? 0),
            $constructionDepth,
            $report['materials_provided_by'] ?? 'client',
            $storeId,
            $report['supervisor'] ?? '',
            count($report['workers'] ?? []),
            $report['remarks'] ?? '',
            $report['incident_log'] ?? '',
            $report['solution_log'] ?? '',
            $report['recommendation_log'] ?? '',
            floatval($report['balance_bf'] ?? 0),
            floatval($report['contract_sum'] ?? $report['full_contract_sum'] ?? 0),
            floatval($report['rig_fee_charged'] ?? 0),
            floatval($report['rig_fee_collected'] ?? 0),
            floatval($report['cash_received'] ?? 0),
            floatval($report['materials_income'] ?? 0),
            floatval($report['materials_cost'] ?? $report['materials_purchased'] ?? 0),
            floatval($report['momo_transfer'] ?? 0),
            floatval($report['cash_given'] ?? 0),
            floatval($report['bank_deposit'] ?? $report['deposit_amount'] ?? 0),
            $totalIncome,
            $totalExpenses,
            $totalWages,
            $netProfit,
            $totals['total_money_banked'] ?? 0,
            $totals['days_balance'] ?? 0,
            $totals['outstanding_rig_fee'] ?? 0,
            $_SESSION['user_id'] ?? 1 // Default to admin if no session
        ];
        
        // Add maintenance columns if they exist
        try {
            $pdo->query("SELECT is_maintenance_work FROM field_reports LIMIT 1");
            $isMaintenanceWork = ($report['job_type'] ?? '') === 'maintenance' || ($report['is_maintenance_work'] ?? false);
            $columns[] = 'is_maintenance_work';
            $columns[] = 'maintenance_work_type';
            $columns[] = 'maintenance_description';
            $columns[] = 'asset_id';
            $values[] = $isMaintenanceWork ? 1 : 0;
            $values[] = $report['maintenance_work_type'] ?? null;
            $values[] = $report['maintenance_description'] ?? null;
            $values[] = !empty($report['asset_id']) ? intval($report['asset_id']) : null;
        } catch (PDOException $e) {
            // Columns don't exist yet
        }
        
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $columnsList = implode(', ', $columns);
        
        $sql = "INSERT INTO field_reports ($columnsList) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $reportDbId = $pdo->lastInsertId();

        if (($report['materials_provided_by'] ?? '') === 'store' && $storeId) {
            FieldReportPosIntegrator::syncInventory(
                $pdo,
                [
                    'report_db_id' => $reportDbId,
                    'report_code' => $reportId,
                    'store_id' => $storeId,
                    'screen_pipes_used' => (int) ($report['screen_pipes_used'] ?? 0),
                    'plain_pipes_used' => (int) ($report['plain_pipes_used'] ?? 0),
                    'gravel_used' => (int) ($report['gravel_used'] ?? 0),
                    'materials_provided_by' => 'store',
                    'performed_by' => $_SESSION['user_id'] ?? null,
                ]
            );
        }
        
        // Save workers/payroll
        if (!empty($report['workers']) && is_array($report['workers'])) {
            foreach ($report['workers'] as $worker) {
                $workerStmt = $pdo->prepare("
                    INSERT INTO payroll_entries 
                    (report_id, worker_name, role, wage_type, units, pay_per_unit, benefits, loan_reclaim, amount, paid_today, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $workerStmt->execute([
                    $reportDbId,
                    $worker['worker_name'] ?? '',
                    $worker['role'] ?? '',
                    $worker['wage_type'] ?? 'daily',
                    floatval($worker['units'] ?? 0),
                    floatval($worker['pay_per_unit'] ?? 0),
                    floatval($worker['benefits'] ?? 0),
                    floatval($worker['loan_reclaim'] ?? 0),
                    floatval($worker['amount'] ?? 0),
                    ($worker['paid_today'] ?? '0') === '1' ? 'Yes' : 'No',
                    $worker['notes'] ?? ''
                ]);
            }
        }
        
        // Save expenses (with unit cost and quantity)
        if (!empty($report['expenses']) && is_array($report['expenses'])) {
            foreach ($report['expenses'] as $expense) {
                // Check if expense_entries table has unit_cost and quantity columns
                try {
                    $pdo->query("SELECT unit_cost, quantity FROM expense_entries LIMIT 1");
                    $expenseStmt = $pdo->prepare("
                        INSERT INTO expense_entries 
                        (report_id, description, unit_cost, quantity, amount, category, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $expenseStmt->execute([
                        $reportDbId,
                        $expense['description'] ?? '',
                        floatval($expense['unit_cost'] ?? 0),
                        floatval($expense['quantity'] ?? 0),
                        floatval($expense['amount'] ?? 0),
                        $expense['category'] ?? ''
                    ]);
                } catch (PDOException $e) {
                    // Fallback for older schema
                    $expenseStmt = $pdo->prepare("
                        INSERT INTO expense_entries 
                        (report_id, description, amount, category, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $expenseStmt->execute([
                        $reportDbId,
                        $expense['description'] ?? '',
                        floatval($expense['amount'] ?? 0),
                        $expense['category'] ?? ''
                    ]);
                }
            }
        }
        
        // Handle maintenance extraction if applicable
        if (($report['job_type'] ?? '') === 'maintenance' || ($report['is_maintenance_work'] ?? false)) {
            try {
                $extractor = new MaintenanceExtractor($pdo);
                $maintenanceData = $extractor->extractFromFieldReport($report);
                if ($maintenanceData) {
                    $userId = $_SESSION['user_id'] ?? null;
                    if (!$userId) {
                        // Try to get user from report or use system user
                        $userId = $report['user_id'] ?? 1; // Default to admin/system user
                    }
                    $extractor->createMaintenanceRecord($maintenanceData, $reportDbId, $userId);
                }
            } catch (Exception $e) {
                error_log('Maintenance extraction error: ' . $e->getMessage());
            }
        }
        
        $pdo->commit();
        
        // Automatically track financial transactions in accounting system - runs for EVERY synced report
        try {
            // Ensure accounting tables exist
            try {
                $pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
            } catch (PDOException $e) {
                // Initialize if needed
                $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
                if (file_exists($migrationFile)) {
                    $sql = file_get_contents($migrationFile);
                    if ($sql) {
                        foreach (preg_split('/;\s*\n/', $sql) as $stmt) {
                            $stmt = trim($stmt);
                            if ($stmt) {
                                try {
                                    $pdo->exec($stmt);
                                } catch (PDOException $e2) {}
                            }
                        }
                    }
                }
            }
            
            $accountingTracker = new AccountingAutoTracker($pdo);
            
            // Get report data for accounting
            $reportStmt = $pdo->prepare("SELECT fr.*, c.client_name 
                                        FROM field_reports fr 
                                        LEFT JOIN clients c ON fr.client_id = c.id 
                                        WHERE fr.id = ?");
            $reportStmt->execute([$reportDbId]);
            $savedReport = $reportStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($savedReport) {
                // Calculate totals
                $totals = $abbis->calculateFinancialTotals($savedReport);
                
                $reportDataForAccounting = [
                    'report_id' => $savedReport['report_id'],
                    'report_date' => $savedReport['report_date'],
                    'site_name' => $savedReport['site_name'] ?? '',
                    'client_name' => $savedReport['client_name'] ?? '',
                    'created_by' => $savedReport['created_by'] ?? 1,
                    'contract_sum' => floatval($savedReport['contract_sum'] ?? 0),
                    'rig_fee_charged' => floatval($savedReport['rig_fee_charged'] ?? 0),
                    'rig_fee_collected' => floatval($savedReport['rig_fee_collected'] ?? 0),
                    'cash_received' => floatval($savedReport['cash_received'] ?? 0),
                    'materials_income' => floatval($savedReport['materials_income'] ?? 0),
                    'materials_cost' => floatval($savedReport['materials_cost'] ?? 0),
                    'momo_transfer' => floatval($savedReport['momo_transfer'] ?? 0),
                    'cash_given' => floatval($savedReport['cash_given'] ?? 0),
                    'bank_deposit' => floatval($savedReport['bank_deposit'] ?? 0),
                    'total_wages' => $totals['total_wages'],
                    'total_expenses' => $totals['total_expenses'],
                    'outstanding_rig_fee' => $totals['outstanding_rig_fee']
                ];
                
                $result = $accountingTracker->trackFieldReport($reportDbId, $reportDataForAccounting);
                if ($result) {
                    error_log("Accounting: Auto-tracked synced field report {$savedReport['report_id']} (ID: {$reportDbId})");
                }
            }
        } catch (Exception $e) {
            // Log but don't fail the sync if accounting tracking fails
            error_log("Accounting auto-tracking error for synced report ID {$reportDbId}: " . $e->getMessage());
        }
        
        return [
            'success' => true,
            'message' => 'Report synced successfully',
            'report_id' => $reportDbId,
            'report_reference' => $reportId
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Check for conflicts (duplicate reports)
 */
function checkConflict($pdo, $report) {
    // Check for reports with same date, site, and rig
    $date = $report['report_date'] ?? date('Y-m-d');
    $siteName = $report['site_name'] ?? '';
    $rigName = $report['rig_name'] ?? '';
    
    if (empty($siteName)) {
        return ['has_conflict' => false];
    }
    
    // Try to find rig ID
    $rigId = null;
    if (!empty($rigName)) {
        $rigStmt = $pdo->prepare("SELECT id FROM rigs WHERE rig_name LIKE ? OR rig_code LIKE ? LIMIT 1");
        $rigSearch = '%' . $rigName . '%';
        $rigStmt->execute([$rigSearch, $rigSearch]);
        $rig = $rigStmt->fetch();
        if ($rig) {
            $rigId = $rig['id'];
        }
    }
    
    // Check for existing report
    $sql = "SELECT * FROM field_reports WHERE report_date = ? AND site_name = ?";
    $params = [$date, $siteName];
    
    if ($rigId) {
        $sql .= " AND rig_id = ?";
        $params[] = $rigId;
    }
    
    $sql .= " LIMIT 1";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $existing = $stmt->fetch();
    
    if ($existing) {
        return [
            'has_conflict' => true,
            'server_report' => $existing
        ];
    }
    
    return ['has_conflict' => false];
}


