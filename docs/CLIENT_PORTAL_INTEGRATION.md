# Client Portal Integration Analysis

## Overview

This document explains how the Client Portal integrates with ABBIS clients, CMS customers, and POS customers.

## Integration Status

### ✅ **ABBIS Clients - FULLY INTEGRATED**

**Status:** Deeply integrated - uses the same database table

**How it works:**
- Client Portal uses the main `clients` table from ABBIS
- Users with `ROLE_CLIENT` can access the portal
- Client linking happens via:
  1. `users.client_id` field (direct link)
  2. Email matching (automatic linking if email matches)
  3. Manual assignment by admin

**Code References:**
- `client-portal/auth-check.php` - Links users to clients
- `includes/sso.php` - SSO token generation for client portal access
- `modules/crm.php` - Client management in ABBIS

**Features:**
- ✅ Clients can view their quotes, invoices, and payments
- ✅ Admins can view all clients via SSO (admin mode)
- ✅ Automatic client-user linking by email
- ✅ Client portal activities logged per client

---

### ⚠️ **CMS Customers - PARTIALLY INTEGRATED**

**Status:** Separate table, but can create ABBIS clients

**How it works:**
- CMS has its own `cms_customers` table for ecommerce
- Quote requests from CMS can create ABBIS clients
- No automatic sync between CMS customers and ABBIS clients
- POS can link to CMS customers via unified entity linking

**Code References:**
- `cms/public/quote.php` - Creates ABBIS client from quote request
- `database/migrations/pos/013_unified_entity_linking.sql` - Links POS to CMS customers

**Current Limitations:**
- ❌ CMS customers cannot directly access client portal
- ❌ No automatic account creation for CMS customers
- ⚠️ Manual process: CMS quote → ABBIS client → User account → Client portal access

**Integration Points:**
- Quote requests can create ABBIS clients
- POS can reference CMS customers as entities
- No direct client portal access for CMS-only customers

---

### ✅ **POS Customers - FULLY INTEGRATED**

**Status:** Uses ABBIS clients table directly

**How it works:**
- POS sales reference `clients.id` via `customer_id` foreign key
- Same clients used in ABBIS are used in POS
- POS can also link to other entities (workers, CMS customers) via unified entity system

**Code References:**
- `database/migrations/pos/001_create_pos_tables.sql` - POS sales table
- `database/migrations/pos/013_unified_entity_linking.sql` - Unified entity linking

**Features:**
- ✅ POS sales linked to ABBIS clients
- ✅ Client portal can show POS-related invoices (if implemented)
- ✅ Same client data across ABBIS and POS
- ✅ Unified entity linking allows POS to reference multiple entity types

---

## Client Portal Access Methods

### 1. **Direct Client Login**
- User with `ROLE_CLIENT` role
- Linked to client via `users.client_id` or email match
- Views only their own data

### 2. **Admin SSO Access**
- ABBIS admin/super admin
- SSO token-based authentication
- Can view all clients or specific client
- Admin mode indicator shown

### 3. **Client Account Creation Flow**

**Current Process:**
1. Client created in ABBIS (`clients` table)
2. User account created with `ROLE_CLIENT`
3. User linked to client via `users.client_id` or email
4. Client can log into portal

**CMS Quote Request Flow:**
1. Customer submits quote on CMS website
2. Quote saved to `cms_quote_requests`
3. Admin can create ABBIS client from quote
4. User account created and linked
5. Client can access portal

---

## Database Schema

### Main Clients Table (ABBIS)
```sql
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  -- CRM fields added via crm_migration.sql
  `company_type` VARCHAR(50),
  `website` VARCHAR(255),
  `status` ENUM('active', 'inactive', 'lead', 'prospect', 'customer'),
  -- ... more fields
  PRIMARY KEY (`id`)
);
```

### Users Table (Links to Clients)
```sql
-- Users can be linked to clients via:
users.client_id → clients.id
-- Or matched by email:
users.username/email → clients.email
```

### CMS Customers Table (Separate)
```sql
CREATE TABLE `cms_customers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  -- Separate from ABBIS clients
  -- Used for ecommerce only
);
```

### POS Sales (References Clients)
```sql
CREATE TABLE `pos_sales` (
  `customer_id` INT(11),
  FOREIGN KEY (`customer_id`) REFERENCES `clients` (`id`)
  -- Also supports unified entity linking
);
```

---

## Integration Gaps & Recommendations

### Current Gaps

1. **CMS Customers → Client Portal**
   - ❌ No direct access for CMS-only customers
   - ⚠️ Requires manual client creation in ABBIS
   - **Recommendation:** Auto-create ABBIS client when CMS customer makes first purchase

2. **Unified Client Management**
   - ⚠️ CMS customers separate from ABBIS clients
   - ⚠️ No single view of all customer types
   - **Recommendation:** Consider unified customer table or sync mechanism

3. **Client Portal Features**
   - ✅ Quotes, invoices, payments working
   - ⚠️ POS purchase history not shown
   - ⚠️ CMS order history not shown
   - **Recommendation:** Add POS and CMS order history to client portal

### Recommended Enhancements

1. **Auto-Sync CMS Customers**
   ```php
   // When CMS customer makes purchase:
   // 1. Check if ABBIS client exists (by email)
   // 2. If not, create ABBIS client
   // 3. Create user account with ROLE_CLIENT
   // 4. Send welcome email with portal access
   ```

2. **Unified Customer Dashboard**
   - Show ABBIS quotes/invoices
   - Show POS purchase history
   - Show CMS order history
   - All in one client portal view

3. **Cross-System Client Lookup**
   - Search clients across ABBIS, CMS, POS
   - Unified client profile view
   - Link related entities automatically

---

## Summary

### Integration Depth

| System | Integration Level | Shared Data | Portal Access |
|--------|------------------|-------------|---------------|
| **ABBIS Clients** | ✅ **Deep** | Same table | ✅ Full access |
| **POS Customers** | ✅ **Deep** | Same table | ✅ Full access |
| **CMS Customers** | ⚠️ **Partial** | Separate table | ❌ No direct access |

### Key Findings

1. ✅ **Client Portal is deeply integrated with ABBIS clients**
   - Uses same database table
   - Direct user-client linking
   - Full SSO support for admins

2. ✅ **POS is fully integrated**
   - Uses same clients table
   - Sales linked to ABBIS clients
   - Unified entity system allows flexibility

3. ⚠️ **CMS integration is partial**
   - Separate customer table
   - Quote requests can create ABBIS clients
   - No automatic portal access for CMS-only customers

### Conclusion

**The client portal is deeply integrated with ABBIS clients and POS customers** (they use the same table). **CMS customers have partial integration** - they can become ABBIS clients through quote requests, but CMS-only customers cannot directly access the client portal.

**To enable full CMS integration:**
1. Implement auto-sync from CMS customers to ABBIS clients
2. Add CMS order history to client portal
3. Create unified customer management interface

---

**Last Updated:** November 2025

