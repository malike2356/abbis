-- Worker-Rig-Role Mapping System Migration
-- Allows workers to have multiple roles and track rig preferences

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

USE `abbis_3_2`;

-- 1. Worker Role Assignments Table (Many-to-Many: Workers can have multiple roles)
CREATE TABLE IF NOT EXISTS `worker_role_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `role_name` varchar(100) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `default_rate` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_role_unique` (`worker_id`, `role_name`),
  KEY `worker_id` (`worker_id`),
  KEY `role_name` (`role_name`),
  KEY `is_primary` (`is_primary`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Worker Rig Preferences Table (Many-to-Many: Workers can work on multiple rigs)
CREATE TABLE IF NOT EXISTS `worker_rig_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `worker_id` int(11) NOT NULL,
  `rig_id` int(11) NOT NULL,
  `preference_level` enum('primary','secondary','occasional') DEFAULT 'primary',
  `notes` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `worker_rig_unique` (`worker_id`, `rig_id`),
  KEY `worker_id` (`worker_id`),
  KEY `rig_id` (`rig_id`),
  KEY `preference_level` (`preference_level`),
  FOREIGN KEY (`worker_id`) REFERENCES `workers` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`rig_id`) REFERENCES `rigs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Migrate existing worker roles to worker_role_assignments
INSERT INTO `worker_role_assignments` (`worker_id`, `role_name`, `is_primary`, `default_rate`)
SELECT 
    w.id as worker_id,
    w.role as role_name,
    1 as is_primary,
    w.default_rate as default_rate
FROM `workers` w
WHERE w.role IS NOT NULL AND w.role != ''
ON DUPLICATE KEY UPDATE 
    `default_rate` = VALUES(`default_rate`),
    `updated_at` = CURRENT_TIMESTAMP;

-- 4. Create indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_worker_role_primary` ON `worker_role_assignments` (`worker_id`, `is_primary`);
CREATE INDEX IF NOT EXISTS `idx_worker_rig_primary` ON `worker_rig_preferences` (`rig_id`, `preference_level`);

-- Success message
SELECT 'Worker-Rig-Role Mapping tables created and data migrated successfully!' as status;

