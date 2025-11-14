-- Recruitment Module Migration
-- Builds the vacancy, applicant tracking, and hiring workflow infrastructure

USE `abbis_3_2`;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- 1. CORE MASTER DATA
-- ============================================

CREATE TABLE IF NOT EXISTS `recruitment_candidates` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `candidate_code` VARCHAR(20) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `other_names` VARCHAR(100) DEFAULT NULL,
  `preferred_name` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone_primary` VARCHAR(50) DEFAULT NULL,
  `phone_secondary` VARCHAR(50) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `linkedin_url` VARCHAR(255) DEFAULT NULL,
  `portfolio_url` VARCHAR(255) DEFAULT NULL,
  `years_experience` DECIMAL(5,2) DEFAULT NULL,
  `highest_education` VARCHAR(150) DEFAULT NULL,
  `current_employer` VARCHAR(150) DEFAULT NULL,
  `current_position` VARCHAR(150) DEFAULT NULL,
  `expected_salary` DECIMAL(12,2) DEFAULT NULL,
  `availability_date` DATE DEFAULT NULL,
  `consent_to_contact` TINYINT(1) DEFAULT 1,
  `source` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `candidate_code` (`candidate_code`),
  UNIQUE KEY `candidate_email` (`email`),
  KEY `idx_candidate_country` (`country`),
  KEY `idx_candidate_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_tags` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tag_key` VARCHAR(50) NOT NULL,
  `tag_label` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tag_key` (`tag_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_candidate_tags` (
  `candidate_id` INT(11) NOT NULL,
  `tag_id` INT(11) NOT NULL,
  `assigned_by` INT(11) DEFAULT NULL,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`candidate_id`, `tag_id`),
  KEY `idx_candidate_tag` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 2. VACANCY MANAGEMENT
-- ============================================

CREATE TABLE IF NOT EXISTS `recruitment_vacancies` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `vacancy_code` VARCHAR(30) NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `department_id` INT(11) DEFAULT NULL,
  `position_id` INT(11) DEFAULT NULL,
  `location` VARCHAR(150) DEFAULT NULL,
  `employment_type` ENUM('full_time', 'part_time', 'contract', 'temporary', 'internship') DEFAULT 'full_time',
  `seniority_level` ENUM('entry', 'mid', 'senior', 'executive', 'intern') DEFAULT 'entry',
  `salary_currency` VARCHAR(3) DEFAULT 'USD',
  `salary_min` DECIMAL(12,2) DEFAULT NULL,
  `salary_max` DECIMAL(12,2) DEFAULT NULL,
  `salary_visible` TINYINT(1) DEFAULT 0,
  `description` MEDIUMTEXT DEFAULT NULL,
  `responsibilities` MEDIUMTEXT DEFAULT NULL,
  `requirements` MEDIUMTEXT DEFAULT NULL,
  `benefits` MEDIUMTEXT DEFAULT NULL,
  `status` ENUM('draft', 'published', 'closed', 'archived') DEFAULT 'draft',
  `opening_date` DATE DEFAULT NULL,
  `closing_date` DATE DEFAULT NULL,
  `published_at` DATETIME DEFAULT NULL,
  `recruiter_user_id` INT(11) DEFAULT NULL,
  `hiring_manager_id` INT(11) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vacancy_code` (`vacancy_code`),
  KEY `idx_vacancy_status` (`status`),
  KEY `idx_vacancy_department` (`department_id`),
  KEY `idx_vacancy_position` (`position_id`),
  KEY `idx_vacancy_recruiter` (`recruiter_user_id`),
  KEY `idx_vacancy_hiring_manager` (`hiring_manager_id`),
  KEY `idx_vacancy_dates` (`opening_date`, `closing_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 3. APPLICATION PIPELINE
-- ============================================

CREATE TABLE IF NOT EXISTS `recruitment_statuses` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `status_key` VARCHAR(50) NOT NULL,
  `status_label` VARCHAR(120) NOT NULL,
  `status_group` ENUM('pipeline', 'positive', 'negative', 'closed') DEFAULT 'pipeline',
  `sort_order` INT(11) DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_terminal` TINYINT(1) DEFAULT 0,
  `color_hex` VARCHAR(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `status_key` (`status_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_applications` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_code` VARCHAR(30) NOT NULL,
  `vacancy_id` INT(11) NOT NULL,
  `candidate_id` INT(11) NOT NULL,
  `current_status` VARCHAR(50) NOT NULL DEFAULT 'new',
  `source` VARCHAR(120) DEFAULT 'career_portal',
  `applicant_message` TEXT DEFAULT NULL,
  `desired_salary` DECIMAL(12,2) DEFAULT NULL,
  `availability_date` DATE DEFAULT NULL,
  `years_experience` DECIMAL(5,2) DEFAULT NULL,
  `resume_path` VARCHAR(255) DEFAULT NULL,
  `cover_letter_path` VARCHAR(255) DEFAULT NULL,
  `supporting_documents` JSON DEFAULT NULL,
  `rating` DECIMAL(4,2) DEFAULT NULL,
  `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
  `assigned_to_user_id` INT(11) DEFAULT NULL,
  `hiring_manager_id` INT(11) DEFAULT NULL,
  `hired_worker_id` INT(11) DEFAULT NULL,
  `hired_at` DATETIME DEFAULT NULL,
  `last_status_change` DATETIME DEFAULT NULL,
  `is_withdrawn` TINYINT(1) DEFAULT 0,
  `is_archived` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `application_code` (`application_code`),
  KEY `idx_application_status` (`current_status`),
  KEY `idx_application_vacancy` (`vacancy_id`),
  KEY `idx_application_candidate` (`candidate_id`),
  KEY `idx_application_assigned` (`assigned_to_user_id`),
  KEY `idx_application_last_status_change` (`last_status_change`),
  KEY `idx_application_hired_worker` (`hired_worker_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_application_status_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by_user_id` INT(11) DEFAULT NULL,
  `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `comment` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_history_application` (`application_id`),
  KEY `idx_history_status` (`to_status`),
  KEY `idx_history_changed_by` (`changed_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_application_documents` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `document_type` ENUM('cv', 'cover_letter', 'portfolio', 'certificate', 'offer', 'other') DEFAULT 'other',
  `file_name` VARCHAR(255) NOT NULL,
  `storage_path` VARCHAR(255) NOT NULL,
  `mime_type` VARCHAR(120) DEFAULT NULL,
  `file_size_bytes` BIGINT DEFAULT NULL,
  `uploaded_by_user_id` INT(11) DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `is_primary` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_document_application` (`application_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_application_notes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `note_type` ENUM('general', 'interview', 'evaluation', 'offer', 'decision') DEFAULT 'general',
  `note_text` TEXT NOT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_note_application` (`application_id`),
  KEY `idx_note_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_interviews` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `interview_round` INT(11) DEFAULT 1,
  `interview_type` ENUM('phone', 'virtual', 'in_person', 'assessment', 'panel', 'other') DEFAULT 'virtual',
  `scheduled_at` DATETIME DEFAULT NULL,
  `end_at` DATETIME DEFAULT NULL,
  `location` VARCHAR(255) DEFAULT NULL,
  `video_conference_link` VARCHAR(255) DEFAULT NULL,
  `interviewer_user_id` INT(11) DEFAULT NULL,
  `status` ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
  `feedback` TEXT DEFAULT NULL,
  `rating` DECIMAL(4,2) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_interview_application` (`application_id`),
  KEY `idx_interview_status` (`status`),
  KEY `idx_interview_reviewer` (`interviewer_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `recruitment_offers` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `application_id` INT(11) NOT NULL,
  `offer_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `compensation_summary` TEXT DEFAULT NULL,
  `benefits_summary` TEXT DEFAULT NULL,
  `offer_status` ENUM('draft', 'pending_approval', 'sent', 'accepted', 'declined', 'withdrawn') DEFAULT 'draft',
  `approval_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approver_user_id` INT(11) DEFAULT NULL,
  `offer_document_path` VARCHAR(255) DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_offer_application` (`application_id`),
  KEY `idx_offer_status` (`offer_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- 4. PIPELINE DEFAULT DATA
-- ============================================

INSERT IGNORE INTO `recruitment_statuses` (`status_key`, `status_label`, `status_group`, `sort_order`, `is_active`, `is_terminal`, `color_hex`) VALUES
('new', 'New Application', 'pipeline', 10, 1, 0, '#2563eb'),
('screening', 'Screening', 'pipeline', 20, 1, 0, '#0891b2'),
('shortlisted', 'Shortlisted', 'pipeline', 30, 1, 0, '#10b981'),
('interview_scheduled', 'Interview Scheduled', 'pipeline', 40, 1, 0, '#6366f1'),
('interview_completed', 'Interview Completed', 'pipeline', 50, 1, 0, '#a855f7'),
('offer_extended', 'Offer Extended', 'pipeline', 60, 1, 0, '#f59e0b'),
('offer_accepted', 'Offer Accepted', 'positive', 70, 1, 0, '#d97706'),
('hired', 'Hired', 'positive', 80, 1, 0, '#16a34a'),
('onboarding', 'Onboarding', 'positive', 90, 1, 0, '#22c55e'),
('employed', 'Employed', 'positive', 100, 1, 1, '#0f766e'),
('offer_declined', 'Offer Declined', 'negative', 110, 1, 1, '#dc2626'),
('withdrawn', 'Withdrawn', 'negative', 120, 1, 1, '#ef4444'),
('rejected_pre_screen', 'Rejected - Pre Screen', 'negative', 130, 1, 1, '#b91c1c'),
('rejected_post_interview', 'Rejected - Post Interview', 'negative', 140, 1, 1, '#991b1b'),
('blacklisted', 'Blacklisted', 'negative', 150, 1, 1, '#111827');

-- ============================================
-- 5. FEATURE TOGGLES & DASHBOARD REGISTRATION
-- ============================================

INSERT IGNORE INTO `feature_toggles` (`feature_key`, `feature_name`, `description`, `is_enabled`, `is_core`, `category`, `icon`, `menu_position`) VALUES
('recruitment', 'Recruitment', 'Recruitment module - vacancy and applicant tracking integrated with HR', 1, 1, 'core', '🧑‍💼', 11);

SET FOREIGN_KEY_CHECKS = 1;

SELECT 'Recruitment Module Migration Completed Successfully!' AS Status;

