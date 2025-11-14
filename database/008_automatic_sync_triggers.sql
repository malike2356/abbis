-- Automatic Sync Triggers
-- These triggers automatically sync catalog_items changes to POS and vice versa

DELIMITER $$

-- Trigger: After catalog_items UPDATE - sync to POS products
DROP TRIGGER IF EXISTS trg_catalog_items_update_sync$$
CREATE TRIGGER trg_catalog_items_update_sync
AFTER UPDATE ON catalog_items
FOR EACH ROW
BEGIN
    -- Only sync if product details changed (not just timestamp)
    IF (OLD.name != NEW.name OR 
        OLD.sku != NEW.sku OR 
        OLD.sell_price != NEW.sell_price OR 
        OLD.cost_price != NEW.cost_price OR 
        OLD.is_active != NEW.is_active OR
        OLD.category_id != NEW.category_id OR
        (OLD.description IS NULL AND NEW.description IS NOT NULL) OR
        (OLD.description IS NOT NULL AND NEW.description IS NULL) OR
        (OLD.description IS NOT NULL AND NEW.description IS NOT NULL AND OLD.description != NEW.description)) THEN
        
        -- Update all linked POS products
        UPDATE pos_products p
        SET 
            p.name = NEW.name,
            p.sku = COALESCE(NULLIF(NEW.sku, ''), p.sku),
            p.unit_price = NEW.sell_price,
            p.cost_price = NEW.cost_price,
            p.is_active = NEW.is_active,
            p.updated_at = NOW()
        WHERE p.catalog_item_id = NEW.id;
        
    END IF;
    
    -- Sync inventory if stock quantities changed
    IF (COALESCE(OLD.stock_quantity, 0) != COALESCE(NEW.stock_quantity, 0) OR
        COALESCE(OLD.inventory_quantity, 0) != COALESCE(NEW.inventory_quantity, 0)) THEN
        
        -- This will be handled by UnifiedInventoryService in application code
        -- Trigger just ensures POS products are updated
        UPDATE pos_inventory pi
        INNER JOIN pos_products p ON p.id = pi.product_id
        SET pi.updated_at = NOW()
        WHERE p.catalog_item_id = NEW.id;
    END IF;
END$$

-- Trigger: After catalog_items INSERT - create POS product if needed
DROP TRIGGER IF EXISTS trg_catalog_items_insert_sync$$
CREATE TRIGGER trg_catalog_items_insert_sync
AFTER INSERT ON catalog_items
FOR EACH ROW
BEGIN
    -- Only auto-create POS product for active products
    IF NEW.item_type = 'product' AND NEW.is_active = 1 AND NEW.is_sellable = 1 THEN
        -- Check if POS product already exists
        IF NOT EXISTS (SELECT 1 FROM pos_products WHERE catalog_item_id = NEW.id OR sku = NEW.sku) THEN
            -- Auto-create will be handled by application code (PosRepository::upsertProductFromCatalog)
            -- Trigger just logs the event
            INSERT INTO pos_sync_log (action, catalog_item_id, status, created_at)
            VALUES ('catalog_insert', NEW.id, 'pending', NOW())
            ON DUPLICATE KEY UPDATE status = 'pending', created_at = NOW();
        END IF;
    END IF;
END$$

-- Trigger: After pos_products UPDATE - sync to catalog_items
DROP TRIGGER IF EXISTS trg_pos_products_update_sync$$
CREATE TRIGGER trg_pos_products_update_sync
AFTER UPDATE ON pos_products
FOR EACH ROW
BEGIN
    -- Only sync if product details changed and catalog_item_id exists
    IF NEW.catalog_item_id IS NOT NULL AND
       (OLD.name != NEW.name OR 
        OLD.sku != NEW.sku OR 
        OLD.unit_price != NEW.unit_price OR 
        OLD.cost_price != NEW.cost_price OR 
        OLD.is_active != NEW.is_active) THEN
        
        -- Update catalog item
        UPDATE catalog_items
        SET 
            name = NEW.name,
            sku = COALESCE(NULLIF(NEW.sku, ''), sku),
            sell_price = NEW.unit_price,
            cost_price = COALESCE(NEW.cost_price, cost_price),
            is_active = NEW.is_active,
            is_sellable = NEW.is_active,
            updated_at = NOW()
        WHERE id = NEW.catalog_item_id;
    END IF;
END$$

DELIMITER ;

-- Create sync log table for tracking sync operations
CREATE TABLE IF NOT EXISTS `pos_sync_log` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `action` VARCHAR(50) NOT NULL,
    `catalog_item_id` INT DEFAULT NULL,
    `pos_product_id` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('pending','completed','failed') DEFAULT 'pending',
    `error_message` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sync_log_catalog_idx` (`catalog_item_id`),
    KEY `sync_log_pos_idx` (`pos_product_id`),
    KEY `sync_log_status_idx` (`status`),
    UNIQUE KEY `sync_log_unique` (`action`, `catalog_item_id`, `pos_product_id`, `status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

