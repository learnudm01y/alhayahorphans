-- ============================================================================
-- android-v4 — Migration جاهزة (SQL خام)
-- قاعدة: aso  |  لا يُعدَّل أي جدول/عمود موجود، فقط إضافة (ALTER ADD / CREATE)
-- طُبِّق على: data, re_people, dead_pepoles, additional_deceased,
--             guardian_bank_accounts, sponsorships
-- ⚠️ يُنفَّذ على نسخة staging أولاً، ثم نسخة احتياطية كاملة قبل production
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) أعمدة جديدة (Nullable — لا تُغيّر أي سلوك حالي)
-- ----------------------------------------------------------------------------

ALTER TABLE `data`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;

ALTER TABLE `re_people`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;

ALTER TABLE `dead_pepoles`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;

ALTER TABLE `additional_deceased`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;

ALTER TABLE `guardian_bank_accounts`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;

ALTER TABLE `sponsorships`
  ADD COLUMN `client_uuid` CHAR(36) NULL UNIQUE AFTER `id`,
  ADD COLUMN `sync_origin_device_id` VARCHAR(64) NULL AFTER `client_uuid`,
  ADD COLUMN `needs_review` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sync_origin_device_id`;


-- ----------------------------------------------------------------------------
-- 2) Backfill لمرة واحدة — توليد client_uuid لكل سجل قديم بدون قيمة
--    (MySQL 8+ يدعم UUID() مباشرة؛ يُنفَّذ على دفعات إن كانت الجداول كبيرة)
-- ----------------------------------------------------------------------------

UPDATE `data`                   SET client_uuid = UUID() WHERE client_uuid IS NULL;
UPDATE `re_people`               SET client_uuid = UUID() WHERE client_uuid IS NULL;
UPDATE `dead_pepoles`            SET client_uuid = UUID() WHERE client_uuid IS NULL;
UPDATE `additional_deceased`     SET client_uuid = UUID() WHERE client_uuid IS NULL;
UPDATE `guardian_bank_accounts`  SET client_uuid = UUID() WHERE client_uuid IS NULL;
UPDATE `sponsorships`            SET client_uuid = UUID() WHERE client_uuid IS NULL;

-- ⚠️ لجداول كبيرة (>100K صف) نفّذ على دفعات لتفادي قفل طويل، مثال لجدول data:
-- UPDATE `data` SET client_uuid = UUID() WHERE client_uuid IS NULL LIMIT 5000;
-- (كرّر حتى ROW_COUNT() = 0)


-- ----------------------------------------------------------------------------
-- 3) Triggers — تغطية تلقائية لأي إدراج مستقبلي (من الموقع أو v3 أو v4)
--    بدون تعديل أي كود PHP قديم إطلاقاً
-- ----------------------------------------------------------------------------

DELIMITER $$

CREATE TRIGGER `trg_data_v4_client_uuid`
BEFORE INSERT ON `data`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_re_people_v4_client_uuid`
BEFORE INSERT ON `re_people`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_dead_pepoles_v4_client_uuid`
BEFORE INSERT ON `dead_pepoles`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_additional_deceased_v4_client_uuid`
BEFORE INSERT ON `additional_deceased`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_guardian_bank_accounts_v4_client_uuid`
BEFORE INSERT ON `guardian_bank_accounts`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

CREATE TRIGGER `trg_sponsorships_v4_client_uuid`
BEFORE INSERT ON `sponsorships`
FOR EACH ROW
BEGIN
  IF NEW.client_uuid IS NULL THEN
    SET NEW.client_uuid = UUID();
  END IF;
END$$

DELIMITER ;


-- ----------------------------------------------------------------------------
-- 4) جداول جديدة بالكامل — لا علاقة لها بأي جدول قديم
-- ----------------------------------------------------------------------------

CREATE TABLE `sync_outbox_v4` (
  `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `client_uuid`        CHAR(36) NOT NULL,
  `device_id`          VARCHAR(64) NOT NULL,
  `entity_type`        VARCHAR(64) NOT NULL,        -- 'person' | 'bank_account' | 'sponsorship' | 'file_op' ...
  `operation_type`      VARCHAR(32) NOT NULL,        -- 'create' | 'update' | 'delete'
  `payload_json`        JSON NOT NULL,
  `idempotency_key`     CHAR(64) NOT NULL,            -- SHA-256 hex
  `status`               VARCHAR(20) NOT NULL DEFAULT 'pending', -- pending|applied|conflict|failed
  `created_at_device`    DATETIME NOT NULL,
  `server_received_at`   DATETIME NULL,
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_idempotency_key` (`idempotency_key`),
  KEY `idx_device_status` (`device_id`, `status`),
  KEY `idx_entity` (`entity_type`, `client_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `sync_idempotency_log_v4` (
  `id`                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `idempotency_key`    CHAR(64) NOT NULL,
  `response_json`      JSON NOT NULL,
  `entity_type`         VARCHAR(64) NOT NULL,
  `entity_id`           BIGINT UNSIGNED NULL,
  `created_at`           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_key` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `device_registry_v4` (
  `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `device_id`           VARCHAR(64) NOT NULL,
  `device_label`         VARCHAR(128) NULL,           -- اسم مستعار يدوي: "جهاز موظف كذا"
  `user_id`               BIGINT UNSIGNED NULL,          -- ربط بجدول users القديم (بدون FK صارم)
  `app_version`            VARCHAR(20) NULL,
  `last_seen_at`            DATETIME NULL,
  `last_sync_at`            DATETIME NULL,
  `pending_ops_count`        INT NOT NULL DEFAULT 0,
  `health_status`             VARCHAR(20) NOT NULL DEFAULT 'unknown', -- healthy|delayed|stale|error
  `created_at`                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                 TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_device_id` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `conflict_review_queue_v4` (
  `id`                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entity_type`          VARCHAR(64) NOT NULL,
  `existing_record_id`     BIGINT UNSIGNED NOT NULL,
  `existing_record_uuid`    CHAR(36) NULL,
  `incoming_payload_json`    JSON NOT NULL,
  `incoming_source`           VARCHAR(20) NOT NULL,        -- 'web' | 'app_v3' | 'app_v4'
  `incoming_device_id`         VARCHAR(64) NULL,
  `match_reason`                VARCHAR(255) NOT NULL,       -- 'national_id_match' | 'name_dob_match' ...
  `match_confidence`             DECIMAL(4,2) NULL,           -- 0.00–1.00
  `status`                        VARCHAR(20) NOT NULL DEFAULT 'open', -- open|merged|kept_separate|dismissed
  `resolved_by_user_id`            BIGINT UNSIGNED NULL,
  `resolved_at`                     DATETIME NULL,
  `created_at`                       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_status` (`status`),
  KEY `idx_entity_existing` (`entity_type`, `existing_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin_dashboard_snapshot_v4` (
  `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `snapshot_key`       VARCHAR(64) NOT NULL,           -- 'total_cases' | 'active_sponsorships' ...
  `snapshot_value_json` JSON NOT NULL,
  `generated_at`         DATETIME NOT NULL,
  `created_at`             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_snapshot_key` (`snapshot_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `admin_reports_source_v4` (
  `id`                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_type`          VARCHAR(64) NOT NULL,
  `row_data_json`          JSON NOT NULL,
  `source_client_uuid`      CHAR(36) NULL,
  `last_updated_at`          DATETIME NOT NULL,
  `created_at`                TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_report_type` (`report_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `permissions_manifest_v4` (
  `id`               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`             BIGINT UNSIGNED NOT NULL,
  `manifest_json`         JSON NOT NULL,
  `signature`               VARCHAR(255) NOT NULL,        -- HMAC-SHA256
  `issued_at`                 DATETIME NOT NULL,
  `expires_at`                 DATETIME NOT NULL,
  `created_at`                   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================================
-- ROLLBACK (سكربت تراجع كامل — يُختبر إلزامياً على staging قبل الاعتماد)
-- ============================================================================
-- DROP TRIGGER IF EXISTS trg_data_v4_client_uuid;
-- DROP TRIGGER IF EXISTS trg_re_people_v4_client_uuid;
-- DROP TRIGGER IF EXISTS trg_dead_pepoles_v4_client_uuid;
-- DROP TRIGGER IF EXISTS trg_additional_deceased_v4_client_uuid;
-- DROP TRIGGER IF EXISTS trg_guardian_bank_accounts_v4_client_uuid;
-- DROP TRIGGER IF EXISTS trg_sponsorships_v4_client_uuid;
--
-- DROP TABLE IF EXISTS permissions_manifest_v4;
-- DROP TABLE IF EXISTS admin_reports_source_v4;
-- DROP TABLE IF EXISTS admin_dashboard_snapshot_v4;
-- DROP TABLE IF EXISTS conflict_review_queue_v4;
-- DROP TABLE IF EXISTS device_registry_v4;
-- DROP TABLE IF EXISTS sync_idempotency_log_v4;
-- DROP TABLE IF EXISTS sync_outbox_v4;
--
-- ALTER TABLE `data`                  DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;
-- ALTER TABLE `re_people`              DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;
-- ALTER TABLE `dead_pepoles`           DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;
-- ALTER TABLE `additional_deceased`    DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;
-- ALTER TABLE `guardian_bank_accounts` DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;
-- ALTER TABLE `sponsorships`           DROP COLUMN client_uuid, DROP COLUMN sync_origin_device_id, DROP COLUMN needs_review;

-- ============================================================
-- record_audit_log_v4  (معتمد صاحب المشروع 2026-09-23 — الخيار أ)
-- Live migration: database/migrations/2026_09_23_000003_create_record_audit_log_v4_table.php
-- جدول جديد مستقل — لا يلمس أي جدول قائم.
-- ============================================================
CREATE TABLE IF NOT EXISTS `record_audit_log_v4` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entity_type` VARCHAR(64) NOT NULL,
  `client_uuid` CHAR(36) NOT NULL,
  `record_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `operation` VARCHAR(32) NOT NULL COMMENT 'create|update|blocked_sensitive|delete',
  `field_name` VARCHAR(128) NULL DEFAULT NULL,
  `old_value` TEXT NULL,
  `new_value` TEXT NULL,
  `is_sensitive` TINYINT(1) NOT NULL DEFAULT 0,
  `changed_by_device` VARCHAR(64) NULL DEFAULT NULL,
  `changed_by_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `idempotency_key` CHAR(64) NULL DEFAULT NULL,
  `source` VARCHAR(32) NOT NULL DEFAULT 'app_v4',
  `changed_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ral_client_time_idx` (`client_uuid`, `changed_at`),
  KEY `ral_entity_record_idx` (`entity_type`, `record_id`),
  KEY `ral_device_time_idx` (`changed_by_device`, `changed_at`),
  KEY `ral_field_sens_idx` (`field_name`, `is_sensitive`),
  KEY `record_audit_log_v4_idempotency_key_index` (`idempotency_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
