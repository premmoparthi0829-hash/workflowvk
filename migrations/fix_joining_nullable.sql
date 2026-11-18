-- Fix: Make draft fields nullable to allow progressive draft saving without SQL errors
USE `taskmanagementsystem`;

-- 1. Employee personal details
ALTER TABLE `employee_personal_details`
  MODIFY COLUMN `full_name` VARCHAR(150) NULL,
  MODIFY COLUMN `father_name` VARCHAR(150) NULL,
  MODIFY COLUMN `dob` DATE NULL,
  MODIFY COLUMN `gender` ENUM('male', 'female', 'other') NULL DEFAULT 'male',
  MODIFY COLUMN `mobile` VARCHAR(20) NULL,
  MODIFY COLUMN `email` VARCHAR(100) NULL,
  MODIFY COLUMN `designation` VARCHAR(100) NULL,
  MODIFY COLUMN `joining_date` DATE NULL;

-- 2. Employee addresses
ALTER TABLE `employee_addresses`
  MODIFY COLUMN `house_flat_no` VARCHAR(100) NULL,
  MODIFY COLUMN `street_area` VARCHAR(150) NULL,
  MODIFY COLUMN `village_town` VARCHAR(100) NULL,
  MODIFY COLUMN `mandal` VARCHAR(100) NULL,
  MODIFY COLUMN `district` VARCHAR(100) NULL,
  MODIFY COLUMN `state` VARCHAR(100) NULL,
  MODIFY COLUMN `pincode` VARCHAR(10) NULL;

-- 3. Identity documents
ALTER TABLE `employee_identity_documents`
  MODIFY COLUMN `aadhaar_masked` VARCHAR(20) NULL,
  MODIFY COLUMN `aadhaar_last4` VARCHAR(4) NULL;

-- 4. Emergency contacts
ALTER TABLE `employee_emergency_contacts`
  MODIFY COLUMN `full_name` VARCHAR(150) NULL,
  MODIFY COLUMN `relationship` VARCHAR(50) NULL,
  MODIFY COLUMN `mobile` VARCHAR(20) NULL,
  MODIFY COLUMN `address` TEXT NULL;

-- 5. References
ALTER TABLE `employee_references`
  MODIFY COLUMN `full_name` VARCHAR(150) NULL,
  MODIFY COLUMN `relationship_how_known` VARCHAR(100) NULL,
  MODIFY COLUMN `mobile` VARCHAR(20) NULL,
  MODIFY COLUMN `address` TEXT NULL;

-- 6. Signatures
ALTER TABLE `employee_signatures`
  MODIFY COLUMN `signature_image_path` VARCHAR(255) NULL,
  MODIFY COLUMN `signed_at` DATETIME NULL;
