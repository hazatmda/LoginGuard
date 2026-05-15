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
$nowTimestamp = time();
$nowSql = date('Y-m-d H:i:s', $nowTimestamp);
$blockTelemetry = $this->blockedIpTelemetry ?? [];

$statusForBlock = static function (object $item) use ($nowSql): array {
    if ((int) $item->enabled !== 1) {
        return ['disabled', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_DISABLED', 'bg-dark'];
    }

    $blockType = (string) $item->block_type;
    $blockedUntil = (string) $item->blocked_until;

    if ($blockType === 'permanent') {
        return ['permanent', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_PERMANENT', 'bg-primary'];
    }

    if ($blockedUntil === '') {
        return ['expired', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY_NO_EXPIRY', 'bg-warning text-dark'];
    }

    if ($blockedUntil < $nowSql) {
        return ['expired', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_EXPIRED', 'bg-danger'];
    }

    return ['temporary', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY', 'bg-info text-dark'];
};

$expirationForBlock = static function (object $item) use ($nowTimestamp): array {
    if ((string) $item->block_type === 'permanent') {
        return ['COM_LOGINGUARD_BLOCKEDIPS_EXPIRATION_PERMANENT', 'text-primary'];
    }

    $blockedUntil = (string) $item->blocked_until;

    if ($blockedUntil === '') {
        return ['COM_LOGINGUARD_BLOCKEDIPS_EXPIRATION_NO_EXPIRY', 'text-warning'];
    }

    $expires = strtotime($blockedUntil);

    if ($expires === false || $expires <= $nowTimestamp) {
        return ['COM_LOGINGUARD_BLOCKEDIPS_EXPIRATION_EXPIRED', 'text-danger'];
    }

    $remaining = $expires - $nowTimestamp;

    if ($remaining < 3600) {
        return [Text::sprintf('COM_LOGINGUARD_BLOCKEDIPS_EXPIRES_IN_MINUTES', max(1, (int) ceil($remaining / 60))), 'text-info'];
    }

    if ($remaining < 86400) {
        return [Text::sprintf('COM_LOGINGUARD_BLOCKEDIPS_EXPIRES_IN_HOURS', max(1, (int) ceil($remaining / 3600))), 'text-info'];
    }

    return [Text::sprintf('COM_LOGINGUARD_BLOCKEDIPS_EXPIRES_IN_DAYS', max(1, (int) ceil($remaining / 86400))), 'text-info'];
};
?>
<div id="j-main-container" class="j-main-container">
    <div class="row g-3 mb-3">
        <?php foreach ([
            ['active', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_ACTIVE', 'text-bg-success'],
            ['temporary', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_TEMPORARY', 'text-bg-info'],
            ['permanent', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_PERMANENT', 'text-bg-primary'],
            ['expired', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_EXPIRED', 'text-bg-danger'],
        ] as $metric) : ?>
            <div class="col-sm-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <span class="text-muted small text-uppercase fw-semibold"><?php echo Text::_($metric[1]); ?></span>
                            <span class="badge <?php echo $this->escape($metric[2]); ?> rounded-pill"><?php echo (int) ($blockTelemetry[$metric[0]] ?? 0); ?></span>
                        </div>
                        <div class="display-6 mb-0"><?php echo (int) ($blockTelemetry[$metric[0]] ?? 0); ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <details class="card mb-3"<?php echo $editing ? ' open' : ''; ?>>
        <summary class="card-header d-flex justify-content-between align-items-center gap-2">
            <span class="h5 mb-0"><?php echo Text::_($editing ? 'COM_LOGINGUARD_BLOCKEDIPS_EDIT_TITLE' : 'COM_LOGINGUARD_BLOCKEDIPS_ADD_TITLE'); ?></span>
            <span class="small text-muted"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_COLLAPSE_HINT'); ?></span>
        </summary>
        <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="blockedIpForm" id="blockedIpForm" class="card-body">
            <p class="text-muted small mb-3"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_FORM_GUIDANCE'); ?></p>
            <div class="row g-2 align-items-end">
                <div class="col-md-3 col-xl-2">
                    <label class="form-label small" for="blockedIpAddress"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></label>
                    <input type="text" name="ip_address" id="blockedIpAddress" class="form-control form-control-sm" required value="<?php echo $editing ? $this->escape((string) $editItem->ip_address) : ''; ?>">
                </div>
                <div class="col-md-3 col-xl-2">
                    <label class="form-label small" for="blockedIpScope"><?php echo Text::_('COM_LOGINGUARD_HEADING_SCOPE'); ?></label>
                    <?php $scope = $editing ? (string) $editItem->scope : 'all'; ?>
                    <select name="scope" id="blockedIpScope" class="form-select form-select-sm">
                        <option value="all"<?php echo $scope === 'all' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_ALL'); ?></option>
                        <option value="frontend"<?php echo $scope === 'frontend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_FRONTEND'); ?></option>
                        <option value="backend"<?php echo $scope === 'backend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_BACKEND'); ?></option>
                    </select>
                </div>
                <div class="col-md-3 col-xl-2">
                    <label class="form-label small" for="blockedIpType"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_TYPE'); ?></label>
                    <?php $blockType = $editing ? (string) $editItem->block_type : 'temporary'; ?>
                    <select name="block_type" id="blockedIpType" class="form-select form-select-sm">
                        <option value="temporary"<?php echo $blockType === 'temporary' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_TEMPORARY'); ?></option>
                        <option value="permanent"<?php echo $blockType === 'permanent' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_PERMANENT'); ?></option>
                    </select>
                </div>
                <div class="col-md-3 col-xl-2">
                    <label class="form-label small" for="blockedIpUntil" title="<?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCKEDIPS_BLOCKED_UNTIL_TOOLTIP')); ?>"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCKED_UNTIL'); ?></label>
                    <input type="datetime-local" name="blocked_until" id="blockedIpUntil" class="form-control form-control-sm" value="<?php echo $this->escape($blockedUntilValue); ?>" aria-describedby="blockedIpUntilHelp">
                    <div id="blockedIpUntilHelp" class="form-text small"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_BLOCKED_UNTIL_COMPACT_HELP'); ?></div>
                </div>
                <div class="col-md-2 col-xl-1">
                    <label class="form-label small" for="blockedIpFailureCount"><?php echo Text::_('COM_LOGINGUARD_HEADING_FAILURE_COUNT'); ?></label>
                    <input type="number" name="failure_count" id="blockedIpFailureCount" class="form-control form-control-sm" min="0" value="<?php echo $editing ? (int) $editItem->failure_count : 0; ?>">
                </div>
                <div class="col-md-2 col-xl-1">
                    <label class="form-label small" for="blockedIpEnabled"><?php echo Text::_('COM_LOGINGUARD_HEADING_ENABLED'); ?></label>
                    <?php $enabled = !$editing || (int) $editItem->enabled === 1; ?>
                    <select name="enabled" id="blockedIpEnabled" class="form-select form-select-sm">
                        <option value="1"<?php echo $enabled ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_ENABLED'); ?></option>
                        <option value="0"<?php echo !$enabled ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_DISABLED'); ?></option>
                    </select>
                </div>
                <div class="col-md-5 col-xl-2">
                    <label class="form-label small" for="blockedIpReason"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></label>
                    <input type="text" name="reason" id="blockedIpReason" class="form-control form-control-sm" maxlength="50" value="<?php echo $editing ? $this->escape((string) $editItem->reason) : 'manual'; ?>">
                </div>
                <div class="col-md-5 col-xl-12 d-flex justify-content-end gap-2 pt-2">
                    <button type="submit" class="btn btn-sm btn-primary"><?php echo Text::_($editing ? 'JSAVE' : 'COM_LOGINGUARD_BLOCKEDIPS_ADD_BUTTON'); ?></button>
                    <?php if ($editing) : ?>
                        <a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>"><?php echo Text::_('JCANCEL'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
            <input type="hidden" name="id" value="<?php echo $editing ? (int) $editItem->id : 0; ?>">
            <input type="hidden" name="task" value="blockedips.save">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </details>

    <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="adminForm" id="adminForm">
        <div class="card mb-3">
            <div class="card-body py-2">
                <div class="loginguard-filter-toolbar">
                    <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="loginguardBlockedIpsList">
                <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'id', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'ip_address', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_SCOPE', 'scope', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_BLOCK_TYPE', 'block_type', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_STATUS'); ?></th>
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
                            <td colspan="12" class="text-center py-5">
                                <strong class="d-block mb-1"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_EMPTY_TITLE'); ?></strong>
                                <span class="text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_BLOCKED_IPS'); ?></span>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php [$statusKey, $statusLabel, $statusClass] = $statusForBlock($item); ?>
                            <?php [$expirationLabel, $expirationClass] = $expirationForBlock($item); ?>
                            <tr>
                                <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                                <td><?php echo (int) $item->id; ?></td>
                                <td><strong><?php echo $this->escape((string) $item->ip_address); ?></strong></td>
                                <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_SCOPE_' . strtoupper((string) $item->scope))); ?></td>
                                <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_TYPE_' . strtoupper((string) $item->block_type))); ?></td>
                                <td><span class="badge <?php echo $this->escape($statusClass); ?>" data-status="<?php echo $this->escape($statusKey); ?>"><?php echo $this->escape(Text::_($statusLabel)); ?></span></td>
                                <td><span class="badge <?php echo (int) $item->enabled === 1 ? 'bg-success' : 'bg-danger'; ?>"><?php echo $this->escape(Text::_((int) $item->enabled === 1 ? 'COM_LOGINGUARD_BLOCKEDIPS_ENABLED' : 'COM_LOGINGUARD_BLOCKEDIPS_DISABLED')); ?></span></td>
                                <td><?php echo $this->escape((string) $item->reason); ?></td>
                                <td><?php echo (int) $item->failure_count; ?></td>
                                <td>
                                    <span class="fw-semibold <?php echo $this->escape($expirationClass); ?>"><?php echo $this->escape(Text::_($expirationLabel)); ?></span>
                                    <?php if (!empty($item->blocked_until)) : ?>
                                        <span class="d-block small text-muted"><?php echo HTMLHelper::_('date', $item->blocked_until, Text::_('DATE_FORMAT_LC5')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                                <td><a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips&edit_id=' . (int) $item->id); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php echo $this->pagination->getListFooter(); ?>

        <input type="hidden" name="task" value="">
        <input type="hidden" name="boxchecked" value="0">
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
