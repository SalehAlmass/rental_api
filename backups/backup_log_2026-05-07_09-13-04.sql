-- Rental System Backup
-- Type: log
-- Generated at: 2026-05-07 09:13:04

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

INSERT INTO `app_settings` VALUES 
('depreciation.processed_month','2026-05','2026-05-07 08:48:31'),
('schema.depreciation.version','2026_05_depreciation_v1','2026-05-07 08:48:31'),
('schema.financials.version','2026_05_perf_indexes_v1','2026-05-07 08:48:31');
INSERT INTO `audit_logs` VALUES 
('1',NULL,'receipt_skipped_on_close','rent','1','{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-29 07:31:10\"}','2026-04-29 08:31:10'),
('2',NULL,'rent_closed','rent','1','{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.22,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-29 07:31:10\",\"closed_by_user_id\":1}','2026-04-29 08:31:10'),
('3',NULL,'payment_created','payment','1','{\"rent_id\":1,\"amount\":1333.33,\"type\":\"in\"}','2026-04-29 08:33:13'),
('4',NULL,'receipt_skipped_on_close','rent','2','{\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closed_at\":\"2026-04-30 07:03:36\"}','2026-04-30 08:03:36'),
('5',NULL,'rent_closed','rent','2','{\"total_amount\":1333.33,\"gross_total_amount\":1333.33,\"discount_amount\":0,\"discount_note\":null,\"hours\":0.3,\"apply_special_pricing\":false,\"pricing_rule_code\":\"daily_default\",\"closing_paid_amount\":1333,\"closing_payment_method\":\"cash\",\"closing_payment_status\":\"not_created\",\"closing_payment_id\":null,\"closed_at\":\"2026-04-30 07:03:36\",\"closed_by_user_id\":1}','2026-04-30 08:03:36'),
('6',NULL,'payment_created','payment','2','{\"rent_id\":2,\"amount\":1333,\"type\":\"in\"}','2026-04-30 08:03:36');
INSERT INTO `clients` VALUES 
('1','dfs','','785212131','','0','0.00',NULL,'2026-04-29 07:50:51');
INSERT INTO `equipment` VALUES 
('1','سييب','لبي','123','available','2000.00','0.00','12.00','60','2026-04-29','0.00','0.00','0.00','12.00','365','0.00','0.00','2026-05',NULL,'2026-04-29 08:16:44','1','2000'),
('2','لبييب','الا','122121','rented','10000.00','0.00','0.00','60','2026-04-30','0.00','0.00','0.00','0.00','365','0.00','0.00','2026-05',NULL,'2026-04-30 07:56:21','1','10000'),
('3','asads 1','asd','asd 1','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('4','asads 2','asd','asd 2','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('5','asads 3','asd','asd 3','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('6','asads 4','asd','asd 4','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500'),
('7','asads 5','asd','asd 5','available','2500.00','0.00','0.00','60','2026-05-07','0.00','0.00','0.00','0.00','365','0.00','0.00',NULL,NULL,'2026-05-07 09:10:48','1','2500');
INSERT INTO `equipment_depreciation_entries` VALUES 
('1','1','2026-04','0.00','0.00',NULL,'2026-04-29 08:16:44'),
('2','2','2026-04','0.00','0.00',NULL,'2026-04-30 07:56:21'),
('3','1','2026-05','0.00','0.00',NULL,'2026-05-07 08:22:16'),
('4','2','2026-05','0.00','0.00',NULL,'2026-05-07 08:22:16');
INSERT INTO `payments` VALUES 
('1','1','1','1','in','1333.33','cash',NULL,NULL,'2026-04-29 08:33:13','0',NULL,NULL,'rent_1_1777440792841000',NULL),
('2','1','2','1','in','1333.00','cash',NULL,'سند قبض بعد الإغلاق السريع','2026-04-30 08:03:36','0',NULL,NULL,'quick_close_receipt_2_1777525416336',NULL);
INSERT INTO `rents` VALUES 
('1','1','1','2026-04-29 08:18:09','2026-04-29 08:31:10','0.22','2000.00','1333.33',NULL,'closed','2026-04-29 08:18:09','1333.33','0.00','1','2026-04-29 07:33:13','2026-04-29 07:31:10','1','1333.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL),
('2','1','1','2026-04-30 07:45:24','2026-04-30 08:03:36','0.30','2000.00','1333.33',NULL,'closed','2026-04-30 07:45:25','1333.00','0.33','0',NULL,'2026-04-30 07:03:36','1','1333.00','cash','not_created',NULL,'daily_default','الاحتساب الافتراضي: أقل من 3 ساعات = ثلثي السعر اليومي','0','0.00',NULL),
('3','1','2','2026-04-30 07:56:36',NULL,NULL,'10000.00','0.00',NULL,'open','2026-04-30 07:56:36','0.00','0.00','0',NULL,NULL,NULL,'0.00',NULL,NULL,NULL,NULL,NULL,'0','0.00',NULL);
INSERT INTO `users` VALUES 
('1','admin','admin123','admin','2026-01-11 10:30:53','1',NULL,NULL,NULL,NULL);

SET FOREIGN_KEY_CHECKS=1;
