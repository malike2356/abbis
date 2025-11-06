# URL Security & Obfuscation Guide

## 🔒 **Overview**

The ABBIS system now uses URL routing and obfuscation to hide system structure and file paths from users. This enhances security by:

1. **Hiding File Structure**: Users can't see actual file paths or directory structure
2. **Obfuscated IDs**: Record IDs are encoded/encrypted in URLs
3. **Clean URLs**: User-friendly, SEO-friendly URLs
4. **Access Protection**: Direct access to sensitive directories is blocked

---

## 📋 **URL Mapping**

### **Old URLs (Hidden):**
```
/modules/dashboard.php
/modules/field-reports.php
/modules/config.php
/modules/receipt.php?id=123
```

### **New Clean URLs:**
```
/dashboard
/reports
/system/config
/receipt/aBcD123XyZ
```

---

## 🗺️ **Available Routes**

### **Public Routes:**
- `/login` - Login page
- `/logout` - Logout

### **Dashboard:**
- `/` or `/home` - Dashboard
- `/dashboard` - Dashboard

### **Reports:**
- `/reports` - Field Reports
- `/reports/new` - New Report
- `/reports/list` - Reports List
- `/receipt/{encoded_id}` - Receipt (encoded ID)
- `/technical/{encoded_id}` - Technical Report (encoded ID)

### **Operations:**
- `/materials` - Materials Management
- `/payroll` - Payroll
- `/finance` - Finance
- `/loans` - Loans
- `/clients` - Clients

### **System (Admin Only):**
- `/system` - System Management Hub
- `/system/config` - Configuration
- `/system/data` - Data Management
- `/system/keys` - API Keys
- `/system/users` - User Management
- `/system/zoho` - Zoho Integration
- `/system/looker` - Looker Studio
- `/system/elk` - ELK Stack

### **Help:**
- `/help` - Help Documentation

---

## 🔧 **Implementation**

### **1. Using Routes in PHP:**

```php
// Generate obfuscated URL
require_once 'includes/url-helper.php';
$dashboardUrl = route('modules/dashboard.php');
$receiptUrl = route('modules/receipt.php', ['id' => 123]);

// Or use module shortcuts
$reportsUrl = moduleUrl('reports');
```

### **2. Using Routes in HTML:**

```html
<!-- Old way (still works as fallback) -->
<a href="modules/dashboard.php">Dashboard</a>

<!-- New way (obfuscated) -->
<a href="/dashboard">Dashboard</a>
<a href="/receipt/<?php echo encodeId(123); ?>">View Receipt</a>
```

### **3. ID Obfuscation:**

```php
// Encode ID for URL
$encodedId = encodeId($reportId);
// Result: "aBcD123XyZ789..."

// Decode ID from URL
$reportId = decodeId($_GET['id']);
```

---

## 🛡️ **Security Features**

### **1. Directory Protection:**
- `/modules/` - Blocked (403 Forbidden)
- `/includes/` - Blocked (403 Forbidden)
- `/config/` - Blocked (403 Forbidden)

### **2. File Protection:**
- `.sql`, `.md`, `.log`, `.txt`, `.bak` files - Blocked (403 Forbidden)
- Hidden files (starting with `.`) - Blocked

### **3. Security Headers:**
- X-Frame-Options: SAMEORIGIN
- X-Content-Type-Options: nosniff
- X-XSS-Protection: 1; mode=block
- Server information hidden

---

## 📝 **Updating Existing Links**

### **In PHP Files:**

**Before:**
```php
<a href="modules/dashboard.php">Dashboard</a>
<a href="modules/receipt.php?id=<?php echo $id; ?>">Receipt</a>
```

**After:**
```php
<a href="/dashboard">Dashboard</a>
<a href="/receipt/<?php echo encodeId($id); ?>">Receipt</a>
```

### **In JavaScript:**

**Before:**
```javascript
window.location.href = 'modules/dashboard.php';
```

**After:**
```javascript
window.location.href = '/dashboard';
```

---

## 🔐 **ID Encoding Details**

IDs are encoded using:
- **HMAC-SHA256** for hash verification
- **Base64** encoding (URL-safe)
- **Secret Key** stored in `config/secret.key`

This ensures:
- IDs can't be guessed or manipulated
- Hash verification prevents tampering
- URL-safe encoding for compatibility

---

## ⚠️ **Important Notes**

1. **Backward Compatibility**: Old URLs will still work but are not recommended
2. **Secret Key**: Keep `config/secret.key` secure and backed up
3. **Asset Files**: CSS, JS, images in `/assets/` are accessible directly
4. **API Endpoints**: API files in `/api/` are accessible directly (for integrations)
5. **HTTPS**: Enable HTTPS redirect in `.htaccess` for production

---

## 🔄 **Migration Checklist**

- [ ] Update all navigation links
- [ ] Update form actions
- [ ] Update JavaScript redirects
- [ ] Update AJAX endpoints (if needed)
- [ ] Test all routes
- [ ] Update documentation
- [ ] Generate secret key (auto-generated on first use)
- [ ] Enable HTTPS in production

---

## 🐛 **Troubleshooting**

### **404 Errors:**
- Check `.htaccess` is enabled
- Verify mod_rewrite is active
- Check route is defined in `includes/router.php`

### **403 Errors (Forbidden):**
- Check authentication
- Verify role permissions
- Check file permissions

### **ID Decoding Fails:**
- Verify secret key exists
- Check encoding/decoding logic
- Ensure URL hasn't been modified

---

*Last Updated: November 2024*
*ABBIS Version: 3.2.0*

