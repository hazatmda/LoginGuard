CREATE TABLE IF NOT EXISTS `#__loginguard_cleanup_runs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL,
  `finished_at` datetime NOT NULL,
  `attempts_deleted` int unsigned NOT NULL DEFAULT 0,
  `expired_blocks_deleted` int unsigned NOT NULL DEFAULT 0,
  `disabled_blocks_deleted` int unsigned NOT NULL DEFAULT 0,
  `total_deleted` int unsigned NOT NULL DEFAULT 0,
  `batches` int unsigned NOT NULL DEFAULT 0,
  `batch_size` int unsigned NOT NULL DEFAULT 0,
  `login_retention_days` int unsigned NOT NULL DEFAULT 0,
  `blocked_ip_retention_days` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_cleanup_finished_at` (`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
