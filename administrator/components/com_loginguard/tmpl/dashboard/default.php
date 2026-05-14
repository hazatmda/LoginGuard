<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<form action="<?php echo Route::_('index.php?option=com_loginguard'); ?>" method="post" name="adminForm" id="adminForm">
    <?php if (!empty($this->sidebar)) : ?>
        <div id="j-sidebar-container" class="col-md-2">
            <?php echo $this->sidebar; ?>
        </div>
        <div id="j-main-container" class="j-main-container col-md-10">
    <?php else : ?>
        <div id="j-main-container" class="j-main-container">
    <?php endif; ?>
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_DESC'); ?></p>
                    <p><a class="btn btn-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>"><?php echo Text::_('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION'); ?></a></p>
                </div>
            </div>
        </div>
</form>
