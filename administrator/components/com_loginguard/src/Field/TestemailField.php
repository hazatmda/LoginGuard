<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

final class TestemailField extends FormField
{
    protected $type = 'Testemail';

    protected function getInput(): string
    {
        if (!Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_loginguard')) {
            return '';
        }

        $action = Route::_('index.php?option=com_loginguard&task=testemail.send');
        return '<button type="submit" class="btn btn-secondary" formmethod="post" formaction="'
            . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars(Text::_('COM_LOGINGUARD_TEST_EMAIL_BUTTON'), ENT_QUOTES, 'UTF-8')
            . '</button>';
    }
}
