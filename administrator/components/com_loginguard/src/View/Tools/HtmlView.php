<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Tools;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var object */
    protected $actions;

    /** @var string */
    public $sidebar = '';

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('core.manage');

        $this->actions = LoginGuardHelper::getActions();
        LoginGuardHelper::addSubmenu('tools');
        $this->sidebar = \Joomla\CMS\HTML\HTMLHelper::_('sidebar.render');

        ToolbarHelper::title('LoginGuard: Tools', 'wrench');

        if ($this->actions->get('loginguard.export')) {
            ToolbarHelper::link('index.php?option=com_loginguard&task=attempts.export&' . Session::getFormToken() . '=1', 'COM_LOGINGUARD_TOOLBAR_EXPORT', 'download');
        }

        parent::display($tpl);
    }
}
