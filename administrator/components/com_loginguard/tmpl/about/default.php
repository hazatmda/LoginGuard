<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$details = [
    'COM_LOGINGUARD_ABOUT_OWNER' => "Muhammad 'Azizan Hazim",
    'COM_LOGINGUARD_ABOUT_ORGANIZATION' => 'Unit Infrastruktur dan Keselamatan Digital, Bahagian Digital dan Teknologi Maklumat (BDTM)',
    'COM_LOGINGUARD_ABOUT_REPOSITORY' => 'https://github.com/hazatmda/LoginGuard',
    'COM_LOGINGUARD_ABOUT_ISSUES' => 'https://github.com/hazatmda/LoginGuard/issues',
    'COM_LOGINGUARD_ABOUT_VERSION_LABEL' => '0.2.11-alpha',
    'COM_LOGINGUARD_ABOUT_JOOMLA_COMPATIBILITY' => 'Joomla 4.4+ / 5.x administrator MVC',
    'COM_LOGINGUARD_ABOUT_PHP_COMPATIBILITY' => 'PHP 8.1+',
    'COM_LOGINGUARD_ABOUT_RELEASE_CHANNEL' => 'Alpha operational security dashboard release',
];
?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=about'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card h-100">
                    <div class="card-body">
                        <h2><?php echo Text::_('COM_LOGINGUARD_ABOUT_TITLE'); ?></h2>
                        <p class="lead"><?php echo Text::_('COM_LOGINGUARD_ABOUT_DESC'); ?></p>
                        <dl class="row">
                            <?php foreach ($details as $label => $value) : ?>
                                <dt class="col-sm-4"><?php echo Text::_($label); ?></dt>
                                <dd class="col-sm-8"><?php echo $this->escape($value); ?></dd>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3 class="h5"><?php echo Text::_('COM_LOGINGUARD_ABOUT_SUPPORT_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_LOGINGUARD_ABOUT_SUPPORT_DESC'); ?></p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <h3 class="h5"><?php echo Text::_('COM_LOGINGUARD_ABOUT_OPERATIONAL_GUIDANCE_TITLE'); ?></h3>
                        <p><?php echo Text::_('COM_LOGINGUARD_ABOUT_OPERATIONAL_GUIDANCE_DESC'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
