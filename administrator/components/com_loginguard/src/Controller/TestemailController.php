<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use LoginGuard\Component\LoginGuard\Administrator\Service\OperationalAudit;
use LoginGuard\Component\LoginGuard\Administrator\Service\TestEmailService;
use Joomla\Database\DatabaseInterface;
use Throwable;

final class TestemailController extends BaseController
{
    public function send(): void
    {
        $this->checkToken('post');
        $app = $this->getApplication();
        if (!$app->getIdentity()->authorise('core.admin', 'com_loginguard')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $recipients = TestEmailService::normaliseRecipients((string) ComponentHelper::getParams('com_loginguard')->get('audit_alert_recipients', ''));
        if ($recipients === []) {
            $app->enqueueMessage(Text::_('COM_LOGINGUARD_TEST_EMAIL_NO_RECIPIENTS'), 'error');
            $this->setRedirect(Route::_('index.php?option=com_config&view=component&component=com_loginguard', false));
            return;
        }

        try {
            (new TestEmailService())->send($recipients);
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            OperationalAudit::recordHealth($db, 'mail', 'healthy', 'Administrator test email was submitted successfully.');
            $app->enqueueMessage(Text::sprintf('COM_LOGINGUARD_TEST_EMAIL_SUCCESS', implode(', ', $recipients)), 'success');
        } catch (Throwable $exception) {
            $db ??= Factory::getContainer()->get(DatabaseInterface::class);
            OperationalAudit::recordHealth($db, 'mail', 'degraded', 'Administrator test email delivery failed.');
            $app->enqueueMessage(Text::_('COM_LOGINGUARD_TEST_EMAIL_FAILURE'), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_config&view=component&component=com_loginguard', false));
    }
}
