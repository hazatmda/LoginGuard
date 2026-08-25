-- LoginGuard 0.2.21 security, reliability, MFA audit, and operational hardening.

ALTER TABLE `#__loginguard_attempts`
  ADD COLUMN `mfa_method` varchar(100) NOT NULL DEFAULT '' AFTER `attempt_type`;

ALTER TABLE `#__loginguard_attempts`
  ADD KEY `idx_loginguard_ip_status_created` (`ip_address`, `status`, `created`),
  ADD KEY `idx_loginguard_user_status_created` (`user_id`, `status`, `created`);

ALTER TABLE `#__loginguard_blocked_ips`
  ADD COLUMN `source` varchar(20) NOT NULL DEFAULT 'manual' AFTER `reason`,
  ADD COLUMN `active_key` varchar(64) NULL DEFAULT NULL AFTER `source`,
  ADD COLUMN `updated` datetime NULL DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `updated_by` int NOT NULL DEFAULT 0 AFTER `updated`,
  ADD COLUMN `disabled_at` datetime NULL DEFAULT NULL AFTER `updated_by`,
  ADD COLUMN `disabled_by` int NOT NULL DEFAULT 0 AFTER `disabled_at`;

UPDATE `#__loginguard_blocked_ips`
   SET `source` = CASE WHEN `created_by` = 0 THEN 'automatic' ELSE 'manual' END;

-- Preserve the newest active row for each IP/scope and soft-disable older duplicates
-- before introducing the unique active-key constraint.
UPDATE `#__loginguard_blocked_ips` AS older
INNER JOIN `#__loginguard_blocked_ips` AS newer
        ON newer.`ip_address` = older.`ip_address`
       AND newer.`scope` = older.`scope`
       AND newer.`enabled` = 1
       AND older.`enabled` = 1
       AND newer.`id` > older.`id`
   SET older.`enabled` = 0,
       older.`disabled_at` = COALESCE(older.`disabled_at`, UTC_TIMESTAMP()),
       older.`active_key` = NULL;

UPDATE `#__loginguard_blocked_ips`
   SET `active_key` = SHA2(CONCAT(`ip_address`, '|', `scope`), 256)
 WHERE `enabled` = 1;

ALTER TABLE `#__loginguard_blocked_ips`
  ADD UNIQUE KEY `idx_loginguard_active_key` (`active_key`),
  ADD KEY `idx_loginguard_source` (`source`),
  ADD KEY `idx_loginguard_disabled_at` (`disabled_at`);

CREATE TABLE IF NOT EXISTS `#__loginguard_admin_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL DEFAULT '',
  `target_id` int NOT NULL DEFAULT 0,
  `target_ip` varchar(45) NOT NULL DEFAULT '',
  `actor_user_id` int NOT NULL DEFAULT 0,
  `details` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_admin_action` (`action`),
  KEY `idx_loginguard_admin_actor` (`actor_user_id`),
  KEY `idx_loginguard_admin_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__loginguard_health` (
  `health_key` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'healthy',
  `message` text NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`health_key`),
  KEY `idx_loginguard_health_status` (`status`),
  KEY `idx_loginguard_health_updated` (`updated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
