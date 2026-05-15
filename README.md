# LoginGuard

Joomla 5 package for login attempt detection, monitoring, and auditing.

## Status

Current development version: `0.2.14-alpha`.

## Features planned for MVP

- Detect successful Joomla login attempts
- Detect failed Joomla login attempts
- Store login attempt audit records
- Capture proxy-aware IP address, name, username, status, failure reason, where, country, country code, region, city, ISP, ASN, browser, operating system, user agent, and datetime without storing plaintext passwords
- Provide a Joomla administrator component with Dashboard telemetry, Login Information, Configuration, Blocked IPs, and About navigation
- Send optional Joomla mail audit alerts for successful and failed login events using Joomla Global Configuration mail settings
- Publish Joomla update server metadata for package update discovery with direct release ZIP URLs
- Integrate Joomla-native ACL permissions and component configuration through access.xml and com_config
- Search, filter, sort, and paginate login attempt audit records while keeping Login Information as the full audit table
- Run scheduled retention cleanup in bounded batches for old login attempts and stale blocked-IP records
- Generate an installable Joomla package ZIP from GitHub Actions

## GeoIP Enrichment

LoginGuard keeps GeoIP enrichment deterministic and offline. Enable GeoIP in the component configuration and enter one mapping per line:

```text
IP-or-CIDR=Country
IP-or-CIDR=Country|Country Code|Region|City|ISP|ASN
```

The extended format stores `country`, `country_code`, `region`, `city`, `isp`, and `asn`. The Login Information table displays Country, City, ISP, and ASN, and CSV export includes all GeoIP fields.

## Requirements

- Joomla 5.2+
- PHP 8.1+
- MySQL/MariaDB supported by Joomla 5

## Repository Structure

```text
.github/workflows/build.yml               GitHub Actions validation and package artifact workflow
administrator/components/com_loginguard/  Joomla administrator component source
pkg_loginguard/                           Joomla package manifest source
plugins/user/loginguard/                  Joomla user plugin source
plugins/task/loginguardcleanup/            Joomla Scheduler cleanup task plugin source
scripts/build.sh                          Local package build script
scripts/validate.sh                       Local validation script
packages/                                 Generated ZIP output directory, ignored by Git
updates/                                  Joomla extension update stream metadata
VERSION                                   Canonical project version
CHANGELOG.md                              Release notes
```

## Build Package Locally

```bash
bash scripts/validate.sh
bash scripts/build.sh
```

Generated package:

```text
packages/pkg_loginguard_v0.2.14-alpha.zip
```

## Versioning Policy

Before release, these must match:

- `VERSION`
- plugin manifest `<version>`
- component manifest `<version>`
- package manifest `<version>`
- package filename
- release tag
- release notes

Example:

```text
version: 0.2.14-alpha
tag: v0.2.14-alpha
package: pkg_loginguard_v0.2.14-alpha.zip
```

## License

GNU General Public License v3.0.
