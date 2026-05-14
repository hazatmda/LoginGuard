<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$telemetryLabels = [
    'frontend_success' => 'COM_LOGINGUARD_DASHBOARD_FRONTEND_SUCCESS',
    'backend_success' => 'COM_LOGINGUARD_DASHBOARD_BACKEND_SUCCESS',
    'frontend_failed' => 'COM_LOGINGUARD_DASHBOARD_FRONTEND_FAILED',
    'backend_failed' => 'COM_LOGINGUARD_DASHBOARD_BACKEND_FAILED',
    'blocked_login' => 'COM_LOGINGUARD_DASHBOARD_BLOCKED_LOGIN',
];

$failureReasonLabels = [
    'PASSWORD_INCORRECT' => 'COM_LOGINGUARD_REASON_PASSWORD_INCORRECT',
    'USERNAME_NOT_FOUND' => 'COM_LOGINGUARD_REASON_USERNAME_NOT_FOUND',
    'INVALID_CREDENTIALS' => 'COM_LOGINGUARD_REASON_INVALID_CREDENTIALS',
    'ACCOUNT_BLOCKED' => 'COM_LOGINGUARD_REASON_ACCOUNT_BLOCKED',
    'ACCOUNT_DISABLED' => 'COM_LOGINGUARD_REASON_ACCOUNT_DISABLED',
    'IP_BLOCKED' => 'COM_LOGINGUARD_REASON_IP_BLOCKED',
];

?>
<form action="<?php echo Route::_('index.php?option=com_loginguard&view=dashboard'); ?>" method="post" name="adminForm" id="adminForm">
    <div id="j-main-container" class="j-main-container">
            <div class="row g-3 mb-3">
                <?php foreach ($telemetryLabels as $metric => $label) : ?>
                    <div class="col-md-6 col-xl-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h2 class="h5 card-title"><?php echo Text::_($label); ?></h2>
                                <p class="display-6 mb-0"><?php echo (int) ($this->telemetryCounts[$metric] ?? 0); ?></p>
                                <p class="text-muted mb-0"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TELEMETRY_SPLIT_DESC'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY'); ?></h2>
                                    <p class="text-muted"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_DESC'); ?></p>
                                </div>
                                <a class="btn btn-sm btn-primary" href="<?php echo Route::_('index.php?option=com_loginguard&view=attempts'); ?>"><?php echo Text::_('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION'); ?></a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY'); ?></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_USERNAME'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_STATUS'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_FAILURE_REASON'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_WHERE'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($this->recentActivity)) : ?>
                                            <tr>
                                                <td colspan="6" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                                            </tr>
                                        <?php else : ?>
                                            <?php foreach ($this->recentActivity as $item) : ?>
                                                <?php $where = (string) ($item->where_at ?: $item->client); ?>
                                                <tr>
                                                    <td><?php echo $this->escape((string) $item->ip_address); ?></td>
                                                    <td><?php echo $this->escape((string) $item->username); ?></td>
                                                    <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_STATUS_' . strtoupper((string) $item->status))); ?></td>
                                                    <td><?php echo $item->reason === '' ? '' : $this->escape(Text::_('COM_LOGINGUARD_REASON_' . strtoupper((string) $item->reason))); ?></td>
                                                    <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_WHERE_' . strtoupper($where))); ?></td>
                                                    <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_FAILURE_REASONS'); ?></h2>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($failureReasonLabels as $reason => $label) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?php echo Text::_($label); ?></span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo (int) ($this->topFailureReasons[$reason] ?? 0); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row g-3 mb-3">
                <div class="col-xl-4">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_BLOCKED_IP_TELEMETRY'); ?></h2>
                            <ul class="list-group list-group-flush">
                                <?php foreach (['active', 'temporary', 'permanent', 'expired'] as $metric) : ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                        <span><?php echo Text::_('COM_LOGINGUARD_BLOCK_METRIC_' . strtoupper($metric)); ?></span>
                                        <span class="badge bg-secondary rounded-pill"><?php echo (int) ($this->blockedIpTelemetry[$metric] ?? 0); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xl-8">
                    <div class="card h-100">
                        <div class="card-body">
                            <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_BLOCKED_IPS'); ?></h2>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover align-middle">
                                    <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_RECENT_BLOCKED_IPS'); ?></caption>
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_WHERE'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_BLOCK_TYPE'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_BLOCK_REASON'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_BLOCK_UNTIL'); ?></th>
                                            <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($this->recentBlockedIps)) : ?>
                                            <tr>
                                                <td colspan="6" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                                            </tr>
                                        <?php else : ?>
                                            <?php foreach ($this->recentBlockedIps as $item) : ?>
                                                <tr>
                                                    <td><?php echo $this->escape((string) $item->ip_address); ?></td>
                                                    <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_SCOPE_' . strtoupper((string) $item->scope))); ?></td>
                                                    <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_TYPE_' . strtoupper((string) $item->block_type))); ?></td>
                                                    <td><?php echo $this->escape(Text::_('COM_LOGINGUARD_BLOCK_REASON_' . strtoupper((string) $item->reason))); ?></td>
                                                    <td><?php echo $item->blocked_until ? HTMLHelper::_('date', $item->blocked_until, Text::_('DATE_FORMAT_LC5')) : Text::_('COM_LOGINGUARD_BLOCK_PERMANENT'); ?></td>
                                                    <td><?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC5')); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h2 class="h5 card-title"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_IPS'); ?></h2>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <caption class="visually-hidden"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_TOP_IPS'); ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?php echo Text::_('COM_LOGINGUARD_HEADING_IP_ADDRESS'); ?></th>
                                    <th scope="col"><?php echo Text::_('COM_LOGINGUARD_DASHBOARD_FAILED_LOGIN_COUNT'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($this->topFailedIps)) : ?>
                                    <tr>
                                        <td colspan="2" class="text-center"><?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?></td>
                                    </tr>
                                <?php else : ?>
                                    <?php foreach ($this->topFailedIps as $item) : ?>
                                        <tr>
                                            <td><?php echo $this->escape((string) $item->ip_address); ?></td>
                                            <td><?php echo (int) $item->total; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
</form>
