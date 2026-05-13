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
                'username',
                'user_id',
                'status',
                'ip_address',
                'client',
                'created',
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

        parent::populateState($ordering, $direction);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('username'),
                $db->quoteName('user_id'),
                $db->quoteName('status'),
                $db->quoteName('ip_address'),
                $db->quoteName('user_agent'),
                $db->quoteName('client'),
                $db->quoteName('reason'),
                $db->quoteName('created'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'));

        $search = (string) $this->getState('filter.search');

        if ($search !== '') {
            $search = '%' . str_replace(' ', '%', trim($search)) . '%';
            $query->where(
                '('
                . $db->quoteName('username') . ' LIKE ' . $db->quote($search)
                . ' OR ' . $db->quoteName('ip_address') . ' LIKE ' . $db->quote($search)
                . ' OR ' . $db->quoteName('client') . ' LIKE ' . $db->quote($search)
                . ' OR ' . $db->quoteName('reason') . ' LIKE ' . $db->quote($search)
                . ')'
            );
        }

        $status = (string) $this->getState('filter.status');

        if ($status !== '') {
            $query->where($db->quoteName('status') . ' = ' . $db->quote($status));
        }

        $ordering = $this->state->get('list.ordering', 'created');
        $direction = $this->state->get('list.direction', 'desc');

        $query->order($db->escape($ordering . ' ' . $direction));

        return $query;
    }
}
