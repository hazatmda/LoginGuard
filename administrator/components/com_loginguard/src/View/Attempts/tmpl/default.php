<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var Joomla\Component\LoginGuard\Administrator\View\Attempts\HtmlView $this */

$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$search    = $this->escape((string) $this->state->get('filter.search'));
$status    = (string) $this->state->get('filter.status');
$client    = (string) $this->state->get('filter.client');
?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row mb-3">
        <div class="col-md-4">
            <label for="filter_search" class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_FILTER_SEARCH_LABEL'); ?></label>
            <input type="text" name="filter_search" id="filter_search" value="<?php echo $search; ?>" class="form-control" placeholder="<?php echo Text::_('COM_LOGINGUARD_FILTER_SEARCH_LABEL'); ?>">
        </div>
        <div class="col-md-3">
            <label for="filter_status" class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_FILTER_STATUS_LABEL'); ?></label>
            <select name="filter_status" id="filter_status" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('COM_LOGINGUARD_FILTER_STATUS_ALL'); ?></option>
                <option value="success"<?php echo $status === 'success' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_STATUS_SUCCESS'); ?></option>
                <option value="failed"<?php echo $status === 'failed' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_STATUS_FAILED'); ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <label for="filter_client" class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_FILTER_CLIENT_LABEL'); ?></label>
            <select name="filter_client" id="filter_client" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo Text::_('COM_LOGINGUARD_FILTER_CLIENT_ALL'); ?></option>
                <option value="site"<?php echo $client === 'site' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_CLIENT_SITE'); ?></option>
                <option value="administrator"<?php echo $client === 'administrator' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_CLIENT_ADMINISTRATOR'); ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>"><?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?></a>
        </div>
    </div>

    <table class="table table-striped" id="loginguardAttemptsList">
        <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_ATTEMPTS_TITLE'); ?></caption>
        <thead>
            <tr>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'JGLOBAL_FIELD_ID_LABEL', 'a.id', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_USERNAME', 'a.username', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_STATUS', 'a.status', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'a.ip_address', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_CLIENT', 'a.client', $listDirn, $listOrder); ?></th>
                <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_REASON'); ?></th>
                <th scope="col"><?php echo HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_CREATED', 'a.created', $listDirn, $listOrder); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($this->items)) : ?>
                <tr>
                    <td colspan="7"><?php echo Text::_('COM_LOGINGUARD_NO_ATTEMPTS'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($this->items as $item) : ?>
                    <tr>
                        <td><?php echo (int) $item->id; ?></td>
                        <td><?php echo $this->escape($item->username); ?></td>
                        <td><?php echo $this->escape($item->status); ?></td>
                        <td><?php echo $this->escape($item->ip_address); ?></td>
                        <td><?php echo $this->escape($item->client); ?></td>
                        <td><?php echo $this->escape($item->reason); ?></td>
                        <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>">
    <input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
