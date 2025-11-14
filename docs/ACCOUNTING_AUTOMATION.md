# Automated Accounting System

## Overview

The ABBIS system now includes **fully automated accounting integration** that tracks all financial transactions system-wide without requiring manual data entry. Every money flow (inflow or outflow) is automatically recorded in the accounting system using proper double-entry bookkeeping principles.

## How It Works

The `AccountingAutoTracker` class automatically creates journal entries whenever financial transactions occur in the system. This ensures:

1. **Zero Manual Entry**: No need for accountants to manually key in transactions
2. **Real-time Tracking**: Transactions are recorded immediately when they occur
3. **Double-Entry Bookkeeping**: All entries follow proper accounting principles
4. **Complete Audit Trail**: Every transaction is traceable back to its source

## Automated Transaction Tracking

### 1. Field Reports (`api/save-report.php`)

**Automatically tracks:**
- **Revenue (Inflow)**:
  - Contract Sum → Revenue Account + Accounts Receivable
  - Rig Fee Collected → Revenue Account + Cash
  - Outstanding Rig Fee → Revenue Account + Accounts Receivable
  - Materials Income → Revenue Account + Cash
  - Cash Received → Cash (from Bank)

- **Expenses (Outflow)**:
  - Materials Cost → Expense Account + Cash
  - Wages → Expense Account + Cash
  - Daily Operating Expenses → Expense Account + Cash

- **Deposits/Transfers**:
  - MoMo Transfer → Mobile Money Account (from Cash)
  - Cash Given to Company → Bank Account (from Cash)
  - Bank Deposit → Bank Account (from Cash)

### 2. Loan Management (`modules/loans.php`)

**Automatically tracks:**
- **Loan Disbursement**: 
  - Debit: Worker Loans Receivable (Asset)
  - Credit: Cash (Asset)

- **Loan Repayment**:
  - Debit: Cash (Asset)
  - Credit: Worker Loans Receivable (Asset)

### 3. Materials Purchases (`api/update-materials.php`)

**Automatically tracks:**
- **Materials Purchase**:
  - Debit: Materials Inventory (Asset)
  - Credit: Cash (Asset)

### 4. Payroll Payments

Payroll is automatically tracked as part of field reports when wages are paid. Each payroll entry creates:
- Debit: Wages & Salaries Expense
- Credit: Cash

## Chart of Accounts

The system automatically creates a default chart of accounts with the following structure:

### Revenue Accounts (4000-4099)
- 4000: Contract Revenue
- 4010: Rig Fee Revenue
- 4020: Materials Sales Revenue
- 4090: Other Revenue

### Asset Accounts (1000-1999)
- 1000: Cash on Hand
- 1100: Bank Account
- 1200: Mobile Money
- 1300: Accounts Receivable
- 1400: Materials Inventory
- 1500: Worker Loans Receivable

### Expense Accounts (5000-5999)
- 5000: Materials Cost
- 5100: Wages & Salaries
- 5200: Operating Expenses
- 5990: Other Expenses

### Liability Accounts (2000-2999)
- 2000: Loans Payable
- 2100: Accounts Payable

## Viewing Accounting Records

All automatically created journal entries can be viewed in:
- **Accounting → Journal**: See all journal entries
- **Accounting → Ledgers**: View account-specific ledgers
- **Accounting → Trial Balance**: View trial balance
- **Accounting → P&L**: View profit and loss statement
- **Accounting → Balance Sheet**: View balance sheet

## Journal Entry Format

Each automated journal entry includes:
- **Entry Number**: Unique identifier (e.g., `FR-REP001-20241201`)
- **Date**: Transaction date
- **Reference**: Source transaction ID
- **Description**: Human-readable description
- **Debit/Credit Lines**: Properly balanced double-entry lines
- **Memo**: Additional details for each line item

## Error Handling

The system is designed to be resilient:
- If accounting tracking fails, it logs an error but **does not block** the original transaction
- This ensures business operations continue even if accounting has issues
- All errors are logged for review and correction

## Customization

Account mappings can be customized by modifying the `$accountMap` array in `includes/AccountingAutoTracker.php`. The system will automatically create any new accounts that don't exist.

## Benefits

1. **Time Savings**: Eliminates hours of manual data entry
2. **Accuracy**: Reduces human error in data entry
3. **Real-time Financials**: Always up-to-date financial records
4. **Compliance**: Proper double-entry bookkeeping ensures compliance
5. **Audit Trail**: Complete traceability of all transactions
6. **Scalability**: System handles any volume of transactions automatically

## Future Enhancements

Potential future improvements:
- Integration with external accounting software (QuickBooks, Zoho Books)
- Automated reconciliation
- Financial reporting and analytics
- Budget vs. actual comparisons
- Cash flow forecasting

## Technical Details

- **Class**: `AccountingAutoTracker` in `includes/AccountingAutoTracker.php`
- **Database Tables**: 
  - `chart_of_accounts`
  - `journal_entries`
  - `journal_entry_lines`
- **Integration Points**:
  - `api/save-report.php` (Field Reports)
  - `modules/loans.php` (Loans)
  - `api/update-materials.php` (Materials)

## Support

For issues or questions about the automated accounting system, check:
1. Error logs for any tracking failures
2. Journal entries to verify transactions are being recorded
3. Account balances to ensure proper posting

