# Accounting Integrations - How It Works

## 📋 **Overview**

The Accounting Integrations page (`modules/accounting.php?action=integrations`) is designed to connect ABBIS to external accounting software like **QuickBooks** and **Zoho Books**. This allows you to export your ABBIS journal entries and financial data to these external systems.

---

## 🔄 **Two Types of Integration**

### **1. Internal Automatic Accounting** ✅ (Fully Working)
**What it does**: Automatically creates journal entries in ABBIS's internal accounting system whenever financial transactions occur.

**Status**: ✅ **Fully Implemented and Working**

**How it works**:
- Every financial transaction (field reports, loans, materials, payroll, assets, CMS payments) automatically creates journal entries
- Uses double-entry bookkeeping
- All entries are stored in ABBIS's `journal_entries` and `journal_entry_lines` tables
- View entries in: **Accounting → Journal**

**No configuration needed** - it works automatically!

---

### **2. External Accounting Software Integration** ⚠️ (Partially Implemented)
**What it does**: Exports ABBIS journal entries to external accounting software (QuickBooks, Zoho Books).

**Status**: ⚠️ **Framework Ready, OAuth Flow Not Complete**

**Current State**:
- ✅ **Credential Storage**: You can save QuickBooks/Zoho Books credentials (Client ID, Client Secret, Redirect URI)
- ✅ **Database Table**: `accounting_integrations` table stores credentials
- ⚠️ **OAuth Flow**: Not fully implemented yet (placeholder)
- ⚠️ **Data Export**: Export functionality is marked as "placeholder" in `api/accounting-api.php`

---

## 📍 **How the Integrations Page Works**

### **Current Functionality**

1. **Access**: Navigate to `Accounting → Integrations` or `modules/accounting.php?action=integrations`

2. **Configure Integration**:
   - Select Provider: QuickBooks or ZohoBooks
   - Enter Client ID (from the accounting software's developer portal)
   - Enter Client Secret (from the accounting software's developer portal)
   - Enter Redirect URI (callback URL for OAuth)
   - Click "Save Integration"

3. **What Gets Stored**:
   - Credentials are saved in `accounting_integrations` table
   - Status is marked as "Active"
   - Can view saved integrations in the table below

### **What's Missing (Not Yet Implemented)**

1. **OAuth Authentication Flow**:
   - The page stores credentials but doesn't initiate OAuth flow
   - No "Connect" button to authorize with QuickBooks/Zoho
   - No token management (access_token, refresh_token)

2. **Data Export Functionality**:
   - `api/accounting-api.php` has placeholder endpoints:
     - `export_qb` - QuickBooks export (placeholder)
     - `export_zoho` - Zoho Books export (placeholder)
   - These need to be implemented to actually sync journal entries

3. **Automatic Sync**:
   - No scheduled/automatic sync functionality
   - No manual "Sync Now" button

---

## 🔗 **Alternative: Zoho Integration (Already Working)**

**Note**: There's a **separate Zoho integration** that IS working:

- **Location**: `modules/zoho-integration.php` (in System menu)
- **Status**: ✅ Fully Implemented
- **Services**: Zoho CRM, Zoho Inventory, **Zoho Books**, Zoho Payroll, Zoho HR
- **How it works**:
  1. Configure OAuth credentials
  2. Click "Connect" to authorize
  3. Click "Sync Now" to sync data
  4. **Zoho Books** syncs field reports as invoices

**This is different from the Accounting Integrations page!**

---

## 🎯 **How It Should Work (When Complete)**

### **QuickBooks Integration**

1. **Setup**:
   - Create app in QuickBooks Developer Portal
   - Get Client ID and Client Secret
   - Configure Redirect URI
   - Save in ABBIS Accounting Integrations page

2. **Connect**:
   - Click "Connect to QuickBooks"
   - Redirected to QuickBooks for authorization
   - Authorize and get access token
   - Token stored in `accounting_integrations` table

3. **Export**:
   - Click "Export to QuickBooks"
   - ABBIS journal entries are converted to QuickBooks format
   - Sent to QuickBooks API
   - Creates journal entries in QuickBooks

### **Zoho Books Integration**

1. **Setup**: Same as QuickBooks
2. **Connect**: OAuth flow with Zoho
3. **Export**: Journal entries → Zoho Books journal entries

---

## 📊 **Current Integration Status**

| Feature | Status | Notes |
|---------|--------|-------|
| Credential Storage | ✅ Working | Can save Client ID, Secret, Redirect URI |
| OAuth Flow | ⚠️ Not Implemented | Needs OAuth authorization flow |
| Token Management | ⚠️ Not Implemented | No access/refresh token handling |
| Data Export | ⚠️ Placeholder | Export endpoints exist but not functional |
| QuickBooks Export | ⚠️ Not Implemented | Needs QuickBooks API integration |
| Zoho Books Export | ⚠️ Not Implemented | Use separate Zoho integration instead |

---

## 💡 **Recommendations**

### **For Now**:

1. **Use Internal Accounting**: 
   - All transactions are automatically tracked in ABBIS
   - View in: **Accounting → Journal**
   - Generate reports: **Accounting → P&L**, **Balance Sheet**, etc.

2. **For Zoho Books**:
   - Use the **Zoho Integration** page (`modules/zoho-integration.php`)
   - This is fully functional and can sync invoices to Zoho Books

3. **For QuickBooks**:
   - The framework is ready but needs implementation
   - Can manually export data if needed

### **To Complete the Integration**:

1. **Implement OAuth Flow**:
   - Add "Connect" button to initiate OAuth
   - Handle OAuth callback
   - Store access/refresh tokens
   - Implement token refresh

2. **Implement Export Functionality**:
   - Create QuickBooks API adapter
   - Convert ABBIS journal entries to QuickBooks format
   - Send to QuickBooks API
   - Handle errors and retries

3. **Add Sync Options**:
   - Manual "Sync Now" button
   - Scheduled automatic sync
   - Sync status and logs

---

## 🔍 **Where to Find It**

- **Accounting Integrations Page**: `modules/accounting.php?action=integrations`
- **Zoho Integration (Working)**: `modules/zoho-integration.php`
- **Internal Accounting**: `modules/accounting.php` (Journal, Ledgers, Reports)

---

## 📝 **Summary**

**Current State**:
- ✅ Internal automatic accounting: **Fully Working**
- ⚠️ External accounting integrations: **Framework Ready, Needs Implementation**
- ✅ Zoho Books (via separate integration): **Fully Working**

**What You Can Do Now**:
- Use internal accounting system (fully automated)
- Export data manually if needed
- Use Zoho integration for Zoho Books sync

**What Needs Implementation**:
- OAuth flow for QuickBooks/Zoho Books
- Actual export functionality
- Token management and refresh

---

**Last Updated**: December 2024


