<?php

namespace Joomla\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\QueryInterface;

final class AttemptsModel extends ListModel
{
    public function __construct($config = [])
    {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = [
                'id', 'a.id',
                'username', 'a.username',
                'status', 'a.status',
                'ip_address', 'a.ip_address',
                'client', 'a.client',
                'created', 'a.created',
            ];
        }

        parent::__construct($config);
    }

    protected function populateState($ordering = 'a.created', $direction = 'desc'): void
    {
        $app = Factory::getApplication();

        $this->setState('filter.search', $app->getUserStateFromRequest($this->context . '.filter.search', 'filter_search'));
        $this->setState('filter.status', $app->getUserStateFromRequest($this->context . '.filter.status', 'filter_status'));
        $this->setState('filter.client', $app->getUserStateFromRequest($this->context . '.filter.client', 'filter_client'));

        parent::populateState($ordering, $direction);
    }

    protected function getListQuery(): QueryInterface
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select(
            $db->quoteName(
                [
                    'a.id',
                    'a.username',
                    'a.user_id',
                    'a.status',
                    'a.ip_address',
                    'a.user_agent',
                    'a.client',
                    'a.reason',
                    'a.created',
                ]
            )
        )
            ->from($db->quoteName('#__loginguard_attempts', 'a'));

        $search = trim((string) $this->getState('filter.search'));

        if ($search !== '') {
            $searchValue = '%' . str_replace(' ', '%', $search) . '%';
            $query->where(
                [
                    $db->quoteName('a.username') . ' LIKE :searchUsername',
                    $db->quoteName('a.ip_address') . ' LIKE :searchIpAddress',
                    $db->quoteName('a.reason') . ' LIKE :searchReason',
                ],
                'OR'
            )
                ->bind(':searchUsername', $searchValue)
                ->bind(':searchIpAddress', $searchValue)
                ->bind(':searchReason', $searchValue);
        }

        $status = (string) $this->getState('filter.status');

        if ($status !== '') {
            $query->where($db->quoteName('a.status') . ' = :status')
                ->bind(':status', $status);
        }

        $client = (string) $this->getState('filter.client');

        if ($client !== '') {
            $query->where($db->quoteName('a.client') . ' = :client')
                ->bind(':client', $client);
        }

        $ordering = $this->state->get('list.ordering', 'a.created');
        $direction = $this->state->get('list.direction', 'desc');

        $query->order($db->escape($ordering) . ' ' . $db->escape($direction));

        return $query;
    }
}
