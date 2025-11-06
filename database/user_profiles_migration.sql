-- User Profiles & Social Login Migration
-- Run this to extend the users table with profile and authentication features

USE `abbis_3_2`;

-- Extend users table with profile fields
-- Note: IF NOT EXISTS is not supported in ALTER TABLE, so run this carefully
-- Check if columns exist first, or run once only

ALTER TABLE `users` 
ADD COLUMN `phone_number` VARCHAR(20) DEFAULT NULL AFTER `email`,
ADD COLUMN `date_of_birth` DATE DEFAULT NULL AFTER `phone_number`,
ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `date_of_birth`,
ADD COLUMN `bio` TEXT DEFAULT NULL AFTER `profile_photo`,
ADD COLUMN `address` TEXT DEFAULT NULL AFTER `bio`,
ADD COLUMN `city` VARCHAR(100) DEFAULT NULL AFTER `address`,
ADD COLUMN `country` VARCHAR(100) DEFAULT 'Ghana' AFTER `city`,
ADD COLUMN `postal_code` VARCHAR(20) DEFAULT NULL AFTER `country`,
ADD COLUMN `emergency_contact_name` VARCHAR(100) DEFAULT NULL AFTER `postal_code`,
ADD COLUMN `emergency_contact_phone` VARCHAR(20) DEFAULT NULL AFTER `emergency_contact_name`,
ADD COLUMN `email_verified` TINYINT(1) DEFAULT 0 AFTER `emergency_contact_phone`,
ADD COLUMN `phone_verified` TINYINT(1) DEFAULT 0 AFTER `email_verified`,
ADD COLUMN `two_factor_enabled` TINYINT(1) DEFAULT 0 AFTER `phone_verified`,
ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `is_active`;

-- Add unique constraint for phone
-- Run separately if column already exists and constraint is needed:
-- ALTER TABLE `users` ADD UNIQUE KEY `phone_number` (`phone_number`);

-- Social authentication table
CREATE TABLE IF NOT EXISTS `user_social_auth` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `provider` ENUM('google', 'facebook', 'phone') NOT NULL,
  `provider_user_id` VARCHAR(255) NOT NULL,
  `access_token` TEXT DEFAULT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `provider_user` (`provider`, `provider_user_id`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email verification tokens
CREATE TABLE IF NOT EXISTS `email_verification_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `user_id` (`user_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Phone verification codes (for phone number login)
CREATE TABLE IF NOT EXISTS `phone_verification_codes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone_number` VARCHAR(20) NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `verified` TINYINT(1) DEFAULT 0,
  `attempts` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `phone_number` (`phone_number`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

