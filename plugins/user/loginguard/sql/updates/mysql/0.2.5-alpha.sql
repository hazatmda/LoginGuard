CREATE TABLE IF NOT EXISTS `#__loginguard_blocked_ips` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) NOT NULL DEFAULT '',
  `scope` varchar(20) NOT NULL DEFAULT 'all',
  `block_type` varchar(20) NOT NULL DEFAULT 'temporary',
  `reason` varchar(50) NOT NULL DEFAULT 'threshold_exceeded',
  `failure_count` int NOT NULL DEFAULT 0,
  `blocked_until` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_blocked_ip` (`ip_address`),
  KEY `idx_loginguard_blocked_scope` (`scope`),
  KEY `idx_loginguard_blocked_until` (`blocked_until`),
  KEY `idx_loginguard_blocked_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
