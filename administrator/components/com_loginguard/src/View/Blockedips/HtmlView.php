<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Blockedips;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
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

    /** @var object|null */
    public $editItem;

    /** @var object */
    public $actions;

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('loginguard.manage_blocks');

        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->editItem      = $this->get('EditItem');
        $this->actions       = LoginGuardHelper::getActions();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Blocked IPs', 'shield-alt');
        ToolbarHelper::custom('blockedips.enable', 'publish', '', 'COM_LOGINGUARD_TOOLBAR_ENABLE', true);
        ToolbarHelper::custom('blockedips.disable', 'unpublish', '', 'COM_LOGINGUARD_TOOLBAR_DISABLE', true);
        ToolbarHelper::custom('blockedips.unblock', 'unblock', '', 'COM_LOGINGUARD_TOOLBAR_UNBLOCK', true);
        ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'blockedips.delete', 'JTOOLBAR_DELETE');

        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }
}
