# LoginGuard

Joomla 5 package for login attempt detection, MFA-aware auditing, IP enforcement, monitoring, and security operations.

## Status

Current development version: `0.2.22`.

## Core capabilities

- Record successful Joomla login attempts
- Record failed username/password login attempts
- Record blocked login attempts
- Audit Joomla captive Multi-factor Authentication outcomes without storing MFA codes
- Reclassify primary-auth success as `MFA_PENDING` when captive MFA is required and finalize `SUCCESS_LOGIN` only after MFA succeeds
- Capture server-established `REMOTE_ADDR`, name, username, status, failure reason, location metadata, browser, operating system, user agent, and UTC datetime without storing plaintext passwords
- Provide Dashboard telemetry, Login Information, Configuration, Blocked IPs, and About views
- Apply manual and threshold-based IP enforcement with whitelist support
- Use separate configurable MFA-failure blocking thresholds
- Send optional Joomla mail alerts for login, block, and MFA security events
- Send an administrator-triggered, fixed diagnostic email to saved alert recipients without creating security telemetry
- Run scheduled bounded retention cleanup
- Export complete audit data with spreadsheet-formula protection applied only at export time
- Record administrator block-management and audit-export actions
- Surface runtime health/degraded states for database, enforcement, MFA, mail, GeoIP, and cleanup operations
- Publish Joomla update-server metadata from the component lifecycle
- Integrate Joomla-native ACL permissions
- Generate an installable package ZIP from GitHub Actions

## Client IP policy

LoginGuard trusts only the client address already established by the web server / PHP in `REMOTE_ADDR`. It does **not** trust request-supplied `CF-Connecting-IP`, `X-Forwarded-For`, or `X-Real-IP` headers.

If Joomla is behind Cloudflare, a reverse proxy, or a load balancer, configure that trusted proxy at the web-server layer so `REMOTE_ADDR` is rewritten to the verified public client IP before Joomla runs.

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

Users without a captive MFA requirement continue to be recorded as `SUCCESS_LOGIN` normally.

## GeoIP enrichment

LoginGuard automatically enriches login telemetry when a local GeoIP capability is available. No remote lookup is required during authentication. The plugin detects PHP GeoIP functions, common local MaxMind database locations, and legacy offline maps from upgraded installations. If no local provider is available, LoginGuard stores empty location fields while preserving the audit flow.

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
packages/pkg_loginguard_v0.2.22.zip
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
version: 0.2.22
tag: v0.2.22
package: pkg_loginguard_v0.2.22.zip
```

### Stable release sequence

1. Obtain a clean PR review and green GitHub CI validation on PHP 8.1, 8.2, 8.3, and 8.4.
2. Merge v0.2.22 to `main`.
3. Create and publish a GitHub Release with the exact tag `v0.2.22`, targeting the merged `main` commit.
4. The release workflow verifies that the tag is exactly `v${VERSION}`, rebuilds, checks, and attaches only `pkg_loginguard_v0.2.22.zip`.
5. Verify that the release asset URL in `updates/loginguard.xml` is available for Joomla downloads.

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
