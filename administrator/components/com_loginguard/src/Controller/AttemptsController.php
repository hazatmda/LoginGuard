<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
use LoginGuard\Component\LoginGuard\Administrator\Service\OperationalAudit;

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
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        $actorId = (int) Factory::getApplication()->getIdentity()->id;

        if ($ids !== []) {
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__loginguard_attempts'))
                ->whereIn($db->quoteName('id'), $ids);
            $db->setQuery($query)->execute();
        }

        OperationalAudit::recordAdminAction($db, 'AUDIT_RECORDS_DELETED', 'attempt', 0, '', $actorId, ['ids' => $ids, 'count' => count($ids)]);
        $this->setMessage(Text::plural('COM_LOGINGUARD_N_ITEMS_DELETED', count($ids)));
        $this->setRedirect('index.php?option=com_loginguard&view=attempts');
    }

    public function export(): void
    {
        LoginGuardHelper::requirePermission('loginguard.export');
        $this->checkToken();

        $app = Factory::getApplication();
        $model = $this->getModel('Attempts', 'Administrator', ['ignore_request' => false]);
        $ids = $this->input->get('cid', [], 'array');
        $ids = is_array($ids) ? array_values(array_filter(array_map('intval', $ids))) : [];
        $rows = $model->getExportRows($ids);
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        $actorId = (int) $app->getIdentity()->id;

        OperationalAudit::recordAdminAction($db, 'AUDIT_EXPORTED', 'attempt_export', 0, '', $actorId, [
            'selected_ids' => $ids,
            'row_count' => count($rows),
        ]);

        $filename = 'loginguard-login-information-' . gmdate('Ymd-His') . '.csv';
        $safeFilename = OutputFilter::stringUrlSafe(pathinfo($filename, PATHINFO_FILENAME)) . '.csv';

        $app->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $safeFilename . '"', true);
        $app->setHeader('Content-Description', 'File Transfer', true);
        $app->setHeader('Content-Transfer-Encoding', 'binary', true);
        $app->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate', true);
        $app->setHeader('Pragma', 'no-cache', true);
        $app->setHeader('Expires', '0', true);
        $app->sendHeaders();

        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, [
            'ID', 'IP Address', 'Name', 'Username', 'Status', 'Failure Reason', 'Where', 'Attempt Type',
            'Browser', 'Operating System', 'User Agent', 'Datetime',
        ]);

        foreach ($rows as $row) {
            $whereAt = (string) ($row['where_at'] ?: $row['client']);
            $cells = [
                $row['id'], $row['ip_address'], $row['name'], $row['username'], $row['status'], $row['reason'], $whereAt,
                $row['attempt_type'] ?? '', $row['browser'], $row['operating_system'], $row['user_agent'],
                LoginGuardHelper::formatConfiguredDateTime((string) $row['created']),
            ];

            fputcsv($output, array_map([$this, 'sanitiseCsvCell'], $cells));
        }

        fclose($output);
        $app->close();
    }

    /**
     * Prevent spreadsheet formula execution without changing forensic data in the database.
     * Numeric values remain numeric; only exported text which could be interpreted as a formula is prefixed.
     */
    private function sanitiseCsvCell($value)
    {
        if ($value === null || is_int($value) || is_float($value)) {
            return $value;
        }

        $text = (string) $value;
        $candidate = ltrim($text, " \t\r\n");

        if ($candidate !== '' && in_array($candidate[0], ['=', '+', '-', '@'], true)) {
            return "'" . $text;
        }

        return $text;
    }
}
