-- Advanced User Management System Enhancement

-- Enhance cms_users table with additional fields
ALTER TABLE cms_users 
ADD COLUMN IF NOT EXISTS first_name VARCHAR(100) DEFAULT NULL AFTER username,
ADD COLUMN IF NOT EXISTS last_name VARCHAR(100) DEFAULT NULL AFTER first_name,
ADD COLUMN IF NOT EXISTS display_name VARCHAR(100) DEFAULT NULL AFTER last_name,
ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL AFTER email,
ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL AFTER bio,
ADD COLUMN IF NOT EXISTS status ENUM('active','inactive','pending','suspended') DEFAULT 'active' AFTER role,
ADD COLUMN IF NOT EXISTS last_login TIMESTAMP NULL DEFAULT NULL AFTER status,
ADD COLUMN IF NOT EXISTS login_count INT DEFAULT 0 AFTER last_login,
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0 AFTER login_count,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) DEFAULT NULL AFTER email_verified,
ADD COLUMN IF NOT EXISTS password_reset_token VARCHAR(255) DEFAULT NULL AFTER email_verification_token,
ADD COLUMN IF NOT EXISTS password_reset_expires TIMESTAMP NULL DEFAULT NULL AFTER password_reset_token,
ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL AFTER password_reset_expires,
ADD COLUMN IF NOT EXISTS website VARCHAR(255) DEFAULT NULL AFTER phone,
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL AFTER website,
ADD COLUMN IF NOT EXISTS timezone VARCHAR(50) DEFAULT 'UTC' AFTER location,
ADD COLUMN IF NOT EXISTS language VARCHAR(10) DEFAULT 'en' AFTER timezone,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

-- Update role enum to include more roles
ALTER TABLE cms_users MODIFY COLUMN role ENUM('admin','editor','author','contributor','subscriber') DEFAULT 'subscriber';

-- User capabilities table
CREATE TABLE IF NOT EXISTS cms_user_capabilities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  capability VARCHAR(100) NOT NULL,
  granted TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
  UNIQUE KEY unique_user_capability (user_id, capability),
  INDEX idx_user (user_id),
  INDEX idx_capability (capability)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User meta table for extensible metadata
CREATE TABLE IF NOT EXISTS cms_user_meta (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  meta_key VARCHAR(255) NOT NULL,
  meta_value LONGTEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_meta_key (meta_key),
  UNIQUE KEY unique_user_meta (user_id, meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User activity log
CREATE TABLE IF NOT EXISTS cms_user_activity (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  description TEXT,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES cms_users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default capabilities for roles
-- These will be inserted via PHP, but structure is here for reference
-- Admin: all capabilities
-- Editor: edit_posts, publish_posts, edit_pages, publish_pages, manage_categories, moderate_comments
-- Author: edit_posts, publish_posts, upload_files
-- Contributor: edit_posts
-- Subscriber: read

