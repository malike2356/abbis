-- E-Commerce Enhancements (WooCommerce-like)
-- Coupons, Product Reviews, Product Attributes, Shipping Classes

-- Coupons Table
CREATE TABLE IF NOT EXISTS cms_coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  description TEXT DEFAULT NULL,
  discount_type ENUM('fixed','percentage') NOT NULL DEFAULT 'percentage',
  discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  minimum_amount DECIMAL(12,2) DEFAULT NULL,
  maximum_amount DECIMAL(12,2) DEFAULT NULL,
  usage_limit INT DEFAULT NULL,
  used_count INT DEFAULT 0,
  expiry_date DATE DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_code (code),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Reviews Table
CREATE TABLE IF NOT EXISTS cms_product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  rating INT NOT NULL DEFAULT 5,
  title VARCHAR(255) DEFAULT NULL,
  review_text TEXT NOT NULL,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
  INDEX idx_product (product_id),
  INDEX idx_status (status),
  INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Attributes Table
CREATE TABLE IF NOT EXISTS cms_product_attributes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  description TEXT DEFAULT NULL,
  type ENUM('select','color','size','text') DEFAULT 'select',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Attribute Terms (Values)
CREATE TABLE IF NOT EXISTS cms_product_attribute_terms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  attribute_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL,
  value VARCHAR(255) DEFAULT NULL,
  display_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (attribute_id) REFERENCES cms_product_attributes(id) ON DELETE CASCADE,
  INDEX idx_attribute (attribute_id),
  UNIQUE KEY unique_term (attribute_id, slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Product Attribute Relationships
CREATE TABLE IF NOT EXISTS cms_product_attribute_relations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  attribute_id INT NOT NULL,
  term_id INT DEFAULT NULL,
  value VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (product_id) REFERENCES catalog_items(id) ON DELETE CASCADE,
  FOREIGN KEY (attribute_id) REFERENCES cms_product_attributes(id) ON DELETE CASCADE,
  FOREIGN KEY (term_id) REFERENCES cms_product_attribute_terms(id) ON DELETE SET NULL,
  INDEX idx_product (product_id),
  INDEX idx_attribute (attribute_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Shipping Classes Table
CREATE TABLE IF NOT EXISTS cms_shipping_classes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Order Coupons (track which coupons were used in orders)
CREATE TABLE IF NOT EXISTS cms_order_coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  coupon_id INT NOT NULL,
  discount_amount DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES cms_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (coupon_id) REFERENCES cms_coupons(id) ON DELETE CASCADE,
  INDEX idx_order (order_id),
  INDEX idx_coupon (coupon_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add shipping_class_id to catalog_items
ALTER TABLE catalog_items ADD COLUMN shipping_class_id INT DEFAULT NULL AFTER category_id;
ALTER TABLE catalog_items ADD FOREIGN KEY (shipping_class_id) REFERENCES cms_shipping_classes(id) ON DELETE SET NULL;

-- Add tax_class to catalog_items
ALTER TABLE catalog_items ADD COLUMN tax_class VARCHAR(50) DEFAULT 'standard' AFTER taxable;

-- Add sale_price and on_sale to catalog_items
ALTER TABLE catalog_items ADD COLUMN sale_price DECIMAL(12,2) DEFAULT NULL AFTER sell_price;
ALTER TABLE catalog_items ADD COLUMN on_sale TINYINT(1) DEFAULT 0 AFTER sale_price;

-- Add weight and dimensions to catalog_items
ALTER TABLE catalog_items ADD COLUMN weight DECIMAL(10,2) DEFAULT NULL AFTER on_sale;
ALTER TABLE catalog_items ADD COLUMN length DECIMAL(10,2) DEFAULT NULL AFTER weight;
ALTER TABLE catalog_items ADD COLUMN width DECIMAL(10,2) DEFAULT NULL AFTER length;
ALTER TABLE catalog_items ADD COLUMN height DECIMAL(10,2) DEFAULT NULL AFTER width;

-- Insert default shipping classes
INSERT IGNORE INTO cms_shipping_classes (name, slug, description) VALUES
('Standard', 'standard', 'Standard shipping class'),
('Express', 'express', 'Express shipping'),
('Free Shipping', 'free-shipping', 'Free shipping items');

