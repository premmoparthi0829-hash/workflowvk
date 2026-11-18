-- VK Logistics Hub - Electronic Employee Joining Form Database Schema
USE `taskmanagementsystem`;

-- 1. Main Joining Applications Table
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

-- 2. Employee Personal Details Table
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

-- 3. Employee Addresses Table (Present & Permanent)
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

-- 4. Identity Documents Table
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

-- 5. Emergency Contacts Table
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

-- 6. References Table (Ref 1 & Ref 2)
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

-- 7. Previous Employment Table
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

-- 8. Employee Declarations Table
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

-- 9. Employee Signatures Table
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

-- 10. Uploaded Documents Table
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

-- 11. HR Approvals Table
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

-- 12. Application Status History Table
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
