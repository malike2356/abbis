-- Complete WooCommerce Clone Database Schema
-- This creates/updates all tables needed for a full e-commerce system

-- Ensure payment methods are enabled by default
UPDATE cms_payment_methods SET is_active=1 WHERE provider IN ('mobile_money', 'cash', 'bank_transfer');

-- Create/update shipping methods table
CREATE TABLE IF NOT EXISTS cms_shipping_methods (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  method_type VARCHAR(50) NOT NULL,
  cost DECIMAL(10,2) DEFAULT 0.00,
  is_active TINYINT(1) DEFAULT 1,
  config JSON DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cms_shipping_methods (name, method_type, cost, is_active) VALUES
('Standard Shipping', 'standard', 0.00, 1),
('Express Shipping', 'express', 50.00, 1),
('Free Shipping', 'free', 0.00, 1);

-- Create/update tax rates table
CREATE TABLE IF NOT EXISTS cms_tax_rates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  rate DECIMAL(5,2) NOT NULL,
  country VARCHAR(100) DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO cms_tax_rates (name, rate, country, is_active) VALUES
('Ghana VAT', 12.50, 'GH', 1),
('Standard Tax', 0.00, NULL, 1);

-- Add shipping fields to orders table if not exists
ALTER TABLE cms_orders 
ADD COLUMN IF NOT EXISTS shipping_method_id INT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS tax_amount DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00,
ADD COLUMN IF NOT EXISTS billing_address TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shipping_address TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS billing_city VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shipping_city VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS billing_postcode VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shipping_postcode VARCHAR(20) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS billing_country VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS shipping_country VARCHAR(100) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS customer_notes TEXT DEFAULT NULL,
ADD COLUMN IF NOT EXISTS order_key VARCHAR(100) DEFAULT NULL,
ADD INDEX IF NOT EXISTS idx_order_key (order_key);

-- Create customer accounts table (optional - for logged-in users)
CREATE TABLE IF NOT EXISTS cms_customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  email VARCHAR(255) UNIQUE NOT NULL,
  first_name VARCHAR(100) DEFAULT NULL,
  last_name VARCHAR(100) DEFAULT NULL,
  phone VARCHAR(20) DEFAULT NULL,
  billing_address TEXT DEFAULT NULL,
  shipping_address TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE SET NULL,
  INDEX idx_email (email),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create order status history table
CREATE TABLE IF NOT EXISTS cms_order_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  status VARCHAR(50) NOT NULL,
  note TEXT DEFAULT NULL,
  created_by INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES cms_orders(id) ON DELETE CASCADE,
  INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create product reviews table
CREATE TABLE IF NOT EXISTS cms_product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_email VARCHAR(255) NOT NULL,
  rating INT NOT NULL DEFAULT 5,
  review_text TEXT DEFAULT NULL,
  is_approved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_product (product_id),
  INDEX idx_approved (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create wishlist table
CREATE TABLE IF NOT EXISTS cms_wishlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(100) NOT NULL,
  product_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wishlist (session_id, product_id),
  INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

