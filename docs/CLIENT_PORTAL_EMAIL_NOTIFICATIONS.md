# Client Portal Email Notifications - Implementation Guide

## ✅ **What's Been Implemented**

### 1. **Welcome Email - Client Account Creation** ✅
- **Location**: `modules/users.php`
- **Trigger**: When a new user account is created with role `ROLE_CLIENT`
- **What it does**:
  - Automatically sends welcome email with portal login link
  - Includes username and password (if provided)
  - Links user to client record if email matches
  - Uses SSO token for seamless login

### 2. **Quote Notification** ✅
- **Service**: `ClientPortalNotificationService::sendQuoteNotification()`
- **Hook**: `notifyQuoteSent($quoteId)` in `ClientPortalHooks.php`
- **What it does**:
  - Sends email when quote is sent to client
  - Includes quote details (number, amount, valid until)
  - Provides direct link to view quote in portal
  - Uses SSO for automatic login

### 3. **Invoice Notification** ✅
- **Service**: `ClientPortalNotificationService::sendInvoiceNotification()`
- **Hook**: `notifyInvoiceSent($invoiceId)` in `ClientPortalHooks.php`
- **What it does**:
  - Sends email when invoice is issued
  - Includes invoice details (number, amount, balance due, due date)
  - Provides "Pay Online Now" button if balance > 0
  - Direct links to view invoice and make payment

---

## 🔧 **How to Integrate Quote/Invoice Notifications**

### **Option 1: Using Hooks (Recommended)**

Whenever you update quote or invoice status in your code, add the hook:

```php
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalHooks.php';

// Example: When updating quote status to 'sent'
$quoteId = 123;
$oldStatus = 'draft';
$newStatus = 'sent';

$stmt = $pdo->prepare("UPDATE client_quotes SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $quoteId]);

// Send notification if status changed to 'sent'
checkAndNotifyQuoteStatus($quoteId, $oldStatus, $newStatus);
```

```php
// Example: When updating invoice status to 'sent'
$invoiceId = 456;
$oldStatus = 'draft';
$newStatus = 'sent';

$stmt = $pdo->prepare("UPDATE client_invoices SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $invoiceId]);

// Send notification if status changed to 'sent'
checkAndNotifyInvoiceStatus($invoiceId, $oldStatus, $newStatus);
```

### **Option 2: Direct Service Call**

```php
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalNotificationService.php';

$notificationService = new ClientPortalNotificationService();

// Send quote notification
$notificationService->sendQuoteNotification($quoteId);

// Send invoice notification
$notificationService->sendInvoiceNotification($invoiceId);
```

### **Option 3: API Endpoint**

You can also trigger notifications via API:

```php
// Send quote notification
file_get_contents(app_url('api/client-portal-notifications.php?action=quote_sent&quote_id=' . $quoteId));

// Send invoice notification
file_get_contents(app_url('api/client-portal-notifications.php?action=invoice_sent&invoice_id=' . $invoiceId));
```

---

## 📍 **Where to Add Integration**

### **For Quotes:**

Add notification hooks in:
1. **Admin quote management interface** - When admin marks quote as "sent"
2. **API endpoints** - When quote status is updated via API
3. **Bulk operations** - When sending multiple quotes
4. **Automated workflows** - When quotes are auto-generated

**Example locations to check:**
- `cms/admin/quotes.php` - Quote management
- Any API that updates `client_quotes.status`
- CRM modules that create quotes

### **For Invoices:**

Add notification hooks in:
1. **Invoice generation scripts** - When invoices are created
2. **Admin invoice interface** - When admin marks invoice as "sent"
3. **API endpoints** - When invoice status is updated
4. **Automated billing** - When invoices are auto-generated

**Example locations to check:**
- Invoice generation modules
- Any API that updates `client_invoices.status`
- Billing/accounting modules

---

## 🎯 **Example Integration Code**

### **Example 1: Quote Status Update**

```php
<?php
// In your quote management code
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalHooks.php';

// Get current status
$stmt = $pdo->prepare("SELECT status FROM client_quotes WHERE id = ?");
$stmt->execute([$quoteId]);
$oldStatus = $stmt->fetchColumn();

// Update status
$newStatus = 'sent';
$stmt = $pdo->prepare("UPDATE client_quotes SET status = ? WHERE id = ?");
$stmt->execute([$newStatus, $quoteId]);

// Send notification if status changed to 'sent'
if ($newStatus === 'sent' && $oldStatus !== 'sent') {
    checkAndNotifyQuoteStatus($quoteId, $oldStatus, $newStatus);
}
```

### **Example 2: Invoice Creation**

```php
<?php
// In your invoice creation code
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalHooks.php';

// Create invoice
$stmt = $pdo->prepare("
    INSERT INTO client_invoices (client_id, invoice_number, total_amount, balance_due, status, issue_date, due_date)
    VALUES (?, ?, ?, ?, 'sent', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY))
");
$stmt->execute([$clientId, $invoiceNumber, $totalAmount, $balanceDue]);
$invoiceId = $pdo->lastInsertId();

// Send notification immediately since status is 'sent'
notifyInvoiceSent($invoiceId);
```

### **Example 3: Bulk Quote Sending**

```php
<?php
// In your bulk operations
require_once __DIR__ . '/../includes/ClientPortal/ClientPortalHooks.php';

$quoteIds = [1, 2, 3, 4, 5]; // Array of quote IDs to send

foreach ($quoteIds as $quoteId) {
    // Update status
    $stmt = $pdo->prepare("UPDATE client_quotes SET status = 'sent' WHERE id = ?");
    $stmt->execute([$quoteId]);
    
    // Send notification
    notifyQuoteSent($quoteId);
}
```

---

## 📧 **Email Templates**

All emails are HTML-formatted with:
- Professional design with company branding
- Responsive layout
- Clear call-to-action buttons
- Direct portal links with SSO
- Company contact information

### **Welcome Email Includes:**
- Portal login credentials
- Portal features overview
- Direct login link
- Security reminder to change password

### **Quote Email Includes:**
- Quote number and details
- Total amount
- Valid until date
- "View Quote in Portal" button

### **Invoice Email Includes:**
- Invoice number and details
- Total amount and balance due
- Due date
- "View Invoice" button
- "Pay Online Now" button (if balance > 0)

---

## ⚙️ **Configuration**

### **Company Information**

Ensure these are set in `system_config` table:

```sql
INSERT INTO system_config (config_key, config_value, description) VALUES
('company_name', 'Your Company Name', 'Company name for emails'),
('company_email', 'info@yourcompany.com', 'Company contact email');
```

### **Email Settings**

The notification service uses the existing `Email` class, which supports:
- Native PHP mail
- SMTP
- API-based email services
- Email queueing

Configure email settings in your system configuration.

---

## 🔍 **Testing**

### **Test Welcome Email:**
1. Create a new user with role `client` in User Management
2. Check email inbox for welcome email
3. Verify login link works

### **Test Quote Notification:**
```php
require_once __DIR__ . '/includes/ClientPortal/ClientPortalHooks.php';
notifyQuoteSent(1); // Replace 1 with actual quote ID
```

### **Test Invoice Notification:**
```php
require_once __DIR__ . '/includes/ClientPortal/ClientPortalHooks.php';
notifyInvoiceSent(1); // Replace 1 with actual invoice ID
```

---

## 📝 **Next Steps**

1. **Find where quotes are created/updated** and add hooks
2. **Find where invoices are created/updated** and add hooks
3. **Test email delivery** to ensure emails are being sent
4. **Customize email templates** if needed (in `ClientPortalNotificationService.php`)

---

## 🐛 **Troubleshooting**

### **Emails Not Sending:**
- Check email configuration in system settings
- Check error logs: `error_log('Client portal notification error: ...')`
- Verify client email addresses are valid
- Check email queue if using queueing

### **Links Not Working:**
- Verify `app_url()` function returns correct base URL
- Check SSO token generation
- Ensure client user accounts exist and are linked to clients

---

*Last Updated: January 2025*
*ABBIS Version: 3.2.0*

