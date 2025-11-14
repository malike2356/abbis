# Database Import Guide for Hostinger

## Your Database Details

- **Database Name:** `u411287710_abbis32`
- **Database User:** `u411287710_abbisuser`
- **Status:** Empty (needs schema import)

---

## 📋 Import Process

### **Step 1: Export Database from Local (If You Have Data)**

If you have data in your local database:

1. **Open phpMyAdmin locally** (http://localhost/phpmyadmin)
2. **Select your local ABBIS database**
3. **Click Export tab**
4. **Choose "Custom" export method**
5. **Select all tables**
6. **Click "Go"** to download SQL file
7. **Save the file** (e.g., `abbis_backup.sql`)

---

### **Step 2: Import Schema to Hostinger**

#### **Option A: Import Main Schema First (Recommended)**

1. **Go to Hostinger phpMyAdmin:**
   - Databases → Management → phpMyAdmin
   - Select database: `u411287710_abbis32`

2. **Click "Import" tab**

3. **Choose File:**
   - Click "Choose File"
   - Select `database/schema.sql` from your deployment package
   - Or upload from your local computer

4. **Import Settings:**
   - Format: SQL
   - Character set: utf8mb4
   - Click "Go"

5. **Wait for import to complete**

#### **Option B: Import All Migrations (If Fresh Install)**

After importing `schema.sql`, import these migration files in order:

1. `database/schema_updates.sql` - Core updates
2. `database/crm_migration.sql` - CRM system
3. `database/cms_migration.sql` - CMS system
4. `database/client_portal_migration.sql` - Client portal
5. `database/accounting_migration.sql` - Accounting system
6. `database/catalog_migration.sql` - Catalog system
7. `database/maintenance_assets_inventory_migration.sql` - Maintenance
8. `database/rig_tracking_migration.sql` - Rig tracking
9. `database/ai_migration.sql` - AI features
10. `database/migrations/pos/001_create_pos_tables.sql` - POS system
11. `database/migrations/pos/002_integrations.sql` - POS integrations
12. Continue with other POS migrations as needed

**Note:** Import them one by one, or combine them into a single SQL file.

---

### **Step 3: Import Your Data (If You Have Existing Data)**

If you exported data from your local database:

1. **In Hostinger phpMyAdmin:**
   - Select database: `u411287710_abbis32`
   - Click "Import" tab
   - Choose your exported SQL file
   - Click "Go"
   - Wait for import

---

### **Step 4: Create Admin User (If Fresh Install)**

If this is a fresh install, create an admin user:

1. **In phpMyAdmin, go to SQL tab**

2. **Run this query:**
   ```sql
   INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `full_name`, `is_active`) 
   VALUES ('admin', 'admin@kariboreholes.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Administrator', 1);
   ```

3. **Default password:** `password` (change immediately after first login!)

---

## 🔧 Quick Import Script

If you want to import everything at once, you can create a combined SQL file:

1. **Combine all SQL files** into one file
2. **Upload to Hostinger**
3. **Import via phpMyAdmin**

Or use the migration runner (if available on server):
```bash
php database/run_migration.php
```

---

## ✅ Verification

After import, verify:

1. **Check tables exist:**
   - In phpMyAdmin, you should see many tables
   - Key tables: `users`, `clients`, `field_reports`, `rigs`, `workers`

2. **Check admin user:**
   - Go to `users` table
   - Verify admin user exists

3. **Test connection:**
   - Update `config/deployment.php` with database credentials
   - Test login to ABBIS

---

## 📋 Import Order (Complete List)

For a complete fresh install, import in this order:

1. ✅ `database/schema.sql` - **Main schema (START HERE)**
2. ✅ `database/schema_updates.sql` - Core updates
3. ✅ `database/crm_migration.sql` - CRM
4. ✅ `database/cms_migration.sql` - CMS
5. ✅ `database/client_portal_migration.sql` - Client portal
6. ✅ `database/accounting_migration.sql` - Accounting
7. ✅ `database/catalog_migration.sql` - Catalog
8. ✅ `database/maintenance_assets_inventory_migration.sql` - Maintenance
9. ✅ `database/rig_tracking_migration.sql` - Rig tracking
10. ✅ `database/ai_migration.sql` - AI features
11. ✅ `database/migrations/pos/001_create_pos_tables.sql` - POS base
12. ✅ `database/migrations/pos/002_integrations.sql` - POS integrations
13. ✅ Continue with other POS migrations as needed

---

## 🆘 Troubleshooting

### **"Table already exists" Error**
- Some tables might already exist
- This is OK - the migrations use `CREATE TABLE IF NOT EXISTS`
- Continue with next migration

### **"Foreign key constraint fails"**
- Import tables in the correct order
- Start with `schema.sql` first
- Then import migrations in order

### **"Import file too large"**
- Hostinger usually allows up to 50MB
- If file is too large, split into smaller files
- Or use command line import via Terminal

### **"Access denied"**
- Verify database user has proper permissions
- Check database credentials in Hostinger panel

---

## 💡 Pro Tip

**Create a Combined SQL File:**

You can combine all SQL files into one for easier import:

```bash
# On your local computer
cat database/schema.sql \
    database/schema_updates.sql \
    database/crm_migration.sql \
    database/cms_migration.sql \
    database/client_portal_migration.sql \
    database/accounting_migration.sql \
    database/catalog_migration.sql \
    > combined_schema.sql
```

Then upload and import `combined_schema.sql` once.

---

**Last Updated:** November 2025
**Status:** Ready for Database Import ✅

