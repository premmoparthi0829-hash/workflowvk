-- ============================================================================
-- VK LOGISTICS HUB - UNIFIED DATABASE SCHEMA
-- Database: taskmanagementsystem
-- ============================================================================

CREATE DATABASE IF NOT EXISTS `taskmanagementsystem` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `taskmanagementsystem`;

-- ============================================================================
-- PART 1: CORE WORKFLOW & OPERATIONS TABLES
-- ============================================================================

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('management', 'hod', 'employee') NOT NULL,
  `department_id` INT NULL,
  `position` VARCHAR(100) NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`department_id`),
  INDEX (`role`),
  INDEX (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tasks Table
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  `priority` ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
  `status` ENUM('pending', 'not_started', 'in_progress', 'dependency_block', 'waiting_review', 'completed', 'rejected') DEFAULT 'pending',
  `deadline` DATE NOT NULL,
  `submission_details` TEXT NULL,
  `created_by` INT NOT NULL,
  `completed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`created_by`),
  INDEX (`status`),
  INDEX (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Task Assignments Table
CREATE TABLE IF NOT EXISTS `task_assignments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `assigned_to` INT NOT NULL,
  `assigned_by` INT NOT NULL,
  `assignment_type` VARCHAR(50) DEFAULT 'direct',
  `current_assignee` TINYINT(1) DEFAULT 1,
  `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `accepted_at` DATETIME NULL,
  `rejected_at` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  INDEX (`task_id`),
  INDEX (`assigned_to`),
  INDEX (`assigned_by`),
  INDEX (`current_assignee`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Task Logs Table
CREATE TABLE IF NOT EXISTS `task_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `old_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`task_id`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Activity Logs Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Attendance Punches Table
CREATE TABLE IF NOT EXISTS `attendance_punches` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `punch_type` VARCHAR(50) NOT NULL,
  `punch_time` DATETIME NOT NULL,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`punch_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Attendance Summary Table
CREATE TABLE IF NOT EXISTS `attendance_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `first_clock_in` DATETIME NULL,
  `last_clock_out` DATETIME NULL,
  `total_work_hours` DECIMAL(5,2) DEFAULT 0.00,
  `total_break_hours` DECIMAL(5,2) DEFAULT 0.00,
  `total_permission_hours` DECIMAL(5,2) DEFAULT 0.00,
  `status` VARCHAR(50) DEFAULT 'present',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_date` (`user_id`, `date`),
  INDEX (`date`),
  INDEX (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Employee Leave Balance Table
CREATE TABLE IF NOT EXISTS `employee_leave_balance` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `casual_leave` DECIMAL(6,1) DEFAULT 12.0,
  `sick_leave` DECIMAL(6,1) DEFAULT 12.0,
  `earned_leave` DECIMAL(6,1) DEFAULT 15.0,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Employee Leaves Table
CREATE TABLE IF NOT EXISTS `employee_leaves` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `leave_type` VARCHAR(50) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `total_days` DECIMAL(6,1) NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
  `approved_by` INT NULL,
  `approved_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`user_id`),
  INDEX (`status`),
  INDEX (`start_date`),
  INDEX (`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Holidays Table
CREATE TABLE IF NOT EXISTS `holidays` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `date` DATE NOT NULL,
  `type` VARCHAR(50) DEFAULT 'company',
  `description` TEXT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Performance Summary Table
CREATE TABLE IF NOT EXISTS `performance_summary` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `month_year` VARCHAR(7) NOT NULL,
  `total_tasks_assigned` INT DEFAULT 0,
  `tasks_accepted` INT DEFAULT 0,
  `tasks_rejected` INT DEFAULT 0,
  `tasks_completed` INT DEFAULT 0,
  `avg_completion_time_hours` DECIMAL(10,2) DEFAULT 0.00,
  `completion_rate` DECIMAL(5,2) DEFAULT 0.00,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `user_month` (`user_id`, `month_year`),
  INDEX (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PART 2: EMPLOYEE JOINING & ONBOARDING TABLES
-- ============================================================================

-- 13. Main Joining Applications Table
CREATE TABLE IF NOT EXISTS `employee_joining_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_no` VARCHAR(30) NOT NULL UNIQUE,
  `employee_type` ENUM('new_joiner', 'existing_employee') DEFAULT 'new_joiner',
  `token` VARCHAR(64) NOT NULL UNIQUE,
  `token_expires_at` DATETIME NULL,
  `status` ENUM('draft', 'sent_to_employee', 'in_progress', 'submitted', 'pending_hr_verification', 'correction_required', 'resubmitted', 'approved', 'rejected') DEFAULT 'sent_to_employee',
  `created_by` INT NULL,
  `submitted_at` DATETIME NULL,
  `approved_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`application_no`),
  INDEX (`status`),
  INDEX (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Employee Personal Details Table
CREATE TABLE IF NOT EXISTS `employee_personal_details` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `employee_id` VARCHAR(50) NULL,
  `full_name` VARCHAR(150) NULL,
  `father_name` VARCHAR(150) NULL,
  `dob` DATE NULL,
  `gender` ENUM('male', 'female', 'other') NULL DEFAULT 'male',
  `mobile` VARCHAR(20) NULL,
  `alternate_mobile` VARCHAR(20) NULL,
  `email` VARCHAR(100) NULL,
  `designation` VARCHAR(100) NULL,
  `department_id` INT NULL,
  `joining_date` DATE NULL,
  `blood_group` VARCHAR(10) NULL,
  `previous_work_experience` TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (`application_id`),
  INDEX (`mobile`),
  INDEX (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Employee Addresses Table (Present & Permanent)
CREATE TABLE IF NOT EXISTS `employee_addresses` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `address_type` ENUM('present', 'permanent') NOT NULL,
  `house_flat_no` VARCHAR(100) NULL,
  `street_area` VARCHAR(150) NULL,
  `village_town` VARCHAR(100) NULL,
  `mandal` VARCHAR(100) NULL,
  `district` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `pincode` VARCHAR(10) NULL,
  `is_same_as_present` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`),
  INDEX (`address_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Identity Documents Table
CREATE TABLE IF NOT EXISTS `employee_identity_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `aadhaar_masked` VARCHAR(20) NULL,
  `aadhaar_last4` VARCHAR(4) NULL,
  `other_id_type` VARCHAR(50) NULL,
  `other_id_number` VARCHAR(50) NULL,
  `documents_submitted` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Emergency Contacts Table
CREATE TABLE IF NOT EXISTS `employee_emergency_contacts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NULL,
  `relationship` VARCHAR(50) NULL,
  `mobile` VARCHAR(20) NULL,
  `alternate_mobile` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. References Table (Ref 1 & Ref 2)
CREATE TABLE IF NOT EXISTS `employee_references` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `reference_number` TINYINT NOT NULL,
  `full_name` VARCHAR(150) NULL,
  `relationship_how_known` VARCHAR(100) NULL,
  `mobile` VARCHAR(20) NULL,
  `address` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`),
  INDEX (`reference_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Previous Employment Table
CREATE TABLE IF NOT EXISTS `employee_previous_employment` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `employer_name` VARCHAR(150) NULL,
  `designation` VARCHAR(100) NULL,
  `employer_phone` VARCHAR(20) NULL,
  `reason_for_leaving` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Employee Declarations Table
CREATE TABLE IF NOT EXISTS `employee_declarations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `terms_acknowledged` TINYINT(1) DEFAULT 1,
  `data_consent_acknowledged` TINYINT(1) DEFAULT 1,
  `pickup_policy_acknowledged` TINYINT(1) DEFAULT 0,
  `declaration_date` DATE NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. Employee Signatures Table
CREATE TABLE IF NOT EXISTS `employee_signatures` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `signature_image_path` VARCHAR(255) NULL,
  `thumb_impression_path` VARCHAR(255) NULL,
  `signed_ip` VARCHAR(45) NULL,
  `signed_at` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. Uploaded Documents Table
CREATE TABLE IF NOT EXISTS `employee_uploaded_documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `original_file_name` VARCHAR(255) NOT NULL,
  `stored_file_path` VARCHAR(255) NOT NULL,
  `file_size_bytes` INT NOT NULL,
  `mime_type` VARCHAR(50) NOT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`),
  INDEX (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. HR Approvals Table
CREATE TABLE IF NOT EXISTS `employee_joining_approvals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL UNIQUE,
  `verified_by_name` VARCHAR(150) NOT NULL,
  `verified_by_user_id` INT NULL,
  `verified_at` DATETIME NULL,
  `employee_id_assigned` VARCHAR(50) NOT NULL,
  `joining_date_confirmed` DATE NOT NULL,
  `approved_by_user_id` INT NOT NULL,
  `hr_signature_path` VARCHAR(255) NULL,
  `internal_remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Application Status History Table
CREATE TABLE IF NOT EXISTS `employee_joining_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `from_status` VARCHAR(50) NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `changed_by_user_id` INT NULL,
  `changed_by_role` VARCHAR(50) NULL,
  `remarks` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ESSENTIAL SYSTEM INITIALIZATION (NO SEED / DUMMY DATA)
-- ============================================================================

-- Core Departments
INSERT INTO `departments` (`id`, `name`, `description`, `created_by`) VALUES
(1, 'Logistics & Operations', 'Handles fleet, distribution, and daily dispatch', 1),
(2, 'Customer Support', 'Handles inquiries, feedback, and issue resolution', 1),
(3, 'Warehouse & Inventory', 'Manages storage, stock verification, and packing', 1),
(4, 'Finance & Accounts', 'Handles billing, expense claims, and auditing', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default System Administrator Account
-- admin: admin123 (hash: password_hash('admin123', PASSWORD_DEFAULT))
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `department_id`, `position`, `is_active`) VALUES
(1, 'admin', 'admin@vklogisticshub.com', '$2y$10$Y6M9H72mThyvj4fnNRD8h.PEPsMZNfRjxMB5xASir2c9scUB.HBU6', 'System Administrator', 'management', 1, 'Operations Director', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);
