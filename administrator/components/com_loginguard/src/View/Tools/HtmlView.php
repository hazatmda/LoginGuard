<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Tools;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var object */
    protected $actions;

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('core.manage');

        $this->actions = LoginGuardHelper::getActions();

        ToolbarHelper::title('LoginGuard: Tools', 'wrench');

        if ($this->actions->get('loginguard.export')) {
            ToolbarHelper::custom('attempts.export', 'download', '', 'COM_LOGINGUARD_TOOLBAR_EXPORT', false);
        }

        parent::display($tpl);
    }
}
