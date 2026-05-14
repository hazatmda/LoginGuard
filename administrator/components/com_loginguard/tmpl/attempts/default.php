<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');
HTMLHelper::_('searchtools.form', '#adminForm');

$listOrder = $this->escape((string) $this->state->get('list.ordering'));
$listDirn  = $this->escape((string) $this->state->get('list.direction'));
?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo HTMLHelper::_('searchtools.default', array('view' => $this)); ?>

        <table class="table table-striped" id="loginguardAttemptsList">
            <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_ATTEMPTS_TITLE'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_FIELD_ID_LABEL', 'id', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'ip_address', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_NAME', 'name', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_USERNAME', 'username', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_STATUS', 'status', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_DATETIME', 'created', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_COUNTRY', 'country', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_BROWSER', 'browser', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_OS', 'operating_system', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_WHERE', 'client', $listDirn, $listOrder); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->items)) : ?>
                    <tr>
                        <td colspan="10" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($this->items as $item) : ?>
                        <tr>
                            <td><?php echo (int) $item->id; ?></td>
                            <td><?php echo $this->escape((string) $item->ip_address); ?></td>
                            <td><?php echo $this->escape((string) $item->name); ?></td>
                            <td><?php echo $this->escape((string) $item->username); ?></td>
                            <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_STATUS_' . strtoupper((string) $item->status))); ?></td>
                            <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                            <td><?php echo $this->escape((string) $item->country); ?></td>
                            <td><?php echo $this->escape((string) $item->browser); ?></td>
                            <td><?php echo $this->escape((string) $item->operating_system); ?></td>
                            <td><?php echo $this->escape((string) $item->client); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
