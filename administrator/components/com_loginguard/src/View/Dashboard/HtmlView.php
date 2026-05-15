<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var object */
    public $actions;

    /** @var array<string, int> */
    protected $telemetryCounts = [];

    /** @var array<int, object> */
    protected $recentActivity = [];

    /** @var array<string, int> */
    protected $topFailureReasons = [];

    /** @var array<int, object> */
    protected $topFailedIps = [];

    /** @var array<string, int> */
    protected $blockedIpTelemetry = [];

    /** @var array<int, object> */
    protected $recentBlockedIps = [];

    /** @var array<string, int|string> */
    protected $cleanupMetrics = [];

    /** @var array<int, object> */
    protected $topCountries = [];

    /** @var array<string, int> */
    protected $attackOriginSummary = [];

    /** @var array<string, int|string> */
    protected $operationalStatus = [];

    /** @var bool */
    protected $compactDashboardMode = true;

    /** @var string */
    protected $dashboardTimeframe = 'today';

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('core.manage');
        LoginGuardHelper::requirePermission('loginguard.view');

        $user = Factory::getApplication()->getIdentity();
        $this->dashboardTimeframe = (string) $user->getParam('loginguard_dashboard_timeframe', 'today');

        if (!in_array($this->dashboardTimeframe, ['today', '24h', '7d'], true)) {
            $this->dashboardTimeframe = 'today';
        }

        $model = $this->getModel();

        if ($model) {
            $model->setState('dashboard.timeframe', $this->dashboardTimeframe);
        }

        $this->telemetryCounts   = (array) $this->get('TelemetryCounts');
        $this->recentActivity    = (array) $this->get('RecentActivity');
        $this->topFailureReasons = (array) $this->get('TopFailureReasons');
        $this->topFailedIps      = (array) $this->get('TopFailedIps');
        $this->blockedIpTelemetry = (array) $this->get('BlockedIpTelemetry');
        $this->recentBlockedIps  = (array) $this->get('RecentBlockedIps');
        $this->cleanupMetrics    = (array) $this->get('CleanupMetrics');
        $this->topCountries      = (array) $this->get('TopCountries');
        $this->attackOriginSummary = (array) $this->get('AttackOriginSummary');
        $this->operationalStatus = (array) $this->get('OperationalStatus');
        $this->compactDashboardMode = (bool) $user->getParam('loginguard_compact_dashboard', 1);
        $this->actions           = LoginGuardHelper::getActions();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Dashboard', 'shield-alt');

        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }
}
