#!/usr/bin/env bash
set -euo pipefail

php tests/joomla_login_event_aggregation.php
php tests/no_mfa_integration.php
php tests/legacy_mfa_cleanup.php

while IFS= read -r -d '' file; do php -l "$file" >/dev/null; done < <(find administrator plugins pkg_loginguard tests -name '*.php' -type f -print0)

python3 - <<'PY'
from pathlib import Path
import xml.etree.ElementTree as ET
version=Path('VERSION').read_text().strip()
if version!='0.2.25': raise SystemExit(f'Expected VERSION 0.2.25, got {version}')
for p in Path('.').rglob('*.xml'): ET.parse(p)
for p in [Path('pkg_loginguard/pkg_loginguard.xml'),Path('plugins/user/loginguard/loginguard.xml'),Path('plugins/task/loginguardcleanup/loginguardcleanup.xml'),Path('administrator/components/com_loginguard/loginguard.xml'),Path('updates/loginguard.xml')]:
    if version not in p.read_text(): raise SystemExit(f'Version missing from {p}')
manifest=Path('pkg_loginguard/pkg_loginguard.xml').read_text()
for token in ['plg_user_loginguard.zip','plg_task_loginguardcleanup.zip','com_loginguard.zip']:
    if token not in manifest: raise SystemExit(f'Package child missing: {token}')
if 'plg_system_loginguardmfa.zip' in manifest: raise SystemExit('Retired MFA plugin remains packaged')
build=Path('scripts/build.sh').read_text()
if 'loginguardmfa' in build: raise SystemExit('Build still includes retired MFA plugin')
install=Path('plugins/user/loginguard/sql/install.mysql.utf8.sql').read_text()
if 'mfa_method' in install: raise SystemExit('Fresh schema still creates MFA-only column')
plugin=Path('plugins/user/loginguard/src/Extension/LoginGuard.php').read_text()
for token in ['IpResolver::resolve','MAX_USER_AGENT = 2048','maybeAutoBlockIp','AuditAlertService','onUserAuthorisation']:
    if token not in plugin: raise SystemExit(f'Non-MFA hardening missing: {token}')
for token in ['getSession()->set(', 'pending_attempt.', 'MFA_ATTEMPT_SESSION_KEY']:
    if token in plugin: raise SystemExit(f'Forbidden session correlation remains: {token}')
controller=Path('administrator/components/com_loginguard/src/Controller/AttemptsController.php').read_text()
for token in ["requirePermission('loginguard.export')", 'checkToken()', 'sanitiseCsvCell', "['=', '+', '-', '@']", 'AUDIT_EXPORTED']:
    if token not in controller: raise SystemExit(f'CSV/admin audit hardening missing: {token}')
print('LoginGuard v0.2.25 validation completed successfully')
PY
