<?php

/**
 * LoginGuard user-plugin installer lifecycle helper.
 *
 * Schema ownership is intentionally delegated to the manifest install SQL,
 * versioned update migrations, and uninstall SQL. Runtime authentication code
 * and installer PHP do not reconcile or mutate table structure independently.
 */

defined('_JEXEC') or die;

class PlgUserLoginGuardInstallerScript
{
    public function install($adapter): bool
    {
        return true;
    }

    public function update($adapter): bool
    {
        return true;
    }

    public function postflight($type, $adapter): bool
    {
        return true;
    }

    public function uninstall($adapter): bool
    {
        return true;
    }
}
