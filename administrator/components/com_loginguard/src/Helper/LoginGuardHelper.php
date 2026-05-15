<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
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

    public static function getConfiguredTimezone(): \DateTimeZone
    {
        $timezone = (string) Factory::getConfig()->get('offset', 'UTC');

        try {
            return new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (\Exception $exception) {
            return new \DateTimeZone('UTC');
        }
    }

    public static function formatConfiguredDateTime(?string $value, ?string $format = null): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $date = new Date($value, 'UTC');
        $date->setTimezone(self::getConfiguredTimezone());

        return $date->format($format ?: Text::_('DATE_FORMAT_LC5'), false);
    }

    public static function formatConfiguredDateTimeInput(?string $value): string
    {
        return self::formatConfiguredDateTime($value, 'Y-m-d\\TH:i');
    }
}
