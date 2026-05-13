<?php

namespace Joomla\Component\LoginGuard\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class AttemptsModel extends ListModel
{
    protected function getListQuery()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__loginguard_attempts'))
            ->order('created DESC');

        return $query;
    }
}
