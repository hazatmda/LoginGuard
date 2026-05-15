<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

final class BlockedipsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id',
                'ip_address',
                'scope',
                'block_type',
                'reason',
                'failure_count',
                'blocked_until',
                'created',
                'created_by',
                'enabled',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'created', $direction = 'DESC'): void
    {
        parent::populateState($ordering, $direction);

        $app = Factory::getApplication();
        $filters = $app->getUserStateFromRequest($this->context . '.filter', 'filter', [], 'array');
        $filters = is_array($filters) ? $filters : [];

        $this->setState('filter.search', (string) ($filters['search'] ?? $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string')));
        $this->setState('filter.enabled', (string) ($filters['enabled'] ?? $app->getUserStateFromRequest($this->context . '.filter.enabled', 'filter_enabled', '', 'cmd')));
        $this->setState('filter.block_type', (string) ($filters['block_type'] ?? $app->getUserStateFromRequest($this->context . '.filter.block_type', 'filter_block_type', '', 'cmd')));
        $this->setState('filter.scope', (string) ($filters['scope'] ?? $app->getUserStateFromRequest($this->context . '.filter.scope', 'filter_scope', '', 'cmd')));

        $list = $app->getUserStateFromRequest($this->context . '.list', 'list', [], 'array');
        $list = is_array($list) ? $list : [];
        $fullOrdering = trim((string) ($list['fullordering'] ?? ''));

        if ($fullOrdering !== '') {
            $parts = preg_split('/\s+/', $fullOrdering);
            $candidateOrdering = $parts[0] ?? $ordering;
            $candidateDirection = strtoupper($parts[1] ?? $direction);

            if (in_array($candidateOrdering, $this->filter_fields, true)) {
                $this->setState('list.ordering', $candidateOrdering);
            }

            if (in_array($candidateDirection, ['ASC', 'DESC'], true)) {
                $this->setState('list.direction', $candidateDirection);
            }
        }
    }

    public function getEditItem(): ?object
    {
        $id = (int) Factory::getApplication()->input->getInt('edit_id', 0);

        if ($id < 1) {
            return null;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->where($db->quoteName('id') . ' = ' . (string) $id);

        $db->setQuery($query);
        $item = $db->loadObject();

        return $item ?: null;
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('ip_address'),
                $db->quoteName('scope'),
                $db->quoteName('block_type'),
                $db->quoteName('reason'),
                $db->quoteName('failure_count'),
                $db->quoteName('blocked_until'),
                $db->quoteName('created'),
                $db->quoteName('created_by'),
                $db->quoteName('enabled'),
            ])
            ->from($db->quoteName('#__loginguard_blocked_ips'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $pattern = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . $db->quoteName('ip_address') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('reason') . ' LIKE ' . $db->quote($pattern)
                . ')'
            );
        }

        $enabled = (string) $this->getState('filter.enabled');

        if ($enabled !== '') {
            $query->where($db->quoteName('enabled') . ' = ' . (int) $enabled);
        }

        $blockType = (string) $this->getState('filter.block_type');

        if (in_array($blockType, ['temporary', 'permanent'], true)) {
            $query->where($db->quoteName('block_type') . ' = ' . $db->quote($blockType));
        }

        $scope = (string) $this->getState('filter.scope');

        if (in_array($scope, ['all', 'frontend', 'backend'], true)) {
            $query->where($db->quoteName('scope') . ' = ' . $db->quote($scope));
        }

        $ordering = (string) $this->getState('list.ordering', 'created');
        $direction = strtoupper((string) $this->getState('list.direction', 'DESC'));

        if (!in_array($ordering, $this->filter_fields, true)) {
            $ordering = 'created';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        return $query->order($db->quoteName($ordering) . ' ' . $direction);
    }
}
