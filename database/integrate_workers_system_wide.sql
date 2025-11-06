-- Integrate Workers System-Wide
-- Links workers to field reports, payroll, and enables comprehensive tracking

USE `abbis_3_2`;

-- ============================================
-- 1. ENHANCE PAYROLL_ENTRIES WITH WORKER_ID
-- ============================================

-- Add worker_id column if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `payroll_entries` ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `report_id`'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for worker_id
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = 'abbis_3_2' AND TABLE_NAME = 'payroll_entries' AND COLUMN_NAME = 'worker_id') > 0,
    'SELECT 1',
    'ALTER TABLE `payroll_entries` ADD INDEX `idx_worker_id` (`worker_id`)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Migrate existing payroll_entries to link worker_id based on worker_name
UPDATE payroll_entries pe
INNER JOIN workers w ON pe.worker_name = w.worker_name
SET pe.worker_id = w.id
WHERE pe.worker_id IS NULL;

-- ============================================
-- 2. ENHANCE FIELD_REPORTS WITH WORKER LINKS
-- ============================================

-- Create field_report_workers junction table to track all workers on a job
CREATE TABLE IF NOT EXISTS `field_report_workers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `report_id` INT(11) NOT NULL,
  `worker_id` INT(11) NOT NULL,
  `role` VARCHAR(50) DEFAULT NULL COMMENT 'Role on this specific job',
  `hours_worked` DECIMAL(5,2) DEFAULT NULL,
  `is_present` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_worker_unique` (`report_id`, `worker_id`),
  KEY `report_id` (`report_id`),
  KEY `worker_id` (`worker_id`),
  FOREIGN KEY (`report_id`) REFERENCES `field_reports` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Populate field_report_workers from payroll_entries
INSERT INTO field_report_workers (report_id, worker_id, role)
SELECT DISTINCT pe.report_id, pe.worker_id, pe.role
FROM payroll_entries pe
WHERE pe.worker_id IS NOT NULL
ON DUPLICATE KEY UPDATE role = VALUES(role);

-- ============================================
-- 3. CREATE WORKER ACTIVITY VIEW
-- ============================================

CREATE OR REPLACE VIEW `worker_job_activity` AS
SELECT 
    w.id as worker_id,
    w.worker_name,
    w.role,
    fr.id as report_id,
    fr.report_id as report_reference,
    fr.report_date,
    fr.site_name,
    fr.rig_id,
    r.rig_name,
    r.rig_code,
    fr.client_id,
    c.client_name,
    fr.job_type,
    YEAR(fr.report_date) as year,
    MONTH(fr.report_date) as month,
    WEEK(fr.report_date, 1) as week,
    DAYOFWEEK(fr.report_date) as day_of_week,
    pe.amount as wage_amount,
    pe.paid_today,
    fr.total_rpm,
    fr.total_depth
FROM workers w
INNER JOIN payroll_entries pe ON w.id = pe.worker_id
INNER JOIN field_reports fr ON pe.report_id = fr.id
LEFT JOIN rigs r ON fr.rig_id = r.id
LEFT JOIN clients c ON fr.client_id = c.id
WHERE w.status = 'active';

-- ============================================
-- 4. CREATE WORKER STATISTICS VIEW
-- ============================================

CREATE OR REPLACE VIEW `worker_statistics` AS
SELECT 
    w.id as worker_id,
    w.worker_name,
    w.role,
    w.department_id,
    d.department_name,
    w.position_id,
    p.position_title,
    COUNT(DISTINCT fr.id) as total_jobs,
    COUNT(DISTINCT CASE WHEN fr.report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN fr.id END) as jobs_last_week,
    COUNT(DISTINCT CASE WHEN fr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN fr.id END) as jobs_last_month,
    MIN(fr.report_date) as first_job_date,
    MAX(fr.report_date) as last_job_date,
    SUM(pe.amount) as total_wages,
    SUM(CASE WHEN fr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN pe.amount ELSE 0 END) as wages_last_month,
    AVG(pe.amount) as avg_wage_per_job,
    COUNT(DISTINCT fr.rig_id) as rigs_worked_on,
    COUNT(DISTINCT fr.client_id) as clients_worked_for
FROM workers w
LEFT JOIN payroll_entries pe ON w.id = pe.worker_id
LEFT JOIN field_reports fr ON pe.report_id = fr.id
LEFT JOIN departments d ON w.department_id = d.id
LEFT JOIN positions p ON w.position_id = p.id
WHERE w.status = 'active'
GROUP BY w.id, w.worker_name, w.role, w.department_id, d.department_name, w.position_id, p.position_title;

-- ============================================
-- 5. CREATE WEEKLY JOB SUMMARY VIEW
-- ============================================

CREATE OR REPLACE VIEW `worker_weekly_jobs` AS
SELECT 
    w.id as worker_id,
    w.worker_name,
    w.role,
    YEAR(fr.report_date) as year,
    WEEK(fr.report_date, 1) as week,
    DATE(DATE_SUB(fr.report_date, INTERVAL WEEKDAY(fr.report_date) DAY)) as week_start,
    COUNT(DISTINCT fr.id) as jobs_count,
    GROUP_CONCAT(DISTINCT fr.report_id ORDER BY fr.report_date SEPARATOR ', ') as report_ids,
    GROUP_CONCAT(DISTINCT fr.site_name ORDER BY fr.report_date SEPARATOR ', ') as sites,
    SUM(pe.amount) as total_wages,
    SUM(fr.total_rpm) as total_rpm,
    COUNT(DISTINCT fr.rig_id) as rigs_count,
    COUNT(DISTINCT fr.client_id) as clients_count
FROM workers w
INNER JOIN payroll_entries pe ON w.id = pe.worker_id
INNER JOIN field_reports fr ON pe.report_id = fr.id
WHERE w.status = 'active'
GROUP BY w.id, w.worker_name, w.role, YEAR(fr.report_date), WEEK(fr.report_date, 1);

