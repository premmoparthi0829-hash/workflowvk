-- Database: taskmanagementsystem
CREATE DATABASE IF NOT EXISTS `taskmanagementsystem` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `taskmanagementsystem`;

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
  `casual_leave` INT DEFAULT 12,
  `sick_leave` INT DEFAULT 12,
  `earned_leave` INT DEFAULT 15,
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
  `total_days` INT NOT NULL,
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

-- Default Data Seeding

-- Departments
INSERT INTO `departments` (`id`, `name`, `description`, `created_by`) VALUES
(1, 'Logistics & Operations', 'Handles fleet, distribution, and daily dispatch', 1),
(2, 'Customer Support', 'Handles inquiries, feedback, and issue resolution', 1),
(3, 'Warehouse & Inventory', 'Manages storage, stock verification, and packing', 1),
(4, 'Finance & Accounts', 'Handles billing, expense claims, and auditing', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- Default Users:
-- admin: admin123  (hash: password_hash('admin123', PASSWORD_DEFAULT))
-- hod1: hod123    (hash: password_hash('hod123', PASSWORD_DEFAULT))
-- emp1: emp123    (hash: password_hash('emp123', PASSWORD_DEFAULT))
INSERT INTO `users` (`id`, `username`, `email`, `password`, `full_name`, `role`, `department_id`, `position`, `is_active`) VALUES
(1, 'admin', 'admin@vklogisticshub.com', '$2y$10$Y6M9H72mThyvj4fnNRD8h.PEPsMZNfRjxMB5xASir2c9scUB.HBU6', 'System Administrator', 'management', 1, 'Operations Director', 1),
(2, 'hod1', 'hod@vklogisticshub.com', '$2y$10$jtD5Ng.vyP41NTOjU9u6BOV1Vku1ByXZoOqiyBYn7kZf11rmDS/lC', 'Suresh Kumar (HOD)', 'hod', 1, 'Head of Logistics', 1),
(3, 'emp1', 'employee@vklogisticshub.com', '$2y$10$jkA7TLeldwGNwEG7TSfS2.c3JQp2vl8hv2tmJv8LMzLZ5VoPkmVEq', 'Ravi Sharma', 'employee', 1, 'Logistics Associate', 1)
ON DUPLICATE KEY UPDATE `username`=VALUES(`username`);

-- Employee Leave Balance for emp1
INSERT INTO `employee_leave_balance` (`user_id`, `casual_leave`, `sick_leave`, `earned_leave`) VALUES
(3, 12, 12, 15)
ON DUPLICATE KEY UPDATE `casual_leave`=VALUES(`casual_leave`);

-- Seed sample tasks
INSERT INTO `tasks` (`id`, `title`, `description`, `priority`, `status`, `deadline`, `created_by`, `created_at`) VALUES
(1, 'Verify London Freight Manifest', 'Double check container serial codes and weight certificates for incoming vessel shipment.', 'high', 'in_progress', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 1, NOW()),
(2, 'Dispatch Fleet Route Optimization', 'Plan revised route schedules for central delivery hub to reduce fuel consumption.', 'medium', 'not_started', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 2, NOW()),
(3, 'Warehouse Safety Audit Q4', 'Review fire suppression systems and high-stack pallet racking structural safety.', 'critical', 'pending', DATE_ADD(CURDATE(), INTERVAL 2 DAY), 1, NOW())
ON DUPLICATE KEY UPDATE `title`=VALUES(`title`);

-- Seed task assignments
INSERT INTO `task_assignments` (`task_id`, `assigned_to`, `assigned_by`, `assignment_type`, `current_assignee`, `assigned_at`, `accepted_at`) VALUES
(1, 3, 1, 'direct', 1, NOW(), NOW()),
(2, 3, 2, 'direct', 1, NOW(), NULL),
(3, 3, 1, 'direct', 1, NOW(), NULL)
ON DUPLICATE KEY UPDATE `task_id`=VALUES(`task_id`);

-- Seed Holidays for 2026
INSERT INTO `holidays` (`name`, `date`, `type`, `description`, `created_by`) VALUES
('New Year Day', '2026-01-01', 'national', 'New Year public holiday', 1),
('Republic Day', '2026-01-26', 'national', 'Republic Day celebration', 1),
('May Day', '2026-05-01', 'gazetted', 'International Labour Day', 1),
('Independence Day', '2026-08-15', 'national', 'Indian Independence Day', 1),
('Diwali', '2026-11-08', 'company', 'Festival of Lights', 1),
('Christmas', '2026-12-25', 'national', 'Christmas Holiday', 1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);
