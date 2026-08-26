<?php

namespace Joomla\Plugin\User\LoginGuard\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Plugin\User\LoginGuard\Service\IpResolver;
use LoginGuard\Component\LoginGuard\Administrator\Service\AuditAlertService;
use Throwable;

final class LoginGuard extends CMSPlugin
{
    protected $autoloadLanguage = true;

    private const MAX_USERNAME = 255;
    private const MAX_NAME = 255;
    private const MAX_EMAIL = 255;
    private const MAX_IP = 45;
    private const MAX_USER_AGENT = 2048;

    /**
     * Enforce LoginGuard IP blocking before Joomla creates an authenticated session.
     *
     * @return AuthenticationResponse|null
     */
    public function onUserAuthorisation($response = null, $options = [])
    {
        if ($response instanceof Event) {
            $event = $response;
            $authResponse = $this->getAuthenticationResponseFromEvent($event);

            if ($authResponse === null || !$this->enforceBlockedIp($authResponse)) {
                return null;
            }

            $deniedResponse = $this->markAuthenticationResponseDenied($authResponse);
            $event->addResult($deniedResponse);
            $this->enqueueBlockedLoginMessage();

            return null;
        }

        if (!$response instanceof AuthenticationResponse || !$this->enforceBlockedIp($response)) {
            return null;
        }

        $deniedResponse = $this->markAuthenticationResponseDenied($response);
        $this->enqueueBlockedLoginMessage();

        return $deniedResponse;
    }

    /**
     * Participate neutrally in Joomla's result-aggregating user login event.
     *
     * Joomla's application login lifecycle aggregates the boolean results from
     * user plugins before its MultiFactorAuthenticationHandler can take over the
     * authenticated session. Keep the result contributed by LoginGuard
     * explicitly successful, as it was in the known-good v0.2.20 boundary;
     * auditing and enforcement are deliberately handled by other callbacks.
     */
    public function onUserLogin($user = [], $options = []): bool
    {
        return true;
    }

    private function enforceBlockedIp($payload = []): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        try {
            $db = $this->getDatabase();
            $client = $this->detectWhere();
            $params = ComponentHelper::getParams('com_loginguard');
            $ipAddress = $this->cleanString(IpResolver::resolve(
                null,
                (string) $params->get('trusted_proxy_ips', ''),
                (string) $params->get('forwarded_ip_header', 'none')
            ), 'unknown', self::MAX_IP);
            $params = ComponentHelper::getParams('com_loginguard');

            if (!$this->isEnforcementEnabled($client, $params) || $this->isWhitelistedIp($ipAddress, $params)) {
                return false;
            }

            $block = $this->getActiveBlockForIp($ipAddress, $client, $db);

            if ($block === null) {
                $this->recordHealth($db, 'enforcement', 'healthy', 'IP enforcement check completed.');
                return false;
            }

            $record = $this->buildAttemptRecord([
                'name' => $this->readPayloadValue($payload, 'name', ''),
                'username' => $this->readPayloadValue($payload, 'username', null),
                'email' => $this->readPayloadValue($payload, 'email', ''),
                'user_id' => 0,
                'status' => 'BLOCKED_LOGIN',
                'reason' => 'IP_BLOCKED',
            ], $ipAddress, $client);

            $this->insertAttemptRecord($record, $db);
            $this->sendBlockedIpAlert($record, $block, $db);
            $this->recordHealth($db, 'enforcement', 'healthy', 'Blocked IP enforcement completed.');

            return true;
        } catch (Throwable $exception) {
            $this->recordFailure('enforcement', $exception);
            return false;
        }
    }

    private function getAuthenticationResponseFromEvent(Event $event): ?AuthenticationResponse
    {
        if (method_exists($event, 'getAuthenticationResponse')) {
            $response = $event->getAuthenticationResponse();
            return $response instanceof AuthenticationResponse ? $response : null;
        }

        $arguments = $event->getArguments();

        foreach (['authenticationResponse', 'subject', 0] as $key) {
            if (array_key_exists($key, $arguments) && $arguments[$key] instanceof AuthenticationResponse) {
                return $arguments[$key];
            }
        }

        return null;
    }

    private function markAuthenticationResponseDenied(AuthenticationResponse $response): AuthenticationResponse
    {
        $response->status = Authentication::STATUS_DENIED;
        $response->error_message = Text::_('PLG_USER_LOGINGUARD_LOGIN_BLOCKED');
        return $response;
    }

    private function enqueueBlockedLoginMessage(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        try {
            Factory::getApplication()->enqueueMessage(Text::_('PLG_USER_LOGINGUARD_LOGIN_BLOCKED'), 'warning');
        } catch (Throwable $exception) {
            $this->recordFailure('message', $exception);
        }
    }

    /** Log primary authentication without treating captive MFA as a completed login. */
    public function onUserAfterLogin($options = []): void
    {
        $payload = $this->normaliseEventPayload($options);
        $user = $payload['user'] ?? $payload;

        $userId = (int) $this->readPayloadValue($user, 'id', 0);
        $client = $this->detectWhere();
        // This deployment requires every interactive web login to complete
        // Joomla captive MFA. Joomla does not expose a lifecycle signal here
        // which also covers mandatory first-time setup, so do not infer final
        // authentication from the presence of an active-method row. API and CLI
        // authentication cannot enter the web captive flow and remain final.
        $requiresMfa = in_array($client, ['frontend', 'backend'], true);

        $this->storeAttempt([
            'name' => $this->readPayloadValue($user, 'name', ''),
            'username' => $this->readPayloadValue($user, 'username', $this->readPayloadValue($payload, 'username', null)),
            'email' => $this->readPayloadValue($user, 'email', $this->readPayloadValue($payload, 'email', '')),
            'user_id' => $userId,
            // Joomla alone owns captive routing; LoginGuard only classifies the
            // primary interactive event as neutral, incomplete telemetry.
            'status' => $requiresMfa ? 'MFA_PENDING' : 'SUCCESS_LOGIN',
            'reason' => $requiresMfa ? 'MFA_REQUIRED' : '',
        ]);
    }

    /** Log a failed Joomla username/password login without storing plaintext passwords. */
    public function onUserLoginFailure($response = []): void
    {
        $payload = $this->normaliseEventPayload($response);

        $this->storeAttempt([
            'name' => $this->readPayloadValue($payload, 'name', ''),
            'username' => $this->readPayloadValue($payload, 'username', null),
            'email' => $this->readPayloadValue($payload, 'email', ''),
            'user_id' => 0,
            'status' => 'FAILED_LOGIN',
            'reason' => $this->detectFailureReason($payload),
        ]);
    }

    public function onUserAfterLogout($options = []): void
    {
        // LoginGuard only audits login attempts; logout must never interrupt Joomla.
    }

    /** @param array<string, mixed> $attempt */
    private function storeAttempt(array $attempt): void
    {
        try {
            $db = $this->getDatabase();
            $params = ComponentHelper::getParams('com_loginguard');
            $ipAddress = $this->cleanString(IpResolver::resolve(
                null,
                (string) $params->get('trusted_proxy_ips', ''),
                (string) $params->get('forwarded_ip_header', 'none')
            ), 'unknown', self::MAX_IP);
            $client = $this->detectWhere();
            $record = $this->buildAttemptRecord($attempt, $ipAddress, $client);

            $this->insertAttemptRecord($record, $db);
            $this->recordHealth($db, 'database', 'healthy', 'Login audit write completed.');

            // Captive MFA has not reached an authentication outcome yet. Keep
            // this telemetry out of both alert pipelines and failure blocking.
            if (($record['status'] ?? '') === 'MFA_PENDING') {
                return;
            }

            $this->maybeAutoBlockIp($record, $db);
            $this->sendAuditAlert($record, $db);
        } catch (Throwable $exception) {
            // LoginGuard audit failures must never interrupt Joomla authentication.
            $this->recordFailure('database', $exception);
        }
    }

    private function getDatabase(): DatabaseDriver
    {
        try {
            return Factory::getContainer()->get(DatabaseDriver::class);
        } catch (Throwable $exception) {
            try {
                return Factory::getContainer()->get('DatabaseDriver');
            } catch (Throwable $containerException) {
                return Factory::getDbo();
            }
        }
    }

    /** @param array<string, mixed> $attempt @return array<string, mixed> */
    private function buildAttemptRecord(array $attempt, string $ipAddress, string $client): array
    {
        $userAgent = $this->truncate((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), self::MAX_USER_AGENT);
        return [
            'username' => $this->normaliseTelemetryUsername($attempt['username'] ?? null),
            'user_id' => (int) ($attempt['user_id'] ?? 0),
            'name' => $this->cleanString((string) ($attempt['name'] ?? ''), '', self::MAX_NAME),
            'email' => $this->cleanString((string) ($attempt['email'] ?? ''), '', self::MAX_EMAIL),
            'status' => $this->normaliseStatus((string) ($attempt['status'] ?? 'FAILED_LOGIN')),
            'ip_address' => $this->cleanString($ipAddress, 'unknown', self::MAX_IP),
            'user_agent' => $userAgent,
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'where_at' => $client,
            'client' => $client,
            'attempt_type' => $this->truncate((string) ($attempt['attempt_type'] ?? 'login'), 50),
            'mfa_method' => $this->truncate((string) ($attempt['mfa_method'] ?? ''), 100),
            'reason' => $this->normaliseFailureReason((string) ($attempt['reason'] ?? '')),
            'created' => gmdate('Y-m-d H:i:s'),
        ];
    }

    /** @param array<string, mixed> $record */
    private function insertAttemptRecord(array $record, DatabaseDriver $db): int
    {
        $columns = array_keys($record);
        $values = [];

        foreach ($record as $column => $value) {
            if ($column === 'user_id') {
                $values[] = (string) (int) $value;
            } elseif ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $db->quote((string) $value);
            }
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__loginguard_attempts'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query)->execute();

        return (int) $db->insertid();
    }

    private function isEnforcementEnabled(string $client, $params): bool
    {
        if (!$params->get('enforcement_enabled', 0)) {
            return false;
        }

        if ($client === 'backend') {
            return (bool) $params->get('backend_enforcement_enabled', 1);
        }

        return (bool) $params->get('frontend_enforcement_enabled', 1);
    }

    private function isWhitelistedIp(string $ipAddress, $params): bool
    {
        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return false;
        }

        $configured = (string) $params->get('whitelisted_ips', '');
        return IpResolver::matchesAnyRule($ipAddress, $configured);
    }

    private function ipMatchesRule(string $ipAddress, string $rule): bool
    {
        return IpResolver::matchesRule($ipAddress, $rule);
    }

    private function getActiveBlockForIp(string $ipAddress, string $client, DatabaseDriver $db): ?object
    {
        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return null;
        }

        $now = gmdate('Y-m-d H:i:s');
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('scope') . ' IN (' . $this->quoteList($db, ['all', $client]) . ')')
            ->where(
                '(' . $db->quoteName('block_type') . ' = ' . $db->quote('permanent')
                . ' OR (' . $db->quoteName('block_type') . ' = ' . $db->quote('temporary')
                . ' AND ' . $db->quoteName('blocked_until') . ' IS NOT NULL'
                . ' AND ' . $db->quoteName('blocked_until') . ' >= ' . $db->quote($now) . '))'
            )
            ->order($db->quoteName('created') . ' DESC');

        $db->setQuery($query, 0, 1);
        $block = $db->loadObject();
        return $block ?: null;
    }

    /** @param array<string, mixed> $record */
    private function maybeAutoBlockIp(array $record, DatabaseDriver $db): void
    {
        $params = ComponentHelper::getParams('com_loginguard');

        if (!$params->get('automatic_blocking_enabled', 0) || (string) ($record['status'] ?? '') !== 'FAILED_LOGIN') {
            return;
        }

        $ipAddress = (string) ($record['ip_address'] ?? '');
        $client = (string) ($record['where_at'] ?? 'frontend');

        if (!$this->isEnforcementEnabled($client, $params) || $this->isWhitelistedIp($ipAddress, $params)) {
            return;
        }

        $threshold = max(1, (int) $params->get('failed_attempt_threshold', 5));
        $windowMinutes = max(1, (int) $params->get('threshold_window_minutes', 15));
        $cooldownMinutes = max(1, (int) $params->get('cooldown_duration_minutes', 30));
        $scope = (string) $params->get('automatic_block_scope', 'all');
        $scope = in_array($scope, ['all', 'frontend', 'backend'], true) ? $scope : 'all';
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__loginguard_attempts'))
                ->where($db->quoteName('status') . ' = ' . $db->quote('FAILED_LOGIN'))
                ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($since));
            $db->setQuery($query);
            $failureCount = (int) $db->loadResult();

            if ($failureCount < $threshold || $this->getActiveBlockForIp($ipAddress, $client, $db) !== null) {
                return;
            }

            $now = gmdate('Y-m-d H:i:s');

            // Release the uniqueness key from every expired row before creating a new active block.
            $expireQuery = $db->getQuery(true)
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
            $db->setQuery($expireQuery)->execute();

            $blockedUntil = gmdate('Y-m-d H:i:s', time() + ($cooldownMinutes * 60));
            $activeKey = hash('sha256', $ipAddress . '|' . $scope);
            $columns = [
                'ip_address', 'scope', 'block_type', 'reason', 'source', 'active_key', 'failure_count',
                'blocked_until', 'created', 'created_by', 'updated', 'updated_by', 'disabled_at', 'disabled_by', 'enabled',
            ];
            $values = [
                $db->quote($ipAddress), $db->quote($scope), $db->quote('temporary'), $db->quote('threshold_exceeded'),
                $db->quote('automatic'), $db->quote($activeKey), (string) $failureCount, $db->quote($blockedUntil),
                $db->quote($now), '0', 'NULL', '0', 'NULL', '0', '1',
            ];

            $sql = 'INSERT IGNORE INTO ' . $db->quoteName('#__loginguard_blocked_ips')
                . ' (' . implode(',', array_map([$db, 'quoteName'], $columns)) . ') VALUES (' . implode(',', $values) . ')';
            $db->setQuery($sql)->execute();

            if ($db->getAffectedRows() > 0) {
                $this->sendBlockedIpAlert($record + ['block_until' => $blockedUntil, 'failure_count' => $failureCount], null, $db);
            }

            $this->recordHealth($db, 'enforcement', 'healthy', 'Automatic password-failure blocking evaluation completed.');
        } catch (Throwable $exception) {
            $this->recordFailure('automatic_block', $exception);
        }
    }

    /** @param array<string, mixed> $record */
    private function sendAuditAlert(array $record, DatabaseDriver $db): void
    {
        // Keep post-login auditing independent of Joomla's session and captive MFA state.
        $this->getApplication()->bootComponent('com_loginguard');
        (new AuditAlertService())->send($record, $db);
    }

    /** @param array<string, mixed> $record */
    private function sendBlockedIpAlert(array $record, ?object $block, DatabaseDriver $db): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $params = ComponentHelper::getParams('com_loginguard');
        if (!$params->get('audit_alerts_enabled', 0) || !$params->get('blocked_ip_alerts_enabled', 1)) {
            return;
        }

        $recipients = $this->normaliseAlertRecipients((string) $params->get('audit_alert_recipients', ''));
        if ($recipients === []) {
            return;
        }

        $variables = $this->buildAlertTemplateVariables($record);
        $variables['block_type'] = (string) ($block->block_type ?? 'temporary');
        $variables['block_until'] = $this->formatConfiguredDateTime((string) ($block->blocked_until ?? ($record['block_until'] ?? '')));
        $variables['failure_count'] = (string) ($block->failure_count ?? ($record['failure_count'] ?? ''));
        $variables['block_reason'] = (string) ($block->reason ?? 'threshold_exceeded');

        $subjectTemplate = (string) $params->get('blocked_ip_alert_subject', '[LOGIN GUARD] BLOCKED LOGIN ATTEMPT');
        $bodyTemplate = (string) $params->get('blocked_ip_alert_body', $this->getDefaultBlockedIpAlertBodyTemplate());
        $this->sendMail($recipients, $subjectTemplate, $bodyTemplate, $variables, 'BLOCKED_LOGIN', $db);
    }

    /** @param list<string> $recipients @param array<string, string> $variables */
    private function sendMail(array $recipients, string $subjectTemplate, string $bodyTemplate, array $variables, string $status, DatabaseDriver $db): void
    {
        $subjectTemplate = $this->normaliseLegacyGeoIpTemplate($subjectTemplate);
        $bodyTemplate = $this->normaliseLegacyGeoIpTemplate($bodyTemplate);
        $subject = strtoupper($this->replaceAlertTemplateVariables($subjectTemplate, $variables));
        $plainBody = $this->withAlertFooter($this->replaceAlertTemplateVariables($bodyTemplate, $variables));
        $htmlBody = $this->buildAlertHtmlBody($subject, $bodyTemplate, $variables, $status);

        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($recipients);
            $mailer->setSubject($subject);
            $mailer->setBody($htmlBody);
            $mailer->AltBody = $plainBody;
            $mailer->isHtml(true);
            $mailer->Send();
            $this->recordHealth($db, 'mail', 'healthy', 'Last configured LoginGuard alert was sent successfully.');
        } catch (Throwable $exception) {
            $this->recordFailure('mail', $exception);
        }
    }

    /** @param array<string, mixed> $record */
    private function isFailedAlertThrottled(array $record, DatabaseDriver $db): bool
    {
        $params = ComponentHelper::getParams('com_loginguard');
        if (!$params->get('failed_alert_throttling_enabled', 0)) {
            return false;
        }

        $ipAddress = (string) ($record['ip_address'] ?? '');
        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return false;
        }

        $threshold = max(1, (int) $params->get('failed_alert_threshold', 10));
        $windowMinutes = max(1, (int) $params->get('failed_alert_throttle_window_minutes', 15));
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__loginguard_attempts'))
                ->where($db->quoteName('status') . ' = ' . $db->quote('FAILED_LOGIN'))
                ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($since));
            $db->setQuery($query);
            return (int) $db->loadResult() > $threshold;
        } catch (Throwable $exception) {
            $this->recordFailure('alert_throttle', $exception);
            return false;
        }
    }

    /** @return list<string> */
    private function normaliseAlertRecipients(string $configuredRecipients): array
    {
        $recipients = preg_split('/[\s,;]+/', $configuredRecipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validRecipients = [];

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $validRecipients[] = $recipient;
            }
        }

        return array_values(array_unique($validRecipients));
    }

    /** @param array<string, mixed> $record @return array<string, string> */
    private function buildAlertTemplateVariables(array $record): array
    {
        $config = Factory::getConfig();
        $status = (string) ($record['status'] ?? '');
        $failureReason = in_array($status, ['FAILED_LOGIN', 'BLOCKED_LOGIN', 'MFA_FAILED', 'MFA_TRY_LIMIT'], true)
            ? (string) ($record['reason'] ?? '') : '';

        return [
            'username' => $this->formatNullableUsername($record['username'] ?? null),
            'ip' => (string) ($record['ip_address'] ?? 'unknown'),
            'status' => $this->formatAlertStatus($status),
            'failure_reason' => $this->formatAlertFailureReason($failureReason),
            'where' => $this->formatAlertWhere((string) ($record['where_at'] ?? 'frontend')),
            'browser' => (string) ($record['browser'] ?? 'unknown'),
            'os' => (string) ($record['operating_system'] ?? 'unknown'),
            'datetime' => $this->formatConfiguredDateTime((string) ($record['created'] ?? gmdate('Y-m-d H:i:s'))),
            'site_name' => (string) $config->get('sitename', ''),
            'name' => (string) ($record['name'] ?? ''),
            'full_name' => (string) ($record['name'] ?? ''),
            'email' => (string) ($record['email'] ?? ''),
            'user_agent' => (string) ($record['user_agent'] ?? ''),
        ];
    }

    private function getConfiguredTimezone(): \DateTimeZone
    {
        $timezone = (string) Factory::getConfig()->get('offset', 'UTC');
        try {
            return new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (\Exception $exception) {
            return new \DateTimeZone('UTC');
        }
    }

    private function formatConfiguredDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return '';
        }

        return $date->setTimezone($this->getConfiguredTimezone())->format(Text::_('DATE_FORMAT_LC5'));
    }

    /** @param array<string, string> $variables */
    private function replaceAlertTemplateVariables(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = $value;
        }
        return strtr($template, $replacements);
    }

    /** Remove retired GeoIP rows/tokens from alert templates saved by older releases. */
    private function normaliseLegacyGeoIpTemplate(string $template): string
    {
        $legacyNames = 'country|country_code|region|city|isp|asn';
        $template = preg_replace(
            '/^[ \t]*[^{}\r\n:]{1,80}:[ \t]*\{(?:' . $legacyNames . ')\}[ \t]*(?:\R|$)/mi',
            '',
            $template
        ) ?? $template;

        return preg_replace('/\{(?:' . $legacyNames . ')\}/i', '', $template) ?? $template;
    }

    private function getDefaultAlertBodyTemplate(): string
    {
        return "LoginGuard recorded a {status} event on {site_name}.\n\nFull Name: {full_name}\nUsername: {username}\nEmail: {email}\nIP Address: {ip}\nWhere: {where}\nBrowser: {browser}\nOperating System: {os}\nFailure Reason: {failure_reason}\nUser Agent: {user_agent}\nDate/Time: {datetime}\n\nGenerated automatically by Login Guard MDA.";
    }

    private function getDefaultBlockedIpAlertBodyTemplate(): string
    {
        return "LoginGuard recorded a {status} event on {site_name}.\n\nFull Name: {full_name}\nUsername: {username}\nEmail: {email}\nIP Address: {ip}\nWhere: {where}\nBrowser: {browser}\nOperating System: {os}\nFailure Reason: {failure_reason}\nBlock Type: {block_type}\nBlock Reason: {block_reason}\nBlocked Until: {block_until}\nFailure Count: {failure_count}\nUser Agent: {user_agent}\nDate/Time: {datetime}\n\nGenerated automatically by Login Guard MDA.";
    }

    private function withAlertFooter(string $body): string
    {
        $footer = 'Generated automatically by Login Guard MDA.';
        if (str_contains($body, $footer)) {
            return $body;
        }
        return rtrim($body) . "\n\n" . $footer;
    }

    /** @param array<string, string> $variables */
    private function buildAlertHtmlBody(string $subject, string $bodyTemplate, array $variables, string $status): string
    {
        $accentColor = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#1f8f45',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#b45309',
            default => '#c62828',
        };
        $severityBackground = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#ecfdf3',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#fffbeb',
            default => '#fef2f2',
        };
        $severityText = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#166534',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#92400e',
            default => '#991b1b',
        };
        $htmlRows = '';

        foreach ($this->buildStructuredAlertRows($bodyTemplate, $variables, $status) as $row) {
            $htmlRows .= '<tr>'
                . '<th class="lg-label" style="padding:10px 12px;text-align:left;color:#475569;border-bottom:1px solid #e2e8f0;width:34%;font-weight:700;font-size:13px;line-height:1.4;vertical-align:top;">' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</th>'
                . '<td class="lg-value" style="padding:10px 12px;color:#0f172a;border-bottom:1px solid #e2e8f0;font-size:14px;line-height:1.45;word-break:break-word;vertical-align:top;">' . nl2br(htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8')) . '</td>'
                . '</tr>';
        }

        $intro = $this->extractAlertIntro($bodyTemplate, $variables);
        $footer = $this->extractAlertFooter($bodyTemplate, $variables);
        $statusLabel = $variables['status'] ?? $this->formatAlertStatus($status);
        $siteName = $variables['site_name'] ?? '';

        return '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>@media only screen and (max-width:600px){.lg-wrap{padding:12px!important}.lg-card-body{padding:18px!important}.lg-label,.lg-value{display:block!important;width:auto!important}.lg-label{padding-bottom:4px!important;border-bottom:0!important}.lg-value{padding-top:0!important}.lg-title{font-size:18px!important}}</style>'
            . '</head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;-webkit-text-size-adjust:100%;">'
            . '<div class="lg-wrap" style="max-width:680px;margin:0 auto;padding:24px 16px;">'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">'
            . '<div style="height:8px;background:' . $accentColor . ';"></div>'
            . '<div class="lg-card-body" style="padding:24px;">'
            . '<p style="margin:0 0 10px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;">LoginGuard Security Notification</p>'
            . '<h1 class="lg-title" style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#0f172a;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div style="display:inline-block;margin:0 0 18px;padding:6px 10px;border-radius:999px;background:' . $severityBackground . ';color:' . $severityText . ';font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</div>'
            . ($intro !== '' ? '<p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#334155;">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>' : '')
            . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">' . $htmlRows . '</table>'
            . '<p style="margin:0;font-size:12px;color:#64748b;text-align:center;line-height:1.5;">' . htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') . ($siteName !== '' ? '<br>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') : '') . '</p>'
            . '</div></div></div></body></html>';
    }

    /** @param array<string, string> $variables @return list<array{label: string, value: string}> */
    private function buildStructuredAlertRows(string $bodyTemplate, array $variables, string $status): array
    {
        $labels = $this->getAlertVariableLabels();
        $variableNames = $this->extractAlertTemplateVariableNames($bodyTemplate);

        if ($variableNames === []) {
            $variableNames = ['full_name', 'username', 'email', 'ip', 'status', 'failure_reason', 'where', 'browser', 'os', 'user_agent', 'datetime'];
        }
        if (in_array($status, ['SUCCESS_LOGIN', 'MFA_SUCCESS'], true)) {
            $variableNames = array_values(array_filter($variableNames, static fn ($name) => $name !== 'failure_reason'));
        }

        $rows = [];
        $seen = [];
        foreach ($variableNames as $name) {
            if (isset($seen[$name]) || !array_key_exists($name, $labels)) {
                continue;
            }
            $value = trim((string) ($variables[$name] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows[] = ['label' => $this->extractAlertVariableLabel($bodyTemplate, $name, $labels[$name]), 'value' => $value];
            $seen[$name] = true;
        }
        return $rows;
    }

    /** @return array<string, string> */
    private function getAlertVariableLabels(): array
    {
        return [
            'full_name' => 'Full Name', 'name' => 'Full Name', 'username' => 'Username', 'email' => 'Email',
            'ip' => 'IP Address', 'status' => 'Status', 'failure_reason' => 'Failure Reason', 'where' => 'Where',
            'browser' => 'Browser', 'os' => 'Operating System', 'user_agent' => 'User Agent',
            'datetime' => 'Date/Time', 'block_type' => 'Block Type', 'block_reason' => 'Block Reason',
            'block_until' => 'Blocked Until', 'failure_count' => 'Failure Count',
        ];
    }

    /** @return list<string> */
    private function extractAlertTemplateVariableNames(string $template): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $template, $matches);
        $names = [];
        foreach ($matches[1] ?? [] as $name) {
            $name = strtolower((string) $name);
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    private function extractAlertVariableLabel(string $template, string $name, string $default): string
    {
        if (preg_match('/^\s*([^\r\n:{}]{2,60})\s*:\s*\{' . preg_quote($name, '/') . '\}/mi', $template, $match)) {
            return trim($match[1]);
        }
        return $default;
    }

    /** @param array<string, string> $variables */
    private function extractAlertIntro(string $template, array $variables): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($template), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($paragraphs as $paragraph) {
            if (preg_match('/^\s*[^\r\n:{}]+\s*:\s*\{[a-z0-9_]+\}/mi', $paragraph)) {
                continue;
            }
            $intro = trim($this->replaceAlertTemplateVariables($paragraph, $variables));
            $intro = preg_replace('/\s+/', ' ', $intro) ?? $intro;
            if ($intro !== '' && !str_contains($intro, 'Generated automatically by Login Guard MDA.')) {
                return $intro;
            }
        }
        return 'LoginGuard recorded this security event with structured telemetry for review.';
    }

    /** @param array<string, string> $variables */
    private function extractAlertFooter(string $template, array $variables): string
    {
        $footer = 'Generated automatically by Login Guard MDA.';
        $paragraphs = preg_split('/\R{2,}/', trim($template), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($index = count($paragraphs) - 1; $index >= 0; $index--) {
            $paragraph = trim($paragraphs[$index]);
            if ($index === 0 || $paragraph === '' || preg_match('/^\s*[^\r\n:{}]+\s*:\s*\{[a-z0-9_]+\}/mi', $paragraph)) {
                continue;
            }
            $customFooter = trim($this->replaceAlertTemplateVariables($paragraph, $variables));
            if ($customFooter !== '') {
                return $customFooter;
            }
        }
        return $footer;
    }

    private function formatAlertStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS_LOGIN' => 'SUCCESS LOGIN',
            'FAILED_LOGIN' => 'FAILED LOGIN',
            'BLOCKED_LOGIN' => 'BLOCKED LOGIN',
            'MFA_PENDING' => 'MFA PENDING',
            'MFA_FAILED' => 'MFA FAILED',
            'MFA_SUCCESS' => 'MFA SUCCESS',
            'MFA_TRY_LIMIT' => 'MFA TRY LIMIT',
            default => str_replace('_', ' ', strtoupper($status)),
        };
    }

    private function formatAlertFailureReason(string $reason): string
    {
        return match ($reason) {
            'PASSWORD_INCORRECT' => 'Incorrect Password',
            'USERNAME_NOT_FOUND' => 'User Not Found',
            'ACCOUNT_DISABLED' => 'Account Disabled',
            'ACCOUNT_BLOCKED' => 'Account Blocked',
            'IP_BLOCKED' => 'IP Address Blocked',
            'INVALID_CREDENTIALS' => 'Invalid Credentials',
            'MFA_REQUIRED' => 'MFA Required',
            'MFA_INVALID_CODE' => 'Invalid MFA Code',
            'MFA_INVALID_METHOD' => 'Invalid MFA Method',
            'MFA_TRY_LIMIT_REACHED' => 'MFA Try Limit Reached',
            'MFA_COMPLETED' => 'MFA Completed',
            'MFA_THRESHOLD_EXCEEDED' => 'MFA Failure Threshold Exceeded',
            '' => '',
            default => ucwords(strtolower(str_replace('_', ' ', $reason))),
        };
    }

    private function formatAlertWhere(string $where): string
    {
        return match (strtolower($where)) {
            'backend', 'administrator' => 'Backend',
            'frontend', 'site' => 'Frontend',
            'api' => 'API',
            'cli' => 'CLI',
            default => ucwords(strtolower(str_replace('_', ' ', $where))),
        };
    }

    /** @return array<string, mixed> */
    private function normaliseEventPayload($payload): array
    {
        if ($payload instanceof Event) {
            $arguments = $payload->getArguments();
            foreach (['options', 'response', 'user'] as $key) {
                if (array_key_exists($key, $arguments)) {
                    return $this->normaliseEventPayload($arguments[$key]);
                }
            }
            if (isset($arguments[0])) {
                return $this->normaliseEventPayload($arguments[0]);
            }
            return $arguments;
        }

        if (is_array($payload)) {
            return $payload;
        }
        if (is_object($payload)) {
            return ['__payload' => $payload] + get_object_vars($payload);
        }
        return [];
    }

    private function readPayloadValue($payload, string $key, $default = '')
    {
        if (is_array($payload)) {
            if (array_key_exists($key, $payload)) {
                return $payload[$key];
            }
            if (isset($payload['__payload'])) {
                return $this->readPayloadValue($payload['__payload'], $key, $default);
            }
            return $default;
        }

        if (is_object($payload)) {
            if (isset($payload->{$key})) {
                return $payload->{$key};
            }
            if (method_exists($payload, 'get')) {
                return $payload->get($key, $default);
            }
        }
        return $default;
    }

    /** @param array<string, mixed> $payload */
    private function detectFailureReason(array $payload): string
    {
        $error = strtolower((string) $this->readPayloadValue($payload, 'error_message', ''));
        $type = strtoupper((string) $this->readPayloadValue($payload, 'type', ''));

        if (str_contains($error, 'block')) {
            return 'ACCOUNT_BLOCKED';
        }
        if (str_contains($error, 'disable') || str_contains($error, 'inactive') || str_contains($error, 'activate')) {
            return 'ACCOUNT_DISABLED';
        }
        if ($type === 'USERNAME_NOT_FOUND') {
            return 'USERNAME_NOT_FOUND';
        }
        if ($type === 'PASSWORD_INCORRECT') {
            return 'PASSWORD_INCORRECT';
        }

        $username = trim((string) $this->readPayloadValue($payload, 'username', ''));
        if ($username !== '') {
            try {
                $userId = (int) UserHelper::getUserId($username);
                if ($userId === 0) {
                    return 'USERNAME_NOT_FOUND';
                }
                if (str_contains($error, 'password')) {
                    return 'PASSWORD_INCORRECT';
                }
            } catch (Throwable $exception) {
                $this->recordFailure('failure_reason', $exception);
                return 'INVALID_CREDENTIALS';
            }
        }
        return 'INVALID_CREDENTIALS';
    }

    private function detectWhere(): string
    {
        if (PHP_SAPI === 'cli') {
            return 'cli';
        }
        $app = Factory::getApplication();
        if ($app->isClient('api')) {
            return 'api';
        }
        if ($app->isClient('administrator')) {
            return 'backend';
        }
        return 'frontend';
    }

    private function normaliseStatus(string $status): string
    {
        $status = strtoupper(trim($status));
        $allowed = ['SUCCESS_LOGIN', 'FAILED_LOGIN', 'BLOCKED_LOGIN', 'MFA_PENDING', 'MFA_FAILED', 'MFA_SUCCESS', 'MFA_TRY_LIMIT'];
        return in_array($status, $allowed, true) ? $status : 'FAILED_LOGIN';
    }

    private function normaliseFailureReason(string $reason): string
    {
        $reason = strtoupper(trim($reason));
        if ($reason === '') {
            return '';
        }
        $allowed = [
            'USERNAME_NOT_FOUND', 'PASSWORD_INCORRECT', 'INVALID_CREDENTIALS', 'ACCOUNT_BLOCKED', 'ACCOUNT_DISABLED',
            'IP_BLOCKED', 'MFA_REQUIRED', 'MFA_INVALID_CODE', 'MFA_INVALID_METHOD', 'MFA_TRY_LIMIT_REACHED',
            'MFA_COMPLETED', 'MFA_THRESHOLD_EXCEEDED',
        ];
        return in_array($reason, $allowed, true) ? $reason : 'INVALID_CREDENTIALS';
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

    private function recordFailure(string $category, Throwable $exception): void
    {
        $message = $this->truncate($exception->getMessage(), 2000);
        Log::add('LoginGuard ' . $category . ' failure: ' . $message, Log::ERROR, 'com_loginguard.' . preg_replace('/[^a-z0-9_.-]+/i', '_', strtolower($category)));

        try {
            $this->recordHealth($this->getDatabase(), $category, 'degraded', $message);
        } catch (Throwable) {
            // Logging must never recursively interrupt authentication.
        }
    }

    private function recordHealth(DatabaseDriver $db, string $key, string $status, string $message): void
    {
        try {
            $key = $this->truncate(strtolower(trim($key)), 64);
            if ($key === '') {
                return;
            }
            $status = $this->truncate(strtolower(trim($status)), 20);
            $message = $this->truncate($message, 2000);
            $now = gmdate('Y-m-d H:i:s');
            $sql = 'INSERT INTO ' . $db->quoteName('#__loginguard_health')
                . ' (' . implode(',', array_map([$db, 'quoteName'], ['health_key', 'status', 'message', 'updated'])) . ')'
                . ' VALUES (' . implode(',', [$db->quote($key), $db->quote($status ?: 'healthy'), $db->quote($message), $db->quote($now)]) . ')'
                . ' ON DUPLICATE KEY UPDATE '
                . $db->quoteName('status') . '=VALUES(' . $db->quoteName('status') . '),'
                . $db->quoteName('message') . '=VALUES(' . $db->quoteName('message') . '),'
                . $db->quoteName('updated') . '=VALUES(' . $db->quoteName('updated') . ')';
            $db->setQuery($sql)->execute();
        } catch (Throwable $exception) {
            Log::add('LoginGuard health write failure: ' . $this->truncate($exception->getMessage(), 1000), Log::ERROR, 'com_loginguard.health');
        }
    }

    /** @param list<string> $values */
    private function quoteList(DatabaseDriver $db, array $values): string
    {
        return implode(',', array_map(static fn ($value) => $db->quote($value), $values));
    }
}
