CREATE DATABASE IF NOT EXISTS `lead_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `lead_management`;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admins_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `api_colleagues` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `colleague_name` VARCHAR(190) NOT NULL,
  `college_id` VARCHAR(100) NOT NULL,
  `region` VARCHAR(50) NOT NULL,
  `api_endpoint` VARCHAR(255) NOT NULL,
  `api_token` VARCHAR(255) DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_colleagues_unique` (`colleague_name`, `college_id`, `region`),
  KEY `api_colleagues_region_status_index` (`region`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lead_push_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `admins` (`email`, `password`)
VALUES ('admin@example.com', '$2y$10$5WatyiUIFK5kZeDFI0WAhOY.4J7q0exI0QVmpBSFBwWaq5iPxLr1q')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

INSERT INTO `api_colleagues` (`colleague_name`, `college_id`, `region`, `api_endpoint`, `api_token`, `status`)
VALUES
  ('Amit Kumar', 'IBI', 'South', 'https://api.nopaperforms.com/dataporting/578/career_mantra', '76f848961f95f47d9a9c7ce4f80d41c9', 1),
  ('Ravi Sharma', 'Sunstone', 'South', 'https://hub-console-api.sunstone.in/lead/leadPush', 'cac0713d-076b-4817-b713-af8bc49e5a66', 1),
  ('Neha Singh', 'IILM', 'South', 'https://api.nopaperforms.com/dataporting/377/career_mantra', '8a44b9c9743ed51af2795dea2f3bc7e4', 1),
  ('Vikas Arora', 'IBI', 'North', 'https://api.nopaperforms.com/dataporting/578/career_mantra', '76f848961f95f47d9a9c7ce4f80d41c9', 1),
  ('Ananya Sen', 'IILM', 'East', 'https://api.nopaperforms.com/dataporting/377/career_mantra', '8a44b9c9743ed51af2795dea2f3bc7e4', 1),
  ('Mihir Shah', 'Sunstone', 'West / Others', 'https://hub-console-api.sunstone.in/lead/leadPush', 'cac0713d-076b-4817-b713-af8bc49e5a66', 1)
ON DUPLICATE KEY UPDATE
  `api_endpoint` = VALUES(`api_endpoint`),
  `api_token` = VALUES(`api_token`),
  `status` = VALUES(`status`);
