<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Attempts;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

final class HtmlView extends BaseHtmlView
{
    protected $items;

    protected $pagination;

    protected $state;

    public function display($tpl = null): void
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');

        ToolbarHelper::title(Text::_('COM_LOGINGUARD_ATTEMPTS_TITLE'), 'shield-alt');

        parent::display($tpl);
    }
}
