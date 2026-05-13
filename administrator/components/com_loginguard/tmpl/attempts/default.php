<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');

$listOrder = $this->state->get('list.ordering');
$listDirn  = $this->state->get('list.direction');
?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="input-group">
                <input type="text" name="filter_search" id="filter_search" class="form-control" value="<?php echo htmlspecialchars((string) $this->state->get('filter.search'), ENT_QUOTES, 'UTF-8'); ?>" placeholder="<?php echo Text::_('COM_LOGINGUARD_SEARCH_PLACEHOLDER'); ?>">
                <button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
                <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts&filter_search=&filter_status='); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
            </div>
        </div>
        <div class="col-md-3">
            <select name="filter_status" id="filter_status" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('COM_LOGINGUARD_FILTER_STATUS'); ?></option>
                <?php echo HTMLHelper::_('select.options', [
                    HTMLHelper::_('select.option', 'success', Text::_('COM_LOGINGUARD_STATUS_SUCCESS')),
                    HTMLHelper::_('select.option', 'failed', Text::_('COM_LOGINGUARD_STATUS_FAILED')),
                ], 'value', 'text', (string) $this->state->get('filter.status')); ?>
            </select>
        </div>
    </div>

    <table class="table table-striped" id="loginguardAttemptsList">
        <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_ATTEMPTS_TITLE'); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_FIELD_ID_LABEL', 'id', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_USERNAME', 'username', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_STATUS', 'status', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'ip_address', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_CLIENT', 'client', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_REASON'); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_CREATED', 'created', $listDirn, $listOrder); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($this->items)) : ?>
                <tr>
                    <td colspan="7" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($this->items as $item) : ?>
                    <tr>
                        <td><?php echo (int) $item->id; ?></td>
                        <td><?php echo htmlspecialchars((string) $item->username, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item->status, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item->ip_address, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item->client, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars((string) $item->reason, ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="filter_order" value="<?php echo htmlspecialchars((string) $listOrder, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo htmlspecialchars((string) $listDirn, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
