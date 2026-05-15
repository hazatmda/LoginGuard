<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;

final class DashboardModel extends BaseDatabaseModel
{
    /**
     * @return array<string, int>
     */
    public function getTelemetryCounts(): array
    {
        $counts = [
            'frontend_success' => 0,
            'backend_success' => 0,
            'frontend_failed' => 0,
            'backend_failed' => 0,
            'blocked_login' => 0,
        ];

        $db = $this->getDatabase();
        $originExpression = 'LOWER(COALESCE(NULLIF(' . $db->quoteName('where_at') . ', ' . $db->quote('') . '), ' . $db->quoteName('client') . '))';
        $query = $db->getQuery(true)
            ->select([
                $originExpression . ' AS ' . $db->quoteName('origin'),
                $db->quoteName('status'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($originExpression . ' IN (' . $this->quoteList(['frontend', 'backend']) . ')')
            ->where($db->quoteName('status') . ' IN (' . $this->quoteList(['SUCCESS_LOGIN', 'FAILED_LOGIN', 'BLOCKED_LOGIN']) . ')')
            ->group([$originExpression, $db->quoteName('status')]);

        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            $origin = (string) $row->origin;
            $status = (string) $row->status;
            $key = $status === 'BLOCKED_LOGIN' ? 'blocked_login' : $origin . '_' . ($status === 'SUCCESS_LOGIN' ? 'success' : 'failed');

            if (array_key_exists($key, $counts)) {
                $counts[$key] = (int) $row->total;
            }
        }

        return $counts;
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
            'IP_BLOCKED' => 0,
        ];

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('reason'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('status') . ' IN (' . $this->quoteList(['FAILED_LOGIN', 'BLOCKED_LOGIN']) . ')')
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
     * @return array<string, int>
     */
    public function getBlockedIpTelemetry(): array
    {
        $telemetry = [
            'active' => 0,
            'temporary' => 0,
            'permanent' => 0,
            'expired' => 0,
        ];

        $db = $this->getDatabase();
        $now = date('Y-m-d H:i:s');
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('block_type'),
                $db->quoteName('blocked_until'),
                'COUNT(*) AS ' . $db->quoteName('total'),
            ])
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->where($db->quoteName('enabled') . ' = 1')
            ->group([$db->quoteName('block_type'), $db->quoteName('blocked_until')]);

        $db->setQuery($query);

        foreach ($db->loadObjectList() ?: [] as $row) {
            $total = (int) $row->total;
            $type = (string) $row->block_type;
            $until = (string) $row->blocked_until;
            $isPermanent = $type === 'permanent';
            $isTemporaryActive = $type === 'temporary' && $until !== '' && $until >= $now;

            if (!$isPermanent && !$isTemporaryActive) {
                $telemetry['expired'] += $total;
                continue;
            }

            $telemetry['active'] += $total;

            if ($isPermanent) {
                $telemetry['permanent'] += $total;
            } elseif ($isTemporaryActive) {
                $telemetry['temporary'] += $total;
            }
        }

        return $telemetry;
    }

    /**
     * @return array<int, object>
     */
    public function getRecentBlockedIps(): array
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('ip_address'),
                $db->quoteName('scope'),
                $db->quoteName('block_type'),
                $db->quoteName('reason'),
                $db->quoteName('failure_count'),
                $db->quoteName('blocked_until'),
                $db->quoteName('created'),
                $db->quoteName('enabled'),
            ])
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->order($db->quoteName('created') . ' DESC');

        $db->setQuery($query, 0, 10);

        return $db->loadObjectList() ?: [];
    }


    /**
     * @return array<string, int|string>
     */
    public function getCleanupMetrics(): array
    {
        $db = $this->getDatabase();
        $params = \Joomla\CMS\Component\ComponentHelper::getParams('com_loginguard');
        $metrics = [
            'total_attempts' => $this->countTable('#__loginguard_attempts'),
            'total_blocked_ips' => $this->countTable('#__loginguard_blocked_ips'),
            'last_cleanup_execution' => '',
            'last_attempts_deleted' => 0,
            'last_expired_blocks_deleted' => 0,
            'last_disabled_blocks_deleted' => 0,
            'last_total_deleted' => 0,
            'last_batches' => 0,
            'automatic_cleanup_enabled' => (int) $params->get('automatic_cleanup_enabled', 0),
            'login_retention_days' => max(1, (int) $params->get('login_retention_days', (int) $params->get('retention_days', 90))),
            'blocked_ip_retention_days' => max(1, (int) $params->get('blocked_ip_retention_days', 30)),
            'cleanup_batch_size' => max(1, (int) $params->get('cleanup_batch_size', 500)),
        ];

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('finished_at'),
                $db->quoteName('attempts_deleted'),
                $db->quoteName('expired_blocks_deleted'),
                $db->quoteName('disabled_blocks_deleted'),
                $db->quoteName('total_deleted'),
                $db->quoteName('batches'),
            ])
            ->from($db->quoteName('#__loginguard_cleanup_runs'))
            ->order($db->quoteName('finished_at') . ' DESC');

        $db->setQuery($query, 0, 1);
        $lastRun = $db->loadObject();

        if ($lastRun) {
            $metrics['last_cleanup_execution'] = (string) $lastRun->finished_at;
            $metrics['last_attempts_deleted'] = (int) $lastRun->attempts_deleted;
            $metrics['last_expired_blocks_deleted'] = (int) $lastRun->expired_blocks_deleted;
            $metrics['last_disabled_blocks_deleted'] = (int) $lastRun->disabled_blocks_deleted;
            $metrics['last_total_deleted'] = (int) $lastRun->total_deleted;
            $metrics['last_batches'] = (int) $lastRun->batches;
        }

        return $metrics;
    }

    private function countTable(string $table): int
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($table));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * @param   array<int, string>  $values
     */
    private function quoteList(array $values): string
    {
        $db = $this->getDatabase();

        return implode(',', array_map(static fn ($value) => $db->quote($value), $values));
    }
}
