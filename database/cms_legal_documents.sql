-- Legal Documents Management System

CREATE TABLE IF NOT EXISTS cms_legal_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  document_type VARCHAR(50) NOT NULL,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(100) UNIQUE NOT NULL,
  content LONGTEXT NOT NULL,
  version VARCHAR(20) DEFAULT '1.0',
  effective_date DATE DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_type (document_type),
  INDEX idx_slug (slug),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default legal documents
INSERT IGNORE INTO cms_legal_documents (document_type, title, slug, content, version) VALUES
('drilling_agreement', 'Terms & Conditions / Agreement for Drilling', 'drilling-agreement', '', '1.0'),
('terms_of_service', 'Terms of Service', 'terms-of-service', '', '1.0'),
('privacy_policy', 'Privacy Policy', 'privacy-policy', '', '1.0');

