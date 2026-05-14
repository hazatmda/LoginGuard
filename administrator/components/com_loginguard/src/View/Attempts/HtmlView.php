<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Attempts;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

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

    public function display($tpl = null): void
    {
        $this->items         = $this->get('Items');
        $this->pagination    = $this->get('Pagination');
        $this->state         = $this->get('State');
        $this->filterForm    = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Login Attempts', 'shield-alt');

        parent::display($tpl);
    }
}
