# How to Activate Automatic Catalog Sync

## Step 1: Run the Database Migration

You have **two options** to run the migration:

### Option A: Via Web Interface (Recommended)
1. Go to: `http://localhost:8080/abbis3.2/modules/database-migrations.php`
2. Look for the migration file: `migrations/pos/008_automatic_sync_triggers.sql`
3. Select it and click "Run Migration"
4. Confirm the execution

### Option B: Via Command Line
```bash
cd /opt/lampp/htdocs/abbis3.2
mysql -u root -p your_database_name < database/migrations/pos/008_automatic_sync_triggers.sql
```

**Note:** If the migration file isn't showing in the web interface, you may need to copy it to the main `database/` folder:
```bash
cp database/migrations/pos/008_automatic_sync_triggers.sql database/008_automatic_sync_triggers.sql
```

## Step 2: Verify the Triggers Were Created

Run this SQL query to check:
```sql
SHOW TRIGGERS LIKE 'trg_%sync%';
```

You should see:
- `trg_catalog_items_update_sync`
- `trg_catalog_items_insert_sync`
- `trg_pos_products_update_sync`

## Step 3: Test the Sync

### Test 1: Edit a Product in CMS
1. Go to: `http://localhost:8080/abbis3.2/cms/admin/products.php`
2. Edit any product (change name, price, etc.)
3. Save
4. Check POS: `http://localhost:8080/abbis3.2/pos/index.php?action=admin&tab=catalog`
5. The product should be updated automatically

### Test 2: Edit a Product in POS
1. Go to: `http://localhost:8080/abbis3.2/pos/index.php?action=admin&tab=catalog`
2. Edit any product
3. Save
4. Check CMS: `http://localhost:8080/abbis3.2/cms/admin/products.php`
5. The product should be updated automatically

### Test 3: Edit Inventory in ABBIS
1. Go to: `http://localhost:8080/abbis3.2/modules/resources.php`
2. Edit inventory quantity for any item
3. Save
4. Check POS inventory: `http://localhost:8080/abbis3.2/pos/index.php?action=admin&tab=inventory`
5. The inventory should be updated automatically

## Step 4: Monitor Sync Status (Optional)

Check the sync log table:
```sql
SELECT * FROM pos_sync_log ORDER BY created_at DESC LIMIT 10;
```

## Troubleshooting

### Triggers Not Created?
- Check MySQL error log
- Verify you have CREATE TRIGGER permissions
- Try running the migration manually via command line

### Sync Not Working?
1. Check error logs:
   - PHP error log: `/opt/lampp/logs/php_error_log`
   - Application logs: Check for `[CMS Products] Sync failed` or `[POS Product Update] Catalog sync failed`

2. Verify catalog_item_id links:
   ```sql
   SELECT p.id, p.name, p.catalog_item_id, ci.id as catalog_id, ci.name as catalog_name
   FROM pos_products p
   LEFT JOIN catalog_items ci ON p.catalog_item_id = ci.id
   LIMIT 10;
   ```

3. Test manual sync:
   ```php
   require_once 'includes/pos/UnifiedCatalogSyncService.php';
   $sync = new UnifiedCatalogSyncService();
   $results = $sync->syncAll();
   print_r($results);
   ```

## What's Already Working

✅ **Code is already integrated** - All sync code is in place
✅ **Automatic sync on updates** - Happens automatically when you edit products
✅ **Error handling** - Errors are logged but don't break operations

## Next Steps After Activation

1. ✅ Run the migration (Step 1)
2. ✅ Test the sync (Step 3)
3. ✅ Monitor for any errors
4. ✅ Enjoy automatic synchronization!

The system will now automatically keep all three systems (ABBIS, CMS, POS) in sync whenever you make changes!

