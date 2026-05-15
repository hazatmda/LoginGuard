<?php

/**
 * LoginGuard package installer lifecycle helper.
 *
 * Joomla's package adapter owns installation and removal of the child plugin and
 * component. This script keeps the package registry synchronized around those
 * operations so upgrades, rollbacks, and package uninstalls do not leave stale
 * package-child metadata behind when an alpha child extension was removed first.
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

        return $this->synchroniseChildExtensions($type === 'uninstall');
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
                    $this->deleteOrphanUpdateSites($db);
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

    private function deleteOrphanUpdateSites(DatabaseDriver $db): void
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
