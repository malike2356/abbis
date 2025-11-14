<?php
/**
 * Accounting Auto Tracker
 * Automatically creates journal entries for all financial transactions system-wide
 * 
 * This class ensures that every financial transaction (inflow/outflow) is
 * automatically recorded in the accounting system without manual intervention.
 */
class AccountingAutoTracker {
    private $pdo;
    private $accountMap;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadAccountMapping();
        $this->ensureDefaultAccounts();
    }
    
    /**
     * Load account mapping configuration
     * Maps transaction types to chart of accounts
     */
    private function loadAccountMapping() {
        // Default account mappings - can be customized via settings
        $this->accountMap = [
            // Revenue/Income Accounts
            'revenue_contract' => ['code' => '4000', 'name' => 'Contract Revenue'],
            'revenue_rig_fee' => ['code' => '4010', 'name' => 'Rig Fee Revenue'],
            'revenue_materials' => ['code' => '4020', 'name' => 'Materials Sales Revenue'],
            'revenue_other' => ['code' => '4090', 'name' => 'Other Revenue'],
            
            // Asset Accounts
            'asset_cash' => ['code' => '1000', 'name' => 'Cash on Hand'],
            'asset_bank' => ['code' => '1100', 'name' => 'Bank Account'],
            'asset_momo' => ['code' => '1200', 'name' => 'Mobile Money'],
            'asset_accounts_receivable' => ['code' => '1300', 'name' => 'Accounts Receivable'],
            'asset_materials_inventory' => ['code' => '1400', 'name' => 'Materials Inventory'],
            'asset_fixed_assets' => ['code' => '1600', 'name' => 'Fixed Assets'],
            
            // Expense Accounts
            'expense_materials' => ['code' => '5000', 'name' => 'Materials Cost'],
            'expense_wages' => ['code' => '5100', 'name' => 'Wages & Salaries'],
            'expense_operating' => ['code' => '5200', 'name' => 'Operating Expenses'],
            'expense_other' => ['code' => '5990', 'name' => 'Other Expenses'],
            
            // Loan Asset (Worker Loans Receivable)
            'asset_worker_loans' => ['code' => '1500', 'name' => 'Worker Loans Receivable'],
            
            // Liability Accounts
            'liability_loans_payable' => ['code' => '2000', 'name' => 'Loans Payable'],
            'liability_accounts_payable' => ['code' => '2100', 'name' => 'Accounts Payable'],
        ];
    }
    
    /**
     * Ensure default chart of accounts exist
     */
    private function ensureDefaultAccounts() {
        try {
            // Check if chart_of_accounts table exists
            $this->pdo->query("SELECT 1 FROM chart_of_accounts LIMIT 1");
        } catch (PDOException $e) {
            // Table doesn't exist, run migration
            $migrationFile = __DIR__ . '/../database/accounting_migration.sql';
            if (file_exists($migrationFile)) {
                $sql = file_get_contents($migrationFile);
                if ($sql) {
                    $stmts = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
                    foreach ($stmts as $stmt) {
                        if ($stmt !== '') {
                            try {
                                $this->pdo->exec($stmt);
                            } catch (PDOException $e2) {
                                // Ignore if already exists
                            }
                        }
                    }
                }
            }
        }
        
        // Create default accounts if they don't exist
        foreach ($this->accountMap as $key => $account) {
            $this->getOrCreateAccount($account['code'], $account['name'], $this->getAccountType($key));
        }
    }
    
    /**
     * Get account type based on key prefix
     */
    private function getAccountType($key) {
        if (strpos($key, 'revenue_') === 0) return 'Revenue';
        if (strpos($key, 'asset_') === 0) return 'Asset';
        if (strpos($key, 'expense_') === 0) return 'Expense';
        if (strpos($key, 'liability_') === 0) return 'Liability';
        return 'Expense'; // Default
    }
    
    /**
     * Get or create an account in chart of accounts
     */
    private function getOrCreateAccount($code, $name, $type) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ?");
            $stmt->execute([$code]);
            $account = $stmt->fetch();
            
            if ($account) {
                return $account['id'];
            }
            
            // Create account
            $stmt = $this->pdo->prepare("
                INSERT INTO chart_of_accounts (account_code, account_name, account_type, is_active)
                VALUES (?, ?, ?, 1)
            ");
            $stmt->execute([$code, $name, $type]);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating account: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get account ID by code
     */
    private function getAccountId($code) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code = ? AND is_active = 1");
            $stmt->execute([$code]);
            $account = $stmt->fetch();
            return $account ? $account['id'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }
    
    /**
     * Create a journal entry with double-entry bookkeeping
     * 
     * @param string $entryNumber Unique entry number
     * @param string $date Transaction date
     * @param array $debits Array of ['account_code' => code, 'amount' => amount, 'memo' => memo]
     * @param array $credits Array of ['account_code' => code, 'amount' => amount, 'memo' => memo]
     * @param string $reference Reference number/ID
     * @param string $description Description
     * @param int $createdBy User ID
     * @return int|false Journal entry ID or false on failure
     */
    public function createJournalEntry($entryNumber, $date, $debits, $credits, $reference = null, $description = null, $createdBy = null) {
        try {
            // Validate debits and credits balance
            $totalDebit = array_sum(array_column($debits, 'amount'));
            $totalCredit = array_sum(array_column($credits, 'amount'));
            
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new Exception("Debits ({$totalDebit}) and Credits ({$totalCredit}) must balance");
            }
            
            $this->pdo->beginTransaction();
            
            // Create journal entry
            $stmt = $this->pdo->prepare("
                INSERT INTO journal_entries (entry_number, entry_date, reference, description, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$entryNumber, $date, $reference, $description, $createdBy]);
            $entryId = $this->pdo->lastInsertId();
            
            // Create debit lines
            foreach ($debits as $debit) {
                $accountId = $this->getAccountId($debit['account_code']);
                if ($accountId && $debit['amount'] > 0) {
                    $lineStmt = $this->pdo->prepare("
                        INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, memo)
                        VALUES (?, ?, ?, 0, ?)
                    ");
                    $lineStmt->execute([
                        $entryId,
                        $accountId,
                        $debit['amount'],
                        $debit['memo'] ?? null
                    ]);
                }
            }
            
            // Create credit lines
            foreach ($credits as $credit) {
                $accountId = $this->getAccountId($credit['account_code']);
                if ($accountId && $credit['amount'] > 0) {
                    $lineStmt = $this->pdo->prepare("
                        INSERT INTO journal_entry_lines (journal_entry_id, account_id, debit, credit, memo)
                        VALUES (?, ?, 0, ?, ?)
                    ");
                    $lineStmt->execute([
                        $entryId,
                        $accountId,
                        $credit['amount'],
                        $credit['memo'] ?? null
                    ]);
                }
            }
            
            $this->pdo->commit();
            return $entryId;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log("Accounting Auto Tracker Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track field report financial transactions
     * Automatically creates journal entries for all money flows in a field report
     */
    public function trackFieldReport($reportId, $reportData) {
        try {
            $entryNumber = 'FR-' . $reportData['report_id'] . '-' . date('Ymd');
            $date = $reportData['report_date'];
            $reference = $reportData['report_id'];
            $description = "Field Report: " . ($reportData['site_name'] ?? '') . " - " . ($reportData['client_name'] ?? '');
            $createdBy = $reportData['created_by'] ?? null;
            
            $debits = [];
            $credits = [];
            
            // INFLOW TRANSACTIONS (Revenue - Credit, Asset - Debit)
            
            // Contract Sum (Revenue)
            if (!empty($reportData['contract_sum']) && $reportData['contract_sum'] > 0) {
                $amount = floatval($reportData['contract_sum']);
                $credits[] = [
                    'account_code' => $this->accountMap['revenue_contract']['code'],
                    'amount' => $amount,
                    'memo' => 'Contract revenue from field report'
                ];
                $debits[] = [
                    'account_code' => $this->accountMap['asset_accounts_receivable']['code'],
                    'amount' => $amount,
                    'memo' => 'Accounts receivable - contract sum'
                ];
            }
            
            // Rig Fee - Track charged vs collected
            $rigFeeCharged = floatval($reportData['rig_fee_charged'] ?? 0);
            $rigFeeCollected = floatval($reportData['rig_fee_collected'] ?? 0);
            
            if ($rigFeeCharged > 0) {
                // Record full rig fee as revenue (even if not fully collected)
                $credits[] = [
                    'account_code' => $this->accountMap['revenue_rig_fee']['code'],
                    'amount' => $rigFeeCharged,
                    'memo' => 'Rig fee revenue (charged)'
                ];
                
                // If collected, debit cash
                if ($rigFeeCollected > 0) {
                    $debits[] = [
                        'account_code' => $this->accountMap['asset_cash']['code'],
                        'amount' => $rigFeeCollected,
                        'memo' => 'Cash received - rig fee'
                    ];
                }
                
                // If there's outstanding amount, debit accounts receivable
                $outstandingRigFee = $rigFeeCharged - $rigFeeCollected;
                if ($outstandingRigFee > 0) {
                    $debits[] = [
                        'account_code' => $this->accountMap['asset_accounts_receivable']['code'],
                        'amount' => $outstandingRigFee,
                        'memo' => 'Outstanding rig fee receivable'
                    ];
                }
            }
            
            // Cash Received (Asset)
            if (!empty($reportData['cash_received']) && $reportData['cash_received'] > 0) {
                $amount = floatval($reportData['cash_received']);
                $debits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash received from company'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_bank']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash withdrawn from bank'
                ];
            }
            
            // Materials Income (Revenue)
            if (!empty($reportData['materials_income']) && $reportData['materials_income'] > 0) {
                $amount = floatval($reportData['materials_income']);
                $credits[] = [
                    'account_code' => $this->accountMap['revenue_materials']['code'],
                    'amount' => $amount,
                    'memo' => 'Materials sales revenue'
                ];
                $debits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash from materials sales'
                ];
            }
            
            // OUTFLOW TRANSACTIONS (Expense - Debit, Asset - Credit)
            
            // Materials Cost (Expense)
            if (!empty($reportData['materials_cost']) && $reportData['materials_cost'] > 0) {
                $amount = floatval($reportData['materials_cost']);
                $debits[] = [
                    'account_code' => $this->accountMap['expense_materials']['code'],
                    'amount' => $amount,
                    'memo' => 'Materials purchased/used'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash paid for materials'
                ];
            }
            
            // Total Wages (Expense)
            if (!empty($reportData['total_wages']) && $reportData['total_wages'] > 0) {
                $amount = floatval($reportData['total_wages']);
                $debits[] = [
                    'account_code' => $this->accountMap['expense_wages']['code'],
                    'amount' => $amount,
                    'memo' => 'Wages paid to workers'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash paid for wages'
                ];
            }
            
            // Daily Expenses (Operating Expense)
            $dailyExpenses = floatval($reportData['total_expenses'] ?? 0) - floatval($reportData['total_wages'] ?? 0) - floatval($reportData['materials_cost'] ?? 0);
            if ($dailyExpenses > 0) {
                $debits[] = [
                    'account_code' => $this->accountMap['expense_operating']['code'],
                    'amount' => $dailyExpenses,
                    'memo' => 'Daily operating expenses'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $dailyExpenses,
                    'memo' => 'Cash paid for expenses'
                ];
            }
            
            // DEPOSITS/TRANSFERS (Asset movements)
            
            // MoMo Transfer (Cash to MoMo)
            if (!empty($reportData['momo_transfer']) && $reportData['momo_transfer'] > 0) {
                $amount = floatval($reportData['momo_transfer']);
                $debits[] = [
                    'account_code' => $this->accountMap['asset_momo']['code'],
                    'amount' => $amount,
                    'memo' => 'Transfer to mobile money'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash transferred to MoMo'
                ];
            }
            
            // Cash Given to Company (Cash to Bank)
            if (!empty($reportData['cash_given']) && $reportData['cash_given'] > 0) {
                $amount = floatval($reportData['cash_given']);
                $debits[] = [
                    'account_code' => $this->accountMap['asset_bank']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash deposited to bank'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash given to company'
                ];
            }
            
            // Bank Deposit (Cash to Bank)
            if (!empty($reportData['bank_deposit']) && $reportData['bank_deposit'] > 0) {
                $amount = floatval($reportData['bank_deposit']);
                $debits[] = [
                    'account_code' => $this->accountMap['asset_bank']['code'],
                    'amount' => $amount,
                    'memo' => 'Bank deposit'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_cash']['code'],
                    'amount' => $amount,
                    'memo' => 'Cash deposited to bank'
                ];
            }
            
            
            // Only create journal entry if there are transactions
            if (!empty($debits) && !empty($credits)) {
                return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
            }
            
            return true; // No transactions to record
        } catch (Exception $e) {
            error_log("Error tracking field report: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track payroll payment
     */
    public function trackPayrollPayment($payrollEntryId, $payrollData) {
        try {
            $entryNumber = 'PAY-' . $payrollEntryId . '-' . date('Ymd');
            $date = $payrollData['payment_date'] ?? date('Y-m-d');
            $reference = 'PAY-' . $payrollEntryId;
            $description = "Payroll payment: " . ($payrollData['worker_name'] ?? '');
            $createdBy = $payrollData['created_by'] ?? null;
            
            $amount = floatval($payrollData['amount'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['expense_wages']['code'],
                'amount' => $amount,
                'memo' => 'Wage payment to ' . ($payrollData['worker_name'] ?? '')
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash paid for wages'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking payroll: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track loan disbursement
     * Loans to workers are assets (receivables), not expenses
     */
    public function trackLoanDisbursement($loanId, $loanData) {
        try {
            $entryNumber = 'LOAN-DISB-' . $loanId . '-' . date('Ymd');
            $date = $loanData['issue_date'] ?? date('Y-m-d');
            $reference = 'LOAN-' . $loanId;
            $description = "Loan disbursement to " . ($loanData['worker_name'] ?? '');
            $createdBy = $loanData['created_by'] ?? null;
            
            $amount = floatval($loanData['loan_amount'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['asset_worker_loans']['code'],
                'amount' => $amount,
                'memo' => 'Loan receivable from ' . ($loanData['worker_name'] ?? '')
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash disbursed as loan'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking loan disbursement: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track loan repayment
     * Reduces the loan receivable asset when worker repays
     */
    public function trackLoanRepayment($repaymentId, $repaymentData) {
        try {
            $entryNumber = 'LOAN-REPAY-' . $repaymentId . '-' . date('Ymd');
            $date = $repaymentData['repayment_date'] ?? date('Y-m-d');
            $reference = 'REPAY-' . $repaymentId;
            $description = "Loan repayment from " . ($repaymentData['worker_name'] ?? '');
            $createdBy = $repaymentData['created_by'] ?? null;
            
            $amount = floatval($repaymentData['repayment_amount'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Loan repayment received'
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_worker_loans']['code'],
                'amount' => $amount,
                'memo' => 'Reduce loan receivable - repayment received'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking loan repayment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track materials purchase
     */
    public function trackMaterialsPurchase($transactionId, $transactionData) {
        try {
            $entryNumber = 'MAT-PURCH-' . $transactionId . '-' . date('Ymd');
            $date = $transactionData['transaction_date'] ?? date('Y-m-d');
            $reference = 'MAT-' . $transactionId;
            $description = "Materials purchase: " . ($transactionData['description'] ?? '');
            $createdBy = $transactionData['created_by'] ?? null;
            
            $amount = floatval($transactionData['total_cost'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['asset_materials_inventory']['code'],
                'amount' => $amount,
                'memo' => 'Materials inventory purchase'
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash paid for materials'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking materials purchase: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track materials sale
     */
    public function trackMaterialsSale($transactionId, $transactionData) {
        try {
            $entryNumber = 'MAT-SALE-' . $transactionId . '-' . date('Ymd');
            $date = $transactionData['transaction_date'] ?? date('Y-m-d');
            $reference = 'MAT-' . $transactionId;
            $description = "Materials sale: " . ($transactionData['description'] ?? '');
            $createdBy = $transactionData['created_by'] ?? null;
            
            $amount = floatval($transactionData['total_cost'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash received from materials sale'
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['revenue_materials']['code'],
                'amount' => $amount,
                'memo' => 'Materials sales revenue'
            ]];
            
            // Also reduce inventory (COGS)
            $cost = floatval($transactionData['unit_cost'] ?? 0) * floatval($transactionData['quantity'] ?? 0);
            if ($cost > 0) {
                $debits[] = [
                    'account_code' => $this->accountMap['expense_materials']['code'],
                    'amount' => $cost,
                    'memo' => 'Cost of materials sold'
                ];
                $credits[] = [
                    'account_code' => $this->accountMap['asset_materials_inventory']['code'],
                    'amount' => $cost,
                    'memo' => 'Reduce inventory'
                ];
            }
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking materials sale: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track asset purchase
     * When an asset is purchased, it's recorded as a fixed asset
     */
    public function trackAssetPurchase($assetId, $assetData) {
        try {
            $entryNumber = 'ASSET-PURCH-' . $assetId . '-' . date('Ymd');
            $date = $assetData['purchase_date'] ?? date('Y-m-d');
            $reference = 'ASSET-' . $assetId;
            $description = "Asset purchase: " . ($assetData['asset_name'] ?? '');
            $createdBy = $assetData['created_by'] ?? null;
            
            $amount = floatval($assetData['purchase_cost'] ?? 0);
            if ($amount <= 0) return false;
            
            // Check if this is a new purchase (not an edit)
            $isNewPurchase = isset($assetData['is_new_purchase']) && $assetData['is_new_purchase'] === true;
            
            // Only track if it's a new purchase or if purchase_cost changed
            if (!$isNewPurchase && isset($assetData['old_purchase_cost'])) {
                $oldCost = floatval($assetData['old_purchase_cost']);
                if (abs($amount - $oldCost) < 0.01) {
                    // No change in purchase cost, skip
                    return true;
                }
                // Cost changed, track the difference
                $amount = $amount - $oldCost;
                if ($amount <= 0) return true;
            }
            
            $debits = [[
                'account_code' => $this->accountMap['asset_fixed_assets']['code'],
                'amount' => $amount,
                'memo' => 'Asset purchase: ' . ($assetData['asset_name'] ?? '')
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash paid for asset purchase'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking asset purchase: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track CMS order payment
     * When a CMS order payment is completed
     */
    public function trackCMSPayment($paymentId, $paymentData) {
        try {
            $entryNumber = 'CMS-PAY-' . $paymentId . '-' . date('Ymd');
            $date = $paymentData['payment_date'] ?? date('Y-m-d');
            $reference = 'CMS-PAY-' . $paymentId;
            $description = "CMS Order Payment: " . ($paymentData['order_number'] ?? '');
            $createdBy = $paymentData['created_by'] ?? null;
            
            $amount = floatval($paymentData['amount'] ?? 0);
            if ($amount <= 0) return false;
            
            // Determine payment method and corresponding asset account
            $paymentMethod = strtolower($paymentData['payment_method'] ?? 'cash');
            $assetAccountCode = $this->accountMap['asset_cash']['code']; // Default to cash
            
            if (strpos($paymentMethod, 'bank') !== false || strpos($paymentMethod, 'transfer') !== false) {
                $assetAccountCode = $this->accountMap['asset_bank']['code'];
            } elseif (strpos($paymentMethod, 'momo') !== false || strpos($paymentMethod, 'mobile') !== false) {
                $assetAccountCode = $this->accountMap['asset_momo']['code'];
            }
            
            $debits = [[
                'account_code' => $assetAccountCode,
                'amount' => $amount,
                'memo' => 'Payment received for CMS order'
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['revenue_other']['code'],
                'amount' => $amount,
                'memo' => 'CMS order revenue'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking CMS payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track Client Portal invoice payment
     * Debits cash/bank/mobile account and credits Accounts Receivable
     */
    public function trackClientInvoicePayment($paymentId, array $paymentData) {
        try {
            $entryNumber = 'CLT-PAY-' . $paymentId . '-' . date('Ymd');
            $date = $paymentData['payment_date'] ?? date('Y-m-d');
            $reference = 'CLT-PAY-' . $paymentId;
            $description = "Client payment for invoice: " . ($paymentData['invoice_number'] ?? '');
            $createdBy = $paymentData['created_by'] ?? null;
            
            $amount = floatval($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                return false;
            }
            
            $methodKey = strtolower($paymentData['payment_method'] ?? 'cash');
            $assetAccountCode = $this->accountMap['asset_cash']['code'];
            
            if (strpos($methodKey, 'bank') !== false || strpos($methodKey, 'transfer') !== false) {
                $assetAccountCode = $this->accountMap['asset_bank']['code'];
            } elseif (strpos($methodKey, 'momo') !== false || strpos($methodKey, 'mobile') !== false) {
                $assetAccountCode = $this->accountMap['asset_momo']['code'];
            } elseif (strpos($methodKey, 'card') !== false) {
                $assetAccountCode = $this->accountMap['asset_bank']['code'];
            }
            
            $debits = [[
                'account_code' => $assetAccountCode,
                'amount' => $amount,
                'memo' => 'Client payment received'
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_accounts_receivable']['code'],
                'amount' => $amount,
                'memo' => 'Accounts receivable settlement'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking client invoice payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track maintenance cost
     * When maintenance work is performed and costs are incurred
     */
    public function trackMaintenanceCost($maintenanceId, $maintenanceData) {
        try {
            $entryNumber = 'MNT-' . $maintenanceId . '-' . date('Ymd');
            $date = $maintenanceData['maintenance_date'] ?? date('Y-m-d');
            $reference = 'MNT-' . $maintenanceId;
            $description = "Maintenance cost: " . ($maintenanceData['description'] ?? '');
            $createdBy = $maintenanceData['created_by'] ?? null;
            
            $amount = floatval($maintenanceData['total_cost'] ?? 0);
            if ($amount <= 0) return false;
            
            $debits = [[
                'account_code' => $this->accountMap['expense_operating']['code'],
                'amount' => $amount,
                'memo' => 'Maintenance expense: ' . ($maintenanceData['description'] ?? '')
            ]];
            
            $credits = [[
                'account_code' => $this->accountMap['asset_cash']['code'],
                'amount' => $amount,
                'memo' => 'Cash paid for maintenance'
            ]];
            
            return $this->createJournalEntry($entryNumber, $date, $debits, $credits, $reference, $description, $createdBy);
        } catch (Exception $e) {
            error_log("Error tracking maintenance cost: " . $e->getMessage());
            return false;
        }
    }
}

