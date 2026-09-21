-- Schul-Dashboard: Update-Script – Neue Tabellen
-- In phpMyAdmin: Importieren → diese Datei → OK
-- Bestehende Daten bleiben erhalten (CREATE TABLE IF NOT EXISTS)

USE `dbs15593008`;

-- Lehrerkürzel pro Mandant
CREATE TABLE IF NOT EXISTS `lehrer` (
    `tenant_id` INT UNSIGNED  NOT NULL,
    `kuerzel`   VARCHAR(20)   NOT NULL,
    `vorname`   VARCHAR(100)  NULL,
    `nachname`  VARCHAR(100)  NOT NULL,
    PRIMARY KEY (`tenant_id`, `kuerzel`),
    CONSTRAINT `fk_lehrer_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fächerkürzel pro Mandant
CREATE TABLE IF NOT EXISTS `faecher` (
    `tenant_id` INT UNSIGNED  NOT NULL,
    `kuerzel`   VARCHAR(20)   NOT NULL,
    `vollname`  VARCHAR(100)  NOT NULL,
    PRIMARY KEY (`tenant_id`, `kuerzel`),
    CONSTRAINT `fk_faecher_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Notizen zu Terminen
CREATE TABLE IF NOT EXISTS `event_notes` (
    `event_id`   INT UNSIGNED NOT NULL,
    `tenant_id`  INT UNSIGNED NOT NULL,
    `content`    MEDIUMTEXT   NOT NULL,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                              ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`event_id`),
    CONSTRAINT `fk_notes_event`
        FOREIGN KEY (`event_id`)  REFERENCES `events`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_notes_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
