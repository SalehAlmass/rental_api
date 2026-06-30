-- Rental System Backup
-- Type: full
-- Generated at: 2026-06-30 10:13:39

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
('backup_custom_path_1','C:/xampp/htdocs/alkhair/rental_api/backups/test_path_1','2026-06-24 10:46:25'),
('backup_custom_path_2','E:\\','2026-06-24 11:04:13'),
('depreciation.processed_month','2026-05','2026-05-07 08:48:31'),
('schema.depreciation.version','2026_05_depreciation_v1','2026-05-07 08:48:31'),
('schema.financials.version','2026_05_perf_indexes_v1','2026-05-07 08:48:31'),
('_permissions_seeded','1','2026-06-25 08:25:14');

-- ----------------------------
-- Table: `attendance_logs`
-- ----------------------------
DROP TABLE IF EXISTS `attendance_logs`;
CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `ts` datetime NOT NULL,
  `method` varchar(20) DEFAULT NULL,
  `shift` varchar(20) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `break_minutes` int(11) DEFAULT NULL,
  `late_minutes` int(11) DEFAULT NULL,
  `early_leave_minutes` int(11) DEFAULT NULL,
  `overtime_minutes` int(11) DEFAULT NULL,
  `worked_hours` decimal(10,2) DEFAULT NULL,
  `device_timezone` varchar(100) DEFAULT NULL,
  `device_platform` varchar(50) DEFAULT NULL,
  `device_app_version` varchar(50) DEFAULT NULL,
  `device_name` varchar(100) DEFAULT NULL,
  `server_ts` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_ts` (`user_id`,`ts`),
  KEY `idx_attendance_user_ts` (`user_id`,`ts`),
  KEY `idx_attendance_type` (`type`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `attendance_logs` VALUES 
('1','1','in','2026-06-25 09:29:10','manual','morning',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-25 10:29:10'),
('2','1','out','2026-06-29 09:35:34','manual','morning',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-29 10:35:34'),
('3','1','in','2026-06-30 08:01:51','manual','morning',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-06-30 09:01:51'),
('4','13','in','2026-06-30 07:13:21','biometric','morning',NULL,NULL,'58',NULL,NULL,NULL,'Asia/Riyadh','Android','1.5.0','Samsung S22 Ultra','2026-06-30 10:13:21','2026-06-30 10:13:21'),
('5','15','in','2026-06-30 07:13:39','biometric','morning',NULL,NULL,'58',NULL,NULL,NULL,'Asia/Riyadh','Android','1.5.0','Samsung S22 Ultra','2026-06-30 10:13:39','2026-06-30 10:13:39');

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
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_entity_id` (`entity`,`entity_id`),
  KEY `idx_audit_logs_entity` (`entity`),
  KEY `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_logs` VALUES 
('1','1','receipt_skipped_on_close','rent','1',NULL,NULL,'{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-29 07:31:10\"}','2026-04-29 08:31:10',NULL,NULL),
('2','1','rent_closed','rent','1',NULL,NULL,'{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.22,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-29 07:31:10\",\"closed_by_user_id\":1}','2026-04-29 08:31:10',NULL,NULL),
('3','1','payment_created','payment','1',NULL,NULL,'{\"rent_id\":1,\"amount\":1333.33,\"type\":\"in\"}','2026-04-29 08:33:13',NULL,NULL),
('4','1','receipt_skipped_on_close','rent','2',NULL,NULL,'{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-30 07:03:36\"}','2026-04-30 08:03:36',NULL,NULL),
('5','1','rent_closed','rent','2',NULL,NULL,'{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.3,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-30 07:03:36\",\"closed_by_user_id\":1}','2026-04-30 08:03:36',NULL,NULL),
('6','1','payment_created','payment','2',NULL,NULL,'{\"rent_id\":2,\"amount\":1333,\"type\":\"in\"}','2026-04-30 08:03:36',NULL,NULL),
('7','1','receipt_skipped_on_close','rent','3',NULL,NULL,'{\"closing_paid_amount\":120000,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-05-23 10:35:08\"}','2026-05-23 11:35:08',NULL,NULL),
('8','1','rent_closed','rent','3',NULL,NULL,'{\"total_amount\":120000,\"gross_total_amount\":240000,\"discount_amount\":120000,\"discount_note\":\"نصف المتبقي\",\"hours\":555.64,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":120000,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-05-23 10:35:08\",\"closed_by_user_id\":1}','2026-05-23 11:35:08',NULL,NULL),
('9','1','payment_created','payment','3',NULL,NULL,'{\"rent_id\":3,\"amount\":120000,\"type\":\"in\"}','2026-05-23 11:35:08',NULL,NULL),
('10','1','payment_created','payment','4',NULL,NULL,'{\"rent_id\":2,\"amount\":0.33,\"type\":\"in\"}','2026-05-23 13:36:51',NULL,NULL),
('11','1','rent_closed','rent','6',NULL,NULL,'{\"total_amount\":2500}','2026-05-24 08:54:44',NULL,NULL),
('12','5','payment_created','payment','5',NULL,NULL,'{\"rent_id\":6,\"amount\":2500,\"type\":\"in\"}','2026-05-24 08:55:27',NULL,NULL),
('13','1','payment_created','payment','7',NULL,NULL,'{\"rent_id\":7,\"amount\":77500,\"type\":\"in\"}','2026-06-23 10:44:21',NULL,NULL),
('14','1','rent_closed','rent','7',NULL,NULL,'{\"total_amount\":77500}','2026-06-23 10:54:14',NULL,NULL),
('15','1','payment_created','payment','8',NULL,NULL,'{\"rent_id\":5,\"amount\":77500,\"type\":\"in\"}','2026-06-23 11:13:08',NULL,NULL),
('16','1','rent_closed','rent','5',NULL,NULL,'{\"total_amount\":77500}','2026-06-23 11:20:15',NULL,NULL),
('17','1','payment_created','payment','9',NULL,NULL,'{\"rent_id\":4,\"amount\":77500,\"type\":\"in\"}','2026-06-23 11:25:03',NULL,NULL),
('18',NULL,'rent_closed_auto','rent','5',NULL,NULL,'{\"total_amount\":77500,\"trigger\":\"payment_paid_in_full\"}','2026-06-23 11:47:11',NULL,NULL),
('19','1','rent_closed_auto','rent','4',NULL,NULL,'{\"total_amount\":77500,\"trigger\":\"payment_paid_in_full\"}','2026-06-23 11:50:40',NULL,NULL),
('20','1','payment_created','payment','10',NULL,NULL,'{\"rent_id\":4,\"amount\":77500,\"type\":\"in\"}','2026-06-23 11:50:40',NULL,NULL),
('21','1','payment_created','payment','11',NULL,NULL,'{\"rent_id\":11,\"amount\":5000,\"type\":\"in\"}','2026-06-24 08:33:03',NULL,NULL),
('22','1','rents_archived','rents',NULL,NULL,NULL,'{\"count\":7}','2026-06-24 08:34:06',NULL,NULL),
('23','1','collection_followup_created','rent','11',NULL,NULL,'{\"followup_id\":1,\"contact_type\":\"call\",\"outcome\":\"promise_to_pay\"}','2026-06-24 08:37:13',NULL,NULL),
('24','1','rent_closed','rent','12',NULL,NULL,'{\"total_amount\":7000}','2026-06-24 09:05:24',NULL,NULL),
('25','1','payment_created','payment','12',NULL,NULL,'{\"rent_id\":12,\"amount\":3500,\"type\":\"in\"}','2026-06-24 09:05:25',NULL,NULL),
('26','1','rent_closed','rent','13',NULL,NULL,'{\"total_amount\":15000}','2026-06-24 09:19:30',NULL,NULL),
('27','1','rent_closed','rent','14',NULL,NULL,'{\"total_amount\":12500}','2026-06-24 09:34:40',NULL,NULL),
('28','1','payment_created','payment','13',NULL,NULL,'{\"rent_id\":14,\"amount\":6250,\"type\":\"in\"}','2026-06-24 09:34:41',NULL,NULL),
('29','1','rent_closed','rent','16',NULL,NULL,'{\"total_amount\":5000}','2026-06-24 10:20:15',NULL,NULL),
('30','1','payment_created','payment','14',NULL,NULL,'{\"rent_id\":16,\"amount\":5000,\"type\":\"in\"}','2026-06-24 10:20:29',NULL,NULL),
('31','1','rent_closed','rent','15',NULL,NULL,'{\"total_amount\":5000}','2026-06-24 10:20:53',NULL,NULL),
('32','1','payment_created','payment','15',NULL,NULL,'{\"rent_id\":15,\"amount\":5000,\"type\":\"in\"}','2026-06-24 10:20:53',NULL,NULL),
('33','1','payment_created','payment','16',NULL,NULL,'{\"rent_id\":13,\"amount\":15000,\"type\":\"in\"}','2026-06-25 08:21:11',NULL,NULL),
('34','1','rent_closed','rent','17',NULL,NULL,'{\"total_amount\":5000}','2026-06-25 08:22:23',NULL,NULL),
('35','1','payment_created','payment','17',NULL,NULL,'{\"rent_id\":17,\"amount\":5000,\"type\":\"in\"}','2026-06-25 08:22:23',NULL,NULL),
('36','1','rent_closed','rent','18',NULL,NULL,'{\"total_amount\":5000}','2026-06-25 09:36:44',NULL,NULL),
('37','1','payment_created','payment','18',NULL,NULL,'{\"rent_id\":18,\"amount\":5000,\"type\":\"in\"}','2026-06-25 09:36:45',NULL,NULL),
('38','5','shift_closed','shift_closing','2',NULL,NULL,'{\"shift_date\":\"2026-06-29\",\"expected_amount\":0,\"actual_amount\":100,\"difference\":100,\"cash_total\":0,\"transfer_total\":0}','2026-06-29 08:55:46',NULL,NULL),
('39','5','shift_difference_detected','shift_closing','2',NULL,NULL,'{\"shift_date\":\"2026-06-29\",\"difference\":100,\"expected_amount\":0,\"actual_amount\":100}','2026-06-29 08:55:46',NULL,NULL),
('40','5','shift_closed','shift_closing','2',NULL,NULL,'{\"shift_date\":\"2026-06-29\",\"expected_amount\":0,\"actual_amount\":100,\"difference\":100,\"cash_total\":0,\"transfer_total\":0}','2026-06-29 08:56:05',NULL,NULL),
('41','5','shift_difference_detected','shift_closing','2',NULL,NULL,'{\"shift_date\":\"2026-06-29\",\"difference\":100,\"expected_amount\":0,\"actual_amount\":100}','2026-06-29 08:56:05',NULL,NULL),
('42','1','rent_closed','rent','11',NULL,NULL,'{\"total_amount\":60000}','2026-06-29 09:02:25',NULL,NULL),
('43','1','payment_created','payment','19',NULL,NULL,'{\"rent_id\":11,\"amount\":55000,\"type\":\"in\"}','2026-06-29 09:02:25',NULL,NULL),
('46','1','permissions_changed','user','5','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"reports\":false,\"hr\":false,\"attendance\":false,\"shifts\":false,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"reports\":false,\"hr\":false,\"attendance\":false,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}',NULL,'2026-06-29 09:54:13',NULL,NULL),
('47','1','user_updated','user','5','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"hr\":false,\"attendance\":false,\"shifts\":false,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false}}}','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"hr\":false,\"attendance\":false,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-29 09:54:13',NULL,NULL),
('48','5','rent_closed','rent','10','{\"total_amount\":84000}',NULL,NULL,'2026-06-30 09:02:40',NULL,NULL),
('49','1','permissions_changed','user','5','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"reports\":false,\"hr\":false,\"attendance\":false,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}',NULL,'2026-06-30 09:03:33',NULL,NULL),
('50','1','user_updated','user','5','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":false,\"receipts\":false,\"hr\":false,\"attendance\":false,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}}','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 09:03:33',NULL,NULL),
('51','1','permissions_changed','user','6','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}','{\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL,'2026-06-30 09:03:48',NULL,NULL),
('52','1','user_updated','user','6','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true}}','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 09:03:48',NULL,NULL),
('53','5','rent_closed','rent','19','{\"total_amount\":5000}',NULL,NULL,'2026-06-30 09:04:59',NULL,NULL),
('54','5','payment_created','payment','20','{\"rent_id\":19,\"amount\":5000,\"type\":\"in\"}',NULL,NULL,'2026-06-30 09:05:00',NULL,NULL),
('55','5','payment_created','payment','21','{\"rent_id\":10,\"amount\":84000,\"type\":\"in\"}',NULL,NULL,'2026-06-30 09:05:17',NULL,NULL),
('56','5','rent_closed','rent','20','{\"total_amount\":30000}',NULL,NULL,'2026-06-30 09:08:36',NULL,NULL),
('57',NULL,'login_failed','user','1',NULL,NULL,'{\"username\":\"admin\",\"reason\":\"wrong_password\"}','2026-06-30 10:10:53','127.0.0.1',NULL),
('58',NULL,'password_changed','user','1',NULL,NULL,'{\"reason\":\"auto_upgrade_to_hash\"}','2026-06-30 10:11:18','127.0.0.1',NULL),
('59',NULL,'login_success','user','1',NULL,NULL,'{\"session_id\":1,\"device_name\":\"Test Desktop PC\",\"device_platform\":\"Integration_Test_Runner\"}','2026-06-30 10:11:18','127.0.0.1',NULL),
('60',NULL,'session_created','session','1',NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-01 10:11:18\"}','2026-06-30 10:11:18','127.0.0.1',NULL),
('61','1','user_created','user','7',NULL,'{\"username\":\"test_employee_1782803478\",\"role\":\"employee\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 10:11:18','127.0.0.1',NULL),
('62','1','user_created','user','8',NULL,'{\"username\":\"test_manager_1782803478\",\"role\":\"manager\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}}',NULL,'2026-06-30 10:11:18','127.0.0.1',NULL),
('63',NULL,'login_success','user','7',NULL,NULL,'{\"session_id\":2,\"device_name\":\"Samsung S22 Ultra\",\"device_platform\":\"Android\"}','2026-06-30 10:11:18','127.0.0.1',NULL),
('64',NULL,'session_created','session','2',NULL,NULL,'{\"user_id\":7,\"expires_at\":\"2026-07-01 10:11:18\"}','2026-06-30 10:11:18','127.0.0.1',NULL),
('65','7','depreciation_generated','equipment','14',NULL,NULL,'{\"depreciation_type\":\"accounting\",\"amount\":2500,\"period\":\"2026-06\"}','2026-06-30 10:11:19','127.0.0.1',NULL),
('66','7','depreciation_generated','equipment','14',NULL,NULL,'{\"depreciation_type\":\"operational\",\"amount\":410.96,\"period\":\"2026-06\"}','2026-06-30 10:11:19','127.0.0.1',NULL),
('67',NULL,'login_success','user','1',NULL,NULL,'{\"session_id\":3,\"device_name\":\"Test Desktop PC\",\"device_platform\":\"Integration_Test_Runner\"}','2026-06-30 10:12:29','127.0.0.1',NULL),
('68',NULL,'session_created','session','3',NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-01 10:12:29\"}','2026-06-30 10:12:29','127.0.0.1',NULL),
('69','1','user_created','user','9',NULL,'{\"username\":\"test_employee_1782803549\",\"role\":\"employee\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 10:12:29','127.0.0.1',NULL),
('70','1','user_created','user','10',NULL,'{\"username\":\"test_manager_1782803549\",\"role\":\"manager\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}}',NULL,'2026-06-30 10:12:29','127.0.0.1',NULL),
('71',NULL,'login_success','user','9',NULL,NULL,'{\"session_id\":4,\"device_name\":\"Samsung S22 Ultra\",\"device_platform\":\"Android\"}','2026-06-30 10:12:30','127.0.0.1',NULL),
('72',NULL,'session_created','session','4',NULL,NULL,'{\"user_id\":9,\"expires_at\":\"2026-07-01 10:12:30\"}','2026-06-30 10:12:30','127.0.0.1',NULL),
('73',NULL,'login_success','user','1',NULL,NULL,'{\"session_id\":5,\"device_name\":\"Test Desktop PC\",\"device_platform\":\"Integration_Test_Runner\"}','2026-06-30 10:13:10','127.0.0.1',NULL),
('74',NULL,'session_created','session','5',NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-01 10:13:10\"}','2026-06-30 10:13:10','127.0.0.1',NULL),
('75','1','user_created','user','11',NULL,'{\"username\":\"test_employee_1782803590\",\"role\":\"employee\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 10:13:10','127.0.0.1',NULL),
('76','1','user_created','user','12',NULL,'{\"username\":\"test_manager_1782803590\",\"role\":\"manager\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}}',NULL,'2026-06-30 10:13:10','127.0.0.1',NULL),
('77',NULL,'login_success','user','11',NULL,NULL,'{\"session_id\":6,\"device_name\":\"Samsung S22 Ultra\",\"device_platform\":\"Android\"}','2026-06-30 10:13:10','127.0.0.1',NULL),
('78',NULL,'session_created','session','6',NULL,NULL,'{\"user_id\":11,\"expires_at\":\"2026-07-01 10:13:10\"}','2026-06-30 10:13:10','127.0.0.1',NULL),
('79',NULL,'login_success','user','1',NULL,NULL,'{\"session_id\":7,\"device_name\":\"Test Desktop PC\",\"device_platform\":\"Integration_Test_Runner\"}','2026-06-30 10:13:20','127.0.0.1',NULL),
('80',NULL,'session_created','session','7',NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-01 10:13:20\"}','2026-06-30 10:13:20','127.0.0.1',NULL),
('81','1','user_created','user','13',NULL,'{\"username\":\"test_employee_1782803600\",\"role\":\"employee\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 10:13:20','127.0.0.1',NULL),
('82','1','user_created','user','14',NULL,'{\"username\":\"test_manager_1782803600\",\"role\":\"manager\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}}',NULL,'2026-06-30 10:13:20','127.0.0.1',NULL),
('83',NULL,'login_success','user','13',NULL,NULL,'{\"session_id\":8,\"device_name\":\"Samsung S22 Ultra\",\"device_platform\":\"Android\"}','2026-06-30 10:13:20','127.0.0.1',NULL),
('84',NULL,'session_created','session','8',NULL,NULL,'{\"user_id\":13,\"expires_at\":\"2026-07-01 10:13:20\"}','2026-06-30 10:13:20','127.0.0.1',NULL),
('85','13','depreciation_generated','equipment','15',NULL,NULL,'{\"depreciation_type\":\"accounting\",\"amount\":2500,\"period\":\"2026-06\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('86','13','depreciation_generated','equipment','15',NULL,NULL,'{\"depreciation_type\":\"operational\",\"amount\":410.96,\"period\":\"2026-06\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('87','13','payment_created','payment','24','{\"rent_id\":23,\"amount\":500,\"type\":\"in\"}',NULL,NULL,'2026-06-30 10:13:21','127.0.0.1',NULL),
('88','13','attendance_late','attendance','4',NULL,NULL,'{\"late_minutes\":58,\"ts\":\"2026-06-30 07:13:21\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('89','13','attendance_check_in','attendance','4',NULL,NULL,'{\"ts\":\"2026-06-30 07:13:21\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('90',NULL,'login_success','user','14',NULL,NULL,'{\"session_id\":9,\"device_name\":\"Manager iPad Pro\",\"device_platform\":\"iOS\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('91',NULL,'session_created','session','9',NULL,NULL,'{\"user_id\":14,\"expires_at\":\"2026-07-01 10:13:21\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('92','1','backup_failed','backup',NULL,NULL,NULL,'{\"file\":\"backup_full_2026-06-30_10-13-21.sql\",\"reason\":\"PDO::quote(): Argument #1 ($string) must be of type string, int given\"}','2026-06-30 10:13:21','127.0.0.1',NULL),
('93',NULL,'login_success','user','1',NULL,NULL,'{\"session_id\":10,\"device_name\":\"Test Desktop PC\",\"device_platform\":\"Integration_Test_Runner\"}','2026-06-30 10:13:38','127.0.0.1',NULL),
('94',NULL,'session_created','session','10',NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-01 10:13:38\"}','2026-06-30 10:13:38','127.0.0.1',NULL),
('95','1','user_created','user','15',NULL,'{\"username\":\"test_employee_1782803618\",\"role\":\"employee\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}',NULL,'2026-06-30 10:13:38','127.0.0.1',NULL),
('96','1','user_created','user','16',NULL,'{\"username\":\"test_manager_1782803618\",\"role\":\"manager\",\"is_active\":1,\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}}',NULL,'2026-06-30 10:13:38','127.0.0.1',NULL),
('97',NULL,'login_success','user','15',NULL,NULL,'{\"session_id\":11,\"device_name\":\"Samsung S22 Ultra\",\"device_platform\":\"Android\"}','2026-06-30 10:13:38','127.0.0.1',NULL),
('98',NULL,'session_created','session','11',NULL,NULL,'{\"user_id\":15,\"expires_at\":\"2026-07-01 10:13:38\"}','2026-06-30 10:13:38','127.0.0.1',NULL),
('99','15','depreciation_generated','equipment','16',NULL,NULL,'{\"depreciation_type\":\"accounting\",\"amount\":2500,\"period\":\"2026-06\"}','2026-06-30 10:13:39','127.0.0.1',NULL),
('100','15','depreciation_generated','equipment','16',NULL,NULL,'{\"depreciation_type\":\"operational\",\"amount\":410.96,\"period\":\"2026-06\"}','2026-06-30 10:13:39','127.0.0.1',NULL),
('101','15','payment_created','payment','26','{\"rent_id\":24,\"amount\":500,\"type\":\"in\"}',NULL,NULL,'2026-06-30 10:13:39','127.0.0.1',NULL),
('102','15','attendance_late','attendance','5',NULL,NULL,'{\"late_minutes\":58,\"ts\":\"2026-06-30 07:13:39\"}','2026-06-30 10:13:39','127.0.0.1',NULL),
('103','15','attendance_check_in','attendance','5',NULL,NULL,'{\"ts\":\"2026-06-30 07:13:39\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\"}','2026-06-30 10:13:39','127.0.0.1',NULL),
('104',NULL,'login_success','user','16',NULL,NULL,'{\"session_id\":12,\"device_name\":\"Manager iPad Pro\",\"device_platform\":\"iOS\"}','2026-06-30 10:13:39','127.0.0.1',NULL),
('105',NULL,'session_created','session','12',NULL,NULL,'{\"user_id\":16,\"expires_at\":\"2026-07-01 10:13:39\"}','2026-06-30 10:13:39','127.0.0.1',NULL);

-- ----------------------------
-- Table: `backup_logs`
-- ----------------------------
DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `backup_logs` VALUES 
('1','1','backup_full_2026-06-30_10-13-21.sql','0','failed','2026-06-30 10:13:21');

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
  PRIMARY KEY (`id`),
  KEY `idx_clients_phone` (`phone`),
  KEY `idx_clients_national_id` (`national_id`),
  KEY `idx_clients_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clients` VALUES 
('1','dfييs',NULL,'785212131','','0','20000.00',NULL,'2026-04-29 07:50:51'),
('2','dfs','','123345678','','0','0.00',NULL,'2026-05-23 11:38:50'),
('5','صالح الماس',NULL,'123456779','','0','20000.00',NULL,'2026-06-23 11:45:04'),
('6','عميل تجريبي من الفحص','1234567890','0564823031','','0','0.00',NULL,'2026-06-30 10:11:18'),
('7','عميل تجريبي 6551','NID-7136039','0511579031','','0','0.00',NULL,'2026-06-30 10:13:20'),
('8','عميل تجريبي 3448','NID-3974942','0580951875','','0','0.00',NULL,'2026-06-30 10:13:38');

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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `collection_followups` VALUES 
('1','11','5','call','promise_to_pay',NULL,'2026-06-25 08:37:10',NULL,'2026-06-24 08:37:13');

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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment` VALUES 
('1','سييب','لبي','123','available','2000.00','0.00','12.00','60','2026-04-29','0.00','0.00','0.00','12.00','365','0.00','0.00','2026-06',NULL,'2026-04-29 08:16:44','1','2000'),
('2','لبييب','الا','122121','available','10000.00','0.00','0.00','60','2026-04-30','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-04-30 07:56:21','1','10000'),
('3','asads 1','asd','asd 1','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-05-07 09:10:48','1','2500'),
('4','asads 2','asd','asd 2','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-05-07 09:10:48','1','2500'),
('5','asads 3','asd','asd 3','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-05-07 09:10:48','1','2500'),
('6','asads 4','asd','asd 4','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-05-07 09:10:48','1','2500'),
('7','asads 5','asd','123456','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-05-07 09:10:48','1','2500'),
('8','ماطور','',NULL,'available','2000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:10','1','2000'),
('9','در يل 1','',NULL,'rented','5000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:46','1','5000'),
('10','در يل 2','',NULL,'available','5000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:46','1','5000'),
('11','در يل 3','',NULL,'available','5000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:46','1','5000'),
('12','در يل 4','',NULL,'available','5000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:46','1','5000'),
('13','در يل 5','',NULL,'available','5000.00','0.00','0.00','60','2026-06-23','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-06',NULL,'2026-06-23 11:22:46','1','5000'),
('14','رافعة تلسكوبية 15 متر','',NULL,'rented','2000.00','150000.00','0.00','60','2026-06-30','0.00','2500.00','2500.00','147500.00','365','410.96','410.96','2026-06',NULL,'2026-06-30 10:11:19','1','2000'),
('15','رافعة تلسكوبية 15 متر','',NULL,'rented','2000.00','150000.00','0.00','60','2026-06-30','0.00','2500.00','2500.00','147500.00','365','410.96','410.96','2026-06',NULL,'2026-06-30 10:13:21','1','2000'),
('16','رافعة تلسكوبية 15 متر','',NULL,'rented','2000.00','150000.00','0.00','60','2026-06-30','0.00','2500.00','2500.00','147500.00','365','410.96','410.96','2026-06',NULL,'2026-06-30 10:13:38','1','2000');

-- ----------------------------
-- Table: `equipment_depreciation_entries`
-- ----------------------------
DROP TABLE IF EXISTS `equipment_depreciation_entries`;
CREATE TABLE `equipment_depreciation_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `depreciation_month` char(7) NOT NULL,
  `depreciation_type` varchar(20) NOT NULL DEFAULT 'accounting',
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `accum_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `accum_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `book_before` decimal(12,2) NOT NULL DEFAULT 0.00,
  `book_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `accounting_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `operational_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `voucher_payment_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_equipment_dep_month_type` (`equipment_id`,`depreciation_month`,`depreciation_type`),
  KEY `idx_equipment_dep_month` (`depreciation_month`),
  KEY `idx_equipment_dep_entries_equipment_id` (`equipment_id`),
  KEY `idx_equipment_dep_entries_depreciation_month` (`depreciation_month`),
  KEY `idx_equipment_dep_entries_depreciation_type` (`depreciation_type`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `equipment_depreciation_entries` VALUES 
('1','1','2026-04','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-04-29 08:16:44'),
('2','2','2026-04','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-04-30 07:56:21'),
('3','1','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-07 08:22:16'),
('4','2','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-07 08:22:16'),
('5','3','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-24 08:50:28'),
('6','4','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-24 08:50:28'),
('7','5','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-24 08:50:28'),
('8','6','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-24 08:50:28'),
('9','7','2026-05','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-05-24 08:50:28'),
('10','1','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('11','2','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('12','3','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('13','4','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('14','5','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('15','6','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('16','7','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 10:22:04'),
('17','8','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:10'),
('18','9','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:46'),
('19','10','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:46'),
('20','11','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:46'),
('21','12','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:46'),
('22','13','2026-06','accounting','0.00','0.00','0.00','0.00','0.00','0.00','0.00',NULL,'2026-06-23 11:22:46'),
('24','14','2026-06','accounting','2500.00','0.00','2500.00','150000.00','147500.00','2500.00','0.00','22','2026-06-30 10:11:19'),
('25','14','2026-06','operational','410.96','0.00','410.96','147500.00','147500.00','0.00','410.96',NULL,'2026-06-30 10:11:19'),
('26','15','2026-06','accounting','2500.00','0.00','2500.00','150000.00','147500.00','2500.00','0.00','23','2026-06-30 10:13:21'),
('27','15','2026-06','operational','410.96','0.00','410.96','147500.00','147500.00','0.00','410.96',NULL,'2026-06-30 10:13:21'),
('28','16','2026-06','accounting','2500.00','0.00','2500.00','150000.00','147500.00','2500.00','0.00','25','2026-06-30 10:13:39'),
('29','16','2026-06','operational','410.96','0.00','410.96','147500.00','147500.00','0.00','410.96',NULL,'2026-06-30 10:13:39');

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
  KEY `idx_payments_client_id` (`client_id`),
  KEY `idx_payments_rent_id` (`rent_id`),
  KEY `idx_payments_user_id` (`user_id`),
  KEY `idx_payments_created_at` (`created_at`),
  KEY `idx_payments_user_date_void` (`user_id`,`created_at`,`is_void`),
  KEY `idx_payments_type` (`type`),
  KEY `idx_payments_method` (`method`),
  KEY `idx_payments_is_void` (`is_void`),
  KEY `idx_payments_user_created` (`user_id`,`created_at`),
  KEY `idx_payments_type_method` (`type`,`method`),
  CONSTRAINT `fk_payments_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_rent` FOREIGN KEY (`rent_id`) REFERENCES `rents` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `payments` VALUES 
('1','1','1','1','in','1333.33','cash',NULL,NULL,'2026-04-29 08:33:13','0',NULL,NULL,'rent_1_1777440792841000',NULL),
('2','1','2','1','in','1333.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-04-30 08:03:36','0',NULL,NULL,'quick_close_receipt_2_1777525416336',NULL),
('3','1','3','1','in','120000.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-05-23 11:35:08','0',NULL,NULL,'quick_close_receipt_3_1779525308616',NULL),
('4','1','2','1','in','0.33','cash',NULL,NULL,'2026-05-23 13:36:51','0',NULL,NULL,'rent_2_1779532611565000',NULL),
('5','2','6','5','in','2500.00','cash',NULL,NULL,'2026-05-24 08:55:27','0',NULL,NULL,'rent_6_1779602127290000',NULL),
('7','1','7','1','in','77500.00','cash',NULL,NULL,'2026-06-23 10:44:21','0',NULL,NULL,'rent_7_1782200660740000',NULL),
('8','1','5','1','in','77500.00','cash',NULL,NULL,'2026-06-23 11:13:08','0',NULL,NULL,'rent_5_1782202388554000',NULL),
('9','2','4','1','in','77500.00','cash',NULL,NULL,'2026-06-23 11:25:03','0',NULL,NULL,'rent_4_1782203102943000',NULL),
('10','2','4','1','in','77500.00','cash',NULL,NULL,'2026-06-23 11:50:40','0',NULL,NULL,'rent_4_1782204640469000',NULL),
('11','5','11','1','in','5000.00','cash',NULL,NULL,'2026-06-24 08:33:03','0',NULL,NULL,'rent_11_1782279183582000',NULL),
('12','5','12','1','in','3500.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-06-24 09:05:25','0',NULL,NULL,'quick_close_receipt_12_1782281124944',NULL),
('13','5','14','1','in','6250.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-06-24 09:34:41','0',NULL,NULL,'quick_close_receipt_14_1782282880913',NULL),
('14','1','16','1','in','5000.00','cash',NULL,NULL,'2026-06-24 10:20:29','0',NULL,NULL,'rent_16_1782285629142000',NULL),
('15','5','15','1','in','5000.00','cash',NULL,'سند قبض بعد إغلاق العقد','2026-06-24 10:20:53','0',NULL,NULL,'rent_close_receipt_15_1782285653389',NULL),
('16','5','13','1','in','15000.00','cash',NULL,NULL,'2026-06-25 08:21:11','0',NULL,NULL,'rent_13_1782364871460000',NULL),
('17','5','17','1','in','5000.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-06-25 08:22:23','0',NULL,NULL,'quick_close_receipt_17_1782364943241',NULL),
('18','2','18','1','in','5000.00','cash',NULL,'سند قبض بعد إغلاق العقد','2026-06-25 09:36:45','0',NULL,NULL,'rent_close_receipt_18_1782369405038',NULL),
('19','5','11','1','in','55000.00','cash',NULL,'سند قبض بعد إغلاق العقد','2026-06-29 09:02:25','0',NULL,NULL,'rent_close_receipt_11_1782712945540',NULL),
('20','5','19','5','in','5000.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-06-30 09:05:00','0',NULL,NULL,'quick_close_receipt_19_1782799499954',NULL),
('21','1','10','5','in','84000.00','cash',NULL,NULL,'2026-06-30 09:05:17','0',NULL,NULL,'rent_10_1782799516842000',NULL),
('22',NULL,NULL,NULL,'','2500.00','system','DEP-2026-06-14','قيد إهلاك شهري تلقائي للمعدة #14 عن شهر 2026-06','2026-06-30 10:11:19','0',NULL,NULL,'dep_14_2026-06','14'),
('23',NULL,NULL,NULL,'','2500.00','system','DEP-2026-06-15','قيد إهلاك شهري تلقائي للمعدة #15 عن شهر 2026-06','2026-06-30 10:13:21','0',NULL,NULL,'dep_15_2026-06','15'),
('24','7','23','13','in','500.00','cash',NULL,'دفعة نقدية تجريبية','2026-06-30 10:13:21','0',NULL,NULL,NULL,NULL),
('25',NULL,NULL,NULL,'','2500.00','system','DEP-2026-06-16','قيد إهلاك شهري تلقائي للمعدة #16 عن شهر 2026-06','2026-06-30 10:13:39','0',NULL,NULL,'dep_16_2026-06','16'),
('26','8','24','15','in','500.00','cash',NULL,'دفعة نقدية تجريبية','2026-06-30 10:13:39','0',NULL,NULL,NULL,NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `rent_items` VALUES 
('1','1','1','2000.00',NULL,'closed','2026-04-29 08:18:09','2026-04-29 08:31:10',NULL),
('2','2','1','2000.00',NULL,'closed','2026-04-30 07:45:24','2026-04-30 08:03:36',NULL),
('3','3','2','10000.00',NULL,'closed','2026-04-30 07:56:36','2026-05-23 11:35:08',NULL),
('4','4','7','2500.00',NULL,'closed','2026-05-23 13:46:17','2026-06-23 10:50:40',NULL),
('5','5','6','2500.00',NULL,'closed','2026-05-23 14:58:42','2026-06-23 08:47:11',NULL),
('6','6','5','2500.00',NULL,'closed','2026-05-23 15:23:20','2026-05-24 08:54:44',NULL),
('7','7','5','2500.00',NULL,'closed','2026-05-24 09:20:28','2026-06-23 10:54:13',NULL),
('10','10','2','10000.00',NULL,'closed','2026-06-23 10:51:34','2026-06-30 09:02:40',NULL),
('11','10','1','2000.00',NULL,'closed','2026-06-23 10:51:34','2026-06-30 09:02:40',NULL),
('12','11','13','5000.00',NULL,'replaced','2026-06-23 11:45:25','2026-06-24 07:36:43','9'),
('13','11','11','5000.00',NULL,'closed','2026-06-23 11:45:25','2026-06-29 09:02:25',NULL),
('14','11','9','5000.00',NULL,'closed','2026-06-24 07:36:43','2026-06-29 09:02:25',NULL),
('15','12','8','2000.00',NULL,'replaced','2026-06-24 08:38:16','2026-06-24 07:39:10','10'),
('16','12','10','5000.00',NULL,'closed','2026-06-24 07:39:10','2026-06-24 09:05:24',NULL),
('17','13','3','2500.00',NULL,'closed','2026-06-24 09:11:34','2026-06-24 09:19:30',NULL),
('18','13','13','5000.00',NULL,'replaced','2026-06-24 09:11:34','2026-06-24 08:12:23','10'),
('19','13','10','5000.00',NULL,'replaced','2026-06-24 09:11:34','2026-06-24 08:11:51','12'),
('20','13','5','2500.00',NULL,'closed','2026-06-24 09:11:34','2026-06-24 09:19:30',NULL),
('21','13','12','5000.00',NULL,'closed','2026-06-24 08:11:51','2026-06-24 09:19:30',NULL),
('22','13','10','5000.00',NULL,'closed','2026-06-24 08:12:23','2026-06-24 09:19:30',NULL),
('23','14','13','5000.00',NULL,'replaced','2026-06-24 09:20:31','2026-06-24 08:20:51','12'),
('24','14','10','5000.00',NULL,'replaced','2026-06-24 09:20:31','2026-06-24 08:21:11','13'),
('25','14','4','2500.00',NULL,'closed','2026-06-24 09:20:31','2026-06-24 08:21:54',NULL),
('26','14','12','5000.00',NULL,'closed','2026-06-24 08:20:51','2026-06-24 09:34:40',NULL),
('27','14','13','5000.00',NULL,'closed','2026-06-24 08:21:11','2026-06-24 09:34:40',NULL),
('28','15','13','5000.00',NULL,'closed','2026-06-24 10:19:12','2026-06-24 10:20:53',NULL),
('29','16','12','5000.00',NULL,'closed','2026-06-24 10:19:23','2026-06-24 10:20:15',NULL),
('30','17','13','5000.00',NULL,'closed','2026-06-25 08:22:14','2026-06-25 08:22:23',NULL),
('31','18','12','5000.00',NULL,'closed','2026-06-25 09:36:33','2026-06-25 09:36:44',NULL),
('32','19','13','5000.00',NULL,'closed','2026-06-30 09:04:54','2026-06-30 09:04:59',NULL),
('33','20','13','5000.00',NULL,'closed','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL),
('34','20','12','5000.00',NULL,'closed','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL),
('35','20','2','10000.00',NULL,'closed','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL),
('36','20','11','5000.00',NULL,'closed','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL),
('37','20','10','5000.00',NULL,'closed','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL),
('38','21','9','5000.00',NULL,'open','2026-06-30 09:07:59',NULL,NULL),
('39','22','14','2000.00',NULL,'open','2026-06-30 07:11:19','2026-07-01 07:11:19',NULL),
('40','23','15','2000.00',NULL,'open','2026-06-30 07:13:21','2026-07-01 07:13:21',NULL),
('41','24','16','2000.00',NULL,'open','2026-06-30 07:13:38','2026-07-01 07:13:38',NULL);

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
  `archived_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_rents_client` (`client_id`),
  KEY `fk_rents_equipment` (`equipment_id`),
  KEY `idx_rents_status_start` (`status`,`start_datetime`),
  KEY `idx_rents_client_status` (`client_id`,`status`),
  KEY `idx_rents_equipment_status` (`equipment_id`,`status`),
  KEY `idx_rents_remaining` (`remaining_amount`),
  KEY `idx_rents_client_id` (`client_id`),
  KEY `idx_rents_status` (`status`),
  KEY `idx_rents_created_at` (`created_at`),
  KEY `idx_rents_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_rents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rents_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rents` VALUES 
('1','1','1','2026-04-29 08:18:09','2026-04-29 08:31:10','0.22','2000.00','1333.33',NULL,'closed','2026-04-29 08:18:09','1333.33','0.00','1','2026-04-29 07:33:13','2026-04-29 07:31:10','1','1333.00','cash','created','1','daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL,'2026-06-24 08:34:06'),
('2','1','1','2026-04-30 07:45:24','2026-04-30 08:03:36','0.30','2000.00','1333.33',NULL,'closed','2026-04-30 07:45:25','1333.33','0.00','1','2026-05-23 12:36:51','2026-04-30 07:03:36','1','1333.00','cash','created','2','daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL,'2026-06-24 08:34:06'),
('3','1','2','2026-04-30 07:56:36','2026-05-23 11:35:08','555.64','10000.00','120000.00',NULL,'closed','2026-04-30 07:56:36','120000.00','0.00','1','2026-05-23 10:35:08','2026-05-23 10:35:08','1','120000.00','cash','created','3','daily_default','الاحتساب الافتراضي: احتساب 24 يوم × السعر اليومي','0','120000.00','نصف المتبقي','2026-06-24 08:34:06'),
('4','2','7','2026-05-23 13:46:17','2026-06-23 10:50:40',NULL,NULL,'77500.00',NULL,'closed','2026-05-23 13:46:17','155000.00','0.00','1','2026-06-23 10:50:40','2026-06-23 10:50:40',NULL,'0.00','cash','created','10',NULL,NULL,'0','0.00',NULL,'2026-06-24 08:34:06'),
('5','1','6','2026-05-23 14:58:42','2026-06-23 08:47:11',NULL,NULL,'77500.00',NULL,'closed','2026-05-23 14:58:42','77500.00','0.00','0',NULL,'2026-06-23 08:47:11',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00','','2026-06-24 08:34:06'),
('6','2','5','2026-05-23 15:23:20','2026-05-24 08:54:44',NULL,NULL,'2500.00',NULL,'closed','2026-05-23 15:23:20','2500.00','0.00','1','2026-05-24 07:55:27','2026-05-24 08:54:44',NULL,'0.00','cash','created','5',NULL,NULL,'0','0.00','','2026-06-24 08:34:06'),
('7','1','5','2026-05-24 09:20:28','2026-06-23 10:54:13',NULL,NULL,'77500.00',NULL,'closed','2026-05-24 09:20:29','77500.00','0.00','1','2026-06-23 09:54:14','2026-06-23 10:54:13',NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00','','2026-06-24 08:34:06'),
('10','1','2','2026-06-23 10:51:34','2026-06-30 09:02:40',NULL,NULL,'84000.00',NULL,'closed','2026-06-23 10:51:34','84000.00','0.00','1','2026-06-30 08:05:17','2026-06-30 09:02:40','5','0.00','cash','created','21',NULL,NULL,'0','0.00','',NULL),
('11','5','9','2026-06-23 11:45:25','2026-06-29 09:02:25',NULL,NULL,'60000.00',NULL,'closed','2026-06-23 11:45:25','60000.00','0.00','1','2026-06-29 08:02:25','2026-06-29 09:02:25','1','0.00','cash','created','19',NULL,NULL,'0','0.00','',NULL),
('12','5','10','2026-06-24 08:38:16','2026-06-24 09:05:24',NULL,NULL,'7000.00',NULL,'closed','2026-06-24 08:38:16','3500.00','0.00','1','2026-06-24 08:05:25','2026-06-24 09:05:24',NULL,'0.00','cash','created','12',NULL,NULL,'0','3500.00','نصف المتبقي',NULL),
('13','5','3','2026-06-24 09:11:34','2026-06-24 09:19:30',NULL,NULL,'15000.00',NULL,'closed','2026-06-24 09:11:35','15000.00','0.00','1','2026-06-25 07:21:11','2026-06-24 09:19:30',NULL,'0.00','cash','created','16',NULL,NULL,'0','0.00','',NULL),
('14','5','12','2026-06-24 09:20:31','2026-06-24 09:34:40',NULL,NULL,'12500.00',NULL,'closed','2026-06-24 09:20:32','6250.00','0.00','1','2026-06-24 08:34:41','2026-06-24 09:34:40',NULL,'0.00','cash','created','13',NULL,NULL,'0','6250.00','نصف المتبقي',NULL),
('15','5','13','2026-06-24 10:19:12','2026-06-24 10:20:53',NULL,NULL,'5000.00',NULL,'closed','2026-06-24 10:19:12','5000.00','0.00','1','2026-06-24 09:20:53','2026-06-24 10:20:53','1','0.00','cash','created','15',NULL,NULL,'0','0.00','',NULL),
('16','1','12','2026-06-24 10:19:23','2026-06-24 10:20:15',NULL,NULL,'5000.00',NULL,'closed','2026-06-24 10:19:23','5000.00','0.00','1','2026-06-24 09:20:29','2026-06-24 10:20:15','1','0.00','cash','created','14',NULL,NULL,'0','0.00','',NULL),
('17','5','13','2026-06-25 08:22:14','2026-06-25 08:22:23',NULL,NULL,'5000.00',NULL,'closed','2026-06-25 08:22:14','5000.00','0.00','1','2026-06-25 07:22:23','2026-06-25 08:22:23','1','0.00','cash','created','17',NULL,NULL,'0','0.00','',NULL),
('18','2','12','2026-06-25 09:36:33','2026-06-25 09:36:44',NULL,NULL,'5000.00',NULL,'closed','2026-06-25 09:36:33','5000.00','0.00','1','2026-06-25 08:36:45','2026-06-25 09:36:44','1','0.00','cash','created','18',NULL,NULL,'0','0.00','',NULL),
('19','5','13','2026-06-30 09:04:54','2026-06-30 09:04:59',NULL,NULL,'5000.00',NULL,'closed','2026-06-30 09:04:55','5000.00','0.00','1','2026-06-30 08:05:00','2026-06-30 09:04:59','5','0.00','cash','created','20',NULL,NULL,'0','0.00','',NULL),
('20','5','13','2026-06-30 09:07:53','2026-06-30 09:08:36',NULL,NULL,'30000.00',NULL,'closed','2026-06-30 09:07:53','0.00','30000.00','0',NULL,'2026-06-30 09:08:36','5','0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00','',NULL),
('21','5','9','2026-06-30 09:07:59',NULL,NULL,NULL,NULL,NULL,'open','2026-06-30 09:07:59','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL,NULL),
('22','6','14','2026-06-30 07:11:19','2026-07-01 07:11:19',NULL,NULL,NULL,'عقد تجريبي للتحقق من أمان النظام ورصد الأخطاء','open','2026-06-30 10:11:19','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL,NULL),
('23','7','15','2026-06-30 07:13:21','2026-07-01 07:13:21',NULL,NULL,NULL,'عقد تجريبي للتحقق من أمان النظام ورصد الأخطاء','open','2026-06-30 10:13:21','500.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL,NULL),
('24','8','16','2026-06-30 07:13:38','2026-07-01 07:13:38',NULL,NULL,NULL,'عقد تجريبي للتحقق من أمان النظام ورصد الأخطاء','open','2026-06-30 10:13:38','500.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL,NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `shift_closings` VALUES 
('2','5','2026-06-29','0.00','100.00','100.00','0.00','0.00','Security test closing [cash_total=0, transfer_total=0]','2026-06-29 08:55:46');

-- ----------------------------
-- Table: `system_errors`
-- ----------------------------
DROP TABLE IF EXISTS `system_errors`;
CREATE TABLE `system_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `api` varchar(255) NOT NULL,
  `error_message` text NOT NULL,
  `stack_trace` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_errors` VALUES 
('1','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('2','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('3','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('4','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('5','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('6','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('7','13','/index.php?path=attendance/checkin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:21'),
('8','14','/index.php?path=attendance/admin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('9','14','/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('10','14','/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('11','14','/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('12','14','/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('13','14','/index.php?path=attendance/admin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('14','14','/index.php?path=attendance/admin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:21'),
('15','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('16','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('17','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('18','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('19','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('20','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('21','15','/index.php?path=attendance/checkin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','2026-06-30 10:13:39'),
('22','16','/index.php?path=attendance/admin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('23','16','/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('24','16','/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('25','16','/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('26','16','/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('27','16','/index.php?path=attendance/admin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39'),
('28','16','/index.php?path=attendance/admin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','2026-06-30 10:13:39');

-- ----------------------------
-- Table: `user_sessions`
-- ----------------------------
DROP TABLE IF EXISTS `user_sessions`;
CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `device_name` varchar(255) DEFAULT NULL,
  `device_platform` varchar(100) DEFAULT NULL,
  `last_activity` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_token_hash` (`token_hash`),
  KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `user_sessions` VALUES 
('1','1','877c706c2f571b006db5c284b42950a4623f7c3c0eae7fa6d1e8999f6e67a3bb','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:11:18','2026-07-01 10:11:18','2026-06-30 10:11:18'),
('2','7','a080496d80d38e5697105f8c47c2bda0276114b8b847427b2622c6341c0156b8','Samsung S22 Ultra','Android','2026-06-30 10:11:19','2026-07-01 10:11:18','2026-06-30 10:11:18'),
('3','1','dd2af5d82c4ac91b46bf80673066eca109f08294edfb5442bf5bd2209010843a','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:12:29','2026-07-01 10:12:29','2026-06-30 10:12:29'),
('4','9','83f06007c321b25c9ebc7fee6ba3b2fbb8baad6f1ab1bd216a6ca6f2abfc9041','Samsung S22 Ultra','Android','2026-06-30 10:12:30','2026-07-01 10:12:30','2026-06-30 10:12:30'),
('5','1','300be1d20c7b39e0d48c9edca6a4cae7c1bfc659c0cbb77e7d27048fe6e7344e','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:10','2026-07-01 10:13:10','2026-06-30 10:13:10'),
('6','11','01fadbffb761f88c7fe09f725f92c6d9eb25caba3a2746776261706555f7cc5c','Samsung S22 Ultra','Android','2026-06-30 10:13:10','2026-07-01 10:13:10','2026-06-30 10:13:10'),
('7','1','75e6a0c27315742d4e8334b1067c608c3fa3470c08ddf3730770736ea43b6e6e','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:21','2026-07-01 10:13:20','2026-06-30 10:13:20'),
('8','13','8bded326ba17dcc6c7eac1753568fb80117d6b0ac274b16c7f7c2d505ec26f95','Samsung S22 Ultra','Android','2026-06-30 10:13:21','2026-07-01 10:13:20','2026-06-30 10:13:20'),
('9','14','09bd576650c41e24b21349ac755da328269904d89cec7ca94c13e007f06bfe75','Manager iPad Pro','iOS','2026-06-30 10:13:21','2026-07-01 10:13:21','2026-06-30 10:13:21'),
('10','1','7debf7657406da02dd3db2cdae0ed4685b4f06587f1b1ea0a8e0dcae45185267','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:39','2026-07-01 10:13:38','2026-06-30 10:13:38'),
('11','15','3289733a5dc3560099934770f4b75ae80b77ece70a3971ae3e3c2869e169c082','Samsung S22 Ultra','Android','2026-06-30 10:13:39','2026-07-01 10:13:38','2026-06-30 10:13:38'),
('12','16','83f39c36b1eb25845864de43b4b06be11ba87b0e4f386da40215889e5def7a9d','Manager iPad Pro','iOS','2026-06-30 10:13:39','2026-07-01 10:13:39','2026-06-30 10:13:39');

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
  `screen_permissions_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'per-user screen permission overrides' CHECK (json_valid(`screen_permissions_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` VALUES 
('1','admin','$2y$10$xTezEffLcJPxj4HgnZrcI.RHduM6yZZikxaY6JMcaRgTtzq/a9kEy','admin','2026-01-11 10:30:53','1',NULL,NULL,NULL,NULL,NULL),
('5','صالح','12345','employee','2026-05-24 08:53:40','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('6','محمد','12345','employee','2026-06-29 08:40:50','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('7','test_employee_1782803478','$2y$10$Gdkb0toTWAoGw.OjWvZgteLP9iCY6kwsgm.WfCIs1E9jU8W929xDa','employee','2026-06-30 10:11:18','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('8','test_manager_1782803478','$2y$10$47JauVXhKN8QyyPh92qPROusXo9b1AU7u/OXC.6lmk2pjNFpN39va','','2026-06-30 10:11:18','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}',NULL),
('9','test_employee_1782803549','$2y$10$iajz0vTqid2Z2nMmXVHAreDNusEK1UVaQpp6JAGN9OFlw8MOt2whu','employee','2026-06-30 10:12:29','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('10','test_manager_1782803549','$2y$10$o8fDb00pVTkrtBOwQQrm3.14IqzB2vobsis9l9tt/ODLB.aGWA9Lu','','2026-06-30 10:12:29','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}',NULL),
('11','test_employee_1782803590','$2y$10$bB0UXoHv7224WUT9AtxnI.Lyc5V78K7jbNWwPP0RwIvwBNUaUmhXa','employee','2026-06-30 10:13:10','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('12','test_manager_1782803590','$2y$10$2BiIYQadcwPD0iz39EDufuJI/yQzBeueK8cbEUROLO2gr/hTv0Acm','','2026-06-30 10:13:10','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}',NULL),
('13','test_employee_1782803600','$2y$10$D0LT8v6mnTJ6xEjtQwn6f.1BnzE3M7SlMqOobcyRfyKKrg8UbNkva','employee','2026-06-30 10:13:20','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('14','test_manager_1782803600','$2y$10$n2CT3YZFH8OhSmUI/gt2yOvELVe7l/n3hWV6wEsl9JwMPuEwN.BIm','','2026-06-30 10:13:20','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}',NULL),
('15','test_employee_1782803618','$2y$10$KUqaJZ5P48nPJzHkvkWDdeVr0tqj.SFsAcA3dnjchRerRMlrX9sge','employee','2026-06-30 10:13:38','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":false,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}',NULL),
('16','test_manager_1782803618','$2y$10$HVa93RtGR30LWxpnGXFHW.EzJvvHvobkvsDX90WPbDKyxwGxVE9aq','','2026-06-30 10:13:38','1',NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":true,\"hr\":true,\"attendance\":true,\"shifts\":true,\"backup\":true,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":true}}',NULL);

SET FOREIGN_KEY_CHECKS=1;
