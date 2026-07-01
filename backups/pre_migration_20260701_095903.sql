
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `api_performance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_performance_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `api_name` varchar(255) NOT NULL,
  `execution_time_ms` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `method` varchar(10) DEFAULT NULL,
  `status_code` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_perf_created_at` (`created_at`),
  KEY `idx_perf_api_name` (`api_name`),
  KEY `idx_perf_slow` (`execution_time_ms`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `api_performance_logs` WRITE;
/*!40000 ALTER TABLE `api_performance_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `api_performance_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_settings` (
  `setting_key` varchar(80) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `app_settings` WRITE;
/*!40000 ALTER TABLE `app_settings` DISABLE KEYS */;
INSERT INTO `app_settings` VALUES ('ALLOW_TEST_DATA_CLEANUP','0','2026-07-01 09:32:03'),('backup_custom_path_1','C:/xampp/htdocs/alkhair/rental_api/backups/test_path_1','2026-06-24 10:46:25'),('backup_custom_path_2','E:\\','2026-06-24 11:04:13'),('depreciation.processed_month','2026-05','2026-05-07 08:48:31'),('depreciation_last_checked','1782888570','2026-07-01 09:49:30'),('schema.depreciation.version','2026_05_depreciation_v1','2026-05-07 08:48:31'),('schema.financials.version','2026_05_perf_indexes_v1','2026-05-07 08:48:31'),('session_migration_complete','0','2026-06-30 10:40:58'),('_permissions_seeded','1','2026-06-25 08:25:14');
/*!40000 ALTER TABLE `app_settings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `attendance_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_user_ts` (`user_id`,`ts`),
  KEY `idx_attendance_type` (`type`),
  KEY `idx_attendance_logs_ts` (`ts`),
  KEY `idx_attendance_user_ts` (`user_id`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=5003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `attendance_logs` WRITE;
/*!40000 ALTER TABLE `attendance_logs` DISABLE KEYS */;
INSERT INTO `attendance_logs` VALUES (5001,1,'in','2026-07-01 08:59:13','manual','morning',NULL,NULL,164,NULL,NULL,NULL,'Arabian Standard Time','web','8.1.0','Browser User','2026-07-01 09:59:13','2026-07-01 06:59:13',0),(5002,5,'in','2026-07-01 09:12:49','manual','morning',NULL,NULL,177,NULL,NULL,NULL,'Arabian Standard Time','web','8.1.0','Browser User','2026-07-01 10:12:49','2026-07-01 07:12:49',0);
/*!40000 ALTER TABLE `attendance_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_user_id` (`user_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_entity_id` (`entity`,`entity_id`),
  KEY `idx_audit_logs_entity` (`entity`),
  KEY `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=50065 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (50001,1,'TEST_DATA_CLEANUP','system',NULL,NULL,NULL,'{\"deleted_records_count\":{\"system_alerts\":0,\"attendance_logs\":5000,\"shift_closings\":0,\"payments\":100000,\"rent_items\":0,\"rents\":50000,\"collection_followups\":0,\"equipment_maintenance\":0,\"equipment_depreciation_entries\":0,\"equipment\":500,\"clients\":10000,\"users\":14,\"audit_logs\":50000},\"backup_reference\":\"backup_full_auto_2026-07-01_08-31-35.sql\",\"user_id\":1,\"ip\":\"::1\",\"device\":\"Unknown Device\",\"timestamp\":\"2026-07-01 08:32:03\"}','2026-07-01 09:32:03','::1','Unknown Device',0),(50002,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":55,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 09:34:38','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50003,NULL,'session_created','session',55,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 09:34:38\"}','2026-07-01 09:34:38','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50004,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":56,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 09:42:00','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50005,NULL,'session_created','session',56,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 09:42:00\"}','2026-07-01 09:42:00','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50006,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":57,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 09:49:30','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50007,NULL,'session_created','session',57,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 09:49:30\"}','2026-07-01 09:49:30','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50008,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":58,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 09:50:55','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50009,NULL,'session_created','session',58,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 09:50:55\"}','2026-07-01 09:50:55','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50010,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":59,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 09:58:24','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50011,NULL,'session_created','session',59,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 09:58:24\"}','2026-07-01 09:58:24','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50012,1,'backup_created','backup',7,NULL,NULL,'{\"file\":\"backup_full_2026-07-01_09-58-24.sql\",\"size\":72346,\"type\":\"full\"}','2026-07-01 09:58:24','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50013,1,'attendance_late','attendance',5001,NULL,NULL,'{\"late_minutes\":164,\"ts\":\"2026-07-01 08:59:13\"}','2026-07-01 09:59:13','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50014,1,'attendance_check_in','attendance',5001,NULL,NULL,'{\"ts\":\"2026-07-01 08:59:13\",\"device_timezone\":\"Arabian Standard Time\",\"device_platform\":\"web\"}','2026-07-01 09:59:13','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50015,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":60,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:01:21','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50016,NULL,'session_created','session',60,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:01:21\"}','2026-07-01 10:01:21','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50017,5,'rent_closed','rent',50002,'{\"total_amount\":3500}',NULL,NULL,'2026-07-01 10:01:49','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50018,5,'payment_created','payment',100001,'{\"rent_id\":50002,\"amount\":3500,\"type\":\"in\"}',NULL,NULL,'2026-07-01 10:01:49','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50019,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":61,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:06:11','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50020,NULL,'session_created','session',61,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:06:11\"}','2026-07-01 10:06:11','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50021,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":62,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:07:30','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50022,NULL,'session_created','session',62,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:07:30\"}','2026-07-01 10:07:30','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50023,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":63,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:09:01','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50024,NULL,'session_created','session',63,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:09:01\"}','2026-07-01 10:09:01','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50025,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":64,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:10:40','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50026,NULL,'session_created','session',64,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:10:40\"}','2026-07-01 10:10:40','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50027,1,'user_updated','user',5,'{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false}}}','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false,\"attendance_dashboard\":false,\"attendance_statistics\":false,\"attendance_reports\":false,\"attendance_breaks\":false,\"attendance_edit\":true,\"attendance_manage\":false}}}',NULL,'2026-07-01 10:12:15','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50028,1,'user_updated','user',6,'{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false}}}','{\"permissions\":{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false,\"attendance_dashboard\":false,\"attendance_statistics\":false,\"attendance_reports\":false,\"attendance_breaks\":false,\"attendance_edit\":false,\"attendance_manage\":false}}}',NULL,'2026-07-01 10:12:23','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50029,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":65,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:12:34','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50030,NULL,'session_created','session',65,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:12:34\"}','2026-07-01 10:12:34','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50031,5,'attendance_late','attendance',5002,NULL,NULL,'{\"late_minutes\":177,\"ts\":\"2026-07-01 09:12:49\"}','2026-07-01 10:12:49','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50032,5,'attendance_check_in','attendance',5002,NULL,NULL,'{\"ts\":\"2026-07-01 09:12:49\",\"device_timezone\":\"Arabian Standard Time\",\"device_platform\":\"web\"}','2026-07-01 10:12:49','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50033,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":66,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:15:56','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50034,NULL,'session_created','session',66,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:15:56\"}','2026-07-01 10:15:56','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50035,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":67,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:16:44','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50036,NULL,'session_created','session',67,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:16:44\"}','2026-07-01 10:16:44','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50037,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":68,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:18:45','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50038,NULL,'session_created','session',68,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:18:45\"}','2026-07-01 10:18:45','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50039,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":69,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:20:55','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50040,NULL,'session_created','session',69,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:20:55\"}','2026-07-01 10:20:55','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50041,5,'payment_created','payment',100002,'{\"rent_id\":50003,\"amount\":2000,\"type\":\"in\"}',NULL,NULL,'2026-07-01 10:21:43','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50042,5,'rent_closed','rent',50003,'{\"total_amount\":3500}',NULL,NULL,'2026-07-01 10:22:09','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50043,5,'payment_created','payment',100003,'{\"rent_id\":50003,\"amount\":750,\"type\":\"in\"}',NULL,NULL,'2026-07-01 10:22:09','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50044,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":70,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:29:29','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50045,NULL,'session_created','session',70,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:29:29\"}','2026-07-01 10:29:29','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50046,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":71,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:30:39','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50047,NULL,'session_created','session',71,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:30:39\"}','2026-07-01 10:30:39','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50048,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":72,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:30:58','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50049,NULL,'session_created','session',72,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:30:58\"}','2026-07-01 10:30:58','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50050,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":73,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:34:46','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50051,NULL,'session_created','session',73,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:34:46\"}','2026-07-01 10:34:46','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50052,NULL,'login_success','user',5,NULL,NULL,'{\"session_id\":74,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:36:01','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50053,NULL,'session_created','session',74,NULL,NULL,'{\"user_id\":5,\"expires_at\":\"2026-07-02 10:36:01\"}','2026-07-01 10:36:01','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50054,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":75,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:36:22','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50055,NULL,'session_created','session',75,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:36:22\"}','2026-07-01 10:36:22','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50056,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":76,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 10:40:08','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50057,NULL,'session_created','session',76,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 10:40:08\"}','2026-07-01 10:40:08','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50058,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":77,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 11:28:36','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50059,NULL,'session_created','session',77,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 11:28:36\"}','2026-07-01 11:28:36','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50060,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":78,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 11:48:15','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50061,NULL,'session_created','session',78,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 11:48:15\"}','2026-07-01 11:48:15','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50062,1,'rent_closed','rent',50004,'{\"total_amount\":20000}',NULL,NULL,'2026-07-01 11:49:16','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50063,NULL,'login_success','user',1,NULL,NULL,'{\"session_id\":79,\"device_name\":\"\",\"device_platform\":\"\"}','2026-07-01 11:52:31','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0),(50064,NULL,'session_created','session',79,NULL,NULL,'{\"user_id\":1,\"expires_at\":\"2026-07-02 11:52:30\"}','2026-07-01 11:52:31','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',0);
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `backup_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `backup_logs` WRITE;
/*!40000 ALTER TABLE `backup_logs` DISABLE KEYS */;
INSERT INTO `backup_logs` VALUES (1,1,'backup_full_2026-06-30_10-13-21.sql',0,'failed','2026-06-30 10:13:21'),(2,1,'backup_full_2026-06-30_10-13-39.sql',85145,'success','2026-06-30 10:13:39'),(3,1,'backup_full_2026-06-30_10-13-51.sql',96800,'success','2026-06-30 10:13:51'),(4,NULL,'backup_full_cron_2026-06-30_10-16-43.sql',100114,'success','2026-06-30 10:16:43'),(5,1,'backup_full_2026-06-30_10-40-58.sql',110299,'success','2026-06-30 10:40:58'),(6,1,'backup_full_auto_2026-07-01_08-31-35.sql',33116735,'success','2026-07-01 09:31:36'),(7,1,'backup_full_2026-07-01_09-58-24.sql',72346,'success','2026-07-01 09:58:24');
/*!40000 ALTER TABLE `backup_logs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `national_id` varchar(50) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `is_frozen` tinyint(1) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_clients_phone` (`phone`),
  KEY `idx_clients_name` (`name`),
  KEY `idx_clients_is_frozen` (`is_frozen`),
  KEY `idx_clients_created_at` (`created_at`),
  KEY `idx_clients_national_id` (`national_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (10003,'صالح الماس','','777359678','',0,NULL,'2026-07-01 06:35:36',0),(10004,'محمد احمد','','777252532','',0,NULL,'2026-07-01 08:34:34',0);
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `collection_followups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_collection_followups_rent_id` (`rent_id`),
  KEY `idx_collection_followups_client_id` (`client_id`),
  KEY `idx_collection_followups_next_followup_at` (`next_followup_at`),
  KEY `idx_followups_next_at` (`next_followup_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `collection_followups` WRITE;
/*!40000 ALTER TABLE `collection_followups` DISABLE KEYS */;
/*!40000 ALTER TABLE `collection_followups` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_equipment_active_status` (`is_active`,`status`),
  KEY `idx_equipment_status` (`status`),
  KEY `idx_equipment_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=507 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (501,'دريل 1','2000',NULL,'rented',3500.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 06:36:06',1,3500,0),(502,'دريل 2','2000',NULL,'available',3500.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 06:36:06',1,3500,0),(503,'دريل 3','2000',NULL,'available',3500.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 06:36:06',1,3500,0),(504,'دريل 4','2000',NULL,'rented',3500.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 06:36:06',1,3500,0),(505,'دريل 5','2000',NULL,'rented',3500.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 06:36:06',1,3500,0),(506,'ماطور','',NULL,'rented',20000.00,0.00,0.00,60,'2026-07-01',0.00,0.00,0.00,0.00,365,0.00,0.00,NULL,NULL,'2026-07-01 08:34:06',1,20000,0);
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `equipment_depreciation_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_equipment_dep_month_type` (`equipment_id`,`depreciation_month`,`depreciation_type`),
  KEY `idx_equipment_dep_month` (`depreciation_month`),
  KEY `idx_equipment_dep_entries_equipment_id` (`equipment_id`),
  KEY `idx_equipment_dep_entries_depreciation_month` (`depreciation_month`),
  KEY `idx_equipment_dep_entries_depreciation_type` (`depreciation_type`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `equipment_depreciation_entries` WRITE;
/*!40000 ALTER TABLE `equipment_depreciation_entries` DISABLE KEYS */;
INSERT INTO `equipment_depreciation_entries` VALUES (34,14,'2026-07','accounting',2500.00,2500.00,5000.00,147500.00,145000.00,2500.00,0.00,100031,'2026-07-01 08:48:11',0),(35,15,'2026-07','accounting',2500.00,2500.00,5000.00,147500.00,145000.00,2500.00,0.00,100032,'2026-07-01 08:48:11',0),(36,16,'2026-07','accounting',2500.00,2500.00,5000.00,147500.00,145000.00,2500.00,0.00,100033,'2026-07-01 08:48:11',0),(37,17,'2026-07','accounting',2500.00,2500.00,5000.00,147500.00,145000.00,2500.00,0.00,100034,'2026-07-01 08:48:11',0),(38,18,'2026-07','accounting',2500.00,2500.00,5000.00,147500.00,145000.00,2500.00,0.00,100035,'2026-07-01 08:48:11',0);
/*!40000 ALTER TABLE `equipment_depreciation_entries` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `equipment_maintenance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_id` int(11) NOT NULL,
  `maintenance_date` date NOT NULL,
  `details` text DEFAULT NULL,
  `cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_maint_equipment` (`equipment_id`),
  CONSTRAINT `fk_maint_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `equipment_maintenance` WRITE;
/*!40000 ALTER TABLE `equipment_maintenance` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment_maintenance` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
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
) ENGINE=InnoDB AUTO_INCREMENT=100004 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (100001,10003,50002,5,'in',3500.00,'cash',NULL,'سند قبض بعد الإغلاق السريع','2026-07-01 07:01:49',0,NULL,NULL,'quick_close_receipt_50002_1782889309353',NULL,0),(100002,10003,50003,5,'in',2000.00,'cash',NULL,NULL,'2026-07-01 07:21:43',0,NULL,NULL,'rent_50003_1782890503505000',NULL,0),(100003,10003,50003,5,'in',750.00,'cash',NULL,'سند قبض بعد إغلاق العقد','2026-07-01 07:22:09',0,NULL,NULL,'rent_close_receipt_50003_1782890529573',NULL,0);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rent_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `rent_id` (`rent_id`),
  KEY `equipment_id` (`equipment_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rent_items` WRITE;
/*!40000 ALTER TABLE `rent_items` DISABLE KEYS */;
INSERT INTO `rent_items` VALUES (1,50001,505,3500.00,NULL,'replaced','2026-07-01 09:36:14','2026-07-01 08:58:41',501,0),(2,50001,504,3500.00,NULL,'open','2026-07-01 09:36:14',NULL,NULL,0),(3,50002,503,3500.00,NULL,'closed','2026-07-01 09:38:16','2026-07-01 10:01:49',NULL,0),(4,50001,501,3500.00,NULL,'open','2026-07-01 08:58:41',NULL,NULL,0),(5,50003,505,3500.00,NULL,'closed','2026-07-01 10:21:26','2026-07-01 10:22:09',NULL,0),(6,50004,506,20000.00,NULL,'closed','2026-07-01 11:48:43','2026-07-01 11:49:16',NULL,0),(7,50005,505,3500.00,NULL,'open','2026-07-01 11:48:51',NULL,NULL,0),(8,50006,506,20000.00,NULL,'open','2026-07-01 11:49:20',NULL,NULL,0);
/*!40000 ALTER TABLE `rent_items` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_rents_client` (`client_id`),
  KEY `fk_rents_equipment` (`equipment_id`),
  KEY `idx_rents_status_start` (`status`,`start_datetime`),
  KEY `idx_rents_client_status` (`client_id`,`status`),
  KEY `idx_rents_equipment_status` (`equipment_id`,`status`),
  KEY `idx_rents_remaining` (`remaining_amount`),
  KEY `idx_rents_created_at` (`created_at`),
  KEY `idx_rents_status_created` (`status`,`created_at`),
  KEY `idx_rents_closed_by_user_id` (`closed_by_user_id`),
  KEY `idx_rents_archived_at` (`archived_at`),
  KEY `idx_rents_client_id` (`client_id`),
  KEY `idx_rents_status` (`status`),
  CONSTRAINT `fk_rents_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_rents_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=50007 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rents` WRITE;
/*!40000 ALTER TABLE `rents` DISABLE KEYS */;
INSERT INTO `rents` VALUES (50001,10003,501,'2026-07-01 09:36:14',NULL,NULL,NULL,NULL,NULL,'open','2026-07-01 06:36:15',0.00,0.00,0,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL,NULL,0,0.00,NULL,NULL,0),(50002,10003,503,'2026-07-01 09:38:16','2026-07-01 10:01:49',NULL,NULL,3500.00,NULL,'closed','2026-07-01 06:38:16',3500.00,0.00,1,'2026-07-01 09:01:49','2026-07-01 10:01:49',5,0.00,'cash','created',100001,NULL,NULL,0,0.00,'',NULL,0),(50003,10003,505,'2026-07-01 10:21:26','2026-07-01 10:22:09',NULL,NULL,3500.00,NULL,'closed','2026-07-01 07:21:26',2750.00,0.00,1,'2026-07-01 09:22:09','2026-07-01 10:22:09',5,0.00,'cash','created',100003,NULL,NULL,0,750.00,'نصف المتبقي',NULL,0),(50004,10003,506,'2026-07-01 11:48:43','2026-07-01 11:49:16',NULL,NULL,20000.00,NULL,'closed','2026-07-01 08:48:44',0.00,20000.00,0,NULL,'2026-07-01 11:49:16',1,0.00,NULL,NULL,NULL,NULL,NULL,0,0.00,'',NULL,0),(50005,10003,505,'2026-07-01 11:48:51',NULL,NULL,NULL,NULL,NULL,'open','2026-07-01 08:48:51',0.00,0.00,0,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL,NULL,0,0.00,NULL,NULL,0),(50006,10004,506,'2026-07-01 11:49:20',NULL,NULL,NULL,NULL,NULL,'open','2026-07-01 08:49:21',0.00,0.00,0,NULL,NULL,NULL,0.00,NULL,NULL,NULL,NULL,NULL,0,0.00,NULL,NULL,0);
/*!40000 ALTER TABLE `rents` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `shift_closings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_shift_user_date` (`user_id`,`shift_date`),
  KEY `idx_shift_closings_user_id` (`user_id`),
  KEY `idx_shift_closings_shift_date` (`shift_date`),
  CONSTRAINT `fk_shift_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `shift_closings` WRITE;
/*!40000 ALTER TABLE `shift_closings` DISABLE KEYS */;
INSERT INTO `shift_closings` VALUES (2,5,'2026-06-29',0.00,100.00,100.00,0.00,0.00,'Security test closing [cash_total=0, transfer_total=0]','2026-06-29 05:55:46',0);
/*!40000 ALTER TABLE `shift_closings` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `system_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_alerts` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL DEFAULT 'system',
  `category` varchar(50) DEFAULT NULL,
  `severity` enum('info','low','medium','high','critical','warning','success') NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `action_hint` varchar(255) DEFAULT NULL,
  `entity_type` varchar(100) DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `source_api` varchar(100) DEFAULT NULL,
  `status` enum('open','resolved','ignored') NOT NULL DEFAULT 'open',
  `read_at` datetime DEFAULT NULL,
  `dedup_key` varchar(192) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alerts_dedup` (`dedup_key`),
  KEY `idx_alerts_severity` (`severity`),
  KEY `idx_alerts_status` (`status`),
  KEY `idx_alerts_created_at` (`created_at`),
  KEY `idx_alerts_entity` (`entity_type`,`entity_id`),
  KEY `idx_alerts_type` (`alert_type`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `system_alerts` WRITE;
/*!40000 ALTER TABLE `system_alerts` DISABLE KEYS */;
INSERT INTO `system_alerts` VALUES (1,NULL,'system','system','critical','اختبار الإشعارات','هذا إشعار تجريبي للتحقق من عمل النظام','تأكيد عمل الإشعار',NULL,NULL,NULL,'open','2026-06-30 12:12:22',NULL,NULL,NULL,NULL,NULL,'2026-06-30 12:12:02','2026-06-30 12:12:22',0);
/*!40000 ALTER TABLE `system_alerts` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `system_errors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `api` varchar(255) NOT NULL,
  `error_message` text NOT NULL,
  `stack_trace` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `system_errors` WRITE;
/*!40000 ALTER TABLE `system_errors` DISABLE KEYS */;
INSERT INTO `system_errors` VALUES (1,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(2,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(3,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(4,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(5,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(6,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(7,13,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(8,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(9,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(10,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(11,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(12,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(13,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(14,14,'/index.php?path=attendance/admin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:21'),(15,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(16,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(17,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(18,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(19,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(20,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(21,15,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(22,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(23,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(24,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(25,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(26,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(27,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(28,16,'/index.php?path=attendance/admin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:39'),(29,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(30,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(31,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(32,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(33,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(34,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(35,17,'/index.php?path=attendance/checkin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"POST\",\"get\":{\"path\":\"attendance\\/checkin\"},\"post\":{\"shift\":\"morning\",\"device_timezone\":\"Asia\\/Riyadh\",\"device_platform\":\"Android\",\"device_app_version\":\"1.5.0\",\"device_name\":\"Samsung S22 Ultra\"}}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(36,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(37,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(38,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(39,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(40,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(41,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(42,18,'/index.php?path=attendance/admin','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/admin\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:13:51'),(43,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(44,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(45,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(46,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(47,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(48,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(49,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:16:34'),(50,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_WEEKLY_HOLIDAY_DOW already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 79',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(51,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_MORNING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 80',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(52,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_MORNING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 81',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(53,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_EVENING_START already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 82',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(54,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_EVENING_END already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 83',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(55,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_GRACE_MINUTES already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 84',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(56,1,'/alkhair/rental_api/index.php?path=attendance/me&month=2026-06','PHP Error: Constant HR_WORKDAY_HOURS already defined in C:\\xampp\\htdocs\\alkhair\\rental_api\\attendance.php on line 85',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"attendance\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:19:33'),(57,1,'/alkhair/rental_api/index.php?path=reports/employee-performance','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'r.created_by\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\reports.php(771): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(37): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"reports\\/employee-performance\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:57:28'),(58,1,'/alkhair/rental_api/index.php?path=reports/employee-performance','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'r.created_by\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\reports.php(771): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(37): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"reports\\/employee-performance\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 10:57:33'),(59,1,'/alkhair/rental_api/index.php?path=reports/employee-performance','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'r.created_by\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\reports.php(771): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(37): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"reports\\/employee-performance\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 11:20:16'),(60,1,'/alkhair/rental_api/index.php?path=reports/employee-performance&from_date=2026-01-01&to_date=2026-06-30','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'r.created_by\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\reports.php(771): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(37): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"reports\\/employee-performance\",\"from_date\":\"2026-01-01\",\"to_date\":\"2026-06-30\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 11:21:03'),(61,1,'/alkhair/rental_api/index.php?path=reports/employee-performance&from_date=2026-01-01&to_date=2026-06-30','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'r.created_by\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\reports.php(771): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(37): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"reports\\/employee-performance\",\"from_date\":\"2026-01-01\",\"to_date\":\"2026-06-30\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 11:22:58'),(62,1,'/alkhair/rental_api/index.php?path=payroll/me&month=2026-06','PHP Error: Undefined array key \"uid\" in C:\\xampp\\htdocs\\alkhair\\rental_api\\payroll.php on line 179',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"payroll\\/me\",\"month\":\"2026-06\"},\"post\":[]}','resolved','2026-06-30 11:44:37','2026-06-30 11:33:06'),(63,1,'/alkhair/rental_api/index.php?path=system-health/errors','PHP Error: Undefined array key \"open\" in C:\\xampp\\htdocs\\alkhair\\rental_api\\system.php on line 326',NULL,'{\"method\":\"GET\",\"get\":{\"path\":\"system-health\\/errors\"},\"post\":[]}','resolved','2026-06-30 11:48:41','2026-06-30 11:47:59'),(64,1,'/alkhair/rental_api/index.php?path=notifications','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'n.user_id\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(88): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\"},\"post\":[]}','open',NULL,'2026-06-30 12:06:55'),(65,1,'/alkhair/rental_api/index.php?path=notifications','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'n.user_id\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(88): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\"},\"post\":[]}','open',NULL,'2026-06-30 12:11:00'),(66,1,'/alkhair/rental_api/index.php?path=notifications','SQLSTATE[42S22]: Column not found: 1054 Unknown column \'n.user_id\' in \'on clause\'','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(88): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\"},\"post\":[]}','open',NULL,'2026-06-30 12:11:07'),(67,1,'/alkhair/rental_api/index.php?path=notifications&limit=30&offset=0&category=financial','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'?\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(108): PDO->query(\'SELECT COUNT(*)...\')\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\",\"limit\":\"30\",\"offset\":\"0\",\"category\":\"financial\"},\"post\":[]}','open',NULL,'2026-06-30 12:16:16'),(68,1,'/alkhair/rental_api/index.php?path=notifications&limit=30&offset=0&category=security','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'?\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(108): PDO->query(\'SELECT COUNT(*)...\')\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\",\"limit\":\"30\",\"offset\":\"0\",\"category\":\"security\"},\"post\":[]}','open',NULL,'2026-06-30 12:16:16'),(69,1,'/alkhair/rental_api/index.php?path=notifications&limit=30&offset=0&category=financial','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'?\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\notifications.php(108): PDO->query(\'SELECT COUNT(*)...\')\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(58): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"notifications\",\"limit\":\"30\",\"offset\":\"0\",\"category\":\"financial\"},\"post\":[]}','open',NULL,'2026-06-30 12:16:27'),(70,1,'/alkhair/rental_api/index.php?path=equipment&page=1&limit=20','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\equipment.php(58): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(27): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"equipment\",\"page\":\"1\",\"limit\":\"20\"},\"post\":[]}','open',NULL,'2026-07-01 08:48:48'),(71,1,'/alkhair/rental_api/index.php?path=equipment&page=1&limit=20','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\equipment.php(58): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(27): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"equipment\",\"page\":\"1\",\"limit\":\"20\"},\"post\":[]}','open',NULL,'2026-07-01 08:49:46'),(72,1,'/alkhair/rental_api/index.php?path=equipment&page=1&limit=20','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\equipment.php(58): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(27): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"equipment\",\"page\":\"1\",\"limit\":\"20\"},\"post\":[]}','open',NULL,'2026-07-01 08:49:48'),(73,1,'/alkhair/rental_api/index.php?path=equipment&page=1&limit=20','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\equipment.php(58): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(27): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"equipment\",\"page\":\"1\",\"limit\":\"20\"},\"post\":[]}','open',NULL,'2026-07-01 08:51:03'),(74,1,'/alkhair/rental_api/index.php?path=equipment&page=1&limit=20','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 1','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\equipment.php(58): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(27): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"equipment\",\"page\":\"1\",\"limit\":\"20\"},\"post\":[]}','open',NULL,'2026-07-01 08:51:17'),(75,1,'/alkhair/rental_api/index.php?path=shifts','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 17','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\shifts.php(133): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(42): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"shifts\"},\"post\":[]}','open',NULL,'2026-07-01 08:53:23'),(76,1,'/alkhair/rental_api/index.php?path=shifts','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 17','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\shifts.php(133): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(42): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"shifts\"},\"post\":[]}','open',NULL,'2026-07-01 08:57:25'),(77,1,'/alkhair/rental_api/index.php?path=shifts','SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\'20\' OFFSET \'0\'\' at line 17','#0 C:\\xampp\\htdocs\\alkhair\\rental_api\\shifts.php(133): PDOStatement->execute(Array)\n#1 C:\\xampp\\htdocs\\alkhair\\rental_api\\index.php(42): require_once(\'C:\\\\xampp\\\\htdocs...\')\n#2 {main}','{\"method\":\"GET\",\"get\":{\"path\":\"shifts\"},\"post\":[]}','open',NULL,'2026-07-01 08:57:34');
/*!40000 ALTER TABLE `system_errors` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `system_errors_archive`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_errors_archive` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `api` varchar(255) NOT NULL,
  `error_message` text NOT NULL,
  `stack_trace` text DEFAULT NULL,
  `request_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_data`)),
  `status` enum('open','resolved') NOT NULL DEFAULT 'open',
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `system_errors_archive` WRITE;
/*!40000 ALTER TABLE `system_errors_archive` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_errors_archive` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `system_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_migrations` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `version` varchar(64) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `checksum` varchar(64) NOT NULL,
  `applied_by` varchar(100) DEFAULT NULL,
  `applied_at` datetime NOT NULL DEFAULT current_timestamp(),
  `finished_at` datetime DEFAULT NULL,
  `execution_time_ms` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  `backup_file` varchar(255) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_migrations_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `system_migrations` WRITE;
/*!40000 ALTER TABLE `system_migrations` DISABLE KEYS */;
INSERT INTO `system_migrations` VALUES (1,'20260701_095606','Auto-migration based on reference schema','6fde232dfb34b9a138a962f2d98c00aa','cli','2026-07-01 12:56:06','2026-07-01 12:56:06',51,'completed','C:\\xampp\\htdocs\\alkhair\\rental_api\\migrations/backups/pre_migration_20260701_095606.sql','[{\"type\":\"ADD FOREIGN KEY\",\"target\":\"equipment_maintenance.fk_maint_equipment\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`equipment_maintenance` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"payments.fk_payments_client\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`payments` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"payments.fk_payments_rent\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`payments` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"payments.fk_payments_user\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`payments` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"rents.fk_rents_client\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`rents` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"rents.fk_rents_equipment\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`rents` (errno: 121 \\\"Duplicate key on write or update\\\")\"},{\"type\":\"ADD FOREIGN KEY\",\"target\":\"shift_closings.fk_shift_user\",\"status\":\"skipped\",\"error\":\"SQLSTATE[HY000]: General error: 1005 Can\'t create table `rental_system`.`shift_closings` (errno: 121 \\\"Duplicate key on write or update\\\")\"}]'),(2,'20260701_095705','Auto-migration based on reference schema','3e8726e7179a939efefaf5c634add823','cli','2026-07-01 12:57:05','2026-07-01 12:57:05',7,'completed','C:\\xampp\\htdocs\\alkhair\\rental_api/backups/pre_migration_20260701_095705.sql','[{\"type\":\"ADD COLUMN\",\"target\":\"clients.credit_limit\",\"status\":\"skipped\",\"error\":\"SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\' at line 1\"},{\"type\":\"ADD INDEX\",\"target\":\"clients.idx_clients_national_id\",\"status\":\"applied\",\"time_ms\":5}]'),(3,'20260701_095822','Auto-migration based on reference schema','87e0323e00fe61df74e917a1b24f9f7c','cli','2026-07-01 12:58:22','2026-07-01 12:58:22',1,'completed','C:\\xampp\\htdocs\\alkhair\\rental_api/backups/pre_migration_20260701_095822.sql','[{\"type\":\"ADD COLUMN\",\"target\":\"clients.credit_limit\",\"status\":\"skipped\",\"error\":\"SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax; check the manual that corresponds to your MariaDB server version for the right syntax to use near \'\' at line 1\"}]');
/*!40000 ALTER TABLE `system_migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_sessions` WRITE;
/*!40000 ALTER TABLE `user_sessions` DISABLE KEYS */;
INSERT INTO `user_sessions` VALUES (1,1,'877c706c2f571b006db5c284b42950a4623f7c3c0eae7fa6d1e8999f6e67a3bb','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:11:18','2026-07-01 10:11:18','2026-06-30 10:11:18'),(2,7,'a080496d80d38e5697105f8c47c2bda0276114b8b847427b2622c6341c0156b8','Samsung S22 Ultra','Android','2026-06-30 10:11:19','2026-07-01 10:11:18','2026-06-30 10:11:18'),(3,1,'dd2af5d82c4ac91b46bf80673066eca109f08294edfb5442bf5bd2209010843a','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:12:29','2026-07-01 10:12:29','2026-06-30 10:12:29'),(4,9,'83f06007c321b25c9ebc7fee6ba3b2fbb8baad6f1ab1bd216a6ca6f2abfc9041','Samsung S22 Ultra','Android','2026-06-30 10:12:30','2026-07-01 10:12:30','2026-06-30 10:12:30'),(5,1,'300be1d20c7b39e0d48c9edca6a4cae7c1bfc659c0cbb77e7d27048fe6e7344e','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:10','2026-07-01 10:13:10','2026-06-30 10:13:10'),(6,11,'01fadbffb761f88c7fe09f725f92c6d9eb25caba3a2746776261706555f7cc5c','Samsung S22 Ultra','Android','2026-06-30 10:13:10','2026-07-01 10:13:10','2026-06-30 10:13:10'),(7,1,'75e6a0c27315742d4e8334b1067c608c3fa3470c08ddf3730770736ea43b6e6e','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:21','2026-07-01 10:13:20','2026-06-30 10:13:20'),(8,13,'8bded326ba17dcc6c7eac1753568fb80117d6b0ac274b16c7f7c2d505ec26f95','Samsung S22 Ultra','Android','2026-06-30 10:13:21','2026-07-01 10:13:20','2026-06-30 10:13:20'),(9,14,'09bd576650c41e24b21349ac755da328269904d89cec7ca94c13e007f06bfe75','Manager iPad Pro','iOS','2026-06-30 10:13:21','2026-07-01 10:13:21','2026-06-30 10:13:21'),(10,1,'7debf7657406da02dd3db2cdae0ed4685b4f06587f1b1ea0a8e0dcae45185267','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:39','2026-07-01 10:13:38','2026-06-30 10:13:38'),(12,16,'83f39c36b1eb25845864de43b4b06be11ba87b0e4f386da40215889e5def7a9d','Manager iPad Pro','iOS','2026-06-30 10:13:39','2026-07-01 10:13:39','2026-06-30 10:13:39'),(13,1,'075cd92f63fe335a931dc0b9f75f17369f93465d079f09ab3e3ca1fa73bc124d','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:13:51','2026-07-01 10:13:50','2026-06-30 10:13:50'),(15,18,'e29d017a07e9fe62a701b3e06c52ffafa2a0a925db0659ddd64ea2c6ee2a2c7f','Manager iPad Pro','iOS','2026-06-30 10:13:51','2026-07-01 10:13:51','2026-06-30 10:13:51'),(16,1,'a68edd6b38de79da22744d664f90c8f0c9ca5e16edadefd240f81e17a2ea77d3','','','2026-06-30 10:16:34','2026-07-01 10:15:56','2026-06-30 10:15:56'),(17,1,'b76472c41e276b49cd53bb33e89be26cedfbb432a296e9f09e45e6b650d60c4a','','','2026-06-30 10:31:53','2026-07-01 10:19:26','2026-06-30 10:19:26'),(18,1,'8ceaaa40659d1bd2dd1c76bf56d1569816d46735dac6031314929795aa2b23e3','','','2026-06-30 10:36:35','2026-07-01 10:36:05','2026-06-30 10:36:05'),(19,1,'a21fbf4c6918bfe666bc6c4f7b870c27f1e872adadc4e646567d4c38721fc024','Test Desktop PC','Integration_Test_Runner','2026-06-30 10:40:58','2026-07-01 10:40:56','2026-06-30 10:40:56'),(21,20,'c548dd75271db5722fe31941a22f2e05d249577fbc236ff5066705e0352b5105','Manager iPad Pro','iOS','2026-06-30 10:40:58','2026-07-01 10:40:58','2026-06-30 10:40:58'),(22,1,'eb2a7181de76fd2654bbfc49c93222998d7723ec65159a8073a8ac069881733d','','','2026-06-30 10:57:40','2026-07-01 10:42:11','2026-06-30 10:42:11'),(23,1,'c833ef7d9f13cbac55db1614ebf762425d169fbc59b11f49be25c8d6bf6e9cc6','','','2026-06-30 11:15:03','2026-07-01 11:14:56','2026-06-30 11:14:56'),(24,1,'ab48388e3dcf6b0d5d028476103266c691b899b3966575548421f569a7b0b29b','','','2026-06-30 11:24:55','2026-07-01 11:20:10','2026-06-30 11:20:10'),(25,1,'0ef64e7d66f364f8699aab77088b5dedc568bc8fdc7b1ad798cb76832ff67f93','','','2026-06-30 11:27:08','2026-07-01 11:26:51','2026-06-30 11:26:51'),(26,1,'8f79425a5b6af829ee3e190ff528c52c9ca73c19fe8ed806438d79bc0ce8c36d','','','2026-06-30 11:32:53','2026-07-01 11:32:53','2026-06-30 11:32:53'),(27,1,'cd5199769c72fd3eededc60cea636c60828c1bbcb69d65a216f851f550ebf230','','','2026-06-30 11:32:58','2026-07-01 11:32:58','2026-06-30 11:32:58'),(28,1,'ea39f7ac9096b03d04b524017e36eb490b249d48c148416165d2a059ad3b5de8','','','2026-06-30 11:33:06','2026-07-01 11:33:06','2026-06-30 11:33:06'),(29,1,'02281bba0c1edd9e0afd60c084666bf1b28a789ad5cf1b39175e7ca9fae71bed','','','2026-06-30 11:33:39','2026-07-01 11:33:39','2026-06-30 11:33:39'),(30,1,'f9846c2b66ba3317c828ba3989bcaab9f752c1a4fc0a8655e524e6af5f0efc7c','','','2026-06-30 11:33:45','2026-07-01 11:33:44','2026-06-30 11:33:44'),(31,1,'ddc4272c2bd37fce41b7613f2a16fd336c0dc7a7d2a6dea8565034d266a449fd','','','2026-06-30 11:34:42','2026-07-01 11:34:42','2026-06-30 11:34:42'),(32,1,'166ecbeb115ef4ee90e4edd3e622a3696f94c13e19288ce635da50c87853a31e','','','2026-06-30 11:34:48','2026-07-01 11:34:48','2026-06-30 11:34:48'),(33,1,'219e077ac83f2fea6f8765a62152f48259782d410fd6b8b405d3e0497d327775','','','2026-06-30 11:35:53','2026-07-01 11:35:53','2026-06-30 11:35:53'),(34,1,'77dc2e2d31d173fa297d6315b9d23924161e6aedd5a48ec3ea49f28d3a434f97','','','2026-06-30 11:35:59','2026-07-01 11:35:59','2026-06-30 11:35:59'),(35,1,'dc8e3ddc7e5d4b39dae9d94b9a476aa120938e66b8e0e6d144dc31adaa9effe7','','','2026-06-30 11:42:25','2026-07-01 11:42:03','2026-06-30 11:42:03'),(36,1,'4ba366a95358b20b79effd9cd49fce6307c2a9f06fa469eafa7bc1cb3131a522','','','2026-06-30 11:44:22','2026-07-01 11:44:00','2026-06-30 11:44:00'),(37,1,'db97bd0efbcc258cfdeb63f11b096088ad2ada87282e03fd263f090806074f7e','','','2026-06-30 12:17:08','2026-07-01 11:47:42','2026-06-30 11:47:42'),(38,1,'352da35ff68475599ea18c1b5a7360e216262bb99e8dd57d287788fe0ad16fb8','','','2026-06-30 11:58:41','2026-07-01 11:53:27','2026-06-30 11:53:27'),(39,1,'5cee1bead0e749d740266c99f7b12b3d37bc340a898551f0b8b6fd25b5796822','','','2026-06-30 12:19:31','2026-07-01 12:15:07','2026-06-30 12:15:07'),(40,1,'5a0930aa6192d42f0af0ce1081cc04bc246b3702fa2ab66bd988a27ca7cd6525','','','2026-06-30 12:22:55','2026-07-01 12:22:50','2026-06-30 12:22:50'),(41,1,'a2d26f2da4621f150982dc915c0d8d360410680e16b57ebf33d30d8b685710f2','','','2026-06-30 12:30:32','2026-07-01 12:30:24','2026-06-30 12:30:24'),(42,1,'2c1b5c434a945d8489c954a10eb67235d1d6e6277cb90c9b2bc6acd57ee781b3','','','2026-07-01 08:42:34','2026-07-02 08:42:33','2026-07-01 08:42:33'),(43,1,'595aa8d18f82ceb49960e492a76be9ad9f2713a2a1aa08f49a7efb98a817f3e5','','','2026-07-01 08:49:48','2026-07-02 08:48:11','2026-07-01 08:48:11'),(44,1,'5bd07689d3c1c9f2c6ac7b10080d3ee6bdeb6bc9511dc18b5e673e4c64999498','','','2026-07-01 08:51:03','2026-07-02 08:50:58','2026-07-01 08:50:58'),(45,1,'9a7a39fe27856840c7be4d349172350f434ff35a58c369fd529da88dc7e5bb6c','','','2026-07-01 08:51:17','2026-07-02 08:51:17','2026-07-01 08:51:17'),(46,1,'aea58f3b7a033bbf3eaf56b46f9462456deaff650bc901d6e27c4f3ccf5b524f','','','2026-07-01 08:52:08','2026-07-02 08:52:07','2026-07-01 08:52:07'),(47,1,'73e6db7b1fd0c982cfdba6d0e5b24eca1ec3a93ac4c871dce79b48fdc8d74530','','','2026-07-01 08:53:30','2026-07-02 08:52:34','2026-07-01 08:52:34'),(48,1,'6719b06e679dcf20bb7bea8fdffcb48cdfe936dd63ac2f5a0d6fb23ad8e594f4','','','2026-07-01 08:58:27','2026-07-02 08:57:14','2026-07-01 08:57:14'),(49,1,'5fb0b23e89a8182da1f1d3fb15059c67db0c9fc8d4d523ab5953716206b9f078',NULL,NULL,'2026-07-01 08:58:37','2026-07-01 09:58:37','2026-07-01 08:58:37'),(50,5,'ae8459e51b154dff844fffa6e98bedb987cc65dbe77189bb203e278fff7db459','','','2026-07-01 08:59:03','2026-07-02 08:58:42','2026-07-01 08:58:42'),(51,1,'d85595226c0277c158e7eaa4e1329f50cc7ed0b3fb2bc4ae762a74bcbb999024','','','2026-07-01 08:58:58','2026-07-02 08:58:58','2026-07-01 08:58:58'),(52,1,'332f582562ac8a945ba34e086e32ef2cbb8e52018dae569fa5a6d26ee14618b8',NULL,NULL,'2026-07-01 09:00:22','2026-07-01 10:00:22','2026-07-01 09:00:22'),(53,1,'8241e81706f33f8932ce69dbd0ab2494a97e6bce579a0442e9395a4157b58471',NULL,NULL,'2026-07-01 09:00:45','2026-07-01 10:00:45','2026-07-01 09:00:45'),(54,1,'93671366d66834047a3ed1c9030ed7e93e399190b5b32e8fe3cec5faf61b53bb','','','2026-07-01 09:20:10','2026-07-02 09:04:58','2026-07-01 09:04:58'),(55,1,'4eef71491edb47e34c2ee6f5aba891d29927901c75394571f06621fe0a4bfba4','','','2026-07-01 09:38:16','2026-07-02 09:34:38','2026-07-01 09:34:38'),(56,1,'8c8b85afbf16d0cb730aec179bfb08bf9d6d1503495b767de09409ead5924df5','','','2026-07-01 09:48:33','2026-07-02 09:42:00','2026-07-01 09:42:00'),(57,1,'8a7a5222f77fab1281e3839404e54e0cea5db51a532026e56a21eb11dbcbad53','','','2026-07-01 09:50:04','2026-07-02 09:49:30','2026-07-01 09:49:30'),(58,1,'a9d7d0f3a977f72e3ddcc5262242660ce11fb996d5c1d32e5df926ca9d7f6a7e','','','2026-07-01 09:51:00','2026-07-02 09:50:55','2026-07-01 09:50:55'),(59,1,'e52dcd7a379e56bdf4faf192b4f4ddbcd2257c9f7279f5a167c97294f9cb459b','','','2026-07-01 10:01:09','2026-07-02 09:58:24','2026-07-01 09:58:24'),(60,5,'798f580645729bf43788b7204756aa3cada4a80fe8a8987d8991c8060f470603','','','2026-07-01 10:05:43','2026-07-02 10:01:21','2026-07-01 10:01:21'),(61,1,'13a33e6aeca1844f54bc862f9c377e9fad6a97344919a5577d0c54ec18378558','','','2026-07-01 10:07:19','2026-07-02 10:06:11','2026-07-01 10:06:11'),(62,5,'3d06ba999549f30814df5985787ed33c8a8019f5a26d7ba4f1910d258c3bf796','','','2026-07-01 10:08:52','2026-07-02 10:07:30','2026-07-01 10:07:30'),(63,1,'88d5fc82794c8be77213a9dc84ed3dd689a9e457caf0e63b8516ce33efbcdf3d','','','2026-07-01 10:10:01','2026-07-02 10:09:01','2026-07-01 10:09:01'),(64,1,'35de6280a0fde8b9a635b627cb9c8e9cf1e641d0dd26665ca478dcdbb269c7ac','','','2026-07-01 10:12:27','2026-07-02 10:10:40','2026-07-01 10:10:40'),(65,5,'2b55d7fe8a9dacea0b224b274e23e176e1cde29e805004ace9c55bb818e7229d','','','2026-07-01 10:12:49','2026-07-02 10:12:34','2026-07-01 10:12:34'),(66,1,'5e9aca45664abc1da52be1f2bff03d45eadcf4cd24efc95c4d0139e82dd174aa','','','2026-07-01 10:16:06','2026-07-02 10:15:56','2026-07-01 10:15:56'),(67,5,'b9bdc82c45e55ef4a0da9070b108a11004b3abe3bb6d10c80532d0cf2465c6d2','','','2026-07-01 10:17:19','2026-07-02 10:16:44','2026-07-01 10:16:44'),(68,5,'72aad00f443b38345f6045e4f6f74066e9d7f5ad849c22f7bc8a10280153edf5','','','2026-07-01 10:19:17','2026-07-02 10:18:45','2026-07-01 10:18:45'),(69,5,'ebe94ab7fc96de4d2ecd0a74b3b7cadd4ed33caa044ce60d86ede54970963a43','','','2026-07-01 10:28:33','2026-07-02 10:20:55','2026-07-01 10:20:55'),(70,1,'d1e625fb3cdb2990343afdd9751c8a7194fb4a16e0d697c045fb8226704e919d','','','2026-07-01 10:30:31','2026-07-02 10:29:29','2026-07-01 10:29:29'),(71,5,'93541f44dbb3dd727a056bf8b8cb5afee915d36a5f8bf8955c46cab7f4cbad2e','','','2026-07-01 10:30:44','2026-07-02 10:30:39','2026-07-01 10:30:39'),(72,1,'f08f3a26ea3f27aeda6ce41f19e42411a41b9f214a3e71743ec60e26b22add74','','','2026-07-01 10:34:18','2026-07-02 10:30:58','2026-07-01 10:30:58'),(73,1,'32a0813c44cd4969cfb0d29b606e7dc3895780b646c9803e9f9fb5a4b6c77bd4','','','2026-07-01 10:35:12','2026-07-02 10:34:46','2026-07-01 10:34:46'),(74,5,'d1bc9f838ca750765c25414dca3f441ed4239dcb95f23f84cbf42f7ad55445fc','','','2026-07-01 10:36:01','2026-07-02 10:36:01','2026-07-01 10:36:01'),(75,1,'9d577ab84eaeccc8a6877699dab0bf97ba8bb98b9f2cbec72c85b233ad66975e','','','2026-07-01 10:39:54','2026-07-02 10:36:22','2026-07-01 10:36:22'),(76,1,'b824a8eb85507b2c201958142958c47b1266347f01dc7be4a7d6902827885a49','','','2026-07-01 10:44:38','2026-07-02 10:40:08','2026-07-01 10:40:08'),(77,1,'b7bb1c8bc879b486a0a68db4b076c53d05a4aa375129931828a15d3732493cd0','','','2026-07-01 11:45:47','2026-07-02 11:28:36','2026-07-01 11:28:36'),(78,1,'bb906bd28d27f4209e61d07bd677f3e2bf3116859f04eba335ce24a2e271d723','','','2026-07-01 11:50:41','2026-07-02 11:48:15','2026-07-01 11:48:15'),(79,1,'fe6e8762c67e1a3f08801efc5233d8fc88d6686b2059d7b9708d8a477a9a751f','','','2026-07-01 11:52:33','2026-07-02 11:52:30','2026-07-01 11:52:30');
/*!40000 ALTER TABLE `user_sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `is_test_data` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$xTezEffLcJPxj4HgnZrcI.RHduM6yZZikxaY6JMcaRgTtzq/a9kEy','admin','2026-01-11 07:30:53',1,NULL,NULL,NULL,NULL,NULL,0),(5,'صالح','$2y$10$XE25gek2audHk7.e9RznEuNkyDHZZDG.9hgJovPQMTssM3evEJE/i','employee','2026-05-24 05:53:40',1,NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"rents\":true,\"clients\":true,\"reports\":false,\"dashboard\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":false,\"export\":false,\"audit_logs\":false,\"financial_reports\":false,\"attendance_dashboard\":false,\"attendance_statistics\":false,\"attendance_reports\":false,\"attendance_breaks\":false,\"attendance_edit\":true,\"attendance_manage\":false}}',NULL,0),(6,'محمد','12345','employee','2026-06-29 05:40:50',1,NULL,NULL,NULL,'{\"contract_hour_pricing_mode\":\"inherit\",\"contract_payment_receipt_mode\":\"inherit\",\"user_management\":false,\"backup\":false,\"reports\":false,\"hr\":false,\"attendance\":true,\"equipment\":true,\"clients\":true,\"rents\":true,\"payments\":true,\"receipts\":true,\"shifts\":true,\"settings\":false,\"dashboard\":true,\"print\":true,\"screen_permissions\":{\"dashboard\":true,\"rents\":true,\"clients\":true,\"equipment\":true,\"payments\":true,\"receipts\":true,\"reports\":false,\"hr\":false,\"attendance\":true,\"shifts\":true,\"backup\":false,\"settings\":true,\"user_management\":false,\"print\":true,\"export\":true,\"audit_logs\":false,\"financial_reports\":false,\"attendance_dashboard\":false,\"attendance_statistics\":false,\"attendance_reports\":false,\"attendance_breaks\":false,\"attendance_edit\":false,\"attendance_manage\":false}}',NULL,0);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

