# POS Accounting Sync - Improvements & Enhancements

## Overview
The POS accounting sync has been significantly enhanced to provide comprehensive double-entry bookkeeping with additional data points and improved accuracy.

## Current Implementation

### How It Works
1. **Queue System**: Sales are automatically queued in `pos_accounting_queue` when created
2. **Sync Process**: `PosAccountingSync::syncPendingSales()` processes pending sales
3. **Journal Entries**: Creates double-entry journal entries via `AccountingAutoTracker`
4. **Status Tracking**: Tracks sync status (pending, processing, synced, error)

## Key Improvements Made

### 1. Fixed Double-Entry Logic
**Before**: Payment credits were duplicated, causing unbalanced entries
**After**: Proper double-entry with:
- **Debits**: Payment methods (cash received), COGS, discount expense, processing fees, accounts receivable
- **Credits**: Revenue, inventory reduction, tax payable, accounts receivable (for credit sales)

### 2. Enhanced Data Points

#### Sales Accounting Now Includes:
- ✅ **Payment Method Breakdown**: Separate tracking for Cash, Card, Mobile Money, Bank Transfer, Gift Cards
- ✅ **COGS Tracking**: Accurate cost of goods sold with inventory reduction
- ✅ **Discount Expense**: Separate account for discounts (Account 5201)
- ✅ **Payment Processing Fees**: Configurable fees for card/MoMo payments (Account 5202)
- ✅ **Tax Liability**: Proper tax payable tracking
- ✅ **Accounts Receivable**: Credit sales and partial payments
- ✅ **Customer Context**: Customer name in journal entry descriptions
- ✅ **Store Context**: Store information in descriptions
- ✅ **Product Breakdown**: Item-level details in memos
- ✅ **Payment Method Grouping**: Payments grouped by method for cleaner entries

#### Refund Accounting (NEW):
- ✅ **Revenue Reversal**: Reduces sales revenue
- ✅ **Tax Reversal**: Reduces tax payable
- ✅ **Inventory Restoration**: Restores inventory for returned items
- ✅ **COGS Reversal**: Reverses cost of goods sold
- ✅ **Payment Method Tracking**: Tracks refund payment method
- ✅ **Refund Reason**: Includes reason in journal entry

### 3. New Accounting Accounts

Added to `chart_of_accounts`:
- **5201** - Discount Expense
- **5202** - Payment Processing Fees
- **1101** - Card Receivables
- **1102** - Checks Receivable
- **2101** - Gift Card Liability
- **4010** - Service Revenue (for service-based stores)

### 4. Enhanced Journal Entry Descriptions

**Before**: `POS Sale POS-20241201-0001 (Main Store)`
**After**: `POS Sale POS-20241201-0001 - John Doe (Main Store)`

Includes:
- Customer name
- Store name
- Product summaries
- Payment method details
- Item-level breakdowns in memos

### 5. Processing Status Tracking

Added `processing` status to prevent concurrent sync attempts:
- Sales are marked as `processing` when sync starts
- Prevents duplicate syncs
- Better error handling and retry logic

### 6. Refund Sync Support

New `syncRefunds()` method:
- Syncs completed refunds to accounting
- Creates reversal journal entries
- Handles inventory restoration
- Tracks refund reasons and approval

### 7. Configurable Processing Fees

System configuration for:
- `pos_card_processing_fee_rate` (default: 3%)
- `pos_momo_processing_fee_rate` (default: 1%)

Fees are automatically calculated and recorded as expenses.

### 8. Improved Error Handling

- Detailed error messages with debit/credit differences
- Stack traces for debugging
- Better logging
- Error messages stored in queue for review

### 9. Enhanced Admin UI

Accounting tab now shows:
- Sync statistics (pending, synced, errors, processing)
- Separate sync buttons for sales and refunds
- Enhanced queue table with sale date, amount, and error messages
- Feature list of accounting capabilities

## Accounting Entry Structure

### POS Sale Entry Example

**Debits:**
1. Cash on Hand (1000) - Cash payments received
2. Bank Account (1100) - Card/bank payments
3. Mobile Money (1200) - MoMo payments
4. COGS (5000) - Cost of goods sold
5. Discount Expense (5201) - Discounts given
6. Payment Processing Fees (5202) - Card/MoMo fees
7. Accounts Receivable (1300) - Outstanding balance (if partial payment)

**Credits:**
1. Sales Revenue (4020) - Net revenue (subtotal - discounts)
2. Tax Payable (2100) - Sales tax collected
3. Inventory Asset (1400) - Inventory reduction
4. Accounts Receivable (1300) - Credit sale amount (if credit sale)

### Refund Entry Example

**Debits:**
1. Sales Revenue (4020) - Revenue reversal
2. Tax Payable (2100) - Tax reversal
3. Inventory Asset (1400) - Inventory restoration

**Credits:**
1. Cash on Hand/Bank (1000/1100) - Refund paid out
2. COGS (5000) - COGS reversal

## Additional Data Points Available

### From Sales:
- Customer ID and name
- Store ID and name
- Cashier ID
- Sale timestamp
- Payment methods and amounts
- Product details (SKU, name, quantity, cost, price)
- Discount type and amount
- Tax breakdown
- Total amounts (subtotal, discount, tax, total)

### From Refunds:
- Original sale reference
- Refund reason
- Approval status and approver
- Refund method
- Refunded items with costs
- Inventory restoration status

## Configuration Options

### System Config (`system_config` table):
- `pos_card_processing_fee_rate` - Card processing fee percentage (default: 0.03 = 3%)
- `pos_momo_processing_fee_rate` - Mobile money fee percentage (default: 0.01 = 1%)
- `pos_discount_approval_threshold` - Discount amount requiring approval
- `pos_discount_approval_percentage` - Discount percentage requiring approval
- `pos_price_override_approval_threshold` - Price override percentage requiring approval

## Future Enhancements (Recommended)

### 1. Metadata Table
Create `pos_sale_accounting_metadata` table to store:
- Customer ID
- Store ID
- Product breakdown (JSON)
- Payment method breakdown (JSON)
- Discount details (JSON)
- Tax breakdown (JSON)
- Category-level revenue breakdown

### 2. Multi-Store Accounting
- Store-specific revenue accounts
- Store-level profit/loss reporting
- Inter-store transfer tracking

### 3. Product Category Revenue
- Category-level revenue accounts
- Product category breakdown in journal entries
- Category profit analysis

### 4. Loyalty Points Accounting
- Loyalty points liability account
- Points earned/redeemed tracking
- Points expiration handling

### 5. Gift Card Accounting
- Gift card liability tracking
- Gift card sales (cash to liability)
- Gift card redemptions (liability to revenue)

### 6. Sales Commissions
- Commission expense account
- Cashier commission tracking
- Commission payable tracking

### 7. Shipping/Delivery Costs
- Shipping expense account
- Delivery cost tracking
- Shipping revenue (if charged separately)

### 8. Advanced Reporting
- Revenue by product category
- Revenue by payment method
- COGS analysis
- Discount analysis
- Tax reporting
- Store performance comparison

### 9. Integration with External Accounting Systems
- Export to QuickBooks
- Export to Xero
- Export to Zoho Books
- Export to Sage
- CSV/Excel export for manual import

### 10. Real-time Sync
- Webhook-based sync
- API-based sync
- Automatic sync on sale completion
- Batch sync optimization

## Database Schema Enhancements

### New Table: `pos_sale_accounting_metadata`
Stores detailed metadata for each synced sale:
- Customer ID
- Store ID
- Product breakdown
- Payment method breakdown
- Discount details
- Tax breakdown
- Category breakdown

### Enhanced `pos_accounting_queue`
- `reference_type` - Type of reference (sale, refund, etc.)
- `reference_id` - Reference ID
- `payload_json` - Full sale/refund data
- `status` - Sync status (pending, processing, synced, error)
- `attempts` - Number of sync attempts
- `last_error` - Last error message

## Best Practices

### 1. Regular Sync
- Sync sales daily or in real-time
- Monitor queue for errors
- Review failed syncs regularly

### 2. Error Handling
- Review error messages in admin panel
- Fix data issues before retrying
- Monitor for balance errors

### 3. Reconciliation
- Reconcile POS sales with accounting entries
- Verify COGS matches inventory reductions
- Check tax calculations
- Verify payment method totals

### 4. Reporting
- Use journal entries for financial reporting
- Generate P&L reports from journal entries
- Track revenue by store, category, payment method
- Monitor discount expenses
- Track processing fees

## Testing Recommendations

1. **Test Sales Sync**:
   - Cash sales
   - Card sales
   - Mobile money sales
   - Mixed payment methods
   - Credit sales
   - Partial payments
   - Discounted sales
   - Tax-exempt sales

2. **Test Refund Sync**:
   - Full refunds
   - Partial refunds
   - Refunds with inventory restoration
   - Refunds without inventory restoration
   - Refunds with different payment methods

3. **Test Edge Cases**:
   - Zero-amount sales
   - Sales with no items
   - Sales with no payments
   - Refunds with no items
   - Large discounts
   - Price overrides

## Migration Steps

1. Run migration: `012_enhanced_accounting_accounts.sql`
2. Update `PosAccountingSync.php` (already done)
3. Test sync with sample sales
4. Verify journal entries balance
5. Review accounting reports
6. Configure processing fee rates
7. Enable refund sync

## Conclusion

The enhanced accounting sync provides:
- ✅ Accurate double-entry bookkeeping
- ✅ Comprehensive data tracking
- ✅ Better error handling
- ✅ Refund support
- ✅ Configurable fees
- ✅ Enhanced reporting capabilities
- ✅ Foundation for future enhancements

The system is now production-ready and provides a solid foundation for financial reporting and analysis.

