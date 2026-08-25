<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Throwable;

final class OperationalAudit
{
    /** @param array<string, mixed> $details */
    public static function recordAdminAction(
        DatabaseInterface $db,
        string $action,
        string $targetType,
        int $targetId,
        string $targetIp,
        int $actorUserId,
        array $details = []
    ): void {
        try {
            $columns = ['action', 'target_type', 'target_id', 'target_ip', 'actor_user_id', 'details', 'created'];
            $values = [
                $db->quote(substr(strtoupper($action), 0, 50)),
                $db->quote(substr($targetType, 0, 50)),
                (string) max(0, $targetId),
                $db->quote(substr($targetIp, 0, 45)),
                (string) max(0, $actorUserId),
                $db->quote((string) json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                $db->quote(gmdate('Y-m-d H:i:s')),
            ];

            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__loginguard_admin_audit'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($query)->execute();
        } catch (Throwable $exception) {
            self::logFailure('admin_audit', $exception->getMessage());
        }
    }

    public static function recordHealth(DatabaseInterface $db, string $key, string $status, string $message = ''): void
    {
        try {
            $key = substr(strtolower(trim($key)), 0, 64);
            $status = substr(strtolower(trim($status)), 0, 20);
            $message = self::truncate($message, 2000);
            $now = gmdate('Y-m-d H:i:s');

            if ($key === '') {
                return;
            }

            $query = 'INSERT INTO ' . $db->quoteName('#__loginguard_health')
                . ' (' . implode(',', array_map([$db, 'quoteName'], ['health_key', 'status', 'message', 'updated'])) . ')'
                . ' VALUES (' . implode(',', [$db->quote($key), $db->quote($status ?: 'healthy'), $db->quote($message), $db->quote($now)]) . ')'
                . ' ON DUPLICATE KEY UPDATE '
                . $db->quoteName('status') . ' = VALUES(' . $db->quoteName('status') . '), '
                . $db->quoteName('message') . ' = VALUES(' . $db->quoteName('message') . '), '
                . $db->quoteName('updated') . ' = VALUES(' . $db->quoteName('updated') . ')';

            $db->setQuery($query)->execute();
        } catch (Throwable $exception) {
            self::logFailure('health', $exception->getMessage());
        }
    }

    public static function logFailure(string $category, string $message): void
    {
        Log::add(
            'LoginGuard ' . $category . ' failure: ' . self::truncate($message, 2000),
            Log::ERROR,
            'com_loginguard.' . preg_replace('/[^a-z0-9_.-]+/i', '_', strtolower($category))
        );
    }

    private static function truncate(string $value, int $maxLength): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}
