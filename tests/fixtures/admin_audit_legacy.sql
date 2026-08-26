CREATE TABLE `#__loginguard_admin_audit` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int NOT NULL DEFAULT 0,
  `target_ip` varchar(45) NOT NULL DEFAULT '',
  `actor_user_id` int NOT NULL DEFAULT 0,
  `details` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `#__loginguard_admin_audit`
  (`id`, `action`, `target_type`, `target_id`, `target_ip`, `actor_user_id`, `details`, `created`)
VALUES
  (17, 'blocked_ip.delete', 'blocked_ip', 42, '192.0.2.10', 7, '{"source":"legacy"}', '2026-08-25 12:00:00');
