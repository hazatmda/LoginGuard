<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;

final class LoginGuardHelper
{
    public static function getActions(): CMSObject
    {
        $user = Factory::getApplication()->getIdentity();
        $actions = new CMSObject();

        foreach (['core.manage', 'loginguard.view', 'core.admin', 'loginguard.delete', 'loginguard.export'] as $action) {
            $actions->set($action, (bool) $user->authorise($action, 'com_loginguard'));
        }

        return $actions;
    }

    public static function addSubmenu(string $activeView): void
    {
        Sidebar::addEntry(Text::_('COM_LOGINGUARD_SUBMENU_DASHBOARD'), Route::_('index.php?option=com_loginguard', false), $activeView === 'dashboard');
        Sidebar::addEntry(Text::_('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION'), Route::_('index.php?option=com_loginguard&view=attempts', false), $activeView === 'attempts');
        Sidebar::addEntry(Text::_('COM_LOGINGUARD_SUBMENU_CONFIGURATION'), Route::_('index.php?option=com_config&view=component&component=com_loginguard', false), $activeView === 'configuration');
        Sidebar::addEntry(Text::_('COM_LOGINGUARD_SUBMENU_TOOLS'), Route::_('index.php?option=com_loginguard&view=tools', false), $activeView === 'tools');
        Sidebar::addEntry(Text::_('COM_LOGINGUARD_SUBMENU_ABOUT'), Route::_('index.php?option=com_loginguard&view=about', false), $activeView === 'about');
    }

    public static function requirePermission(string $permission): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise($permission, 'com_loginguard')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
