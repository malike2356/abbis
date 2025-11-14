-- Geology Estimator schema

CREATE TABLE IF NOT EXISTS `geology_wells` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reference_code` VARCHAR(80) DEFAULT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `region` VARCHAR(150) DEFAULT NULL,
  `district` VARCHAR(150) DEFAULT NULL,
  `community` VARCHAR(150) DEFAULT NULL,
  `depth_m` DECIMAL(8,2) NOT NULL,
  `static_water_level_m` DECIMAL(8,2) DEFAULT NULL,
  `yield_m3_per_hr` DECIMAL(8,2) DEFAULT NULL,
  `aquifer_type` VARCHAR(120) DEFAULT NULL,
  `lithology` TEXT DEFAULT NULL,
  `water_quality_notes` TEXT DEFAULT NULL,
  `tds_mg_per_l` DECIMAL(8,2) DEFAULT NULL,
  `sample_date` DATE DEFAULT NULL,
  `data_source` VARCHAR(150) DEFAULT NULL,
  `confidence_score` DECIMAL(5,2) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_geology_location` (`latitude`, `longitude`),
  INDEX `idx_geology_region` (`region`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `geology_prediction_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `client_id` INT DEFAULT NULL,
  `user_id` INT DEFAULT NULL,
  `latitude` DECIMAL(10,7) NOT NULL,
  `longitude` DECIMAL(10,7) NOT NULL,
  `region` VARCHAR(150) DEFAULT NULL,
  `district` VARCHAR(150) DEFAULT NULL,
  `requested_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `predicted_depth_min_m` DECIMAL(8,2) DEFAULT NULL,
  `predicted_depth_avg_m` DECIMAL(8,2) DEFAULT NULL,
  `predicted_depth_max_m` DECIMAL(8,2) DEFAULT NULL,
  `confidence_score` DECIMAL(5,2) DEFAULT NULL,
  `neighbor_count` INT DEFAULT 0,
  `estimation_method` VARCHAR(80) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  INDEX `idx_prediction_client` (`client_id`),
  INDEX `idx_prediction_location` (`latitude`, `longitude`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

