<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Object\CMSObject;

final class LoginGuardHelper
{
    public static function getActions(): CMSObject
    {
        $user = Factory::getApplication()->getIdentity();
        $actions = new CMSObject();

        foreach (['core.manage', 'loginguard.view', 'core.admin', 'loginguard.delete', 'loginguard.export', 'loginguard.manage_blocks'] as $action) {
            $actions->set($action, (bool) $user->authorise($action, 'com_loginguard'));
        }

        return $actions;
    }


    public static function requirePermission(string $permission): void
    {
        if (!Factory::getApplication()->getIdentity()->authorise($permission, 'com_loginguard')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
