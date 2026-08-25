#!/usr/bin/env bash
set -euo pipefail

find plugins administrator pkg_loginguard -name "*.php" -exec php -l {} \;

php scripts/checks/mfa_provider_instantiation.php

php <<'PHP'
<?php
define('_JEXEC', 1);
require 'plugins/user/loginguard/src/Service/IpResolver.php';

use Joomla\Plugin\User\LoginGuard\Service\IpResolver;

$cases = [
    'public_remote_addr' => [['REMOTE_ADDR' => '203.0.113.10'], '203.0.113.10'],
    'private_remote_addr' => [['REMOTE_ADDR' => '10.10.10.20'], '10.10.10.20'],
    'ipv6_remote_addr' => [['REMOTE_ADDR' => '2001:db8::10'], '2001:db8::10'],
    'forwarded_headers_are_not_trusted' => [[
        'REMOTE_ADDR' => '192.0.2.50',
        'HTTP_CF_CONNECTING_IP' => '1.1.1.1',
        'HTTP_X_FORWARDED_FOR' => '8.8.8.8',
        'HTTP_X_REAL_IP' => '9.9.9.9',
    ], '192.0.2.50'],
    'invalid_remote_addr' => [['REMOTE_ADDR' => 'not-an-ip'], 'unknown'],
    'missing_remote_addr' => [[], 'unknown'],
];

foreach ($cases as $name => [$server, $expected]) {
    $actual = IpResolver::resolve($server);
    if ($actual !== $expected) {
        fwrite(STDERR, "$name expected $expected got $actual\n");
        exit(1);
    }
}

echo "IpResolver REMOTE_ADDR validation completed successfully\n";
PHP

python3 - <<'PY'
from pathlib import Path
import xml.etree.ElementTree as ET

VERSION = Path('VERSION').read_text(encoding='utf-8').strip()
if VERSION != '0.2.21':
    raise SystemExit(f'Expected VERSION 0.2.21, got {VERSION}')
if '-' in VERSION:
    raise SystemExit('Canonical release version must be stable semantic version')

xml_files = [
    *Path('plugins').rglob('*.xml'),
    *Path('administrator').rglob('*.xml'),
    *Path('pkg_loginguard').rglob('*.xml'),
    *Path('updates').rglob('*.xml'),
]
roots = {}
for xml_file in xml_files:
    try:
        roots[xml_file] = ET.parse(xml_file).getroot()
    except ET.ParseError as exc:
        raise SystemExit(f'Invalid XML in {xml_file}: {exc}')

versioned = {
    Path('plugins/user/loginguard/loginguard.xml'),
    Path('plugins/system/loginguardmfa/loginguardmfa.xml'),
    Path('plugins/task/loginguardcleanup/loginguardcleanup.xml'),
    Path('administrator/components/com_loginguard/loginguard.xml'),
    Path('pkg_loginguard/pkg_loginguard.xml'),
}
for path in versioned:
    root = roots[path]
    actual = (root.findtext('version') or '').strip()
    if actual != VERSION:
        raise SystemExit(f'Version mismatch: {path}={actual}, VERSION={VERSION}')

update_root = roots[Path('updates/loginguard.xml')]
update = update_root.find('update')
if update is None or (update.findtext('version') or '').strip() != VERSION:
    raise SystemExit('Update stream version is not synchronized')
update_text = Path('updates/loginguard.xml').read_text(encoding='utf-8')
for token in [f'/v{VERSION}/', f'pkg_loginguard_v{VERSION}.zip', '<php_minimum>8.1.0</php_minimum>', 'version="5\\..*"']:
    if token not in update_text:
        raise SystemExit(f'Update stream missing: {token}')

plugin_manifest = roots[Path('plugins/user/loginguard/loginguard.xml')]
if plugin_manifest.findtext('./update/schemas/schemapath') != 'sql/updates/mysql':
    raise SystemExit('User plugin update schema path missing')
migration = Path(f'plugins/user/loginguard/sql/updates/mysql/{VERSION}.sql')
if not migration.is_file():
    raise SystemExit(f'Missing migration {migration}')

migration_text = migration.read_text(encoding='utf-8')
for token in [
    'mfa_method',
    'idx_loginguard_ip_status_created',
    'idx_loginguard_user_status_created',
    'source',
    'active_key',
    'idx_loginguard_active_key',
    'updated_by',
    'disabled_at',
    '#__loginguard_admin_audit',
    '#__loginguard_health',
]:
    if token not in migration_text:
        raise SystemExit(f'v0.2.21 migration missing {token}')

schema_text = Path('plugins/user/loginguard/sql/install.mysql.utf8.sql').read_text(encoding='utf-8')
for token in ['mfa_method', 'idx_loginguard_ip_status_created', 'active_key', '#__loginguard_admin_audit', '#__loginguard_health']:
    if token not in schema_text:
        raise SystemExit(f'Fresh install schema missing {token}')

installer_text = Path('plugins/user/loginguard/script.php').read_text(encoding='utf-8')
for forbidden in ['ALTER TABLE', 'CREATE TABLE IF NOT EXISTS', 'ensureSchema(']:
    if forbidden in installer_text:
        raise SystemExit(f'Installer PHP must delegate schema changes to Joomla SQL lifecycle: {forbidden}')

ip_resolver_text = Path('plugins/user/loginguard/src/Service/IpResolver.php').read_text(encoding='utf-8')
if 'REMOTE_ADDR' not in ip_resolver_text or 'FILTER_VALIDATE_IP' not in ip_resolver_text:
    raise SystemExit('IpResolver must validate REMOTE_ADDR')
for forbidden in ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP']:
    if forbidden in ip_resolver_text:
        raise SystemExit(f'IpResolver must not trust {forbidden}')

login_guard = Path('plugins/user/loginguard/src/Extension/LoginGuard.php').read_text(encoding='utf-8')
for forbidden in ['ensureSchema(', 'ALTER TABLE', 'CREATE TABLE IF NOT EXISTS']:
    if forbidden in login_guard:
        raise SystemExit(f'Authentication runtime contains schema DDL/reconciliation token: {forbidden}')
for token in [
    'IpResolver::resolve()',
    'INSERT IGNORE INTO',
    'active_key',
    "'source'",
    'MAX_USER_AGENT = 2048',
    'Log::add(',
    'recordHealth(',
    'MFA_PENDING',
    'MFA_FAILED',
    'MFA_SUCCESS',
    'MFA_TRY_LIMIT',
]:
    if token not in login_guard:
        raise SystemExit(f'Core LoginGuard missing hardening token: {token}')

# An expired manual temporary block must release its active key before the
# threshold-triggered automatic INSERT IGNORE is attempted.
expiry_start = login_guard.index('// Release the uniqueness key from every expired row')
insert_start = login_guard.index('INSERT IGNORE INTO', expiry_start)
expiry_end = login_guard.index('$db->setQuery($expireQuery)->execute()', expiry_start)
if expiry_end > insert_start:
    raise SystemExit('Expired-block release must execute before automatic block insertion')
expiry_query = login_guard[expiry_start:expiry_end]
for token in ["'enabled'", "'block_type'", "'temporary'", "'blocked_until'", "'active_key'"]:
    if token not in expiry_query:
        raise SystemExit(f'Expired-block release regression check missing {token}')
if "'source'" in expiry_query:
    raise SystemExit('Expired manual temporary blocks must be released before automatic blocking')

mfa_plugin = Path('plugins/system/loginguardmfa/src/Extension/LoginGuardMfa.php').read_text(encoding='utf-8')
for token in [
    'onComUsersCaptiveShowCaptive',
    'onComUsersCaptiveValidateFailed',
    'onComUsersCaptiveValidateTryLimitReached',
    'onComUsersCaptiveValidateInvalidMethod',
    'onComUsersCaptiveValidateSuccess',
    'MFA_PENDING',
    'MFA_FAILED',
    'MFA_SUCCESS',
    'MFA_TRY_LIMIT',
    "#__user_mfa",
    'REMOTE_ADDR',
    'mfa_automatic_blocking_enabled',
    'mfa_failed_attempt_threshold',
    'INSERT IGNORE INTO',
]:
    if token not in mfa_plugin:
        raise SystemExit(f'MFA audit plugin missing: {token}')
for forbidden in ["get('code'", 'get("code"', '$code =', '$mfaCode', '$mfa_code']:
    if forbidden in mfa_plugin:
        raise SystemExit(f'MFA audit plugin must never read/store MFA codes: {forbidden}')

# The primary login path must bind its newly inserted attempt to the Joomla
# session. Captive MFA may only reclassify and finalise that exact row.
for token in ['MFA_ATTEMPT_SESSION_KEY', '$attemptId = $this->insertAttemptRecord', 'insertid()', 'set(', '$attemptId']:
    if token not in login_guard:
        raise SystemExit(f'Primary pending-attempt association missing: {token}')
for token in ['ATTEMPT_SESSION_KEY', 'get($sessionKey, 0)', 'clear($sessionKey)']:
    if token not in mfa_plugin:
        raise SystemExit(f'MFA pending-attempt association missing: {token}')
pending_start = mfa_plugin.index('private function markPrimarySuccessPending')
pending_end = mfa_plugin.index('private function finalisePendingLogin', pending_start)
pending_code = mfa_plugin[pending_start:pending_end]
for token in [
    '$id = (int) $session->get($sessionKey, 0)',
    "->where($db->quoteName('id') . ' = ' . (string) $id)",
    "->where($db->quoteName('user_id') . ' = ' . (string) $userId)",
    "->where($db->quoteName('status') . ' = ' . $db->quote('SUCCESS_LOGIN'))",
]:
    if token not in pending_code:
        raise SystemExit(f'Exact captive-attempt reclassification missing: {token}')
for forbidden in ["gmdate('Y-m-d H:i:s', time() - 600)", "order($db->quoteName('created')"]:
    if forbidden in pending_code:
        raise SystemExit(f'Captive flow must not infer a primary attempt: {forbidden}')

# Regression model: concurrent sessions retain independent attempt ownership;
# refreshes are idempotent and abandoning one flow cannot affect the other.
attempts = {101: 'SUCCESS_LOGIN', 102: 'SUCCESS_LOGIN'}
sessions = {'first': 101, 'second': 102}
def show_captive(session):
    attempt_id = sessions.get(session, 0)
    if attempts.get(attempt_id) == 'SUCCESS_LOGIN':
        attempts[attempt_id] = 'MFA_PENDING'
def complete_captive(session):
    attempt_id = sessions.get(session, 0)
    if attempts.get(attempt_id) == 'MFA_PENDING':
        attempts[attempt_id] = 'SUCCESS_LOGIN'
        sessions.pop(session, None)
show_captive('first')
show_captive('second')
show_captive('first')
if attempts != {101: 'MFA_PENDING', 102: 'MFA_PENDING'}:
    raise SystemExit('Concurrent captive flows or captive refresh are not isolated')
complete_captive('second')
if attempts != {101: 'MFA_PENDING', 102: 'SUCCESS_LOGIN'}:
    raise SystemExit('A captive flow finalised an attempt owned by another session')
sessions.pop('first')
if attempts[102] != 'SUCCESS_LOGIN' or attempts[101] != 'MFA_PENDING':
    raise SystemExit('Abandoning one captive flow changed another flow')

# Normal single-session MFA still transitions pending to final exactly once.
attempts = {201: 'SUCCESS_LOGIN'}
sessions = {'single': 201}
show_captive('single')
complete_captive('single')
complete_captive('single')
if attempts != {201: 'SUCCESS_LOGIN'} or 'single' in sessions:
    raise SystemExit('Single-session captive MFA finalisation is not idempotent')

for token in ['hasCaptiveMfa(', "#__user_mfa", "status === 'SUCCESS_LOGIN'", 'The MFA system plugin sends the single final success alert']:
    if token not in login_guard:
        raise SystemExit(f'Primary success-alert deferral missing: {token}')
defer_start = login_guard.index("if ($status === 'SUCCESS_LOGIN' && $this->hasCaptiveMfa")
audit_success_check = login_guard.index("if ($status === 'SUCCESS_LOGIN' && !$params->get('audit_alert_success'", defer_start)
if defer_start > audit_success_check:
    raise SystemExit('Captive MFA success alert must be suppressed before primary success mail handling')

config_text = Path('administrator/components/com_loginguard/config.xml').read_text(encoding='utf-8')
for token in [
    'mfa_automatic_blocking_enabled', 'mfa_failed_attempt_threshold', 'mfa_threshold_window_minutes',
    'mfa_cooldown_duration_minutes', 'mfa_alert_failed', 'mfa_alert_threshold',
]:
    if token not in config_text:
        raise SystemExit(f'MFA configuration missing {token}')
for obsolete in ['name="trusted_proxies"', 'name="logging_level"', 'name="export_requires_permission"']:
    if obsolete in config_text:
        raise SystemExit(f'Unused/misleading configuration remains: {obsolete}')

attempts_controller = Path('administrator/components/com_loginguard/src/Controller/AttemptsController.php').read_text(encoding='utf-8')
for token in ["requirePermission('loginguard.export')", 'checkToken()', 'sanitiseCsvCell', "['=', '+', '-', '@']", 'AUDIT_EXPORTED', 'MFA Method']:
    if token not in attempts_controller:
        raise SystemExit(f'CSV/export hardening missing {token}')

blocked_controller = Path('administrator/components/com_loginguard/src/Controller/BlockedipsController.php').read_text(encoding='utf-8')
for token in ['OperationalAudit::recordAdminAction', 'BLOCK_CREATED', 'BLOCK_UPDATED', 'BLOCK_DISABLED', 'BLOCK_UNBLOCKED', 'active_key', 'disabled_at']:
    if token not in blocked_controller:
        raise SystemExit(f'Blocked IP lifecycle hardening missing {token}')
if '->delete($db->quoteName(\'#__loginguard_blocked_ips\'))' in blocked_controller:
    raise SystemExit('Normal blocked-IP controller flow must not hard-delete security history')

cleanup = Path('administrator/components/com_loginguard/src/Service/CleanupService.php').read_text(encoding='utf-8')
for token in ['disabled_at', 'OperationalAudit::recordHealth', 'OperationalAudit::logFailure', 'MAX_BATCHES_PER_RUN']:
    if token not in cleanup:
        raise SystemExit(f'Cleanup hardening missing {token}')
cleanup_catch = cleanup.index('} catch (Throwable $exception) {')
cleanup_return = cleanup.index('return $metrics;', cleanup_catch)
cleanup_failure = cleanup[cleanup_catch:cleanup_return]
if 'throw $exception;' not in cleanup_failure:
    raise SystemExit('Cleanup failures must propagate to scheduler and manual callers')

dashboard_view = Path('administrator/components/com_loginguard/src/View/Dashboard/HtmlView.php').read_text(encoding='utf-8')
dashboard_template = Path('administrator/components/com_loginguard/tmpl/dashboard/default.php').read_text(encoding='utf-8')
for token in ['loadHealthStatus', 'loadMfaTelemetry']:
    if token not in dashboard_view:
        raise SystemExit(f'Dashboard view missing {token}')
for token in ['COM_LOGINGUARD_DASHBOARD_SYSTEM_HEALTH', 'COM_LOGINGUARD_DASHBOARD_MFA_TELEMETRY', 'MFA_FAILED']:
    if token not in dashboard_template:
        raise SystemExit(f'Dashboard template missing {token}')

attempts_model = Path('administrator/components/com_loginguard/src/Model/AttemptsModel.php').read_text(encoding='utf-8')
attempts_view = Path('administrator/components/com_loginguard/src/View/Attempts/HtmlView.php').read_text(encoding='utf-8')
filter_text = Path('administrator/components/com_loginguard/forms/filter_attempts.xml').read_text(encoding='utf-8')
for token in ['mfa_method', 'attempt_type']:
    if token not in attempts_model or token not in attempts_view:
        raise SystemExit(f'Login Information missing {token}')
for status in ['MFA_PENDING', 'MFA_FAILED', 'MFA_SUCCESS', 'MFA_TRY_LIMIT']:
    if status not in filter_text:
        raise SystemExit(f'Login Information filter missing {status}')

package_text = Path('pkg_loginguard/pkg_loginguard.xml').read_text(encoding='utf-8')
package_script = Path('pkg_loginguard/script.php').read_text(encoding='utf-8')
build_text = Path('scripts/build.sh').read_text(encoding='utf-8')
for token in ['plg_user_loginguard.zip', 'plg_system_loginguardmfa.zip', 'plg_task_loginguardcleanup.zip', 'com_loginguard.zip']:
    if token not in build_text:
        raise SystemExit(f'Build script missing {token}')
if '<file type="plugin" id="loginguardmfa" group="system">plg_system_loginguardmfa.zip</file>' not in package_text:
    raise SystemExit('Package manifest missing MFA child plugin')
for token in ["'element' => 'loginguardmfa'", "'folder' => 'system'", "enableChildExtension('plugin', 'loginguardmfa', 'system')"]:
    if token not in package_script:
        raise SystemExit(f'Package lifecycle missing MFA plugin handling: {token}')

readme = Path('README.md').read_text(encoding='utf-8')
for token in ['Joomla 5.2+', 'PHP 8.1+', f'pkg_loginguard_v{VERSION}.zip', 'REMOTE_ADDR', 'never records the MFA code']:
    if token not in readme:
        raise SystemExit(f'README missing {token}')

about = Path('administrator/components/com_loginguard/tmpl/about/default.php').read_text(encoding='utf-8')
for token in ["'0.2.21'", "'Joomla 5.2+'", "'PHP 8.1+'"]:
    if token not in about:
        raise SystemExit(f'About metadata missing {token}')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ["'8.1'", "'8.2'", "'8.3'", "'8.4'", 'contents: read', 'contents: write', 'codex/**']:
    if token not in workflow:
        raise SystemExit(f'CI hardening missing {token}')
if workflow.count('contents: write') != 1:
    raise SystemExit('CI write permission must be limited to the release publishing job')

print('LoginGuard v0.2.21 validation completed successfully')
PY
