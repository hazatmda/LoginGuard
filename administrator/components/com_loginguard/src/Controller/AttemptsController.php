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
        $this->checkToken();

        $app = Factory::getApplication();
        $ids = array_map('intval', (array) $this->input->get('cid', [], 'array'));
        $ids = array_values(array_filter($ids));
        $model = $this->getModel('Attempts', 'Administrator', ['ignore_request' => false]);
        $rows = $model->getExportRows($ids);
        $filename = 'loginguard-login-information-' . gmdate('Ymd-His') . '.csv';

        $app->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $app->setHeader('Pragma', 'no-cache', true);
        $app->sendHeaders();

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
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
