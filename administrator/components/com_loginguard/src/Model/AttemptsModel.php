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
                'client',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'created', $direction = 'desc'): void
    {
        $app = Factory::getApplication();

        $search = $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search', '', 'string');
        $this->setState('filter.search', $search);

        $status = $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status', '', 'cmd');
        $this->setState('filter.status', $status);

        $client = $app->getUserStateFromRequest($this->context . '.filter.client', 'filter_client', '', 'cmd');
        $this->setState('filter.client', $client);

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
                . ' OR ' . $db->quoteName('client') . ' LIKE ' . $db->quote($pattern)
                . ')'
            );
        }

        $status = (string) $this->getState('filter.status');

        if ($status !== '') {
            $query->where($db->quoteName('status') . ' = ' . $db->quote($status));
        }

        $client = (string) $this->getState('filter.client');

        if ($client !== '') {
            $query->where($db->quoteName('client') . ' = ' . $db->quote($client));
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
