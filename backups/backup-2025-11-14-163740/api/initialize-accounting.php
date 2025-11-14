<?php
/**
 * Initialize Accounting System
 * Backfills all existing financial data into the accounting system
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/migration-helpers.php';
require_once __DIR__ . '/../includes/AccountingAutoTracker.php';

header('Content-Type: application/json');

$auth->requireAuth();
$auth->requireRole(ROLE_ADMIN);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
}

// Validate CSRF token
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'message' => 'Invalid security token'], 403);
}

try {
    $pdo = getDBConnection();
    $tracker = new AccountingAutoTracker($pdo);
    
    $results = [
        'field_reports' => ['processed' => 0, 'skipped' => 0, 'errors' => []],
        'loans' => ['processed' => 0, 'skipped' => 0, 'errors' => []],
        'materials' => ['processed' => 0, 'skipped' => 0, 'errors' => []],
        'payroll' => ['processed' => 0, 'skipped' => 0, 'errors' => []]
    ];

    // Detect available loan tables (legacy/new)
    $loanTable = null;
    $loanRepaymentTable = null;
    if (tableExists($pdo, 'worker_loans')) {
        $loanTable = 'worker_loans';
        if (tableExists($pdo, 'loan_repayments')) {
            $loanRepaymentTable = 'loan_repayments';
        } elseif (tableExists($pdo, 'worker_loan_repayments')) {
            $loanRepaymentTable = 'worker_loan_repayments';
        }
    } elseif (tableExists($pdo, 'loans')) {
        $loanTable = 'loans';
        if (tableExists($pdo, 'loan_repayments')) {
            $loanRepaymentTable = 'loan_repayments';
        }
    }
    
    // Process existing field reports
    try {
        $reports = $pdo->query("SELECT fr.*, c.client_name 
                                FROM field_reports fr 
                                LEFT JOIN clients c ON fr.client_id = c.id 
                                ORDER BY fr.report_date ASC, fr.id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reports as $report) {
            // Check if journal entry already exists for this report (check by report_id)
            $checkStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference = ? LIMIT 1");
            $checkStmt->execute([$report['report_id']]);
            if ($checkStmt->fetch()) {
                $results['field_reports']['skipped']++;
                continue;
            }
            
            try {
                // Calculate totals (similar to save-report.php)
                $totals = [
                    'total_wages' => floatval($report['total_wages'] ?? 0),
                    'total_expenses' => floatval($report['total_expenses'] ?? 0),
                    'outstanding_rig_fee' => floatval($report['outstanding_rig_fee'] ?? 0)
                ];
                
                $reportData = [
                    'report_id' => $report['report_id'],
                    'report_date' => $report['report_date'],
                    'site_name' => $report['site_name'] ?? '',
                    'client_name' => $report['client_name'] ?? '',
                    'created_by' => $report['created_by'] ?? 1,
                    'contract_sum' => floatval($report['contract_sum'] ?? 0),
                    'rig_fee_charged' => floatval($report['rig_fee_charged'] ?? 0),
                    'rig_fee_collected' => floatval($report['rig_fee_collected'] ?? 0),
                    'cash_received' => floatval($report['cash_received'] ?? 0),
                    'materials_income' => floatval($report['materials_income'] ?? 0),
                    'materials_cost' => floatval($report['materials_cost'] ?? 0),
                    'momo_transfer' => floatval($report['momo_transfer'] ?? 0),
                    'cash_given' => floatval($report['cash_given'] ?? 0),
                    'bank_deposit' => floatval($report['bank_deposit'] ?? 0),
                    'total_wages' => $totals['total_wages'],
                    'total_expenses' => $totals['total_expenses'],
                    'outstanding_rig_fee' => $totals['outstanding_rig_fee']
                ];
                
                if ($tracker->trackFieldReport($report['id'], $reportData)) {
                    $results['field_reports']['processed']++;
                } else {
                    $results['field_reports']['skipped']++;
                }
            } catch (Exception $e) {
                $results['field_reports']['errors'][] = "Report {$report['report_id']}: " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $results['field_reports']['errors'][] = "Error processing reports: " . $e->getMessage();
    }
    
    // Process existing loans
    if ($loanTable) {
        try {
            $loans = $pdo->query("SELECT * FROM `{$loanTable}` ORDER BY issue_date ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($loans as $loan) {
                // Check if already processed
                $checkStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference = ? LIMIT 1");
                $checkStmt->execute(['LOAN-' . $loan['id']]);
                if ($checkStmt->fetch()) {
                    $results['loans']['skipped']++;
                    continue;
                }
                
                try {
                    $workerName = $loan['worker_name'] ?? '';
                    if (!$workerName && isset($loan['worker_id'])) {
                        $workerLookup = $pdo->prepare("SELECT worker_name FROM workers WHERE id = ?");
                        $workerLookup->execute([$loan['worker_id']]);
                        $worker = $workerLookup->fetch(PDO::FETCH_ASSOC);
                        $workerName = $worker['worker_name'] ?? '';
                    }
                    
                    $loanData = [
                        'worker_name' => $workerName,
                        'loan_amount' => floatval($loan['loan_amount'] ?? 0),
                        'issue_date' => $loan['issue_date'] ?? date('Y-m-d'),
                        'created_by' => $loan['created_by'] ?? 1
                    ];
                    
                    if ($tracker->trackLoanDisbursement($loan['id'], $loanData)) {
                        $results['loans']['processed']++;
                    } else {
                        $results['loans']['skipped']++;
                    }
                } catch (Exception $e) {
                    $results['loans']['errors'][] = "Loan ID {$loan['id']}: " . $e->getMessage();
                }
            }
            
            // Process loan repayments if table available
            if ($loanRepaymentTable) {
                $repayments = $pdo->query("SELECT lr.*, wl.worker_name 
                                           FROM `{$loanRepaymentTable}` lr 
                                           JOIN `{$loanTable}` wl ON lr.loan_id = wl.id 
                                           ORDER BY lr.repayment_date ASC, lr.id ASC")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($repayments as $repayment) {
                    $checkStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference = ? LIMIT 1");
                    $checkStmt->execute(['REPAY-' . $repayment['id']]);
                    if ($checkStmt->fetch()) {
                        continue; // Already processed
                    }
                    
                    try {
                        $repaymentData = [
                            'worker_name' => $repayment['worker_name'] ?? '',
                            'repayment_amount' => floatval($repayment['repayment_amount'] ?? 0),
                            'repayment_date' => $repayment['repayment_date'] ?? date('Y-m-d'),
                            'created_by' => $repayment['created_by'] ?? 1
                        ];
                        
                        if ($tracker->trackLoanRepayment($repayment['id'], $repaymentData)) {
                            $results['loans']['processed']++;
                        }
                    } catch (Exception $e) {
                        $results['loans']['errors'][] = "Repayment ID {$repayment['id']}: " . $e->getMessage();
                    }
                }
            } else {
                $results['loans']['errors'][] = "Loan repayment table not found (checked for `loan_repayments` and `worker_loan_repayments`).";
            }
        } catch (Exception $e) {
            $results['loans']['errors'][] = "Error processing loans: " . $e->getMessage();
        }
    } else {
        $results['loans']['errors'][] = "Loan tables not found (expected `worker_loans` or `loans`).";
    }
    
    // Process existing materials purchases
    try {
        $materials = $pdo->query("SELECT * FROM materials_transactions 
                                 WHERE transaction_type = 'purchase' AND quantity_received > 0 
                                 ORDER BY transaction_date ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($materials as $material) {
            $checkStmt = $pdo->prepare("SELECT id FROM journal_entries WHERE reference = ? LIMIT 1");
            $checkStmt->execute(['MATERIAL-' . $material['id']]);
            if ($checkStmt->fetch()) {
                $results['materials']['skipped']++;
                continue;
            }
            
            try {
                $materialData = [
                    'description' => "Purchase: " . ($material['material_type'] ?? 'Materials'),
                    'total_cost' => floatval($material['quantity_received'] ?? 0) * floatval($material['unit_cost'] ?? 0),
                    'unit_cost' => floatval($material['unit_cost'] ?? 0),
                    'quantity' => floatval($material['quantity_received'] ?? 0),
                    'transaction_date' => $material['transaction_date'] ?? date('Y-m-d'),
                    'created_by' => $material['created_by'] ?? 1
                ];
                
                if ($tracker->trackMaterialsPurchase($material['id'], $materialData)) {
                    $results['materials']['processed']++;
                } else {
                    $results['materials']['skipped']++;
                }
            } catch (Exception $e) {
                $results['materials']['errors'][] = "Material ID {$material['id']}: " . $e->getMessage();
            }
        }
    } catch (Exception $e) {
        $results['materials']['errors'][] = "Error processing materials: " . $e->getMessage();
    }
    
    $totalProcessed = $results['field_reports']['processed'] + $results['loans']['processed'] + 
                     $results['materials']['processed'] + $results['payroll']['processed'];
    $totalSkipped = $results['field_reports']['skipped'] + $results['loans']['skipped'] + 
                   $results['materials']['skipped'] + $results['payroll']['skipped'];
    
    jsonResponse([
        'success' => true,
        'message' => "Initialization complete! Processed {$totalProcessed} transaction(s), skipped {$totalSkipped} duplicate(s).",
        'results' => $results,
        'summary' => [
            'total_processed' => $totalProcessed,
            'total_skipped' => $totalSkipped
        ]
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => 'Initialization failed: ' . $e->getMessage()
    ], 500);
}
