<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

HTMLHelper::_('behavior.core');
HTMLHelper::_('searchtools.form', '#adminForm');

$listOrder = $this->escape((string) $this->state->get('list.ordering'));
$listDirn  = $this->escape((string) $this->state->get('list.direction'));
$canDelete = $this->actions && $this->actions->get('loginguard.delete');
$visibleColumns = array_flip($this->visibleColumns);
$columnValue = function (object $item, string $column): string {
    return match ($column) {
        'status' => $this->escape(Text::_('COM_LOGINGUARD_STATUS_' . strtoupper((string) $item->status))),
        'reason' => (string) $item->reason === '' ? '' : $this->escape(Text::_('COM_LOGINGUARD_REASON_' . strtoupper((string) $item->reason))),
        'where_at' => $this->escape(Text::_('COM_LOGINGUARD_WHERE_' . strtoupper((string) ($item->where_at ?: $item->client)))),
        'created' => LoginGuardHelper::formatConfiguredDateTime((string) $item->created),
        'username' => $this->escape(LoginGuardHelper::formatNullableUsername($item->username ?? null)),
        'user_agent' => '<span class="small text-break">' . $this->escape((string) $item->user_agent) . '</span>',
        default => $this->escape((string) ($item->{$column} ?? '')),
    };
};
$columnCount = count($this->visibleColumns) + 1 + ($canDelete ? 1 : 0);
$columnStorageKey = 'loginguard.logininfo.columns';
?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
        <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

        <details class="card mb-3">
            <summary class="card-header fw-semibold"><?php echo Text::_('COM_LOGINGUARD_COLUMNS_TITLE'); ?></summary>
            <div class="card-body">
                <p class="text-muted"><?php echo Text::_('COM_LOGINGUARD_COLUMNS_DESC'); ?></p>
                <input type="hidden" name="visible_columns[]" value="__none">
                <div class="row g-2">
                    <?php foreach ($this->availableColumns as $column => $label) : ?>
                        <div class="col-sm-6 col-lg-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="visible_columns[]" value="<?php echo $this->escape($column); ?>" id="column-<?php echo $this->escape($column); ?>" data-loginguard-column="<?php echo $this->escape($column); ?>"<?php echo isset($visibleColumns[$column]) ? ' checked' : ''; ?>>
                                <label class="form-check-label" for="column-<?php echo $this->escape($column); ?>"><?php echo Text::_($label); ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-secondary mt-3"><?php echo Text::_('COM_LOGINGUARD_COLUMNS_APPLY'); ?></button>
            </div>
        </details>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle" id="loginguardAttemptsList">
                <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_ATTEMPTS_TITLE'); ?></caption>
                <thead>
                    <tr>
                        <?php if ($canDelete) : ?>
                            <td class="w-1 text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></td>
                        <?php endif; ?>
                        <th scope="col"><?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_FIELD_ID_LABEL', 'id', $listDirn, $listOrder); ?></th>
                        <?php foreach ($this->availableColumns as $column => $label) : ?>
                            <?php if (isset($visibleColumns[$column])) : ?>
                                <th scope="col" data-loginguard-column-cell="<?php echo $this->escape($column); ?>"><?php echo HTMLHelper::_('searchtools.sort', $label, $column, $listDirn, $listOrder); ?></th>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($this->items)) : ?>
                        <tr>
                            <td colspan="<?php echo $columnCount; ?>" class="text-center text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_ATTEMPTS'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <tr>
                                <?php if ($canDelete) : ?>
                                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, (int) $item->id); ?></td>
                                <?php endif; ?>
                                <td><?php echo (int) $item->id; ?></td>
                                <?php foreach ($this->availableColumns as $column => $label) : ?>
                                    <?php if (isset($visibleColumns[$column])) : ?>
                                        <td data-loginguard-column-cell="<?php echo $this->escape($column); ?>"><?php echo $columnValue($item, $column); ?></td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
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
    </div>
</form>
<script>
(() => {
    'use strict';

    const storageKey = <?php echo json_encode($columnStorageKey); ?>;
    const syncFlagKey = storageKey + '.syncing';
    const form = document.getElementById('adminForm');
    let localStore = null;
    let sessionStore = null;

    try {
        localStore = window.localStorage;
        sessionStore = window.sessionStorage;
    } catch (error) {
        return;
    }

    if (!form || !localStore || !sessionStore) {
        return;
    }

    const columnInputs = Array.from(form.querySelectorAll('[data-loginguard-column]'));
    const allowedColumns = columnInputs.map((input) => input.value);

    const normaliseColumns = (columns) => {
        if (!Array.isArray(columns)) {
            return null;
        }

        const seen = new Set();

        return columns.filter((column) => {
            if (!allowedColumns.includes(column) || seen.has(column)) {
                return false;
            }

            seen.add(column);

            return true;
        });
    };

    const readColumns = () => {
        try {
            return normaliseColumns(JSON.parse(localStore.getItem(storageKey) || 'null'));
        } catch (error) {
            return null;
        }
    };

    const writeColumns = (columns) => {
        try {
            localStore.setItem(storageKey, JSON.stringify(columns));
        } catch (error) {
            // Ignore private browsing or locked-down storage failures; the form still submits normally.
        }
    };

    const selectedColumns = () => columnInputs
        .filter((input) => input.checked)
        .map((input) => input.value);

    const sameColumns = (first, second) => first.length === second.length
        && first.every((column, index) => column === second[index]);

    const applyColumns = (columns) => {
        const visible = new Set(columns);

        columnInputs.forEach((input) => {
            input.checked = visible.has(input.value);
        });

        form.querySelectorAll('[data-loginguard-column-cell]').forEach((cell) => {
            cell.hidden = !visible.has(cell.getAttribute('data-loginguard-column-cell'));
        });
    };

    const persistedColumns = readColumns();
    const renderedColumns = selectedColumns();

    if (persistedColumns === null) {
        writeColumns(renderedColumns);
    } else {
        applyColumns(persistedColumns);

        if (!sameColumns(persistedColumns, renderedColumns) && sessionStore.getItem(syncFlagKey) !== '1') {
            sessionStore.setItem(syncFlagKey, '1');

            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }

            return;
        }
    }

    sessionStore.removeItem(syncFlagKey);

    columnInputs.forEach((input) => {
        input.addEventListener('change', () => {
            const columns = selectedColumns();
            writeColumns(columns);
            applyColumns(columns);
        });
    });

    form.addEventListener('submit', () => {
        writeColumns(selectedColumns());
    });
})();
</script>
