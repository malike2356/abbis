# Unified Catalog Sync System

## Overview

The Unified Catalog Sync System automatically synchronizes product and inventory data across three systems:
- **ABBIS** (Main system - Resources/Materials)
- **CMS** (Content Management System - Products)
- **POS** (Point of Sale System)

## Architecture

### Source of Truth
- **`catalog_items` table** is the central source of truth for all product data
- All three systems read from and sync to `catalog_items`

### Sync Flow

```
┌─────────┐         ┌──────────────┐         ┌─────────┐
│  ABBIS  │ ──────> │ catalog_items│ <────── │   CMS   │
│Resources│         │  (Source of  │         │Products │
└─────────┘         │    Truth)   │         └─────────┘
                    └──────────────┘
                           │
                           │
                    ┌──────┴──────┐
                    │     POS     │
                    │  Products   │
                    └─────────────┘
```

## Components

### 1. UnifiedCatalogSyncService
**Location:** `includes/pos/UnifiedCatalogSyncService.php`

Main service that handles bidirectional sync:
- `syncCatalogToPos()` - Syncs catalog item changes to POS products
- `syncPosToCatalog()` - Syncs POS product changes to catalog
- `syncInventoryToPos()` - Syncs inventory from catalog to POS
- `syncInventoryToCatalog()` - Syncs inventory from POS to catalog
- `syncAll()` - Full system sync

### 2. UnifiedInventoryService
**Location:** `includes/pos/UnifiedInventoryService.php`

Handles inventory synchronization:
- `setCatalogStock()` - Sets absolute stock quantity in catalog
- `updateCatalogStock()` - Adjusts stock quantity (delta)
- `adjustPosInventory()` - Adjusts POS inventory and syncs to catalog
- `syncAllInventory()` - Syncs all inventory across systems

### 3. Database Triggers
**Location:** `database/migrations/pos/008_automatic_sync_triggers.sql`

Automatic database-level triggers:
- `trg_catalog_items_update_sync` - Syncs catalog updates to POS
- `trg_catalog_items_insert_sync` - Logs new catalog items for sync
- `trg_pos_products_update_sync` - Syncs POS updates to catalog

## Automatic Sync Points

### When Catalog Items are Updated (ABBIS/CMS)
1. **CMS Products** (`cms/admin/products.php`)
   - Product create/update → `UnifiedCatalogSyncService::syncCatalogToPos()`
   - Inventory changes → `UnifiedInventoryService::setCatalogStock()`

2. **ABBIS Resources** (`modules/resources.php`)
   - Catalog item create/update → `UnifiedCatalogSyncService::syncCatalogToPos()`
   - Inventory changes → `UnifiedInventoryService::setCatalogStock()`

### When POS Products are Updated
1. **POS Product Update** (`includes/pos/PosRepository::updateProduct()`)
   - Product update → `UnifiedCatalogSyncService::syncPosToCatalog()`

2. **POS Inventory Adjustments** (`includes/pos/PosRepository::adjustStock()`)
   - Uses `UnifiedInventoryService::adjustPosInventory()`

## What Gets Synced

### Product Details
- ✅ Name
- ✅ SKU
- ✅ Price (sell_price ↔ unit_price)
- ✅ Cost Price
- ✅ Active Status
- ✅ Description (if available)
- ✅ Category (auto-created if needed)

### Inventory
- ✅ Stock Quantity (stock_quantity / inventory_quantity)
- ✅ Distributed across POS stores
- ✅ Real-time updates

## Usage Examples

### Manual Sync (if needed)
```php
require_once 'includes/pos/UnifiedCatalogSyncService.php';

$syncService = new UnifiedCatalogSyncService();
$results = $syncService->syncAll();
// Returns: ['catalog_to_pos' => X, 'pos_to_catalog' => Y, 'inventory_synced' => Z]
```

### Sync Specific Product
```php
// Sync catalog item to POS
$syncService->syncCatalogToPos($catalogItemId);

// Sync POS product to catalog
$syncService->syncPosToCatalog($posProductId);
```

## Error Handling

- All sync operations are wrapped in try-catch blocks
- Errors are logged but don't fail the main operation
- Check error logs for sync failures:
  - `[CMS Products] Sync failed: ...`
  - `[POS Product Update] Catalog sync failed: ...`
  - `[Resources] POS sync failed: ...`

## Database Schema

### Sync Log Table
```sql
CREATE TABLE pos_sync_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50),
    catalog_item_id INT,
    pos_product_id INT,
    status ENUM('pending','completed','failed'),
    error_message TEXT,
    created_at TIMESTAMP,
    completed_at DATETIME
);
```

## Best Practices

1. **Always update catalog_items first** - It's the source of truth
2. **Let automatic sync handle propagation** - Don't manually update multiple systems
3. **Check sync logs** - Monitor `pos_sync_log` table for sync status
4. **Use UnifiedInventoryService** - For all inventory adjustments
5. **Test sync after schema changes** - Run `syncAll()` after migrations

## Troubleshooting

### Products not syncing
1. Check if `catalog_item_id` is set in `pos_products`
2. Verify SKU matches between systems
3. Check error logs for sync failures
4. Run manual sync: `$syncService->syncAll()`

### Inventory discrepancies
1. Use `UnifiedInventoryService::syncAllInventory()` to fix
2. Check `catalog_items.inventory_quantity` vs `pos_inventory.quantity_on_hand`
3. Verify store distribution logic

### Sync triggers not firing
1. Check if triggers exist: `SHOW TRIGGERS LIKE 'trg_%sync%'`
2. Re-run migration: `database/migrations/pos/008_automatic_sync_triggers.sql`
3. Verify MySQL version supports triggers

