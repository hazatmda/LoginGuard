<?php

namespace Joomla\Plugin\User\LoginGuard\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Authentication\Authentication;
use Joomla\CMS\Authentication\AuthenticationResponse;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\UserHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Plugin\User\LoginGuard\Service\IpResolver;
use Throwable;

final class LoginGuard extends CMSPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Enforce LoginGuard IP blocking before Joomla creates an authenticated session.
     *
     * @param   mixed  $response  Joomla authorisation response payload.
     * @param   mixed  $options   Joomla login options payload.
     *
     * @return  AuthenticationResponse|null  Denied response for legacy dispatchers; null when LoginGuard allows the login.
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
     * Keep the legacy login hook non-blocking; blocking happens in onUserAuthorisation.
     *
     * @param   mixed  $user     Joomla login credential payload.
     * @param   mixed  $options  Joomla login options payload.
     *
     * @return  bool
     */
    public function onUserLogin($user = [], $options = []): bool
    {
        return true;
    }

    /**
     * @param   mixed  $payload  Joomla login payload.
     */
    private function enforceBlockedIp($payload = []): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        try {
            $db = $this->getDatabase();
            $this->ensureSchema($db);

            $client = $this->detectWhere();
            $ipAddress = $this->cleanString(IpResolver::resolve(), 'unknown');
            $params = ComponentHelper::getParams('com_loginguard');

            if (!$this->isEnforcementEnabled($client, $params) || $this->isWhitelistedIp($ipAddress, $params)) {
                return false;
            }

            $block = $this->getActiveBlockForIp($ipAddress, $client, $db);

            if ($block === null) {
                return false;
            }

            $record = $this->buildAttemptRecord([
                'name' => $this->readPayloadValue($payload, 'name', ''),
                'username' => $this->readPayloadValue($payload, 'username', 'unknown'),
                'email' => $this->readPayloadValue($payload, 'email', ''),
                'user_id' => 0,
                'status' => 'BLOCKED_LOGIN',
                'reason' => 'IP_BLOCKED',
            ], $ipAddress, $client);

            $this->insertAttemptRecord($record, $db);
            $this->sendBlockedIpAlert($record, $block, $db);

            return true;
        } catch (Throwable $exception) {
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
            // User-facing messaging must never interrupt the authorisation response.
        }
    }

    /**
     * Log a successful Joomla login.
     *
     * @param   mixed  $options  Login event options or an Event payload.
     */
    public function onUserAfterLogin($options = []): void
    {
        $payload = $this->normaliseEventPayload($options);
        $user    = $payload['user'] ?? $payload;

        $this->storeAttempt([
            'name' => $this->readPayloadValue($user, 'name', ''),
            'username' => $this->readPayloadValue($user, 'username', $this->readPayloadValue($payload, 'username', 'unknown')),
            'email' => $this->readPayloadValue($user, 'email', $this->readPayloadValue($payload, 'email', '')),
            'user_id' => (int) $this->readPayloadValue($user, 'id', 0),
            'status' => 'SUCCESS_LOGIN',
            'reason' => '',
        ]);
    }

    /**
     * Log a failed Joomla login without storing plaintext passwords.
     *
     * @param   mixed  $response  Failure response array/object/Event.
     */
    public function onUserLoginFailure($response = []): void
    {
        $payload = $this->normaliseEventPayload($response);

        $this->storeAttempt([
            'name' => $this->readPayloadValue($payload, 'name', ''),
            'username' => $this->readPayloadValue($payload, 'username', 'unknown'),
            'email' => $this->readPayloadValue($payload, 'email', ''),
            'user_id' => 0,
            'status' => 'FAILED_LOGIN',
            'reason' => $this->detectFailureReason($payload),
        ]);
    }

    public function onUserLogout($user = [], $options = []): bool
    {
        return true;
    }

    public function onUserAfterLogout($options = []): void
    {
        // LoginGuard only audits login attempts; logout must never interrupt Joomla.
    }

    /**
     * @param   array<string, mixed>  $attempt
     */
    private function storeAttempt(array $attempt): void
    {
        $db = $this->getDatabase();
        $this->ensureSchema($db);

        $ipAddress = $this->cleanString(IpResolver::resolve(), 'unknown');
        $client    = $this->detectWhere();
        $record    = $this->buildAttemptRecord($attempt, $ipAddress, $client);

        $this->insertAttemptRecord($record, $db);
        $this->maybeAutoBlockIp($record, $db);
        $this->sendAuditAlert($record, $db);
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

    /**
     * @param   array<string, mixed>  $attempt
     *
     * @return  array<string, mixed>
     */
    private function buildAttemptRecord(array $attempt, string $ipAddress, string $client): array
    {
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $geoip     = $this->detectGeoIp($ipAddress);

        return [
            'username' => $this->cleanString((string) ($attempt['username'] ?? 'unknown'), 'unknown'),
            'user_id' => (int) ($attempt['user_id'] ?? 0),
            'name' => $this->cleanString((string) ($attempt['name'] ?? '')),
            'email' => $this->cleanString((string) ($attempt['email'] ?? '')),
            'status' => $this->normaliseStatus((string) ($attempt['status'] ?? 'FAILED_LOGIN')),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'country' => $geoip['country'],
            'country_code' => $geoip['country_code'],
            'region' => $geoip['region'],
            'city' => $geoip['city'],
            'isp' => $geoip['isp'],
            'asn' => $geoip['asn'],
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'where_at' => $client,
            'client' => $client,
            'attempt_type' => 'login',
            'reason' => $this->normaliseFailureReason((string) ($attempt['reason'] ?? '')),
            'created' => (new Date())->toSql(),
        ];
    }

    /** @param array<string, mixed> $record */
    private function insertAttemptRecord(array $record, DatabaseDriver $db): void
    {
        $columns = array_keys($record);
        $values  = [];

        foreach ($record as $column => $value) {
            $values[] = $column === 'user_id' ? (string) (int) $value : $db->quote((string) $value);
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__loginguard_attempts'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query)->execute();
    }

    private function ensureSchema(DatabaseDriver $db): void
    {
        $attemptColumns = [
            'name' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `name` varchar(255) NOT NULL DEFAULT '' AFTER `user_id`",
            'email' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '' AFTER `username`",
            'country' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country` varchar(100) NOT NULL DEFAULT '' AFTER `user_agent`",
            'country_code' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country_code` varchar(10) NOT NULL DEFAULT '' AFTER `country`",
            'region' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `region` varchar(100) NOT NULL DEFAULT '' AFTER `country_code`",
            'city' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `city` varchar(100) NOT NULL DEFAULT '' AFTER `region`",
            'isp' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `isp` varchar(255) NOT NULL DEFAULT '' AFTER `city`",
            'asn' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `asn` varchar(50) NOT NULL DEFAULT '' AFTER `isp`",
            'browser' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `browser` varchar(100) NOT NULL DEFAULT '' AFTER `country`",
            'operating_system' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `operating_system` varchar(100) NOT NULL DEFAULT '' AFTER `browser`",
            'where_at' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `where_at` varchar(50) NOT NULL DEFAULT 'frontend' AFTER `country`",
            'attempt_type' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `attempt_type` varchar(50) NOT NULL DEFAULT 'login' AFTER `user_agent`",
        ];

        try {
            $db->setQuery($this->getBlockedIpsCreateSql())->execute();
            $existing = [];

            foreach ($db->getTableColumns('#__loginguard_attempts') as $column => $type) {
                $existing[$column] = true;
            }

            foreach ($attemptColumns as $column => $sql) {
                if (!isset($existing[$column])) {
                    $db->setQuery($sql)->execute();
                }
            }
        } catch (Throwable $exception) {
            // Schema reconciliation must never block a Joomla login.
        }
    }

    private function getBlockedIpsCreateSql(): string
    {
        return "CREATE TABLE IF NOT EXISTS `#__loginguard_blocked_ips` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(255) NOT NULL DEFAULT '',
  `scope` varchar(20) NOT NULL DEFAULT 'all',
  `block_type` varchar(20) NOT NULL DEFAULT 'temporary',
  `reason` varchar(50) NOT NULL DEFAULT 'threshold_exceeded',
  `failure_count` int NOT NULL DEFAULT 0,
  `blocked_until` datetime NULL DEFAULT NULL,
  `created` datetime NOT NULL,
  `created_by` int NOT NULL DEFAULT 0,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_loginguard_blocked_ip` (`ip_address`),
  KEY `idx_loginguard_blocked_scope` (`scope`),
  KEY `idx_loginguard_blocked_until` (`blocked_until`),
  KEY `idx_loginguard_blocked_enabled` (`enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci";
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
        $entries = preg_split('/[\r\n,;\s]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($entries as $entry) {
            if ($this->ipMatchesRule($ipAddress, trim($entry))) {
                return true;
            }
        }

        return false;
    }

    private function ipMatchesRule(string $ipAddress, string $rule): bool
    {
        if ($rule === '') {
            return false;
        }

        if ($ipAddress === $rule) {
            return true;
        }

        if (!str_contains($rule, '/')) {
            return false;
        }

        [$network, $bits] = array_pad(explode('/', $rule, 2), 2, '');
        $bits = (int) $bits;
        $ipBinary = @inet_pton($ipAddress);
        $networkBinary = @inet_pton($network);

        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;

        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }

        if ($remainder === 0) {
            return true;
        }

        $mask = chr((0xff << (8 - $remainder)) & 0xff);

        return ($ipBinary[$bytes] & $mask) === ($networkBinary[$bytes] & $mask);
    }

    private function getActiveBlockForIp(string $ipAddress, string $client, DatabaseDriver $db): ?object
    {
        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return null;
        }

        $now = (new Date())->toSql();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
            ->where($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('scope') . ' IN (' . $this->quoteList($db, ['all', $client]) . ')')
            ->where(
                '('
                . $db->quoteName('block_type') . ' = ' . $db->quote('permanent')
                . ' OR (' . $db->quoteName('block_type') . ' = ' . $db->quote('temporary')
                . ' AND ' . $db->quoteName('blocked_until') . ' IS NOT NULL'
                . ' AND ' . $db->quoteName('blocked_until') . ' >= ' . $db->quote($now) . ')'
                . ')'
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
        $since = (new Date('-' . $windowMinutes . ' minutes'))->toSql();

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

            $now = new Date();
            $blockedUntil = (new Date('+' . $cooldownMinutes . ' minutes'))->toSql();
            $columns = ['ip_address', 'scope', 'block_type', 'reason', 'failure_count', 'blocked_until', 'created', 'created_by', 'enabled'];
            $values = [
                $db->quote($ipAddress),
                $db->quote((string) $params->get('automatic_block_scope', 'all')),
                $db->quote('temporary'),
                $db->quote('threshold_exceeded'),
                (string) $failureCount,
                $db->quote($blockedUntil),
                $db->quote($now->toSql()),
                '0',
                '1',
            ];

            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__loginguard_blocked_ips'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

            $db->setQuery($insert)->execute();
            $this->sendBlockedIpAlert($record + ['block_until' => $blockedUntil, 'failure_count' => $failureCount], null, $db);
        } catch (Throwable $exception) {
            // Automatic blocking must never interrupt audit logging.
        }
    }

    /**
     * Send an optional Joomla mail audit alert for the audited login event.
     *
     * @param   array<string, mixed>  $record
     */
    private function sendAuditAlert(array $record, DatabaseDriver $db): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $params = ComponentHelper::getParams('com_loginguard');

        if (!$params->get('audit_alerts_enabled', 0)) {
            return;
        }

        $status = (string) ($record['status'] ?? '');

        if ($status === 'SUCCESS_LOGIN' && !$params->get('audit_alert_success', 0)) {
            return;
        }

        if ($status === 'FAILED_LOGIN' && !$params->get('audit_alert_failed', 1)) {
            return;
        }

        if ($status === 'BLOCKED_LOGIN' && !$params->get('blocked_ip_alerts_enabled', 1)) {
            return;
        }

        if ($status === 'FAILED_LOGIN' && $this->isFailedAlertThrottled($record, $db)) {
            return;
        }

        $recipients = $this->normaliseAlertRecipients((string) $params->get('audit_alert_recipients', ''));

        if ($recipients === []) {
            return;
        }

        $isSuccess = $status === 'SUCCESS_LOGIN';
        $subjectTemplate = (string) $params->get(
            $isSuccess ? 'audit_alert_success_subject' : 'audit_alert_failed_subject',
            $isSuccess ? 'LoginGuard: successful login for {username}' : 'LoginGuard: failed login for {username}'
        );
        $bodyTemplate = (string) $params->get(
            $isSuccess ? 'audit_alert_success_body' : 'audit_alert_failed_body',
            $this->getDefaultAlertBodyTemplate()
        );

        $this->sendMail($recipients, $subjectTemplate, $bodyTemplate, $this->buildAlertTemplateVariables($record));
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
        $variables['block_until'] = (string) ($block->blocked_until ?? ($record['block_until'] ?? ''));
        $variables['failure_count'] = (string) ($block->failure_count ?? ($record['failure_count'] ?? ''));
        $variables['block_reason'] = (string) ($block->reason ?? 'threshold_exceeded');

        $subjectTemplate = (string) $params->get('blocked_ip_alert_subject', 'LoginGuard: blocked IP {ip}');
        $bodyTemplate = (string) $params->get('blocked_ip_alert_body', $this->getDefaultBlockedIpAlertBodyTemplate());

        $this->sendMail($recipients, $subjectTemplate, $bodyTemplate, $variables);
    }

    /** @param list<string> $recipients @param array<string, string> $variables */
    private function sendMail(array $recipients, string $subjectTemplate, string $bodyTemplate, array $variables): void
    {
        $subject = $this->replaceAlertTemplateVariables($subjectTemplate, $variables);
        $body = $this->replaceAlertTemplateVariables($bodyTemplate, $variables);

        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($recipients);
            $mailer->setSubject($subject);
            $mailer->setBody($body);
            $mailer->isHtml(false);
            $mailer->Send();
        } catch (Throwable $exception) {
            // Audit mail must never block the Joomla login flow.
        }
    }

    /** @param array<string, mixed> $record */
    private function isFailedAlertThrottled(array $record, DatabaseDriver $db): bool
    {
        $ipAddress = (string) ($record['ip_address'] ?? '');

        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return false;
        }

        $threshold = (new Date('-15 minutes'))->toSql();

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__loginguard_attempts'))
                ->where($db->quoteName('status') . ' = ' . $db->quote('FAILED_LOGIN'))
                ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($threshold));

            $db->setQuery($query);

            return (int) $db->loadResult() > 1;
        } catch (Throwable $exception) {
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
        $failureReason = $status === 'FAILED_LOGIN' || $status === 'BLOCKED_LOGIN' ? (string) ($record['reason'] ?? '') : '';

        return [
            'username' => (string) ($record['username'] ?? 'unknown'),
            'ip' => (string) ($record['ip_address'] ?? 'unknown'),
            'status' => $status,
            'failure_reason' => $failureReason,
            'where' => (string) ($record['where_at'] ?? 'frontend'),
            'browser' => (string) ($record['browser'] ?? 'unknown'),
            'os' => (string) ($record['operating_system'] ?? 'unknown'),
            'datetime' => (string) ($record['created'] ?? (new Date())->toSql()),
            'site_name' => (string) $config->get('sitename', ''),
            'country' => (string) ($record['country'] ?? ''),
            'country_code' => (string) ($record['country_code'] ?? ''),
            'region' => (string) ($record['region'] ?? ''),
            'city' => (string) ($record['city'] ?? ''),
            'isp' => (string) ($record['isp'] ?? ''),
            'asn' => (string) ($record['asn'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'full_name' => (string) ($record['name'] ?? ''),
            'email' => (string) ($record['email'] ?? ''),
            'user_agent' => (string) ($record['user_agent'] ?? ''),
        ];
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

    private function getDefaultAlertBodyTemplate(): string
    {
        return "LoginGuard recorded a {status} event on {site_name}.\n\nUsername: {username}\nFull name: {full_name}\nName: {name}\nEmail: {email}\nIP: {ip}\nCountry: {country}\nCountry code: {country_code}\nRegion: {region}\nCity: {city}\nISP: {isp}\nASN: {asn}\nWhere: {where}\nBrowser: {browser}\nOS: {os}\nUser agent: {user_agent}\nFailure reason: {failure_reason}\nDate/time: {datetime}";
    }

    private function getDefaultBlockedIpAlertBodyTemplate(): string
    {
        return "LoginGuard blocked IP {ip} on {site_name}.\n\nWhere: {where}\nBlock type: {block_type}\nBlock reason: {block_reason}\nBlocked until: {block_until}\nFailure count: {failure_count}\nCountry: {country}\nCountry code: {country_code}\nRegion: {region}\nCity: {city}\nISP: {isp}\nASN: {asn}\nDate/time: {datetime}";
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
        $type  = strtoupper((string) $this->readPayloadValue($payload, 'type', ''));

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

        return in_array($status, ['SUCCESS_LOGIN', 'FAILED_LOGIN', 'BLOCKED_LOGIN'], true) ? $status : 'FAILED_LOGIN';
    }

    private function normaliseFailureReason(string $reason): string
    {
        $reason = strtoupper(trim($reason));

        if ($reason === '') {
            return '';
        }

        return in_array($reason, ['USERNAME_NOT_FOUND', 'PASSWORD_INCORRECT', 'INVALID_CREDENTIALS', 'ACCOUNT_BLOCKED', 'ACCOUNT_DISABLED', 'IP_BLOCKED'], true)
            ? $reason
            : 'INVALID_CREDENTIALS';
    }

    /**
     * Resolve optional offline GeoIP fields from configured IP/CIDR rules.
     *
     * The map is intentionally local and deterministic so login enforcement never
     * depends on a remote telemetry service. Each line supports either the legacy
     * format `IP/CIDR=Country` or the extended v0.2.6 format
     * `IP/CIDR=Country|Country Code|Region|City|ISP|ASN`.
     *
     * @return array{country: string, country_code: string, region: string, city: string, isp: string, asn: string}
     */
    private function detectGeoIp(string $ipAddress): array
    {
        $empty = [
            'country' => '',
            'country_code' => '',
            'region' => '',
            'city' => '',
            'isp' => '',
            'asn' => '',
        ];
        $params = ComponentHelper::getParams('com_loginguard');

        if (!$params->get('geoip_enabled', 0) || $ipAddress === '' || $ipAddress === 'unknown') {
            return $empty;
        }

        $map = (string) $params->get('geoip_country_map', '');
        $entries = preg_split('/\R+/', $map, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($entries as $entry) {
            [$rule, $metadata] = array_pad(array_map('trim', explode('=', $entry, 2)), 2, '');

            if ($metadata === '' || !$this->ipMatchesRule($ipAddress, $rule)) {
                continue;
            }

            [$country, $countryCode, $region, $city, $isp, $asn] = array_pad(
                array_map('trim', explode('|', $metadata, 6)),
                6,
                ''
            );

            return [
                'country' => $country,
                'country_code' => strtoupper($countryCode),
                'region' => $region,
                'city' => $city,
                'isp' => $isp,
                'asn' => strtoupper($asn),
            ];
        }

        return $empty;
    }

    private function cleanString(string $value, string $fallback = ''): string
    {
        $value = trim($value);

        return $value === '' ? $fallback : $value;
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

    /** @param list<string> $values */
    private function quoteList(DatabaseDriver $db, array $values): string
    {
        return implode(',', array_map(static fn ($value) => $db->quote($value), $values));
    }
}
