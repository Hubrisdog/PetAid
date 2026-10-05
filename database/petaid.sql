-- ============================================================
-- PetAid: Community Animal Assistance Mini System
-- Simple Integrative Programming College Project
-- Database Dump (MySQL)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `petaid`;
USE `petaid`;

-- Drop existing tables in reverse dependency order
DROP TABLE IF EXISTS `case_updates`;
DROP TABLE IF EXISTS `assistance_requests`;
DROP TABLE IF EXISTS `animal_reports`;
DROP TABLE IF EXISTS `users`;

-- ------------------------------------------------------------
-- Table 1: users
-- ------------------------------------------------------------
CREATE TABLE `users` (
  `user_id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `contact_number` VARCHAR(30) DEFAULT NULL,
  `role` ENUM('reporter','volunteer','admin') DEFAULT 'reporter',
  `status` ENUM('active','inactive') DEFAULT 'active'
);

-- ------------------------------------------------------------
-- Table 2: animal_reports
-- ------------------------------------------------------------
CREATE TABLE `animal_reports` (
  `report_id` INT PRIMARY KEY AUTO_INCREMENT,
  `reported_by` INT NOT NULL,
  `animal_type` VARCHAR(50) NOT NULL,
  `description` TEXT NOT NULL,
  `location` VARCHAR(150) NOT NULL,
  `condition` ENUM('safe','needs_attention','injured','critical') NOT NULL,
  `status` ENUM('reported','verified','assistance_requested','volunteer_assigned','rescued','treated','closed') DEFAULT 'reported',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`reported_by`) REFERENCES `users`(`user_id`)
);

-- ------------------------------------------------------------
-- Table 3: assistance_requests
-- ------------------------------------------------------------
CREATE TABLE `assistance_requests` (
  `request_id` INT PRIMARY KEY AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `volunteer_id` INT NOT NULL,
  `request_type` VARCHAR(50) NOT NULL,
  `status` ENUM('pending','accepted','completed','cancelled') DEFAULT 'pending',
  `requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`report_id`) REFERENCES `animal_reports`(`report_id`),
  FOREIGN KEY (`volunteer_id`) REFERENCES `users`(`user_id`)
);

-- ------------------------------------------------------------
-- Table 4: case_updates
-- ------------------------------------------------------------
CREATE TABLE `case_updates` (
  `update_id` INT PRIMARY KEY AUTO_INCREMENT,
  `report_id` INT NOT NULL,
  `updated_by` INT NOT NULL,
  `update_text` TEXT NOT NULL,
  `new_status` VARCHAR(50) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`report_id`) REFERENCES `animal_reports`(`report_id`),
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`user_id`)
);

-- ============================================================
-- Sample Data
-- ============================================================

-- Users
INSERT INTO `users` (`user_id`, `name`, `email`, `contact_number`, `role`, `status`) VALUES
(1, 'Juan Dela Cruz', 'juan@example.com', '0918-234-5678', 'reporter', 'active'),
(2, 'Maria Santos', 'maria@example.com', '0917-123-4567', 'volunteer', 'active'),
(3, 'Alex Reyes', 'alex@example.com', '0919-345-6789', 'volunteer', 'active'),
(4, 'Dr. Jose Rizal', 'jose@example.com', '0921-567-8901', 'admin', 'active'),
(5, 'Ana Lim', 'ana@example.com', '0922-678-9012', 'reporter', 'inactive');

-- Animal Reports
INSERT INTO `animal_reports` (`report_id`, `reported_by`, `animal_type`, `description`, `location`, `condition`, `status`, `created_at`) VALUES
(1, 1, 'Dog', 'Brown dog with injured front leg limping near the security gate.', 'Near university gate', 'injured', 'rescued', '2026-10-06 08:30:00'),
(2, 1, 'Cat', 'Stray calico cat near cafeteria food stalls searching for food.', 'Cafeteria', 'needs_attention', 'volunteer_assigned', '2026-10-06 09:15:00'),
(3, 1, 'Dog', 'Friendly black dog needing food near parking lot.', 'Near parking area', 'safe', 'reported', '2026-10-06 10:00:00'),
(4, 1, 'Bird', 'Maya bird trapped in net near student center.', 'Student Center', 'safe', 'closed', '2026-10-05 14:00:00');

-- Assistance Requests
INSERT INTO `assistance_requests` (`request_id`, `report_id`, `volunteer_id`, `request_type`, `status`, `requested_at`) VALUES
(1, 1, 2, 'Rescue', 'completed', '2026-10-06 09:00:00'),
(2, 2, 3, 'Food & Shelter', 'accepted', '2026-10-06 10:30:00');

-- Case Updates
INSERT INTO `case_updates` (`update_id`, `report_id`, `updated_by`, `update_text`, `new_status`, `created_at`) VALUES
(1, 1, 1, 'Report created for injured brown dog.', 'reported', '2026-10-06 08:30:00'),
(2, 1, 4, 'Guard on duty verified the animal location.', 'verified', '2026-10-06 08:50:00'),
(3, 1, 2, 'Volunteer Maria assigned for rescue.', 'volunteer_assigned', '2026-10-06 09:00:00'),
(4, 1, 2, 'Animal successfully rescued and taken to vet.', 'rescued', '2026-10-06 10:15:00'),
(5, 2, 1, 'Report created for stray calico cat.', 'reported', '2026-10-06 09:15:00'),
(6, 2, 3, 'Volunteer Alex assigned to bring food and carrier.', 'volunteer_assigned', '2026-10-06 10:30:00'),
(7, 3, 1, 'Report created for lost dog in parking area.', 'reported', '2026-10-06 10:00:00'),
(8, 4, 1, 'Maya bird trapped in badminton netting.', 'reported', '2026-10-05 14:00:00'),
(9, 4, 4, 'Bird safely freed and released into trees. Case closed.', 'closed', '2026-10-05 14:45:00');
