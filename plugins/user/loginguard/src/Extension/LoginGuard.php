<?php

namespace Joomla\Plugin\User\LoginGuard\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
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
     * Log a successful Joomla login.
     *
     * Joomla user events may provide the authenticated user as an array, an object,
     * or a Joomla\CMS\User\User instance depending on the dispatcher path. Keep
     * this handler intentionally defensive so runtime payload changes do not break
     * the login flow.
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

    /**
     * Keep Joomla logout handling safe and side-effect free.
     *
     * @param   mixed  $user     Joomla logout user payload.
     * @param   mixed  $options  Joomla logout options payload.
     *
     * @return  bool
     */
    public function onUserLogout($user = [], $options = []): bool
    {
        return true;
    }

    /**
     * Support dispatchers that call the post-logout hook.
     *
     * @param   mixed  $options  Joomla logout options payload.
     */
    public function onUserAfterLogout($options = []): void
    {
        // LoginGuard only audits login attempts; logout must never interrupt Joomla.
    }

    /**
     * @param   array<string, mixed>  $attempt
     */
    private function storeAttempt(array $attempt): void
    {
        try {
            $db = Factory::getContainer()->get(DatabaseDriver::class);
        } catch (Throwable $exception) {
            try {
                $db = Factory::getContainer()->get('DatabaseDriver');
            } catch (Throwable $containerException) {
                $db = Factory::getDbo();
            }
        }

        $this->ensureSchema($db);

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $client    = $this->detectWhere();

        $record = [
            'username' => $this->cleanString((string) ($attempt['username'] ?? 'unknown'), 'unknown'),
            'user_id' => (int) ($attempt['user_id'] ?? 0),
            'name' => $this->cleanString((string) ($attempt['name'] ?? '')),
            'email' => $this->cleanString((string) ($attempt['email'] ?? '')),
            'status' => $this->normaliseStatus((string) ($attempt['status'] ?? 'FAILED_LOGIN')),
            'ip_address' => $this->cleanString(IpResolver::resolve(), 'unknown'),
            'user_agent' => $userAgent,
            'country' => '',
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'where_at' => $client,
            'client' => $client,
            'attempt_type' => 'login',
            'reason' => $this->normaliseFailureReason((string) ($attempt['reason'] ?? '')),
            'created' => (new Date())->toSql(),
        ];

        $columns = array_keys($record);
        $values  = [];

        foreach ($record as $column => $value) {
            $values[] = $column === 'user_id' ? (string) (int) $value : $db->quote((string) $value);
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__loginguard_attempts'))
            ->columns($db->quoteName($columns))
            ->values(implode(',', $values));

        $db->setQuery($query);
        $db->execute();
    }

    private function ensureSchema(DatabaseDriver $db): void
    {
        $columns = [
            'name' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `name` varchar(255) NOT NULL DEFAULT '' AFTER `user_id`",
            'email' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `email` varchar(255) NOT NULL DEFAULT '' AFTER `username`",
            'country' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country` varchar(100) NOT NULL DEFAULT '' AFTER `user_agent`",
            'browser' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `browser` varchar(100) NOT NULL DEFAULT '' AFTER `country`",
            'operating_system' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `operating_system` varchar(100) NOT NULL DEFAULT '' AFTER `browser`",
            'where_at' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `where_at` varchar(50) NOT NULL DEFAULT 'frontend' AFTER `country`",
            'attempt_type' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `attempt_type` varchar(50) NOT NULL DEFAULT 'login' AFTER `user_agent`",
        ];

        $existing = [];

        try {
            foreach ($db->getTableColumns('#__loginguard_attempts') as $column => $type) {
                $existing[$column] = true;
            }
        } catch (Throwable $exception) {
            return;
        }

        foreach ($columns as $column => $sql) {
            if (isset($existing[$column])) {
                continue;
            }

            try {
                $db->setQuery($sql)->execute();
            } catch (Throwable $exception) {
                // Another request may have added it first; keep login non-blocking.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
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


    /**
     * Determine a safe failure reason without storing passwords or exposing more certainty than Joomla provides.
     *
     * @param   array<string, mixed>  $payload
     */
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

        return in_array($status, ['SUCCESS_LOGIN', 'FAILED_LOGIN'], true) ? $status : 'FAILED_LOGIN';
    }

    private function normaliseFailureReason(string $reason): string
    {
        $reason = strtoupper(trim($reason));

        if ($reason === '') {
            return '';
        }

        return in_array($reason, ['USERNAME_NOT_FOUND', 'PASSWORD_INCORRECT', 'INVALID_CREDENTIALS', 'ACCOUNT_BLOCKED', 'ACCOUNT_DISABLED'], true)
            ? $reason
            : 'INVALID_CREDENTIALS';
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
}
