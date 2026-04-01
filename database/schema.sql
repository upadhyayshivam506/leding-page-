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
  `lead_origin` VARCHAR(190) DEFAULT NULL,
  `campaign` VARCHAR(190) DEFAULT NULL,
  `lead_stage` VARCHAR(190) DEFAULT NULL,
  `lead_status` VARCHAR(190) DEFAULT NULL,
  `form_initiated` VARCHAR(50) DEFAULT NULL,
  `paid_apps` VARCHAR(50) DEFAULT NULL,
  `state` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(50) NOT NULL,
  `source_file` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  KEY `leads_batch_id_index` (`batch_id`),
  KEY `leads_region_index` (`region`),
  KEY `leads_lead_id_index` (`lead_id`),
  KEY `leads_created_at_index` (`created_at`),
  KEY `leads_course_index` (`course`),
  KEY `leads_state_index` (`state`),
  KEY `leads_city_index` (`city`),
  KEY `leads_lead_origin_index` (`lead_origin`),
  KEY `leads_campaign_index` (`campaign`),
  KEY `leads_lead_stage_index` (`lead_stage`),
  KEY `leads_lead_status_index` (`lead_status`),
  KEY `leads_form_initiated_index` (`form_initiated`),
  KEY `leads_paid_apps_index` (`paid_apps`)

) ENGINE=InnoDB 
DEFAULT CHARSET=utf8mb4 
COLLATE=utf8mb4_unicode_ci;


-- Create table: colleagues
CREATE TABLE `colleagues` (
  `id` VARCHAR(100) NOT NULL,
  `college_name` VARCHAR(190) NOT NULL,
  `region` VARCHAR(50) NOT NULL,
  `api_url` VARCHAR(255) DEFAULT NULL,
  `api_token` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `colleagues_region_index` (`region`),
  KEY `colleagues_college_name_index` (`college_name`)

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


-- Create table: lead_mapping_configurations
CREATE TABLE `lead_mapping_configurations` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` VARCHAR(100) NOT NULL,
  `selected_regions` JSON NOT NULL,
  `mapping_column` VARCHAR(100) NOT NULL,
  `selected_courses` JSON NOT NULL,
  `selected_specialization` VARCHAR(190) DEFAULT NULL,
  `selected_colleges` JSON NOT NULL,
  `total_leads` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(50) NOT NULL DEFAULT 'previewed',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `lead_mapping_configurations_batch_id_index` (`batch_id`),
  KEY `lead_mapping_configurations_status_index` (`status`)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- Create table: lead_mapping_jobs
CREATE TABLE `lead_mapping_jobs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mapping_configuration_id` BIGINT(20) UNSIGNED NOT NULL,
  `batch_id` VARCHAR(100) NOT NULL,
  `job_token` VARCHAR(120) NOT NULL,
  `batch_size` INT NOT NULL,
  `delay_seconds` DECIMAL(8,2) NOT NULL DEFAULT 0.20,
  `status` VARCHAR(50) NOT NULL DEFAULT 'queued',
  `total_leads` INT NOT NULL DEFAULT 0,
  `total_requests` INT NOT NULL DEFAULT 0,
  `processed_leads` INT NOT NULL DEFAULT 0,
  `processed_requests` INT NOT NULL DEFAULT 0,
  `success_count` INT NOT NULL DEFAULT 0,
  `failed_count` INT NOT NULL DEFAULT 0,
  `colleges_json` JSON NOT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `lead_mapping_jobs_job_token_unique` (`job_token`),
  UNIQUE KEY `lead_mapping_jobs_mapping_configuration_unique` (`mapping_configuration_id`),
  KEY `lead_mapping_jobs_status_index` (`status`)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- Create table: lead_api_logs
CREATE TABLE `lead_api_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `mapping_configuration_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `job_token` VARCHAR(120) DEFAULT NULL,
  `batch_id` VARCHAR(100) DEFAULT NULL,
  `lead_id` VARCHAR(100) NOT NULL,
  `name` VARCHAR(190) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `course` VARCHAR(190) DEFAULT NULL,
  `specialization` VARCHAR(190) DEFAULT NULL,
  `campus` VARCHAR(190) DEFAULT NULL,
  `college_name` VARCHAR(190) NOT NULL,
  `city` VARCHAR(120) DEFAULT NULL,
  `state` VARCHAR(120) DEFAULT NULL,
  `region` VARCHAR(50) DEFAULT NULL,
  `source_file` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL,
  `response` LONGTEXT DEFAULT NULL,
  `request_key` VARCHAR(160) DEFAULT NULL,
  `attempt_no` INT NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `lead_api_logs_batch_id_index` (`batch_id`),
  KEY `lead_api_logs_lead_id_index` (`lead_id`),
  KEY `lead_api_logs_college_name_index` (`college_name`),
  KEY `lead_api_logs_status_index` (`status`),
  KEY `lead_api_logs_request_key_index` (`request_key`),
  KEY `lead_api_logs_job_token_index` (`job_token`)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;
