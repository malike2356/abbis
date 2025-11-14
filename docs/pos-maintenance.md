## POS Maintenance Cheatsheet

### 1. Seed material mappings

Run the seeding script whenever you provision a new environment or add additional stores. It will:

- Create/update the `Field Materials` POS category
- Ensure core products (screen pipe, plain pipe, gravel) exist
- Configure sensible reorder thresholds per store
- Populate `pos_material_mappings` so field reports can decrement stock

```bash
php scripts/pos/seed-material-mappings.php
```

### 2. Sync POS products to the public catalog

Products flagged with `expose_to_shop = 1` are automatically mirrored to `catalog_items`. You can trigger a resync manually:

```bash
# Sync all exposed POS products
php scripts/pos/sync-catalog.php

# Sync a single product by POS product ID
php scripts/pos/sync-catalog.php 42
```

When a product is no longer exposed, its linked catalog item is disabled but retained for reference.

### 3. Refresh dashboard metrics

The POS dashboard fetches live metrics from `pos/api/reports.php`. Ensure cron or supervisors call the catalog/seed scripts after major data imports so the dashboard reflects accurate inventory and product data.

