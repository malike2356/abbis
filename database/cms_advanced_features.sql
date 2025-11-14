-- Advanced CMS Features: Drupal, Joomla, WordPress Inspired
-- Content Types, Views, Taxonomy, ACL, Multi-language, Module Positions, REST API

-- ==========================================
-- 1. CONTENT TYPES & CUSTOM FIELDS (Drupal-inspired)
-- ==========================================

-- Content Types Table
CREATE TABLE IF NOT EXISTS cms_content_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) UNIQUE NOT NULL COMMENT 'Unique identifier (e.g., portfolio_item)',
    label VARCHAR(255) NOT NULL COMMENT 'Human-readable name (e.g., Portfolio Item)',
    description TEXT DEFAULT NULL,
    icon VARCHAR(50) DEFAULT '📄',
    base_table VARCHAR(100) DEFAULT NULL COMMENT 'Base table name if using existing table',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_machine_name (machine_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Custom Fields Table
CREATE TABLE IF NOT EXISTS cms_custom_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT NOT NULL,
    machine_name VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'textarea', 'number', 'email', 'url', 'date', 'datetime', 'boolean', 'select', 'multiselect', 'image', 'file', 'wysiwyg', 'json') NOT NULL,
    field_settings JSON DEFAULT NULL COMMENT 'Field-specific settings (options, validation, etc.)',
    required TINYINT(1) DEFAULT 0,
    default_value TEXT DEFAULT NULL,
    help_text TEXT DEFAULT NULL,
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_type_id) REFERENCES cms_content_types(id) ON DELETE CASCADE,
    INDEX idx_content_type (content_type_id),
    INDEX idx_status (status),
    UNIQUE KEY unique_field (content_type_id, machine_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Field Values Table (stores actual field data)
CREATE TABLE IF NOT EXISTS cms_field_values (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT NOT NULL,
    entity_id INT NOT NULL COMMENT 'ID of the content item',
    field_id INT NOT NULL,
    field_value LONGTEXT DEFAULT NULL,
    language_code VARCHAR(10) DEFAULT 'en' COMMENT 'For multilingual support',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_type_id) REFERENCES cms_content_types(id) ON DELETE CASCADE,
    FOREIGN KEY (field_id) REFERENCES cms_custom_fields(id) ON DELETE CASCADE,
    INDEX idx_entity (content_type_id, entity_id),
    INDEX idx_field (field_id),
    INDEX idx_language (language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 2. VIEWS SYSTEM (Drupal-inspired)
-- ==========================================

CREATE TABLE IF NOT EXISTS cms_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) UNIQUE NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    content_type_id INT DEFAULT NULL COMMENT 'Primary content type for this view',
    display_type ENUM('list', 'grid', 'table', 'calendar', 'map', 'chart') DEFAULT 'list',
    query_config JSON NOT NULL COMMENT 'Query configuration (filters, sorts, pagination)',
    style_config JSON DEFAULT NULL COMMENT 'Display style configuration',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_type_id) REFERENCES cms_content_types(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_machine_name (machine_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 3. ADVANCED TAXONOMY (Drupal-inspired)
-- ==========================================

-- Vocabularies (like "Categories", "Tags", "Product Types")
CREATE TABLE IF NOT EXISTS cms_vocabularies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_name VARCHAR(100) UNIQUE NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    hierarchical TINYINT(1) DEFAULT 0 COMMENT 'Allow parent-child relationships',
    multiple TINYINT(1) DEFAULT 1 COMMENT 'Allow multiple terms per content',
    required TINYINT(1) DEFAULT 0,
    content_types JSON DEFAULT NULL COMMENT 'Which content types can use this vocabulary',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_machine_name (machine_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Terms (individual taxonomy items)
CREATE TABLE IF NOT EXISTS cms_terms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vocabulary_id INT NOT NULL,
    parent_id INT DEFAULT NULL COMMENT 'For hierarchical vocabularies',
    machine_name VARCHAR(100) NOT NULL,
    label VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    slug VARCHAR(255) NOT NULL,
    weight INT DEFAULT 0 COMMENT 'Display order',
    language_code VARCHAR(10) DEFAULT 'en',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vocabulary_id) REFERENCES cms_vocabularies(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES cms_terms(id) ON DELETE CASCADE,
    INDEX idx_vocabulary (vocabulary_id),
    INDEX idx_parent (parent_id),
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    UNIQUE KEY unique_term (vocabulary_id, machine_name, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Term Relationships (link terms to content)
CREATE TABLE IF NOT EXISTS cms_term_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT NOT NULL,
    entity_id INT NOT NULL COMMENT 'ID of the content item',
    term_id INT NOT NULL,
    language_code VARCHAR(10) DEFAULT 'en',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (term_id) REFERENCES cms_terms(id) ON DELETE CASCADE,
    INDEX idx_entity (content_type_id, entity_id),
    INDEX idx_term (term_id),
    INDEX idx_language (language_code),
    UNIQUE KEY unique_relationship (content_type_id, entity_id, term_id, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 4. ACCESS CONTROL LISTS (Joomla-inspired)
-- ==========================================

CREATE TABLE IF NOT EXISTS cms_acl_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT DEFAULT NULL COMMENT 'NULL = global rule',
    entity_id INT DEFAULT NULL COMMENT 'NULL = content type level, set = item level',
    user_id INT DEFAULT NULL COMMENT 'NULL = role-based, set = user-specific',
    role VARCHAR(50) DEFAULT NULL COMMENT 'User role if user_id is NULL',
    permission ENUM('view', 'edit', 'delete', 'publish', 'unpublish', 'manage') NOT NULL,
    granted TINYINT(1) DEFAULT 1 COMMENT '1 = allow, 0 = deny',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_type_id) REFERENCES cms_content_types(id) ON DELETE CASCADE,
    INDEX idx_entity (content_type_id, entity_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role),
    INDEX idx_permission (permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 5. MULTI-LANGUAGE SUPPORT (Joomla/Drupal-inspired)
-- ==========================================

-- Languages Table
CREATE TABLE IF NOT EXISTS cms_languages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL COMMENT 'ISO 639-1 code (e.g., en, fr, es)',
    name VARCHAR(100) NOT NULL COMMENT 'English name',
    native_name VARCHAR(100) NOT NULL COMMENT 'Native name',
    flag VARCHAR(10) DEFAULT NULL COMMENT 'Flag emoji or code',
    rtl TINYINT(1) DEFAULT 0 COMMENT 'Right-to-left language',
    default_language TINYINT(1) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Content Translations
CREATE TABLE IF NOT EXISTS cms_translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content_type_id INT NOT NULL,
    entity_id INT NOT NULL COMMENT 'Original content ID',
    language_code VARCHAR(10) NOT NULL,
    translated_entity_id INT DEFAULT NULL COMMENT 'Translated content ID (if separate entity)',
    title VARCHAR(255) DEFAULT NULL COMMENT 'Translated title',
    content LONGTEXT DEFAULT NULL COMMENT 'Translated content',
    status ENUM('active', 'inactive', 'needs_translation') DEFAULT 'needs_translation',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (content_type_id) REFERENCES cms_content_types(id) ON DELETE CASCADE,
    INDEX idx_entity (content_type_id, entity_id),
    INDEX idx_language (language_code),
    INDEX idx_status (status),
    UNIQUE KEY unique_translation (content_type_id, entity_id, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Interface Translations (for admin UI)
CREATE TABLE IF NOT EXISTS cms_i18n_strings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    string_key VARCHAR(255) NOT NULL COMMENT 'Translation key',
    language_code VARCHAR(10) NOT NULL,
    translation TEXT NOT NULL,
    context VARCHAR(100) DEFAULT NULL COMMENT 'Where this string is used',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (string_key),
    INDEX idx_language (language_code),
    UNIQUE KEY unique_string (string_key, language_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 6. MODULE POSITIONS (Joomla-inspired)
-- ==========================================

CREATE TABLE IF NOT EXISTS cms_module_positions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position_name VARCHAR(100) UNIQUE NOT NULL COMMENT 'e.g., sidebar-left, footer-column-1',
    label VARCHAR(255) NOT NULL COMMENT 'Human-readable label',
    description TEXT DEFAULT NULL,
    template VARCHAR(100) DEFAULT NULL COMMENT 'Specific template, NULL = all templates',
    display_order INT DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_template (template)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Widget/Module Assignments to Positions
CREATE TABLE IF NOT EXISTS cms_module_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    widget_id INT DEFAULT NULL COMMENT 'Widget ID from cms_widgets',
    module_id INT DEFAULT NULL COMMENT 'Custom module ID if not widget',
    position_id INT NOT NULL,
    display_order INT DEFAULT 0,
    conditions JSON DEFAULT NULL COMMENT 'Display conditions (pages, roles, etc.)',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES cms_module_positions(id) ON DELETE CASCADE,
    INDEX idx_position (position_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- 7. REST API (WordPress-inspired)
-- ==========================================

-- API Keys/Authentication
CREATE TABLE IF NOT EXISTS cms_api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(255) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL COMMENT 'Generated API key',
    api_secret VARCHAR(64) DEFAULT NULL COMMENT 'Optional secret for enhanced security',
    user_id INT DEFAULT NULL COMMENT 'Associated user',
    permissions JSON DEFAULT NULL COMMENT 'Allowed operations',
    rate_limit INT DEFAULT 1000 COMMENT 'Requests per hour',
    last_used_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    status ENUM('active', 'inactive', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_key (api_key),
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- API Logs (optional, for monitoring)
CREATE TABLE IF NOT EXISTS cms_api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT DEFAULT NULL,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(10) NOT NULL,
    status_code INT NOT NULL,
    response_time INT DEFAULT NULL COMMENT 'Response time in milliseconds',
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES cms_api_keys(id) ON DELETE SET NULL,
    INDEX idx_api_key (api_key_id),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==========================================
-- DEFAULT DATA
-- ==========================================

-- Insert default languages
INSERT IGNORE INTO cms_languages (code, name, native_name, flag, default_language, status) VALUES
('en', 'English', 'English', '🇬🇧', 1, 'active'),
('fr', 'French', 'Français', '🇫🇷', 0, 'active'),
('es', 'Spanish', 'Español', '🇪🇸', 0, 'active'),
('de', 'German', 'Deutsch', '🇩🇪', 0, 'active'),
('ar', 'Arabic', 'العربية', '🇸🇦', 0, 'active');

-- Insert default module positions
INSERT IGNORE INTO cms_module_positions (position_name, label, description, display_order, status) VALUES
('header', 'Header', 'Header area', 1, 'active'),
('sidebar-left', 'Left Sidebar', 'Left sidebar area', 2, 'active'),
('sidebar-right', 'Right Sidebar', 'Right sidebar area', 3, 'active'),
('content-top', 'Content Top', 'Above main content', 4, 'active'),
('content-bottom', 'Content Bottom', 'Below main content', 5, 'active'),
('footer-column-1', 'Footer Column 1', 'First footer column', 6, 'active'),
('footer-column-2', 'Footer Column 2', 'Second footer column', 7, 'active'),
('footer-column-3', 'Footer Column 3', 'Third footer column', 8, 'active'),
('footer-column-4', 'Footer Column 4', 'Fourth footer column', 9, 'active'),
('footer', 'Footer', 'Footer area', 10, 'active');

