<?php

declare(strict_types=1);

require_once __DIR__ . '/../AccountingAutoTracker.php';
require_once __DIR__ . '/PosRepository.php';

class PosAccountingSync
{
    private PDO $pdo;
    private PosRepository $repository;
    private AccountingAutoTracker $tracker;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?: getDBConnection();
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->repository = new PosRepository($this->pdo);
        $this->tracker = new AccountingAutoTracker($this->pdo);
    }

    public function syncPendingSales(int $limit = 25): array
    {
        $queued = $this->repository->fetchPendingAccountingQueue($limit);
        $results = [
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($queued as $queueRow) {
            $results['processed']++;
            $saleId = (int) $queueRow['sale_id'];

            try {
                // Mark as processing
                $this->repository->markAccountingSyncStatus($saleId, 'processing', null);

                $sale = $this->repository->getSaleForAccounting($saleId);
                if (!$sale) {
                    throw new RuntimeException('Sale not found.');
                }

                $entryNumber = 'POS-' . $sale['sale_number'];
                $date = substr($sale['sale_timestamp'], 0, 10);
                $reference = $sale['sale_number'];
                
                // Enhanced description with more context
                $customerName = $sale['customer_name'] ?? 'Walk-in Customer';
                $description = sprintf(
                    'POS Sale %s - %s (%s)',
                    $sale['sale_number'],
                    $customerName,
                    $sale['store_name']
                );
                $createdBy = (int) $sale['cashier_id'];

                $debits = $this->buildDebitLines($sale);
                $credits = $this->buildCreditLines($sale);

                // Ensure totals match
                $totalDebit = array_sum(array_column($debits, 'amount'));
                $totalCredit = array_sum(array_column($credits, 'amount'));
                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new RuntimeException(
                        sprintf(
                            'Accounting entry out of balance. Debit: %.2f Credit: %.2f Difference: %.2f',
                            $totalDebit,
                            $totalCredit,
                            abs($totalDebit - $totalCredit)
                        )
                    );
                }

                $journalId = $this->tracker->createJournalEntry(
                    $entryNumber,
                    $date,
                    $debits,
                    $credits,
                    $reference,
                    $description,
                    $createdBy
                );
                if (!$journalId) {
                    throw new RuntimeException('Failed to create journal entry.');
                }

                // Store additional metadata in journal entry description or custom fields
                $this->storeSaleMetadata($journalId, $sale);

                $this->repository->markAccountingSyncStatus($saleId, 'synced', null);
                $results['synced']++;
            } catch (Throwable $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'sale_id' => $saleId,
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ];
                $this->repository->markAccountingSyncStatus($saleId, 'error', $e->getMessage());
                error_log('[POS Accounting Sync] Error syncing sale ' . $saleId . ': ' . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Build debit lines for POS sale
     * Proper double-entry: Debit assets (cash received) and expenses (COGS, discounts)
     */
    private function buildDebitLines(array $sale): array
    {
        $debits = [];
        $payments = $sale['payments'] ?? [];
        $totalAmount = (float) ($sale['total_amount'] ?? 0);
        $amountPaid = (float) ($sale['amount_paid'] ?? 0);

        // 1. DEBIT: Payment methods (Cash, Bank, MoMo, etc.)
        $paymentMethods = [];
        foreach ($payments as $payment) {
            $amount = (float) $payment['amount'];
            if ($amount <= 0) {
                continue;
            }

            $method = strtolower($payment['payment_method'] ?? 'cash');
            $accountCode = $this->getPaymentAccountCode($method);
            
            // Group payments by method
            if (!isset($paymentMethods[$accountCode])) {
                $paymentMethods[$accountCode] = [
                    'account_code' => $accountCode,
                    'amount' => 0,
                    'methods' => [],
                ];
            }
            $paymentMethods[$accountCode]['amount'] += $amount;
            $paymentMethods[$accountCode]['methods'][] = $method;
        }

        // Create debit entries for each payment method
        foreach ($paymentMethods as $paymentMethod) {
            $debits[] = [
                'account_code' => $paymentMethod['account_code'],
                'amount' => $paymentMethod['amount'],
                'memo' => sprintf(
                    'Payment received via %s - Sale %s',
                    implode(', ', array_unique($paymentMethod['methods'])),
                    $sale['sale_number']
                ),
            ];
        }

        // 2. DEBIT: Accounts Receivable (if partial payment or credit sale)
        if ($totalAmount > $amountPaid) {
            $outstanding = $totalAmount - $amountPaid;
            $debits[] = [
                'account_code' => '1300', // Accounts Receivable
                'amount' => $outstanding,
                'memo' => sprintf(
                    'Outstanding balance for sale %s - Customer: %s',
                    $sale['sale_number'],
                    $sale['customer_name'] ?? 'Unknown'
                ),
            ];
        }

        // 3. DEBIT: Cost of Goods Sold (COGS)
        $costTotal = 0.0;
        $itemDetails = [];
        foreach ($sale['items'] as $item) {
            $itemCost = (float) ($item['cost_amount'] ?? 0);
            if ($itemCost > 0) {
                $costTotal += $itemCost;
                $itemDetails[] = sprintf(
                    '%s (Qty: %s, Cost: %.2f)',
                    $item['name'] ?? $item['description'] ?? 'Unknown',
                    $item['quantity'] ?? 1,
                    $itemCost
                );
            }
        }
        if ($costTotal > 0) {
            $debits[] = [
                'account_code' => '5000', // COGS
                'amount' => $costTotal,
                'memo' => sprintf(
                    'COGS for sale %s - Items: %s',
                    $sale['sale_number'],
                    implode('; ', $itemDetails)
                ),
            ];
        }

        // 4. DEBIT: Discount Expense (if discounts given)
        $discount = (float) ($sale['discount_total'] ?? 0);
        if ($discount > 0) {
            $discountType = $sale['discount_type'] ?? 'unknown';
            $debits[] = [
                'account_code' => '5201', // Discount Expense (or create new account)
                'amount' => $discount,
                'memo' => sprintf(
                    'Discount applied to sale %s - Type: %s',
                    $sale['sale_number'],
                    $discountType
                ),
            ];
        }

        // 5. DEBIT: Payment Processing Fees (if applicable)
        $processingFees = $this->calculateProcessingFees($payments);
        if ($processingFees > 0) {
            $debits[] = [
                'account_code' => '5202', // Payment Processing Fees
                'amount' => $processingFees,
                'memo' => sprintf(
                    'Payment processing fees for sale %s',
                    $sale['sale_number']
                ),
            ];
        }

        return $debits;
    }

    /**
     * Build credit lines for POS sale
     * Proper double-entry: Credit revenue, inventory reduction, tax payable
     */
    private function buildCreditLines(array $sale): array
    {
        $credits = [];
        $subtotal = (float) ($sale['subtotal_amount'] ?? 0);
        $discount = (float) ($sale['discount_total'] ?? 0);
        $tax = (float) ($sale['tax_total'] ?? 0);
        $totalAmount = (float) ($sale['total_amount'] ?? 0);

        // 1. CREDIT: Sales Revenue (net of discounts)
        $netRevenue = max($subtotal - $discount, 0);
        if ($netRevenue > 0) {
            // Determine revenue account based on product categories or store type
            $revenueAccount = $this->getRevenueAccountCode($sale);
            
            $itemSummary = $this->getItemSummary($sale['items'] ?? []);
            $credits[] = [
                'account_code' => $revenueAccount,
                'amount' => $netRevenue,
                'memo' => sprintf(
                    'Sales revenue for sale %s - %s (Store: %s)',
                    $sale['sale_number'],
                    $itemSummary,
                    $sale['store_name'] ?? 'Unknown'
                ),
            ];
        }

        // 2. CREDIT: Inventory Reduction (COGS counterpart)
        $costTotal = 0.0;
        $inventoryItems = [];
        foreach ($sale['items'] as $item) {
            $itemCost = (float) ($item['cost_amount'] ?? 0);
            if ($itemCost > 0) {
                $costTotal += $itemCost;
                $inventoryItems[] = sprintf(
                    '%s x%d',
                    $item['name'] ?? $item['description'] ?? 'Unknown',
                    $item['quantity'] ?? 1
                );
            }
        }
        if ($costTotal > 0) {
            $credits[] = [
                'account_code' => '1400', // Inventory Asset
                'amount' => $costTotal,
                'memo' => sprintf(
                    'Inventory reduction for sale %s - Items: %s',
                    $sale['sale_number'],
                    implode(', ', $inventoryItems)
                ),
            ];
        }

        // 3. CREDIT: Tax Payable (sales tax collected)
        if ($tax > 0) {
            $credits[] = [
                'account_code' => '2100', // Tax Payable / Sales Tax Liability
                'amount' => $tax,
                'memo' => sprintf(
                    'Sales tax collected for sale %s',
                    $sale['sale_number']
                ),
            ];
        }

        // 4. CREDIT: Accounts Receivable (if credit sale/partial payment)
        // This balances the AR debit - represents the sale amount
        $amountPaid = (float) ($sale['amount_paid'] ?? 0);
        if ($totalAmount > $amountPaid) {
            $credits[] = [
                'account_code' => '1300', // Accounts Receivable (credit side)
                'amount' => $totalAmount - $amountPaid,
                'memo' => sprintf(
                    'Credit sale balance for sale %s',
                    $sale['sale_number']
                ),
            ];
        }

        // 5. CREDIT: Payment Processing Fee Income (offset to fee expense)
        // This is only if you want to track fees separately
        // For now, we'll debit fee expense and credit revenue net, which is simpler

        return $credits;
    }

    /**
     * Get payment method account code
     */
    private function getPaymentAccountCode(string $method): string
    {
        return match (strtolower($method)) {
            'cash' => '1000', // Cash on Hand
            'card', 'credit_card', 'debit_card' => '1101', // Card Receivables (settles to bank)
            'bank_transfer', 'bank', 'transfer' => '1100', // Bank Account
            'mobile_money', 'momo', 'mtn_momo', 'vodafone_cash', 'airteltigo_money' => '1200', // Mobile Money
            'voucher', 'gift_card' => '2101', // Gift Card Liability / Voucher Liability
            'check', 'cheque' => '1102', // Checks Receivable
            'credit', 'account' => '1300', // Accounts Receivable
            default => '1000', // Default to cash
        };
    }

    /**
     * Get revenue account code based on sale context
     */
    private function getRevenueAccountCode(array $sale): string
    {
        // Check if sale has specific product categories that determine revenue account
        $items = $sale['items'] ?? [];
        
        // Check for materials/products vs services
        $hasMaterials = false;
        $hasServices = false;
        
        foreach ($items as $item) {
            // You can enhance this by checking product categories from pos_products
            // For now, default to materials sales revenue
            $hasMaterials = true;
        }
        
        // Default to materials sales revenue
        // You can customize this based on store type, product categories, etc.
        $storeCode = $sale['store_code'] ?? '';
        
        // Store-specific revenue accounts (if you have multiple store types)
        if (strpos(strtolower($storeCode), 'service') !== false) {
            return '4010'; // Service Revenue
        }
        
        return '4020'; // Materials Sales Revenue (default)
    }

    /**
     * Calculate payment processing fees
     */
    private function calculateProcessingFees(array $payments): float
    {
        $totalFees = 0.0;
        
        // Get processing fee rates from system config
        $cardFeeRate = $this->getConfigValue('pos_card_processing_fee_rate', 0.03); // 3% default
        $momoFeeRate = $this->getConfigValue('pos_momo_processing_fee_rate', 0.01); // 1% default
        
        foreach ($payments as $payment) {
            $amount = (float) ($payment['amount'] ?? 0);
            $method = strtolower($payment['payment_method'] ?? '');
            
            if ($amount <= 0) {
                continue;
            }
            
            // Calculate fees based on payment method
            if (in_array($method, ['card', 'credit_card', 'debit_card'])) {
                $totalFees += $amount * $cardFeeRate;
            } elseif (in_array($method, ['mobile_money', 'momo', 'mtn_momo', 'vodafone_cash', 'airteltigo_money'])) {
                $totalFees += $amount * $momoFeeRate;
            }
            // Cash and bank transfers typically have no fees
        }
        
        return round($totalFees, 2);
    }

    /**
     * Get item summary for memo
     */
    private function getItemSummary(array $items): string
    {
        if (empty($items)) {
            return 'No items';
        }
        
        $summary = [];
        $itemCount = count($items);
        
        if ($itemCount <= 3) {
            // List all items if 3 or fewer
            foreach ($items as $item) {
                $summary[] = sprintf(
                    '%s (x%d)',
                    $item['name'] ?? $item['description'] ?? 'Unknown',
                    $item['quantity'] ?? 1
                );
            }
        } else {
            // Summarize if more than 3 items
            $totalQty = array_sum(array_column($items, 'quantity'));
            $summary[] = sprintf('%d items (Total Qty: %d)', $itemCount, $totalQty);
        }
        
        return implode(', ', $summary);
    }

    /**
     * Store additional sale metadata
     */
    private function storeSaleMetadata(int $journalId, array $sale): void
    {
        try {
            // You can extend journal_entries table to store metadata
            // Or create a separate table for POS sale metadata
            // For now, we'll store key info in the description field which is already enhanced
            
            // Optional: Create a pos_sale_accounting_metadata table to store:
            // - Customer ID
            // - Store ID
            // - Product breakdown
            // - Payment method breakdown
            // - Discount details
            // - Tax breakdown
            // etc.
            
            // This allows for better reporting and analysis later
        } catch (Throwable $e) {
            // Non-critical, just log
            error_log('[POS Accounting Sync] Failed to store metadata: ' . $e->getMessage());
        }
    }

    /**
     * Get config value with default
     */
    private function getConfigValue(string $key, $default = null)
    {
        try {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ? LIMIT 1");
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (PDOException $e) {
            return $default;
        }
    }

    /**
     * Sync refunds to accounting (reversals)
     */
    public function syncRefunds(int $limit = 25): array
    {
        $results = [
            'processed' => 0,
            'synced' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            // Get completed refunds that haven't been synced
            $refunds = $this->repository->listRefunds($limit, 0, 'completed');
            
            foreach ($refunds as $refund) {
                $results['processed']++;
                
                try {
                    // Check if refund already synced
                    $checkStmt = $this->pdo->prepare("
                        SELECT id FROM journal_entries 
                        WHERE reference = ? AND entry_number LIKE ?
                    ");
                    $checkStmt->execute([
                        $refund['refund_number'],
                        'REFUND-%'
                    ]);
                    
                    if ($checkStmt->fetch()) {
                        continue; // Already synced
                    }
                    
                    // Get original sale for context
                    $originalSale = $this->repository->getSaleForAccounting($refund['original_sale_id']);
                    if (!$originalSale) {
                        throw new RuntimeException('Original sale not found for refund');
                    }
                    
                    // Build reversal entries
                    $entryNumber = 'REFUND-' . $refund['refund_number'];
                    $date = substr($refund['refund_timestamp'], 0, 10);
                    $reference = $refund['refund_number'];
                    $description = sprintf(
                        'Refund %s for sale %s - Reason: %s',
                        $refund['refund_number'],
                        $originalSale['sale_number'],
                        $refund['refund_reason'] ?? 'Not specified'
                    );
                    $createdBy = (int) ($refund['approved_by'] ?? $refund['cashier_id']);
                    
                    $debits = $this->buildRefundDebits($refund, $originalSale);
                    $credits = $this->buildRefundCredits($refund, $originalSale);
                    
                    // Add inventory restoration entries if items are returned to stock
                    // Check if refund actually restored inventory (based on refund items having cost)
                    $refundItems = $refund['items'] ?? [];
                    $hasInventoryRestoration = false;
                    foreach ($refundItems as $item) {
                        // Check if we can determine cost (meaning inventory was tracked)
                        if (!empty($item['product_id'])) {
                            try {
                                $productStmt = $this->pdo->prepare("SELECT track_inventory, cost_price FROM pos_products WHERE id = ?");
                                $productStmt->execute([$item['product_id']]);
                                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                                if ($product && !empty($product['track_inventory']) && !empty($product['cost_price'])) {
                                    $hasInventoryRestoration = true;
                                    break;
                                }
                            } catch (PDOException $e) {
                                // Continue
                            }
                        }
                    }
                    
                    if ($hasInventoryRestoration) {
                        $inventoryDebits = $this->buildRefundInventoryDebits($refund);
                        $inventoryCredits = $this->buildRefundInventoryCredits($refund);
                        
                        // Merge all debits and credits
                        $debits = array_merge($debits, $inventoryDebits);
                        $credits = array_merge($credits, $inventoryCredits);
                    }
                    
                    // Ensure balance
                    $totalDebit = array_sum(array_column($debits, 'amount'));
                    $totalCredit = array_sum(array_column($credits, 'amount'));
                    if (abs($totalDebit - $totalCredit) > 0.01) {
                        throw new RuntimeException(sprintf(
                            'Refund entry out of balance. Debit: %.2f Credit: %.2f Difference: %.2f',
                            $totalDebit,
                            $totalCredit,
                            abs($totalDebit - $totalCredit)
                        ));
                    }
                    
                    $journalId = $this->tracker->createJournalEntry(
                        $entryNumber,
                        $date,
                        $debits,
                        $credits,
                        $reference,
                        $description,
                        $createdBy
                    );
                    
                    if ($journalId) {
                        $results['synced']++;
                    } else {
                        throw new RuntimeException('Failed to create refund journal entry');
                    }
                } catch (Throwable $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'refund_id' => $refund['id'],
                        'message' => $e->getMessage(),
                    ];
                    error_log('[POS Accounting Sync] Error syncing refund ' . $refund['id'] . ': ' . $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            error_log('[POS Accounting Sync] Error in syncRefunds: ' . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Build debit lines for refund (reversals)
     * Refund reverses the original sale:
     * - DEBIT Revenue (reduces sales)
     * - DEBIT Tax Payable (reduces tax liability)
     * - DEBIT Discount Expense reversal (if discount was given - credit to discount expense)
     */
    private function buildRefundDebits(array $refund, array $originalSale): array
    {
        $debits = [];
        $refundAmount = (float) ($refund['total_amount'] ?? 0);
        
        // 1. DEBIT: Reverse revenue (reduce sales)
        // Use subtotal from refund (which is the amount being refunded before tax)
        $subtotal = (float) ($refund['subtotal_amount'] ?? 0);
        
        // Check if there was a discount on the original sale that needs to be reversed
        // For refunds, we typically refund the net amount (after discount), so we reverse net revenue
        $originalDiscount = (float) ($originalSale['discount_total'] ?? 0);
        $refundSubtotal = $subtotal;
        
        // If refund is for items that had discounts, we need to reverse the net revenue
        // The refund subtotal should already be net of discounts
        $netRevenue = max($refundSubtotal, 0);
        
        if ($netRevenue > 0) {
            $revenueAccount = $this->getRevenueAccountCode($originalSale);
            $debits[] = [
                'account_code' => $revenueAccount,
                'amount' => $netRevenue,
                'memo' => sprintf('Revenue reversal for refund %s', $refund['refund_number']),
            ];
        }
        
        // 2. DEBIT: Reverse tax (reduce tax payable)
        $tax = (float) ($refund['tax_total'] ?? 0);
        if ($tax > 0) {
            $debits[] = [
                'account_code' => '2100', // Tax Payable (debit reduces liability)
                'amount' => $tax,
                'memo' => sprintf('Tax reversal for refund %s', $refund['refund_number']),
            ];
        }
        
        // 3. DEBIT: Reverse discount expense (if applicable)
        // If the refunded items had discounts, we should reverse the discount expense
        // This is a credit to discount expense (which is a debit-balance account)
        // So we credit discount expense to reverse it, which means we debit a contra account
        // Actually, to reverse an expense, we credit it. So this should be a credit, not a debit.
        // For now, we'll handle discounts as part of the net revenue reversal
        
        return $debits;
    }

    /**
     * Build credit lines for refund (reversals)
     * Refund credits: Payment method (cash paid out)
     */
    private function buildRefundCredits(array $refund, array $originalSale): array
    {
        $credits = [];
        $refundAmount = (float) ($refund['total_amount'] ?? 0);
        $refundMethod = strtolower($refund['refund_method'] ?? 'original_method');
        
        // CREDIT: Payment method (cash paid out, bank transfer, etc.)
        // This represents the actual cash/asset paid out for the refund
        $accountCode = $this->getPaymentAccountCode($refundMethod);
        $credits[] = [
            'account_code' => $accountCode,
            'amount' => $refundAmount,
            'memo' => sprintf('Refund paid via %s for refund %s', $refundMethod, $refund['refund_number']),
        ];
        
        return $credits;
    }
    
    /**
     * Build additional debit lines for refund (inventory restoration)
     * This is separate because inventory restoration affects COGS reversal
     */
    private function buildRefundInventoryDebits(array $refund): array
    {
        $debits = [];
        $refundItems = $refund['items'] ?? [];
        $costTotal = 0.0;
        $itemDetails = [];
        
        foreach ($refundItems as $item) {
            // Get cost from original sale item or product
            $quantity = (float) ($item['quantity'] ?? 1);
            
            // Try to get cost from original sale item, then refund item, then product
            $itemCost = 0.0;
            if (isset($item['original_cost_amount']) && $item['original_cost_amount'] > 0) {
                // Use cost from original sale item (most accurate)
                $itemCost = (float) $item['original_cost_amount'];
            } elseif (isset($item['cost_amount']) && $item['cost_amount'] > 0) {
                $itemCost = (float) $item['cost_amount'];
            } elseif (isset($item['cost_price']) && $item['cost_price'] > 0) {
                // Use product cost price
                $itemCost = (float) $item['cost_price'] * $quantity;
            } else {
                // Fallback: lookup product cost
                try {
                    $productStmt = $this->pdo->prepare("SELECT cost_price FROM pos_products WHERE id = ?");
                    $productStmt->execute([$item['product_id'] ?? 0]);
                    $costPrice = $productStmt->fetchColumn();
                    if ($costPrice) {
                        $itemCost = (float) $costPrice * $quantity;
                    }
                } catch (PDOException $e) {
                    // Skip if can't get cost
                }
            }
            
            if ($itemCost > 0) {
                $costTotal += $itemCost;
                $itemDetails[] = sprintf('%s (Qty: %s)', $item['name'] ?? 'Unknown', $quantity);
            }
        }
        
        if ($costTotal > 0) {
            // DEBIT: Inventory Asset (restore inventory)
            $debits[] = [
                'account_code' => '1400', // Inventory Asset
                'amount' => $costTotal,
                'memo' => sprintf('Inventory restored for refund %s - Items: %s', $refund['refund_number'], implode(', ', $itemDetails)),
            ];
        }
        
        return $debits;
    }
    
    /**
     * Build additional credit lines for refund (COGS reversal)
     */
    private function buildRefundInventoryCredits(array $refund): array
    {
        $credits = [];
        $refundItems = $refund['items'] ?? [];
        $costTotal = 0.0;
        
        foreach ($refundItems as $item) {
            $quantity = (float) ($item['quantity'] ?? 1);
            
            // Get cost from original sale item, then refund item, then product
            $itemCost = 0.0;
            if (isset($item['original_cost_amount']) && $item['original_cost_amount'] > 0) {
                // Use cost from original sale item (most accurate)
                $itemCost = (float) $item['original_cost_amount'];
            } elseif (isset($item['cost_amount']) && $item['cost_amount'] > 0) {
                $itemCost = (float) $item['cost_amount'];
            } elseif (isset($item['cost_price']) && $item['cost_price'] > 0) {
                // Use product cost price
                $itemCost = (float) $item['cost_price'] * $quantity;
            } else {
                // Fallback: lookup product cost
                try {
                    $productStmt = $this->pdo->prepare("SELECT cost_price FROM pos_products WHERE id = ?");
                    $productStmt->execute([$item['product_id'] ?? 0]);
                    $costPrice = $productStmt->fetchColumn();
                    if ($costPrice) {
                        $itemCost = (float) $costPrice * $quantity;
                    }
                } catch (PDOException $e) {
                    // Skip if can't get cost
                }
            }
            
            if ($itemCost > 0) {
                $costTotal += $itemCost;
            }
        }
        
        if ($costTotal > 0) {
            // CREDIT: COGS (reverse the expense - credit reduces expense)
            $credits[] = [
                'account_code' => '5000', // COGS
                'amount' => $costTotal,
                'memo' => sprintf('COGS reversal for refund %s (items returned to inventory)', $refund['refund_number']),
            ];
        }
        
        return $credits;
    }
}
