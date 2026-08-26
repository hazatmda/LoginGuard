# LoginGuard

Joomla 5 package for login attempt detection, MFA-aware auditing, IP enforcement, monitoring, and security operations.

## Status

Current development version: `0.2.24`.

## Core capabilities

- Record successful Joomla login attempts
- Record failed username/password login attempts
- Record blocked login attempts
- Audit Joomla captive Multi-factor Authentication outcomes without storing MFA codes
- Treat frontend/backend primary authentication as `MFA_PENDING` under this site's mandatory-MFA policy and finalize `SUCCESS_LOGIN` only after Joomla emits captive success
- Capture server-established `REMOTE_ADDR`, name, username, status, failure reason, location metadata, browser, operating system, user agent, and UTC datetime without storing plaintext passwords
- Provide Dashboard telemetry, Login Information, Configuration, Blocked IPs, and About views
- Apply manual and threshold-based IP enforcement with whitelist support
- Use separate configurable MFA-failure blocking thresholds
- Send optional Joomla mail alerts for login, block, and MFA security events
- Run scheduled bounded retention cleanup
- Export complete audit data with spreadsheet-formula protection applied only at export time
- Record administrator block-management and audit-export actions
- Surface runtime health/degraded states for database, enforcement, MFA, mail and cleanup operations
- Publish Joomla update-server metadata from the component lifecycle
- Integrate Joomla-native ACL permissions
- Generate an installable package ZIP from GitHub Actions

## Client IP policy

LoginGuard uses validated `REMOTE_ADDR` by default. When Joomla is behind Cloudflare or another reverse proxy, administrators may configure exact IPv4/IPv6 addresses or CIDRs for the immediate trusted proxy and select `CF-Connecting-IP` or defensively parsed `X-Forwarded-For`. Forwarded headers from any untrusted peer are ignored, malformed values fall back safely, and X-Forwarded-For is walked from the immediate peer toward the client while discarding only configured proxies.

The existing `whitelisted_ips` list accepts exact IPv4/IPv6 and CIDR rules. Matching resolved client addresses are still recorded in the audit log but never receive or enforce LoginGuard password/MFA automatic blocks. Invalid rules never match.

## MFA auditing

LoginGuard listens to Joomla captive MFA events. It records MFA state and method metadata, but never records the MFA code itself.

Typical sequence:

```text
SUCCESS primary authentication
        -> MFA_PENDING
        -> MFA_FAILED / MFA_TRY_LIMIT (when applicable)
        -> MFA_SUCCESS
        -> SUCCESS_LOGIN finalized
```

For this mandatory-MFA deployment, every frontend/backend primary-authentication event remains pending, including first-time MFA setup where no active method row exists. LoginGuard does not inspect or mutate Joomla MFA session/routing state and does not redirect. API and CLI authentication cannot enter Joomla's interactive captive flow, so those clients continue to record `SUCCESS_LOGIN` immediately.

`MFA_PENDING` is neutral telemetry only: it is excluded from success and failed alerts, throttling, automatic blocking, and threshold notifications. Captive success records optional `MFA_SUCCESS` telemetry plus exactly one final `SUCCESS_LOGIN`, and uses the shared Success Alert with MFA metadata. Captive failures use the shared Failed Alert with the same MFA variables.

## Network-origin telemetry in 0.2.24

GeoIP enrichment is deferred and is not included in 0.2.24. LoginGuard records only the trusted-proxy-aware public client IP for network origin; it performs no PHP GeoIP, MaxMind/MMDB, or configured-map lookup and does not derive country, region, city, ISP, or ASN data. Previously saved alert templates are normalized narrowly at render time to remove retired GeoIP rows and placeholders without resetting other custom text.

Existing GeoIP database columns and historical values are retained. Joomla's MySQL update files are declarative SQL, and the MySQL/MariaDB versions supported by Joomla do not share a portable conditional `DROP COLUMN` form. An unconditional drop could fail an upgrade on a drifted schema, so v0.2.24 deliberately avoids that destructive migration.

## Requirements

- Joomla 5.2+
- PHP 8.1+
- MySQL/MariaDB supported by Joomla 5

## Repository structure

```text
.github/workflows/build.yml                 validation and package workflow
administrator/components/com_loginguard/    administrator component
pkg_loginguard/                             package manifest / lifecycle script
plugins/user/loginguard/                    login audit and enforcement plugin
plugins/system/loginguardmfa/               Joomla captive MFA audit plugin
plugins/task/loginguardcleanup/              scheduled retention cleanup plugin
scripts/build.sh                            package build script
scripts/validate.sh                         repository validation script
packages/                                   generated ZIP output, ignored by Git
updates/                                    Joomla extension update stream
VERSION                                     canonical version
CHANGELOG.md                                release notes
```

## Build package locally

```bash
bash scripts/validate.sh
bash scripts/build.sh
```

Generated package:

```text
packages/pkg_loginguard_v0.2.24.zip
```

## Versioning policy

Before release, these must match:

- `VERSION`
- plugin manifests
- component manifest
- package manifest
- update stream
- package filename
- release tag
- release notes

```text
version: 0.2.24
tag: v0.2.24
package: pkg_loginguard_v0.2.24.zip
```

### Stable release sequence

1. Obtain a clean PR review and green GitHub CI validation on PHP 8.1, 8.2, 8.3, and 8.4.
2. Merge the validated v0.2.24 version to `main`.
3. GitHub Actions validates and builds the merged commit, then automatically creates the exact `v${VERSION}` release if it does not already exist.
4. The workflow attaches the exact `pkg_loginguard_v${VERSION}.zip` package to that release.
5. The workflow verifies that the Joomla updater can retrieve the exact release asset referenced by `updates/loginguard.xml`.

The release job intentionally fails before upload if the release tag, canonical
version, update-stream URLs, or exact package asset do not agree. Only that job
has `contents: write`; ordinary validation and build jobs remain read-only.

## Security principles

- Never store passwords or MFA codes.
- Keep source telemetry in the database; sanitise only presentation/export contexts where required.
- Keep LoginGuard internal failures fail-open for Joomla authentication to avoid site lockout, while making failures observable through Joomla logs and dashboard health.
- Perform schema changes during install/update migrations, never in the login request path.

## License

GNU General Public License v3.0.
