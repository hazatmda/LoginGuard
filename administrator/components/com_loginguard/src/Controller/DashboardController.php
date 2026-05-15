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

    public function setCompactDensity(): void
    {
        $this->setDashboardDensity(1);
    }

    public function setComfortableDensity(): void
    {
        $this->setDashboardDensity(0);
    }

    private function setDashboardDensity(int $compactMode): void
    {
        LoginGuardHelper::requirePermission('core.manage');
        LoginGuardHelper::requirePermission('loginguard.view');
        $this->checkToken();

        $user = Factory::getApplication()->getIdentity();
        $user->setParam('loginguard_compact_dashboard', $compactMode);
        $user->save(true);

        $message = $compactMode === 1
            ? 'COM_LOGINGUARD_DASHBOARD_COMPACT_MODE_SAVED'
            : 'COM_LOGINGUARD_DASHBOARD_COMFORTABLE_MODE_SAVED';

        $this->setMessage(Text::_($message));
        $this->setRedirect('index.php?option=com_loginguard&view=dashboard');
    }
}
