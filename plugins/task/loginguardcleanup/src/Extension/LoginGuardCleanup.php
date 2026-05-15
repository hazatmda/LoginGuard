<?php

namespace Joomla\Plugin\Task\LoginGuardCleanup\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\SubscriberInterface;
use LoginGuard\Component\LoginGuard\Administrator\Service\CleanupService;

final class LoginGuardCleanup extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    protected const TASKS_MAP = [
        'loginguard.cleanup' => [
            'langConstPrefix' => 'PLG_TASK_LOGINGUARDCLEANUP_TASK_CLEANUP',
            'method' => 'runCleanup',
        ],
    ];

    protected $autoloadLanguage = true;

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList' => 'advertiseRoutines',
            'onExecuteTask' => 'standardRoutineHandler',
        ];
    }

    private function runCleanup(ExecuteTaskEvent $event): int
    {
        $params = ComponentHelper::getParams('com_loginguard');

        if ((int) $params->get('automatic_cleanup_enabled', 0) !== 1) {
            return Status::OK;
        }

        $container = \Joomla\CMS\Factory::getContainer();
        $service = new CleanupService(
            $container->get(DatabaseDriver::class),
            $params
        );

        $service->execute();

        return Status::OK;
    }
}
