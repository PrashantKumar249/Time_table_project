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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
	(18, 'MBA', '2025-09-25 15:58:39'),
	(19, 'test', '2025-09-25 18:08:56');

-- Dumping structure for table timetable_management.faculty
CREATE TABLE IF NOT EXISTS `faculty` (
  `faculty_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `faculty_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `password` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`faculty_id`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `faculty_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty: ~23 rows (approximately)
INSERT INTO `faculty` (`faculty_id`, `user_id`, `branch_id`, `faculty_name`, `email`, `created_by`, `created_at`, `password`) VALUES
	(15, 15, 1, 'MR.UMAKANT PANDEY', 'mr.umakant.pandey@example.com', 1, '2025-10-06 09:34:13', '123456'),
	(16, 16, 1, 'DR.BIRENDRA SINGH', 'dr.birendra.singh@example.com', 1, '2025-10-06 09:34:13', 'DR.BIRENDRA SINGH'),
	(17, 17, 1, 'DR.SUDHAKAR DIXIT', 'dr.sudhakhar.dixit@example.com', 1, '2025-10-06 09:34:13', 'DR.SUDHAKAR DIXIT'),
	(18, 18, 1, 'DR.AVANEESH KUMAR SINGH', 'dr.avaneesh.kumar.singh@example.com', 1, '2025-10-06 09:34:13', 'DR.AVANEESH KUMAR SINGH'),
	(19, 19, 1, 'MISS.ANUBHUTI RAO', 'miss.anubhuti.rao@example.com', 1, '2025-10-06 09:34:13', 'MISS.ANUBHUTI RAO'),
	(20, 20, 1, 'DR.P.S DIXIT', 'dr.p.s.dixit@example.com', 1, '2025-10-06 09:34:13', 'DR.P.S DIXIT'),
	(21, 21, 1, 'DR.SWATI SHRIVASTAVA', 'dr.swati.shrivastava@example.com', 1, '2025-10-06 09:34:13', 'DR.SWATI SHRIVASTAVA'),
	(22, 22, 1, 'DR.KUNAL GUPTA', 'dr.kunal.gupta@example.com', 1, '2025-10-06 09:34:13', 'DR.KUNAL GUPTA'),
	(23, 23, 1, 'DR.KRISHNANAND MISHRA', 'dr.krishnanand.mishra@example.com', 1, '2025-10-06 09:34:13', 'DR.KRISHNANAND MISHRA'),
	(24, 24, 1, 'MR.AYODHYA PRASAD', 'mr.ayodhya.prasad@example.com', 1, '2025-10-06 09:34:13', 'MR.AYODHYA PRASAD'),
	(25, 25, 1, 'MR.ATEBAR HAIDER', 'mr.atebar.haider@example.com', 1, '2025-10-06 09:34:13', 'MR.ATEBAR HAIDER'),
	(26, 26, 1, 'MR.NIRANJAN SHRIVASTAV', 'mr.niranjan.shrivastav@example.com', 1, '2025-10-06 09:34:13', 'MR.NIRANJAN SHRIVASTAV'),
	(27, 27, 1, 'MR.VIKAL SAXENA', 'mr.vikal.saxena@example.com', 1, '2025-10-06 09:34:13', 'MR.VIKAL SAXENA'),
	(28, 28, 1, 'DR.RAJAN PRASAD', 'dr.rajan.prasad@example.com', 1, '2025-10-06 09:34:13', 'DR.RAJAN PRASAD'),
	(29, 29, 1, 'MR.VIPIN RAWAT', 'mr.vipin.rawat@example.com', 1, '2025-10-06 09:34:13', 'MR.VIPIN RAWAT'),
	(30, 30, 1, 'MR.ALOK MISHRA', 'mr.alok.mishra@example.com', 1, '2025-10-06 09:34:13', 'MR.ALOK MISHRA'),
	(31, 31, 1, 'MISS.KM DIVYA', 'miss.km.divya@example.com', 1, '2025-10-06 09:34:13', 'MISS.KM DIVYA'),
	(32, 32, 1, 'DR.DEVENDRA KUMAR', 'dr.devendra.kumar@example.com', 1, '2025-10-06 09:34:13', 'DR.DEVENDRA KUMAR'),
	(33, 33, 1, 'MR.PRADEEP DUBEY', 'mr.pradeep.dubey@example.com', 1, '2025-10-06 09:34:13', 'MR.PRADEEP DUBEY'),
	(34, 34, 1, 'MRS.GARIMA MISHRA', 'mrs.garima.mishra@example.com', 1, '2025-10-06 09:34:13', 'MRS.GARIMA MISHRA'),
	(35, 35, 1, 'MR.SUDHEER KUMAR', 'mr.sudheer.kumar@example.com', 1, '2025-10-06 09:34:13', 'MR.SUDHEER KUMAR'),
	(36, 36, 1, 'MR.AMRITANSHU SHEKHER', 'mr.amritanshu.shekher@example.com', 1, '2025-10-06 09:34:13', 'MR.AMRITANSHU SHEKHER');

-- Dumping structure for table timetable_management.faculty_attendance
CREATE TABLE IF NOT EXISTS `faculty_attendance` (
  `attendance_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('present','absent') NOT NULL,
  `reason` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`),
  UNIQUE KEY `unique_faculty_date` (`faculty_id`,`attendance_date`),
  KEY `faculty_id` (`faculty_id`),
  CONSTRAINT `faculty_attendance_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_attendance: ~0 rows (approximately)

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_leave: ~1 rows (approximately)
INSERT INTO `faculty_leave` (`leave_id`, `faculty_id`, `leave_date`, `reason`, `status`, `substitute_faculty_id`, `created_at`) VALUES
	(3, 16, '2025-10-15', 'Leave Type: Full | Reason: ek2OPE2PI', 'approved', 16, '2025-10-15 06:48:39');

-- Dumping structure for table timetable_management.faculty_load
CREATE TABLE IF NOT EXISTS `faculty_load` (
  `sno` int NOT NULL AUTO_INCREMENT,
  `faculty_name` varchar(255) NOT NULL,
  `desig` varchar(50) NOT NULL,
  `year` int DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `branch` varchar(50) DEFAULT NULL,
  `sub_code` varchar(20) DEFAULT NULL,
  `theory_lab` varchar(100) DEFAULT NULL,
  `l` int DEFAULT NULL,
  `p` int DEFAULT NULL,
  `theory_load` int DEFAULT NULL,
  `lab_load` int DEFAULT NULL,
  `total_load` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sno`),
  KEY `faculty_name` (`faculty_name`),
  KEY `sub_code` (`sub_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_load: ~0 rows (approximately)

-- Dumping structure for table timetable_management.faculty_load_details
CREATE TABLE IF NOT EXISTS `faculty_load_details` (
  `load_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `year` int DEFAULT NULL,
  `section` varchar(10) DEFAULT NULL,
  `branch` varchar(50) DEFAULT NULL,
  `theory_lab` varchar(50) DEFAULT NULL,
  `l_hours` int DEFAULT NULL,
  `p_hours` int DEFAULT NULL,
  `theory_load` int DEFAULT NULL,
  `lab_load` int DEFAULT NULL,
  `total_load` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`load_id`),
  KEY `faculty_id` (`faculty_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `faculty_load_details_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE,
  CONSTRAINT `faculty_load_details_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_load_details: ~31 rows (approximately)
INSERT INTO `faculty_load_details` (`load_id`, `faculty_id`, `subject_id`, `year`, `section`, `branch`, `theory_lab`, `l_hours`, `p_hours`, `theory_load`, `lab_load`, `total_load`, `created_at`) VALUES
	(32, 30, 185, 1, 'A', 'CSE', 'PPS', 7, 0, 7, 10, 17, '2025-10-06 09:44:50'),
	(33, 30, 186, 1, 'A', 'CSE', 'PPS LAB', 0, 4, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(34, 30, 187, 4, 'D', 'IT', 'Project', 0, 4, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(35, 30, 161, 4, 'AB', 'CSE', 'STARTUP AND ENTREPRENEURIAL ACTIVITY ASSESMENT', NULL, 2, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(36, 29, 190, 3, 'F', 'IT', 'WEB TECHNOLOGY', 5, 0, 9, 10, 19, '2025-10-06 09:44:50'),
	(37, 29, 31, 3, 'F', 'IT', 'COMPILER DESIGN', 4, 0, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(38, 29, 37, 3, 'F', 'IT', 'WEB TECH LAB Lab', 0, 2, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(39, 29, 159, 4, 'AB', 'CSE+IT', 'Mini Project or Internship', 0, 4, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(40, 29, 160, 4, 'AB', 'CSE', 'Project', 0, 4, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(41, 31, 29, 3, 'A', 'CSE', 'Design and Analysis of Algorithm', 6, 0, 12, 12, 24, '2025-10-06 09:44:50'),
	(42, 31, 40, 3, 'A', 'CSE', 'Design and Analysis of Algorithm Lab[TRAINING]', 0, 4, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(43, 31, 29, 3, 'B', 'CSE', '', 6, 0, 12, NULL, NULL, '2025-10-06 09:44:50'),
	(44, 33, 153, 2, 'A', 'CSE', 'NUMERICAL REASONING', 2, NULL, 8, 0, 8, '2025-10-06 09:44:50'),
	(45, 33, 153, 2, 'B', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(46, 33, 153, 2, 'C', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(47, 33, 153, 2, 'D', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(48, 33, 153, 2, 'E', 'AIML', 'NUMERICAL REASONING', 2, NULL, 6, NULL, 6, '2025-10-06 09:44:50'),
	(49, 33, 153, 3, 'E', 'AIML', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(50, 33, 153, 3, 'F', 'DS+IT', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(51, 34, 152, 3, 'A', 'CSE', 'PDP+ENG', 2, NULL, 6, 0, 6, '2025-10-06 09:44:50'),
	(52, 34, 152, 3, 'B', 'CSE', 'PDP+ENG', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(53, 34, 152, 3, 'C', 'CSE', 'PDP+ENG', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(54, 35, 153, 3, 'A', 'CSE', 'NUMERICAL REASONING', 2, NULL, 16, 0, 16, '2025-10-06 09:44:50'),
	(55, 35, 153, 2, 'F', 'DS+IT', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(56, 35, 153, 3, 'B', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(57, 35, 153, 3, 'C', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(58, 35, 153, 3, 'D', 'CSE', 'NUMERICAL REASONING', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(59, 35, 153, 4, 'AB', 'CSE', 'NUMERICAL REASONING', 3, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(60, 35, 153, 4, 'CD', 'CSE/AIML/IT', 'NUMERICAL REASONING', 3, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50'),
	(61, 36, 152, 3, 'ALL', 'CSE', 'PLACEMENT SKILL', 6, NULL, 8, 0, 8, '2025-10-06 09:44:50'),
	(62, 36, 152, 4, 'ALL', 'CSE', 'PLACEMENT SKILL', 2, NULL, NULL, NULL, NULL, '2025-10-06 09:44:50');

-- Dumping structure for table timetable_management.faculty_subjects
CREATE TABLE IF NOT EXISTS `faculty_subjects` (
  `faculty_subject_id` int NOT NULL AUTO_INCREMENT,
  `faculty_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`faculty_subject_id`),
  UNIQUE KEY `faculty_id` (`faculty_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `faculty_subjects_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.faculty_subjects: ~24 rows (approximately)
INSERT INTO `faculty_subjects` (`faculty_subject_id`, `faculty_id`, `subject_id`, `created_at`) VALUES
	(36, 15, 139, '2025-10-06 09:34:13'),
	(37, 16, 139, '2025-10-06 09:34:13'),
	(38, 17, 140, '2025-10-06 09:34:13'),
	(39, 18, 140, '2025-10-06 09:34:13'),
	(40, 19, 141, '2025-10-06 09:34:13'),
	(41, 20, 142, '2025-10-06 09:34:13'),
	(42, 21, 142, '2025-10-06 09:34:13'),
	(43, 22, 143, '2025-10-06 09:34:13'),
	(44, 23, 145, '2025-10-06 09:34:13'),
	(45, 24, 145, '2025-10-06 09:34:13'),
	(46, 25, 145, '2025-10-06 09:34:13'),
	(47, 26, 155, '2025-10-06 09:34:13'),
	(48, 19, 156, '2025-10-06 09:34:13'),
	(49, 24, 156, '2025-10-06 09:34:13'),
	(50, 27, 157, '2025-10-06 09:34:13'),
	(51, 26, 158, '2025-10-06 09:34:13'),
	(52, 28, 158, '2025-10-06 09:34:13'),
	(53, 29, 159, '2025-10-06 09:34:13'),
	(54, 26, 159, '2025-10-06 09:34:13'),
	(55, 30, 160, '2025-10-06 09:34:13'),
	(56, 29, 160, '2025-10-06 09:34:13'),
	(57, 31, 160, '2025-10-06 09:34:13'),
	(58, 32, 160, '2025-10-06 09:34:13'),
	(59, 30, 161, '2025-10-06 09:34:13');

-- Dumping structure for table timetable_management.hod
CREATE TABLE IF NOT EXISTS `hod` (
  `hod_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `hod_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `college_name` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `college_logo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`hod_id`),
  UNIQUE KEY `email` (`email`),
  KEY `user_id` (`user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `hod_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `hod_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE,
  CONSTRAINT `hod_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.hod: ~1 rows (approximately)
INSERT INTO `hod` (`hod_id`, `user_id`, `branch_id`, `hod_name`, `email`, `created_by`, `created_at`, `college_name`, `address`, `college_logo`) VALUES
	(1, 1, 1, 'Alok Mishra', 'hod_cs1@example.com', 1, '2025-09-25 14:31:59', 'Ambalika Institute Of Management & Technology', 'Lucknow, Uttar Pradesh', 'AIMT.jpg'),
	(8, 1, 1, 'Test HOD', 'test@example.com', 1, '2025-11-23 10:33:15', NULL, NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.sections: ~5 rows (approximately)
INSERT INTO `sections` (`section_id`, `branch_id`, `section_name`, `year`, `semester`, `created_at`) VALUES
	(1, 1, 'Section A', 2, 3, '2025-09-25 14:31:59'),
	(2, 1, 'Section B', 2, 3, '2025-09-25 14:31:59'),
	(3, 2, 'Section A', 1, 1, '2025-09-25 14:31:59'),
	(4, 1, 'B', 1, 1, '2025-11-18 11:33:20'),
	(5, 1, 'A', 1, 1, '2025-11-18 11:33:42');

-- Dumping structure for table timetable_management.section_subjects
CREATE TABLE IF NOT EXISTS `section_subjects` (
  `allocation_id` int NOT NULL AUTO_INCREMENT,
  `section_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`allocation_id`),
  UNIQUE KEY `unique_section_subject` (`section_id`,`subject_id`),
  KEY `subject_id` (`subject_id`),
  CONSTRAINT `section_subjects_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`section_id`) ON DELETE CASCADE,
  CONSTRAINT `section_subjects_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`subject_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.section_subjects: ~0 rows (approximately)

-- Dumping structure for table timetable_management.subjects
CREATE TABLE IF NOT EXISTS `subjects` (
  `subject_id` int NOT NULL AUTO_INCREMENT,
  `branch_id` int NOT NULL,
  `subject_code` varchar(20) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `weekly_hours` int NOT NULL,
  `year` int NOT NULL DEFAULT '1',
  `semester` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`subject_id`),
  UNIQUE KEY `subject_code` (`subject_code`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_year_sem` (`year`,`semester`),
  CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`branch_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=196 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.subjects: ~41 rows (approximately)
INSERT INTO `subjects` (`subject_id`, `branch_id`, `subject_code`, `subject_name`, `weekly_hours`, `year`, `semester`, `created_at`) VALUES
	(29, 1, 'BCS503', 'Design and Analysis of Algorithm', 6, 3, 5, '2025-10-06 09:13:48'),
	(30, 1, 'BCS-052', 'Data Analytics', 4, 3, 5, '2025-10-06 09:13:48'),
	(31, 1, 'BIT052', 'Compiler Design', 4, 3, 6, '2025-10-06 09:13:48'),
	(32, 1, 'BCS058', 'Data Warehouse & Data Mining', 4, 3, 6, '2025-10-06 09:13:48'),
	(33, 1, 'BCS055', 'Machine Learning Techniques', 4, 3, 5, '2025-10-06 09:13:48'),
	(35, 1, 'BNC501', 'Constitution of India, Law and Engineering', 2, 3, 5, '2025-10-06 09:13:48'),
	(36, 1, 'BCS551', 'Database Management System Lab', 4, 3, 5, '2025-10-06 09:13:48'),
	(37, 1, 'BCS552', 'Web Technology Lab', 4, 3, 5, '2025-10-06 09:13:48'),
	(39, 1, 'BCDS551', 'Data Analytics and Visualization Lab', 4, 3, 5, '2025-10-06 09:13:48'),
	(40, 1, 'BCS553', 'Design and Analysis of Algorithm Lab', 4, 3, 5, '2025-10-06 09:13:48'),
	(41, 1, 'BCS554', 'Mini Project or Internship Assessment*', 4, 4, 7, '2025-10-06 09:13:48'),
	(139, 1, 'BAS303', 'Maths IV', 6, 2, 3, '2025-10-06 09:29:51'),
	(140, 1, 'BOE312', 'LASER SYSTEM & APPLICATIONS', 5, 2, 4, '2025-10-06 09:29:51'),
	(141, 1, 'BVE301', 'Universal Human values', 3, 2, 3, '2025-10-06 09:29:51'),
	(142, 1, 'BAS301', 'Technical Communication', 3, 2, 4, '2025-10-06 09:29:51'),
	(143, 1, 'BCC301', 'Cyber Security', 3, 2, 3, '2025-10-06 09:29:51'),
	(144, 1, 'BCC302', 'PYTHON', 4, 2, 4, '2025-10-06 09:29:51'),
	(145, 1, 'BCS301', 'Data Structure', 5, 2, 3, '2025-10-06 09:29:51'),
	(146, 1, 'BCS302', 'Computer Organization and Architecture', 5, 2, 3, '2025-10-06 09:29:51'),
	(147, 1, 'BCS303', 'Discrete Structures & Theory of Logic', 5, 2, 3, '2025-10-06 09:29:51'),
	(148, 1, 'BCS351', 'Data Structures Using C Lab', 4, 2, 3, '2025-10-06 09:29:51'),
	(149, 1, 'BCS352', 'Computer Organization Lab', 4, 2, 3, '2025-10-06 09:29:51'),
	(150, 1, 'BCS353', 'WEB DESIGNING WORKSHOP', 4, 2, 3, '2025-10-06 09:29:51'),
	(151, 1, 'BCC351', 'Mini Project or Internship Assessment', 4, 4, 7, '2025-10-06 09:29:51'),
	(152, 1, 'PDP', 'PDP', 2, 2, 3, '2025-10-06 09:29:51'),
	(153, 1, 'NUMREAS', 'Numerical & Reasoning', 2, 2, 4, '2025-10-06 09:29:51'),
	(154, 1, 'JAVATRAIN', 'JAVA TRAINING', 3, 4, 7, '2025-10-06 09:29:51'),
	(155, 1, 'BCS701', 'ARTIFICIAL INTELLIGENCE/DEEP LEARNING', 3, 4, 7, '2025-10-06 09:29:51'),
	(156, 1, 'BCS071', 'CLOUD COMPUTING/PRINCIPLES OF GENERATIVE AI', 3, 4, 7, '2025-10-06 09:29:51'),
	(157, 1, 'BOE074', 'RER', 3, 4, 7, '2025-10-06 09:29:51'),
	(158, 1, 'BCS751', 'ARTIFICIAL INTELLIGENCE LAB/DEEP LEARNING LAB', 2, 4, 7, '2025-10-06 09:29:51'),
	(159, 1, 'BCS752', 'MINIPROJECT OR INTERNSHIP ASSESSMENT', 4, 4, 7, '2025-10-06 09:29:51'),
	(160, 1, 'BCS753', 'PROJECT-I', 4, 4, 8, '2025-10-06 09:29:51'),
	(161, 1, 'BCS754', 'STARTUP AND ENTREPRENEURIAL ACTIVITY ASSESMENT', 4, 4, 7, '2025-10-06 09:29:51'),
	(185, 1, 'BCS101', 'PPS', 7, 1, 1, '2025-10-06 09:43:54'),
	(186, 1, 'BCS151', 'PPS LAB', 4, 1, 1, '2025-10-06 09:43:54'),
	(187, 1, 'BIT753', 'Project', 4, 1, 1, '2025-10-06 09:43:54'),
	(190, 1, 'BCS502', 'WEB TECHNOLOGY', 5, 3, 5, '2025-10-06 09:44:50'),
	(192, 1, 'BCS300', 'Science', 5, 3, 1, '2025-10-15 06:43:26'),
	(194, 1, 'SUB11370', 'Engineering Chemistry', 6, 1, 1, '2025-11-18 11:31:43'),
	(195, 1, 'SUB11765', 'Engineering Chemistry', 6, 1, 1, '2025-11-18 11:32:19');

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
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table timetable_management.users: ~23 rows (approximately)
INSERT INTO `users` (`user_id`, `username`, `password`, `role`, `email`, `created_at`) VALUES
	(1, 'hod_cs1', 'hod123', 'hod', 'hod_cs1@example.com', '2025-09-25 14:31:58'),
	(15, 'mr_umakant_pandey', 'MR.UMAKANT PANDEY', 'faculty', 'mr.umakant.pandey@example.com', '2025-10-06 09:34:13'),
	(16, 'dr_birendra_singh', 'DR.BIRENDRA SINGH', 'faculty', 'dr.birendra.singh@example.com', '2025-10-06 09:34:13'),
	(17, 'dr_sudhakhar_dixit', 'DR.SUDHAKAR DIXIT', 'faculty', 'dr.sudhakhar.dixit@example.com', '2025-10-06 09:34:13'),
	(18, 'dr_avaneesh_kumar_singh', 'DR.AVANEESH KUMAR SINGH', 'faculty', 'dr.avaneesh.kumar.singh@example.com', '2025-10-06 09:34:13'),
	(19, 'miss_anubhuti_rao', 'MISS.ANUBHUTI RAO', 'faculty', 'miss.anubhuti.rao@example.com', '2025-10-06 09:34:13'),
	(20, 'dr_p_s_dixit', 'DR.P.S DIXIT', 'faculty', 'dr.p.s.dixit@example.com', '2025-10-06 09:34:13'),
	(21, 'dr_swati_shrivastava', 'DR.SWATI SHRIVASTAVA', 'faculty', 'dr.swati.shrivastava@example.com', '2025-10-06 09:34:13'),
	(22, 'dr_kunal_gupta', 'DR.KUNAL GUPTA', 'faculty', 'dr.kunal.gupta@example.com', '2025-10-06 09:34:13'),
	(23, 'dr_krishnanand_mishra', 'DR.KRISHNANAND MISHRA', 'faculty', 'dr.krishnanand.mishra@example.com', '2025-10-06 09:34:13'),
	(24, 'mr_ayodhya_prasad', 'MR.AYODHYA PRASAD', 'faculty', 'mr.ayodhya.prasad@example.com', '2025-10-06 09:34:13'),
	(25, 'mr_atebar_haider', 'MR.ATEBAR HAIDER', 'faculty', 'mr.atebar.haider@example.com', '2025-10-06 09:34:13'),
	(26, 'mr_niranjan_shrivastav', 'MR.NIRANJAN SHRIVASTAV', 'faculty', 'mr.niranjan.shrivastav@example.com', '2025-10-06 09:34:13'),
	(27, 'mr_vikal_saxena', 'MR.VIKAL SAXENA', 'faculty', 'mr.vikal.saxena@example.com', '2025-10-06 09:34:13'),
	(28, 'dr_rajan_prasad', 'DR.RAJAN PRASAD', 'faculty', 'dr.rajan.prasad@example.com', '2025-10-06 09:34:13'),
	(29, 'mr_vipin_rawat', 'MR.VIPIN RAWAT', 'faculty', 'mr.vipin.rawat@example.com', '2025-10-06 09:34:13'),
	(30, 'mr_alok_mishra', 'MR.ALOK MISHRA', 'faculty', 'mr.alok.mishra@example.com', '2025-10-06 09:34:13'),
	(31, 'miss_km_divya', 'MISS.KM DIVYA', 'faculty', 'miss.km.divya@example.com', '2025-10-06 09:34:13'),
	(32, 'dr_devendra_kumar', 'DR.DEVENDRA KUMAR', 'faculty', 'dr.devendra.kumar@example.com', '2025-10-06 09:34:13'),
	(33, 'mr_pradeep_dubey', 'MR.PRADEEP DUBEY', 'faculty', 'mr.pradeep.dubey@example.com', '2025-10-06 09:34:13'),
	(34, 'mrs_garima_mishra', 'MRS.GARIMA MISHRA', 'faculty', 'mrs.garima.mishra@example.com', '2025-10-06 09:34:13'),
	(35, 'mr_sudheer_kumar', 'MR.SUDHEER KUMAR', 'faculty', 'mr.sudheer.kumar@example.com', '2025-10-06 09:34:13'),
	(36, 'mr_amritanshu_shekher', 'MR.AMRITANSHU SHEKHER', 'faculty', 'mr.amritanshu.shekher@example.com', '2025-10-06 09:34:13');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
