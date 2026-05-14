<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\About;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
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
        LoginGuardHelper::addSubmenu('about');
        $this->sidebar = \Joomla\CMS\HTML\HTMLHelper::_('sidebar.render');

        ToolbarHelper::title('LoginGuard: About', 'info-circle');

        parent::display($tpl);
    }
}
