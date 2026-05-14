<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Attempts;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    /** @var array<int, object> */
    protected $items = [];

    /** @var object */
    protected $pagination;

    /** @var object */
    protected $state;

    /** @var object|null */
    public $filterForm;

    /** @var array<string, mixed> */
    public $activeFilters = [];

    /** @var object */
    public $actions;

    /** @var string */
    public $sidebar = '';

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('loginguard.view');

        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->actions       = LoginGuardHelper::getActions();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        LoginGuardHelper::addSubmenu('attempts');
        $this->sidebar = \Joomla\CMS\HTML\HTMLHelper::_('sidebar.render');

        ToolbarHelper::title('LoginGuard: Login Information', 'shield-alt');

        if ($this->actions->get('loginguard.export')) {
            ToolbarHelper::link('index.php?option=com_loginguard&task=attempts.export&' . Session::getFormToken() . '=1', 'COM_LOGINGUARD_TOOLBAR_EXPORT', 'download');
        }

        if ($this->actions->get('loginguard.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'attempts.delete', 'JTOOLBAR_DELETE');
        }

        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }
}
