<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class AttemptsController extends AdminController
{
    public function getModel($name = 'Attempts', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function delete(): void
    {
        LoginGuardHelper::requirePermission('loginguard.delete');
        $this->checkToken();

        $ids = array_map('intval', (array) $this->input->get('cid', [], 'array'));
        $ids = array_values(array_filter($ids));

        if ($ids !== []) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__loginguard_attempts'))
                ->whereIn($db->quoteName('id'), $ids);
            $db->setQuery($query)->execute();
        }

        $this->setMessage(Text::plural('COM_LOGINGUARD_N_ITEMS_DELETED', count($ids)));
        $this->setRedirect('index.php?option=com_loginguard&view=attempts');
    }

    public function export(): void
    {
        LoginGuardHelper::requirePermission('loginguard.export');
        $this->checkToken('get');

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('ip_address'),
                $db->quoteName('name'),
                $db->quoteName('username'),
                $db->quoteName('status'),
                $db->quoteName('reason', 'failure_reason'),
                $db->quoteName('where_at'),
                $db->quoteName('country'),
                $db->quoteName('browser'),
                $db->quoteName('operating_system'),
                $db->quoteName('user_agent'),
                $db->quoteName('created'),
            ])
            ->from($db->quoteName('#__loginguard_attempts'))
            ->order($db->quoteName('created') . ' DESC');

        $rows = $db->setQuery($query)->loadAssocList();
        $filename = 'loginguard-login-information-' . gmdate('Ymd-His') . '.csv';

        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->sendHeaders();

        $output = fopen('php://output', 'wb');
        fputcsv($output, ['ID', 'IP Address', 'Name', 'Username', 'Status', 'Failure Reason', 'Where', 'Country', 'Browser', 'Operating System', 'User Agent', 'Datetime']);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'],
                $row['ip_address'],
                $row['name'],
                $row['username'],
                $row['status'],
                $row['failure_reason'],
                $row['where_at'],
                $row['country'],
                $row['browser'],
                $row['operating_system'],
                $row['user_agent'],
                $row['created'],
            ]);
        }

        fclose($output);
        $app->close();
    }
}
