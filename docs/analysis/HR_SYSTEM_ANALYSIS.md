# HR System - Staff and Stakeholders Analysis & Implementation Plan
## Comprehensive HR System for ABBIS

**Date:** <?php echo date('Y-m-d'); ?>  
**Status:** Analysis Complete - Awaiting Approval

---

## 🔍 **CURRENT STATE ANALYSIS**

### **1. Existing Worker Management**

✅ **What EXISTS:**
- `workers` table (basic structure):
  - `id`, `worker_name`, `role`, `default_rate`, `contact_number`, `email`, `status`, `created_at`
  - Used for payroll entries and basic worker tracking
- Worker references in:
  - `payroll_entries.worker_name` (text field - **PROBLEM**)
  - `loans.worker_name` (text field - **PROBLEM**)
  - `field_reports.supervisor` (text field - **PROBLEM**)

❌ **Critical Issues:**
1. **No Foreign Key Relationship**: `payroll_entries` and `loans` reference workers by NAME (text) instead of ID
2. **Data Integrity Risk**: Worker name changes break historical data
3. **No User-Worker Link**: System users (`users` table) are separate from workers - no connection
4. **Limited HR Data**: No employee information, hiring date, position, department, etc.
5. **No Stakeholder Management**: No system to track stakeholders (partners, investors, board members, etc.)
6. **No HR Features**: No leave management, attendance, performance reviews, training, etc.

---

### **2. System-Wide Interconnections**

#### **Current Worker References:**

**Field Reports:**
- `field_reports.supervisor` → text field (should link to `workers.id`)
- `field_reports.created_by` → links to `users.id` (system user, not worker)

**Payroll:**
- `payroll_entries.worker_name` → text field (should link to `workers.id`)
- `payroll_entries.role` → text field (should link to position/role)

**Loans:**
- `loans.worker_name` → text field (should link to `workers.id`)
- `loans.created_by` → links to `users.id`

**Maintenance:**
- `maintenance_records.performed_by` → links to `users.id` (should optionally link to `workers.id`)
- `maintenance_records.supervised_by` → links to `users.id` (should optionally link to `workers.id`)

**Assets:**
- `assets.assigned_to` → links to `workers.id` (✅ already correct)

**Users vs Workers:**
- `users` table: System login accounts (admin, manager, supervisor, clerk)
- `workers` table: Field workers (driller, rig driver, rod boy, etc.)
- **PROBLEM**: These are separate systems with no connection

---

### **3. Missing HR Features**

**Standard HR Requirements:**
1. ❌ Employee Information Management
   - Personal details (DOB, ID number, address, emergency contacts)
   - Employment details (hire date, position, department, manager)
   - Employment history (contracts, promotions, transfers)

2. ❌ Staff vs Workers Distinction
   - Staff: Office-based employees (admin, manager, supervisor, clerk)
   - Workers: Field-based employees (driller, operator, driver, laborer)
   - Should be unified under HR system

3. ❌ Attendance & Time Tracking
   - Daily attendance records
   - Time in/out for field workers
   - Overtime tracking
   - Absenteeism tracking

4. ❌ Leave Management
   - Leave types (annual, sick, casual, unpaid)
   - Leave requests and approvals
   - Leave balance tracking
   - Leave calendar

5. ❌ Performance Management
   - Performance reviews
   - KPIs and goals
   - Performance ratings
   - Performance history

6. ❌ Training & Development
   - Training records
   - Certifications
   - Skills inventory
   - Training schedules

7. ❌ Compensation Management
   - Salary/wage structure
   - Allowances and benefits
   - Pay grades and bands
   - Compensation history

8. ❌ Stakeholder Management
   - Stakeholder types (board members, investors, partners, advisors)
   - Stakeholder information
   - Stakeholder relationships
   - Communication history

9. ❌ Document Management
   - Employee documents (contracts, ID, certificates)
   - Document storage and retrieval
   - Document expiry tracking

10. ❌ Organizational Structure
    - Departments and divisions
    - Reporting hierarchy
    - Team structure
    - Organizational chart

---

## 🎯 **PROPOSED HR SYSTEM ARCHITECTURE**

### **Core Principle: Unified HR System**

**Concept:**
- **HR Module** manages ALL personnel (staff + workers + stakeholders)
- **Worker Management** becomes part of HR (not separate)
- **Staff** = System users who are also employees
- **Workers** = Field workers who may or may not have system access
- **Stakeholders** = External parties with interest in business

---

### **Database Schema Design**

#### **1. Enhanced Workers Table (Core HR Data)**

```sql
-- Enhance existing workers table
ALTER TABLE `workers` 
ADD COLUMN `employee_code` VARCHAR(50) UNIQUE AFTER `id`,
ADD COLUMN `employee_type` ENUM('staff', 'worker', 'contractor', 'stakeholder') DEFAULT 'worker' AFTER `employee_code`,
ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `employee_type` COMMENT 'Link to users table if they have system access',
ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `email`,
ADD COLUMN `national_id` VARCHAR(50) DEFAULT NULL AFTER `date_of_birth`,
ADD COLUMN `gender` ENUM('male', 'female', 'other') DEFAULT NULL AFTER `national_id`,
ADD COLUMN `address` TEXT DEFAULT NULL AFTER `gender`,
ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `country` VARCHAR(100) DEFAULT 'Ghana' AFTER `city`,
ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL AFTER `country`,
ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL AFTER `postal_code`,
ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL AFTER `emergency_contact_name`,
ADD COLUMN `emergency_contact_relationship` VARCHAR(50) DEFAULT NULL AFTER `emergency_contact_phone`,
ADD COLUMN `hire_date` DATE DEFAULT NULL AFTER `emergency_contact_relationship`,
ADD COLUMN `employment_type` ENUM('full_time', 'part_time', 'contract', 'casual', 'intern') DEFAULT 'full_time' AFTER `hire_date`,
ADD COLUMN `department_id` INT(11) DEFAULT NULL AFTER `employment_type`,
ADD COLUMN `position_id` INT(11) DEFAULT NULL AFTER `department_id`,
ADD COLUMN `manager_id` INT(11) DEFAULT NULL AFTER `position_id` COMMENT 'Reports to (worker ID)',
ADD COLUMN `salary` DECIMAL(12,2) DEFAULT NULL AFTER `manager_id`,
ADD COLUMN `bank_name` VARCHAR(100) DEFAULT NULL AFTER `salary`,
ADD COLUMN `bank_account_number` VARCHAR(50) DEFAULT NULL AFTER `bank_name`,
ADD COLUMN `bank_branch` VARCHAR(100) DEFAULT NULL AFTER `bank_account_number`,
ADD COLUMN `tax_id` VARCHAR(50) DEFAULT NULL AFTER `bank_branch`,
ADD COLUMN `photo_path` VARCHAR(255) DEFAULT NULL AFTER `tax_id`,
ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `photo_path`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,

-- Add indexes
ADD INDEX `idx_employee_code` (`employee_code`),
ADD INDEX `idx_employee_type` (`employee_type`),
ADD INDEX `idx_user_id` (`user_id`),
ADD INDEX `idx_department_id` (`department_id`),
ADD INDEX `idx_position_id` (`position_id`),
ADD INDEX `idx_manager_id` (`manager_id`),
ADD INDEX `idx_hire_date` (`hire_date`),

-- Add foreign keys
ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
ADD FOREIGN KEY (`manager_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL;
```

#### **2. New HR Tables**

```sql
-- Departments
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
  KEY `parent_department_id` (`parent_department_id`),
  FOREIGN KEY (`manager_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`parent_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Positions/Job Titles
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
  KEY `reports_to_position_id` (`reports_to_position_id`),
  FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`reports_to_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Attendance Records
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
  KEY `attendance_status` (`attendance_status`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Types
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

-- Leave Requests
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
  KEY `start_date` (`start_date`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leave Balances
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
  UNIQUE KEY `worker_leave_year` (`worker_id`, `leave_type_id`, `year`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Performance Reviews
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
  KEY `review_period_end` (`review_period_end`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Performance Review Criteria/Scores
CREATE TABLE IF NOT EXISTS `performance_review_scores` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `review_id` INT(11) NOT NULL,
  `criteria_name` VARCHAR(100) NOT NULL,
  `score` DECIMAL(3,2) NOT NULL COMMENT '1.00 to 5.00',
  `weight` DECIMAL(5,2) DEFAULT 1.00 COMMENT 'Weight for calculation',
  `comments` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `review_id` (`review_id`),
  FOREIGN KEY (`review_id`) REFERENCES `performance_reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Training Records
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
  KEY `certificate_expiry` (`certificate_expiry`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Skills Inventory
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
  KEY `skill_name` (`skill_name`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employee Documents
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
  KEY `expiry_date` (`expiry_date`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Employment History
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
  KEY `event_type` (`event_type`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`previous_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`new_position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`previous_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`new_department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stakeholders
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
  KEY `is_active` (`is_active`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Stakeholder Communications
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
  KEY `communication_date` (`communication_date`),
  FOREIGN KEY (`stakeholder_id`) REFERENCES `stakeholders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### **3. Fix Existing Tables (Data Integrity)**

```sql
-- Fix payroll_entries to use worker_id instead of worker_name
ALTER TABLE `payroll_entries`
ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `id`,
ADD INDEX `idx_worker_id` (`worker_id`),
ADD FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE RESTRICT;

-- Migrate existing data: Match worker_name to workers.id
UPDATE `payroll_entries` pe
INNER JOIN `workers` w ON pe.worker_name = w.worker_name
SET pe.worker_id = w.id
WHERE pe.worker_id IS NULL;

-- After migration, make worker_id NOT NULL and remove worker_name (or keep for backward compatibility)
-- ALTER TABLE `payroll_entries` MODIFY COLUMN `worker_id` INT(11) NOT NULL;

-- Fix loans table
ALTER TABLE `loans`
ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `id`,
ADD INDEX `idx_worker_id` (`worker_id`),
ADD FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE RESTRICT;

-- Migrate existing data
UPDATE `loans` l
INNER JOIN `workers` w ON l.worker_name = w.worker_name
SET l.worker_id = w.id
WHERE l.worker_id IS NULL;

-- Fix field_reports supervisor
ALTER TABLE `field_reports`
ADD COLUMN `supervisor_id` INT(11) DEFAULT NULL AFTER `supervisor`,
ADD INDEX `idx_supervisor_id` (`supervisor_id`),
ADD FOREIGN KEY (`supervisor_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL;

-- Migrate existing data (will need manual matching for some)
UPDATE `field_reports` fr
INNER JOIN `workers` w ON fr.supervisor = w.worker_name
SET fr.supervisor_id = w.id
WHERE fr.supervisor_id IS NULL AND fr.supervisor IS NOT NULL;

-- Fix maintenance_records to support both users and workers
-- Keep performed_by and supervised_by as user_id, but add worker_id fields
ALTER TABLE `maintenance_records`
ADD COLUMN `performed_by_worker_id` INT(11) DEFAULT NULL AFTER `performed_by`,
ADD COLUMN `supervised_by_worker_id` INT(11) DEFAULT NULL AFTER `supervised_by`,
ADD INDEX `idx_performed_by_worker` (`performed_by_worker_id`),
ADD INDEX `idx_supervised_by_worker` (`supervised_by_worker_id`),
ADD FOREIGN KEY (`performed_by_worker_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL,
ADD FOREIGN KEY (`supervised_by_worker_id`) REFERENCES `workers` (`id`) ON DELETE SET NULL;
```

---

### **4. System Interconnections Map**

#### **HR → Field Reports**
- `field_reports.supervisor_id` → `workers.id`
- Field report shows worker attendance/availability
- Worker performance affects field report quality

#### **HR → Payroll**
- `payroll_entries.worker_id` → `workers.id`
- Payroll uses worker salary/wage from HR
- Leave balances affect payroll calculations
- Overtime from attendance affects payroll

#### **HR → Loans**
- `loans.worker_id` → `workers.id`
- Loan eligibility based on employment status
- Loan repayment from payroll (already implemented)

#### **HR → Maintenance**
- `maintenance_records.performed_by_worker_id` → `workers.id`
- Worker skills determine who can perform maintenance
- Training records show maintenance certifications

#### **HR → Assets**
- `assets.assigned_to` → `workers.id`
- Asset assignment based on worker role/department
- Asset maintenance linked to worker training

#### **HR → Clients/CRM**
- Stakeholders can be clients
- Worker-client relationships
- Communication history

#### **HR → Accounting**
- Worker salaries/wages in accounting
- Benefits and allowances in accounting
- Training costs in accounting

#### **HR → Analytics**
- Workforce analytics
- Performance metrics
- Attendance trends
- Training ROI

---

## 📋 **IMPLEMENTATION PLAN**

### **Phase 1: Database Migration** 
1. Create all new HR tables
2. Enhance workers table
3. Fix existing table relationships (payroll, loans, field_reports)
4. Migrate existing data
5. Add foreign key constraints

### **Phase 2: Core HR Module**
1. Create `modules/hr.php` - Main HR dashboard
2. Employee Management (CRUD)
3. Department Management
4. Position Management
5. Basic reporting

### **Phase 3: Attendance & Leave**
1. Attendance tracking system
2. Leave management
3. Leave balance tracking
4. Leave calendar

### **Phase 4: Performance & Training**
1. Performance review system
2. Training management
3. Skills inventory
4. Certification tracking

### **Phase 5: Stakeholder Management**
1. Stakeholder CRUD
2. Stakeholder communications
3. Stakeholder reporting

### **Phase 6: Integration**
1. Integrate HR with Payroll
2. Integrate HR with Field Reports
3. Integrate HR with Maintenance
4. Update all existing modules to use worker_id

### **Phase 7: Advanced Features**
1. Document management
2. Employment history
3. Organizational chart
4. HR analytics dashboard

---

## 🚨 **RISKS & MITIGATION**

**Risk 1: Data Migration Issues**
- **Mitigation**: Backup database before migration
- Test migration on development environment
- Create rollback scripts
- Migrate in phases

**Risk 2: Breaking Existing Functionality**
- **Mitigation**: Keep backward compatibility (worker_name fields)
- Gradual migration approach
- Extensive testing

**Risk 3: User-Worker Link Complexity**
- **Mitigation**: Clear distinction between system users and workers
- Optional linking (not all workers need system access)
- Clear documentation

**Risk 4: Performance Impact**
- **Mitigation**: Proper indexing
- Query optimization
- Caching where appropriate

---

## ✅ **BENEFITS**

1. **Unified Personnel Management** - All staff, workers, and stakeholders in one system
2. **Data Integrity** - Foreign keys ensure referential integrity
3. **Comprehensive HR Features** - Standard HR functionality
4. **System Integration** - HR connects to all other modules
5. **Better Reporting** - HR analytics across entire system
6. **Scalability** - Can grow with business needs
7. **Compliance** - Track certifications, documents, training

---

## 📊 **SUCCESS METRICS**

- ✅ 100% of workers linked by ID (not name)
- ✅ All HR features operational
- ✅ Zero data integrity issues
- ✅ All modules integrated with HR
- ✅ Complete employment history tracking
- ✅ Real-time attendance and leave tracking

---

**Ready for implementation once approved!** 🚀

