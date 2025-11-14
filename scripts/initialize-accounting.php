<?php
/**
 * Initialize Accounting System
 * 
 * This script:
 * 1. Creates the chart of accounts if they don't exist
 * 2. Retroactively processes existing financial data to create journal entries
 * 3. Ensures the accounting system is populated with data from ABBIS
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/AccountingAutoTracker.php';

$pdo = getDBConnection();

echo "Initializing Accounting System...\n\n";

// Step 1: Initialize AccountingAutoTracker (this creates default accounts)
echo "Step 1: Creating chart of accounts...\n";
try {
    $tracker = new AccountingAutoTracker($pdo);
    echo "✓ Chart of accounts initialized\n";
} catch (Exception $e) {
    echo "✗ Error initializing accounts: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Count accounts created
$accountCount = $pdo->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn();
echo "  → Created {$accountCount} accounts\n\n";

// Step 3: Retroactively process field reports
echo "Step 2: Processing existing field reports...\n";
try {
    $stmt = $pdo->query("
        SELECT fr.*, c.client_name 
        FROM field_reports fr 
        LEFT JOIN clients c ON fr.client_id = c.id 
        ORDER BY fr.id
    ");
    $reports = $stmt->fetchAll();
    
    $processed = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($reports as $report) {
        // Check if journal entry already exists for this report
        $existingStmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference = ?");
        $existingStmt->execute([$report['report_id']]);
        if ($existingStmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        
        // Prepare report data for tracking
        $reportData = [
            'report_id' => $report['report_id'],
            'report_date' => $report['report_date'],
            'site_name' => $report['site_name'] ?? '',
            'client_name' => $report['client_name'] ?? '',
            'contract_sum' => $report['contract_sum'] ?? 0,
            'rig_fee_charged' => $report['rig_fee_charged'] ?? 0,
            'rig_fee_collected' => $report['rig_fee_collected'] ?? 0,
            'cash_received' => $report['cash_received'] ?? 0,
            'materials_income' => $report['materials_income'] ?? 0,
            'materials_cost' => $report['materials_cost'] ?? 0,
            'total_wages' => $report['total_wages'] ?? 0,
            'total_expenses' => $report['total_expenses'] ?? 0,
            'momo_transfer' => $report['momo_transfer'] ?? 0,
            'cash_given' => $report['cash_given'] ?? 0,
            'bank_deposit' => $report['bank_deposit'] ?? 0,
            'created_by' => $report['created_by'] ?? null
        ];
        
        // Track the field report
        $result = $tracker->trackFieldReport($report['id'], $reportData);
        if ($result) {
            $processed++;
            echo "  ✓ Processed report: {$report['report_id']}\n";
        } else {
            $errors++;
            echo "  ✗ Error processing report: {$report['report_id']}\n";
        }
    }
    
    echo "\n  → Processed: {$processed}\n";
    echo "  → Skipped (already processed): {$skipped}\n";
    echo "  → Errors: {$errors}\n\n";
    
} catch (Exception $e) {
    echo "✗ Error processing field reports: " . $e->getMessage() . "\n";
}

// Step 4: Process existing loans
echo "Step 3: Processing existing loans...\n";
try {
    $stmt = $pdo->query("
        SELECT l.*, w.worker_name 
        FROM loans l 
        LEFT JOIN workers w ON l.worker_id = w.id 
        ORDER BY l.id
    ");
    $loans = $stmt->fetchAll();
    
    $processed = 0;
    $skipped = 0;
    
    foreach ($loans as $loan) {
        // Check if journal entry already exists
        $existingStmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference = ?");
        $existingStmt->execute(['LOAN-' . $loan['id']]);
        if ($existingStmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        
        $loanData = [
            'loan_amount' => $loan['loan_amount'] ?? 0,
            'issue_date' => $loan['issue_date'] ?? date('Y-m-d'),
            'worker_name' => $loan['worker_name'] ?? '',
            'created_by' => $loan['created_by'] ?? null
        ];
        
        $result = $tracker->trackLoanDisbursement($loan['id'], $loanData);
        if ($result) {
            $processed++;
        }
    }
    
    echo "  → Processed: {$processed} loans\n";
    echo "  → Skipped: {$skipped}\n\n";
    
} catch (Exception $e) {
    echo "✗ Error processing loans: " . $e->getMessage() . "\n";
}

// Step 5: Process existing payroll payments
echo "Step 4: Processing existing payroll payments...\n";
try {
    $stmt = $pdo->query("
        SELECT pe.*, fr.report_date 
        FROM payroll_entries pe 
        LEFT JOIN field_reports fr ON pe.report_id = fr.id 
        WHERE pe.paid_today = 1
        ORDER BY pe.id
    ");
    $payrolls = $stmt->fetchAll();
    
    $processed = 0;
    $skipped = 0;
    
    foreach ($payrolls as $payroll) {
        // Check if journal entry already exists
        $existingStmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference = ?");
        $existingStmt->execute(['PAY-' . $payroll['id']]);
        if ($existingStmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        
        $payrollData = [
            'worker_name' => $payroll['worker_name'] ?? '',
            'amount' => $payroll['amount'] ?? 0,
            'payment_date' => $payroll['report_date'] ?? date('Y-m-d'),
            'created_by' => $payroll['created_by'] ?? null
        ];
        
        $result = $tracker->trackPayrollPayment($payroll['id'], $payrollData);
        if ($result) {
            $processed++;
        }
    }
    
    echo "  → Processed: {$processed} payroll payments\n";
    echo "  → Skipped: {$skipped}\n\n";
    
} catch (Exception $e) {
    echo "✗ Error processing payroll: " . $e->getMessage() . "\n";
}

// Step 6: Process existing materials purchases
echo "Step 5: Processing existing materials purchases...\n";
try {
    $stmt = $pdo->query("
        SELECT * FROM materials_transactions 
        WHERE transaction_type = 'purchase' 
        ORDER BY id
    ");
    $transactions = $stmt->fetchAll();
    
    $processed = 0;
    $skipped = 0;
    
    foreach ($transactions as $transaction) {
        // Check if journal entry already exists
        $existingStmt = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference = ?");
        $existingStmt->execute(['MAT-' . $transaction['id']]);
        if ($existingStmt->fetchColumn() > 0) {
            $skipped++;
            continue;
        }
        
        $transactionData = [
            'total_cost' => $transaction['total_cost'] ?? 0,
            'transaction_date' => $transaction['transaction_date'] ?? date('Y-m-d'),
            'description' => $transaction['description'] ?? 'Materials purchase',
            'created_by' => $transaction['created_by'] ?? null
        ];
        
        $result = $tracker->trackMaterialsPurchase($transaction['id'], $transactionData);
        if ($result) {
            $processed++;
        }
    }
    
    echo "  → Processed: {$processed} material purchases\n";
    echo "  → Skipped: {$skipped}\n\n";
    
} catch (Exception $e) {
    echo "✗ Error processing materials: " . $e->getMessage() . "\n";
}

// Step 7: Show final statistics
echo "Step 6: Final Statistics\n";
try {
    $accountCount = $pdo->query("SELECT COUNT(*) FROM chart_of_accounts")->fetchColumn();
    $entryCount = $pdo->query("SELECT COUNT(*) FROM journal_entries")->fetchColumn();
    $totalDebits = $pdo->query("SELECT COALESCE(SUM(debit), 0) FROM journal_entry_lines")->fetchColumn();
    $totalCredits = $pdo->query("SELECT COALESCE(SUM(credit), 0) FROM journal_entry_lines")->fetchColumn();
    
    echo "  → Accounts: {$accountCount}\n";
    echo "  → Journal Entries: {$entryCount}\n";
    echo "  → Total Debits: GHS " . number_format($totalDebits, 2) . "\n";
    echo "  → Total Credits: GHS " . number_format($totalCredits, 2) . "\n";
    
    if (abs($totalDebits - $totalCredits) > 0.01) {
        echo "\n⚠ WARNING: Debits and Credits don't balance!\n";
        echo "  Difference: GHS " . number_format(abs($totalDebits - $totalCredits), 2) . "\n";
    } else {
        echo "\n✓ Accounting system is balanced!\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error getting statistics: " . $e->getMessage() . "\n";
}

echo "\n✓ Accounting system initialization complete!\n";


