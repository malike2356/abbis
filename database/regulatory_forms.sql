/* Regulatory forms template storage */
CREATE TABLE IF NOT EXISTS `regulatory_form_templates` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `form_name` VARCHAR(150) NOT NULL,
  `jurisdiction` VARCHAR(120) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `reference_type` ENUM('field_report','rig','client','custom') NOT NULL DEFAULT 'field_report',
  `html_template` MEDIUMTEXT NOT NULL,
  `instructions` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_reference_type` (`reference_type`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `regulatory_form_exports` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `template_id` INT NOT NULL,
  `reference_type` ENUM('field_report','rig','client','custom') NOT NULL,
  `reference_id` INT DEFAULT NULL,
  `generated_by` INT DEFAULT NULL,
  `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `output_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  INDEX `idx_template` (`template_id`),
  INDEX `idx_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_reg_form_template` FOREIGN KEY (`template_id`) REFERENCES `regulatory_form_templates`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

