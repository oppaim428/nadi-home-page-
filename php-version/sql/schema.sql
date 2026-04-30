-- ============================================================
-- NadiPlayer — MySQL Schema
-- For cPanel: create a database first (cPanel → MySQL Databases),
-- then run install.php which executes this schema automatically.
-- This file is provided so you can also import it manually via phpMyAdmin.
-- ============================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------
-- Admins
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(64)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role`          VARCHAR(32)  NOT NULL DEFAULT 'admin',
  `created_at`    VARCHAR(40)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Site configuration (single document, JSON-encoded)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `site_config` (
  `id`         VARCHAR(64) NOT NULL,
  `data`       LONGTEXT    NOT NULL,
  `updated_at` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Pages (custom landing/legal/info pages, EN+AR)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `pages` (
  `id`          VARCHAR(64)  NOT NULL,
  `slug`        VARCHAR(80)  NOT NULL,
  `title_en`    VARCHAR(255) NOT NULL DEFAULT '',
  `title_ar`    VARCHAR(255) NOT NULL DEFAULT '',
  `content_en`  LONGTEXT     NOT NULL,
  `content_ar`  LONGTEXT     NOT NULL,
  `show_in_nav` TINYINT(1)   NOT NULL DEFAULT 0,
  `published`   TINYINT(1)   NOT NULL DEFAULT 1,
  `updated_at`  VARCHAR(40)  NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
