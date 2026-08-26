<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

HTMLHelper::_('behavior.core');

$operationalStatus = $this->operationalStatus ?? [];
$cleanupMetrics = $this->cleanupMetrics ?? [];
$healthStatus = $this->healthStatus ?? [];
$timeframe = (string) ($this->dashboardTimeframe ?? 'today');
$tableClass = 'table table-sm table-striped table-hover align-middle mb-0';

$timeframeOptions = [
    'today' => ['COM_LOGINGUARD_DASHBOARD_TIMEFRAME_TODAY', 'dashboard.setTodayTimeframe'],
    '24h' => ['COM_LOGINGUARD_DASHBOARD_TIMEFRAME_24H', 'dashboard.set24hTimeframe'],
    '7d' => ['COM_LOGINGUARD_DASHBOARD_TIMEFRAME_7D', 'dashboard.set7dTimeframe'],
    'all' => ['COM_LOGINGUARD_DASHBOARD_TIMEFRAME_ALL', 'dashboard.setAllTimeframe'],
];

$kpiCards = [
    ['success_login', 'COM_LOGINGUARD_DASHBOARD_SUCCESSFUL_LOGINS', 'success'],
    ['failed_login', 'COM_LOGINGUARD_DASHBOARD_FAILED_LOGINS', 'danger'],
    ['blocked_login', 'COM_LOGINGUARD_DASHBOARD_BLOCKED_LOGINS', 'warning'],
];

$breakdownCards = [
    ['frontend_success', 'COM_LOGINGUARD_DASHBOARD_FRONTEND_SUCCESS', 'success'],
    ['frontend_failed', 'COM_LOGINGUARD_DASHBOARD_FRONTEND_FAILED', 'danger'],
    ['backend_success', 'COM_LOGINGUARD_DASHBOARD_BACKEND_SUCCESS', 'success'],
    ['backend_failed', 'COM_LOGINGUARD_DASHBOARD_BACKEND_FAILED', 'danger'],
];


$healthLabels = [
    'database' => 'COM_LOGINGUARD_HEALTH_DATABASE',
    'enforcement' => 'COM_LOGINGUARD_HEALTH_ENFORCEMENT',
    'automatic_block' => 'COM_LOGINGUARD_HEALTH_AUTOMATIC_BLOCK',
    'mail' => 'COM_LOGINGUARD_HEALTH_MAIL',
    'cleanup' => 'COM_LOGINGUARD_HEALTH_CLEANUP',
];

$statusMap = [
    'active' => ['success', 'COM_LOGINGUARD_STATUS_BANNER_PROTECTION_ACTIVE', 'COM_LOGINGUARD_STATUS_BANNER_PROTECTION_ACTIVE_DESC'],
    'enforcement_disabled' => ['warning', 'COM_LOGINGUARD_STATUS_BANNER_ENFORCEMENT_DISABLED', 'COM_LOGINGUARD_STATUS_BANNER_ENFORCEMENT_DISABLED_DESC'],
    'scheduler_not_running' => ['warning', 'COM_LOGINGUARD_STATUS_BANNER_SCHEDULER_NOT_RUNNING', 'COM_LOGINGUARD_STATUS_BANNER_SCHEDULER_NOT_RUNNING_DESC'],
    'cleanup_failure' => ['danger', 'COM_LOGINGUARD_STATUS_BANNER_CLEANUP_FAILURE', 'COM_LOGINGUARD_STATUS_BANNER_CLEANUP_FAILURE_DESC'],
];
$banner = $statusMap[(string) ($operationalStatus['status'] ?? 'active')] ?? $statusMap['active'];

$failureReasonLabels = [
    'PASSWORD_INCORRECT' => 'COM_LOGINGUARD_REASON_PASSWORD_INCORRECT',
    'USERNAME_NOT_FOUND' => 'COM_LOGINGUARD_REASON_USERNAME_NOT_FOUND',
    'INVALID_CREDENTIALS' => 'COM_LOGINGUARD_REASON_INVALID_CREDENTIALS',
    'ACCOUNT_BLOCKED' => 'COM_LOGINGUARD_REASON_ACCOUNT_BLOCKED',
    'ACCOUNT_DISABLED' => 'COM_LOGINGUARD_REASON_ACCOUNT_DISABLED',
    'IP_BLOCKED' => 'COM_LOGINGUARD_REASON_IP_BLOCKED',
];
?>
<style>
.loginguard-dashboard{--lg-gap:.75rem}.loginguard-dashboard .card{box-shadow:none}.lg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(145px,1fr));gap:var(--lg-gap)}.lg-metric{border-left:.22rem solid var(--bs-secondary);text-align:center}.lg-metric--success{border-left-color:var(--bs-success)}.lg-metric--danger{border-left-color:var(--bs-danger)}.lg-metric--warning{border-left-color:var(--bs-warning)}.lg-metric--info{border-left-color:var(--bs-info)}.lg-value{font-size:1.7rem;font-weight:700;line-height:1}.lg-label{font-size:.72rem;letter-spacing:.04em}.lg-health-message{max-width:420px;word-break:break-word}.lg-time-range{flex-wrap:wrap}.lg-status{font-size:.75rem}.lg-status-healthy{background:var(--bs-success)}.lg-status-degraded{background:var(--bs-danger)}.lg-status-unknown{background:var(--bs-secondary)}
</style>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=dashboard'); ?>" method="post" name="adminForm" id="adminForm">
<div id="j-main-container" class="j-main-container loginguard-dashboard">
    <div class="d-flex flex-column flex-xl-row justify-content-between align-items-xl-center gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TITLE'); ?></h2>
            <p class="text-muted small mb-0"><?php echo Text::sprintf('COM_LOGINGUARD_DASHBOARD_TIMEFRAME_ACTIVE', Text::_($timeframeOptions[$timeframe][0] ?? $timeframeOptions['today'][0])); ?></p>
        </div>
        <div><div class="small fw-semibold mb-1"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TIMEFRAME'); ?></div><div class="btn-group btn-group-sm lg-time-range" role="group" aria-label="<?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TIMEFRAME'); ?>">
            <?php foreach ($timeframeOptions as $range => $option) : ?>
                <button type="submit" class="btn <?php echo $timeframe === $range ? 'btn-primary' : 'btn-outline-primary'; ?>" name="task" value="<?php echo $this->escape($option[1]); ?>" aria-pressed="<?php echo $timeframe === $range ? 'true' : 'false'; ?>"><?php echo Text::_($option[0]); ?></button>
            <?php endforeach; ?>
        </div></div>
    </div>

    <div class="lg-grid mb-3">
        <?php foreach ($kpiCards as $card) : ?>
            <div class="card lg-metric lg-metric--<?php echo $this->escape($card[2]); ?>"><div class="card-body py-3">
                <div class="text-muted text-uppercase lg-label mb-2"><?php echo Text::_($card[1]); ?></div>
                <div class="lg-value"><?php echo (int) ($this->telemetryCounts[$card[0]] ?? 0); ?></div>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="card mb-3"><div class="card-body py-3">
        <h2 class="h6 mb-3"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_CLIENT_BREAKDOWN'); ?></h2>
        <div class="lg-grid"><?php foreach ($breakdownCards as $card) : ?><div class="text-center"><div class="text-muted small"><?php echo Text::_($card[1]); ?></div><strong class="h5 text-<?php echo $this->escape($card[2]); ?>"><?php echo (int) ($this->telemetryCounts[$card[0]] ?? 0); ?></strong></div><?php endforeach; ?></div>
    </div></div>

    <div class="alert alert-<?php echo $this->escape($banner[0]); ?> py-2 mb-3" role="status">
        <strong><?php echo Text::_($banner[1]); ?></strong> — <?php echo Text::_($banner[2]); ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_SYSTEM_HEALTH'); ?></h2>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th><?php echo Text::_('COM_LOGINGUARD_HEALTH_COMPONENT'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEALTH_STATUS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEALTH_LAST_UPDATE'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEALTH_MESSAGE'); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($healthLabels as $key => $label) : ?>
                        <?php $health = $healthStatus[$key] ?? ['status' => 'unknown', 'updated' => '', 'message' => '']; $healthState = strtolower((string) ($health['status'] ?? 'unknown')); ?>
                        <tr>
                            <td><?php echo Text::_($label); ?></td>
                            <td><span class="badge rounded-pill lg-status lg-status-<?php echo $this->escape(in_array($healthState, ['healthy','degraded'], true) ? $healthState : 'unknown'); ?>"><?php echo $this->escape(strtoupper($healthState)); ?></span></td>
                            <td><?php echo $this->escape(LoginGuardHelper::formatConfiguredDateTime((string) ($health['updated'] ?? '')) ?: '—'); ?></td>
                            <td class="small text-muted lg-health-message"><?php echo $this->escape((string) ($health['message'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-4"><div class="card h-100"><div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_OPERATIONAL_HEALTH'); ?></h2>
            <dl class="row small mb-0">
                <dt class="col-7"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOTAL_ATTEMPTS'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($cleanupMetrics['total_attempts'] ?? 0); ?></dd>
                <dt class="col-7"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOTAL_BLOCKED_IPS'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($cleanupMetrics['total_blocked_ips'] ?? 0); ?></dd>
                <dt class="col-7"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_LAST_CLEANUP'); ?></dt><dd class="col-5 text-end"><?php echo $this->escape(LoginGuardHelper::formatConfiguredDateTime((string) ($cleanupMetrics['last_cleanup_execution'] ?? '')) ?: Text::_('COM_LOGINGUARD_DASHBOARD_LAST_CLEANUP_NEVER')); ?></dd>
                <dt class="col-7"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_DELETED'); ?></dt><dd class="col-5 text-end"><?php echo (int) ($cleanupMetrics['last_total_deleted'] ?? 0); ?></dd>
            </dl>
        </div></div></div>
        <div class="col-xl-4"><div class="card h-100"><div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_BLOCKED_IP_TELEMETRY'); ?></h2>
            <ul class="list-group list-group-flush">
                <?php foreach (['active','temporary','permanent','expired'] as $metric) : ?>
                    <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo Text::_('COM_LOGINGUARD_BLOCK_METRIC_' . strtoupper($metric)); ?></span><strong><?php echo (int) ($this->blockedIpTelemetry[$metric] ?? 0); ?></strong></li>
                <?php endforeach; ?>
            </ul>
        </div></div></div>
        <div class="col-xl-4"><div class="card h-100"><div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_QUICK_ACTIONS'); ?></h2>
            <div class="d-grid gap-2">
                <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts&filter[status]=FAILED_LOGIN'); ?>"><?php echo Text::_('COM_LOGINGUARD_QUICK_VIEW_FAILED_LOGINS'); ?></a>
                <a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=blockedips'); ?>"><?php echo Text::_('COM_LOGINGUARD_QUICK_VIEW_BLOCKED_IPS'); ?></a>
                <?php if ($this->actions->get('core.admin')) : ?><button type="submit" class="btn btn-sm btn-warning" name="task" value="dashboard.cleanup"><?php echo Text::_('COM_LOGINGUARD_QUICK_RUN_CLEANUP'); ?></button><?php endif; ?>
                <a class="btn btn-sm btn-secondary" href="<?php echo Route::_('index.php?option=com_config&view=component&component=com_loginguard'); ?>"><?php echo Text::_('COM_LOGINGUARD_QUICK_OPEN_CONFIGURATION'); ?></a>
            </div>
        </div></div></div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-6"><div class="card h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY'); ?></h2><a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>"><?php echo Text::_('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION'); ?></a></div>
            <div class="table-responsive"><table class="<?php echo $tableClass; ?>"><thead><tr><th><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_USERNAME'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_STATUS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></th></tr></thead><tbody>
                <?php if (empty($this->recentActivity)) : ?><tr><td colspan="4" class="text-center text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_RECENT_ACTIVITY'); ?></td></tr><?php else : ?>
                <?php foreach ($this->recentActivity as $item) : ?><tr><td><?php echo $this->escape((string) $item->ip_address); ?></td><td><?php echo $this->escape(LoginGuardHelper::formatNullableUsername($item->username ?? null)); ?></td><td><?php echo $this->escape(Text::_('COM_LOGINGUARD_STATUS_' . strtoupper((string) $item->status))); ?></td><td><?php echo $this->escape(LoginGuardHelper::formatConfiguredDateTime((string) $item->created)); ?></td></tr><?php endforeach; ?>
                <?php endif; ?>
            </tbody></table></div>
        </div></div></div>
        <div class="col-xl-6"><div class="card h-100"><div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_FAILURE_REASONS'); ?></h2>
            <ul class="list-group list-group-flush">
                <?php foreach ($failureReasonLabels as $reason => $label) : ?><li class="list-group-item d-flex justify-content-between px-0"><span><?php echo Text::_($label); ?></span><strong><?php echo (int) ($this->topFailureReasons[$reason] ?? 0); ?></strong></li><?php endforeach; ?>
            </ul>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-12"><div class="card h-100"><div class="card-body">
            <h2 class="h5"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_IPS'); ?></h2>
            <div class="table-responsive"><table class="<?php echo $tableClass; ?>"><thead><tr><th><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th><th class="text-end"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_FAILED_LOGIN_COUNT'); ?></th></tr></thead><tbody>
                <?php if (empty($this->topFailedIps)) : ?><tr><td colspan="2" class="text-center text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_TOP_IPS'); ?></td></tr><?php else : ?>
                <?php foreach ($this->topFailedIps as $item) : ?><tr><td><?php echo $this->escape((string) $item->ip_address); ?></td><td class="text-end"><?php echo (int) $item->total; ?></td></tr><?php endforeach; ?>
                <?php endif; ?>
            </tbody></table></div>
        </div></div></div>
    </div>

    <input type="hidden" name="option" value="com_loginguard">
    <?php echo HTMLHelper::_('form.token'); ?>
</div>
</form>
