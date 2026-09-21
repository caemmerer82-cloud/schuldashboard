-- Schul-Dashboard: MySQL-Installationsskript für Strato (dbs15593008)
USE `dbs15593008`;

CREATE TABLE IF NOT EXISTS `tenants` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`name` VARCHAR(100) NOT NULL,`active` TINYINT(1) NOT NULL DEFAULT 1,`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_name` (`name`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `users` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`username` VARCHAR(100) NOT NULL,`password_hash` VARCHAR(255) NOT NULL,`role` ENUM('tenant_admin','user') NOT NULL DEFAULT 'user',`active` TINYINT(1) NOT NULL DEFAULT 1,`last_login` DATETIME NULL,`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_username` (`username`),KEY `idx_tenant` (`tenant_id`),CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `homework` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`subject` VARCHAR(100) NOT NULL,`title` VARCHAR(255) NOT NULL,`description` TEXT NULL,`due_date` DATE NOT NULL,`status` ENUM('todo','doing','done') NOT NULL DEFAULT 'todo',`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_tenant_due` (`tenant_id`,`due_date`),CONSTRAINT `fk_hw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `events` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`tenant_id` INT UNSIGNED NOT NULL,`event_type` ENUM('klausur','test','pruefung','veranstaltung','sonstiges') NOT NULL DEFAULT 'sonstiges',`title` VARCHAR(255) NOT NULL,`event_date` DATE NOT NULL,`notes` TEXT NULL,`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_tenant_date` (`tenant_id`,`event_date`),CONSTRAINT `fk_ev_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `settings` (`tenant_id` INT UNSIGNED NOT NULL,`skey` VARCHAR(64) NOT NULL,`svalue` TEXT NULL,PRIMARY KEY (`tenant_id`,`skey`),CONSTRAINT `fk_st_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Neue Tabellen: Lehrer, Fächer, Termin-Notizen
CREATE TABLE IF NOT EXISTS `lehrer` (
    `tenant_id` INT UNSIGNED NOT NULL,
    `kuerzel`   VARCHAR(20)  NOT NULL,
    `vorname`   VARCHAR(100) NULL,
    `nachname`  VARCHAR(100) NOT NULL,
    PRIMARY KEY (`tenant_id`, `kuerzel`),
    CONSTRAINT `fk_lehrer_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `faecher` (
    `tenant_id`  INT UNSIGNED NOT NULL,
    `kuerzel`    VARCHAR(20)  NOT NULL,
    `vollname`   VARCHAR(100) NOT NULL,
    PRIMARY KEY (`tenant_id`, `kuerzel`),
    CONSTRAINT `fk_faecher_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `event_notes` (
    `event_id`   INT UNSIGNED NOT NULL,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `content`    MEDIUMTEXT   NOT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`),
    CONSTRAINT `fk_notes_event`  FOREIGN KEY (`event_id`)  REFERENCES `events`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_notes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
