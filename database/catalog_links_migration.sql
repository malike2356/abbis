-- Link field reports to catalog items

CREATE TABLE IF NOT EXISTS field_report_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  catalog_item_id INT NOT NULL,
  description VARCHAR(200) DEFAULT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  unit VARCHAR(40) DEFAULT NULL,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  item_type ENUM('expense','revenue','material') DEFAULT 'expense',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES field_reports(id) ON DELETE CASCADE,
  FOREIGN KEY (catalog_item_id) REFERENCES catalog_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


