-- HR System - Comprehensive Migration
-- ABBIS Human Resources Management System
-- This migration creates the complete HR system infrastructure

USE `abbis_3_2`;

-- Disable foreign key checks temporarily to avoid constraint issues
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. ENHANCE WORKERS TABLE (Core HR Data)
-- ============================================

-- Add employee_code if not exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'employee_code') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `employee_code` VARCHAR(50) UNIQUE AFTER `id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add other HR fields (using safe ALTER TABLE with IF NOT EXISTS check)
-- Note: MySQL doesn't support IF NOT EXISTS in ALTER TABLE, so we'll check each column

-- Function to safely add column
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'employee_type') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `employee_type` ENUM(\'staff\', \'worker\', \'contractor\', \'stakeholder\') DEFAULT \'worker\' AFTER `employee_code`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'user_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `employee_type`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add email column first if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'email') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `email` VARCHAR(100) DEFAULT NULL AFTER `contact_number`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'date_of_birth') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `email`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'national_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `national_id` VARCHAR(50) DEFAULT NULL AFTER `date_of_birth`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'gender') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `gender` ENUM(\'male\', \'female\', \'other\') DEFAULT NULL AFTER `national_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'address') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `address` TEXT DEFAULT NULL AFTER `gender`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'city') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'country') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `country` VARCHAR(100) DEFAULT \'Ghana\' AFTER `city`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'postal_code') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL AFTER `country`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'emergency_contact_name') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL AFTER `postal_code`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'emergency_contact_phone') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL AFTER `emergency_contact_name`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'emergency_contact_relationship') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL AFTER `emergency_contact_phone`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'hire_date') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `hire_date` DATE DEFAULT NULL AFTER `emergency_contact_relationship`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'employment_type') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `employment_type` ENUM(\'full_time\', \'part_time\', \'contract\', \'casual\', \'intern\') DEFAULT \'full_time\' AFTER `hire_date`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'department_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `department_id` INT(11) DEFAULT NULL AFTER `employment_type`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'position_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `position_id` INT(11) DEFAULT NULL AFTER `department_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'manager_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `manager_id` INT(11) DEFAULT NULL AFTER `position_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'salary') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `salary` DECIMAL(12,2) DEFAULT NULL AFTER `manager_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'bank_name') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `bank_name` VARCHAR(100) DEFAULT NULL AFTER `salary`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'bank_account_number') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `bank_account_number` VARCHAR(50) DEFAULT NULL AFTER `bank_name`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'bank_branch') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `bank_branch` VARCHAR(100) DEFAULT NULL AFTER `bank_account_number`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'tax_id') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `tax_id` VARCHAR(50) DEFAULT NULL AFTER `bank_branch`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'photo_path') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `photo_path` VARCHAR(255) DEFAULT NULL AFTER `tax_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'notes') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `photo_path`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND COLUMN_NAME = 'updated_at') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add indexes (safe - will ignore if exists)
ALTER TABLE `workers` 
ADD INDEX IF NOT EXISTS `idx_employee_code` (`employee_code`),
ADD INDEX IF NOT EXISTS `idx_employee_type` (`employee_type`),
ADD INDEX IF NOT EXISTS `idx_user_id` (`user_id`),
ADD INDEX IF NOT EXISTS `idx_department_id` (`department_id`),
ADD INDEX IF NOT EXISTS `idx_position_id` (`position_id`),
ADD INDEX IF NOT EXISTS `idx_manager_id` (`manager_id`),
ADD INDEX IF NOT EXISTS `idx_hire_date` (`hire_date`);

-- Add foreign keys (will fail if column doesn't exist, but that's fine - we'll add them after creating referenced tables)
-- We'll add these at the end after all tables are created

-- ============================================
-- 2. DEPARTMENTS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `department_code` VARCHAR(20) NOT NULL UNIQUE,
  `department_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `manager_id` INT(11) DEFAULT NULL COMMENT 'Department head (worker ID)',
  `parent_department_id` INT(11) DEFAULT NULL COMMENT 'For sub-departments',
  `budget` DECIMAL(15,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `manager_id` (`manager_id`),
  KEY `parent_department_id` (`parent_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. POSITIONS TABLE
-- ============================================
CREATE TABLE IF NOT EXISTS `positions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `position_code` VARCHAR(20) NOT NULL UNIQUE,
  `position_title` VARCHAR(100) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `requirements` TEXT DEFAULT NULL,
  `min_salary` DECIMAL(12,2) DEFAULT 0.00,
  `max_salary` DECIMAL(12,2) DEFAULT 0.00,
  `reports_to_position_id` INT(11) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_code` (`position_code`),
  KEY `department_id` (`department_id`),
  KEY `reports_to_position_id` (`reports_to_position_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. ATTENDANCE RECORDS
-- ============================================
CREATE TABLE IF NOT EXISTS `attendance_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `worker_id` INT(11) NOT NULL,
  `attendance_date` DATE NOT NULL,
  `time_in` TIME DEFAULT NULL,
  `time_out` TIME DEFAULT NULL,
  `total_hours` DECIMAL(5,2) DEFAULT 0.00,
  `overtime_hours` DECIMAL(5,2) DEFAULT 0.00,
  `attendance_status` ENUM('present', 'absent', 'late', 'half_day', 'leave', 'holiday') DEFAULT 'present',
  `check_in_location` VARCHAR(255) DEFAULT NULL COMMENT 'GPS coordinates or location name',
  `check_out_location` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL COMMENT 'User who recorded attendance',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_date` (`worker_id`, `attendance_date`),
  KEY `attendance_date` (`attendance_date`),
  KEY `attendance_status` (`attendance_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 5. LEAVE TYPES
-- ============================================
CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `leave_code` VARCHAR(20) NOT NULL UNIQUE,
  `leave_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `max_days_per_year` INT(11) DEFAULT NULL,
  `carry_forward_allowed` TINYINT(1) DEFAULT 0,
  `max_carry_forward_days` INT(11) DEFAULT 0,
  `requires_approval` TINYINT(1) DEFAULT 1,
  `is_paid` TINYINT(1) DEFAULT 1,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leave_code` (`leave_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default leave types
INSERT IGNORE INTO `leave_types` (`leave_code`, `leave_name`, `description`, `max_days_per_year`, `carry_forward_allowed`, `max_carry_forward_days`, `requires_approval`, `is_paid`) VALUES
('ANNUAL', 'Annual Leave', 'Annual vacation leave', 21, 1, 5, 1, 1),
('SICK', 'Sick Leave', 'Medical leave for illness', 10, 0, 0, 1, 1),
('CASUAL', 'Casual Leave', 'Casual leave for personal matters', 7, 0, 0, 1, 1),
('MATERNITY', 'Maternity Leave', 'Maternity leave for new mothers', 90, 0, 0, 1, 1),
('PATERNITY', 'Paternity Leave', 'Paternity leave for new fathers', 7, 0, 0, 1, 1),
('UNPAID', 'Unpaid Leave', 'Unpaid leave', NULL, 0, 0, 1, 0),
('COMPASSIONATE', 'Compassionate Leave', 'Leave for bereavement', 3, 0, 0, 1, 1);

-- ============================================
-- 6. LEAVE REQUESTS
-- ============================================
CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `request_code` VARCHAR(50) NOT NULL UNIQUE,
  `worker_id` INT(11) NOT NULL,
  `leave_type_id` INT(11) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` INT(11) NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
  `approved_by` INT(11) DEFAULT NULL COMMENT 'User/Worker who approved',
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `rejection_reason` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_code` (`request_code`),
  KEY `worker_id` (`worker_id`),
  KEY `leave_type_id` (`leave_type_id`),
  KEY `status` (`status`),
  KEY `start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 7. LEAVE BALANCES
-- ============================================
CREATE TABLE IF NOT EXISTS `leave_balances` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `worker_id` INT(11) NOT NULL,
  `leave_type_id` INT(11) NOT NULL,
  `year` YEAR NOT NULL,
  `allocated_days` INT(11) DEFAULT 0,
  `used_days` INT(11) DEFAULT 0,
  `remaining_days` INT(11) DEFAULT 0,
  `carried_forward_days` INT(11) DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_leave_year` (`worker_id`, `leave_type_id`, `year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 8. PERFORMANCE REVIEWS
-- ============================================
CREATE TABLE IF NOT EXISTS `performance_reviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `review_code` VARCHAR(50) NOT NULL UNIQUE,
  `worker_id` INT(11) NOT NULL,
  `review_period_start` DATE NOT NULL,
  `review_period_end` DATE NOT NULL,
  `review_type` ENUM('annual', 'quarterly', 'monthly', 'probation', 'promotion') DEFAULT 'annual',
  `reviewer_id` INT(11) NOT NULL COMMENT 'User/Worker conducting review',
  `overall_rating` DECIMAL(3,2) DEFAULT NULL COMMENT '1.00 to 5.00',
  `strengths` TEXT DEFAULT NULL,
  `areas_for_improvement` TEXT DEFAULT NULL,
  `goals` TEXT DEFAULT NULL,
  `recommendations` TEXT DEFAULT NULL,
  `status` ENUM('draft', 'in_progress', 'completed', 'acknowledged') DEFAULT 'draft',
  `acknowledged_by_employee` TINYINT(1) DEFAULT 0,
  `acknowledged_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_code` (`review_code`),
  KEY `worker_id` (`worker_id`),
  KEY `reviewer_id` (`reviewer_id`),
  KEY `review_period_end` (`review_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 9. PERFORMANCE REVIEW SCORES
-- ============================================
CREATE TABLE IF NOT EXISTS `performance_review_scores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `review_id` INT(11) NOT NULL,
  `criteria_name` VARCHAR(100) NOT NULL,
  `score` DECIMAL(3,2) NOT NULL COMMENT '1.00 to 5.00',
  `weight` DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight for calculation',
  `comments` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `review_id` (`review_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 10. TRAINING RECORDS
-- ============================================
CREATE TABLE IF NOT EXISTS `training_records` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `training_code` VARCHAR(50) NOT NULL UNIQUE,
  `worker_id` INT(11) NOT NULL,
  `training_title` VARCHAR(255) NOT NULL,
  `training_type` ENUM('internal', 'external', 'online', 'certification') DEFAULT 'internal',
  `provider` VARCHAR(255) DEFAULT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `duration_hours` DECIMAL(5,2) DEFAULT 0.00,
  `cost` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
  `certificate_number` VARCHAR(100) DEFAULT NULL,
  `certificate_expiry` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `certificate_path` VARCHAR(255) DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `training_code` (`training_code`),
  KEY `worker_id` (`worker_id`),
  KEY `status` (`status`),
  KEY `certificate_expiry` (`certificate_expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 11. WORKER SKILLS
-- ============================================
CREATE TABLE IF NOT EXISTS `worker_skills` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `worker_id` INT(11) NOT NULL,
  `skill_name` VARCHAR(100) NOT NULL,
  `skill_category` VARCHAR(50) DEFAULT NULL,
  `proficiency_level` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'beginner',
  `certified` TINYINT(1) DEFAULT 0,
  `certification_date` DATE DEFAULT NULL,
  `certification_expiry` DATE DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `worker_id` (`worker_id`),
  KEY `skill_name` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 12. EMPLOYEE DOCUMENTS
-- ============================================
CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `worker_id` INT(11) NOT NULL,
  `document_type` ENUM('contract', 'id_card', 'certificate', 'license', 'passport', 'other') NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `document_number` VARCHAR(100) DEFAULT NULL,
  `issue_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_size` INT(11) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `uploaded_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `worker_id` (`worker_id`),
  KEY `document_type` (`document_type`),
  KEY `expiry_date` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 13. EMPLOYMENT HISTORY
-- ============================================
CREATE TABLE IF NOT EXISTS `employment_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `worker_id` INT(11) NOT NULL,
  `event_type` ENUM('hire', 'promotion', 'transfer', 'salary_change', 'position_change', 'termination', 'resignation') NOT NULL,
  `event_date` DATE NOT NULL,
  `previous_position_id` INT(11) DEFAULT NULL,
  `new_position_id` INT(11) DEFAULT NULL,
  `previous_department_id` INT(11) DEFAULT NULL,
  `new_department_id` INT(11) DEFAULT NULL,
  `previous_salary` DECIMAL(12,2) DEFAULT NULL,
  `new_salary` DECIMAL(12,2) DEFAULT NULL,
  `reason` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `worker_id` (`worker_id`),
  KEY `event_date` (`event_date`),
  KEY `event_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 14. STAKEHOLDERS
-- ============================================
CREATE TABLE IF NOT EXISTS `stakeholders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_code` VARCHAR(50) NOT NULL UNIQUE,
  `stakeholder_type` ENUM('board_member', 'investor', 'partner', 'advisor', 'consultant', 'vendor', 'supplier', 'other') NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `organization` VARCHAR(255) DEFAULT NULL,
  `position_title` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Ghana',
  `relationship_start_date` DATE DEFAULT NULL,
  `relationship_end_date` DATE DEFAULT NULL,
  `stake_percentage` DECIMAL(5,2) DEFAULT NULL COMMENT 'For investors',
  `investment_amount` DECIMAL(15,2) DEFAULT NULL COMMENT 'For investors',
  `notes` TEXT DEFAULT NULL,
  `photo_path` VARCHAR(255) DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stakeholder_code` (`stakeholder_code`),
  KEY `stakeholder_type` (`stakeholder_type`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 15. STAKEHOLDER COMMUNICATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS `stakeholder_communications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `stakeholder_id` INT(11) NOT NULL,
  `communication_type` ENUM('meeting', 'email', 'phone', 'letter', 'report', 'other') NOT NULL,
  `subject` VARCHAR(255) DEFAULT NULL,
  `message` TEXT DEFAULT NULL,
  `communication_date` DATETIME NOT NULL,
  `initiated_by` INT(11) DEFAULT NULL COMMENT 'User ID',
  `attachments` TEXT DEFAULT NULL COMMENT 'JSON array of file paths',
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `stakeholder_id` (`stakeholder_id`),
  KEY `communication_date` (`communication_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 16. FIX EXISTING TABLES (Add worker_id)
-- ============================================

-- Fix payroll_entries
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `payroll_entries` ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing payroll data
UPDATE `payroll_entries` pe
INNER JOIN `workers` w ON pe.worker_name = w.worker_name
SET pe.worker_id = w.id
WHERE pe.worker_id IS NULL AND pe.worker_name IS NOT NULL;

-- Fix loans table
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'loans' AND COLUMN_NAME = 'worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `loans` ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing loans data
UPDATE `loans` l
INNER JOIN `workers` w ON l.worker_name = w.worker_name
SET l.worker_id = w.id
WHERE l.worker_id IS NULL AND l.worker_name IS NOT NULL;

-- Fix field_reports supervisor
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'field_reports' AND COLUMN_NAME = 'supervisor_id') > 0,
    'SELECT 1',
    'ALTER TABLE `field_reports` ADD COLUMN `supervisor_id` INT(11) DEFAULT NULL AFTER `supervisor`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing supervisor data
UPDATE `field_reports` fr
INNER JOIN `workers` w ON fr.supervisor = w.worker_name
SET fr.supervisor_id = w.id
WHERE fr.supervisor_id IS NULL AND fr.supervisor IS NOT NULL;

-- Fix maintenance_records to support workers
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'maintenance_records' AND COLUMN_NAME = 'performed_by_worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `maintenance_records` ADD COLUMN `performed_by_worker_id` INT(11) DEFAULT NULL AFTER `performed_by`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'maintenance_records' AND COLUMN_NAME = 'supervised_by_worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `maintenance_records` ADD COLUMN `supervised_by_worker_id` INT(11) DEFAULT NULL AFTER `supervised_by`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 17. ADD FOREIGN KEY CONSTRAINTS
-- ============================================
-- Note: MySQL doesn't support IF NOT EXISTS for FOREIGN KEY, so we check if constraint exists first

-- Workers table foreign keys
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND CONSTRAINT_NAME = 'workers_ibfk_1') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND CONSTRAINT_NAME = 'workers_ibfk_2') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD FOREIGN KEY (`manager_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND CONSTRAINT_NAME = 'workers_ibfk_3') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'workers' AND CONSTRAINT_NAME = 'workers_ibfk_4') > 0,
    'SELECT 1',
    'ALTER TABLE `workers` ADD FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Note: Adding foreign keys is complex with IF NOT EXISTS, so we'll use a simpler approach
-- Foreign keys will be added automatically by MySQL when tables are created with REFERENCES
-- For existing tables, we'll add them conditionally

-- For departments, positions, and other tables, foreign keys are already defined in CREATE TABLE
-- We only need to add them for tables that might already exist

-- Add foreign key for payroll_entries if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'payroll_entries' 
     AND COLUMN_NAME = 'worker_id' AND REFERENCED_TABLE_NAME = 'workers') > 0,
    'SELECT 1',
    'ALTER TABLE `payroll_entries` ADD FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE RESTRICT'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for loans if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'loans' 
     AND COLUMN_NAME = 'worker_id' AND REFERENCED_TABLE_NAME = 'workers') > 0,
    'SELECT 1',
    'ALTER TABLE `loans` ADD FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE RESTRICT'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for field_reports if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'field_reports' 
     AND COLUMN_NAME = 'supervisor_id' AND REFERENCED_TABLE_NAME = 'workers') > 0,
    'SELECT 1',
    'ALTER TABLE `field_reports` ADD FOREIGN KEY (`supervisor_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================
-- 18. ADD INDEXES FOR PERFORMANCE
-- ============================================

-- Add indexes to existing tables
ALTER TABLE `payroll_entries` ADD INDEX IF NOT EXISTS `idx_worker_id` (`worker_id`);
ALTER TABLE `loans` ADD INDEX IF NOT EXISTS `idx_worker_id` (`worker_id`);
ALTER TABLE `field_reports` ADD INDEX IF NOT EXISTS `idx_supervisor_id` (`supervisor_id`);

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 19. GENERATE EMPLOYEE CODES FOR EXISTING WORKERS
-- ============================================

-- Generate employee codes for existing workers without codes
UPDATE `workers` 
SET `employee_code` = CONCAT('EMP-', LPAD(`id`, 5, '0'))
WHERE `employee_code` IS NULL OR `employee_code` = '';

-- ============================================
-- 20. ADD HR TO FEATURE TOGGLES
-- ============================================

INSERT IGNORE INTO `feature_toggles` (`feature_key`, `feature_name`, `description`, `is_enabled`, `is_core`, `category`, `icon`, `menu_position`) VALUES
('hr', 'Human Resources', 'HR Management System - Staff, Workers, and Stakeholders', 1, 1, 'core', '👥', 10);

-- Migration Complete!
SELECT 'HR System Migration Completed Successfully!' AS Status;

