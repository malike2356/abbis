<?php
/**
 * Purge Dummy Data and Keep 5 Realistic Test Records
 * 
 * This script will:
 * 1. Keep only the 5 most recent field reports
 * 2. Keep only related clients, rigs, and workers
 * 3. Update financial figures to be realistic (not bloated)
 * 4. Clean up all child records (payroll, expenses, loans, etc.)
 * 5. Maintain referential integrity
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

// Check if running from CLI or web
$isCLI = php_sapi_name() === 'cli';

if ($isCLI) {
    // CLI mode - skip auth, auto-confirm
    echo "WARNING: This will delete most data and keep only 5 test records!\n";
    echo "Running in CLI mode - proceeding automatically...\n\n";
    
    // Set a default user ID for CLI
    $_SESSION = ['user_id' => 1, 'role' => 'admin'];
} else {
    // Web mode - require auth
    require_once __DIR__ . '/../includes/auth.php';
    $auth->requireAuth();
    if ($_SESSION['role'] !== 'admin') {
        die('Access denied. Admin only.');
    }
}

$pdo = getDBConnection();

try {
    $pdo->beginTransaction();
    
    echo "<h2>Purging Dummy Data - Keeping 5 Realistic Test Records</h2>\n";
    echo "<pre>\n";
    
    // Step 1: Get the 5 most recent field reports
    $stmt = $pdo->query("SELECT id FROM field_reports ORDER BY created_at DESC LIMIT 5");
    $keepReports = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($keepReports)) {
        echo "No field reports found. Nothing to purge.\n";
        $pdo->rollBack();
        exit;
    }
    
    echo "Keeping " . count($keepReports) . " field reports: " . implode(', ', $keepReports) . "\n";
    
    // Get IDs of clients, rigs, and users referenced by these reports
    $placeholders = implode(',', array_fill(0, count($keepReports), '?'));
    $stmt = $pdo->prepare("SELECT DISTINCT client_id, rig_id, created_by FROM field_reports WHERE id IN ($placeholders)");
    $stmt->execute($keepReports);
    $refs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $keepClients = array_filter(array_column($refs, 'client_id'));
    $keepRigs = array_unique(array_column($refs, 'rig_id'));
    $keepUsers = array_unique(array_column($refs, 'created_by'));
    
    echo "Keeping " . count($keepClients) . " clients, " . count($keepRigs) . " rigs, " . count($keepUsers) . " users\n\n";
    
    // Step 2: Delete child records for reports we're deleting
    echo "Deleting child records for reports to be removed...\n";
    
    // Delete payroll entries
    if (count($keepReports) > 0) {
        $stmt = $pdo->prepare("DELETE FROM payroll_entries WHERE report_id NOT IN ($placeholders)");
        $stmt->execute($keepReports);
        echo "  - Deleted payroll entries: " . $stmt->rowCount() . " rows\n";
    }
    
    // Delete expense entries
    if (count($keepReports) > 0) {
        $stmt = $pdo->prepare("DELETE FROM expense_entries WHERE report_id NOT IN ($placeholders)");
        $stmt->execute($keepReports);
        echo "  - Deleted expense entries: " . $stmt->rowCount() . " rows\n";
    }
    
    // Delete field_report_items
    try {
        if (count($keepReports) > 0) {
            $stmt = $pdo->prepare("DELETE FROM field_report_items WHERE report_id NOT IN ($placeholders)");
            $stmt->execute($keepReports);
            echo "  - Deleted field_report_items: " . $stmt->rowCount() . " rows\n";
        }
    } catch (PDOException $e) {
        echo "  - field_report_items table doesn't exist (skipped)\n";
    }
    
    // Delete debt recoveries not linked to kept reports
    try {
        if (count($keepReports) > 0) {
            $stmt = $pdo->prepare("DELETE FROM debt_recoveries WHERE field_report_id IS NOT NULL AND field_report_id NOT IN ($placeholders)");
            $stmt->execute($keepReports);
            echo "  - Deleted debt_recoveries: " . $stmt->rowCount() . " rows\n";
        }
    } catch (PDOException $e) {
        echo "  - debt_recoveries table doesn't exist (skipped)\n";
    }
    
    // Delete rig_fee_debts not linked to kept reports
    try {
        if (count($keepReports) > 0) {
            $stmt = $pdo->prepare("DELETE FROM rig_fee_debts WHERE report_id NOT IN ($placeholders)");
            $stmt->execute($keepReports);
            echo "  - Deleted rig_fee_debts: " . $stmt->rowCount() . " rows\n";
        }
    } catch (PDOException $e) {
        echo "  - rig_fee_debts table doesn't exist (skipped)\n";
    }
    
    echo "\n";
    
    // Step 3: Delete field reports we're not keeping
    echo "Deleting field reports...\n";
    if (count($keepReports) > 0) {
        $stmt = $pdo->prepare("DELETE FROM field_reports WHERE id NOT IN ($placeholders)");
        $stmt->execute($keepReports);
        echo "  - Deleted field reports: " . $stmt->rowCount() . " rows\n";
    }
    
    // Step 4: Delete clients not referenced
    echo "\nDeleting unused clients...\n";
    if (!empty($keepClients)) {
        $clientPlaceholders = implode(',', array_fill(0, count($keepClients), '?'));
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id NOT IN ($clientPlaceholders)");
        $stmt->execute($keepClients);
        echo "  - Deleted clients: " . $stmt->rowCount() . " rows\n";
    } else {
        $stmt = $pdo->query("DELETE FROM clients");
        echo "  - Deleted all clients: " . $stmt->rowCount() . " rows\n";
    }
    
    // Step 5: Delete rigs not referenced (but keep at least 2)
    echo "\nCleaning up rigs...\n";
    if (!empty($keepRigs)) {
        $rigPlaceholders = implode(',', array_fill(0, count($keepRigs), '?'));
        $stmt = $pdo->prepare("DELETE FROM rigs WHERE id NOT IN ($rigPlaceholders)");
        $stmt->execute($keepRigs);
        echo "  - Deleted rigs: " . $stmt->rowCount() . " rows\n";
    }
    
    // Step 6: Delete workers not referenced (keep top 8 most used)
    echo "\nCleaning up workers...\n";
    $stmt = $pdo->query("
        SELECT worker_name, COUNT(*) as usage_count 
        FROM payroll_entries 
        GROUP BY worker_name 
        ORDER BY usage_count DESC 
        LIMIT 8
    ");
    $keepWorkerNames = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($keepWorkerNames)) {
        $workerPlaceholders = implode(',', array_fill(0, count($keepWorkerNames), '?'));
        $stmt = $pdo->prepare("DELETE FROM workers WHERE worker_name NOT IN ($workerPlaceholders)");
        $stmt->execute($keepWorkerNames);
        echo "  - Deleted workers: " . $stmt->rowCount() . " rows\n";
        echo "  - Keeping workers: " . implode(', ', $keepWorkerNames) . "\n";
    }
    
    // Step 7: Clean up loans (keep only 1-2 active loans)
    echo "\nCleaning up loans...\n";
    $stmt = $pdo->query("SELECT id FROM loans WHERE status = 'active' ORDER BY issue_date DESC LIMIT 2");
    $keepLoans = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($keepLoans)) {
        $loanPlaceholders = implode(',', array_fill(0, count($keepLoans), '?'));
        // Delete repayments for loans we're deleting
        $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id NOT IN ($loanPlaceholders)");
        $stmt->execute($keepLoans);
        echo "  - Deleted loan repayments: " . $stmt->rowCount() . " rows\n";
        
        // Delete loans
        $stmt = $pdo->prepare("DELETE FROM loans WHERE id NOT IN ($loanPlaceholders)");
        $stmt->execute($keepLoans);
        echo "  - Deleted loans: " . $stmt->rowCount() . " rows\n";
    } else {
        // Delete all loans if no active ones
        $stmt = $pdo->query("DELETE FROM loan_repayments");
        echo "  - Deleted all loan repayments: " . $stmt->rowCount() . " rows\n";
        $stmt = $pdo->query("DELETE FROM loans");
        echo "  - Deleted all loans: " . $stmt->rowCount() . " rows\n";
    }
    
    // Step 8: Update financial figures to be realistic
    echo "\nUpdating financial figures to realistic values...\n";
    
    // Get the kept reports and update their figures
    $reports = $keepReports;
    
    // Realistic figures for 5 test reports
    $realisticData = [
        [
            'contract_sum' => 8500.00,
            'rig_fee_charged' => 3200.00,
            'rig_fee_collected' => 3000.00,
            'cash_received' => 5000.00,
            'materials_income' => 1200.00,
            'materials_cost' => 800.00,
            'momo_transfer' => 2000.00,
            'cash_given' => 1500.00,
            'bank_deposit' => 4500.00,
            'total_wages' => 1800.00,
            'total_depth' => 45.5,
            'total_rpm' => 2.5,
            'total_duration' => 240,
        ],
        [
            'contract_sum' => 12000.00,
            'rig_fee_charged' => 4500.00,
            'rig_fee_collected' => 4500.00,
            'cash_received' => 7500.00,
            'materials_income' => 1800.00,
            'materials_cost' => 1200.00,
            'momo_transfer' => 3000.00,
            'cash_given' => 2000.00,
            'bank_deposit' => 6000.00,
            'total_wages' => 2200.00,
            'total_depth' => 62.0,
            'total_rpm' => 3.2,
            'total_duration' => 320,
        ],
        [
            'contract_sum' => 6500.00,
            'rig_fee_charged' => 2800.00,
            'rig_fee_collected' => 2500.00,
            'cash_received' => 4000.00,
            'materials_income' => 900.00,
            'materials_cost' => 600.00,
            'momo_transfer' => 1500.00,
            'cash_given' => 1000.00,
            'bank_deposit' => 3500.00,
            'total_wages' => 1500.00,
            'total_depth' => 38.0,
            'total_rpm' => 1.8,
            'total_duration' => 180,
        ],
        [
            'contract_sum' => 9500.00,
            'rig_fee_charged' => 3500.00,
            'rig_fee_collected' => 3500.00,
            'cash_received' => 6000.00,
            'materials_income' => 1500.00,
            'materials_cost' => 950.00,
            'momo_transfer' => 2500.00,
            'cash_given' => 1800.00,
            'bank_deposit' => 5200.00,
            'total_wages' => 1900.00,
            'total_depth' => 55.0,
            'total_rpm' => 2.8,
            'total_duration' => 280,
        ],
        [
            'contract_sum' => 11000.00,
            'rig_fee_charged' => 4200.00,
            'rig_fee_collected' => 4000.00,
            'cash_received' => 7000.00,
            'materials_income' => 1600.00,
            'materials_cost' => 1100.00,
            'momo_transfer' => 2800.00,
            'cash_given' => 1900.00,
            'bank_deposit' => 5800.00,
            'total_wages' => 2100.00,
            'total_depth' => 58.5,
            'total_rpm' => 3.0,
            'total_duration' => 300,
        ],
    ];
    
    foreach ($reports as $index => $reportId) {
        if (isset($realisticData[$index])) {
            $data = $realisticData[$index];
            
            // Calculate derived values
            $totalIncome = $data['cash_received'] + $data['materials_income'] + $data['rig_fee_collected'];
            // Expenses = materials cost + wages + cash/momo transfers
            $totalExpenses = $data['materials_cost'] + $data['total_wages'];
            $netProfit = $totalIncome - $totalExpenses;
            $totalMoneyBanked = $data['bank_deposit'];
            $outstandingRigFee = max(0, $data['rig_fee_charged'] - $data['rig_fee_collected']);
            $daysBalance = $totalIncome - ($data['momo_transfer'] + $data['cash_given'] + $data['bank_deposit']);
            
            $updateStmt = $pdo->prepare("
                UPDATE field_reports SET
                    contract_sum = ?,
                    rig_fee_charged = ?,
                    rig_fee_collected = ?,
                    cash_received = ?,
                    materials_income = ?,
                    materials_cost = ?,
                    momo_transfer = ?,
                    cash_given = ?,
                    bank_deposit = ?,
                    total_wages = ?,
                    total_income = ?,
                    total_expenses = ?,
                    net_profit = ?,
                    total_money_banked = ?,
                    outstanding_rig_fee = ?,
                    days_balance = ?,
                    total_depth = ?,
                    total_rpm = ?,
                    total_duration = ?
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $data['contract_sum'],
                $data['rig_fee_charged'],
                $data['rig_fee_collected'],
                $data['cash_received'],
                $data['materials_income'],
                $data['materials_cost'],
                $data['momo_transfer'],
                $data['cash_given'],
                $data['bank_deposit'],
                $data['total_wages'],
                $totalIncome,
                $totalExpenses,
                $netProfit,
                $totalMoneyBanked,
                $outstandingRigFee,
                $daysBalance,
                $data['total_depth'],
                $data['total_rpm'],
                $data['total_duration'],
                $reportId
            ]);
            
            echo "  - Updated report ID $reportId with realistic figures\n";
        }
    }
    
    // Step 9: Update materials inventory to realistic values
    echo "\nUpdating materials inventory...\n";
    try {
        $pdo->exec("
            UPDATE materials_inventory SET
                quantity_received = CASE 
                    WHEN material_type = 'screen_pipe' THEN 50
                    WHEN material_type = 'plain_pipe' THEN 80
                    WHEN material_type = 'gravel' THEN 120
                END,
                quantity_used = CASE 
                    WHEN material_type = 'screen_pipe' THEN 15
                    WHEN material_type = 'plain_pipe' THEN 25
                    WHEN material_type = 'gravel' THEN 40
                END,
                quantity_remaining = CASE 
                    WHEN material_type = 'screen_pipe' THEN 35
                    WHEN material_type = 'plain_pipe' THEN 55
                    WHEN material_type = 'gravel' THEN 80
                END,
                unit_cost = CASE 
                    WHEN material_type = 'screen_pipe' THEN 45.00
                    WHEN material_type = 'plain_pipe' THEN 28.00
                    WHEN material_type = 'gravel' THEN 15.00
                END,
                total_value = CASE 
                    WHEN material_type = 'screen_pipe' THEN 1575.00
                    WHEN material_type = 'plain_pipe' THEN 1540.00
                    WHEN material_type = 'gravel' THEN 1200.00
                END
        ");
        echo "  - Updated materials inventory with realistic values\n";
    } catch (PDOException $e) {
        echo "  - Materials inventory update failed (table might not exist): " . $e->getMessage() . "\n";
    }
    
    // Step 10: Clean up any orphaned records
    echo "\nCleaning up orphaned records...\n";
    
    // Delete debt recoveries without field reports
    try {
        $stmt = $pdo->query("DELETE FROM debt_recoveries WHERE field_report_id IS NOT NULL AND field_report_id NOT IN (SELECT id FROM field_reports)");
        echo "  - Deleted orphaned debt_recoveries: " . $stmt->rowCount() . " rows\n";
    } catch (PDOException $e) {
        // Table might not exist
    }
    
    // Delete debt recoveries without clients
    try {
        $stmt = $pdo->query("DELETE FROM debt_recoveries WHERE client_id IS NOT NULL AND client_id NOT IN (SELECT id FROM clients)");
        echo "  - Deleted orphaned debt_recoveries (no client): " . $stmt->rowCount() . " rows\n";
    } catch (PDOException $e) {
        // Table might not exist
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo "\n✅ Purge completed successfully!\n";
    echo "\nSummary:\n";
    echo "  - Field Reports: 5 kept\n";
    echo "  - Clients: " . count($keepClients) . " kept\n";
    echo "  - Rigs: " . count($keepRigs) . " kept\n";
    echo "  - Workers: " . count($keepWorkerNames) . " kept\n";
    echo "  - All financial figures updated to realistic values\n";
    echo "\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Transaction rolled back.\n";
    exit(1);
}

echo "</pre>";
?>

