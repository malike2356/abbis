-- Widget System for CMS (WordPress-like)

-- Widget Areas (Sidebars)
CREATE TABLE IF NOT EXISTS cms_widget_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  description TEXT DEFAULT NULL,
  location VARCHAR(50) DEFAULT 'sidebar',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_slug (slug),
  INDEX idx_location (location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Widget Instances
CREATE TABLE IF NOT EXISTS cms_widgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  widget_area_id INT NOT NULL,
  widget_type VARCHAR(50) NOT NULL,
  widget_title VARCHAR(255) DEFAULT NULL,
  widget_order INT DEFAULT 0,
  widget_data JSON DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (widget_area_id) REFERENCES cms_widget_areas(id) ON DELETE CASCADE,
  INDEX idx_area (widget_area_id),
  INDEX idx_order (widget_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default widget areas
INSERT IGNORE INTO cms_widget_areas (name, slug, description, location) VALUES
('Primary Sidebar', 'sidebar-1', 'Add widgets here to appear in your sidebar.', 'sidebar'),
('Footer Column 1', 'footer-1', 'Add widgets here to appear in footer column 1.', 'footer'),
('Footer Column 2', 'footer-2', 'Add widgets here to appear in footer column 2.', 'footer'),
('Footer Column 3', 'footer-3', 'Add widgets here to appear in footer column 3.', 'footer'),
('Footer Column 4', 'footer-4', 'Add widgets here to appear in footer column 4.', 'footer'),
('Header Widget', 'header-widget', 'Add widgets here to appear in the header.', 'header');

