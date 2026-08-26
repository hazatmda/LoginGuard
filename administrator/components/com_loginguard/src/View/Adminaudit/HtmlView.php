<?php

namespace LoginGuard\Component\LoginGuard\Administrator\View\Adminaudit;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class HtmlView extends BaseHtmlView
{
    protected $items = [];
    protected $pagination;

    public function display($tpl = null): void
    {
        LoginGuardHelper::requirePermission('loginguard.view');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');

        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        ToolbarHelper::title('LoginGuard: Administrator Audit', 'shield-alt');
        parent::display($tpl);
    }
}
