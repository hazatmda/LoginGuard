<?php

namespace Joomla\Plugin\User\LoginGuard\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

final class LoginGuard extends CMSPlugin
{
    protected $autoloadLanguage = true;

    public function onUserAfterLogin(array $options): void
    {
        $user = $options['user'] ?? [];

        $this->storeAttempt(
            $user['username'] ?? 'unknown',
            (int) ($user['id'] ?? 0),
            'success',
            'Login successful'
        );
    }

    public function onUserLoginFailure(array $response): void
    {
        $this->storeAttempt(
            $response['username'] ?? 'unknown',
            0,
            'failed',
            $response['error_message'] ?? 'Login failed'
        );
    }

    private function storeAttempt(
        string $username,
        int $userId,
        string $status,
        string $reason
    ): void {
        $app = Factory::getApplication();
        $db  = Factory::getContainer()->get('DatabaseDriver');

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

        $client = $app->isClient('administrator') ? 'administrator' : 'site';

        $columns = [
            'username',
            'user_id',
            'status',
            'ip_address',
            'user_agent',
            'client',
            'reason',
            'created'
        ];

        $values = [
            $db->quote($username),
            $userId,
            $db->quote($status),
            $db->quote($ipAddress),
            $db->quote($userAgent),
            $db->quote($client),
            $db->quote($reason),
            $db->quote((new Date())->toSql())
        ];

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__loginguard_attempts'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();
    }
}
