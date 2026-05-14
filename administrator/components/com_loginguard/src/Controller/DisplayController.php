<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

final class DisplayController extends BaseController
{
    protected $default_view = 'dashboard';

    public function display($cachable = false, $urlparams = []): self
    {
        LoginGuardHelper::requirePermission('core.manage');

        $view = $this->input->getCmd('view', $this->default_view);

        if (in_array($view, ['dashboard', 'attempts'], true)) {
            LoginGuardHelper::requirePermission('loginguard.view');
        }

        if ($view === 'configuration') {
            LoginGuardHelper::requirePermission('core.admin');
        }

        return parent::display($cachable, $urlparams);
    }
}
