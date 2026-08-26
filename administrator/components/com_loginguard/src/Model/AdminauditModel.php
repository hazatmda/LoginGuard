<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

final class AdminauditModel extends ListModel
{
    public function __construct($config = [])
    {
        $config['filter_fields'] ??= ['id', 'actor_user_id', 'actor_username', 'action', 'target_type', 'target_id', 'created'];
        parent::__construct($config);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();

        return $db->getQuery(true)
            ->select($db->quoteName(['id', 'actor_user_id', 'actor_username', 'action', 'target_type', 'target_id', 'details', 'created']))
            ->from($db->quoteName('#__loginguard_admin_audit'))
            ->order($db->quoteName('created') . ' DESC, ' . $db->quoteName('id') . ' DESC');
    }
}
