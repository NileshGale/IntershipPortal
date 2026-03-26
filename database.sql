-- ============================================================
-- CareerFlow - Internship & Placement Tracking System
-- Complete Database Schema
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+05:30";

CREATE DATABASE IF NOT EXISTS `placement_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `placement_db`;

-- ── Users ────────────────────────────────────────────────────
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','student','company') NOT NULL DEFAULT 'student',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `otp` VARCHAR(10) DEFAULT NULL,
  `otp_expiry` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Students ─────────────────────────────────────────────────
DROP TABLE IF EXISTS `students`;
CREATE TABLE `students` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `roll_number` VARCHAR(50) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `dob` DATE DEFAULT NULL,
  `gender` ENUM('Male','Female','Other') DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `branch` VARCHAR(100) DEFAULT NULL,
  `year` VARCHAR(10) DEFAULT NULL,
  `cgpa` DECIMAL(4,2) DEFAULT NULL,
  `skills` TEXT DEFAULT NULL,
  `projects` TEXT DEFAULT NULL,
  `certifications` TEXT DEFAULT NULL,
  `linkedin` VARCHAR(255) DEFAULT NULL,
  `github` VARCHAR(255) DEFAULT NULL,
  `placement_status` ENUM('Not Placed','In Progress','Placed') DEFAULT 'Not Placed',
  `internship_status` ENUM('None','Active','Completed') DEFAULT 'None',
  `profile_verified` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_branch` (`branch`),
  INDEX `idx_placement` (`placement_status`),
  INDEX `idx_cgpa` (`cgpa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Companies ────────────────────────────────────────────────
DROP TABLE IF EXISTS `companies`;
CREATE TABLE `companies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `company_name` VARCHAR(200) NOT NULL,
  `industry` VARCHAR(100) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `hr_name` VARCHAR(100) DEFAULT NULL,
  `hr_email` VARCHAR(191) DEFAULT NULL,
  `hr_phone` VARCHAR(20) DEFAULT NULL,
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_approved` (`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Opportunities ────────────────────────────────────────────
DROP TABLE IF EXISTS `opportunities`;
CREATE TABLE `opportunities` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('Internship','Placement') NOT NULL DEFAULT 'Placement',
  `location` VARCHAR(200) DEFAULT NULL,
  `salary` VARCHAR(100) DEFAULT NULL,
  `stipend` VARCHAR(100) DEFAULT NULL,
  `duration` VARCHAR(100) DEFAULT NULL,
  `eligibility_cgpa` DECIMAL(4,2) DEFAULT NULL,
  `eligibility_branches` VARCHAR(500) DEFAULT NULL,
  `eligibility_year` VARCHAR(50) DEFAULT NULL,
  `skills_required` TEXT DEFAULT NULL,
  `positions` INT DEFAULT 1,
  `deadline` DATE DEFAULT NULL,
  `status` ENUM('Open','Closed','Draft') DEFAULT 'Open',
  `is_approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_approved` (`is_approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Applications ────────────────────────────────────────────
DROP TABLE IF EXISTS `applications`;
CREATE TABLE `applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `opportunity_id` INT NOT NULL,
  `status` ENUM('Applied','Shortlisted','Interview Scheduled','Selected','Rejected') DEFAULT 'Applied',
  `cover_letter` TEXT DEFAULT NULL,
  `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities`(`id`) ON DELETE CASCADE,
  UNIQUE KEY `unique_application` (`student_id`, `opportunity_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Interviews ──────────────────────────────────────────────
DROP TABLE IF EXISTS `interviews`;
CREATE TABLE `interviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `application_id` INT NOT NULL,
  `round_number` INT DEFAULT 1,
  `round_name` VARCHAR(100) DEFAULT NULL,
  `interview_date` DATE NOT NULL,
  `interview_time` TIME NOT NULL,
  `mode` ENUM('Online','Offline','Hybrid') DEFAULT 'Offline',
  `venue` VARCHAR(255) DEFAULT NULL,
  `meeting_link` VARCHAR(500) DEFAULT NULL,
  `status` ENUM('Scheduled','Completed','Cancelled','Rescheduled') DEFAULT 'Scheduled',
  `student_response` ENUM('Pending','Accepted','Declined') DEFAULT 'Pending',
  `attendance` ENUM('Present','Absent','Not Marked') DEFAULT 'Not Marked',
  `feedback` TEXT DEFAULT NULL,
  `result` ENUM('Passed','Failed','Pending') DEFAULT 'Pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON DELETE CASCADE,
  INDEX `idx_date` (`interview_date`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Internships ─────────────────────────────────────────────
DROP TABLE IF EXISTS `internships`;
CREATE TABLE `internships` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `title` VARCHAR(200) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `start_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `supervisor_name` VARCHAR(100) DEFAULT NULL,
  `supervisor_email` VARCHAR(191) DEFAULT NULL,
  `supervisor_phone` VARCHAR(20) DEFAULT NULL,
  `stipend` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('Active','Completed','Terminated') DEFAULT 'Active',
  `progress_updates` TEXT DEFAULT NULL,
  `completion_certificate` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Placements ──────────────────────────────────────────────
DROP TABLE IF EXISTS `placements`;
CREATE TABLE `placements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `opportunity_id` INT DEFAULT NULL,
  `job_title` VARCHAR(200) DEFAULT NULL,
  `salary` VARCHAR(100) DEFAULT NULL,
  `joining_date` DATE DEFAULT NULL,
  `offer_letter` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('Offered','Accepted','Joined','Declined') DEFAULT 'Offered',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`opportunity_id`) REFERENCES `opportunities`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Documents ───────────────────────────────────────────────
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `student_id` INT NOT NULL,
  `doc_type` ENUM('Resume','Marksheet','ID Proof','Certificate','Other') NOT NULL,
  `file_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`student_id`) REFERENCES `students`(`id`) ON DELETE CASCADE,
  INDEX `idx_type` (`doc_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifications ───────────────────────────────────────────
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `type` ENUM('Info','Success','Warning','Error') DEFAULT 'Info',
  `is_read` TINYINT(1) DEFAULT 0,
  `link` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_read` (`user_id`, `is_read`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Announcements ───────────────────────────────────────────
DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `content` TEXT NOT NULL,
  `target_role` ENUM('all','student','company') DEFAULT 'all',
  `priority` ENUM('Normal','Important','Urgent') DEFAULT 'Normal',
  `created_by` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_target` (`target_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Placement Drives ────────────────────────────────────────
DROP TABLE IF EXISTS `placement_drives`;
CREATE TABLE `placement_drives` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `company_id` INT DEFAULT NULL,
  `drive_date` DATE NOT NULL,
  `venue` VARCHAR(255) DEFAULT NULL,
  `eligibility_criteria` TEXT DEFAULT NULL,
  `status` ENUM('Upcoming','Ongoing','Completed','Cancelled') DEFAULT 'Upcoming',
  `rounds` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE SET NULL,
  INDEX `idx_status` (`status`),
  INDEX `idx_date` (`drive_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SAMPLE DATA
-- ============================================================

-- Admin user (password: 12345678)
INSERT INTO `users` (`email`, `password`, `role`, `is_active`) VALUES
('parinitapaigwar@gmail.com', '$2y$10$.JvO.i5/MRDBsoP0XdfB1uTMKI/rM3Y5/0VAKWu/A4oTCvvDmaJd.', 'admin', 1);

-- Sample student users (password: Student@123)
INSERT INTO `users` (`email`, `password`, `role`, `is_active`) VALUES
('rahul.sharma@student.edu', '$2y$10$YJ1nKsP7QzRX5YZ6QK3FxOqIJn5L6m8Vk9X2D1c4H7W0R3T6U8p0Q', 'student', 1),
('priya.patel@student.edu', '$2y$10$YJ1nKsP7QzRX5YZ6QK3FxOqIJn5L6m8Vk9X2D1c4H7W0R3T6U8p0Q', 'student', 1),
('amit.verma@student.edu', '$2y$10$YJ1nKsP7QzRX5YZ6QK3FxOqIJn5L6m8Vk9X2D1c4H7W0R3T6U8p0Q', 'student', 1),
('sneha.kumar@student.edu', '$2y$10$YJ1nKsP7QzRX5YZ6QK3FxOqIJn5L6m8Vk9X2D1c4H7W0R3T6U8p0Q', 'student', 1),
('vikram.singh@student.edu', '$2y$10$YJ1nKsP7QzRX5YZ6QK3FxOqIJn5L6m8Vk9X2D1c4H7W0R3T6U8p0Q', 'student', 1);

-- Sample company users (password: Company@123)
INSERT INTO `users` (`email`, `password`, `role`, `is_active`) VALUES
('hr@techcorp.com', '$2y$10$nJ3iK4lM5oP6qR7sT8uV9wX0yZ1aB2cD3eF4gH5iJ6kL7mN8oP9q', 'company', 1),
('recruit@infosoft.com', '$2y$10$nJ3iK4lM5oP6qR7sT8uV9wX0yZ1aB2cD3eF4gH5iJ6kL7mN8oP9q', 'company', 1),
('careers@datawave.com', '$2y$10$nJ3iK4lM5oP6qR7sT8uV9wX0yZ1aB2cD3eF4gH5iJ6kL7mN8oP9q', 'company', 1);

-- Students
INSERT INTO `students` (`user_id`, `first_name`, `last_name`, `roll_number`, `phone`, `dob`, `gender`, `branch`, `year`, `cgpa`, `skills`, `linkedin`, `github`, `placement_status`) VALUES
(2, 'Rahul', 'Sharma', 'CS2022001', '9876543210', '2002-05-15', 'Male', 'Computer Science', '4th', 8.50, 'Python, Java, React, MySQL, Machine Learning', 'https://linkedin.com/in/rahulsharma', 'https://github.com/rahulsharma', 'In Progress'),
(3, 'Priya', 'Patel', 'CS2022002', '9876543211', '2002-08-20', 'Female', 'Computer Science', '4th', 9.10, 'JavaScript, Node.js, MongoDB, AWS, Docker', 'https://linkedin.com/in/priyapatel', 'https://github.com/priyapatel', 'Placed'),
(4, 'Amit', 'Verma', 'IT2022001', '9876543212', '2002-03-10', 'Male', 'IT', '4th', 7.80, 'PHP, Laravel, MySQL, HTML, CSS', 'https://linkedin.com/in/amitverma', 'https://github.com/amitverma', 'Not Placed'),
(5, 'Sneha', 'Kumar', 'EC2022001', '9876543213', '2002-11-25', 'Female', 'Electronics', '3rd', 8.20, 'VHDL, Embedded C, Arduino, IoT', 'https://linkedin.com/in/snehakumar', 'https://github.com/snehakumar', 'Not Placed'),
(6, 'Vikram', 'Singh', 'ME2022001', '9876543214', '2001-07-30', 'Male', 'Mechanical', '4th', 7.50, 'AutoCAD, SolidWorks, MATLAB, 3D Printing', 'https://linkedin.com/in/vikramsingh', 'https://github.com/vikramsingh', 'In Progress');

-- Companies
INSERT INTO `companies` (`user_id`, `company_name`, `industry`, `website`, `description`, `hr_name`, `hr_email`, `hr_phone`, `is_approved`) VALUES
(7, 'TechCorp Solutions', 'Information Technology', 'https://techcorp.com', 'Leading IT solutions company specializing in cloud computing and AI.', 'Ananya Mehta', 'hr@techcorp.com', '9800000001', 1),
(8, 'InfoSoft Technologies', 'Software Development', 'https://infosoft.com', 'Software development firm focused on enterprise solutions.', 'Rajesh Gupta', 'recruit@infosoft.com', '9800000002', 1),
(9, 'DataWave Analytics', 'Data Science', 'https://datawave.com', 'Data analytics and business intelligence company.', 'Meera Reddy', 'careers@datawave.com', '9800000003', 0);

-- Opportunities
INSERT INTO `opportunities` (`company_id`, `title`, `description`, `type`, `location`, `salary`, `stipend`, `duration`, `eligibility_cgpa`, `eligibility_branches`, `eligibility_year`, `skills_required`, `positions`, `deadline`, `status`, `is_approved`) VALUES
(1, 'Software Developer', 'Full-stack developer role working on enterprise web applications.', 'Placement', 'Bangalore', '8 LPA', NULL, NULL, 7.00, 'Computer Science,IT', '4th', 'Java, Python, SQL, React', 5, '2026-05-01', 'Open', 1),
(1, 'Summer Internship - Frontend', '3-month frontend development internship.', 'Internship', 'Remote', NULL, '25000/month', '3 months', 6.50, 'Computer Science,IT,Electronics', '3rd,4th', 'HTML, CSS, JavaScript, React', 10, '2026-04-15', 'Open', 1),
(2, 'Data Engineer', 'Design and maintain data pipelines and warehouses.', 'Placement', 'Hyderabad', '10 LPA', NULL, NULL, 8.00, 'Computer Science,IT', '4th', 'Python, SQL, Spark, AWS', 3, '2026-05-15', 'Open', 1),
(2, 'ML Intern', '6-month machine learning internship.', 'Internship', 'Pune', NULL, '30000/month', '6 months', 7.50, 'Computer Science', '3rd,4th', 'Python, TensorFlow, Statistics', 5, '2026-04-30', 'Open', 1),
(3, 'Business Analyst', 'Analyze data to drive business decisions.', 'Placement', 'Mumbai', '7 LPA', NULL, NULL, 7.00, 'Computer Science,IT,Electronics,Mechanical', '4th', 'Excel, SQL, Tableau, Python', 4, '2026-06-01', 'Open', 0);

-- Sample Applications
INSERT INTO `applications` (`student_id`, `opportunity_id`, `status`, `applied_at`) VALUES
(1, 1, 'Shortlisted', '2026-03-01 10:00:00'),
(1, 2, 'Applied', '2026-03-05 14:00:00'),
(2, 1, 'Selected', '2026-03-01 11:00:00'),
(2, 3, 'Interview Scheduled', '2026-03-10 09:00:00'),
(3, 1, 'Applied', '2026-03-02 15:00:00'),
(4, 2, 'Applied', '2026-03-06 12:00:00'),
(5, 1, 'Rejected', '2026-03-03 16:00:00');

-- Sample Interviews
INSERT INTO `interviews` (`application_id`, `round_number`, `round_name`, `interview_date`, `interview_time`, `mode`, `venue`, `meeting_link`, `status`, `student_response`) VALUES
(1, 1, 'Technical Round', '2026-04-01', '10:00:00', 'Online', NULL, 'https://meet.google.com/abc-defg-hij', 'Scheduled', 'Accepted'),
(3, 1, 'Technical Round', '2026-03-20', '14:00:00', 'Offline', 'Room 101, Main Building', NULL, 'Completed', 'Accepted'),
(3, 2, 'HR Round', '2026-03-22', '11:00:00', 'Online', NULL, 'https://zoom.us/j/123456', 'Completed', 'Accepted'),
(4, 1, 'Aptitude Test', '2026-04-05', '09:00:00', 'Offline', 'Exam Hall A', NULL, 'Scheduled', 'Pending');

-- Sample Internship
INSERT INTO `internships` (`student_id`, `company_id`, `title`, `start_date`, `end_date`, `supervisor_name`, `supervisor_email`, `stipend`, `status`) VALUES
(4, 1, 'Frontend Development Intern', '2026-01-15', '2026-04-15', 'Suresh Kumar', 'suresh@techcorp.com', '25000/month', 'Active');

-- Sample Placement
INSERT INTO `placements` (`student_id`, `company_id`, `opportunity_id`, `job_title`, `salary`, `joining_date`, `status`) VALUES
(2, 1, 1, 'Software Developer', '8 LPA', '2026-07-01', 'Offered');

-- Sample Notifications
INSERT INTO `notifications` (`user_id`, `title`, `message`, `type`, `is_read`) VALUES
(2, 'Application Shortlisted', 'Your application for Software Developer at TechCorp Solutions has been shortlisted.', 'Success', 0),
(2, 'Interview Scheduled', 'Your technical interview is scheduled for April 1, 2026 at 10:00 AM.', 'Info', 0),
(3, 'Congratulations!', 'You have been selected for Software Developer at TechCorp Solutions.', 'Success', 0),
(1, 'Welcome!', 'Welcome to the Placement Tracking System. Complete your profile to apply.', 'Info', 1);

-- Sample Announcements
INSERT INTO `announcements` (`title`, `content`, `target_role`, `priority`, `created_by`) VALUES
('TechCorp Campus Drive', 'TechCorp Solutions is conducting a campus drive on April 10, 2026. All eligible students must register by April 5.', 'student', 'Important', 1),
('Profile Verification Deadline', 'All final year students must get their profiles verified by March 31, 2026. Contact the placement cell.', 'student', 'Urgent', 1),
('New Companies Onboarded', 'Three new companies have been added to our placement network. Check opportunities for details.', 'all', 'Normal', 1);

-- Sample Placement Drive
INSERT INTO `placement_drives` (`title`, `description`, `company_id`, `drive_date`, `venue`, `eligibility_criteria`, `status`, `rounds`) VALUES
('TechCorp Campus Recruitment 2026', 'Annual campus recruitment drive by TechCorp Solutions', 1, '2026-04-10', 'Main Auditorium', 'CGPA >= 7.0, CS/IT students, Final Year', 'Upcoming', 'Aptitude Test, Technical Interview, HR Round');

COMMIT;
