<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

final class AttemptsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id',
                'ip_address',
                'name',
                'username',
                'status',
                'created',
                'reason',
                'country',
                'country_code',
                'region',
                'city',
                'isp',
                'asn',
                'browser',
                'operating_system',
                'where_at',
                'client',
                'user_agent',
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

        $this->setState(
            'filter.search',
            (string) ($filters['search'] ?? $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string'))
        );
        $this->setState(
            'filter.status',
            (string) ($filters['status'] ?? $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'cmd'))
        );
        $this->setState(
            'filter.where_at',
            (string) ($filters['where_at'] ?? $filters['client'] ?? $app->getUserStateFromRequest($this->context . '.filter.where_at', 'filter_where_at', '', 'cmd'))
        );

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


    /**
     * Return all rows matching the current SearchTools state, optionally limited to selected IDs.
     *
     * @param   array<int, int>  $ids
     *
     * @return array<int, array<string, mixed>>
     */
    public function getExportRows(array $ids = []): array
    {
        $db = $this->getDatabase();
        $query = $this->getListQuery();

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

        if ($ids !== []) {
            $query->whereIn($db->quoteName('id'), $ids);
        }

        $rows = $db->setQuery($query)->loadAssocList();

        return is_array($rows) ? $rows : [];
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('ip_address'),
                $db->quoteName('name'),
                $db->quoteName('username'),
                $db->quoteName('status'),
                $db->quoteName('created'),
                $db->quoteName('reason'),
                $db->quoteName('country'),
                $db->quoteName('country_code'),
                $db->quoteName('region'),
                $db->quoteName('city'),
                $db->quoteName('isp'),
                $db->quoteName('asn'),
                $db->quoteName('browser'),
                $db->quoteName('operating_system'),
                $db->quoteName('where_at'),
                $db->quoteName('client'),
                $db->quoteName('user_agent'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $pattern = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                '('
                . $db->quoteName('ip_address') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('name') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('username') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('country') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('country_code') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('region') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('city') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('isp') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('asn') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('browser') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('operating_system') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('reason') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('user_agent') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('where_at') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('client') . ' LIKE ' . $db->quote($pattern)
                . ')'
            );
        }

        $status = (string) $this->getState('filter.status');

        if ($status !== '') {
            $query->where($db->quoteName('status') . ' = ' . $db->quote($status));
        }

        $whereAt = (string) $this->getState('filter.where_at');

        if ($whereAt !== '') {
            $query->where(
                '('
                . $db->quoteName('where_at') . ' = ' . $db->quote($whereAt)
                . ' OR ' . $db->quoteName('client') . ' = ' . $db->quote($whereAt)
                . ')'
            );
        }

        $ordering = (string) $this->state->get('list.ordering', 'created');
        $direction = strtoupper((string) $this->state->get('list.direction', 'DESC'));

        if (!in_array($ordering, $this->filter_fields, true)) {
            $ordering = 'created';
        }

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'DESC';
        }

        $query->order($db->quoteName($ordering) . ' ' . $direction);

        return $query;
    }
}
