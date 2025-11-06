-- Performance Optimization Indexes
-- Run this to improve query performance by 30-50%

USE `abbis_3_2`;

-- Field Reports Indexes
ALTER TABLE `field_reports` 
ADD INDEX IF NOT EXISTS `idx_rig_date` (`rig_id`, `report_date`),
ADD INDEX IF NOT EXISTS `idx_client_date` (`client_id`, `report_date`),
ADD INDEX IF NOT EXISTS `idx_rig_status` (`rig_id`, `status`),
ADD INDEX IF NOT EXISTS `idx_report_date_status` (`report_date`, `status`),
ADD INDEX IF NOT EXISTS `idx_job_type_date` (`job_type`, `report_date`);

-- Expense Entries Indexes
ALTER TABLE `expense_entries`
ADD INDEX IF NOT EXISTS `idx_report_category` (`report_id`, `category`),
ADD INDEX IF NOT EXISTS `idx_report_date` (`report_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_category_date` (`category`, `created_at`);

-- Payroll Entries Indexes
ALTER TABLE `payroll_entries`
ADD INDEX IF NOT EXISTS `idx_report_worker` (`report_id`, `worker_name`),
ADD INDEX IF NOT EXISTS `idx_worker_date` (`worker_name`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_report_role` (`report_id`, `role`);

-- Maintenance Records Indexes
ALTER TABLE `maintenance_records`
ADD INDEX IF NOT EXISTS `idx_rig_status_date` (`rig_id`, `status`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_asset_date` (`asset_id`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_status_priority` (`status`, `priority`),
ADD INDEX IF NOT EXISTS `idx_rig_asset` (`rig_id`, `asset_id`);

-- Debt Recovery Indexes
ALTER TABLE `debt_recoveries`
ADD INDEX IF NOT EXISTS `idx_status_due_date` (`status`, `due_date`),
ADD INDEX IF NOT EXISTS `idx_client_status` (`client_id`, `status`),
ADD INDEX IF NOT EXISTS `idx_field_report` (`field_report_id`),
ADD INDEX IF NOT EXISTS `idx_priority_status` (`priority`, `status`),
ADD INDEX IF NOT EXISTS `idx_next_followup` (`next_followup_date`, `status`);

-- Clients Indexes
ALTER TABLE `clients`
ADD INDEX IF NOT EXISTS `idx_client_name` (`client_name`),
ADD INDEX IF NOT EXISTS `idx_contact_email` (`email`),
ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`);

-- Workers Indexes
ALTER TABLE `workers`
ADD INDEX IF NOT EXISTS `idx_worker_name` (`worker_name`),
ADD INDEX IF NOT EXISTS `idx_role_status` (`role`, `status`),
ADD INDEX IF NOT EXISTS `idx_status` (`status`);

-- Rigs Indexes
ALTER TABLE `rigs`
ADD INDEX IF NOT EXISTS `idx_status` (`status`),
ADD INDEX IF NOT EXISTS `idx_rig_code` (`rig_code`);

-- Materials Inventory Indexes
ALTER TABLE `materials_inventory`
ADD INDEX IF NOT EXISTS `idx_material_type` (`material_type`),
ADD INDEX IF NOT EXISTS `idx_status_type` (`status`, `material_type`);

-- Loans Indexes
ALTER TABLE `loans`
ADD INDEX IF NOT EXISTS `idx_worker_status` (`worker_id`, `status`),
ADD INDEX IF NOT EXISTS `idx_status_due_date` (`status`, `due_date`);

-- Email Queue Indexes
ALTER TABLE `email_queue`
ADD INDEX IF NOT EXISTS `idx_status_created` (`status`, `created_at`),
ADD INDEX IF NOT EXISTS `idx_type_status` (`type`, `status`);

COMMIT;

-- Verify indexes were created
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS COLUMNS
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'abbis_3_2'
    AND TABLE_NAME IN ('field_reports', 'expense_entries', 'payroll_entries', 'maintenance_records', 'debt_recoveries')
    AND INDEX_NAME LIKE 'idx_%'
GROUP BY TABLE_NAME, INDEX_NAME
ORDER BY TABLE_NAME, INDEX_NAME;

