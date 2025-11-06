-- ============================================
-- DATA INTERCONNECTION MIGRATION
-- Links field_reports and maintenance_records
-- Adds maintenance work tracking to field reports
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. LINK MAINTENANCE RECORDS TO FIELD REPORTS
-- ============================================

-- Add field_report_id to maintenance_records
ALTER TABLE `maintenance_records`
ADD COLUMN `field_report_id` INT(11) DEFAULT NULL COMMENT 'Linked field report if maintenance was recorded in field report' AFTER `rig_id`,
ADD INDEX `idx_field_report_id` (`field_report_id`);

-- Add foreign key constraint (ON DELETE SET NULL to preserve maintenance records if field report is deleted)
ALTER TABLE `maintenance_records`
ADD CONSTRAINT `fk_maintenance_field_report` 
FOREIGN KEY (`field_report_id`) REFERENCES `field_reports` (`id`) ON DELETE SET NULL;

-- ============================================
-- 2. ADD MAINTENANCE WORK TRACKING TO FIELD REPORTS
-- ============================================

-- Add maintenance work flag
ALTER TABLE `field_reports`
ADD COLUMN `is_maintenance_work` TINYINT(1) DEFAULT 0 COMMENT '1 if this report is for maintenance work, not drilling' AFTER `job_type`,
ADD INDEX `idx_is_maintenance` (`is_maintenance_work`);

-- Add maintenance work type
ALTER TABLE `field_reports`
ADD COLUMN `maintenance_work_type` VARCHAR(100) DEFAULT NULL COMMENT 'Type of maintenance work performed' AFTER `is_maintenance_work`;

-- Extend job_type to include maintenance option
ALTER TABLE `field_reports`
MODIFY COLUMN `job_type` ENUM('direct','subcontract','maintenance') NOT NULL DEFAULT 'direct';

-- Add maintenance description field
ALTER TABLE `field_reports`
ADD COLUMN `maintenance_description` TEXT DEFAULT NULL COMMENT 'Maintenance work description extracted from logs' AFTER `maintenance_work_type`;

-- ============================================
-- 3. LINK FIELD REPORTS TO ASSETS (for maintenance tracking)
-- ============================================

-- Add asset_id to field_reports (for maintenance work on specific assets)
ALTER TABLE `field_reports`
ADD COLUMN `asset_id` INT(11) DEFAULT NULL COMMENT 'Asset being maintained (if applicable)' AFTER `rig_id`,
ADD INDEX `idx_asset_id` (`asset_id`);

-- Add foreign key to assets
ALTER TABLE `field_reports`
ADD CONSTRAINT `fk_field_report_asset` 
FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL;

-- ============================================
-- 4. LINK MAINTENANCE EXPENSES TO FIELD REPORT EXPENSES
-- ============================================

-- Add maintenance_record_id to expense_entries (to link expenses to maintenance)
ALTER TABLE `expense_entries`
ADD COLUMN `maintenance_record_id` INT(11) DEFAULT NULL COMMENT 'Linked maintenance record if expense is for maintenance' AFTER `report_id`,
ADD INDEX `idx_maintenance_record_id` (`maintenance_record_id`);

-- Add foreign key constraint
ALTER TABLE `expense_entries`
ADD CONSTRAINT `fk_expense_maintenance` 
FOREIGN KEY (`maintenance_record_id`) REFERENCES `maintenance_records` (`id`) ON DELETE SET NULL;

-- ============================================
-- 5. CREATE INDEXES FOR PERFORMANCE
-- ============================================

-- Index for querying maintenance by field report
CREATE INDEX IF NOT EXISTS `idx_maintenance_field_report` ON `maintenance_records` (`field_report_id`, `rig_id`);

-- Index for querying field reports by maintenance work
CREATE INDEX IF NOT EXISTS `idx_field_report_maintenance` ON `field_reports` (`is_maintenance_work`, `report_date`);

-- Index for querying expenses by maintenance
CREATE INDEX IF NOT EXISTS `idx_expense_maintenance` ON `expense_entries` (`maintenance_record_id`);

-- ============================================
-- 6. CREATE VIEW FOR MAINTENANCE-FIELD REPORT LINKAGE
-- ============================================

CREATE OR REPLACE VIEW `v_maintenance_field_reports` AS
SELECT 
    mr.id as maintenance_id,
    mr.maintenance_code,
    mr.status as maintenance_status,
    mr.description as maintenance_description,
    mr.work_performed,
    mr.total_cost as maintenance_cost,
    fr.id as field_report_id,
    fr.report_id,
    fr.report_date,
    fr.rig_id,
    r.rig_name,
    r.rig_code,
    fr.site_name,
    fr.maintenance_work_type,
    fr.is_maintenance_work,
    fr.incident_log,
    fr.solution_log,
    fr.remarks
FROM maintenance_records mr
LEFT JOIN field_reports fr ON mr.field_report_id = fr.id
LEFT JOIN rigs r ON mr.rig_id = r.id
WHERE mr.field_report_id IS NOT NULL;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- MIGRATION COMPLETE
-- ============================================
-- Summary:
-- 1. Added field_report_id to maintenance_records
-- 2. Added is_maintenance_work and maintenance_work_type to field_reports
-- 3. Extended job_type enum to include 'maintenance'
-- 4. Added asset_id to field_reports for asset-specific maintenance
-- 5. Linked expense_entries to maintenance_records
-- 6. Created indexes for performance
-- 7. Created view for easy querying
-- ============================================

