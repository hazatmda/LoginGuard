<?php

namespace Joomla\Plugin\System\LoginGuardMfa\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\User\LoginGuard\Service\IpResolver;
use LoginGuard\Component\LoginGuard\Administrator\Service\AuditAlertService;
use Throwable;

final class LoginGuardMfa extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    private const MAX_USER_AGENT = 2048;
    private const MAX_TEXT = 255;
    private const ATTEMPT_SESSION_KEY = 'plg_system_loginguardmfa.pending_attempt.';

    public static function getSubscribedEvents(): array
    {
        return [
            'onComUsersCaptiveShowCaptive' => 'onCaptiveShown',
            'onComUsersCaptiveValidateFailed' => 'onMfaFailed',
            'onComUsersCaptiveValidateTryLimitReached' => 'onMfaTryLimitReached',
            'onComUsersCaptiveValidateInvalidMethod' => 'onMfaInvalidMethod',
            'onComUsersCaptiveValidateSuccess' => 'onMfaSuccess',
        ];
    }

    public function onCaptiveShown(Event $event): void
    {
        try {
            if (!$this->isMfaAuditingEnabled()) {
                return;
            }

            $user = $this->getApplication()->getIdentity();
            if (!$user || $user->guest || (int) $user->id <= 0) {
                return;
            }

            $context = $this->buildContext();
            $method = $this->getMfaMethod((int) $user->id);
            $this->markPrimarySuccessPending((int) $user->id, $method);
            $this->recordHealth('mfa', 'healthy', 'MFA captive auditing is operational.');
        } catch (Throwable $exception) {
            $this->recordFailure('mfa', $exception);
        }
    }

    public function onMfaFailed(Event $event): void
    {
        $this->recordMfaEvent('MFA_FAILED', 'MFA_INVALID_CODE', true);
    }

    public function onMfaTryLimitReached(Event $event): void
    {
        $this->recordMfaEvent('MFA_TRY_LIMIT', 'MFA_TRY_LIMIT_REACHED', true);
    }

    public function onMfaInvalidMethod(Event $event): void
    {
        $this->recordMfaEvent('MFA_FAILED', 'MFA_INVALID_METHOD', false);
    }

    public function onMfaSuccess(Event $event): void
    {
        try {
            if (!$this->isMfaAuditingEnabled()) {
                // Auditing may be disabled while a captive flow which LoginGuard
                // already owns is in progress. Complete only that exact row and
                // deliver the general success notification deferred by the user
                // plugin; do not create an MFA row or run MFA policy/alerts.
                $user = $this->getApplication()->getIdentity();
                if ($user && !$user->guest && (int) $user->id > 0) {
                    $telemetry = $this->loadPendingAttemptTelemetry((int) $user->id);
                    if ($this->finalisePendingLogin((int) $user->id, '')) {
                        $this->sendFinalSuccessAlert($user, $this->buildContext(), '', $telemetry);
                    }
                }
                return;
            }

            $user = $this->getApplication()->getIdentity();
            if (!$user || $user->guest || (int) $user->id <= 0) {
                return;
            }

            $context = $this->buildContext();
            $method = $this->getMfaMethod((int) $user->id);
            // Capture the session-owned primary attempt before finalisation clears
            // its id. Never infer ownership from another recent user/IP row.
            $telemetry = $this->loadPendingAttemptTelemetry((int) $user->id);
            $this->insertAttempt($user, $context, 'MFA_SUCCESS', 'MFA_COMPLETED', $method);
            if ($this->finalisePendingLogin((int) $user->id, $method)) {
                $this->sendFinalSuccessAlert($user, $context, $method, $telemetry);
            }
            $this->recordHealth('mfa', 'healthy', 'Last MFA validation completed successfully.');
        } catch (Throwable $exception) {
            $this->recordFailure('mfa', $exception);
        }
    }

    private function recordMfaEvent(string $status, string $reason, bool $countForBlocking): void
    {
        try {
            if (!$this->isMfaAuditingEnabled()) {
                return;
            }

            $user = $this->getApplication()->getIdentity();
            if (!$user || $user->guest || (int) $user->id <= 0) {
                return;
            }

            $context = $this->buildContext();
            $method = $this->getMfaMethod((int) $user->id);
            $telemetry = $this->loadPendingAttemptTelemetry((int) $user->id);
            $this->insertAttempt($user, $context, $status, $reason, $method);

            if ($countForBlocking) {
                $this->maybeAutoBlockMfa($context['ip_address'], $context['where_at']);
            }

            $this->sendMfaFailureAlert($user, $context, $status, $reason, $method, $telemetry);
            $this->recordHealth('mfa', 'healthy', 'MFA audit event recorded.');
        } catch (Throwable $exception) {
            $this->recordFailure('mfa', $exception);
        }
    }

    private function isMfaAuditingEnabled(): bool
    {
        // This component switch controls only LoginGuard's observers. Joomla's
        // captive MFA flow is never modified or short-circuited by this plugin.
        return (bool) ComponentHelper::getParams('com_loginguard')->get('mfa_auditing_enabled', 1);
    }

    /** @return array{ip_address:string,user_agent:string,browser:string,operating_system:string,where_at:string} */
    private function buildContext(): array
    {
        $server = $_SERVER;
        $params = ComponentHelper::getParams('com_loginguard');
        $ip = IpResolver::resolve($server, (string) $params->get('trusted_proxy_ips', ''), (string) $params->get('forwarded_ip_header', 'none'));

        $userAgent = $this->truncate((string) ($server['HTTP_USER_AGENT'] ?? 'unknown'), self::MAX_USER_AGENT);
        $app = $this->getApplication();
        $where = $app->isClient('administrator') ? 'backend' : ($app->isClient('api') ? 'api' : 'frontend');

        return [
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'where_at' => $where,
        ];
    }

    private function getMfaMethod(int $userId): string
    {
        $recordId = $this->getApplication()->getInput()->getInt('record_id', 0);
        if ($recordId <= 0) {
            return '';
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('method'))
            ->from($db->quoteName('#__user_mfa'))
            ->where($db->quoteName('id') . ' = ' . (string) $recordId)
            ->where($db->quoteName('user_id') . ' = ' . (string) $userId);
        $db->setQuery($query, 0, 1);

        return $this->truncate((string) $db->loadResult(), 100);
    }

    private function markPrimarySuccessPending(int $userId, string $method): void
    {
        $session = $this->getApplication()->getSession();
        $sessionKey = self::ATTEMPT_SESSION_KEY . $userId;
        $id = (int) $session->get($sessionKey, 0);
        if ($id <= 0) {
            return;
        }

        $db = $this->getDatabase();
        $update = $db->getQuery(true)
            ->update($db->quoteName('#__loginguard_attempts'))
            ->set($db->quoteName('status') . ' = ' . $db->quote('MFA_PENDING'))
            ->set($db->quoteName('attempt_type') . ' = ' . $db->quote('mfa'))
            ->set($db->quoteName('mfa_method') . ' = ' . $db->quote($method))
            ->set($db->quoteName('reason') . ' = ' . $db->quote('MFA_REQUIRED'))
            ->where($db->quoteName('id') . ' = ' . (string) $id)
            ->where($db->quoteName('user_id') . ' = ' . (string) $userId)
            ->where($db->quoteName('status') . ' = ' . $db->quote('SUCCESS_LOGIN'));
        $db->setQuery($update)->execute();
    }

    private function finalisePendingLogin(int $userId, string $method): bool
    {
        $session = $this->getApplication()->getSession();
        $sessionKey = self::ATTEMPT_SESSION_KEY . $userId;
        $pendingAttemptId = (int) $session->get($sessionKey, 0);
        if ($pendingAttemptId <= 0) {
            return false;
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('user_id') . ' = ' . (string) $userId)
            ->where($db->quoteName('status') . ' = ' . $db->quote('MFA_PENDING'))
            ->where($db->quoteName('id') . ' = ' . (string) $pendingAttemptId);
        $db->setQuery($query, 0, 1);
        $id = (int) $db->loadResult();

        if ($id > 0) {
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__loginguard_attempts'))
                ->set($db->quoteName('status') . ' = ' . $db->quote('SUCCESS_LOGIN'))
                ->set($db->quoteName('attempt_type') . ' = ' . $db->quote('login'))
                ->set($db->quoteName('reason') . ' = ' . $db->quote('MFA_COMPLETED'))
                ->where($db->quoteName('id') . ' = ' . (string) $id);
            if ($method !== '') {
                $update->set($db->quoteName('mfa_method') . ' = ' . $db->quote($method));
            }
            $db->setQuery($update)->execute();
            $session->clear($sessionKey);
            return true;
        }

        $session->clear($sessionKey);
        return false;
    }

    private function insertAttempt($user, array $context, string $status, string $reason, string $method, string $attemptType = 'mfa'): void
    {
        $db = $this->getDatabase();
        $username = (string) ($user->username ?? '');
        $columns = [
            'user_id', 'name', 'username', 'email', 'ip_address', 'status', 'browser', 'operating_system',
            'country', 'country_code', 'region', 'city', 'isp', 'asn', 'where_at', 'user_agent',
            'attempt_type', 'mfa_method', 'client', 'reason', 'created',
        ];
        $values = [
            (string) (int) ($user->id ?? 0),
            $db->quote($this->truncate((string) ($user->name ?? ''), self::MAX_TEXT)),
            $username === '' ? 'NULL' : $db->quote($this->truncate($username, self::MAX_TEXT)),
            $db->quote($this->truncate((string) ($user->email ?? ''), self::MAX_TEXT)),
            $db->quote($this->truncate($context['ip_address'], 45)),
            $db->quote($status),
            $db->quote($this->truncate($context['browser'], 100)),
            $db->quote($this->truncate($context['operating_system'], 100)),
            $db->quote(''), $db->quote(''), $db->quote(''), $db->quote(''), $db->quote(''), $db->quote(''),
            $db->quote($context['where_at']), $db->quote($context['user_agent']), $db->quote($attemptType),
            $db->quote($this->truncate($method, 100)), $db->quote($context['where_at']), $db->quote($reason),
            $db->quote(gmdate('Y-m-d H:i:s')),
        ];

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__loginguard_attempts'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));
        $db->setQuery($query)->execute();
    }

    private function maybeAutoBlockMfa(string $ipAddress, string $client): void
    {
        if ($ipAddress === 'unknown') {
            return;
        }

        $params = ComponentHelper::getParams('com_loginguard');
        if (!(int) $params->get('enforcement_enabled', 0) || !(int) $params->get('mfa_automatic_blocking_enabled', 0)) {
            return;
        }
        if (($client === 'backend' && !(int) $params->get('backend_enforcement_enabled', 1))
            || ($client === 'frontend' && !(int) $params->get('frontend_enforcement_enabled', 1))) {
            return;
        }
        if ($this->isWhitelistedIp($ipAddress, (string) $params->get('whitelisted_ips', ''))) {
            return;
        }

        $threshold = max(1, (int) $params->get('mfa_failed_attempt_threshold', 5));
        $windowMinutes = max(1, (int) $params->get('mfa_threshold_window_minutes', 10));
        $cooldownMinutes = max(1, (int) $params->get('mfa_cooldown_duration_minutes', 30));
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
            ->where($db->quoteName('status') . ' IN (' . $db->quote('MFA_FAILED') . ',' . $db->quote('MFA_TRY_LIMIT') . ')')
            ->where($db->quoteName('created') . ' >= ' . $db->quote($since));
        $db->setQuery($query);
        $failureCount = (int) $db->loadResult();
        if ($failureCount < $threshold) {
            return;
        }

        $scope = (string) $params->get('automatic_block_scope', 'all');
        $scope = in_array($scope, ['all', 'frontend', 'backend'], true) ? $scope : 'all';
        $now = gmdate('Y-m-d H:i:s');

        // Expired rows are history and must not retain the unique active key.
        $expire = $db->getQuery(true)
            ->update($db->quoteName('#__loginguard_blocked_ips'))
            ->set($db->quoteName('enabled') . ' = 0')
            ->set($db->quoteName('active_key') . ' = NULL')
            ->set($db->quoteName('disabled_at') . ' = ' . $db->quote($now))
            ->set($db->quoteName('disabled_by') . ' = 0')
            ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
            ->where($db->quoteName('scope') . ' = ' . $db->quote($scope))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('block_type') . ' = ' . $db->quote('temporary'))
            ->where($db->quoteName('blocked_until') . ' IS NOT NULL')
            ->where($db->quoteName('blocked_until') . ' < ' . $db->quote($now));
        $db->setQuery($expire)->execute();

        $blockedUntil = gmdate('Y-m-d H:i:s', time() + ($cooldownMinutes * 60));
        $activeKey = hash('sha256', $ipAddress . '|' . $scope);
        $columns = [
            'ip_address', 'scope', 'block_type', 'reason', 'source', 'active_key', 'failure_count',
            'blocked_until', 'created', 'created_by', 'updated', 'updated_by', 'disabled_at', 'disabled_by', 'enabled',
        ];
        $values = [
            $db->quote($ipAddress), $db->quote($scope), $db->quote('temporary'), $db->quote('mfa_threshold_exceeded'),
            $db->quote('automatic'), $db->quote($activeKey), (string) $failureCount, $db->quote($blockedUntil),
            $db->quote($now), '0', 'NULL', '0', 'NULL', '0', '1',
        ];
        $sql = 'INSERT IGNORE INTO ' . $db->quoteName('#__loginguard_blocked_ips')
            . ' (' . implode(',', array_map([$db, 'quoteName'], $columns)) . ') VALUES (' . implode(',', $values) . ')';
        $db->setQuery($sql)->execute();

        if ($db->getAffectedRows() > 0) {
            $this->sendMfaThresholdAlert($ipAddress, $client, $failureCount, $blockedUntil);
        }
    }

    private function sendMfaFailureAlert($user, array $context, string $status, string $reason, string $method, array $telemetry): void
    {
        $params = ComponentHelper::getParams('com_loginguard');
        if (!(int) $params->get('audit_alerts_enabled', 0) || !(int) $params->get('audit_alert_failed', 1)) {
            return;
        }
        $this->sendSharedAuditAlert($user, $context, $status, $reason, $method, $telemetry);
    }

    private function sendMfaThresholdAlert(string $ipAddress, string $client, int $failureCount, string $blockedUntil): void
    {
        $params = ComponentHelper::getParams('com_loginguard');
        if (!(int) $params->get('audit_alerts_enabled', 0) || !(int) $params->get('mfa_alert_threshold', 1)) {
            return;
        }

        $this->sendAlert('[LOGIN GUARD] MFA FAILURE THRESHOLD BLOCK', [
            'IP Address' => $ipAddress,
            'Where' => ucfirst($client),
            'MFA Failures' => (string) $failureCount,
            'Blocked Until (UTC)' => $blockedUntil,
            'Date/Time (UTC)' => gmdate('Y-m-d H:i:s'),
        ], (string) $params->get('audit_alert_recipients', ''));
    }

    private function sendFinalSuccessAlert($user, array $context, string $method, array $telemetry): void
    {
        $params = ComponentHelper::getParams('com_loginguard');
        if (!(int) $params->get('audit_alerts_enabled', 0) || !(int) $params->get('audit_alert_success', 0)) {
            return;
        }

        $this->sendSharedAuditAlert($user, $context, 'SUCCESS_LOGIN', 'MFA_COMPLETED', $method, $telemetry);
    }

    private function sendSharedAuditAlert($user, array $context, string $status, string $reason, string $method, array $telemetry): void
    {
        $record = array_merge($telemetry, [
            'user_id' => (int) ($user->id ?? 0),
            'name' => (string) ($user->name ?? ''),
            'username' => (string) ($user->username ?? ''),
            'email' => (string) ($user->email ?? ''),
            'ip_address' => $context['ip_address'],
            'where_at' => $context['where_at'],
            'browser' => $context['browser'],
            'operating_system' => $context['operating_system'],
            'user_agent' => $context['user_agent'],
            'status' => $status,
            'reason' => $reason,
            'mfa_method' => $method !== '' ? $method : 'Unknown',
            'mfa_status' => $status,
            'mfa_reason' => $reason,
            'created' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->getApplication()->bootComponent('com_loginguard');
        (new AuditAlertService())->send($record, $this->getDatabase());
    }

    /** @return array<string, mixed> */
    private function loadPendingAttemptTelemetry(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $pendingAttemptId = (int) $this->getApplication()->getSession()->get(self::ATTEMPT_SESSION_KEY . $userId, 0);
        if ($pendingAttemptId <= 0) {
            return [];
        }

        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['country', 'country_code', 'region', 'city', 'isp', 'asn']))
            ->from($db->quoteName('#__loginguard_attempts'))
            ->where($db->quoteName('user_id') . ' = ' . (string) $userId)
            ->where($db->quoteName('id') . ' = ' . (string) $pendingAttemptId);
        $db->setQuery($query, 0, 1);

        return (array) ($db->loadAssoc() ?: []);
    }

    /** @param array<string, string> $rows */
    private function sendAlert(string $subject, array $rows, string $recipientConfig): void
    {
        $recipients = preg_split('/[\s,;]+/', $recipientConfig, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $recipients = array_values(array_unique(array_filter(array_map('trim', $recipients), static fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))));
        if ($recipients === []) {
            return;
        }

        $htmlRows = '';
        $plainRows = [];
        foreach ($rows as $label => $value) {
            $plainRows[] = $label . ': ' . $value;
            $htmlRows .= '<tr><th style="text-align:left;padding:8px;border-bottom:1px solid #ddd">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th><td style="padding:8px;border-bottom:1px solid #ddd">'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }

        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($recipients);
            $mailer->setSubject($subject);
            $mailer->setBody('<h2>' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h2><table>' . $htmlRows . '</table><p>Generated automatically by Login Guard MDA.</p>');
            $mailer->AltBody = implode("\n", $plainRows) . "\n\nGenerated automatically by Login Guard MDA.";
            $mailer->isHtml(true);
            $mailer->Send();
            $this->recordHealth('mail', 'healthy', 'Last MFA-aware LoginGuard alert was sent successfully.');
        } catch (Throwable $exception) {
            $this->recordFailure('mail', $exception);
        }
    }

    private function isWhitelistedIp(string $ipAddress, string $configured): bool
    {
        return IpResolver::matchesAnyRule($ipAddress, $configured);
    }

    private function recordHealth(string $key, string $status, string $message): void
    {
        try {
            $db = $this->getDatabase();
            $sql = 'INSERT INTO ' . $db->quoteName('#__loginguard_health')
                . ' (' . implode(',', array_map([$db, 'quoteName'], ['health_key', 'status', 'message', 'updated'])) . ')'
                . ' VALUES (' . implode(',', [
                    $db->quote($this->truncate($key, 64)), $db->quote($this->truncate($status, 20)),
                    $db->quote($this->truncate($message, 2000)), $db->quote(gmdate('Y-m-d H:i:s')),
                ]) . ') ON DUPLICATE KEY UPDATE '
                . $db->quoteName('status') . '=VALUES(' . $db->quoteName('status') . '),'
                . $db->quoteName('message') . '=VALUES(' . $db->quoteName('message') . '),'
                . $db->quoteName('updated') . '=VALUES(' . $db->quoteName('updated') . ')';
            $db->setQuery($sql)->execute();
        } catch (Throwable $exception) {
            Log::add('LoginGuard health write failed: ' . $exception->getMessage(), Log::ERROR, 'com_loginguard.health');
        }
    }

    private function recordFailure(string $category, Throwable $exception): void
    {
        Log::add('LoginGuard ' . $category . ' failure: ' . $this->truncate($exception->getMessage(), 2000), Log::ERROR, 'com_loginguard.' . $category);
        $this->recordHealth($category, 'degraded', $exception->getMessage());
    }

    private function detectBrowser(string $userAgent): string
    {
        return match (true) {
            stripos($userAgent, 'Edg/') !== false => 'Microsoft Edge',
            stripos($userAgent, 'OPR/') !== false || stripos($userAgent, 'Opera') !== false => 'Opera',
            stripos($userAgent, 'Chrome/') !== false => 'Chrome',
            stripos($userAgent, 'Firefox/') !== false => 'Firefox',
            stripos($userAgent, 'Safari/') !== false => 'Safari',
            default => 'Unknown',
        };
    }

    private function detectOperatingSystem(string $userAgent): string
    {
        return match (true) {
            stripos($userAgent, 'Windows') !== false => 'Windows',
            stripos($userAgent, 'Mac OS') !== false || stripos($userAgent, 'Macintosh') !== false => 'macOS',
            stripos($userAgent, 'Android') !== false => 'Android',
            stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'iPad') !== false => 'iOS',
            stripos($userAgent, 'Linux') !== false => 'Linux',
            default => 'Unknown',
        };
    }

    private function truncate(string $value, int $maxLength): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }
        return substr($value, 0, $maxLength);
    }
}
