-- Rental System Backup
-- Type: full
-- Generated at: 2026-04-28 12:53:38

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';


-- ----------------------------
-- Table: `app_settings`
-- ----------------------------
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `shift` enum('morning','evening') DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_ts` (`user_id`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance_logs` VALUES 
('1','0','in','2026-02-16 09:38:24','biometric',NULL,NULL,'2026-02-16 11:38:24'),
('2','1','in','2026-02-17 08:59:29','manual',NULL,NULL,'2026-02-17 10:59:29'),
('3','1','out','2026-02-17 09:04:14','manual',NULL,NULL,'2026-02-17 11:04:14'),
('4','3','in','2026-02-17 09:05:14','manual',NULL,NULL,'2026-02-17 11:05:14'),
('5','1','in','2026-02-17 09:28:42','manual',NULL,NULL,'2026-02-17 11:28:42'),
('6','1','out','2026-04-13 08:30:40','manual',NULL,NULL,'2026-04-13 09:30:40'),
('7','1','in','2026-04-13 08:31:10','manual',NULL,NULL,'2026-04-13 09:31:10'),
('8','1','out','2026-04-13 12:57:50','manual',NULL,NULL,'2026-04-13 13:57:50'),
('9','1','in','2026-04-18 10:59:15','manual',NULL,NULL,'2026-04-18 11:59:15'),
('10','3','out','2026-04-21 09:26:41','manual',NULL,NULL,'2026-04-21 10:26:41'),
('11','3','in','2026-04-21 09:26:45','manual',NULL,NULL,'2026-04-21 10:26:45'),
('12','1','out','2026-04-26 13:29:07','manual','morning',NULL,'2026-04-26 14:29:07'),
('13','1','in','2026-04-26 13:29:15','manual','morning',NULL,'2026-04-26 14:29:15'),
('14','1','out','2026-04-27 08:46:11','manual','morning',NULL,'2026-04-27 09:46:11'),
('15','1','in','2026-04-27 08:46:32','manual','morning',NULL,'2026-04-27 09:46:32');

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
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` VALUES 
('1',NULL,'payment_created','payment','17','{\"rent_id\":null,\"amount\":20000,\"type\":\"out\"}','2026-02-25 12:03:34'),
('2',NULL,'payment_created','payment','18','{\"rent_id\":25,\"amount\":12000,\"type\":\"in\"}','2026-04-16 09:32:36'),
('3',NULL,'rent_closed','rent','25','{\"total_amount\":17787.5,\"hours\":71.15,\"apply_special_pricing\":false,\"pricing_rule_code\":\"hourly\",\"closing_paid_amount\":5780,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"created\",\"closing_payment_id\":19,\"closed_at\":\"2026-04-16 08:33:02\",\"closed_by_user_id\":1}','2026-04-16 09:33:02'),
('4',NULL,'rent_closed','rent','26','{\"total_amount\":2000,\"hours\":50.28,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":2000,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"created\",\"closing_payment_id\":20,\"closed_at\":\"2026-04-18 10:51:03\",\"closed_by_user_id\":1}','2026-04-18 11:51:03'),
('5',NULL,'rent_closed','rent','24','{\"total_amount\":1200,\"hours\":121.91,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1200,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"created\",\"closing_payment_id\":21,\"closed_at\":\"2026-04-18 10:58:24\",\"closed_by_user_id\":1}','2026-04-18 11:58:24'),
('6',NULL,'rent_closed','rent','5','{\"total_amount\":250,\"hours\":2325.17,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":250,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"created\",\"closing_payment_id\":22,\"closed_at\":\"2026-04-18 11:00:25\",\"closed_by_user_id\":1}','2026-04-18 12:00:25'),
('7',NULL,'receipt_skipped_on_close','rent','23','{\"closing_paid_amount\":5000,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-21 08:33:38\"}','2026-04-21 09:33:38'),
('8',NULL,'rent_closed','rent','23','{\"total_amount\":5000,\"hours\":191.52,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":5000,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-21 08:33:38\",\"closed_by_user_id\":3}','2026-04-21 09:33:38'),
('9',NULL,'payment_created','payment','23','{\"rent_id\":23,\"amount\":5000,\"type\":\"in\"}','2026-04-21 09:34:10'),
('10',NULL,'receipt_skipped_on_close','rent','14','{\"closing_paid_amount\":300,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-21 09:55:47\"}','2026-04-21 10:55:47'),
('11',NULL,'rent_closed','rent','14','{\"total_amount\":300,\"hours\":2395.29,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":300,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-21 09:55:47\",\"closed_by_user_id\":3}','2026-04-21 10:55:47'),
('12',NULL,'payment_created','payment','24','{\"rent_id\":null,\"amount\":1500,\"type\":\"out\"}','2026-04-21 11:08:46'),
('13',NULL,'receipt_skipped_on_close','rent','11','{\"closing_paid_amount\":1200,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-21 10:10:31\"}','2026-04-21 11:10:31'),
('14',NULL,'rent_closed','rent','11','{\"total_amount\":1200,\"hours\":2395.54,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1200,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-21 10:10:31\",\"closed_by_user_id\":1}','2026-04-21 11:10:31'),
('15',NULL,'payment_created','payment','25','{\"rent_id\":11,\"amount\":1200,\"type\":\"in\"}','2026-04-21 11:10:32'),
('16',NULL,'rent_closed','rent','6','{\"total_amount\":127200,\"gross_total_amount\":127200,\"discount_amount\":0,\"discount_note\":null,\"hours\":2537.4,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":127200,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"created\",\"closing_payment_id\":26,\"closed_at\":\"2026-04-27 07:17:22\",\"closed_by_user_id\":1}','2026-04-27 08:17:22'),
('17',NULL,'payment_created','payment','27','{\"rent_id\":25,\"amount\":7.5,\"type\":\"in\"}','2026-04-27 08:32:39'),
('18',NULL,'receipt_skipped_on_close','rent','28','{\"closing_paid_amount\":350000,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-27 07:40:53\"}','2026-04-27 08:40:53'),
('19',NULL,'rent_closed','rent','28','{\"total_amount\":30000,\"gross_total_amount\":30000,\"discount_amount\":0,\"discount_note\":null,\"hours\":143.07,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":350000,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-27 07:40:53\",\"closed_by_user_id\":1}','2026-04-27 08:40:53'),
('20',NULL,'payment_created','payment','28','{\"rent_id\":28,\"amount\":30000,\"type\":\"in\"}','2026-04-27 09:39:35'),
('21',NULL,'receipt_skipped_on_close','rent','8','{\"closing_paid_amount\":31800,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-27 09:48:32\"}','2026-04-27 10:48:32'),
('22',NULL,'rent_closed','rent','8','{\"total_amount\":31800,\"gross_total_amount\":31800,\"discount_amount\":0,\"discount_note\":null,\"hours\":2543.81,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":31800,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-27 09:48:32\",\"closed_by_user_id\":1}','2026-04-27 10:48:32'),
('23',NULL,'payment_created','payment','29','{\"rent_id\":8,\"amount\":31800,\"type\":\"in\"}','2026-04-27 10:48:35');

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
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
('17','asd','1122000011','2200000111','sa','0','0.00',NULL,'2026-01-24 13:19:54'),
('19','عوض بازار','00126584132','777359688','غيل باوزير','0','0.00',NULL,'2026-04-13 10:00:57');

-- ----------------------------
-- Table: `collection_followups`
-- ----------------------------
DROP TABLE IF EXISTS `collection_followups`;
CREATE TABLE `collection_followups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rent_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `contact_type` varchar(30) NOT NULL,
  `outcome` varchar(40) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `next_followup_at` datetime DEFAULT NULL,
  `created_by_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_collection_followups_rent_id` (`rent_id`),
  KEY `idx_collection_followups_client_id` (`client_id`),
  KEY `idx_collection_followups_next_followup_at` (`next_followup_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `purchase_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `salvage_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `useful_life_months` int(11) NOT NULL DEFAULT 60,
  `depreciation_start_date` date DEFAULT NULL,
  `depreciation_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `depreciation_monthly` decimal(12,2) NOT NULL DEFAULT 0.00,
  `depreciation_accumulated` decimal(12,2) NOT NULL DEFAULT 0.00,
  `book_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estimated_usage_days` int(11) NOT NULL DEFAULT 365,
  `operational_depreciation_per_day` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operational_depreciation_accumulated` decimal(12,2) NOT NULL DEFAULT 0.00,
  `last_depreciation_month` char(7) DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment` VALUES 
('1','الشيول الأول','CAT 966','EQ-0001','available','250.00','0.00','0.00','60',NULL,'10.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-01-11 10:30:53','1'),
('2','القلاب الثاني','VOLVO A40','EQ-0002','available','320.00','0.00','0.00','60',NULL,'8.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-01-11 10:30:53','1'),
('3','حفار كهربائي متطور','HX-4000','SN123456','available','180.00','0.00','0.00','60',NULL,'1.30','0.00','0.00','0.00','365','0.00','0.00',NULL,'2026-01-12','2026-01-11 10:32:52','1'),
('4','ssr','','2200','available','1200.00','0.00','0.00','60',NULL,'0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-01-11 13:17:08','1'),
('5','حفار كاتربيلر2024 ','CAT 320','EQ-1001','available','200.00','0.00','0.00','60',NULL,'8.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-01-12 10:28:27','0'),
('6','حفار كهربائيkk','HX-3000','SN123456','rented','150.00','0.00','0.00','60',NULL,'1.50','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-01-12 13:51:26','1'),
('7','ماطور كهربائي','2002','123','available','5000.00','0.00','0.00','60',NULL,'12.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-04-13 09:32:07','1'),
('8','لاب','ياباني','321','rented','5000.00','0.00','0.00','60',NULL,'250.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-04-21 09:36:15','1');

-- ----------------------------
-- Table: `equipment_depreciation_entries`
-- ----------------------------
DROP TABLE IF EXISTS `equipment_depreciation_entries`;
CREATE TABLE `equipment_depreciation_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `depreciation_month` char(7) NOT NULL,
  `accounting_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operational_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `voucher_payment_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_equipment_dep_month` (`equipment_id`,`depreciation_month`),
  KEY `idx_equipment_dep_month` (`depreciation_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
  `equipment_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_payments_idem_key` (`idempotency_key`),
  KEY `fk_payments_client` (`client_id`),
  KEY `fk_payments_rent` (`rent_id`),
  KEY `fk_payments_user` (`user_id`),
  KEY `idx_payments_equipment` (`equipment_id`),
  CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_rent` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` VALUES 
('1','1','1','1','in','450.00','cash','RCPT-0001','تعديل','2026-01-11 10:30:53','1','2026-01-12 11:15:17',NULL,NULL,NULL),
('2','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 10:42:21','0',NULL,NULL,NULL,NULL),
('3','1','5',NULL,'out','250.00','cash',NULL,NULL,'2026-01-11 13:51:48','1','2026-01-14 12:01:12',NULL,NULL,NULL),
('4','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 14:29:54','0',NULL,NULL,NULL,NULL),
('5','2','1',NULL,'in','500.00','cash','RCPT-0001','دفعة أولى','2026-01-11 14:30:03','0',NULL,NULL,NULL,NULL),
('6','1','5',NULL,'in','200.00','cash',NULL,'دفعة مقدمة','2026-01-11 14:31:31','0',NULL,NULL,NULL,NULL),
('7','2','2',NULL,'in','2000.00','cash',NULL,NULL,'2026-01-12 10:17:25','0',NULL,NULL,NULL,NULL),
('8','5',NULL,NULL,'in','22.00','cash',NULL,NULL,'2026-01-24 13:20:33','0',NULL,NULL,NULL,NULL),
('9','2','21','1','in','5000.00','cash','2200','111','2026-02-02 10:41:05','0',NULL,NULL,NULL,NULL),
('10','4',NULL,'1','out','722.00','cash','2000',NULL,'2026-02-03 11:57:01','0',NULL,NULL,NULL,NULL),
('11','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-05 10:00:42','0',NULL,NULL,NULL,NULL),
('12','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-05 10:00:50','0',NULL,NULL,NULL,NULL),
('13','6','12','1','in','21984.00','cash',NULL,NULL,'2026-02-07 08:21:32','0',NULL,NULL,NULL,NULL),
('14','1','20','1','in','24045.00','cash',NULL,NULL,'2026-02-07 08:21:45','0',NULL,NULL,NULL,NULL),
('15','7','18','1','in','30000.00','cash',NULL,NULL,'2026-02-08 08:34:41','0',NULL,NULL,NULL,NULL),
('16','4','22','1','in','15006.00','cash',NULL,NULL,'2026-02-08 13:34:50','0',NULL,NULL,NULL,NULL),
('17','17',NULL,'1','out','20000.00','cash',NULL,NULL,'2026-02-25 12:03:34','0',NULL,NULL,NULL,NULL),
('18','4','25','1','in','12000.00','cash',NULL,NULL,'2026-04-16 09:32:36','0',NULL,NULL,'rent_25_1776321156310000',NULL),
('19','4','25','1','in','5780.00','cash',NULL,'سند قبض تلقائي عند إغلاق العقد','2026-04-16 09:33:02','0',NULL,NULL,'rent_close_25_1776321182137',NULL),
('20','17','26','1','in','2000.00','cash',NULL,'سند قبض تلقائي عند إغلاق العقد','2026-04-18 11:51:03','0',NULL,NULL,'rent_close_26_1776502263602',NULL),
('21','19','24','1','in','1200.00','cash',NULL,'سند قبض تلقائي عند إغلاق العقد','2026-04-18 11:58:24','0',NULL,NULL,'rent_close_24_1776502704706',NULL),
('22','1','5','1','in','250.00','cash',NULL,'سند قبض تلقائي عند إغلاق العقد','2026-04-18 12:00:25','0',NULL,NULL,'rent_close_5_1776502825394',NULL),
('23','19','23','3','in','5000.00','cash',NULL,NULL,'2026-04-21 09:34:10','0',NULL,NULL,'rent_23_1776753250321000',NULL),
('24','19',NULL,'1','out','1500.00','cash',NULL,'سند صرف إهلاك للمعدة: ssr','2026-04-21 11:08:46','0',NULL,NULL,NULL,'4'),
('25','6','11','1','in','1200.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-04-21 11:10:32','0',NULL,NULL,'quick_close_receipt_11_1776759032604',NULL),
('26','6','6','1','in','127200.00','cash',NULL,'سند قبض تلقائي عند إغلاق العقد','2026-04-27 08:17:22','0',NULL,NULL,'rent_close_6_1777267042603',NULL),
('27','4','25','1','in','7.50','cash',NULL,NULL,'2026-04-27 08:32:39','0',NULL,NULL,'rent_25_1777267959443000',NULL),
('28','19','28','1','in','30000.00','cash',NULL,NULL,'2026-04-27 09:39:35','0',NULL,NULL,'rent_28_1777271975437000',NULL),
('29','2','8','1','in','31800.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-04-27 10:48:35','0',NULL,NULL,'quick_close_receipt_8_1777276115531',NULL);

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
  `closed_at` datetime DEFAULT NULL,
  `closed_by_user_id` int(11) DEFAULT NULL,
  `closing_paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `closing_payment_method` varchar(30) DEFAULT NULL,
  `closing_payment_status` varchar(30) DEFAULT NULL,
  `closing_payment_id` int(11) DEFAULT NULL,
  `pricing_rule_code` varchar(40) DEFAULT NULL,
  `pricing_rule_label` varchar(120) DEFAULT NULL,
  `pricing_rule_applied` tinyint(1) NOT NULL DEFAULT 0,
  `discount_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rents_client` (`client_id`),
  KEY `fk_rents_equipment` (`equipment_id`),
  CONSTRAINT `fk_rents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rents_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rents` VALUES 
('1','1','1','2026-01-11 10:30:53','2026-01-11 18:00:00','7.49','250.00','1872.50','عقد تجريبي مفتوح','closed','2026-01-11 10:30:53','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('2','2','3','2026-01-11 10:00:00','2026-01-11 13:32:41','3.54','300.00','1062.00','عقد مدري','closed','2026-01-11 10:33:20','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('3','4','2','2026-01-11 13:32:55','2026-01-11 14:28:03','0.92','320.00','294.40',NULL,'closed','2026-01-11 13:32:59','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('4','3','1','2026-01-11 13:47:19','2026-01-11 14:50:20','1.05','250.00','262.50',NULL,'closed','2026-01-11 13:47:24','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('5','1','1','2026-01-11 13:50:28','2026-04-18 12:00:25','2325.17','250.00','250.00','تم تمديد الإيجار','closed','2026-01-11 13:50:32','450.00','0.00','1','2026-04-18 11:00:25','2026-04-18 11:00:25','1','250.00','cash','created','22','daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('6','6','4','2026-01-11 13:53:24','2026-04-27 08:17:22','2537.40','1200.00','127200.00',NULL,'closed','2026-01-11 13:53:28','127200.00','0.00','1','2026-04-27 07:17:22','2026-04-27 07:17:22','1','127200.00','cash','created','26','daily_default','الاحتساب الافتراضي: احتساب 106 يوم × السعر اليومي','0','0.00',NULL),
('7','2','3','2026-01-11 10:00:00','2026-01-12 09:10:17','23.17','300.00','6951.00','عقد سوي','closed','2026-01-11 14:25:31','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('8','2','3','2026-01-11 10:00:00','2026-04-27 10:48:32','2543.81','300.00','31800.00','عقد سوي','closed','2026-01-11 14:25:31','31800.00','0.00','1','2026-04-27 09:48:35','2026-04-27 09:48:32','1','31800.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: احتساب 106 يوم × السعر اليومي','0','0.00',NULL),
('9','3','3','2026-01-11 14:37:42',NULL,NULL,'20.00',NULL,NULL,'open','2026-01-11 14:37:46','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('10','3','3','2026-01-11 14:37:42','2026-02-03 13:08:18','550.51','20.00','11010.20',NULL,'closed','2026-01-11 14:37:46','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('11','6','4','2026-01-11 14:37:54','2026-04-21 11:10:30','2395.54','1200.00','1200.00',NULL,'closed','2026-01-11 14:37:58','1200.00','0.00','1','2026-04-21 10:10:32','2026-04-21 10:10:31','1','1200.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('12','6','4','2026-01-11 14:37:54','2026-01-12 08:56:51','18.32','1200.00','21984.00',NULL,'closed','2026-01-11 14:37:58','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('13','4','3','2026-01-11 14:38:08','2026-02-08 13:21:41','670.73','300.00','201219.00',NULL,'closed','2026-01-11 14:38:13','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('14','4','3','2026-01-11 14:38:08','2026-04-21 10:55:47','2395.29','300.00','300.00',NULL,'closed','2026-01-11 14:38:13','0.00','300.00','0',NULL,'2026-04-21 09:55:47','3','300.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('15','4','2','2026-01-11 14:48:56',NULL,NULL,'1250.00',NULL,'تعديل ملاحظات','cancelled','2026-01-11 14:49:01','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('16','4','2','2026-01-11 14:48:56','2026-01-13 08:48:15','41.99','1250.00','52487.50',NULL,'closed','2026-01-11 14:49:01','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('17','2','3','2026-01-11 10:00:00','2026-01-17 12:01:40','146.03','300.00','43809.00','عقد سوي','closed','2026-01-12 10:40:07','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('18','7','1','2026-01-12 10:52:01','2026-01-17 12:01:37','121.16','250.00','30290.00',NULL,'closed','2026-01-12 10:52:02','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('19','5','2','2026-01-13 10:43:28','2026-01-17 12:01:36','97.30','520.00','50596.00',NULL,'closed','2026-01-13 10:43:28','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('20','1','1','2026-02-01 09:49:24','2026-02-05 10:00:26','96.18','250.00','24045.00',NULL,'closed','2026-02-01 09:49:25','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('21','2','2','2026-02-01 09:50:18','2026-02-08 13:21:05','171.51','320.00','54883.20',NULL,'closed','2026-02-01 09:50:19','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('22','4','3','2026-02-05 10:19:18','2026-02-08 13:21:23','75.03','200.00','15006.00',NULL,'closed','2026-02-05 10:19:18','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('23','19','7','2026-04-13 10:02:29','2026-04-21 09:33:38','191.52','5000.00','5000.00',NULL,'closed','2026-04-13 10:02:30','5000.00','0.00','1','2026-04-21 08:34:10','2026-04-21 08:33:38','3','5000.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('24','19','4','2026-04-13 10:04:04','2026-04-18 11:58:24','121.91','1200.00','1200.00',NULL,'closed','2026-04-13 10:04:04','1200.00','0.00','1','2026-04-18 10:58:24','2026-04-18 10:58:24','1','1200.00','cash','created','21','daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('25','4','1','2026-04-13 10:23:45','2026-04-16 09:33:02','71.15','250.00','17787.50',NULL,'closed','2026-04-13 10:23:45','17787.50','0.00','1','2026-04-27 07:32:39','2026-04-16 08:33:02','1','5780.00','cash','created','19','hourly','احتساب بالساعات','0','0.00',NULL),
('26','17','1','2026-04-16 09:34:01','2026-04-18 11:51:03','50.28','2000.00','2000.00',NULL,'closed','2026-04-16 09:34:01','2000.00','0.00','1','2026-04-18 10:51:03','2026-04-18 10:51:03','1','2000.00','cash','created','20','daily_default','الاحتساب الافتراضي: 3 ساعات فأكثر = يوم كامل','0','0.00',NULL),
('27','17','8','2026-04-21 09:36:36',NULL,NULL,'5000.00','0.00',NULL,'open','2026-04-21 09:36:36','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('28','19','7','2026-04-21 09:36:55','2026-04-27 08:40:53','143.07','5000.00','30000.00',NULL,'closed','2026-04-21 09:36:56','30000.00','0.00','1','2026-04-27 08:39:35','2026-04-27 07:40:53','1','350000.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: احتساب 6 يوم × السعر اليومي','0','0.00',NULL);

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
  `cash_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transfer_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_user_date` (`user_id`,`shift_date`),
  CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shift_closings` VALUES 
('1','1','2026-01-29','20200.00','20000.00','-200.00','0.00','0.00','[cash_total=20000, transfer_total=200]','2026-01-29 08:30:45');

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
  `permissions_json` longtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES 
('1','admin','admin123','admin','2026-01-11 10:30:53','1',NULL,NULL,NULL,NULL),
('2','employ','emp123','employee','2026-01-11 10:30:53','1',NULL,NULL,NULL,NULL),
('3','aaa','1234567','employee','2026-01-13 14:00:51','1',NULL,NULL,NULL,NULL),
('4','صالح','123456','employee','2026-02-04 10:24:01','0',NULL,NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS=1;
