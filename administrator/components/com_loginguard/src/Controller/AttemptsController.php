<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
use LoginGuard\Component\LoginGuard\Administrator\Service\AdminAuditService;
use LoginGuard\Component\LoginGuard\Administrator\Service\CsvCellNeutralizer;

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
        $db->transactionStart();

        try {
            if ($ids !== []) {
                $query = $db->getQuery(true)
                    ->delete($db->quoteName('#__loginguard_attempts'))
                ->whereIn($db->quoteName('id'), $ids);
                $db->setQuery($query)->execute();
            }

            (new AdminAuditService($db))->append(Factory::getApplication()->getIdentity(), 'login_attempt.delete', 'login_attempt', implode(',', $ids), ['record_count' => count($ids)]);
            $db->transactionCommit();
        } catch (\Throwable $exception) {
            $db->transactionRollback();
            throw $exception;
        }

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
        $rows = $model->getExportRows(is_array($ids) ? $ids : []);
        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        (new AdminAuditService($db))->append(
            $app->getIdentity(),
            'login_attempt.export',
            'login_attempt',
            null,
            ['scope' => is_array($ids) && $ids !== [] ? 'selected' : 'all', 'record_count' => count($rows)]
        );
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
        fputcsv($output, ['ID', 'IP Address', 'Name', 'Username', 'Status', 'Failure Reason', 'Where', 'Country', 'Country Code', 'Region', 'City', 'ISP', 'ASN', 'Browser', 'Operating System', 'User Agent', 'Datetime']);

        foreach ($rows as $row) {
            $whereAt = (string) ($row['where_at'] ?: $row['client']);

            fputcsv($output, [
                $row['id'],
                CsvCellNeutralizer::neutralize((string) $row['ip_address']),
                CsvCellNeutralizer::neutralize((string) $row['name']),
                CsvCellNeutralizer::neutralize((string) $row['username']),
                CsvCellNeutralizer::neutralize((string) $row['status']),
                CsvCellNeutralizer::neutralize((string) $row['reason']),
                CsvCellNeutralizer::neutralize($whereAt),
                CsvCellNeutralizer::neutralize((string) $row['country']),
                CsvCellNeutralizer::neutralize((string) $row['country_code']),
                CsvCellNeutralizer::neutralize((string) $row['region']),
                CsvCellNeutralizer::neutralize((string) $row['city']),
                CsvCellNeutralizer::neutralize((string) $row['isp']),
                CsvCellNeutralizer::neutralize((string) $row['asn']),
                CsvCellNeutralizer::neutralize((string) $row['browser']),
                CsvCellNeutralizer::neutralize((string) $row['operating_system']),
                CsvCellNeutralizer::neutralize((string) $row['user_agent']),
                CsvCellNeutralizer::neutralize(LoginGuardHelper::formatConfiguredDateTime((string) $row['created'])),
            ]);
        }

        fclose($output);
        $app->close();
    }
}
