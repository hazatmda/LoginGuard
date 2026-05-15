<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

HTMLHelper::_('behavior.core');
HTMLHelper::_('bootstrap.modal', '#loginguardBlockedIpModal');
HTMLHelper::_('searchtools.form', '#adminForm');

$listOrder = $this->escape((string) $this->state->get('list.ordering'));
$listDirn  = $this->escape((string) $this->state->get('list.direction'));
$editItem = $this->editItem;
$editing = $editItem !== null;
$blockedUntilValue = $editing && !empty($editItem->blocked_until) ? LoginGuardHelper::formatConfiguredDateTimeInput((string) $editItem->blocked_until) : '';
$nowSql = gmdate('Y-m-d H:i:s');
$nowTimestamp = strtotime($nowSql) ?: time();
$blockTelemetry = $this->blockedIpTelemetry ?? [];

$statusForBlock = static function (object $item) use ($nowSql): array {
    if ((int) $item->enabled !== 1) {
        return ['disabled', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_DISABLED', 'bg-secondary'];
    }

    $blockType = (string) $item->block_type;
    $blockedUntil = (string) $item->blocked_until;

    if ($blockType === 'permanent') {
        return ['permanent', 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_PERMANENT', 'bg-primary'];
    }

    if ($blockedUntil === '' || $blockedUntil < $nowSql) {
        return ['expired', $blockedUntil === '' ? 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY_NO_EXPIRY' : 'COM_LOGINGUARD_BLOCKEDIPS_STATUS_EXPIRED', 'bg-warning text-dark'];
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

    $expires = strtotime($blockedUntil . ' UTC');

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

$metrics = [
    ['active', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_ACTIVE', 'success', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_ACTIVE_DESC'],
    ['temporary', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_TEMPORARY', 'info', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_TEMPORARY_DESC'],
    ['permanent', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_PERMANENT', 'primary', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_PERMANENT_DESC'],
    ['expired', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_EXPIRED', 'warning', 'COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_EXPIRED_DESC'],
];

$modalTitle = $editing ? Text::_('COM_LOGINGUARD_BLOCKEDIPS_EDIT_TITLE') : Text::_('COM_LOGINGUARD_BLOCKEDIPS_ADD_TITLE');
?>
<style>
.loginguard-blockops{--lg-soft-border:rgba(0,0,0,.08);--lg-muted:#64748b}.loginguard-blockops .card{border-color:var(--lg-soft-border)}.loginguard-blockops__hero{background:linear-gradient(135deg,rgba(13,110,253,.09),rgba(13,202,240,.08));border:1px solid var(--lg-soft-border);border-radius:.85rem;padding:1rem}.loginguard-blockops__metrics{display:grid;grid-template-columns:repeat(auto-fit,minmax(12rem,1fr));gap:.75rem}.loginguard-blockops__metric{border:1px solid var(--lg-soft-border);border-radius:.85rem;background:#fff;padding:1rem;height:100%;box-shadow:0 .4rem 1.25rem rgba(15,23,42,.04)}.loginguard-blockops__metric-label{font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:var(--lg-muted);font-weight:700}.loginguard-blockops__metric-value{font-size:2rem;line-height:1;font-weight:800}.loginguard-blockops__metric-desc{font-size:.78rem;color:var(--lg-muted)}.loginguard-blockops__toolbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.75rem}.loginguard-blockops__filters{min-width:18rem;flex:1}.loginguard-blockops__primary-cell{min-width:12rem}.loginguard-blockops__reason{max-width:22rem}.loginguard-blockops__actions{min-width:8rem}.loginguard-blockops__table th{font-size:.75rem;letter-spacing:.04em;text-transform:uppercase;color:var(--lg-muted);white-space:nowrap}.loginguard-blockops__table td{padding:.9rem .75rem}.loginguard-blockops__mobile-label{display:none}.loginguard-blockops__expiry[data-block-type="permanent"]{display:none}@media (max-width:767.98px){.loginguard-blockops__hero{padding:.85rem}.loginguard-blockops__toolbar{align-items:stretch}.loginguard-blockops__toolbar .btn,.loginguard-blockops__filters{width:100%;min-width:0}.loginguard-blockops__table thead{display:none}.loginguard-blockops__table tbody tr{display:block;border:1px solid var(--lg-soft-border);border-radius:.75rem;margin-bottom:.75rem;background:#fff}.loginguard-blockops__table td{display:flex;justify-content:space-between;gap:1rem;border:0!important;padding:.55rem .75rem}.loginguard-blockops__table td:first-child{padding-top:.85rem}.loginguard-blockops__table td:last-child{padding-bottom:.85rem}.loginguard-blockops__mobile-label{display:inline;font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:var(--lg-muted);font-weight:700}.loginguard-blockops__value{text-align:right}.loginguard-blockops__reason{max-width:none}.loginguard-blockops__actions .btn{width:100%}}
</style>
<div id="j-main-container" class="j-main-container loginguard-blockops">
    <div class="loginguard-blockops__hero mb-3">
        <div class="loginguard-blockops__toolbar mb-3">
            <div>
                <h2 class="h4 mb-1"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_TITLE'); ?></h2>
                <p class="text-muted mb-0 small"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_OPERATIONAL_DESC'); ?></p>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loginguardBlockedIpModal"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_ADD_BUTTON'); ?></button>
        </div>
        <div class="loginguard-blockops__metrics" aria-label="<?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_TELEMETRY_LABEL'); ?>">
            <?php foreach ($metrics as $metric) : ?>
                <div class="loginguard-blockops__metric">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="loginguard-blockops__metric-label"><?php echo Text::_($metric[1]); ?></div>
                        <span class="badge text-bg-<?php echo $this->escape($metric[2]); ?> rounded-pill"><?php echo (int) ($blockTelemetry[$metric[0]] ?? 0); ?></span>
                    </div>
                    <div class="loginguard-blockops__metric-value text-<?php echo $this->escape($metric[2]); ?>"><?php echo (int) ($blockTelemetry[$metric[0]] ?? 0); ?></div>
                    <div class="loginguard-blockops__metric-desc mt-2"><?php echo Text::_($metric[3]); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="modal fade" id="loginguardBlockedIpModal" tabindex="-1" aria-labelledby="loginguardBlockedIpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="blockedIpForm" id="blockedIpForm">
                    <div class="modal-header">
                        <div>
                            <h2 class="modal-title h5" id="loginguardBlockedIpModalLabel"><?php echo $modalTitle; ?></h2>
                            <p class="text-muted small mb-0"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_FORM_GUIDANCE'); ?></p>
                        </div>
                        <a class="btn-close" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" aria-label="<?php echo Text::_('JCANCEL'); ?>"></a>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="blockedIpAddress"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></label>
                                <input type="text" name="ip_address" id="blockedIpAddress" class="form-control" required value="<?php echo $editing ? $this->escape((string) $editItem->ip_address) : ''; ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="blockedIpScope"><?php echo Text::_('COM_LOGINGUARD_HEADING_SCOPE'); ?></label>
                                <?php $scope = $editing ? (string) $editItem->scope : 'all'; ?>
                                <select name="scope" id="blockedIpScope" class="form-select">
                                    <option value="all"<?php echo $scope === 'all' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_ALL'); ?></option>
                                    <option value="frontend"<?php echo $scope === 'frontend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_FRONTEND'); ?></option>
                                    <option value="backend"<?php echo $scope === 'backend' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_SCOPE_BACKEND'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="blockedIpType"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_TYPE'); ?></label>
                                <?php $blockType = $editing ? (string) $editItem->block_type : 'temporary'; ?>
                                <select name="block_type" id="blockedIpType" class="form-select" data-loginguard-block-type>
                                    <option value="temporary"<?php echo $blockType === 'temporary' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_TEMPORARY'); ?></option>
                                    <option value="permanent"<?php echo $blockType === 'permanent' ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE_PERMANENT'); ?></option>
                                </select>
                            </div>
                            <div class="col-md-6 loginguard-blockops__expiry" data-loginguard-expiry>
                                <label class="form-label" for="blockedUntil"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCKED_UNTIL'); ?></label>
                                <input type="datetime-local" name="blocked_until" id="blockedUntil" class="form-control" value="<?php echo $this->escape($blockedUntilValue); ?>">
                                <div class="form-text"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_BLOCKED_UNTIL_COMPACT_HELP'); ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="blockedIpReason"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></label>
                                <input type="text" name="reason" id="blockedIpReason" class="form-control" maxlength="50" value="<?php echo $editing ? $this->escape((string) $editItem->reason) : 'manual'; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="failureCount"><?php echo Text::_('COM_LOGINGUARD_HEADING_FAILURE_COUNT'); ?></label>
                                <input type="number" name="failure_count" id="failureCount" class="form-control" min="0" value="<?php echo $editing ? (int) $editItem->failure_count : 0; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="blockedIpEnabled"><?php echo Text::_('COM_LOGINGUARD_HEADING_ENABLED'); ?></label>
                                <?php $enabled = $editing ? (int) $editItem->enabled : 1; ?>
                                <select name="enabled" id="blockedIpEnabled" class="form-select">
                                    <option value="1"<?php echo $enabled === 1 ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_ENABLED'); ?></option>
                                    <option value="0"<?php echo $enabled === 0 ? ' selected' : ''; ?>><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_DISABLED'); ?></option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="hidden" name="id" value="<?php echo $editing ? (int) $editItem->id : 0; ?>">
                        <input type="hidden" name="task" value="blockedips.save">
                        <?php echo HTMLHelper::_('form.token'); ?>
                        <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>"><?php echo Text::_('JCANCEL'); ?></a>
                        <button type="submit" class="btn btn-primary"><?php echo Text::_($editing ? 'JSAVE' : 'COM_LOGINGUARD_BLOCKEDIPS_ADD_BUTTON'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form action="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>" method="post" name="adminForm" id="adminForm">
        <div class="card mb-3">
            <div class="card-body">
                <div class="loginguard-blockops__toolbar">
                    <div class="loginguard-blockops__filters"><?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?></div>
                    <div class="btn-group" role="group" aria-label="<?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_ACTIONS_LABEL'); ?>">
                        <button type="button" class="btn btn-outline-success" onclick="Joomla.submitbutton('blockedips.enable')"><?php echo Text::_('COM_LOGINGUARD_TOOLBAR_ENABLE'); ?></button>
                        <button type="button" class="btn btn-outline-secondary" onclick="Joomla.submitbutton('blockedips.disable')"><?php echo Text::_('COM_LOGINGUARD_TOOLBAR_DISABLE'); ?></button>
                        <button type="button" class="btn btn-outline-danger" onclick="Joomla.submitbutton('blockedips.delete')"><?php echo Text::_('JTOOLBAR_DELETE'); ?></button>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle loginguard-blockops__table" id="loginguardBlockedIpsList">
                <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_IP_ADDRESS', 'ip_address', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_STATUS'); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_BLOCK_TYPE', 'block_type', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_SCOPE', 'scope', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_BLOCKED_UNTIL', 'blocked_until', $listDirn, $listOrder); ?></th>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'COM_LOGINGUARD_HEADING_DATETIME', 'created', $listDirn, $listOrder); ?></th>
                        <th scope="col" class="text-end"><?php echo Text::_('JACTION_EDIT'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($this->items)) : ?>
                        <tr><td colspan="9" class="text-center py-5"><strong class="d-block mb-1"><?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_EMPTY_TITLE'); ?></strong><span class="text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_BLOCKED_IPS'); ?></span></td></tr>
                    <?php else : ?>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php [$statusKey, $statusLabel, $statusClass] = $statusForBlock($item); ?>
                            <?php [$expirationLabel, $expirationClass] = $expirationForBlock($item); ?>
                            <tr>
                                <td class="text-center"><span class="loginguard-blockops__mobile-label"><?php echo Text::_('JGRID_HEADING_ID'); ?></span><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                                <td class="loginguard-blockops__primary-cell"><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></span><span class="loginguard-blockops__value"><strong class="d-block"><?php echo $this->escape((string) $item->ip_address); ?></strong><span class="small text-muted">#<?php echo (int) $item->id; ?> · <?php echo (int) $item->failure_count; ?> <?php echo Text::_('COM_LOGINGUARD_BLOCKEDIPS_FAILURES_SHORT'); ?></span></span></td>
                                <td><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_STATUS'); ?></span><span class="loginguard-blockops__value"><span class="badge <?php echo $this->escape($statusClass); ?>" data-status="<?php echo $this->escape($statusKey); ?>"><?php echo $this->escape(Text::_($statusLabel)); ?></span></span></td>
                                <td><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_TYPE'); ?></span><span class="loginguard-blockops__value"><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_TYPE_' . strtoupper((string) $item->block_type))); ?></span></td>
                                <td><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_SCOPE'); ?></span><span class="loginguard-blockops__value"><?php echo $this->escape(Text::_('COM_LOGINGUARD_SCOPE_' . strtoupper((string) $item->scope))); ?></span></td>
                                <td class="loginguard-blockops__reason"><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_REASON'); ?></span><span class="loginguard-blockops__value text-break"><?php echo $this->escape((string) $item->reason); ?></span></td>
                                <td><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCKED_UNTIL'); ?></span><span class="loginguard-blockops__value"><span class="fw-semibold <?php echo $this->escape($expirationClass); ?>"><?php echo $this->escape(Text::_($expirationLabel)); ?></span><?php if (!empty($item->blocked_until)) : ?><span class="d-block small text-muted"><?php echo LoginGuardHelper::formatConfiguredDateTime((string) $item->blocked_until); ?></span><?php endif; ?></span></td>
                                <td><span class="loginguard-blockops__mobile-label"><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></span><span class="loginguard-blockops__value"><?php echo LoginGuardHelper::formatConfiguredDateTime((string) $item->created); ?></span></td>
                                <td class="text-end loginguard-blockops__actions"><span class="loginguard-blockops__mobile-label"><?php echo Text::_('JACTION_EDIT'); ?></span><a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips&edit_id=' . (int) $item->id); ?>"><?php echo Text::_('JACTION_EDIT'); ?></a></td>
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
<script>
(function () {
    var typeField = document.querySelector('[data-loginguard-block-type]');
    var expiryGroup = document.querySelector('[data-loginguard-expiry]');
    var modalElement = document.getElementById('loginguardBlockedIpModal');

    function syncExpiry() {
        if (!typeField || !expiryGroup) {
            return;
        }

        expiryGroup.dataset.blockType = typeField.value;
    }

    if (typeField) {
        typeField.addEventListener('change', syncExpiry);
        syncExpiry();
    }

    <?php if ($editing) : ?>
    if (modalElement && window.bootstrap && window.bootstrap.Modal) {
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    }
    <?php endif; ?>
}());
</script>
