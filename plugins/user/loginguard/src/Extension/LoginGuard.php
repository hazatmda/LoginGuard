<?php

namespace Joomla\Plugin\User\LoginGuard\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
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
            'user_id' => (int) $this->readPayloadValue($user, 'id', 0),
            'status' => 'success',
            'reason' => 'Login successful',
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
            'user_id' => 0,
            'status' => 'failed',
            'reason' => $this->readPayloadValue($payload, 'error_message', 'Login failed'),
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

        $app       = Factory::getApplication();
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $client    = $app->isClient('administrator') ? 'administrator' : 'site';

        $record = [
            'username' => $this->cleanString((string) ($attempt['username'] ?? 'unknown'), 'unknown'),
            'user_id' => (int) ($attempt['user_id'] ?? 0),
            'name' => $this->cleanString((string) ($attempt['name'] ?? '')),
            'status' => $this->cleanString((string) ($attempt['status'] ?? 'unknown'), 'unknown'),
            'ip_address' => $this->cleanString((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'unknown'),
            'user_agent' => $userAgent,
            'country' => '',
            'browser' => $this->detectBrowser($userAgent),
            'operating_system' => $this->detectOperatingSystem($userAgent),
            'client' => $client,
            'reason' => $this->cleanString((string) ($attempt['reason'] ?? '')),
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
            'country' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `country` varchar(100) NOT NULL DEFAULT '' AFTER `user_agent`",
            'browser' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `browser` varchar(100) NOT NULL DEFAULT '' AFTER `country`",
            'operating_system' => "ALTER TABLE `#__loginguard_attempts` ADD COLUMN `operating_system` varchar(100) NOT NULL DEFAULT '' AFTER `browser`",
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
