# Client Portal - Milestone 2 (Payments & Accounting)

## 🎯 Objectives
- Enable clients to settle invoices online from the ABBIS portal
- Support Paystack and Flutterwave for card/MoMo transactions
- Record manual payment intents (mobile money, bank transfer, cash)
- Automatically reconcile payments with invoices and accounting journals

## ✅ Feature Highlights
- **Payment Methods:** Pulls active methods from `cms_payment_methods`. Paystack/Flutterwave trigger secure redirects; offline methods log a pending record.
- **Payment Workflow:** Clients select an invoice, enter amount (partial payments supported), choose a method, and are redirected to the appropriate gateway when required.
- **Gateway Bridge:** New pages handle gateway redirection (`client-portal/payment-gateway.php`) and callback verification (`client-portal/payment-callback.php`).
- **Accounting Sync:** Successful payments invoke `AccountingAutoTracker::trackClientInvoicePayment()` to debit the appropriate cash/bank account and credit Accounts Receivable (`1300`).
- **Activity Log:** Every action is logged to `client_portal_activities`. Payments themselves are stored in `client_payments` with status tracking.
- **Email Notifications:** Both client and finance admin receive confirmation emails when payments are initiated and when a transaction is confirmed.
- **Approvals & Downloads:** Clients can approve/decline quotes with a typed signature, view full quote/invoice details, and download printable HTML summaries for their records.

## 🗂️ Database Updates
- `client_payments` now includes `payment_method_id`, `gateway_provider`, and indexes/foreign keys referencing `cms_payment_methods`.
- Migration script: `database/client_portal_migration.sql`
- CLI installer updated: `scripts/setup-client-portal.php`

## 🔐 Security
- CSRF protection on payment form (`CSRF::getTokenField()`)
- Gateway callbacks validate provider signatures via Paystack/Flutterwave APIs
- Session-authenticated portal ensures only the owning client can initiate/complete a payment

## 🧾 File Overview
| Path | Purpose |
| --- | --- |
| `client-portal/payments.php` | Portal UI for outstanding invoices, payment history, and method selection |
| `client-portal/process-payment.php` | Validates input, instantiates payment service, routes to gateway |
| `client-portal/payment-gateway.php` | Bridge page that loads external checkout flows |
| `client-portal/payment-callback.php` | Verifies gateway response and finalises invoices/accounting |
| `includes/ClientPortal/ClientPaymentService.php` | Core business logic for portal payments (initiation, verification, accounting sync) |
| `client-portal/client-styles.css` | Styling updates for alerts, payment cards, badges |
| `modules/help.php` | New help section covering portal usage and configuration |
| `docs/CLIENT_PORTAL_MILESTONE2.md` | This document |

## ⚙️ Configuration Checklist
1. **Gateway Keys:** Enter API keys in CMS → Payment Methods for Paystack/Flutterwave (JSON config fields `public_key`, `secret_key`).
2. **Client Users:** Create ABBIS users with `role=client` and link `client_id` (auto-detected by email if missing).
3. **Run Migration:** `php scripts/setup-client-portal.php`
4. **Test Flow:**
   - Log in as client → `client-portal/login.php`
   - Verify invoices display with outstanding balances
   - Run a Paystack/Flutterwave test payment and confirm ledger entries

## 📈 Accounting Behaviour
- Journal entry number: `CLT-PAY-{paymentId}-{date}`
- Debit: `1000` (Cash), `1100` (Bank), or `1200` (Mobile Money) based on payment method
- Credit: `1300` (Accounts Receivable)
- Applies partial payments and updates `client_invoices.amount_paid`, `balance_due`, and status (`paid`/`partial`).

## 🔄 Manual Payment Handling
- Non-gateway methods remain in `pending` status until finance staff reconcile and update the record (future admin UI can build on `client_payments`).
- Notes field allows clients to supply reference numbers for manual verification.

## 🧪 Testing Notes
- Paystack test cards: <https://paystack.com/docs/payments/test-cards>
- Flutterwave test data: <https://developer.flutterwave.com/docs/test-cards>
- Confirm callback redirects the client to `client-portal/payments.php` with success/failure message.
- Validate accounting journal entry in `journal_entries` after successful payment.

## 📚 Related Documents
- [Client Portal - Milestone 1](CLIENT_PORTAL_MILESTONE1.md)
- [Accounting Integrations Explained](ACCOUNTING_INTEGRATIONS_EXPLAINED.md)
- [Complete System Features](COMPLETE_SYSTEM_FEATURES.md)
