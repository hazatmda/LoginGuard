CREATE TABLE IF NOT EXISTS `#__loginguard_admin_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `actor_user_id` int unsigned NOT NULL DEFAULT 0,
  `actor_username` varchar(255) NOT NULL DEFAULT '',
  `action` varchar(64) NOT NULL,
  `target_type` varchar(64) NOT NULL,
  `target_id` text NULL DEFAULT NULL,
  `details` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_admin_audit_actor` (`actor_user_id`),
  KEY `idx_loginguard_admin_audit_action` (`action`),
  KEY `idx_loginguard_admin_audit_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
