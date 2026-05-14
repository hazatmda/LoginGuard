<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=tools'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
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
                    <h2><?php echo Text::_('COM_LOGINGUARD_TOOLS_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_LOGINGUARD_TOOLS_DESC'); ?></p>
                    <ul>
                        <li><?php echo Text::_('COM_LOGINGUARD_TOOLS_EXPORT_NOTE'); ?></li>
                        <li><?php echo Text::_('COM_LOGINGUARD_TOOLS_RETENTION_NOTE'); ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</form>
