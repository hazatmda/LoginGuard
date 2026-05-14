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
                'country',
                'browser',
                'operating_system',
                'where_at',
                'client',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'created', $direction = 'desc'): void
    {
        $app = Factory::getApplication();
        $inputFilters = $app->getInput()->get('filter', [], 'array');

        $search = array_key_exists('search', $inputFilters)
            ? (string) $inputFilters['search']
            : (string) $app->getUserState($this->context . '.filter.search', '');
        $app->setUserState($this->context . '.filter.search', $search);
        $this->setState('filter.search', $search);

        $status = array_key_exists('status', $inputFilters)
            ? (string) $inputFilters['status']
            : (string) $app->getUserState($this->context . '.filter.status', '');
        $app->setUserState($this->context . '.filter.status', $status);
        $this->setState('filter.status', $status);

        $whereAt = array_key_exists('where_at', $inputFilters)
            ? (string) $inputFilters['where_at']
            : (string) $app->getUserState($this->context . '.filter.where_at', '');
        $app->setUserState($this->context . '.filter.where_at', $whereAt);
        $this->setState('filter.where_at', $whereAt);

        parent::populateState($ordering, $direction);
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
                $db->quoteName('country'),
                $db->quoteName('browser'),
                $db->quoteName('operating_system'),
                $db->quoteName('where_at'),
                $db->quoteName('client'),
                $db->quoteName('reason'),
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
                . ' OR ' . $db->quoteName('browser') . ' LIKE ' . $db->quote($pattern)
                . ' OR ' . $db->quoteName('operating_system') . ' LIKE ' . $db->quote($pattern)
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
                . ' OR (' . $db->quoteName('where_at') . ' = ' . $db->quote('')
                . ' AND ' . $db->quoteName('client') . ' = ' . $db->quote($whereAt) . ')'
                . ')'
            );
        }

        $ordering = (string) $this->state->get('list.ordering', 'created');
        $direction = strtoupper((string) $this->state->get('list.direction', 'desc'));

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
