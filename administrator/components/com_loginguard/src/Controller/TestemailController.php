<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use LoginGuard\Component\LoginGuard\Administrator\Service\OperationalAudit;
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

        try {
            $component = $app->bootComponent('com_config');
            $model = $component->getMVCFactory()->createModel('Application', 'Administrator', ['ignore_request' => true]);
            $config = Factory::getConfig();
            $mailConfig = [];
            foreach (['smtpauth', 'smtpuser', 'smtppass', 'smtphost', 'smtpsecure', 'smtpport', 'mailfrom', 'fromname', 'mailer', 'mailonline'] as $key) {
                $mailConfig[$key] = $config->get($key);
            }

            // Joomla's native com_config model reads its mail contract exclusively
            // from the JSON input bag. Populate it from the saved Global
            // Configuration so this control exercises the exact persisted setup.
            foreach ($mailConfig as $key => $value) {
                $app->getInput()->json->set($key, $value);
            }

            if ($model && method_exists($model, 'sendTestMail')) {
                if ($model->sendTestMail() !== true) {
                    throw new \RuntimeException('Joomla native test mail reported a delivery failure.');
                }
            } else {
                // Joomla 5.2 compatibility: exercise Joomla's configured mailer
                // directly, addressed to the current administrator.
                $recipient = (string) ($app->getIdentity()->email ?? '');
                if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('The administrator account has no valid email address.');
                }
                $mailer = Factory::getContainer()->get(\Joomla\CMS\Mail\MailerFactoryInterface::class)->createMailer();
                $mailer->addRecipient($recipient);
                $mailer->setSubject(Text::_('COM_CONFIG_SENDMAIL_SUBJECT'));
                $mailer->setBody(Text::_('COM_CONFIG_SENDMAIL_BODY'));
                if ($mailer->Send() === false) throw new \RuntimeException('Joomla mailer rejected the test message.');
            }
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            OperationalAudit::recordHealth($db, 'mail', 'healthy', 'Joomla native test mail completed successfully.');
            $app->enqueueMessage(Text::_('COM_LOGINGUARD_TEST_EMAIL_SUCCESS'), 'success');
        } catch (Throwable $exception) {
            $db ??= Factory::getContainer()->get(DatabaseInterface::class);
            OperationalAudit::recordHealth($db, 'mail', 'degraded', 'Administrator test email delivery failed.');
            $app->enqueueMessage(Text::_('COM_LOGINGUARD_TEST_EMAIL_FAILURE'), 'error');
        }
        $this->setRedirect(Route::_('index.php?option=com_config&view=component&component=com_loginguard', false));
    }
}
