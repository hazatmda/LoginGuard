<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

final class CleanupService
{
    private const DEFAULT_LOGIN_RETENTION_DAYS = 90;
    private const DEFAULT_BLOCKED_IP_RETENTION_DAYS = 30;
    private const DEFAULT_BATCH_SIZE = 500;
    private const MAX_BATCH_SIZE = 5000;
    private const MAX_BATCHES_PER_RUN = 50;

    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Registry $params
    ) {
    }

    /**
     * @return array<string, int|string>
     */
    public function execute(?int $batchSize = null): array
    {
        $startedAt = gmdate('Y-m-d H:i:s');
        $batchSize = $this->normaliseBatchSize($batchSize ?? (int) $this->params->get('cleanup_batch_size', self::DEFAULT_BATCH_SIZE));
        $loginRetentionDays = max(1, (int) $this->params->get('login_retention_days', (int) $this->params->get('retention_days', self::DEFAULT_LOGIN_RETENTION_DAYS)));
        $blockedIpRetentionDays = max(1, (int) $this->params->get('blocked_ip_retention_days', self::DEFAULT_BLOCKED_IP_RETENTION_DAYS));
        $attemptCutoff = gmdate('Y-m-d H:i:s', time() - ($loginRetentionDays * 86400));
        $blockedIpCutoff = gmdate('Y-m-d H:i:s', time() - ($blockedIpRetentionDays * 86400));

        $metrics = [
            'started_at' => $startedAt,
            'finished_at' => '',
            'attempts_deleted' => 0,
            'expired_blocks_deleted' => 0,
            'disabled_blocks_deleted' => 0,
            'total_deleted' => 0,
            'batches' => 0,
            'batch_size' => $batchSize,
            'login_retention_days' => $loginRetentionDays,
            'blocked_ip_retention_days' => $blockedIpRetentionDays,
        ];

        $metrics['attempts_deleted'] = $this->cleanupOldAttempts($attemptCutoff, $batchSize, $metrics['batches']);
        $metrics['expired_blocks_deleted'] = $this->cleanupExpiredBlocks($blockedIpCutoff, $batchSize, $metrics['batches']);
        $metrics['disabled_blocks_deleted'] = $this->cleanupDisabledBlocks($blockedIpCutoff, $batchSize, $metrics['batches']);
        $metrics['total_deleted'] = $metrics['attempts_deleted'] + $metrics['expired_blocks_deleted'] + $metrics['disabled_blocks_deleted'];
        $metrics['finished_at'] = gmdate('Y-m-d H:i:s');

        $this->recordMetrics($metrics);

        if ((int) $this->params->get('cleanup_execution_logging', 1) === 1) {
            Log::add(
                sprintf(
                    'LoginGuard cleanup completed: attempts=%d expired_blocks=%d disabled_blocks=%d batches=%d batch_size=%d',
                    $metrics['attempts_deleted'],
                    $metrics['expired_blocks_deleted'],
                    $metrics['disabled_blocks_deleted'],
                    $metrics['batches'],
                    $metrics['batch_size']
                ),
                Log::INFO,
                'com_loginguard.cleanup'
            );
        }

        return $metrics;
    }

    /**
     * @param   array<string, int|string>  $metrics
     */
    private function recordMetrics(array $metrics): void
    {
        $columns = [
            'started_at',
            'finished_at',
            'attempts_deleted',
            'expired_blocks_deleted',
            'disabled_blocks_deleted',
            'total_deleted',
            'batches',
            'batch_size',
            'login_retention_days',
            'blocked_ip_retention_days',
        ];

        $values = [
            $this->db->quote((string) $metrics['started_at']),
            $this->db->quote((string) $metrics['finished_at']),
            (int) $metrics['attempts_deleted'],
            (int) $metrics['expired_blocks_deleted'],
            (int) $metrics['disabled_blocks_deleted'],
            (int) $metrics['total_deleted'],
            (int) $metrics['batches'],
            (int) $metrics['batch_size'],
            (int) $metrics['login_retention_days'],
            (int) $metrics['blocked_ip_retention_days'],
        ];

        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__loginguard_cleanup_runs'))
            ->columns(array_map([$this->db, 'quoteName'], $columns))
            ->values(implode(',', $values));

        $this->db->setQuery($query)->execute();
    }

    private function cleanupOldAttempts(string $cutoff, int $batchSize, int &$batches): int
    {
        return $this->deleteInBatches(
            '#__loginguard_attempts',
            $this->db->quoteName('created') . ' < ' . $this->db->quote($cutoff),
            $batchSize,
            $batches
        );
    }

    private function cleanupExpiredBlocks(string $retentionCutoff, int $batchSize, int &$batches): int
    {
        $now = gmdate('Y-m-d H:i:s');

        return $this->deleteInBatches(
            '#__loginguard_blocked_ips',
            implode(' AND ', [
                $this->db->quoteName('block_type') . ' = ' . $this->db->quote('temporary'),
                $this->db->quoteName('blocked_until') . ' IS NOT NULL',
                $this->db->quoteName('blocked_until') . ' < ' . $this->db->quote($now),
                $this->db->quoteName('blocked_until') . ' < ' . $this->db->quote($retentionCutoff),
            ]),
            $batchSize,
            $batches
        );
    }

    private function cleanupDisabledBlocks(string $retentionCutoff, int $batchSize, int &$batches): int
    {
        return $this->deleteInBatches(
            '#__loginguard_blocked_ips',
            implode(' AND ', [
                $this->db->quoteName('enabled') . ' = 0',
                $this->db->quoteName('created') . ' < ' . $this->db->quote($retentionCutoff),
            ]),
            $batchSize,
            $batches
        );
    }

    private function deleteInBatches(string $table, string $where, int $batchSize, int &$batches): int
    {
        $deleted = 0;

        while ($batches < self::MAX_BATCHES_PER_RUN) {
            $ids = $this->fetchIds($table, $where, $batchSize);

            if ($ids === []) {
                break;
            }

            $query = $this->db->getQuery(true)
                ->delete($this->db->quoteName($table))
                ->whereIn($this->db->quoteName('id'), $ids);
            $this->db->setQuery($query)->execute();

            $deleted += count($ids);
            $batches++;

            if (count($ids) < $batchSize) {
                break;
            }
        }

        return $deleted;
    }

    /**
     * @return array<int, int>
     */
    private function fetchIds(string $table, string $where, int $batchSize): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName($table))
            ->where($where)
            ->order($this->db->quoteName('id') . ' ASC');

        $this->db->setQuery($query, 0, $batchSize);

        return array_map('intval', $this->db->loadColumn() ?: []);
    }

    private function normaliseBatchSize(int $batchSize): int
    {
        return max(1, min(self::MAX_BATCH_SIZE, $batchSize));
    }
}
