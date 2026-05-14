<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=tools'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
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
        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>
</form>
