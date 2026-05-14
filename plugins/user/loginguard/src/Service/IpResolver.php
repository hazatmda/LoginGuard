<?php

namespace Joomla\Plugin\User\LoginGuard\Service;

defined('_JEXEC') or die;

/**
 * Resolves the real client IP address from trusted proxy headers and server data.
 */
final class IpResolver
{
    private const UNKNOWN_IP = 'unknown';

    /**
     * Resolve the best client IP address for the current request.
     *
     * Header priority is intentionally centralized here so login logging does not
     * duplicate proxy parsing or fall back to localhost-only REMOTE_ADDR values
     * when a valid public proxy header is available.
     *
     * @param   array<string, mixed>|null  $server  Optional server array for tests; defaults to $_SERVER.
     */
    public static function resolve(?array $server = null): string
    {
        $server ??= $_SERVER;

        $cloudflareIp = self::readHeader($server, 'HTTP_CF_CONNECTING_IP');
        if ($cloudflareIp !== null && self::isPublicIp($cloudflareIp)) {
            return $cloudflareIp;
        }

        $forwardedFor = self::readHeader($server, 'HTTP_X_FORWARDED_FOR');
        if ($forwardedFor !== null) {
            foreach (explode(',', $forwardedFor) as $candidate) {
                $candidate = self::normaliseIp($candidate);

                if ($candidate !== null && self::isPublicIp($candidate)) {
                    return $candidate;
                }
            }
        }

        $realIp = self::readHeader($server, 'HTTP_X_REAL_IP');
        if ($realIp !== null && self::isPublicIp($realIp)) {
            return $realIp;
        }

        $remoteAddr = self::readHeader($server, 'REMOTE_ADDR');
        if ($remoteAddr !== null && self::isValidIp($remoteAddr)) {
            return $remoteAddr;
        }

        return self::UNKNOWN_IP;
    }

    /**
     * @param   array<string, mixed>  $server
     */
    private static function readHeader(array $server, string $key): ?string
    {
        if (!array_key_exists($key, $server) || !is_scalar($server[$key])) {
            return null;
        }

        return self::normaliseIp((string) $server[$key]);
    }

    private static function normaliseIp(string $ip): ?string
    {
        $ip = trim($ip);

        return $ip === '' ? null : $ip;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private static function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
}
