-- Schul-Dashboard: AGs-Tabelle
-- In phpMyAdmin: Importieren → diese Datei → OK

USE `dbs15593008`;

CREATE TABLE IF NOT EXISTS `ags` (
    `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tenant_id`  INT UNSIGNED  NOT NULL,
    `name`       VARCHAR(100)  NOT NULL,
    `raum`       VARCHAR(50)   NULL,
    `leitung`    VARCHAR(100)  NULL,
    `wochentag`  TINYINT       NOT NULL DEFAULT 1
                               COMMENT '1=Mo 2=Di 3=Mi 4=Do 5=Fr',
    `von_datum`  DATE          NOT NULL COMMENT 'Beginn (Halbjahr)',
    `bis_datum`  DATE          NOT NULL COMMENT 'Ende (Halbjahr)',
    `aktiv`      TINYINT(1)    NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `idx_ag_tenant` (`tenant_id`, `wochentag`),
    CONSTRAINT `fk_ag_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
