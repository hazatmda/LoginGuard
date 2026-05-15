<?php

/**
 * LoginGuard Joomla installer lifecycle helper.
 *
 * The plugin manifest owns the audit table SQL because the user plugin writes
 * login events. This script runs only during Joomla install/update/uninstall
 * lifecycle operations and keeps upgrades data-preserving when older alpha
 * schemas are already present.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

class PlgUserLoginGuardInstallerScript
{
    /**
     * Create or reconcile the audit table after fresh installs and package installs.
     *
     * @param   mixed  $adapter  Joomla installer adapter.
     */
    public function install($adapter): bool
    {
        return $this->ensureSchema();
    }

    /**
     * Preserve existing rows while adding any schema introduced by this release.
     *
     * @param   mixed  $adapter  Joomla installer adapter.
     */
    public function update($adapter): bool
    {
        return $this->ensureSchema();
    }

    /**
     * Keep package/plugin uninstall cleanup deterministic.
     *
     * @param   mixed  $adapter  Joomla installer adapter.
     */
    public function uninstall($adapter): bool
    {
        try {
            $db = $this->getDatabase();
            $db->setQuery('DROP TABLE IF EXISTS `#__loginguard_blocked_ips`')->execute();
            $db->setQuery('DROP TABLE IF EXISTS `#__loginguard_attempts`')->execute();
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    /**
     * Re-run schema reconciliation for package update paths that invoke postflight.
     *
     * @param   string  $type     Install action type.
     * @param   mixed   $adapter  Joomla installer adapter.
     */
    public function postflight($type, $adapter): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        return $this->ensureSchema();
    }

    private function ensureSchema(): bool
    {
        try {
            $db = $this->getDatabase();
            $db->setQuery($this->getCreateTableSql())->execute();
            $this->addMissingColumns($db);
            $db->setQuery($this->getBlockedIpsCreateSql())->execute();
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    private function getDatabase(): DatabaseDriver
    {
        try {
            return Factory::getContainer()->get(DatabaseDriver::class);
        } catch (\Throwable $exception) {
            return Factory::getDbo();
        }
    }

    private function addMissingColumns(DatabaseDriver $db): void
    {
        $existing = [];

        foreach ($db->getTableColumns('#__loginguard_attempts') as $column => $type) {
            $existing[$column] = true;
        }

        $columns = [
            'user_id' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `user_id` int NOT NULL DEFAULT 0 AFTER `id`",
            'name' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `name` varchar(255) NOT NULL DEFAULT '' AFTER `user_id`",
            'username' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `username` varchar(255) NOT NULL DEFAULT '' AFTER `name`",
            'email' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '' AFTER `username`",
            'ip_address' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `ip_address` varchar(255) NOT NULL DEFAULT '' AFTER `email`",
            'status' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `status` varchar(20) NOT NULL DEFAULT 'FAILED_LOGIN' AFTER `ip_address`",
            'browser' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `browser` varchar(100) NOT NULL DEFAULT '' AFTER `status`",
            'operating_system' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `operating_system` varchar(100) NOT NULL DEFAULT '' AFTER `browser`",
            'country' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country` varchar(100) NOT NULL DEFAULT '' AFTER `operating_system`",
            'country_code' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country_code` varchar(10) NOT NULL DEFAULT '' AFTER `country`",
            'region' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `region` varchar(100) NOT NULL DEFAULT '' AFTER `country_code`",
            'city' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `city` varchar(100) NOT NULL DEFAULT '' AFTER `region`",
            'isp' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `isp` varchar(255) NOT NULL DEFAULT '' AFTER `city`",
            'asn' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `asn` varchar(50) NOT NULL DEFAULT '' AFTER `isp`",
            'where_at' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `where_at` varchar(50) NOT NULL DEFAULT 'frontend' AFTER `country`",
            'user_agent' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `user_agent` text NOT NULL AFTER `where_at`",
            'attempt_type' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `attempt_type` varchar(50) NOT NULL DEFAULT 'login' AFTER `user_agent`",
            'client' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `client` varchar(50) NOT NULL DEFAULT 'frontend' AFTER `attempt_type`",
            'reason' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `reason` text NOT NULL AFTER `client`",
            'created' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `created` datetime NOT NULL AFTER `reason`",
        ];

        foreach ($columns as $column => $sql) {
            if (isset($existing[$column])) {
                continue;
            }

            $db->setQuery($sql)->execute();
        }
    }


    private function getBlockedIpsCreateSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS `#__loginguard_blocked_ips` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
    }

    private function getCreateTableSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS `#__loginguard_attempts` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `username` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `ip_address` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(20) NOT NULL DEFAULT 'FAILED_LOGIN',
  `browser` varchar(100) NOT NULL DEFAULT '',
  `operating_system` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT '',
  `country_code` varchar(10) NOT NULL DEFAULT '',
  `region` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `isp` varchar(255) NOT NULL DEFAULT '',
  `asn` varchar(50) NOT NULL DEFAULT '',
  `where_at` varchar(50) NOT NULL DEFAULT 'frontend',
  `user_agent` text NOT NULL,
  `attempt_type` varchar(50) NOT NULL DEFAULT 'login',
  `client` varchar(50) NOT NULL DEFAULT 'frontend',
  `reason` text NOT NULL,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_user_id` (`user_id`),
  KEY `idx_loginguard_status` (`status`),
  KEY `idx_loginguard_created` (`created`),
  KEY `idx_loginguard_where_at` (`where_at`),
  KEY `idx_loginguard_client` (`client`),
  KEY `idx_loginguard_username` (`username`),
  KEY `idx_loginguard_attempt_type` (`attempt_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
    }
}
