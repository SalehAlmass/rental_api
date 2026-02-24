-- Rental System Backup
-- Type: full
-- Generated at: 2026-02-24 14:23:47

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';


-- ----------------------------
-- Table: `attendance_logs`
-- ----------------------------
DROP TABLE IF EXISTS `attendance_logs`;
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('in','out') NOT NULL,
  `ts` datetime NOT NULL,
  `method` varchar(20) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_ts` (`user_id`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance_logs` VALUES 
('1','0','in','2026-02-16 09:38:24','biometric',NULL,'2026-02-16 11:38:24'),
('2','1','in','2026-02-17 08:59:29','manual',NULL,'2026-02-17 10:59:29'),
('3','1','out','2026-02-17 09:04:14','manual',NULL,'2026-02-17 11:04:14'),
('4','3','in','2026-02-17 09:05:14','manual',NULL,'2026-02-17 11:05:14'),
('5','1','in','2026-02-17 09:28:42','manual',NULL,'2026-02-17 11:28:42');

-- ----------------------------
-- Table: `audit_logs`
-- ----------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `entity` varchar(64) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- ----------------------------
-- Table: `clients`
-- ----------------------------
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 0,
  `credit_limit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clients` VALUES 
('1','العميل التجريبي الأول','1234567890','0500000000','المدينة - الحي الأول','0','5000.00',NULL,'2026-01-11 10:30:53'),
('2','العميل التجريبي الثاني','9876543210','0555555555','المدينة - الحي الثاني','0','10000.00',NULL,'2026-01-11 10:30:53'),
('3','Client Updated','1112223334','0500000000','الرياض','0','9000.00',NULL,'2026-01-11 10:32:34'),
('4','صالح الماس','1112223334','0500000000','الرياض','0','9000.00',NULL,'2026-01-11 11:37:58'),
('5','علي محمد','1234567890','0555555555','الرياض','0','5000.00',NULL,'2026-01-11 13:00:46'),
('6','sss','ss','ss','sss','0','0.00',NULL,'2026-01-11 13:05:11'),
('7','ali','02510000020','784569321','ksa','0','0.00',NULL,'2026-01-12 10:12:55'),
('8','ali محمد','1234567890','0555555555','الرياض','0','0.00',NULL,'2026-01-12 13:50:33'),
('10','ahmed محمد','1234567890','0555555555','الرياض','0','0.00',NULL,'2026-01-13 09:44:15'),
('17','asd','1122000011','2200000111','sa','0','0.00',NULL,'2026-01-24 13:19:54');

-- ----------------------------
-- Table: `equipment`
-- ----------------------------
DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_no` varchar(100) DEFAULT NULL,
  `status` enum('available','rented','maintenance') NOT NULL DEFAULT 'available',
  `hourly_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `last_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment` VALUES 
('1','الشيول الأول','CAT 966','EQ-0001','available','250.00','10.00',NULL,'2026-01-11 10:30:53','1'),
('2','القلاب الثاني','VOLVO A40','EQ-0002','available','320.00','8.00',NULL,'2026-01-11 10:30:53','1'),
('3','حفار كهربائي متطور','HX-4000','SN123456','available','180.00','1.30','2026-01-12','2026-01-11 10:32:52','1'),
('4','ssr','','2200','available','1200.00','0.00',NULL,'2026-01-11 13:17:08','1'),
('5','حفار كاتربيلر2024 ','CAT 320','EQ-1001','available','200.00','8.00',NULL,'2026-01-12 10:28:27','0'),
('6','حفار كهربائيkk','HX-3000','SN123456','rented','150.00','1.50',NULL,'2026-01-12 13:51:26','1');

-- ----------------------------
-- Table: `equipment_maintenance`
-- ----------------------------
DROP TABLE IF EXISTS `equipment_maintenance`;
CREATE TABLE `equipment_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `details` text DEFAULT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_maint_equipment` (`equipment_id`),
  CONSTRAINT `fk_maint_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------
-- Table: `payments`
-- ----------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `rent_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('in','out') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(50) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_void` tinyint(1) NOT NULL DEFAULT 0,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` varchar(255) DEFAULT NULL,
  `idempotency_key` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payments_idem_key` (`idempotency_key`),
  KEY `fk_payments_client` (`client_id`),
  KEY `fk_payments_rent` (`rent_id`),
  KEY `fk_payments_user` (`user_id`),
  CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_rent` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` VALUES 
('1','1','1','1','in','450.00','cash','RCPT-0001','تعديل','2026-01-11 10:30:53','1','2026-01-12 11:15:17',NULL,NULL),
('2','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 10:42:21','0',NULL,NULL,NULL),
('3','1','5',NULL,'out','250.00','cash',NULL,NULL,'2026-01-11 13:51:48','1','2026-01-14 12:01:12',NULL,NULL),
('4','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 14:29:54','0',NULL,NULL,NULL),
('5','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 14:30:03','0',NULL,NULL,NULL),
('6','1','5',NULL,'in','200.00','cash',NULL,'دفعة مقدمة','2026-01-11 14:31:31','0',NULL,NULL,NULL),
('7','2','2',NULL,'in','2000.00','cash',NULL,NULL,'2026-01-12 10:17:25','0',NULL,NULL,NULL),
('8','5',NULL,NULL,'in','22.00','cash',NULL,NULL,'2026-01-24 13:20:33','0',NULL,NULL,NULL),
('9','2','21','1','in','5000.00','cash','2200','111','2026-02-02 10:41:05','0',NULL,NULL,NULL),
('10','4',NULL,'1','out','722.00','cash','2000',NULL,'2026-02-03 11:57:01','0',NULL,NULL,NULL),
('11','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-05 10:00:42','0',NULL,NULL,NULL),
('12','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-05 10:00:50','0',NULL,NULL,NULL),
('13','6','12','1','in','21984.00','cash',NULL,NULL,'2026-02-07 08:21:32','0',NULL,NULL,NULL),
('14','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-07 08:21:45','0',NULL,NULL,NULL),
('15','7','18','1','in','30000.00','cash',NULL,NULL,'2026-02-08 08:34:41','0',NULL,NULL,NULL),
('16','4','22','1','in','15006.00','cash',NULL,NULL,'2026-02-08 13:34:50','0',NULL,NULL,NULL);

-- ----------------------------
-- Table: `rents`
-- ----------------------------
DROP TABLE IF EXISTS `rents`;
CREATE TABLE `rents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `hours` decimal(10,2) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `total_amount` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','closed','cancelled') NOT NULL DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `remaining_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `paid_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rents_client` (`client_id`),
  KEY `fk_rents_equipment` (`equipment_id`),
  CONSTRAINT `fk_rents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rents_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rents` VALUES 
('1','1','1','2026-01-11 10:30:53','2026-01-11 18:00:00','7.49','250.00','1872.50','عقد تجريبي مفتوح','closed','2026-01-11 10:30:53','0.00','0.00','0',NULL),
('2','2','3','2026-01-11 10:00:00','2026-01-11 13:32:41','3.54','300.00','1062.00','عقد مدري','closed','2026-01-11 10:33:20','0.00','0.00','0',NULL),
('3','4','2','2026-01-11 13:32:55','2026-01-11 14:28:03','0.92','320.00','294.40',NULL,'closed','2026-01-11 13:32:59','0.00','0.00','0',NULL),
('4','3','1','2026-01-11 13:47:19','2026-01-11 14:50:20','1.05','250.00','262.50',NULL,'closed','2026-01-11 13:47:24','0.00','0.00','0',NULL),
('5','1','1','2026-01-11 13:50:28',NULL,NULL,'250.00',NULL,'تم تمديد الإيجار','open','2026-01-11 13:50:32','0.00','0.00','0',NULL),
('6','6','4','2026-01-11 13:53:24',NULL,NULL,'1200.00',NULL,NULL,'open','2026-01-11 13:53:28','0.00','0.00','0',NULL),
('7','2','3','2026-01-11 10:00:00','2026-01-12 09:10:17','23.17','300.00','6951.00','عقد سوي','closed','2026-01-11 14:25:31','0.00','0.00','0',NULL),
('8','2','3','2026-01-11 10:00:00',NULL,NULL,'300.00',NULL,'عقد سوي','open','2026-01-11 14:25:31','0.00','0.00','0',NULL),
('9','3','3','2026-01-11 14:37:42',NULL,NULL,'20.00',NULL,NULL,'open','2026-01-11 14:37:46','0.00','0.00','0',NULL),
('10','3','3','2026-01-11 14:37:42','2026-02-03 13:08:18','550.51','20.00','11010.20',NULL,'closed','2026-01-11 14:37:46','0.00','0.00','0',NULL),
('11','6','4','2026-01-11 14:37:54',NULL,NULL,'1200.00',NULL,NULL,'open','2026-01-11 14:37:58','0.00','0.00','0',NULL),
('12','6','4','2026-01-11 14:37:54','2026-01-12 08:56:51','18.32','1200.00','21984.00',NULL,'closed','2026-01-11 14:37:58','0.00','0.00','0',NULL),
('13','4','3','2026-01-11 14:38:08','2026-02-08 13:21:41','670.73','300.00','201219.00',NULL,'closed','2026-01-11 14:38:13','0.00','0.00','0',NULL),
('14','4','3','2026-01-11 14:38:08',NULL,NULL,'300.00',NULL,NULL,'open','2026-01-11 14:38:13','0.00','0.00','0',NULL),
('15','4','2','2026-01-11 14:48:56',NULL,NULL,'1250.00',NULL,'تعديل ملاحظات','cancelled','2026-01-11 14:49:01','0.00','0.00','0',NULL),
('16','4','2','2026-01-11 14:48:56','2026-01-13 08:48:15','41.99','1250.00','52487.50',NULL,'closed','2026-01-11 14:49:01','0.00','0.00','0',NULL),
('17','2','3','2026-01-11 10:00:00','2026-01-17 12:01:40','146.03','300.00','43809.00','عقد سوي','closed','2026-01-12 10:40:07','0.00','0.00','0',NULL),
('18','7','1','2026-01-12 10:52:01','2026-01-17 12:01:37','121.16','250.00','30290.00',NULL,'closed','2026-01-12 10:52:02','0.00','0.00','0',NULL),
('19','5','2','2026-01-13 10:43:28','2026-01-17 12:01:36','97.30','520.00','50596.00',NULL,'closed','2026-01-13 10:43:28','0.00','0.00','0',NULL),
('20','1','1','2026-02-01 09:49:24','2026-02-05 10:00:26','96.18','250.00','24045.00',NULL,'closed','2026-02-01 09:49:25','0.00','0.00','0',NULL),
('21','2','2','2026-02-01 09:50:18','2026-02-08 13:21:05','171.51','320.00','54883.20',NULL,'closed','2026-02-01 09:50:19','0.00','0.00','0',NULL),
('22','4','3','2026-02-05 10:19:18','2026-02-08 13:21:23','75.03','200.00','15006.00',NULL,'closed','2026-02-05 10:19:18','0.00','0.00','0',NULL);

-- ----------------------------
-- Table: `shift_closings`
-- ----------------------------
DROP TABLE IF EXISTS `shift_closings`;
CREATE TABLE `shift_closings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shift_date` date NOT NULL,
  `expected_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `actual_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `difference` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_user_date` (`user_id`,`shift_date`),
  CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shift_closings` VALUES 
('1','1','2026-01-29','20200.00','20000.00','-200.00','[cash_total=20000, transfer_total=200]','2026-01-29 08:30:45');

-- ----------------------------
-- Table: `users`
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','employee') NOT NULL DEFAULT 'employee',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `salary_type` enum('hourly','monthly') DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `monthly_salary` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES 
('1','admin','admin123','admin','2026-01-11 10:30:53','1',NULL,NULL,NULL),
('2','employ','emp123','employee','2026-01-11 10:30:53','1',NULL,NULL,NULL),
('3','aaa','1234567','employee','2026-01-13 14:00:51','1',NULL,NULL,NULL),
('4','صالح','123456','employee','2026-02-04 10:24:01','0',NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS=1;
