<?php

namespace Joomla\Plugin\User\LoginGuard\Service;

defined('_JEXEC') or die;

/**
 * Resolves the client IP address already established by the web server / PHP.
 *
 * LoginGuard intentionally does not trust request-supplied forwarding headers.
 * Deployments behind Cloudflare, a load balancer, or another reverse proxy must
 * configure the web server's trusted-proxy / real-IP module so REMOTE_ADDR is
 * rewritten to the verified originating client before Joomla executes.
 */
final class IpResolver
{
    private const UNKNOWN_IP = 'unknown';

    /**
     * @param   array<string, mixed>|null  $server  Optional server array for tests; defaults to $_SERVER.
     */
    public static function resolve(?array $server = null): string
    {
        $server ??= $_SERVER;

        if (!array_key_exists('REMOTE_ADDR', $server) || !is_scalar($server['REMOTE_ADDR'])) {
            return self::UNKNOWN_IP;
        }

        $remoteAddr = trim((string) $server['REMOTE_ADDR']);

        if ($remoteAddr === '' || filter_var($remoteAddr, FILTER_VALIDATE_IP) === false) {
            return self::UNKNOWN_IP;
        }

        return $remoteAddr;
    }
}
