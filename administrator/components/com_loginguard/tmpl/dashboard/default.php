<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.core');

$telemetryLabels = [
    'frontend_success' => 'COM_LOGINGUARD_DASHBOARD_FRONTEND_SUCCESS',
    'backend_success' => 'COM_LOGINGUARD_DASHBOARD_BACKEND_SUCCESS',
    'frontend_failed' => 'COM_LOGINGUARD_DASHBOARD_FRONTEND_FAILED',
    'backend_failed' => 'COM_LOGINGUARD_DASHBOARD_BACKEND_FAILED',
    'blocked_login' => 'COM_LOGINGUARD_DASHBOARD_BLOCKED_LOGIN',
];

$telemetryTone = [
    'frontend_success' => 'success',
    'backend_success' => 'success',
    'frontend_failed' => 'danger',
    'backend_failed' => 'danger',
    'blocked_login' => 'warning',
];

$statusMap = [
    'active' => ['success', 'COM_LOGINGUARD_STATUS_BANNER_PROTECTION_ACTIVE', 'COM_LOGINGUARD_STATUS_BANNER_PROTECTION_ACTIVE_DESC'],
    'enforcement_disabled' => ['warning', 'COM_LOGINGUARD_STATUS_BANNER_ENFORCEMENT_DISABLED', 'COM_LOGINGUARD_STATUS_BANNER_ENFORCEMENT_DISABLED_DESC'],
    'scheduler_not_running' => ['warning', 'COM_LOGINGUARD_STATUS_BANNER_SCHEDULER_NOT_RUNNING', 'COM_LOGINGUARD_STATUS_BANNER_SCHEDULER_NOT_RUNNING_DESC'],
    'cleanup_failure' => ['danger', 'COM_LOGINGUARD_STATUS_BANNER_CLEANUP_FAILURE', 'COM_LOGINGUARD_STATUS_BANNER_CLEANUP_FAILURE_DESC'],
    'geoip_degraded' => ['warning', 'COM_LOGINGUARD_STATUS_BANNER_GEOIP_DEGRADED', 'COM_LOGINGUARD_STATUS_BANNER_GEOIP_DEGRADED_DESC'],
];

$operationalStatus = $this->operationalStatus ?? [];
$banner = $statusMap[(string) ($operationalStatus['status'] ?? 'active')] ?? $statusMap['active'];
$cleanupMetrics = $this->cleanupMetrics ?? [];
$compactMode = (bool) ($this->compactDashboardMode ?? true);
$densityClass = $compactMode ? 'loginguard-dashboard--compact' : 'loginguard-dashboard--comfortable';
$cardPaddingClass = $compactMode ? 'p-2' : 'p-3';
$tableClass = $compactMode ? 'table table-sm table-striped table-hover align-middle mb-0' : 'table table-striped table-hover align-middle mb-0';
$lastCleanup = (string) ($cleanupMetrics['last_cleanup_execution'] ?? $operationalStatus['last_cleanup_execution'] ?? '');
$nextCleanupWindow = (string) ($operationalStatus['next_cleanup_window'] ?? '');
$cleanupEnabled = (int) ($cleanupMetrics['automatic_cleanup_enabled'] ?? 0) === 1;
$cleanupStatus = $cleanupEnabled
    ? Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_ENABLED')
    : Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_DISABLED');
$retentionPolicy = Text::sprintf(
    'COM_LOGINGUARD_DASHBOARD_RETENTION_POLICY_VALUE',
    (int) ($cleanupMetrics['login_retention_days'] ?? 90),
    (int) ($cleanupMetrics['blocked_ip_retention_days'] ?? 30),
    (int) ($cleanupMetrics['cleanup_batch_size'] ?? 500)
);
$quickActions = [
    ['COM_LOGINGUARD_QUICK_VIEW_FAILED_LOGINS', 'index.php?option=com_loginguard&view=attempts&filter[status]=FAILED_LOGIN', 'primary'],
    ['COM_LOGINGUARD_QUICK_VIEW_BLOCKED_IPS', 'index.php?option=com_loginguard&view=blockedips', 'primary'],
    ['COM_LOGINGUARD_QUICK_RUN_CLEANUP', 'dashboard.cleanup', 'warning'],
    ['COM_LOGINGUARD_QUICK_OPEN_CONFIGURATION', 'index.php?option=com_config&view=component&component=com_loginguard', 'secondary'],
];
$failureReasonLabels = [
    'PASSWORD_INCORRECT' => 'COM_LOGINGUARD_REASON_PASSWORD_INCORRECT',
    'USERNAME_NOT_FOUND' => 'COM_LOGINGUARD_REASON_USERNAME_NOT_FOUND',
    'INVALID_CREDENTIALS' => 'COM_LOGINGUARD_REASON_INVALID_CREDENTIALS',
    'ACCOUNT_BLOCKED' => 'COM_LOGINGUARD_REASON_ACCOUNT_BLOCKED',
    'ACCOUNT_DISABLED' => 'COM_LOGINGUARD_REASON_ACCOUNT_DISABLED',
    'IP_BLOCKED' => 'COM_LOGINGUARD_REASON_IP_BLOCKED',
];
$healthChips = [
    ['COM_LOGINGUARD_DASHBOARD_CLEANUP_CHIP', $cleanupEnabled ? 'COM_LOGINGUARD_DASHBOARD_CHIP_ENABLED' : 'COM_LOGINGUARD_DASHBOARD_CHIP_DISABLED', $cleanupEnabled ? 'success' : 'warning'],
    ['COM_LOGINGUARD_DASHBOARD_RETENTION_CHIP', (string) ((int) ($cleanupMetrics['login_retention_days'] ?? 90)) . 'd', 'info'],
    ['COM_LOGINGUARD_DASHBOARD_BLOCKED_IPS_CHIP', (string) ((int) ($cleanupMetrics['blocked_ip_retention_days'] ?? 30)) . 'd', 'info'],
    ['COM_LOGINGUARD_DASHBOARD_BATCH_CHIP', (string) ((int) ($cleanupMetrics['cleanup_batch_size'] ?? 500)), 'secondary'],
];
$statusChips = [
    ['COM_LOGINGUARD_DASHBOARD_SCHEDULER_STATUS', (int) ($operationalStatus['scheduler_enabled'] ?? 0) === 1 ? 'COM_LOGINGUARD_DASHBOARD_SCHEDULER_RUNNING' : 'COM_LOGINGUARD_DASHBOARD_SCHEDULER_NOT_RUNNING', (int) ($operationalStatus['scheduler_enabled'] ?? 0) === 1 ? 'success' : 'warning'],
    ['COM_LOGINGUARD_DASHBOARD_GEOIP_STATUS', (int) ($operationalStatus['geoip_enabled'] ?? 0) === 1 ? ((int) ($operationalStatus['geoip_configured'] ?? 0) === 1 ? 'COM_LOGINGUARD_DASHBOARD_GEOIP_READY' : 'COM_LOGINGUARD_DASHBOARD_GEOIP_DEGRADED') : 'COM_LOGINGUARD_DASHBOARD_GEOIP_DISABLED', (int) ($operationalStatus['geoip_enabled'] ?? 0) === 1 && (int) ($operationalStatus['geoip_configured'] ?? 0) === 0 ? 'warning' : 'success'],
    ['COM_LOGINGUARD_DASHBOARD_CLEANUP_SCHEDULER', $cleanupEnabled ? 'COM_LOGINGUARD_DASHBOARD_CHIP_ENABLED' : 'COM_LOGINGUARD_DASHBOARD_CHIP_DISABLED', $cleanupEnabled ? 'success' : 'warning'],
    ['COM_LOGINGUARD_DASHBOARD_ENFORCEMENT_STATUS', (int) ($operationalStatus['enforcement_enabled'] ?? 0) === 1 ? 'JENABLED' : 'JDISABLED', (int) ($operationalStatus['enforcement_enabled'] ?? 0) === 1 ? 'success' : 'danger'],
];
$nowSql = date('Y-m-d H:i:s');
$blockedIpStatus = static function (object $item) use ($nowSql): string {
    if ((int) $item->enabled !== 1) {
        return Text::_('COM_LOGINGUARD_BLOCKEDIPS_STATUS_DISABLED');
    }

    if ((string) $item->block_type === 'permanent') {
        return Text::_('COM_LOGINGUARD_BLOCKEDIPS_STATUS_PERMANENT');
    }

    if ((string) $item->blocked_until === '') {
        return Text::_('COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY_NO_EXPIRY');
    }

    return (string) $item->blocked_until < $nowSql
        ? Text::_('COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY_EXPIRED')
        : Text::_('COM_LOGINGUARD_BLOCKEDIPS_STATUS_TEMPORARY_ACTIVE');
};
?>
<style>
.loginguard-dashboard .card-body{padding:1rem}.loginguard-dashboard--compact .card-body{padding:.75rem}.loginguard-dashboard--compact .card-title{margin-bottom:.5rem}.loginguard-metric{border-left:.25rem solid var(--bs-info);min-height:5rem}.loginguard-metric--success{border-left-color:var(--bs-success)}.loginguard-metric--warning{border-left-color:var(--bs-warning)}.loginguard-metric--danger{border-left-color:var(--bs-danger)}.loginguard-metric__label{font-size:.72rem;letter-spacing:.055em}.loginguard-metric__value{font-size:1.9rem;line-height:1;font-weight:700}.loginguard-dashboard--compact .loginguard-metric__value{font-size:1.55rem}.loginguard-chip{font-size:.78rem}.loginguard-dashboard--compact .list-group-item{padding-top:.35rem;padding-bottom:.35rem}.loginguard-action-grid{grid-template-columns:repeat(auto-fit,minmax(9rem,1fr))}@media (min-width:1200px){.loginguard-telemetry-strip{grid-template-columns:repeat(6,minmax(0,1fr))}}
</style>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=dashboard'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container loginguard-dashboard <?php echo $densityClass; ?>">
        <div class="alert alert-<?php echo $this->escape($banner[0]); ?> d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 py-2" role="status">
            <div>
                <h2 class="h5 alert-heading mb-1"><?php echo Text::_($banner[1]); ?></h2>
                <p class="mb-0 small"><?php echo Text::_($banner[2]); ?></p>
            </div>
            <div class="d-flex flex-wrap justify-content-lg-end gap-1">
                <?php foreach ($statusChips as $chip) : ?>
                    <span class="badge loginguard-chip bg-<?php echo $this->escape($chip[2]); ?><?php echo $chip[2] === 'warning' ? ' text-dark' : ''; ?>">
                        <?php echo Text::_($chip[0]); ?>: <?php echo Text::_($chip[1]); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-2">
            <div>
                <h2 class="h4 mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_OPERATIONAL_OVERVIEW'); ?></h2>
                <p class="text-muted small mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_DENSITY_DESC'); ?></p>
            </div>
            <div class="btn-group btn-group-sm" role="group" aria-label="<?php echo Text::_('COM_LOGINGUARD_DASHBOARD_COMPACT_MODE'); ?>">
                <button type="submit" class="btn <?php echo $compactMode ? 'btn-primary' : 'btn-outline-primary'; ?>" name="task" value="dashboard.setCompactDensity" formaction="<?php echo Route::_('index.php?option=com_loginguard&view=dashboard'); ?>">
                    <?php echo Text::_('COM_LOGINGUARD_DASHBOARD_COMPACT_MODE'); ?>
                </button>
                <button type="submit" class="btn <?php echo $compactMode ? 'btn-outline-secondary' : 'btn-secondary'; ?>" name="task" value="dashboard.setComfortableDensity" formaction="<?php echo Route::_('index.php?option=com_loginguard&view=dashboard'); ?>">
                    <?php echo Text::_('COM_LOGINGUARD_DASHBOARD_COMFORTABLE_MODE'); ?>
                </button>
            </div>
        </div>

        <div class="d-grid gap-2 loginguard-telemetry-strip mb-3">
            <div class="card loginguard-metric loginguard-metric--info"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOTAL_ATTEMPTS'); ?></div><div class="loginguard-metric__value"><?php echo (int) ($cleanupMetrics['total_attempts'] ?? 0); ?></div></div></div>
            <div class="card loginguard-metric loginguard-metric--danger"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOTAL_BLOCKED_IPS'); ?></div><div class="loginguard-metric__value"><?php echo (int) ($cleanupMetrics['total_blocked_ips'] ?? 0); ?></div></div></div>
            <div class="card loginguard-metric loginguard-metric--warning"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_ACTIVE_BLOCKED_IPS'); ?></div><div class="loginguard-metric__value"><?php echo (int) ($this->blockedIpTelemetry['active'] ?? 0); ?></div></div></div>
            <div class="card loginguard-metric loginguard-metric--<?php echo $cleanupEnabled ? 'success' : 'warning'; ?>"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_CHIP'); ?></div><div class="fw-semibold"><?php echo $this->escape($cleanupStatus); ?></div><div class="text-muted small"><?php echo $lastCleanup !== '' ? HTMLHelper::_('date', $lastCleanup, Text::_('DATE_FORMAT_LC5')) : Text::_('COM_LOGINGUARD_DASHBOARD_LAST_CLEANUP_NEVER'); ?></div></div></div>
            <div class="card loginguard-metric loginguard-metric--<?php echo (int) ($operationalStatus['geoip_enabled'] ?? 0) === 1 && (int) ($operationalStatus['geoip_configured'] ?? 0) === 0 ? 'warning' : 'success'; ?>"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_GEOIP_STATUS'); ?></div><div class="fw-semibold"><?php echo Text::_($statusChips[1][1]); ?></div><div class="text-muted small"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_LOCATION_TELEMETRY'); ?></div></div></div>
            <div class="card loginguard-metric loginguard-metric--<?php echo (int) ($operationalStatus['scheduler_enabled'] ?? 0) === 1 ? 'success' : 'warning'; ?>"><div class="card-body <?php echo $cardPaddingClass; ?>"><div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_SCHEDULER_STATUS'); ?></div><div class="fw-semibold"><?php echo Text::_($statusChips[0][1]); ?></div><div class="text-muted small"><?php echo $nextCleanupWindow !== '' ? $this->escape($nextCleanupWindow) : Text::_('COM_LOGINGUARD_DASHBOARD_NEXT_CLEANUP_UNAVAILABLE_SHORT'); ?></div></div></div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-xl-8">
                <div class="card h-100"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="h5 card-title mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_FAILED_LOGIN_TRENDS'); ?></h2><span class="badge bg-danger"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_PRIORITY_VIEW'); ?></span></div>
                    <?php if (empty($this->failedLoginTrends)) : ?>
                        <p class="text-muted mb-0"><?php echo Text::_('COM_LOGINGUARD_EMPTY_FAILED_TRENDS'); ?></p>
                    <?php else : ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($this->failedLoginTrends as $item) : ?>
                                <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo $this->escape((string) $item->day); ?></span><span class="badge bg-danger rounded-pill"><?php echo (int) $item->total; ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_BACKEND_FRONTEND_ATTACKS'); ?></h2>
                    <ul class="list-group list-group-flush">
                        <?php foreach (['backend', 'frontend', 'other'] as $origin) : ?>
                            <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo Text::_('COM_LOGINGUARD_ATTACK_ORIGIN_' . strtoupper($origin)); ?></span><span class="badge bg-<?php echo $origin === 'other' ? 'secondary' : 'danger'; ?> rounded-pill"><?php echo (int) ($this->attackOriginSummary[$origin] ?? 0); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div></div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_QUICK_ACTIONS'); ?></h2>
                    <h3 class="h6 text-muted text-uppercase mb-2"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_OPERATIONAL_STATUS'); ?></h3>
                    <div class="d-flex flex-wrap gap-1 mb-3">
                        <?php foreach ($statusChips as $chip) : ?>
                            <span class="badge loginguard-chip bg-<?php echo $this->escape($chip[2]); ?><?php echo $chip[2] === 'warning' ? ' text-dark' : ''; ?>"><?php echo Text::_($chip[0]); ?>: <?php echo Text::_($chip[1]); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <h3 class="h6 text-muted text-uppercase mb-2"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_COMPACT_ACTIONS'); ?></h3>
                    <div class="d-grid gap-2 loginguard-action-grid">
                        <a class="btn btn-sm btn-primary" href="<?php echo Route::_($quickActions[0][1]); ?>"><?php echo Text::_($quickActions[0][0]); ?></a>
                        <a class="btn btn-sm btn-primary" href="<?php echo Route::_($quickActions[1][1]); ?>"><?php echo Text::_($quickActions[1][0]); ?></a>
                        <?php if ($this->actions->get('core.admin')) : ?><button type="submit" class="btn btn-sm btn-warning" name="task" value="<?php echo $this->escape($quickActions[2][1]); ?>"><?php echo Text::_($quickActions[2][0]); ?></button><?php endif; ?>
                        <a class="btn btn-sm btn-secondary" href="<?php echo Route::_($quickActions[3][1]); ?>"><?php echo Text::_($quickActions[3][0]); ?></a>
                        <?php if ($this->actions->get('loginguard.export')) : ?><button type="submit" class="btn btn-sm btn-outline-secondary" name="task" value="attempts.export"><?php echo Text::_('COM_LOGINGUARD_QUICK_EXPORT_LOGS'); ?></button><?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_scheduler'); ?>"><?php echo Text::_('COM_LOGINGUARD_QUICK_OPEN_SCHEDULER'); ?></a>
                    </div>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_OPERATIONAL_HEALTH'); ?></h2>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <?php foreach ($healthChips as $chip) : ?>
                            <span class="badge loginguard-chip bg-<?php echo $this->escape($chip[2]); ?><?php echo $chip[2] === 'warning' || $chip[2] === 'info' ? ' text-dark' : ''; ?>"><?php echo Text::_($chip[0]); ?>: <?php echo strpos($chip[1], 'COM_') === 0 || $chip[1] === 'JENABLED' || $chip[1] === 'JDISABLED' ? Text::_($chip[1]) : $this->escape($chip[1]); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="text-muted small mb-0"><?php echo $this->escape($retentionPolicy); ?></p>
                    <dl class="row small mb-0 mt-2">
                        <dt class="col-6"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_DELETED'); ?></dt><dd class="col-6 text-end"><?php echo (int) ($cleanupMetrics['last_total_deleted'] ?? 0); ?></dd>
                        <dt class="col-6"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_CLEANUP_BATCHES'); ?></dt><dd class="col-6 text-end"><?php echo (int) ($cleanupMetrics['last_batches'] ?? 0); ?></dd>
                    </dl>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_BLOCKED_IP_TELEMETRY'); ?></h2>
                    <ul class="list-group list-group-flush">
                        <?php foreach (['active' => 'warning', 'temporary' => 'info', 'permanent' => 'danger', 'expired' => 'secondary'] as $metric => $tone) : ?>
                            <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo Text::_('COM_LOGINGUARD_BLOCK_METRIC_' . strtoupper($metric)); ?></span><span class="badge bg-<?php echo $this->escape($tone); ?><?php echo $tone === 'warning' || $tone === 'info' ? ' text-dark' : ''; ?> rounded-pill"><?php echo (int) ($this->blockedIpTelemetry[$metric] ?? 0); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div></div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_COUNTRIES'); ?></h2>
                    <?php if (empty($this->topCountries)) : ?><p class="text-muted mb-0"><?php echo Text::_('COM_LOGINGUARD_EMPTY_TOP_COUNTRIES'); ?></p><?php else : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($this->topCountries as $item) : ?>
                            <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo $this->escape((string) $item->country); ?></span><span class="badge bg-primary rounded-pill"><?php echo (int) $item->total; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_IPS'); ?></h2>
                    <?php if (empty($this->topFailedIps)) : ?><p class="text-muted mb-0"><?php echo Text::_('COM_LOGINGUARD_EMPTY_TOP_IPS'); ?></p><?php else : ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($this->topFailedIps as $item) : ?>
                            <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo $this->escape((string) $item->ip_address); ?></span><span class="badge bg-danger rounded-pill"><?php echo (int) $item->total; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div></div>
            </div>
            <div class="col-xl-4">
                <div class="card h-100"><div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_FAILURE_REASONS'); ?></h2>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($failureReasonLabels as $reason => $label) : ?>
                            <li class="list-group-item d-flex justify-content-between px-0"><span><?php echo Text::_($label); ?></span><span class="badge bg-secondary rounded-pill"><?php echo (int) ($this->topFailureReasons[$reason] ?? 0); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div></div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <?php foreach ($telemetryLabels as $metric => $label) : ?>
                <div class="col-sm-6 col-xl">
                    <div class="card h-100 loginguard-metric loginguard-metric--<?php echo $this->escape($telemetryTone[$metric] ?? 'info'); ?>"><div class="card-body <?php echo $cardPaddingClass; ?>">
                        <div class="text-muted text-uppercase loginguard-metric__label"><?php echo Text::_($label); ?></div>
                        <div class="loginguard-metric__value"><?php echo (int) ($this->telemetryCounts[$metric] ?? 0); ?></div>
                    </div></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-2">
            <div class="col-xl-6"><div class="card h-100"><div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2"><h2 class="h5 card-title mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY'); ?></h2><a class="btn btn-sm btn-outline-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>"><?php echo Text::_('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION'); ?></a></div>
                <div class="table-responsive"><table class="<?php echo $tableClass; ?>"><caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY'); ?></caption><thead><tr><th><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_USERNAME'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_STATUS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_WHERE'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></th></tr></thead><tbody>
                    <?php if (empty($this->recentActivity)) : ?><tr><td colspan="5" class="text-center text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_RECENT_ACTIVITY'); ?></td></tr><?php else : ?>
                        <?php foreach ($this->recentActivity as $item) : ?><?php $where = (string) ($item->where_at ?: $item->client); ?><tr><td><?php echo $this->escape((string) $item->ip_address); ?></td><td><?php echo $this->escape((string) $item->username); ?></td><td><?php echo $this->escape(Text::_('COM_LOGINGUARD_STATUS_' . strtoupper((string) $item->status))); ?></td><td><?php echo $this->escape(Text::_('COM_LOGINGUARD_WHERE_' . strtoupper($where))); ?></td><td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td></tr><?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
            </div></div></div>
            <div class="col-xl-6"><div class="card h-100"><div class="card-body">
                <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_BLOCKED_IPS'); ?></h2>
                <div class="table-responsive"><table class="<?php echo $tableClass; ?>"><caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_BLOCKED_IPS'); ?></caption><thead><tr><th><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCK_STATUS'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_BLOCKED_UNTIL'); ?></th><th><?php echo Text::_('COM_LOGINGUARD_HEADING_FAILURE_COUNT'); ?></th></tr></thead><tbody>
                    <?php if (empty($this->recentBlockedIps)) : ?><tr><td colspan="4" class="text-center text-muted"><?php echo Text::_('COM_LOGINGUARD_EMPTY_BLOCKED_IPS'); ?></td></tr><?php else : ?>
                        <?php foreach ($this->recentBlockedIps as $item) : ?><tr><td><?php echo $this->escape((string) $item->ip_address); ?></td><td><span class="badge bg-secondary"><?php echo $this->escape($blockedIpStatus($item)); ?></span></td><td><?php echo empty($item->blocked_until) ? Text::_((string) $item->block_type === 'permanent' ? 'COM_LOGINGUARD_BLOCKEDIPS_PERMANENT' : 'COM_LOGINGUARD_BLOCKEDIPS_TEMPORARY_NO_EXPIRY') : HTMLHelper::_('date', $item->blocked_until, Text::_('DATE_FORMAT_LC5')); ?></td><td><?php echo (int) $item->failure_count; ?></td></tr><?php endforeach; ?>
                    <?php endif; ?>
                </tbody></table></div>
            </div></div></div>
        </div>

        <?php echo HTMLHelper::_('form.token'); ?>
    </div>
</form>
