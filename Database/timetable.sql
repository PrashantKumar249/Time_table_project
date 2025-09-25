-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for timetable_management
CREATE DATABASE IF NOT EXISTS `timetable_management` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `timetable_management`;

-- Dumping structure for table timetable_management.branches
CREATE TABLE IF NOT EXISTS `branches` (
  `branch_id` int NOT NULL AUTO_INCREMENT,
  `branch_name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`),
  UNIQUE KEY `branch_name` (`branch_name`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.branches: ~18 rows (approximately)
INSERT INTO `branches` (`branch_id`, `branch_name`, `created_at`) VALUES
	(1, 'Computer Science', '2025-09-25 14:31:59'),
	(2, 'Mechanical Engineering', '2025-09-25 14:31:59'),
	(3, 'Electrical Engineering', '2025-09-25 15:58:39'),
	(4, 'Civil Engineering', '2025-09-25 15:58:39'),
	(5, 'Electronics and Communication', '2025-09-25 15:58:39'),
	(6, 'Information Technology', '2025-09-25 15:58:39'),
	(7, 'Chemical Engineering', '2025-09-25 15:58:39'),
	(8, 'Biotechnology', '2025-09-25 15:58:39'),
	(9, 'Artificial Intelligence and Machine Learning', '2025-09-25 15:58:39'),
	(10, 'Data Science', '2025-09-25 15:58:39'),
	(11, 'Diploma in Computer Science', '2025-09-25 15:58:39'),
	(12, 'Diploma in Mechanical Engineering', '2025-09-25 15:58:39'),
	(13, 'Diploma in Electrical Engineering', '2025-09-25 15:58:39'),
	(14, 'Diploma in Civil Engineering', '2025-09-25 15:58:39'),
	(15, 'Diploma in Electronics and Communication', '2025-09-25 15:58:39'),
	(16, 'BCA', '2025-09-25 15:58:39'),
	(17, 'BBA', '2025-09-25 15:58:39'),
	(18, 'MBA', '2025-09-25 15:58:39');

-- Dumping structure for table timetable_management.faculty
CREATE TABLE IF NOT EXISTS `faculty` (
  `faculty_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty: ~2 rows (approximately)
INSERT INTO `faculty` (`faculty_id`, `user_id`, `branch_id`, `faculty_name`, `email`, `created_by`, `created_at`) VALUES
	(1, 3, 1, 'Prof. Jane Smith', 'faculty1@example.com', 1, '2025-09-25 14:31:59'),
	(2, 4, 1, 'Sushil', 'sushil@gmail.com', 1, '2025-09-25 15:43:59');

-- Dumping structure for table timetable_management.faculty_leave
CREATE TABLE IF NOT EXISTS `faculty_leave` (
  `leave_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `leave_date` date NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `substitute_faculty_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`leave_id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `substitute_faculty_id` (`substitute_faculty_id`),
  CONSTRAINT `faculty_leave_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_leave_ibfk_2` FOREIGN KEY (`substitute_faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_leave: ~0 rows (approximately)

-- Dumping structure for table timetable_management.faculty_subjects
CREATE TABLE IF NOT EXISTS `faculty_subjects` (
  `faculty_subject_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`faculty_subject_id`),
  UNIQUE KEY `faculty_id` (`faculty_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `faculty_subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_subjects: ~2 rows (approximately)
INSERT INTO `faculty_subjects` (`faculty_subject_id`, `faculty_id`, `subject_id`, `created_at`) VALUES
	(1, 1, 1, '2025-09-25 14:31:59'),
	(2, 1, 2, '2025-09-25 14:31:59');

-- Dumping structure for table timetable_management.hod
CREATE TABLE IF NOT EXISTS `hod` (
  `hod_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `hod_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`hod_id`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `hod_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `hod_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `hod_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.hod: ~2 rows (approximately)
INSERT INTO `hod` (`hod_id`, `user_id`, `branch_id`, `hod_name`, `email`, `created_by`, `created_at`) VALUES
	(1, 1, 1, 'Dr. John Doe', 'hod_cs1@example.com', 1, '2025-09-25 14:31:59'),
	(2, 2, 1, 'Dr. Alice Brown', 'hod_cs2@example.com', 1, '2025-09-25 14:31:59'),
	(3, 5, 1, 'Alok Mishra', 'hodcs@gmail.com', 5, '2025-09-25 15:47:28'),
	(4, 6, 6, 'Niranjan ', 'hodit@gmail.com', 6, '2025-09-25 16:00:26');

-- Dumping structure for table timetable_management.rooms
CREATE TABLE IF NOT EXISTS `rooms` (
  `room_id` int NOT NULL AUTO_INCREMENT,
  `room_name` varchar(50) NOT NULL,
  `capacity` int NOT NULL,
  `is_lab` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`room_id`),
  UNIQUE KEY `room_name` (`room_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.rooms: ~2 rows (approximately)
INSERT INTO `rooms` (`room_id`, `room_name`, `capacity`, `is_lab`, `created_at`) VALUES
	(1, 'Room 101', 60, 0, '2025-09-25 14:31:59'),
	(2, 'Lab CS1', 30, 1, '2025-09-25 14:31:59');

-- Dumping structure for table timetable_management.sections
CREATE TABLE IF NOT EXISTS `sections` (
  `section_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `year` int NOT NULL,
  `semester` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`section_id`),
  UNIQUE KEY `branch_id` (`branch_id`,`section_name`,`year`,`semester`),
  CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.sections: ~3 rows (approximately)
INSERT INTO `sections` (`section_id`, `branch_id`, `section_name`, `year`, `semester`, `created_at`) VALUES
	(1, 1, 'Section A', 2, 3, '2025-09-25 14:31:59'),
	(2, 1, 'Section B', 2, 3, '2025-09-25 14:31:59'),
	(3, 2, 'Section A', 1, 1, '2025-09-25 14:31:59');

-- Dumping structure for table timetable_management.subjects
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `weekly_hours` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.subjects: ~3 rows (approximately)
INSERT INTO `subjects` (`subject_id`, `branch_id`, `subject_code`, `subject_name`, `weekly_hours`, `created_at`) VALUES
	(1, 1, 'CS101', 'Introduction to Programming', 4, '2025-09-25 14:31:59'),
	(2, 1, 'CS102', 'Database Systems', 3, '2025-09-25 14:31:59'),
	(3, 2, 'ME101', 'Thermodynamics', 4, '2025-09-25 14:31:59');

-- Dumping structure for table timetable_management.timetable_slots
CREATE TABLE IF NOT EXISTS `timetable_slots` (
  `slot_id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `faculty_id` int NOT NULL,
  `room_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`slot_id`),
  UNIQUE KEY `section_id` (`section_id`,`day_of_week`,`start_time`),
  UNIQUE KEY `faculty_id` (`faculty_id`,`day_of_week`,`start_time`),
  UNIQUE KEY `room_id` (`room_id`,`day_of_week`,`start_time`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `timetable_slots_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_slots_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_slots_ibfk_3` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  CONSTRAINT `timetable_slots_ibfk_4` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`room_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.timetable_slots: ~0 rows (approximately)

-- Dumping structure for table timetable_management.users
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(50) NOT NULL,
  `role` enum('hod','faculty') NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.users: ~3 rows (approximately)
INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `email`, `created_at`) VALUES
	(1, 'hod_cs1', 'hod123', 'hod', 'hod_cs1@example.com', '2025-09-25 14:31:58'),
	(2, 'hod_cs2', 'hod456', 'hod', 'hod_cs2@example.com', '2025-09-25 14:31:58'),
	(3, 'faculty1', 'faculty123', 'faculty', 'faculty1@example.com', '2025-09-25 14:31:58'),
	(4, 'sushil_201', 'sushil', 'faculty', 'sushil@gmail.com', '2025-09-25 15:43:59'),
	(5, 'hod_cs_ambalika1', 'hod123', 'hod', 'hodcs@gmail.com', '2025-09-25 15:47:28'),
	(6, 'hod_it', 'hod123', 'hod', 'hodit@gmail.com', '2025-09-25 16:00:26');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
