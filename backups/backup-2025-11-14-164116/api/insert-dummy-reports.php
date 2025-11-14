<?php
/**
 * Insert Dummy Field Reports for Testing
 * Creates 5 realistic dummy reports with clients, payroll, expenses, and financial data
 */
require_once '../config/app.php';
require_once '../config/security.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/helpers.php';

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Validate CSRF token if present (for form submissions)
if (isset($_POST['csrf_token'])) {
    if (!CSRF::validateToken($_POST['csrf_token'])) {
        jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
    }
}

// Get count from POST data (support both JSON and form data)
$count = 5; // default
if (isset($_POST['count'])) {
    $count = (int)$_POST['count'];
} elseif ($jsonInput = json_decode(file_get_contents('php://input'), true)) {
    $count = (int)($jsonInput['count'] ?? 5);
}

// Validate count
if ($count < 1 || $count > 50) {
    jsonResponse(['success' => false, 'message' => 'Count must be between 1 and 50'], 400);
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    
    $userId = $_SESSION['user_id'];
    $createdReports = [];
    
    // Get or create an active rig
    $rig = $pdo->query("SELECT id, rig_code, rig_name FROM rigs WHERE status = 'active' LIMIT 1")->fetch();
    if (!$rig) {
        // Create a dummy rig if none exists
        $pdo->exec("INSERT INTO rigs (rig_name, rig_code, status) VALUES ('Test Rig 1', 'RIG001', 'active')");
        $rigId = $pdo->lastInsertId();
        $rigCode = 'RIG001';
        $rigName = 'Test Rig 1';
    } else {
        $rigId = $rig['id'];
        $rigCode = $rig['rig_code'] ?? 'RIG001';
        $rigName = $rig['rig_name'];
    }
    
    // Base dummy data templates - we'll use these to generate the requested number of reports
    $dummyDataTemplates = [
        [
            'client_name' => 'Acme Construction Ltd',
            'contact_person' => 'John Mensah',
            'contact_number' => '+233 24 123 4567',
            'email' => 'john.mensah@acme.com',
            'site_name' => 'Acme Office Complex',
            'region' => 'Greater Accra',
            'job_type' => 'direct',
            'total_depth' => 85.5,
            'construction_depth' => 80.0,
            'rods_used' => 17,
            'rod_length' => 5.0,
            'screen_pipes' => 8,
            'plain_pipes' => 12,
            'gravel' => 15,
            'duration' => 480, // 8 hours
            'contract_sum' => 25000.00,
            'rig_fee_collected' => 5000.00,
            'materials_income' => 3000.00,
            'cash_received' => 20000.00,
            'materials_cost' => 2500.00,
            'workers' => [
                ['name' => 'Kwame Asante', 'role' => 'Driller', 'wage_type' => 'per_borehole', 'amount' => 1500.00],
                ['name' => 'Yaw Boateng', 'role' => 'Assistant', 'wage_type' => 'daily', 'amount' => 200.00],
                ['name' => 'Kofi Adjei', 'role' => 'Helper', 'wage_type' => 'daily', 'amount' => 150.00],
            ],
            'expenses' => [
                ['description' => 'Fuel for Rig', 'quantity' => 50, 'unit' => 'L', 'unit_cost' => 12.50, 'amount' => 625.00],
                ['description' => 'Transportation', 'quantity' => 1, 'unit' => 'trip', 'unit_cost' => 300.00, 'amount' => 300.00],
            ]
        ],
        [
            'client_name' => 'Golden Farms Enterprise',
            'contact_person' => 'Mary Darko',
            'contact_number' => '+233 20 987 6543',
            'email' => 'mary@goldenfarms.com',
            'site_name' => 'Golden Farms - Block A',
            'region' => 'Eastern Region',
            'job_type' => 'direct',
            'total_depth' => 92.0,
            'construction_depth' => 88.0,
            'rods_used' => 18,
            'rod_length' => 5.0,
            'screen_pipes' => 9,
            'plain_pipes' => 13,
            'gravel' => 16,
            'duration' => 540, // 9 hours
            'contract_sum' => 28000.00,
            'rig_fee_collected' => 5500.00,
            'materials_income' => 3500.00,
            'cash_received' => 22500.00,
            'materials_cost' => 2800.00,
            'workers' => [
                ['name' => 'Kwame Asante', 'role' => 'Driller', 'wage_type' => 'per_borehole', 'amount' => 1500.00],
                ['name' => 'Yaw Boateng', 'role' => 'Assistant', 'wage_type' => 'daily', 'amount' => 200.00],
                ['name' => 'Ama Serwaa', 'role' => 'Helper', 'wage_type' => 'daily', 'amount' => 150.00],
            ],
            'expenses' => [
                ['description' => 'Fuel for Rig', 'quantity' => 55, 'unit' => 'L', 'unit_cost' => 12.50, 'amount' => 687.50],
                ['description' => 'Lubricants', 'quantity' => 2, 'unit' => 'bottle', 'unit_cost' => 45.00, 'amount' => 90.00],
            ]
        ],
        [
            'client_name' => 'Tech Solutions GH',
            'contact_person' => 'David Osei',
            'contact_number' => '+233 26 555 1234',
            'email' => 'david.osei@techsolutions.gh',
            'site_name' => 'Tech Solutions Factory',
            'region' => 'Ashanti',
            'job_type' => 'subcontract',
            'total_depth' => 75.0,
            'construction_depth' => 70.0,
            'rods_used' => 15,
            'rod_length' => 5.0,
            'screen_pipes' => 7,
            'plain_pipes' => 10,
            'gravel' => 12,
            'duration' => 420, // 7 hours
            'contract_sum' => 0,
            'rig_fee_charged' => 4500.00,
            'rig_fee_collected' => 4500.00,
            'materials_income' => 0,
            'cash_received' => 0,
            'materials_cost' => 0,
            'workers' => [
                ['name' => 'Kwame Asante', 'role' => 'Driller', 'wage_type' => 'per_borehole', 'amount' => 1400.00],
                ['name' => 'Yaw Boateng', 'role' => 'Assistant', 'wage_type' => 'daily', 'amount' => 180.00],
            ],
            'expenses' => [
                ['description' => 'Fuel for Rig', 'quantity' => 45, 'unit' => 'L', 'unit_cost' => 12.50, 'amount' => 562.50],
            ]
        ],
        [
            'client_name' => 'Green Valley Resorts',
            'contact_person' => 'Sarah Appiah',
            'contact_number' => '+233 54 321 9876',
            'email' => 'sarah@appiahresorts.com',
            'site_name' => 'Green Valley - Main Building',
            'region' => 'Central Region',
            'job_type' => 'direct',
            'total_depth' => 98.5,
            'construction_depth' => 95.0,
            'rods_used' => 20,
            'rod_length' => 5.0,
            'screen_pipes' => 10,
            'plain_pipes' => 15,
            'gravel' => 18,
            'duration' => 600, // 10 hours
            'contract_sum' => 32000.00,
            'rig_fee_collected' => 6000.00,
            'materials_income' => 4000.00,
            'cash_received' => 26000.00,
            'materials_cost' => 3200.00,
            'workers' => [
                ['name' => 'Kwame Asante', 'role' => 'Driller', 'wage_type' => 'per_borehole', 'amount' => 1600.00],
                ['name' => 'Yaw Boateng', 'role' => 'Assistant', 'wage_type' => 'daily', 'amount' => 220.00],
                ['name' => 'Kofi Adjei', 'role' => 'Helper', 'wage_type' => 'daily', 'amount' => 150.00],
                ['name' => 'Ama Serwaa', 'role' => 'Helper', 'wage_type' => 'daily', 'amount' => 150.00],
            ],
            'expenses' => [
                ['description' => 'Fuel for Rig', 'quantity' => 60, 'unit' => 'L', 'unit_cost' => 12.50, 'amount' => 750.00],
                ['description' => 'Transportation', 'quantity' => 1, 'unit' => 'trip', 'unit_cost' => 350.00, 'amount' => 350.00],
                ['description' => 'Site Preparation', 'quantity' => 1, 'unit' => 'job', 'unit_cost' => 200.00, 'amount' => 200.00],
            ]
        ],
        [
            'client_name' => 'Mountain View Estates',
            'contact_person' => 'James Amoah',
            'contact_number' => '+233 27 777 8888',
            'email' => 'james.amoah@mountainview.com',
            'site_name' => 'Mountain View - Unit 5',
            'region' => 'Greater Accra',
            'job_type' => 'direct',
            'total_depth' => 88.0,
            'construction_depth' => 85.0,
            'rods_used' => 17,
            'rod_length' => 5.0,
            'screen_pipes' => 8,
            'plain_pipes' => 12,
            'gravel' => 15,
            'duration' => 510, // 8.5 hours
            'contract_sum' => 27000.00,
            'rig_fee_collected' => 5200.00,
            'materials_income' => 3200.00,
            'cash_received' => 22000.00,
            'materials_cost' => 2600.00,
            'workers' => [
                ['name' => 'Kwame Asante', 'role' => 'Driller', 'wage_type' => 'per_borehole', 'amount' => 1550.00],
                ['name' => 'Yaw Boateng', 'role' => 'Assistant', 'wage_type' => 'daily', 'amount' => 210.00],
                ['name' => 'Kofi Adjei', 'role' => 'Helper', 'wage_type' => 'daily', 'amount' => 150.00],
            ],
            'expenses' => [
                ['description' => 'Fuel for Rig', 'quantity' => 52, 'unit' => 'L', 'unit_cost' => 12.50, 'amount' => 650.00],
                ['description' => 'Drilling Bits Replacement', 'quantity' => 2, 'unit' => 'piece', 'unit_cost' => 180.00, 'amount' => 360.00],
            ]
        ],
    ];
    
    $dateBase = date('Y-m-d');
    $reportsCreated = 0;
    
    // Generate the requested number of reports by cycling through templates
    for ($reportIndex = 0; $reportIndex < $count; $reportIndex++) {
        // Select a template (cycle through available templates)
        $templateIndex = $reportIndex % count($dummyDataTemplates);
        // Deep copy the template array to avoid modifying the original
        $data = json_decode(json_encode($dummyDataTemplates[$templateIndex]), true);
        
        // Add variation to make each report unique
        $variationFactor = floor($reportIndex / count($dummyDataTemplates)) + 1;
        $depthVariation = rand(-5, 5) * $variationFactor;
        $data['total_depth'] = max(40, $data['total_depth'] + $depthVariation); // Minimum 40m
        $data['construction_depth'] = max(35, $data['construction_depth'] + rand(-3, 3) * $variationFactor);
        $contractVariation = rand(-2000, 2000) * $variationFactor;
        $data['contract_sum'] = max(20000, $data['contract_sum'] + $contractVariation);
        if ($variationFactor > 1) {
            $data['site_name'] = $data['site_name'] . ' - Unit ' . $variationFactor;
        }
        // Create or get client
        $clientStmt = $pdo->prepare("SELECT id FROM clients WHERE client_name = ?");
        $clientStmt->execute([$data['client_name']]);
        $client = $clientStmt->fetch();
        
        if (!$client) {
            $clientInsert = $pdo->prepare("INSERT INTO clients (client_name, contact_person, contact_number, email, created_at) VALUES (?, ?, ?, ?, NOW())");
            $clientInsert->execute([
                $data['client_name'],
                $data['contact_person'],
                $data['contact_number'],
                $data['email']
            ]);
            $clientId = $pdo->lastInsertId();
        } else {
            $clientId = $client['id'];
        }
        
        // Calculate dates (spread over last 30 days)
        $daysAgo = 30 - ($reportIndex * (30 / max($count, 1)));
        $reportDate = date('Y-m-d', strtotime("-$daysAgo days"));
        
        // Generate report ID
        $year = date('Y', strtotime($reportDate));
        $month = date('m', strtotime($reportDate));
        $seqStmt = $pdo->prepare("SELECT COUNT(*) FROM field_reports WHERE report_id LIKE ?");
        $seqStmt->execute([$rigCode . '-' . $year . '-' . $month . '-%']);
        $seq = $seqStmt->fetchColumn() + 1;
        $reportId = $rigCode . '-' . $year . '-' . $month . '-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
        
        // Calculate financial totals
        $totalWages = array_sum(array_column($data['workers'], 'amount'));
        $totalExpenses = array_sum(array_column($data['expenses'], 'amount')) + ($data['materials_cost'] ?? 0);
        $totalIncome = $data['contract_sum'] + $data['rig_fee_collected'] + $data['materials_income'] + $data['cash_received'];
        $netProfit = $totalIncome - $totalExpenses - $totalWages;
        $totalMoneyBanked = $totalIncome * 0.8; // Assume 80% banked
        $daysBalance = $totalIncome - $totalMoneyBanked;
        
        // Calculate duration in minutes
        $startTime = '07:00:00';
        $finishTimeHours = ($data['duration'] / 60) + 7;
        $finishTime = sprintf('%02d:00:00', floor($finishTimeHours));
        
        // Insert field report - exact copy from save-report.php structure
        $reportStmt = $pdo->prepare("INSERT INTO field_reports (
            report_id, report_date, rig_id, job_type, site_name, plus_code, latitude, longitude,
            location_description, region, client_id, client_contact, start_time, finish_time,
            total_duration, start_rpm, finish_rpm, total_rpm, rod_length, rods_used, total_depth,
            screen_pipes_used, plain_pipes_used, gravel_used, construction_depth, materials_provided_by,
            supervisor, total_workers, remarks, incident_log, solution_log, recommendation_log,
            balance_bf, contract_sum, rig_fee_charged, rig_fee_collected, cash_received, materials_income,
            materials_cost, momo_transfer, cash_given, bank_deposit, total_income, total_expenses,
            total_wages, net_profit, total_money_banked, days_balance, outstanding_rig_fee, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $reportStmt->execute([
            $reportId, $reportDate, $rigId, $data['job_type'], $data['site_name'],
            null, null, null, 'Sample location description',
            $data['region'], $clientId, $data['contact_number'] ?? '', $startTime,
            $finishTime, $data['duration'], null, null,
            null, $data['rod_length'], $data['rods_used'], $data['total_depth'],
            $data['screen_pipes'] ?? 0, $data['plain_pipes'] ?? 0, $data['gravel'] ?? 0,
            $data['construction_depth'] ?? 0, 'client', 'Supervisor Name',
            count($data['workers']), 'Sample drilling operation completed successfully.', '', '', '',
            0, $data['contract_sum'] ?? 0,
            $data['rig_fee_charged'] ?? 0, $data['rig_fee_collected'] ?? 0, $data['cash_received'] ?? 0,
            $data['materials_income'] ?? 0, $data['materials_cost'] ?? 0, 0,
            0, 0, $totalIncome, $totalExpenses,
            $totalWages, $netProfit, $totalMoneyBanked, $daysBalance,
            0, $userId
        ]);
        
        $reportInsertId = $pdo->lastInsertId();
        $createdReports[] = $reportId;
        
        // Insert payroll entries
        foreach ($data['workers'] as $worker) {
            $payrollStmt = $pdo->prepare("INSERT INTO payroll_entries (report_id, worker_name, role, wage_type, units, pay_per_unit, amount, paid_today, notes) VALUES (?, ?, ?, ?, 1, ?, ?, 1, 'Test data')");
            $payrollStmt->execute([
                $reportInsertId, $worker['name'], $worker['role'], $worker['wage_type'],
                $worker['amount'], $worker['amount']
            ]);
        }
        
        // Insert expense entries (check if table exists)
        try {
            foreach ($data['expenses'] as $expense) {
                $expenseStmt = $pdo->prepare("INSERT INTO expense_entries (report_id, description, unit_cost, quantity, amount) VALUES (?, ?, ?, ?, ?)");
                $expenseStmt->execute([
                    $reportInsertId, $expense['description'], $expense['unit_cost'],
                    $expense['quantity'], $expense['amount']
                ]);
            }
        } catch (Throwable $e) {
            // Table may not exist, skip
        }
        
        // Insert field_report_items (for catalog-linked items in receipts)
        try {
            foreach ($data['expenses'] as $expense) {
                $friStmt = $pdo->prepare("INSERT INTO field_report_items (report_id, catalog_item_id, description, quantity, unit, unit_price, total_amount, item_type) VALUES (?, NULL, ?, ?, ?, ?, ?, 'expense')");
                $friStmt->execute([
                    $reportInsertId, $expense['description'], $expense['quantity'],
                    $expense['unit'] ?? 'unit', $expense['unit_cost'], $expense['amount']
                ]);
            }
        } catch (Throwable $e) {
            // Table may not exist, skip
        }
        
        $reportsCreated++;
    }
    
    $pdo->commit();
    
    jsonResponse([
        'success' => true,
        'message' => "Successfully created {$reportsCreated} dummy field reports",
        'reports' => $createdReports,
        'count' => $reportsCreated
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Insert dummy reports error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'Error creating dummy reports: ' . $e->getMessage()
    ], 500);
}
?>
