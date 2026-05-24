-- Rental System Backup
-- Type: full
-- Generated at: 2026-05-23 18:54:09

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

INSERT INTO `app_settings` VALUES 
('depreciation.processed_month','2026-05','2026-05-07 08:48:31'),
('schema.depreciation.version','2026_05_depreciation_v1','2026-05-07 08:48:31'),
('schema.financials.version','2026_05_perf_indexes_v1','2026-05-07 08:48:31');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` VALUES 
('1',NULL,'receipt_skipped_on_close','rent','1','{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-29 07:31:10\"}','2026-04-29 08:31:10'),
('2',NULL,'rent_closed','rent','1','{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.22,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-29 07:31:10\",\"closed_by_user_id\":1}','2026-04-29 08:31:10'),
('3',NULL,'payment_created','payment','1','{\"rent_id\":1,\"amount\":1333.33,\"type\":\"in\"}','2026-04-29 08:33:13'),
('4',NULL,'receipt_skipped_on_close','rent','2','{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-30 07:03:36\"}','2026-04-30 08:03:36'),
('5',NULL,'rent_closed','rent','2','{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.3,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-30 07:03:36\",\"closed_by_user_id\":1}','2026-04-30 08:03:36'),
('6',NULL,'payment_created','payment','2','{\"rent_id\":2,\"amount\":1333,\"type\":\"in\"}','2026-04-30 08:03:36'),
('7',NULL,'receipt_skipped_on_close','rent','3','{\"closing_paid_amount\":120000,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-05-23 10:35:08\"}','2026-05-23 11:35:08'),
('8',NULL,'rent_closed','rent','3','{\"total_amount\":120000,\"gross_total_amount\":240000,\"discount_amount\":120000,\"discount_note\":\"نصف المتبقي\",\"hours\":555.64,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":120000,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-05-23 10:35:08\",\"closed_by_user_id\":1}','2026-05-23 11:35:08'),
('9',NULL,'payment_created','payment','3','{\"rent_id\":3,\"amount\":120000,\"type\":\"in\"}','2026-05-23 11:35:08'),
('10',NULL,'payment_created','payment','4','{\"rent_id\":2,\"amount\":0.33,\"type\":\"in\"}','2026-05-23 13:36:51');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clients` VALUES 
('1','dfييs',NULL,'785212131','','0','20000.00',NULL,'2026-04-29 07:50:51'),
('2','dfs','','123345678','','0','0.00',NULL,'2026-05-23 11:38:50');

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
  `daily_rate` double DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_active_status` (`is_active`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment` VALUES 
('1','سييب','لبي','123','available','2000.00','0.00','12.00','60','2026-04-29','0.00','0.00','0.00','12.00','365','0.00','0.00','2026-05',NULL,'2026-04-29 08:16:44','1','2000'),
('2','لبييب','الا','122121','available','10000.00','0.00','0.00','60','2026-04-30','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-05',NULL,'2026-04-30 07:56:21','1','10000'),
('3','asads 1','asd','asd 1','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('4','asads 2','asd','asd 2','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('5','asads 3','asd','asd 3','rented','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('6','asads 4','asd','asd 4','rented','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('7','asads 5','asd','123456','rented','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500');

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment_depreciation_entries` VALUES 
('1','1','2026-04','0.00','0.00',NULL,'2026-04-29 08:16:44'),
('2','2','2026-04','0.00','0.00',NULL,'2026-04-30 07:56:21'),
('3','1','2026-05','0.00','0.00',NULL,'2026-05-07 08:22:16'),
('4','2','2026-05','0.00','0.00',NULL,'2026-05-07 08:22:16');

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
  KEY `idx_payments_rent_type_void` (`rent_id`,`type`,`is_void`),
  KEY `idx_payments_created_type` (`created_at`,`type`),
  CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_rent` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` VALUES 
('1','1','1','1','in','1333.33','cash',NULL,NULL,'2026-04-29 08:33:13','0',NULL,NULL,'rent_1_1777440792841000',NULL),
('2','1','2','1','in','1333.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-04-30 08:03:36','0',NULL,NULL,'quick_close_receipt_2_1777525416336',NULL),
('3','1','3','1','in','120000.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-05-23 11:35:08','0',NULL,NULL,'quick_close_receipt_3_1779525308616',NULL),
('4','1','2','1','in','0.33','cash',NULL,NULL,'2026-05-23 13:36:51','0',NULL,NULL,'rent_2_1779532611565000',NULL);

-- ----------------------------
-- Table: `rent_items`
-- ----------------------------
DROP TABLE IF EXISTS `rent_items`;
CREATE TABLE `rent_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rent_id` int(11) NOT NULL,
  `equipment_id` int(11) NOT NULL,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `replaced_by_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rent_id` (`rent_id`),
  KEY `equipment_id` (`equipment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rent_items` VALUES 
('1','1','1','2000.00',NULL,'closed','2026-04-29 08:18:09','2026-04-29 08:31:10',NULL),
('2','2','1','2000.00',NULL,'closed','2026-04-30 07:45:24','2026-04-30 08:03:36',NULL),
('3','3','2','10000.00',NULL,'closed','2026-04-30 07:56:36','2026-05-23 11:35:08',NULL),
('4','4','7','2500.00',NULL,'open','2026-05-23 13:46:17',NULL,NULL),
('5','5','6','2500.00',NULL,'open','2026-05-23 14:58:42',NULL,NULL),
('6','6','5','2500.00',NULL,'open','2026-05-23 15:23:20',NULL,NULL);

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
  KEY `idx_rents_status_start` (`status`,`start_datetime`),
  KEY `idx_rents_client_status` (`client_id`,`status`),
  KEY `idx_rents_equipment_status` (`equipment_id`,`status`),
  KEY `idx_rents_remaining` (`remaining_amount`),
  CONSTRAINT `fk_rents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rents_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rents` VALUES 
('1','1','1','2026-04-29 08:18:09','2026-04-29 08:31:10','0.22','2000.00','1333.33',NULL,'closed','2026-04-29 08:18:09','1333.33','0.00','1','2026-04-29 07:33:13','2026-04-29 07:31:10','1','1333.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL),
('2','1','1','2026-04-30 07:45:24','2026-04-30 08:03:36','0.30','2000.00','1333.33',NULL,'closed','2026-04-30 07:45:25','1333.33','0.00','1','2026-05-23 12:36:51','2026-04-30 07:03:36','1','1333.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL),
('3','1','2','2026-04-30 07:56:36','2026-05-23 11:35:08','555.64','10000.00','120000.00',NULL,'closed','2026-04-30 07:56:36','120000.00','0.00','1','2026-05-23 10:35:08','2026-05-23 10:35:08','1','120000.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: احتساب 24 يوم × السعر اليومي','0','120000.00','نصف المتبقي'),
('4','2','7','2026-05-23 13:46:17',NULL,NULL,NULL,NULL,NULL,'open','2026-05-23 13:46:17','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('5','1','6','2026-05-23 14:58:42',NULL,NULL,NULL,NULL,NULL,'open','2026-05-23 14:58:42','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL),
('6','2','5','2026-05-23 15:23:20',NULL,NULL,NULL,NULL,NULL,'open','2026-05-23 15:23:20','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL);

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
('1','admin','admin123','admin','2026-01-11 10:30:53','1',NULL,NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS=1;
