<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseDriver;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
use Throwable;

final class HtmlView extends BaseHtmlView
{
    public $actions;
    protected $telemetryCounts = [];
    protected $recentActivity = [];
    protected $topFailureReasons = [];
    protected $topFailedIps = [];
    protected $blockedIpTelemetry = [];
    protected $recentBlockedIps = [];
    protected $cleanupMetrics = [];
    protected $attackOriginSummary = [];
    protected $operationalStatus = [];
    protected $healthStatus = [];
    protected $mfaTelemetry = [];
    protected $dashboardTimeframe = 'today';

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('core.manage');
        LoginGuardHelper::requirePermission('loginguard.view');

        $user = Factory::getApplication()->getIdentity();
        $this->dashboardTimeframe = (string) $user->getParam('loginguard_dashboard_timeframe', 'today');
        if (!in_array($this->dashboardTimeframe, ['today', '24h', '7d', 'all'], true)) {
            $this->dashboardTimeframe = 'today';
        }

        $model = $this->getModel();
        if ($model) {
            $model->setState('dashboard.timeframe', $this->dashboardTimeframe);
        }

        $this->telemetryCounts = (array) $this->get('TelemetryCounts');
        $this->recentActivity = (array) $this->get('RecentActivity');
        $this->topFailureReasons = (array) $this->get('TopFailureReasons');
        $this->topFailedIps = (array) $this->get('TopFailedIps');
        $this->blockedIpTelemetry = (array) $this->get('BlockedIpTelemetry');
        $this->recentBlockedIps = (array) $this->get('RecentBlockedIps');
        $this->cleanupMetrics = (array) $this->get('CleanupMetrics');
        $this->attackOriginSummary = (array) $this->get('AttackOriginSummary');
        $this->operationalStatus = (array) $this->get('OperationalStatus');
        $this->healthStatus = $this->loadHealthStatus();
        $this->mfaTelemetry = $this->loadMfaTelemetry();
        $this->actions = LoginGuardHelper::getActions();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Dashboard', 'shield-alt');
        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }

    /** @return array<string, array{status:string,message:string,updated:string}> */
    private function loadHealthStatus(): array
    {
        $health = [];
        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('health_key'), $db->quoteName('status'), $db->quoteName('message'), $db->quoteName('updated')])
                ->from($db->quoteName('#__loginguard_health'))
                ->order($db->quoteName('updated') . ' DESC');
            $db->setQuery($query);

            foreach ($db->loadObjectList() ?: [] as $row) {
                $key = (string) $row->health_key;
                if (!isset($health[$key])) {
                    $health[$key] = [
                        'status' => (string) $row->status,
                        'message' => (string) $row->message,
                        'updated' => (string) $row->updated,
                    ];
                }
            }
        } catch (Throwable $exception) {
            $health['database'] = ['status' => 'degraded', 'message' => $exception->getMessage(), 'updated' => gmdate('Y-m-d H:i:s')];
        }

        return $health;
    }

    /** @return array<string, int> */
    private function loadMfaTelemetry(): array
    {
        $counts = ['pending' => 0, 'failed' => 0, 'success' => 0, 'try_limit' => 0];
        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->select([$db->quoteName('status'), 'COUNT(*) AS ' . $db->quoteName('total')])
                ->from($db->quoteName('#__loginguard_attempts'))
                ->where($db->quoteName('status') . ' IN (' . implode(',', array_map([$db, 'quote'], ['MFA_PENDING', 'MFA_FAILED', 'MFA_SUCCESS', 'MFA_TRY_LIMIT'])) . ')');

            $start = $this->getTimeframeStart();
            if ($start !== '') {
                $query->where($db->quoteName('created') . ' >= ' . $db->quote($start));
            }
            $query->group($db->quoteName('status'));
            $db->setQuery($query);

            $mapping = ['MFA_PENDING' => 'pending', 'MFA_FAILED' => 'failed', 'MFA_SUCCESS' => 'success', 'MFA_TRY_LIMIT' => 'try_limit'];
            foreach ($db->loadObjectList() ?: [] as $row) {
                if (isset($mapping[(string) $row->status])) {
                    $counts[$mapping[(string) $row->status]] = (int) $row->total;
                }
            }
        } catch (Throwable) {
            // Dashboard remains available even if pre-migration data is incomplete.
        }

        return $counts;
    }

    private function getTimeframeStart(): string
    {
        return match ($this->dashboardTimeframe) {
            '24h' => gmdate('Y-m-d H:i:s', time() - 86400),
            '7d' => gmdate('Y-m-d H:i:s', time() - (7 * 86400)),
            'all' => '',
            default => gmdate('Y-m-d 00:00:00'),
        };
    }
}
