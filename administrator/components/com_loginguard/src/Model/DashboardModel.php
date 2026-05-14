<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

final class DashboardModel extends BaseDatabaseModel
{
    /**
     * Count all login attempts for the requested normalized status.
     */
    public function getSuccessLoginCount(): int
    {
        return $this->countByStatus('SUCCESS_LOGIN');
    }

    /**
     * Count all login attempts for the requested normalized status.
     */
    public function getFailedLoginCount(): int
    {
        return $this->countByStatus('FAILED_LOGIN');
    }

    /**
     * @return array<string, int>
     */
    public function getOriginCounts(): array
    {
        $origins = [
            'frontend' => 0,
            'backend' => 0,
            'api' => 0,
            'cli' => 0,
        ];

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                'LOWER(COALESCE(NULLIF(' . $db->quoteName('where_at') . ', ' . $db->quote('') . '), ' . $db->quoteName('client') . ')) AS ' . $db->quoteName('origin'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where('LOWER(COALESCE(NULLIF(' . $db->quoteName('where_at') . ', ' . $db->quote('') . '), ' . $db->quoteName('client') . ')) IN (' . $this->quoteList(array_keys($origins)) . ')')
            ->group('LOWER(COALESCE(NULLIF(' . $db->quoteName('where_at') . ', ' . $db->quote('') . '), ' . $db->quoteName('client') . '))');

        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            $origin = (string) $row->origin;

            if (array_key_exists($origin, $origins)) {
                $origins[$origin] = (int) $row->total;
            }
        }

        return $origins;
    }

    /**
     * @return array<int, object>
     */
    public function getRecentActivity(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('ip_address'),
                $db->quoteName('username'),
                $db->quoteName('status'),
                $db->quoteName('reason'),
                $db->quoteName('where_at'),
                $db->quoteName('client'),
                $db->quoteName('created'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->order($db->quoteName('created') . ' DESC');

        $db->setQuery($query, 0, 10);

        return $db->loadObjectList() ?: [];
    }

    /**
     * @return array<string, int>
     */
    public function getTopFailureReasons(): array
    {
        $reasons = [
            'PASSWORD_INCORRECT' => 0,
            'USERNAME_NOT_FOUND' => 0,
            'INVALID_CREDENTIALS' => 0,
            'ACCOUNT_BLOCKED' => 0,
            'ACCOUNT_DISABLED' => 0,
        ];

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('reason'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('status') . ' = ' . $db->quote('FAILED_LOGIN'))
            ->where($db->quoteName('reason') . ' IN (' . $this->quoteList(array_keys($reasons)) . ')')
            ->group($db->quoteName('reason'))
            ->order($db->quoteName('total') . ' DESC');

        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            $reason = (string) $row->reason;

            if (array_key_exists($reason, $reasons)) {
                $reasons[$reason] = (int) $row->total;
            }
        }

        return $reasons;
    }

    /**
     * @return array<int, object>
     */
    public function getTopFailedIps(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('ip_address'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('status') . ' = ' . $db->quote('FAILED_LOGIN'))
            ->where($db->quoteName('ip_address') . ' <> ' . $db->quote(''))
            ->group($db->quoteName('ip_address'))
            ->order($db->quoteName('total') . ' DESC');

        $db->setQuery($query, 0, 10);

        return $db->loadObjectList() ?: [];
    }

    /**
     * @param   array<int, string>  $values
     */
    private function quoteList(array $values): string
    {
        $db = $this->getDatabase();

        return implode(',', array_map(static fn ($value) => $db->quote($value), $values));
    }

    private function countByStatus(string $status): int
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('status') . ' = ' . $db->quote($status));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }
}
