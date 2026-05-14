<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Dashboard;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var object */
    public $actions;

    /** @var int */
    protected $successLoginCount = 0;

    /** @var int */
    protected $failedLoginCount = 0;

    /** @var array<string, int> */
    protected $originCounts = [];

    /** @var array<int, object> */
    protected $recentActivity = [];

    /** @var array<string, int> */
    protected $topFailureReasons = [];

    /** @var array<int, object> */
    protected $topFailedIps = [];

    /** @var string */
    public $sidebar = '';

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('core.manage');
        LoginGuardHelper::requirePermission('loginguard.view');

        $this->successLoginCount = (int) $this->get('SuccessLoginCount');
        $this->failedLoginCount  = (int) $this->get('FailedLoginCount');
        $this->originCounts      = (array) $this->get('OriginCounts');
        $this->recentActivity    = (array) $this->get('RecentActivity');
        $this->topFailureReasons = (array) $this->get('TopFailureReasons');
        $this->topFailedIps      = (array) $this->get('TopFailedIps');
        $this->actions           = LoginGuardHelper::getActions();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }
        LoginGuardHelper::addSubmenu('dashboard');
        $this->sidebar = \Joomla\CMS\HTML\HTMLHelper::_('sidebar.render');

        ToolbarHelper::title('LoginGuard: Dashboard', 'shield-alt');

        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }
}
