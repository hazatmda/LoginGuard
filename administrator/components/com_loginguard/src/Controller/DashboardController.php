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
}
