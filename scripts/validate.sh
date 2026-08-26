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

$proxyCases = [
    'spoofed_cloudflare_header' => [['REMOTE_ADDR' => '198.51.100.9', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7'], '192.0.2.0/24', 'cf-connecting-ip', '198.51.100.9'],
    'trusted_cloudflare_proxy' => [['REMOTE_ADDR' => '172.71.210.74', 'HTTP_CF_CONNECTING_IP' => '203.0.113.7'], '172.64.0.0/13', 'cf-connecting-ip', '203.0.113.7'],
    'trusted_ipv6_proxy' => [['REMOTE_ADDR' => '2001:db8:1::4', 'HTTP_CF_CONNECTING_IP' => '2001:db8:2::8'], '2001:db8:1::/48', 'cf-connecting-ip', '2001:db8:2::8'],
    'defensive_xff_chain' => [['REMOTE_ADDR' => '10.0.0.2', 'HTTP_X_FORWARDED_FOR' => '203.0.113.8, 10.0.0.1'], '10.0.0.0/8', 'x-forwarded-for', '203.0.113.8'],
    'invalid_forwarded_fallback' => [['REMOTE_ADDR' => '10.0.0.2', 'HTTP_CF_CONNECTING_IP' => 'invalid'], '10.0.0.0/8', 'cf-connecting-ip', '10.0.0.2'],
];
foreach ($proxyCases as $name => [$server, $trusted, $header, $expected]) {
    $actual = IpResolver::resolve($server, $trusted, $header);
    if ($actual !== $expected) { fwrite(STDERR, "$name expected $expected got $actual\n"); exit(1); }
}
foreach (['192.0.2.7', '2001:db8::7', '192.0.2.0/24', '2001:db8::/32'] as $rule) {
    $ip = str_contains($rule, ':') ? '2001:db8::7' : '192.0.2.7';
    if (!IpResolver::matchesRule($ip, $rule)) { fwrite(STDERR, "Whitelist rule failed: $rule\n"); exit(1); }
}
if (IpResolver::matchesRule('192.0.2.7', 'invalid/24')) { fwrite(STDERR, "Invalid whitelist matched\n"); exit(1); }

echo "IpResolver trusted proxy and whitelist validation completed successfully\n";
PHP

python3 - <<'PY'
from pathlib import Path
import xml.etree.ElementTree as ET

VERSION = Path('VERSION').read_text(encoding='utf-8').strip()
if VERSION != '0.2.23':
    raise SystemExit(f'Expected VERSION 0.2.23, got {VERSION}')
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
expected_info = f'https://github.com/hazatmda/LoginGuard/releases/tag/v{VERSION}'
expected_download = f'https://github.com/hazatmda/LoginGuard/releases/download/v{VERSION}/pkg_loginguard_v{VERSION}.zip'
if (update.findtext('infourl') or '').strip() != expected_info:
    raise SystemExit(f'Update information URL must equal {expected_info}')
if (update.findtext('./downloads/downloadurl') or '').strip() != expected_download:
    raise SystemExit(f'Update download URL must equal {expected_download}')
for token in ['<php_minimum>8.1.0</php_minimum>', 'version="5\\..*"']:
    if token not in update_text:
        raise SystemExit(f'Update stream missing: {token}')

plugin_manifest = roots[Path('plugins/user/loginguard/loginguard.xml')]
if plugin_manifest.findtext('./update/schemas/schemapath') != 'sql/updates/mysql':
    raise SystemExit('User plugin update schema path missing')
migration = Path(f'plugins/user/loginguard/sql/updates/mysql/{VERSION}.sql')
if not migration.is_file():
    raise SystemExit(f'Missing migration {migration}')

migration_text = Path('plugins/user/loginguard/sql/updates/mysql/0.2.21.sql').read_text(encoding='utf-8')
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
        raise SystemExit(f'v0.2.21 baseline migration missing {token}')

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
for token in ['matchesAnyRule', 'cf-connecting-ip', 'x-forwarded-for']:
    if token not in ip_resolver_text:
        raise SystemExit(f'IpResolver trusted proxy support missing {token}')

login_guard = Path('plugins/user/loginguard/src/Extension/LoginGuard.php').read_text(encoding='utf-8')
for forbidden in ['ensureSchema(', 'ALTER TABLE', 'CREATE TABLE IF NOT EXISTS']:
    if forbidden in login_guard:
        raise SystemExit(f'Authentication runtime contains schema DDL/reconciliation token: {forbidden}')
for token in [
    'IpResolver::resolve(',
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
    'IpResolver::resolve(',
    'mfa_automatic_blocking_enabled',
    'mfa_failed_attempt_threshold',
    "get('mfa_auditing_enabled', 1)",
    'INSERT IGNORE INTO',
]:
    if token not in mfa_plugin:
        raise SystemExit(f'MFA audit plugin missing: {token}')
for forbidden in ["get('code'", 'get("code"', "get('password'", 'get("password"', '$code =', '$mfaCode', '$mfa_code', '$password']:
    if forbidden in mfa_plugin:
        raise SystemExit(f'MFA audit plugin must never read/store passwords or MFA codes: {forbidden}')

# New captive event paths must stop before reading identity, request context,
# MFA method metadata, writing rows, blocking, or sending mail when auditing is off.
for method, next_method in [
    ('public function onCaptiveShown', 'public function onMfaFailed'),
    ('private function recordMfaEvent', 'private function isMfaAuditingEnabled'),
]:
    body = mfa_plugin[mfa_plugin.index(method):mfa_plugin.index(next_method, mfa_plugin.index(method))]
    gate = body.index('if (!$this->isMfaAuditingEnabled())')
    for side_effect in ['$this->getApplication()->getIdentity()', '$this->buildContext()', '$this->getMfaMethod(']:
        if side_effect in body and gate > body.index(side_effect):
            raise SystemExit(f'{method} reads MFA context before the master auditing gate')

# Success is the sole exception: with auditing off it may finalise only the
# exact session-owned pending row and send the already-deferred success mail.
success = mfa_plugin[mfa_plugin.index('public function onMfaSuccess'):mfa_plugin.index('private function recordMfaEvent')]
disabled = success[success.index('if (!$this->isMfaAuditingEnabled())'):success.index('return;', success.index('if (!$this->isMfaAuditingEnabled())'))]
for token in ['finalisePendingLogin(', 'sendFinalSuccessAlert(']:
    if token not in disabled:
        raise SystemExit(f'ON-to-OFF in-flight MFA completion missing {token}')
for forbidden in ['insertAttempt(', 'maybeAutoBlockMfa(', 'sendMfaFailureAlert(', 'recordHealth(']:
    if forbidden in disabled:
        raise SystemExit(f'Disabled in-flight completion introduced MFA side effect: {forbidden}')

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

# ON -> OFF after captive reclassification still completes the owned attempt;
# it creates no new MFA event and cannot touch a row owned by another session.
attempts = {301: 'MFA_PENDING', 302: 'MFA_PENDING'}
sessions = {'disabled_mid_flow': 301, 'other': 302}
complete_captive('disabled_mid_flow')
if attempts != {301: 'SUCCESS_LOGIN', 302: 'MFA_PENDING'} or 'disabled_mid_flow' in sessions:
    raise SystemExit('Disabled mid-flow MFA completion did not finalise only its owned row')

test_controller = Path('administrator/components/com_loginguard/src/Controller/TestemailController.php').read_text(encoding='utf-8')
test_field = Path('administrator/components/com_loginguard/src/Field/TestemailField.php').read_text(encoding='utf-8')
for token in ["checkToken('post')", "authorise('core.admin', 'com_loginguard')", "sendTestMail()", 'MailerFactoryInterface::class', "recordHealth($db, 'mail', 'degraded'"]:
    if token not in test_controller:
        raise SystemExit(f'Test-email controller security/health contract missing: {token}')
if Path('administrator/components/com_loginguard/src/Service/TestEmailService.php').exists():
    raise SystemExit('Parallel LoginGuard test email service must be removed')
if 'formmethod="post"' not in test_field or 'testemail.send' not in test_field:
    raise SystemExit('Test-email configuration control must submit the protected POST action')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ['contents: read', 'contents: write', "github.ref == 'refs/heads/main'", 'TAG="v${VERSION}"', 'test -f "packages/pkg_loginguard_v${VERSION}.zip"', 'packages/pkg_loginguard_v${{ env.VERSION }}.zip']:
    if token not in workflow:
        raise SystemExit(f'Release workflow contract missing: {token}')
if 'files: packages/*.zip' in workflow:
    raise SystemExit('Release workflow must never publish a wildcard package')

for token in ['hasCaptiveMfa(', "#__user_mfa", "(string) ($record['status'] ?? '') === 'SUCCESS_LOGIN'", 'shared pipeline sends this outcome only after MFA completes']:
    if token not in login_guard:
        raise SystemExit(f'Primary success-alert deferral missing: {token}')
audit_service = Path('administrator/components/com_loginguard/src/Service/AuditAlertService.php').read_text(encoding='utf-8')
mfa_plugin = Path('plugins/system/loginguardmfa/src/Extension/LoginGuardMfa.php').read_text(encoding='utf-8')
for token in ['AuditAlertService', 'buildAlertHtmlBody', 'formatConfiguredDateTime', 'mfa_method', 'mfa_status', 'mfa_reason']:
    if token not in audit_service:
        raise SystemExit(f'Shared audit alert pipeline missing: {token}')
if '(new AuditAlertService())->send' not in login_guard or '(new AuditAlertService())->send' not in mfa_plugin:
    raise SystemExit('Password and MFA outcomes must call the same audit alert service')
if 'sendConfiguredAuditAlert' in mfa_plugin or 'sendTemplatedAlert' in mfa_plugin:
    raise SystemExit('Parallel MFA audit-alert rendering pipeline must not exist')

config_text = Path('administrator/components/com_loginguard/config.xml').read_text(encoding='utf-8')
for token in [
    'mfa_automatic_blocking_enabled', 'mfa_failed_attempt_threshold', 'mfa_threshold_window_minutes',
    'mfa_cooldown_duration_minutes', 'mfa_alert_threshold',
]:
    if token not in config_text:
        raise SystemExit(f'MFA configuration missing {token}')
if 'name="mfa_auditing_enabled"' not in config_text or 'name="mfa_auditing_enabled" type="radio"' not in config_text:
    raise SystemExit('MFA master auditing switch is missing')
master_field = config_text[config_text.index('name="mfa_auditing_enabled"'):config_text.index('</field>', config_text.index('name="mfa_auditing_enabled"'))]
if 'default="1"' not in master_field:
    raise SystemExit('MFA auditing must default to enabled for upgrades')
for field in ['mfa_policy_note', 'mfa_automatic_blocking_enabled', 'mfa_failed_attempt_threshold',
              'mfa_threshold_window_minutes', 'mfa_cooldown_duration_minutes', 'mfa_alert_threshold']:
    field_text = config_text[config_text.index(f'name="{field}"'):config_text.index('/>', config_text.index(f'name="{field}"')) + 2]
    if 'mfa_auditing_enabled:1' not in field_text:
        raise SystemExit(f'MFA-specific setting is not gated by master switch: {field}')

# Behaviour model covers MFA and non-MFA users with auditing both on and off.
def primary_login(has_mfa, auditing_enabled):
    result = {'primary': 'SUCCESS_LOGIN', 'session_bound': auditing_enabled, 'success_alert_deferred': False,
              'mfa_rows': [], 'mfa_blocking': False, 'mfa_alerts': False}
    if auditing_enabled and has_mfa:
        result['primary'] = 'MFA_PENDING'
        result['success_alert_deferred'] = True
        result['mfa_rows'] = ['MFA_FAILED', 'MFA_TRY_LIMIT', 'MFA_SUCCESS']
        result['mfa_blocking'] = True
        result['mfa_alerts'] = True
    return result

for has_mfa in (False, True):
    off = primary_login(has_mfa, False)
    if off['primary'] != 'SUCCESS_LOGIN' or off['success_alert_deferred'] or off['mfa_rows'] or off['mfa_blocking'] or off['mfa_alerts']:
        raise SystemExit('Auditing-off login entered an MFA-specific path')
    on = primary_login(has_mfa, True)
    if has_mfa and (on['primary'] != 'MFA_PENDING' or on['mfa_rows'] != ['MFA_FAILED', 'MFA_TRY_LIMIT', 'MFA_SUCCESS']):
        raise SystemExit('Auditing-on MFA lifecycle regression')
    if not has_mfa and (on['primary'] != 'SUCCESS_LOGIN' or on['success_alert_deferred']):
        raise SystemExit('Non-MFA primary login behaviour changed')

if "if ($mfaAuditingEnabled && $record['status'] === 'SUCCESS_LOGIN'" not in login_guard:
    raise SystemExit('Primary attempt session binding is not gated by MFA auditing')
if "$params->get('mfa_auditing_enabled', 1)" not in login_guard:
    raise SystemExit('Primary successful-login alert deferral is not gated by MFA auditing')
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
for token in ["'0.2.23'", "'Joomla 5.2+'", "'PHP 8.1+'"]:
    if token not in about:
        raise SystemExit(f'About metadata missing {token}')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ["'8.1'", "'8.2'", "'8.3'", "'8.4'", 'contents: read', 'contents: write', 'codex/**']:
    if token not in workflow:
        raise SystemExit(f'CI hardening missing {token}')
if workflow.count('contents: write') != 1:
    raise SystemExit('CI write permission must be limited to the release publishing job')

print('LoginGuard v0.2.23 validation completed successfully')
PY
