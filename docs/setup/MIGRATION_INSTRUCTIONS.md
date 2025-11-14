# Material Store System Migration Instructions

## ⚠️ IMPORTANT: Run Migration First

The Material Store system requires database tables to be created. **You must run the migration before using the system.**

## Quick Fix

**Open this URL in your browser:**
```
http://localhost:8080/abbis3.2/modules/admin/run-material-store-migration.php
```

Then click the **"Run Migration"** button.

## What the Migration Creates

1. **`material_store_inventory`** - Tracks materials in the Material Store
2. **`material_store_transactions`** - Logs all material movements
3. **Field Reports Columns** - Adds tracking fields for remaining materials

## After Migration

Once the migration is complete, you can:
- Access the Material Store Dashboard: `modules/material-store-dashboard.php`
- Transfer materials from POS to Material Store
- Use materials in field reports
- View analytics and reports

## Troubleshooting

If you see errors:
1. Make sure you're logged in with proper permissions
2. Check that the database connection is working
3. Verify the migration file exists: `database/migrations/010_material_store_system.sql`

## Alternative: Manual SQL Execution

If the web-based migration doesn't work, you can run the SQL manually:

1. Open: `database/migrations/010_material_store_system.sql`
2. Copy the SQL statements
3. Execute them in phpMyAdmin or MySQL client

---

**Status:** Run the migration to fix the "Table not found" error.

