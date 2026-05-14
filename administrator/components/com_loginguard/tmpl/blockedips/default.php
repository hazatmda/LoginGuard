<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');
HTMLHelper::_('searchtools.form', '#adminForm');

$listOrder = $this->escape((string) $this->state->get('list.ordering'));
$listDirn  = $this->escape((string) $this->state->get('list.direction'));
$editItem = $this->editItem;
$editing = $editItem !== null;
$blockedUntilValue = $editing && !empty($editItem->blocked_until) ? HTMLHelper::_('date', $editItem->blocked_until, 'Y-m-d\TH:i') : '';
?>
<div id="j-main-container" class="j-main-container">
    <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="blockedIpForm" id="blockedIpForm" class="card mb-4">
        <div class="card-body">
            <h2 class="h4 mb-3"><?php echo Text::_($editing ? 'COM_LOGINGUARD_BLOCKEDIPS_EDIT_TITLE' : 'COM_LOGINGUARD_BLOCKEDIPS_ADD_TITLE'); ?></h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="blockedIpAddress"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></label>
                    <input type="text" name="ip_address" id="blockedIpAddress" class="form-control" required value="<?php echo $editing ? $this->escape((string) $editItem->ip_address) : ''; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="blockedIpScope"><?php echo Text::_('COM_LOGINGUARD_HEADING_SCOPE'); ?></label>
                    <?php $scope = $editing ? (string) $editItem->scope : 'all'; ?>
                    <select name="scope" id="blockedIpScope" class="form-select">
                        <option value="all"<?php echo $scope === 'all' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_ALL'); ?></option>
                        <option value="frontend"<?php echo $scope === 'frontend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_FRONTEND'); ?></option>
                        <option value="backend"<?php echo $scope === 'backend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_BACKEND'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="blockedIpType"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_TYPE'); ?></label>
                    <?php $blockType = $editing ? (string) $editItem->block_type : 'temporary'; ?>
                    <select name="block_type" id="blockedIpType" class="form-select">
                        <option value="temporary"<?php echo $blockType === 'temporary' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_TEMPORARY'); ?></option>
                        <option value="permanent"<?php echo $blockType === 'permanent' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_PERMANENT'); ?></option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="blockedIpUntil"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCKED_UNTIL'); ?></label>
                    <input type="datetime-local" name="blocked_until" id="blockedIpUntil" class="form-control" value="<?php echo $this->escape($blockedUntilValue); ?>">
                </div>
                <div class="col-md-1">
                    <label class="form-label" for="blockedIpFailureCount"><?php echo Text::_('COM_LOGINGUARD_HEADING_FAILURE_COUNT'); ?></label>
                    <input type="number" name="failure_count" id="blockedIpFailureCount" class="form-control" min="0" value="<?php echo $editing ? (int) $editItem->failure_count : 0; ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label" for="blockedIpEnabled"><?php echo Text::_('COM_LOGINGUARD_HEADING_ENABLED'); ?></label>
                    <?php $enabled = !$editing || (int) $editItem->enabled === 1; ?>
                    <select name="enabled" id="blockedIpEnabled" class="form-select">
                        <option value="1"<?php echo $enabled ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_ENABLED'); ?></option>
                        <option value="0"<?php echo !$enabled ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_DISABLED'); ?></option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="blockedIpReason"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></label>
                    <input type="text" name="reason" id="blockedIpReason" class="form-control" maxlength="50" value="<?php echo $editing ? $this->escape((string) $editItem->reason) : 'manual'; ?>">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary"><?php echo Text::_($editing ? 'JSAVE' : 'COM_LOGINGUARD_BLOCKEDIPS_ADD_BUTTON'); ?></button>
                    <?php if ($editing) : ?>
                        <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>"><?php echo Text::_('JCANCEL'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <input type="hidden" name="id" value="<?php echo $editing ? (int) $editItem->id : 0; ?>">
        <input type="hidden" name="task" value="blockedips.save">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>

    <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <table class="table table-striped" id="loginguardBlockedIpsList">
            <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_TITLE'); ?></caption>
            <thead>
                <tr>
                    <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'id', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'ip_address', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_SCOPE', 'scope', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_BLOCK_TYPE', 'block_type', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_ENABLED', 'enabled', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_FAILURE_COUNT', 'failure_count', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_BLOCKED_UNTIL', 'blocked_until', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_DATETIME', 'created', $listDirn, $listOrder); ?></th>
                    <th scope="col"><?php echo Text::_('JACTION_EDIT'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($this->items)) : ?>
                    <tr>
                        <td colspan="11" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($this->items as $i => $item) : ?>
                        <tr>
                            <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                            <td><?php echo (int) $item->id; ?></td>
                            <td><?php echo $this->escape((string) $item->ip_address); ?></td>
                            <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_SCOPE_' . strtoupper((string) $item->scope))); ?></td>
                            <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_TYPE_' . strtoupper((string) $item->block_type))); ?></td>
                            <td><?php echo $this->escape(Text::_((int) $item->enabled === 1 ? 'COM_LOGINGUARD_BLOCKEDIPS_ENABLED' : 'COM_LOGINGUARD_BLOCKEDIPS_DISABLED')); ?></td>
                            <td><?php echo $this->escape((string) $item->reason); ?></td>
                            <td><?php echo (int) $item->failure_count; ?></td>
                            <td><?php echo empty($item->blocked_until) ? Text::_('COM_LOGINGUARD_BLOCKEDIPS_PERMANENT') : HTMLHelper::_('date', $item->blocked_until, Text::_('DATE_FORMAT_LC5')); ?></td>
                            <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                            <td><a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips&edit_id=' . (int) $item->id); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php echo $this->pagination->getListFooter(); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
