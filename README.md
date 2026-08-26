# LoginGuard

Joomla 5 package for login-attempt auditing, IP enforcement, monitoring, and security operations.

Current development version: `0.2.25`.

## Features

- Records successful and failed primary Joomla logins without storing credentials.
- Resolves client IPs through explicitly trusted proxies, including defensive Cloudflare and X-Forwarded-For handling.
- Supports exact IPv4/IPv6 and CIDR whitelist rules.
- Enforces manual and automatic temporary IP blocks independently for frontend and administrator logins.
- Sends configurable Joomla mail alerts with failed-login throttling.
- Protects CSV exports from spreadsheet formula injection and records administrator actions.
- Runs bounded retention cleanup and reports runtime health without schema DDL in authentication paths.
- Caps attacker-controlled User-Agent telemetry at 2048 characters.

LoginGuard does not integrate with Joomla Multi-factor Authentication. Joomla core exclusively owns its setup, routing, validation, and session state. LoginGuard records an ordinary `SUCCESS_LOGIN` as soon as Joomla accepts the primary credentials.

## Build and validation

Requirements: Joomla 5.2+ and PHP 8.1+.

```bash
bash scripts/validate.sh
bash scripts/build.sh
```

The build produces `packages/pkg_loginguard_v0.2.25.zip`. Generated ZIP files are release-workflow artifacts and are intentionally not committed.

## Release metadata

```text
version: 0.2.25
tag: v0.2.25
package: pkg_loginguard_v0.2.25.zip
```

## Security invariants

- LoginGuard audit, health, and mail failures remain fail-open; intentional blocked-IP enforcement remains the only authentication denial.
- Passwords and authentication codes are never stored or logged.
- Client-controlled telemetry is bounded and output is escaped.
- Fresh-install schema changes happen only during installation or Joomla migrations.
- Test Email is intentionally absent; use Joomla Global Configuration for mail testing.
