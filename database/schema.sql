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

INSERT INTO `admins` (`email`, `password`)
VALUES ('admin@example.com', '$2y$10$5WatyiUIFK5kZeDFI0WAhOY.4J7q0exI0QVmpBSFBwWaq5iPxLr1q')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);
