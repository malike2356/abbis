<?php
/**
 * Integrate Workers System-Wide - Direct PHP execution
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDBConnection();

echo "Starting worker system-wide integration...\n\n";

try {
    // ============================================
    // 1. ENHANCE PAYROLL_ENTRIES WITH WORKER_ID
    // ============================================
    
    echo "1. Enhancing payroll_entries table...\n";
    
    // Check if worker_id column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM payroll_entries LIKE 'worker_id'");
    if ($checkColumn->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `payroll_entries` ADD COLUMN `worker_id` INT(11) DEFAULT NULL AFTER `report_id`");
        echo "  ✓ Added worker_id column\n";
    } else {
        echo "  ⊙ worker_id column already exists\n";
    }
    
    // Check if index exists
    $checkIndex = $pdo->query("SHOW INDEXES FROM payroll_entries WHERE Key_name = 'idx_worker_id'");
    if ($checkIndex->rowCount() == 0) {
        $pdo->exec("ALTER TABLE `payroll_entries` ADD INDEX `idx_worker_id` (`worker_id`)");
        echo "  ✓ Added worker_id index\n";
    } else {
        echo "  ⊙ worker_id index already exists\n";
    }
    
    // Migrate existing payroll_entries to link worker_id
    $updateStmt = $pdo->prepare("
        UPDATE payroll_entries pe
        INNER JOIN workers w ON pe.worker_name = w.worker_name
        SET pe.worker_id = w.id
        WHERE pe.worker_id IS NULL
    ");
    $updateStmt->execute();
    $updated = $updateStmt->rowCount();
    echo "  ✓ Linked {$updated} payroll entries to workers\n";
    
    // ============================================
    // 2. CREATE FIELD_REPORT_WORKERS TABLE
    // ============================================
    
    echo "\n2. Creating field_report_workers table...\n";
    
    $pdo->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  ✓ Table created\n";
    
    // Populate from payroll_entries
    $populateStmt = $pdo->prepare("
        INSERT INTO field_report_workers (report_id, worker_id, role)
        SELECT DISTINCT pe.report_id, pe.worker_id, pe.role
        FROM payroll_entries pe
        WHERE pe.worker_id IS NOT NULL
        ON DUPLICATE KEY UPDATE role = VALUES(role)
    ");
    $populateStmt->execute();
    $populated = $populateStmt->rowCount();
    echo "  ✓ Populated {$populated} worker-job links\n";
    
    // ============================================
    // 3. CREATE VIEWS (views auto-commit, so do separately)
    // ============================================
    
    echo "\n3. Creating database views...\n";
    
    // Worker Job Activity View
    $pdo->exec("DROP VIEW IF EXISTS `worker_job_activity`");
    $pdo->exec("
        CREATE VIEW `worker_job_activity` AS
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
        WHERE w.status = 'active'
    ");
    echo "  ✓ Created worker_job_activity view\n";
    
    // Worker Statistics View
    $pdo->exec("DROP VIEW IF EXISTS `worker_statistics`");
    $pdo->exec("
        CREATE VIEW `worker_statistics` AS
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
        GROUP BY w.id, w.worker_name, w.role, w.department_id, d.department_name, w.position_id, p.position_title
    ");
    echo "  ✓ Created worker_statistics view\n";
    
    // Weekly Jobs View
    $pdo->exec("DROP VIEW IF EXISTS `worker_weekly_jobs`");
    $pdo->exec("
        CREATE VIEW `worker_weekly_jobs` AS
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
        GROUP BY w.id, w.worker_name, w.role, YEAR(fr.report_date), WEEK(fr.report_date, 1)
    ");
    echo "  ✓ Created worker_weekly_jobs view\n";
    
    // Verification
    echo "\n=== Verification ===\n";
    
    $checkStmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(worker_id) as with_worker_id,
            COUNT(*) - COUNT(worker_id) as without_worker_id
        FROM payroll_entries
    ");
    $payrollStats = $checkStmt->fetch(PDO::FETCH_ASSOC);
    echo "Payroll Entries: Total={$payrollStats['total']}, Linked={$payrollStats['with_worker_id']}, Unlinked={$payrollStats['without_worker_id']}\n";
    
    $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM field_report_workers");
    $frwCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Field Report Workers: {$frwCount} records\n";
    
    $checkStmt = $pdo->query("SELECT COUNT(*) as count FROM worker_statistics");
    $statsCount = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "Worker Statistics: {$statsCount} workers with stats\n";
    
    echo "\n✅ Worker system-wide integration completed successfully!\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

