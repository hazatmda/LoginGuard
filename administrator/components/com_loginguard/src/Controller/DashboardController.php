<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Database\DatabaseInterface;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
use LoginGuard\Component\LoginGuard\Administrator\Service\CleanupService;

final class DashboardController extends BaseController
{
    public function cleanup(): void
    {
        LoginGuardHelper::requirePermission('core.admin');
        $this->checkToken();

        $container = Factory::getContainer();
        $service = new CleanupService(
            $container->get(DatabaseInterface::class),
            ComponentHelper::getParams('com_loginguard')
        );
        $metrics = $service->execute();

        $this->setMessage(Text::sprintf('COM_LOGINGUARD_DASHBOARD_CLEANUP_RUN_COMPLETE', (int) $metrics['total_deleted'], (int) $metrics['batches']));
        $this->setRedirect('index.php?option=com_loginguard&view=dashboard');
    }

    public function setTodayTimeframe(): void
    {
        $this->setDashboardTimeframe('today');
    }

    public function set24hTimeframe(): void
    {
        $this->setDashboardTimeframe('24h');
    }

    public function set7dTimeframe(): void
    {
        $this->setDashboardTimeframe('7d');
    }

    public function setAllTimeframe(): void
    {
        $this->setDashboardTimeframe('all');
    }


    private function setDashboardTimeframe(string $timeframe): void
    {
        LoginGuardHelper::requirePermission('core.manage');
        LoginGuardHelper::requirePermission('loginguard.view');
        $this->checkToken();

        if (!in_array($timeframe, ['today', '24h', '7d', 'all'], true)) {
            $timeframe = 'today';
        }

        $user = Factory::getApplication()->getIdentity();
        $user->setParam('loginguard_dashboard_timeframe', $timeframe);
        $user->save(true);

        $this->setMessage(Text::_('COM_LOGINGUARD_DASHBOARD_TIMEFRAME_SAVED'));
        $this->setRedirect('index.php?option=com_loginguard&view=dashboard');
    }

}