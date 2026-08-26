<?php

namespace Joomla\Plugin\User\LoginGuard\Service;

defined('_JEXEC') or die;

/**
 * Resolves a validated client address, trusting forwarding data only from an
 * explicitly configured immediate proxy.
 */
final class IpResolver
{
    private const UNKNOWN_IP = 'unknown';

    /**
     * @param   array<string, mixed>|null  $server  Optional server array for tests; defaults to $_SERVER.
     */
    public static function resolve(?array $server = null, string $trustedProxies = '', string $header = 'none'): string
    {
        $server ??= $_SERVER;

        if (!array_key_exists('REMOTE_ADDR', $server) || !is_scalar($server['REMOTE_ADDR'])) {
            return self::UNKNOWN_IP;
        }

        $remoteAddr = trim((string) $server['REMOTE_ADDR']);

        if ($remoteAddr === '' || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return self::UNKNOWN_IP;
        }

        if (!self::matchesAnyRule($remoteAddr, $trustedProxies)) {
            return $remoteAddr;
        }

        $headerName = match ($header) {
            'cf-connecting-ip' => 'HTTP_CF_CONNECTING_IP',
            'x-forwarded-for' => 'HTTP_X_FORWARDED_FOR',
            default => '',
        };
        if ($headerName === '' || !isset($server[$headerName]) || !is_scalar($server[$headerName])) {
            return $remoteAddr;
        }

        $value = trim((string) $server[$headerName]);
        if ($header === 'x-forwarded-for') {
            // Walk right-to-left, discarding only configured proxies. This does
            // not trust an attacker-supplied left-most value in a proxy chain.
            $chain = array_map('trim', explode(',', $value));
            $chain[] = $remoteAddr;
            for ($index = count($chain) - 1; $index >= 0; --$index) {
                $candidate = $chain[$index];
                if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                    return $remoteAddr;
                }
                if (!self::matchesAnyRule($candidate, $trustedProxies)) {
                    return $candidate;
                }
            }
            return $remoteAddr;
        }

        return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : $remoteAddr;
    }

    public static function matchesAnyRule(string $ipAddress, string $configured): bool
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $rules = preg_split('/[\r\n,;\s]+/', $configured, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($rules as $rule) {
            if (self::matchesRule($ipAddress, trim($rule))) {
                return true;
            }
        }
        return false;
    }

    public static function matchesRule(string $ipAddress, string $rule): bool
    {
        if (filter_var($ipAddress, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (filter_var($rule, FILTER_VALIDATE_IP) !== false) {
            return inet_pton($ipAddress) === inet_pton($rule);
        }
        if (!str_contains($rule, '/')) {
            return false;
        }
        [$network, $prefix] = array_pad(explode('/', $rule, 2), 2, '');
        $ip = @inet_pton($ipAddress);
        $net = @inet_pton($network);
        if ($ip === false || $net === false || strlen($ip) !== strlen($net) || $prefix === '' || !ctype_digit($prefix)) {
            return false;
        }
        $bits = (int) $prefix;
        if ($bits < 0 || $bits > strlen($ip) * 8) {
            return false;
        }
        $bytes = intdiv($bits, 8);
        $remainder = $bits % 8;
        if ($bytes && substr($ip, 0, $bytes) !== substr($net, 0, $bytes)) {
            return false;
        }
        if (!$remainder) {
            return true;
        }
        $mask = chr((0xff << (8 - $remainder)) & 0xff);
        return ($ip[$bytes] & $mask) === ($net[$bytes] & $mask);
    }
}
