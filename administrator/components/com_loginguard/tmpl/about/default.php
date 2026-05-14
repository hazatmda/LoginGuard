<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=about'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
            <div class="card">
                <div class="card-body">
                    <h2><?php echo Text::_('COM_LOGINGUARD_ABOUT_TITLE'); ?></h2>
                    <p><?php echo Text::_('COM_LOGINGUARD_ABOUT_DESC'); ?></p>
                    <p><?php echo Text::sprintf('COM_LOGINGUARD_ABOUT_VERSION', '0.2.4-alpha'); ?></p>
                </div>
            </div>
        </div>
</form>
