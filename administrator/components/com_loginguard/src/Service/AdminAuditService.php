<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\Database\DatabaseInterface;

final class AdminAuditService
{
    private const FORBIDDEN_DETAIL_KEYS = '/password|passwd|token|secret|session|cookie|authorization|request|body/i';

    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Append an audit event. Callers include this insert in the same transaction as
     * a mutation; an exception therefore prevents the mutation being reported as success.
     *
     * @param array<string, scalar|null> $details
     */
    public function append(object $actor, string $action, string $targetType, ?string $targetId, array $details = []): void
    {
        $safeDetails = [];

        foreach ($details as $key => $value) {
            if (preg_match(self::FORBIDDEN_DETAIL_KEYS, (string) $key) === 1 || (!is_scalar($value) && $value !== null)) {
                continue;
            }

            $safeDetails[substr((string) $key, 0, 64)] = is_string($value) ? substr($value, 0, 512) : $value;
        }

        $encoded = json_encode($safeDetails, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $columns = ['actor_user_id', 'actor_username', 'action', 'target_type', 'target_id', 'details', 'created'];
        $values = [
            (string) (int) ($actor->id ?? 0),
            $this->db->quote(substr((string) ($actor->username ?? ''), 0, 255)),
            $this->db->quote(substr($action, 0, 64)),
            $this->db->quote(substr($targetType, 0, 64)),
            $targetId === null ? 'NULL' : $this->db->quote(substr($targetId, 0, 255)),
            $this->db->quote(substr($encoded, 0, 4000)),
            $this->db->quote(gmdate('Y-m-d H:i:s')),
        ];
        $query = $this->db->getQuery(true)
            ->insert($this->db->quoteName('#__loginguard_admin_audit'))
            ->columns($this->db->quoteName($columns))
            ->values(implode(',', $values));
        $this->db->setQuery($query)->execute();
    }
}
