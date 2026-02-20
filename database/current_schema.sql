mysqldump: [Warning] Using a password on the command line interface can be insecure.

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `table_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` int NOT NULL,
  `action` enum('insert','update','delete') COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows_imported` int DEFAULT '0',
  `rows_failed` int DEFAULT '0',
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `imported_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  KEY `idx_import_date` (`import_date`),
  CONSTRAINT `camera_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_installation_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_type` enum('incremental','cumulative') COLLATE utf8mb4_unicode_ci NOT NULL,
  `rows_imported` int DEFAULT '0',
  `rows_failed` int DEFAULT '0',
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `imported_by` int DEFAULT NULL,
  `movements_detected` int DEFAULT '0' COMMENT 'Number of camera movements detected in this import',
  `invoices_auto_updated` int DEFAULT '0' COMMENT 'Number of draft invoices automatically updated',
  `invoices_flagged_for_xero` int DEFAULT '0' COMMENT 'Number of issued invoices flagged for Xero correction',
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  KEY `idx_import_date` (`import_date`),
  KEY `idx_import_type` (`import_type`),
  CONSTRAINT `camera_installation_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_installations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `store_id` int NOT NULL,
  `installation_date` date NOT NULL,
  `removal_date` date DEFAULT NULL,
  `camera_type` enum('main','additional') COLLATE utf8mb4_unicode_ci NOT NULL,
  `camera_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Individual camera name like Front door, Back door, etc.',
  `safr_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SAFR system code for the camera',
  `invoice_id` int DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_safr_code` (`safr_code`),
  KEY `idx_store` (`store_id`),
  KEY `idx_installation_date` (`installation_date`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_active` (`removal_date`),
  KEY `idx_safr_code` (`safr_code`),
  CONSTRAINT `camera_installations_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_installations_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_movement_invoice_impacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `camera_movement_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_status` varchar(50) NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `old_store_id` int NOT NULL,
  `old_store_name` varchar(255) NOT NULL,
  `new_store_id` int NOT NULL,
  `new_store_name` varchar(255) NOT NULL,
  `action_taken` enum('auto_updated','flagged_for_xero','no_action') NOT NULL,
  `action_notes` text,
  `requires_xero_correction` tinyint(1) DEFAULT '0',
  `xero_corrected` tinyint(1) DEFAULT '0',
  `xero_corrected_at` timestamp NULL DEFAULT NULL,
  `xero_corrected_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `old_store_id` (`old_store_id`),
  KEY `new_store_id` (`new_store_id`),
  KEY `idx_movement` (`camera_movement_id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_requires_xero` (`requires_xero_correction`),
  KEY `idx_xero_corrected` (`xero_corrected`),
  CONSTRAINT `camera_movement_invoice_impacts_ibfk_1` FOREIGN KEY (`camera_movement_id`) REFERENCES `camera_movements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movement_invoice_impacts_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movement_invoice_impacts_ibfk_3` FOREIGN KEY (`old_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movement_invoice_impacts_ibfk_4` FOREIGN KEY (`new_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `camera_installation_id` int NOT NULL COMMENT 'The camera installation record',
  `safr_code` varchar(50) NOT NULL COMMENT 'SAFR code of the camera that moved',
  `camera_name` varchar(100) DEFAULT NULL COMMENT 'Name/description of the camera',
  `from_store_id` int NOT NULL COMMENT 'Store the camera was removed from',
  `from_store_name` varchar(255) NOT NULL COMMENT 'Store name at time of movement',
  `to_store_id` int NOT NULL COMMENT 'Store the camera was installed at',
  `to_store_name` varchar(255) NOT NULL COMMENT 'Store name at time of movement',
  `removal_date` date NOT NULL COMMENT 'Date camera was removed from old store',
  `installation_date` date NOT NULL COMMENT 'Date camera was installed at new store',
  `import_session_id` int DEFAULT NULL COMMENT 'ID of the import session that detected this movement',
  `detected_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the movement was detected',
  `detected_by` varchar(100) DEFAULT NULL COMMENT 'User who ran the import',
  `affected_invoices_count` int DEFAULT '0' COMMENT 'Number of invoices affected by this movement',
  `draft_invoices_updated` int DEFAULT '0' COMMENT 'Number of draft invoices automatically updated',
  `issued_invoices_flagged` int DEFAULT '0' COMMENT 'Number of issued invoices flagged for Xero correction',
  `xero_correction_status` enum('pending','exported','corrected','not_required') DEFAULT 'pending',
  `xero_correction_notes` text COMMENT 'Notes about Xero corrections made',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `camera_installation_id` (`camera_installation_id`),
  KEY `import_session_id` (`import_session_id`),
  KEY `idx_safr_code` (`safr_code`),
  KEY `idx_from_store` (`from_store_id`),
  KEY `idx_to_store` (`to_store_id`),
  KEY `idx_removal_date` (`removal_date`),
  KEY `idx_installation_date` (`installation_date`),
  KEY `idx_xero_status` (`xero_correction_status`),
  CONSTRAINT `camera_movements_ibfk_1` FOREIGN KEY (`camera_installation_id`) REFERENCES `camera_installations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movements_ibfk_2` FOREIGN KEY (`from_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movements_ibfk_3` FOREIGN KEY (`to_store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `camera_movements_ibfk_4` FOREIGN KEY (`import_session_id`) REFERENCES `camera_installation_imports` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `camera_pricing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `effective_date` date NOT NULL COMMENT 'Date these prices become effective (e.g., 2024-01-01)',
  `min_cameras` int NOT NULL COMMENT 'Minimum camera count for this tier',
  `max_cameras` int DEFAULT NULL COMMENT 'Maximum camera count (NULL = unlimited)',
  `price_per_annum` decimal(10,2) NOT NULL COMMENT 'Annual price per camera',
  `price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Quarterly price per camera',
  `price_per_month` decimal(10,2) NOT NULL COMMENT 'Monthly price per camera',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date_range` (`effective_date`,`min_cameras`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_camera_range` (`min_cameras`,`max_cameras`)
) ENGINE=InnoDB AUTO_INCREMENT=55 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Volume-based camera pricing tiers';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cash_flow_forecast` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `subscriber_id` int NOT NULL,
  `expected_payment_date` date NOT NULL,
  `expected_amount` decimal(12,2) NOT NULL,
  `confidence_level` enum('high','medium','low') COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `is_overdue` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_expected_date` (`expected_payment_date`),
  KEY `idx_subscriber` (`subscriber_id`),
  CONSTRAINT `cash_flow_forecast_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_flow_forecast_ibfk_2` FOREIGN KEY (`subscriber_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `default_independent_pricing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `effective_date` date NOT NULL COMMENT 'Date these prices become effective',
  `first_camera_price_per_annum` decimal(10,2) NOT NULL COMMENT 'Default annual price for first camera',
  `first_camera_price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Default quarterly price for first camera',
  `first_camera_price_per_month` decimal(10,2) NOT NULL COMMENT 'Default monthly price for first camera',
  `additional_camera_price_per_annum` decimal(10,2) NOT NULL COMMENT 'Default annual price for cameras 2+',
  `additional_camera_price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Default quarterly price for cameras 2+',
  `additional_camera_price_per_month` decimal(10,2) NOT NULL COMMENT 'Default monthly price for cameras 2+',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_date` (`effective_date`),
  KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Default first camera + additional pricing for entities using independent pricing model';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inflation_rates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `effective_date` date NOT NULL,
  `inflation_percentage` decimal(5,2) NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `effective_date` (`effective_date`),
  KEY `idx_effective_date` (`effective_date`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_camera_allocations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `camera_installation_id` int NOT NULL,
  `store_id` int NOT NULL,
  `legal_entity_id` int NOT NULL,
  `camera_serial` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allocated_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `removed_date` datetime DEFAULT NULL,
  `removed_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_charged` decimal(10,2) NOT NULL,
  `pricing_tier` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g., "first_camera", "additional", "tier_1-49"',
  `allocated_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `removed_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_camera` (`camera_installation_id`),
  KEY `idx_store` (`store_id`),
  KEY `idx_legal_entity` (`legal_entity_id`),
  KEY `idx_allocated_date` (`allocated_date`),
  KEY `idx_removed_date` (`removed_date`),
  CONSTRAINT `invoice_camera_allocations_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_camera_allocations_ibfk_2` FOREIGN KEY (`camera_installation_id`) REFERENCES `camera_installations` (`id`),
  CONSTRAINT `invoice_camera_allocations_ibfk_3` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `invoice_camera_allocations_ibfk_4` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=163843 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_generation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `generation_type` enum('manual','auto_repeat','merged','adjusted') COLLATE utf8mb4_unicode_ci NOT NULL,
  `generation_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `triggered_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trigger_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `camera_count` int DEFAULT NULL,
  `total_amount` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_invoice` (`invoice_id`),
  KEY `idx_generation_date` (`generation_date`),
  KEY `idx_generation_type` (`generation_type`),
  CONSTRAINT `invoice_generation_log_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2125 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `old_status` enum('draft','issued','reconciled_to_xero','cancelled','merged') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` enum('draft','issued','reconciled_to_xero','cancelled','merged') COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_by` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `changed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_invoice_status` (`invoice_id`,`changed_at`),
  CONSTRAINT `invoice_status_history_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `xero_company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xero_customer_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `main_cameras` int DEFAULT '0',
  `additional_cameras` int DEFAULT '0',
  `total_cameras` int GENERATED ALWAYS AS ((`main_cameras` + `additional_cameras`)) STORED,
  `invoice_amount` decimal(12,2) NOT NULL,
  `payment_status` enum('unpaid','paid','overdue','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `payment_frequency` enum('annual','quarterly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'annual',
  `is_vat_exclusive` tinyint(1) DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expected_amount` decimal(10,2) DEFAULT NULL COMMENT 'Calculated expected amount',
  `validation_status` enum('pending','matched','mismatch','manual_override') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `validation_notes` text COLLATE utf8mb4_unicode_ci,
  `variance` decimal(10,2) DEFAULT NULL,
  `reconciliation_status` enum('matched','under_charged','over_charged','pending') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `is_auto_generated` tinyint(1) DEFAULT '0',
  `parent_invoice_id` int DEFAULT NULL,
  `generation_date` datetime DEFAULT NULL,
  `next_generation_date` date DEFAULT NULL,
  `invoice_status` enum('draft','issued','reconciled_to_xero','cancelled','merged','forecast') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `is_forecast` tinyint(1) DEFAULT '0',
  `forecast_year` int DEFAULT NULL,
  `merged_into_invoice_id` int DEFAULT NULL,
  `created_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xero_invoice_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Xero invoice ID for reconciliation',
  `xero_invoice_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Invoice number in Xero',
  `reconciled_date` date DEFAULT NULL COMMENT 'Date reconciled to Xero',
  `reconciled_by` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'User who reconciled',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  UNIQUE KEY `unique_xero_invoice` (`xero_customer_number`,`invoice_date`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_subscriber_date` (`legal_entity_id`,`invoice_date`),
  KEY `idx_payment_status` (`payment_status`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_xero_company` (`xero_company_name`),
  KEY `idx_reconciliation_status` (`reconciliation_status`),
  KEY `idx_xero_invoice_id` (`xero_invoice_id`),
  KEY `idx_xero_invoice_number` (`xero_invoice_number`),
  KEY `idx_invoices_is_forecast` (`is_forecast`),
  KEY `idx_invoices_status_forecast` (`invoice_status`,`is_forecast`),
  KEY `idx_invoices_forecast_year` (`forecast_year`),
  CONSTRAINT `fk_invoice_legal_entity` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2132 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_entity_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `xero_company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `main_camera_rate` int DEFAULT '0' COMMENT 'Annual rate for main camera',
  `additional_camera_rate` int DEFAULT '0' COMMENT 'Annual rate for additional cameras',
  `payment_frequency` enum('annual','quarterly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'annual' COMMENT 'Billing frequency',
  `pricing_type` enum('default','custom') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default' COMMENT 'Whether to use default pricing or entity-specific pricing',
  `pricing_model` enum('volume_based','first_plus_additional') COLLATE utf8mb4_unicode_ci DEFAULT 'volume_based' COMMENT 'Pricing model: volume_based or first_plus_additional',
  `sales_credit` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `payment_terms_days` int DEFAULT '30',
  `pricing_basis` enum('rent','purchase') COLLATE utf8mb4_unicode_ci DEFAULT 'rent',
  `is_legacy_pricing` tinyint(1) DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fcst_lookup_code` (`xero_company_name`),
  UNIQUE KEY `legal_entity_id` (`legal_entity_id`),
  KEY `idx_legal_entity` (`legal_entity_name`),
  KEY `idx_fcst_lookup` (`xero_company_name`),
  KEY `idx_xero_customer_number` (`xero_company_name`),
  KEY `idx_xero_company_name` (`xero_company_name`)
) ENGINE=InnoDB AUTO_INCREMENT=98 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entity_contracts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `effective_date` date NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `first_inflation_date` date DEFAULT NULL,
  `payment_frequency` enum('annual','quarterly','monthly') COLLATE utf8mb4_unicode_ci DEFAULT 'annual',
  `main_camera_rate` decimal(10,2) DEFAULT NULL,
  `additional_camera_rate` decimal(10,2) DEFAULT NULL,
  `additional_camera_percentage` decimal(5,2) DEFAULT NULL,
  `is_hardcoded_rate` tinyint(1) DEFAULT '0',
  `minimum_term_years` int DEFAULT '3',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_subscriber_effective` (`legal_entity_id`,`effective_date`),
  KEY `idx_invoice_date` (`invoice_date`),
  CONSTRAINT `legal_entity_contracts_ibfk_1` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entity_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows_imported` int DEFAULT '0',
  `rows_updated` int DEFAULT '0',
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `imported_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  KEY `idx_import_date` (`import_date`),
  CONSTRAINT `legal_entity_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entity_independent_pricing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `effective_date` date NOT NULL COMMENT 'Date these prices become effective',
  `first_camera_price_per_annum` decimal(10,2) NOT NULL COMMENT 'Annual price for the first camera',
  `first_camera_price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Quarterly price for the first camera',
  `first_camera_price_per_month` decimal(10,2) NOT NULL COMMENT 'Monthly price for the first camera',
  `additional_camera_price_per_annum` decimal(10,2) NOT NULL COMMENT 'Annual price for cameras 2+',
  `additional_camera_price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Quarterly price for cameras 2+',
  `additional_camera_price_per_month` decimal(10,2) NOT NULL COMMENT 'Monthly price for cameras 2+',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_store_date` (`legal_entity_id`,`effective_date`),
  KEY `idx_store` (`legal_entity_id`),
  KEY `idx_effective_date` (`effective_date`),
  CONSTRAINT `fk_entity_independent_pricing` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `legal_entity_independent_pricing_ibfk_1` FOREIGN KEY (`legal_entity_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Entity-specific first camera + additional pricing overrides';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entity_pricing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `effective_date` date NOT NULL COMMENT 'Date these prices become effective',
  `min_cameras` int NOT NULL COMMENT 'Minimum camera count for this tier',
  `max_cameras` int DEFAULT NULL COMMENT 'Maximum camera count (NULL = unlimited)',
  `price_per_annum` decimal(10,2) NOT NULL COMMENT 'Annual price per camera',
  `price_per_quarter` decimal(10,2) NOT NULL COMMENT 'Quarterly price per camera',
  `price_per_month` decimal(10,2) NOT NULL COMMENT 'Monthly price per camera',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_entity_date_range` (`legal_entity_id`,`effective_date`,`min_cameras`),
  KEY `idx_legal_entity` (`legal_entity_id`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_camera_range` (`min_cameras`,`max_cameras`),
  CONSTRAINT `legal_entity_pricing_ibfk_1` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Entity-specific camera pricing tiers';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_entity_rate_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `effective_date` date NOT NULL COMMENT 'Date this rate becomes effective',
  `end_date` date DEFAULT NULL COMMENT 'Date this rate stops being effective (NULL = current)',
  `main_camera_rate` decimal(10,2) NOT NULL COMMENT 'Rate per main camera',
  `additional_camera_rate` decimal(10,2) NOT NULL COMMENT 'Rate per additional camera',
  `total_cameras` int NOT NULL DEFAULT '0' COMMENT 'Total camera count at time of rate change',
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT '0.00' COMMENT 'Volume discount applied',
  `base_main_rate` decimal(10,2) NOT NULL COMMENT 'Base rate before discount',
  `base_additional_rate` decimal(10,2) NOT NULL COMMENT 'Base rate before discount',
  `change_reason` enum('initial','inflation','volume_discount','manual','contract_change') NOT NULL DEFAULT 'manual',
  `inflation_rate` decimal(5,2) DEFAULT NULL COMMENT 'Inflation percentage applied (if applicable)',
  `notes` text,
  `created_by` varchar(100) DEFAULT NULL COMMENT 'User or system that created this rate',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_entity_date` (`legal_entity_id`,`effective_date`),
  KEY `idx_legal_entity_date` (`legal_entity_id`,`effective_date`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_current_rates` (`legal_entity_id`,`end_date`),
  CONSTRAINT `legal_entity_rate_history_ibfk_1` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Historical camera rates for legal entities';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matching_audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `xero_invoice_id` int NOT NULL,
  `system_invoice_id` int DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT NULL,
  `match_details` json DEFAULT NULL,
  `action` enum('auto_matched','suggested','accepted','rejected','manual','deleted') NOT NULL,
  `performed_by` varchar(255) DEFAULT NULL,
  `performed_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `session_id` (`session_id`),
  KEY `xero_invoice_id` (`xero_invoice_id`),
  KEY `system_invoice_id` (`system_invoice_id`),
  CONSTRAINT `matching_audit_log_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `xero_import_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matching_audit_log_ibfk_2` FOREIGN KEY (`xero_invoice_id`) REFERENCES `xero_imported_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `matching_audit_log_ibfk_3` FOREIGN KEY (`system_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `matching_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('entity_name','date','amount') NOT NULL,
  `tolerance_value` varchar(50) NOT NULL,
  `score_weight` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prepayments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `subscriber_id` int NOT NULL,
  `calculation_date` date NOT NULL,
  `invoice_date` date NOT NULL,
  `invoice_amount` decimal(12,2) NOT NULL,
  `payment_frequency` enum('annual','quarterly','monthly') COLLATE utf8mb4_unicode_ci NOT NULL,
  `days_since_invoice` int NOT NULL,
  `days_in_period` int NOT NULL,
  `days_remaining` int GENERATED ALWAYS AS ((`days_in_period` - `days_since_invoice`)) STORED,
  `prepayment_balance` decimal(12,2) NOT NULL,
  `pl_credit_amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_invoice_calc_date` (`invoice_id`,`calculation_date`),
  KEY `idx_calculation_date` (`calculation_date`),
  KEY `idx_subscriber_date` (`subscriber_id`,`calculation_date`),
  CONSTRAINT `prepayments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prepayments_ibfk_2` FOREIGN KEY (`subscriber_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pricing_tiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `effective_date` date NOT NULL,
  `min_cameras` int NOT NULL,
  `max_cameras` int DEFAULT NULL,
  `annual_price_per_camera` decimal(10,2) NOT NULL,
  `monthly_price_per_camera` decimal(10,2) NOT NULL,
  `tier_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_camera_range` (`min_cameras`,`max_cameras`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reconciliation_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `subscriber_id` int NOT NULL,
  `check_date` date NOT NULL,
  `invoiced_main_cameras` int NOT NULL,
  `invoiced_additional_cameras` int NOT NULL,
  `installed_main_cameras` int NOT NULL,
  `installed_additional_cameras` int NOT NULL,
  `installation_variance_main` int GENERATED ALWAYS AS ((`invoiced_main_cameras` - `installed_main_cameras`)) STORED,
  `installation_variance_additional` int GENERATED ALWAYS AS ((`invoiced_additional_cameras` - `installed_additional_cameras`)) STORED,
  `expected_amount` decimal(12,2) NOT NULL,
  `actual_amount` decimal(12,2) NOT NULL,
  `variance` decimal(12,2) GENERATED ALWAYS AS ((`actual_amount` - `expected_amount`)) STORED,
  `variance_percentage` decimal(5,2) DEFAULT NULL,
  `status` enum('ok','warning','error') COLLATE utf8mb4_unicode_ci DEFAULT 'ok',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `idx_check_date` (`check_date`),
  KEY `idx_status` (`status`),
  KEY `idx_subscriber` (`subscriber_id`),
  CONSTRAINT `reconciliation_log_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reconciliation_log_ibfk_2` FOREIGN KEY (`subscriber_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `store_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows_imported` int DEFAULT '0',
  `rows_updated` int DEFAULT '0',
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `imported_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  KEY `idx_import_date` (`import_date`),
  CONSTRAINT `store_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `legal_entity_id` int NOT NULL,
  `store_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pricing_model` enum('default','first_plus_additional') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default' COMMENT 'Pricing model: default (volume-based) or first_plus_additional (first camera full price, rest discounted)',
  `store_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `address_line1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address_line2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `store_id` (`store_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_store_name` (`store_name`),
  KEY `idx_legal_entity` (`legal_entity_id`),
  CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`legal_entity_id`) REFERENCES `legal_entities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1635 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_activity_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `action_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` int DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `user_activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','manager','viewer') COLLATE utf8mb4_unicode_ci DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT '1',
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `xero_import_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_name` varchar(255) NOT NULL,
  `import_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `imported_by` varchar(255) DEFAULT NULL,
  `total_invoices` int DEFAULT '0',
  `matched_count` int DEFAULT '0',
  `reconciled_count` int DEFAULT '0',
  `status` enum('pending','reviewing','completed','cancelled') DEFAULT 'pending',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `xero_imported_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `session_id` int NOT NULL,
  `xero_invoice_id` varchar(255) NOT NULL,
  `xero_invoice_number` varchar(100) NOT NULL,
  `contact_name` varchar(255) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `matched_invoice_id` int DEFAULT NULL,
  `manually_rejected_invoice_id` text,
  `match_confidence` decimal(5,2) DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT NULL COMMENT 'Match score percentage (0-100)',
  `match_breakdown` json DEFAULT NULL COMMENT 'Detailed breakdown of match scoring',
  `match_status` enum('unmatched','auto_matched','suggested','manual_matched','rejected') DEFAULT 'unmatched',
  `match_reason` text,
  `is_reconciled` tinyint(1) DEFAULT '0',
  `reconciled_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `matched_invoice_id` (`matched_invoice_id`),
  KEY `idx_session` (`session_id`),
  KEY `idx_xero_invoice_id` (`xero_invoice_id`),
  KEY `idx_contact_name` (`contact_name`),
  KEY `idx_invoice_date` (`invoice_date`),
  KEY `idx_match_status` (`match_status`),
  CONSTRAINT `xero_imported_invoices_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `xero_import_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `xero_imported_invoices_ibfk_2` FOREIGN KEY (`matched_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `xero_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rows_imported` int DEFAULT '0',
  `rows_failed` int DEFAULT '0',
  `status` enum('success','partial','failed') COLLATE utf8mb4_unicode_ci DEFAULT 'success',
  `error_log` text COLLATE utf8mb4_unicode_ci,
  `imported_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `imported_by` (`imported_by`),
  KEY `idx_import_date` (`import_date`),
  CONSTRAINT `xero_imports_ibfk_1` FOREIGN KEY (`imported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

