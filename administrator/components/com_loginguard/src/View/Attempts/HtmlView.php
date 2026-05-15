<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Attempts;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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

    /** @var object */
    public $actions;

    /** @var array<string, string> */
    public $availableColumns = [];

    /** @var array<int, string> */
    public $visibleColumns = [];

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('loginguard.view');

        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->actions       = LoginGuardHelper::getActions();
        $this->availableColumns = $this->getAvailableColumns();
        $this->visibleColumns = $this->getVisibleColumns();

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Login Information', 'shield-alt');

        if ($this->actions->get('loginguard.export')) {
            ToolbarHelper::custom('attempts.export', 'download', '', 'COM_LOGINGUARD_TOOLBAR_EXPORT', false);
        }

        if ($this->actions->get('loginguard.delete')) {
            ToolbarHelper::deleteList('JGLOBAL_CONFIRM_DELETE', 'attempts.delete', 'JTOOLBAR_DELETE');
        }

        if ($this->actions->get('core.admin')) {
            ToolbarHelper::preferences('com_loginguard');
        }

        parent::display($tpl);
    }

    /**
     * @return array<string, string>
     */
    private function getAvailableColumns(): array
    {
        return [
            'ip_address' => 'COM_LOGINGUARD_HEADING_IP_ADDRESS',
            'name' => 'COM_LOGINGUARD_HEADING_NAME',
            'username' => 'COM_LOGINGUARD_HEADING_USERNAME',
            'status' => 'COM_LOGINGUARD_HEADING_STATUS',
            'reason' => 'COM_LOGINGUARD_HEADING_FAILURE_REASON',
            'where_at' => 'COM_LOGINGUARD_HEADING_WHERE',
            'country' => 'COM_LOGINGUARD_HEADING_COUNTRY',
            'city' => 'COM_LOGINGUARD_HEADING_CITY',
            'isp' => 'COM_LOGINGUARD_HEADING_ISP',
            'asn' => 'COM_LOGINGUARD_HEADING_ASN',
            'browser' => 'COM_LOGINGUARD_HEADING_BROWSER',
            'operating_system' => 'COM_LOGINGUARD_HEADING_OS',
            'user_agent' => 'COM_LOGINGUARD_HEADING_USER_AGENT',
            'created' => 'COM_LOGINGUARD_HEADING_DATETIME',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getVisibleColumns(): array
    {
        $app = Factory::getApplication();
        $default = array_keys($this->availableColumns);
        $requested = $app->getInput()->get('visible_columns', null, 'array');

        if (is_array($requested)) {
            $columns = array_values(array_intersect($requested, $default));
            $app->setUserState('com_loginguard.attempts.visible_columns', $columns);

            return $columns;
        }

        $columns = $app->getUserState('com_loginguard.attempts.visible_columns', $default);

        return array_values(array_intersect(is_array($columns) ? $columns : $default, $default));
    }
}
