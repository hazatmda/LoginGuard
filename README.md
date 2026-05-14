# LoginGuard

Joomla 5 package for login attempt detection, monitoring, and auditing.

## Status

Current development version: `0.1.6-alpha`.

## Features planned for MVP

- Detect successful Joomla login attempts
- Detect failed Joomla login attempts
- Store login attempt audit records
- Capture IP address, name, username, status, datetime, country, browser, operating system, and where without storing plaintext passwords
- Provide an administrator component for viewing login attempt audit records
- Search, filter, sort, and paginate login attempt audit records
- Generate an installable Joomla package ZIP from GitHub Actions

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
scripts/build.sh                          Local package build script
scripts/validate.sh                       Local validation script
packages/                                 Generated ZIP output directory, ignored by Git
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
packages/pkg_loginguard_v0.1.6-alpha.zip
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
version: 0.1.6-alpha
tag: v0.1.6-alpha
package: pkg_loginguard_v0.1.6-alpha.zip
```

## License

GNU General Public License v3.0.
