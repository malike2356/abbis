-- Enhanced Menu System for WordPress-like functionality
-- Add support for multiple menus, menu locations, and object linking

-- Add new columns to cms_menu_items (check if they exist first)
-- Note: MySQL doesn't support IF NOT EXISTS for ADD COLUMN, so we check programmatically

-- Create menu locations table
CREATE TABLE IF NOT EXISTS cms_menu_locations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  location_name VARCHAR(50) UNIQUE NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  menu_name VARCHAR(100) DEFAULT NULL,
  description TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_location (location_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default menu locations
INSERT IGNORE INTO cms_menu_locations (location_name, display_name, description) VALUES
('primary', 'Primary Menu', 'Main navigation menu displayed in the header'),
('footer', 'Footer Menu', 'Menu displayed in the footer'),
('sidebar', 'Sidebar Menu', 'Menu displayed in the sidebar (if theme supports it)');
