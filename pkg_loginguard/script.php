<?php

/**
 * LoginGuard package installer lifecycle helper.
 *
 * Joomla's package adapter owns installation and removal of the child plugin and
 * component. The package remains a bootstrap installer while com_loginguard owns
 * updater authority. This script keeps package-child metadata and component
 * update-site bindings synchronized so upgrades, rollbacks, and package
 * uninstalls do not leave stale lifecycle state behind.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseDriver;

class Pkg_LoginguardInstallerScript
{
    /**
     * Remove stale package-child links before Joomla reconciles this package.
     *
     * @param   string  $type     Install action type.
     * @param   mixed   $adapter  Joomla installer adapter.
     */
    public function preflight($type, $adapter): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install', 'uninstall'], true)) {
            return true;
        }

        if ($type !== 'uninstall') {
            try {
                $this->reconcileAdminAuditSchema($this->getDatabase());
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    'LoginGuard could not repair the administrator audit schema: ' . $exception->getMessage(),
                    0,
                    $exception
                );
            }
        }

        return $this->synchroniseChildExtensions($type === 'uninstall');
    }

    /**
     * Repair legacy audit tables before Joomla runs the component SQL updates.
     *
     * Fresh installs intentionally return when the table is absent: the child
     * component's install SQL remains responsible for creating it.
     */
    private function reconcileAdminAuditSchema(DatabaseDriver $db): void
    {
        $table = $db->replacePrefix('#__loginguard_admin_audit');

        if (!in_array($table, $db->getTableList(), true)) {
            return;
        }

        $columns = $db->getTableColumns($table, false);

        if (!isset($columns['actor_username'])) {
            $this->runSchemaStatement($db, 'ALTER TABLE ' . $db->quoteName('#__loginguard_admin_audit')
                . ' ADD COLUMN ' . $db->quoteName('actor_username')
                . " varchar(255) NOT NULL DEFAULT '' AFTER " . $db->quoteName('actor_user_id'));
            $columns = $db->getTableColumns($table, false);
        }

        $this->runSchemaStatement($db, 'UPDATE ' . $db->quoteName('#__loginguard_admin_audit', 'audit')
            . ' LEFT JOIN ' . $db->quoteName('#__users', 'users')
            . ' ON ' . $db->quoteName('users.id') . ' = ' . $db->quoteName('audit.actor_user_id')
            . ' SET ' . $db->quoteName('audit.actor_username') . ' = COALESCE('
            . $db->quoteName('users.username') . ", '')"
            . ' WHERE ' . $db->quoteName('audit.actor_username') . " = ''");

        $modifications = [];
        if (!$this->columnMatches($columns['id'] ?? null, 'bigint unsigned', false)
            || !$this->columnExtraMatches($columns['id'] ?? null, 'auto_increment')) {
            $modifications[] = 'MODIFY ' . $db->quoteName('id') . ' bigint unsigned NOT NULL AUTO_INCREMENT';
        }
        if (!$this->columnMatches($columns['actor_user_id'] ?? null, 'int unsigned', false)
            || !$this->columnDefaultMatches($columns['actor_user_id'] ?? null, '0')) {
            $modifications[] = 'MODIFY ' . $db->quoteName('actor_user_id') . ' int unsigned NOT NULL DEFAULT 0';
        }
        if (!$this->columnMatches($columns['target_id'] ?? null, 'text', true)) {
            $modifications[] = 'MODIFY ' . $db->quoteName('target_id') . ' text NULL DEFAULT NULL';
        }
        if (!$this->columnMatches($columns['action'] ?? null, 'varchar(64)', false)) {
            $modifications[] = 'MODIFY ' . $db->quoteName('action') . ' varchar(64) NOT NULL';
        }
        if (!$this->columnMatches($columns['target_type'] ?? null, 'varchar(64)', false)) {
            $modifications[] = 'MODIFY ' . $db->quoteName('target_type') . ' varchar(64) NOT NULL';
        }
        if ($modifications !== []) {
            $this->runSchemaStatement($db, 'ALTER TABLE ' . $db->quoteName('#__loginguard_admin_audit')
                . ' ' . implode(', ', $modifications));
        }

        $keys = $db->getTableKeys($table);
        $existingKeys = [];
        foreach ($keys as $key) {
            $name = $key->Key_name ?? $key->key_name ?? null;
            if (is_string($name)) {
                $existingKeys[$name] = true;
            }
        }
        $requiredKeys = [
            'idx_loginguard_admin_audit_actor' => 'actor_user_id',
            'idx_loginguard_admin_audit_action' => 'action',
            'idx_loginguard_admin_audit_created' => 'created',
        ];
        foreach ($requiredKeys as $name => $column) {
            if (!isset($existingKeys[$name])) {
                $this->runSchemaStatement($db, 'ALTER TABLE ' . $db->quoteName('#__loginguard_admin_audit')
                    . ' ADD INDEX ' . $db->quoteName($name) . ' (' . $db->quoteName($column) . ')');
            }
        }
    }

    private function columnMatches(?object $column, string $type, bool $nullable): bool
    {
        if ($column === null) {
            return false;
        }

        $actualType = strtolower((string) ($column->Type ?? $column->type ?? ''));
        $actualNullable = strtoupper((string) ($column->Null ?? $column->null ?? 'NO')) === 'YES';

        return $actualType === $type && $actualNullable === $nullable;
    }

    private function columnDefaultMatches(?object $column, string $default): bool
    {
        if ($column === null) {
            return false;
        }

        return (string) ($column->Default ?? $column->default ?? '') === $default;
    }

    private function columnExtraMatches(?object $column, string $extra): bool
    {
        if ($column === null) {
            return false;
        }

        return strtolower((string) ($column->Extra ?? $column->extra ?? '')) === strtolower($extra);
    }

    private function runSchemaStatement(DatabaseDriver $db, string $sql): void
    {
        $db->setQuery($sql)->execute();
    }

    /**
     * Reconcile package-child links after package install/update paths.
     *
     * @param   string  $type     Install action type.
     * @param   mixed   $adapter  Joomla installer adapter.
     */
    public function postflight($type, $adapter): bool
    {
        if (!in_array($type, ['install', 'update', 'discover_install'], true)) {
            return true;
        }

        $this->synchroniseChildExtensions(false);
        $this->repairUpdateSiteRegistration();
        $this->enableChildExtension('plugin', 'loginguardcleanup', 'task');

        return true;
    }

    /**
     * Keep package uninstall idempotent if a child extension was removed earlier.
     *
     * @param   mixed  $adapter  Joomla installer adapter.
     */
    public function uninstall($adapter): bool
    {
        return $this->synchroniseChildExtensions(true);
    }

    private function synchroniseChildExtensions(bool $isUninstall): bool
    {
        try {
            $db = $this->getDatabase();
            $packageId = $this->getExtensionId($db, 'package', 'pkg_loginguard', '');

            foreach ($this->getChildExtensionDefinitions() as $child) {
                $childId = $this->getExtensionId($db, $child['type'], $child['element'], $child['folder']);

                if ($childId === 0) {
                    $this->deleteStaleUpdateSiteMappings($db);
                    continue;
                }

                if ($packageId > 0) {
                    $this->setPackageId($db, $childId, $isUninstall ? 0 : $packageId);
                }
            }
        } catch (\Throwable $exception) {
            // Registry cleanup is best-effort and must never block package lifecycle actions.
            return true;
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

    /**
     * @return list<array{type: string, element: string, folder: string}>
     */
    private function getChildExtensionDefinitions(): array
    {
        return [
            ['type' => 'plugin', 'element' => 'loginguard', 'folder' => 'user'],
            ['type' => 'plugin', 'element' => 'loginguardcleanup', 'folder' => 'task'],
            ['type' => 'component', 'element' => 'com_loginguard', 'folder' => ''],
        ];
    }

    private function enableChildExtension(string $type, string $element, string $folder): void
    {
        try {
            $db = $this->getDatabase();
            $extensionId = $this->getExtensionId($db, $type, $element, $folder);

            if ($extensionId === 0) {
                return;
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('extension_id') . ' = ' . (int) $extensionId);
            $db->setQuery($query)->execute();
        } catch (\Throwable $exception) {
            // Enabling the scheduler plugin is best-effort and must not block installs.
        }
    }

    private function getExtensionId(DatabaseDriver $db, string $type, string $element, string $folder): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('extension_id'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote($type))
            ->where($db->quoteName('element') . ' = ' . $db->quote($element));

        if ($folder !== '') {
            $query->where($db->quoteName('folder') . ' = ' . $db->quote($folder));
        }

        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }

    private function setPackageId(DatabaseDriver $db, int $extensionId, int $packageId): void
    {
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('package_id') . ' = ' . (int) $packageId)
            ->where($db->quoteName('extension_id') . ' = ' . (int) $extensionId);

        $db->setQuery($query)->execute();
    }

    private function repairUpdateSiteRegistration(): void
    {
        try {
            $db = $this->getDatabase();
            $packageId = $this->getExtensionId($db, 'package', 'pkg_loginguard', '');
            $componentId = $this->getExtensionId($db, 'component', 'com_loginguard', '');

            if ($componentId === 0) {
                return;
            }

            $this->deleteStaleUpdateSiteMappings($db);
            $updateSiteId = $this->ensureUpdateSite($db);
            $this->bindUpdateSiteToExtension($db, $updateSiteId, $componentId);
            $this->removeDuplicateComponentUpdateSiteBindings($db, $updateSiteId, $componentId);

            if ($packageId > 0) {
                $this->removePackageUpdateSiteBindings($db, $packageId);
            }
        } catch (\Throwable $exception) {
            // Update-site repair is best-effort and must never block package installs or updates.
        }
    }

    private function ensureUpdateSite(DatabaseDriver $db): int
    {
        $name = 'LoginGuard Updates';
        $location = 'https://raw.githubusercontent.com/hazatmda/LoginGuard/main/updates/loginguard.xml';
        $type = 'extension';
        $updateSiteId = $this->getUpdateSiteId($db, $name, $location);

        if ($updateSiteId > 0) {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__update_sites'))
                ->set($db->quoteName('name') . ' = ' . $db->quote($name))
                ->set($db->quoteName('type') . ' = ' . $db->quote($type))
                ->set($db->quoteName('location') . ' = ' . $db->quote($location))
                ->set($db->quoteName('enabled') . ' = 1')
                ->set($db->quoteName('last_check_timestamp') . ' = 0')
                ->where($db->quoteName('update_site_id') . ' = ' . (int) $updateSiteId);
            $db->setQuery($query)->execute();

            return $updateSiteId;
        }

        $columns = ['name', 'type', 'location', 'enabled', 'last_check_timestamp'];
        $values = [$db->quote($name), $db->quote($type), $db->quote($location), '1', '0'];
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__update_sites'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query)->execute();

        return (int) $db->insertid();
    }

    private function getUpdateSiteId(DatabaseDriver $db, string $name, string $location): int
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where('(' . $db->quoteName('location') . ' = ' . $db->quote($location) . ' OR ' . $db->quoteName('name') . ' = ' . $db->quote($name) . ')')
            ->order($db->quoteName('update_site_id') . ' DESC');

        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }

    private function bindUpdateSiteToExtension(DatabaseDriver $db, int $updateSiteId, int $extensionId): void
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('update_site_id') . ' = ' . (int) $updateSiteId)
            ->where($db->quoteName('extension_id') . ' = ' . (int) $extensionId);
        $db->setQuery($query);

        if ((int) $db->loadResult() > 0) {
            return;
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__update_sites_extensions'))
            ->columns($db->quoteName(['update_site_id', 'extension_id']))
            ->values((int) $updateSiteId . ',' . (int) $extensionId);
        $db->setQuery($query)->execute();
    }

    private function removeDuplicateComponentUpdateSiteBindings(DatabaseDriver $db, int $canonicalUpdateSiteId, int $componentId): void
    {
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $componentId)
            ->where($db->quoteName('update_site_id') . ' <> ' . (int) $canonicalUpdateSiteId)
            ->where($db->quoteName('update_site_id') . ' IN (SELECT ' . $db->quoteName('update_site_id') . ' FROM ' . $db->quoteName('#__update_sites') . ' WHERE ' . $db->quoteName('name') . ' LIKE ' . $db->quote('%LoginGuard%') . ')');
        $db->setQuery($query)->execute();
    }

    private function removePackageUpdateSiteBindings(DatabaseDriver $db, int $packageId): void
    {
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $packageId)
            ->where($db->quoteName('update_site_id') . ' IN (SELECT ' . $db->quoteName('update_site_id') . ' FROM ' . $db->quoteName('#__update_sites') . ' WHERE ' . $db->quoteName('name') . ' LIKE ' . $db->quote('%LoginGuard%') . ')');
        $db->setQuery($query)->execute();
    }

    private function deleteStaleUpdateSiteMappings(DatabaseDriver $db): void
    {
        $query = $db->getQuery(true)
            ->select($db->quoteName('update_site_id'))
            ->from($db->quoteName('#__update_sites'))
            ->where($db->quoteName('name') . ' LIKE ' . $db->quote('%LoginGuard%'));

        $db->setQuery($query);
        $updateSiteIds = array_map('intval', (array) $db->loadColumn());

        if ($updateSiteIds === []) {
            return;
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__update_sites_extensions'))
            ->where($db->quoteName('update_site_id') . ' IN (' . implode(',', $updateSiteIds) . ')')
            ->where($db->quoteName('extension_id') . ' NOT IN (SELECT ' . $db->quoteName('extension_id') . ' FROM ' . $db->quoteName('#__extensions') . ')');

        $db->setQuery($query)->execute();
    }
}
