-- Unified Entity Linking for POS Sales
-- Allows POS sales to be linked to any entity: clients, workers, CMS customers, contractors, etc.

-- Add entity_type and entity_id to pos_sales for unified entity linking
ALTER TABLE `pos_sales`
ADD COLUMN `entity_type` VARCHAR(50) DEFAULT NULL AFTER `customer_id`,
ADD COLUMN `entity_id` INT DEFAULT NULL AFTER `entity_type`,
ADD INDEX `pos_sales_entity_idx` (`entity_type`, `entity_id`);

-- Update customer_id to be nullable if entity_type/entity_id are used
-- Keep customer_id for backward compatibility (maps to entity_type='client', entity_id=customer_id)

-- Create unified entity lookup view for easier querying
CREATE OR REPLACE VIEW `pos_entity_transactions` AS
SELECT 
    'client' as entity_type,
    c.id as entity_id,
    c.client_name as entity_name,
    c.contact_number as phone,
    c.email,
    c.address,
    'ABBIS Client' as source_system,
    COUNT(DISTINCT s.id) as total_transactions,
    COALESCE(SUM(s.total_amount), 0) as total_spent,
    MAX(s.sale_timestamp) as last_transaction_date
FROM clients c
LEFT JOIN pos_sales s ON (s.entity_type = 'client' AND s.entity_id = c.id) OR (s.entity_type IS NULL AND s.customer_id = c.id)
GROUP BY c.id, c.client_name, c.contact_number, c.email, c.address

UNION ALL

SELECT 
    'worker' as entity_type,
    w.id as entity_id,
    w.worker_name as entity_name,
    w.contact_number as phone,
    NULL as email,
    NULL as address,
    'ABBIS Worker' as source_system,
    COUNT(DISTINCT s.id) as total_transactions,
    COALESCE(SUM(s.total_amount), 0) as total_spent,
    MAX(s.sale_timestamp) as last_transaction_date
FROM workers w
LEFT JOIN pos_sales s ON s.entity_type = 'worker' AND s.entity_id = w.id
WHERE w.status = 'active'
GROUP BY w.id, w.worker_name, w.contact_number

UNION ALL

SELECT 
    'cms_customer' as entity_type,
    cc.id as entity_id,
    CONCAT(COALESCE(cc.first_name, ''), ' ', COALESCE(cc.last_name, '')) as entity_name,
    cc.phone,
    cc.email,
    cc.billing_address as address,
    'CMS Customer' as source_system,
    COUNT(DISTINCT s.id) as total_transactions,
    COALESCE(SUM(s.total_amount), 0) as total_spent,
    MAX(s.sale_timestamp) as last_transaction_date
FROM cms_customers cc
LEFT JOIN pos_sales s ON s.entity_type = 'cms_customer' AND s.entity_id = cc.id
GROUP BY cc.id, cc.first_name, cc.last_name, cc.phone, cc.email, cc.billing_address;

-- Migrate existing customer_id references to entity_type/entity_id
UPDATE pos_sales 
SET entity_type = 'client', entity_id = customer_id 
WHERE customer_id IS NOT NULL AND entity_type IS NULL;

