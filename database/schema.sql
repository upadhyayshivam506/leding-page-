-- Create table: leads
CREATE TABLE `leads` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` VARCHAR(100) NOT NULL,
  `lead_id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(190) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `course` VARCHAR(190) DEFAULT NULL,
  `specialization` VARCHAR(190) DEFAULT NULL,
  `campus` VARCHAR(190) DEFAULT NULL,
  `college_name` VARCHAR(190) DEFAULT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `state` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(50) NOT NULL,
  `source_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `leads_batch_id_index` (`batch_id`),
  KEY `leads_region_index` (`region`),
  KEY `leads_lead_id_index` (`lead_id`)

) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci;


-- Create table: lead_push_logs
CREATE TABLE `lead_push_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `lead_id` VARCHAR(100) NOT NULL,
  `region` VARCHAR(50) NOT NULL,
  `college_id` VARCHAR(100) NOT NULL,
  `api_status` VARCHAR(50) NOT NULL,
  `response` LONGTEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `lead_push_logs_lead_id_index` (`lead_id`),
  KEY `lead_push_logs_region_index` (`region`),
  KEY `lead_push_logs_college_id_index` (`college_id`)

) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci;