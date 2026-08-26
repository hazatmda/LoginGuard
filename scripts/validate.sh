#!/usr/bin/env bash
set -euo pipefail

find plugins administrator pkg_loginguard -name "*.php" -exec php -l {} \;

php scripts/checks/mfa_provider_instantiation.php
php tests/joomla_login_event_aggregation.php
php tests/isolation_candidate_b.php

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
if VERSION != '0.2.24':
    raise SystemExit(f'Expected VERSION 0.2.24, got {VERSION}')
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

# Login observers must be passive: no result-returning legacy callbacks and no
# post-login session writes before Joomla completes captive MFA routing.
for forbidden in ['public function onUserLogin(', 'public function onUserLogout(',
                  'MFA_ATTEMPT_SESSION_KEY', 'pending_attempt.', 'getSession()->set(']:
    if forbidden in login_guard:
        raise SystemExit(f'User plugin can interfere with Joomla login/MFA lifecycle: {forbidden}')
for forbidden in ['com_users.mfa_checked', 'com_users.mandatory_mfa_setup',
                  'com_users.return_url', 'option=com_users&view=captive']:
    if forbidden in login_guard or forbidden in mfa_plugin:
        raise SystemExit(f'LoginGuard must not own Joomla MFA state/routing: {forbidden}')
for forbidden in ['ATTEMPT_SESSION_KEY', 'pending_attempt.', 'markPrimarySuccessPending(',
                  'finalisePendingLogin(']:
    if forbidden in mfa_plugin:
        raise SystemExit(f'MFA observer retains login-session correlation: {forbidden}')
if '$this->insertAttemptRecord($record, $db);' not in login_guard:
    raise SystemExit('Normal failed-login audit storage is missing')
subscription_method = mfa_plugin[mfa_plugin.index('public static function getSubscribedEvents'):mfa_plugin.index('public function onCaptiveShown')]
for captive_event, handler in {
    'onComUsersCaptiveShowCaptive': 'onCaptiveShown',
    'onComUsersCaptiveValidateFailed': 'onMfaFailed',
    'onComUsersCaptiveValidateTryLimitReached': 'onMfaTryLimitReached',
    'onComUsersCaptiveValidateInvalidMethod': 'onMfaInvalidMethod',
    'onComUsersCaptiveValidateSuccess': 'onMfaSuccess',
}.items():
    if f"'{captive_event}' => '{handler}'" not in subscription_method:
        raise SystemExit(f'Isolation Candidate B did not restore captive event: {captive_event}')
if 'insertAttempt(' not in mfa_plugin:
    raise SystemExit('MFA observer implementation is missing')

# LoginGuard test mail is intentionally absent; Joomla Global Configuration is
# the sole test-mail interface.
for removed in [
    Path('administrator/components/com_loginguard/src/Controller/TestemailController.php'),
    Path('administrator/components/com_loginguard/src/Field/TestemailField.php'),
]:
    if removed.exists():
        raise SystemExit(f'Removed Test Email artifact returned: {removed}')
config_text = Path('administrator/components/com_loginguard/config.xml').read_text(encoding='utf-8')
language_text = Path('administrator/components/com_loginguard/language/en-GB/en-GB.com_loginguard.ini').read_text(encoding='utf-8')
if 'testemail' in config_text.lower() or 'TEST_EMAIL' in language_text:
    raise SystemExit('LoginGuard Test Email UI or language remains')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ['contents: read', 'contents: write', "github.ref == 'refs/heads/main'", 'TAG="v${VERSION}"', 'test -f "packages/pkg_loginguard_v${VERSION}.zip"', 'packages/pkg_loginguard_v${{ env.VERSION }}.zip']:
    if token not in workflow:
        raise SystemExit(f'Release workflow contract missing: {token}')
if 'files: packages/*.zip' in workflow:
    raise SystemExit('Release workflow must never publish a wildcard package')

audit_service = Path('administrator/components/com_loginguard/src/Service/AuditAlertService.php').read_text(encoding='utf-8')
mfa_plugin = Path('plugins/system/loginguardmfa/src/Extension/LoginGuardMfa.php').read_text(encoding='utf-8')
for token in ['AuditAlertService', 'buildAlertHtmlBody', 'formatConfiguredDateTime', 'mfa_method', 'mfa_status', 'mfa_reason']:
    if token not in audit_service:
        raise SystemExit(f'Shared audit alert pipeline missing: {token}')
if '(new AuditAlertService())->send' not in login_guard or '(new AuditAlertService())->send' not in mfa_plugin:
    raise SystemExit('Password and MFA outcomes must call the same audit alert service')
after_login = login_guard[login_guard.index('public function onUserAfterLogin'):login_guard.index('public function onUserLoginFailure')]
for forbidden in ['normaliseEventPayload(', 'detectWhere(', 'storeAttempt(', 'getSession()',
                  'sendAuditAlert(', '#__user_mfa', 'ComponentHelper', 'IpResolver']:
    if forbidden in after_login:
        raise SystemExit(f'Isolation Candidate B post-login callback is not a no-op: {forbidden}')
store_attempt = login_guard[login_guard.index('private function storeAttempt'):login_guard.index('private function getDatabase')]
pending_guard = "if (($record['status'] ?? '') === 'MFA_PENDING')"
if pending_guard not in store_attempt:
    raise SystemExit('MFA_PENDING must be explicitly isolated as non-terminal telemetry')
pending_guard_offset = store_attempt.index(pending_guard)
for terminal_pipeline in ['maybeAutoBlockIp(', 'sendAuditAlert(']:
    if terminal_pipeline not in store_attempt[pending_guard_offset:]:
        raise SystemExit(f'MFA_PENDING guard must precede the terminal pipeline: {terminal_pipeline}')
    if terminal_pipeline in store_attempt[:pending_guard_offset]:
        raise SystemExit(f'MFA_PENDING must not enter the terminal pipeline: {terminal_pipeline}')
mfa_success = mfa_plugin[mfa_plugin.index('public function onMfaSuccess'):mfa_plugin.index('private function recordMfaEvent')]
for token in ["'MFA_SUCCESS'", "'SUCCESS_LOGIN'", 'sendSharedAuditAlert(']:
    if token not in mfa_success:
        raise SystemExit(f'MFA completion must deliver one normal success outcome: {token}')
for token in ["'MFA_SUCCESS',", "'MFA_COMPLETED'"]:
    if token not in mfa_success:
        raise SystemExit(f'Final Success Alert must retain captive MFA metadata: {token}')
if "if ($status === 'MFA_PENDING')" not in audit_service:
    raise SystemExit('Shared alert service must reject neutral MFA_PENDING telemetry defensively')
for token in ['MFA Method: {mfa_method}', 'MFA Status: {mfa_status}', 'MFA Result: {mfa_reason}']:
    if token not in audit_service or token.replace('\n', '&#10;') not in config_text:
        raise SystemExit(f'Default Success/Failed templates must expose MFA metadata: {token}')
for update in Path('plugins/user/loginguard/sql/updates/mysql').glob('*.sql'):
    if 'audit_alert_success_body' in update.read_text(encoding='utf-8') or 'audit_alert_failed_body' in update.read_text(encoding='utf-8'):
        raise SystemExit('Upgrades must preserve administrator-saved alert templates')

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
for token in ["'0.2.24'", "'Joomla 5.2+'", "'PHP 8.1+'"]:
    if token not in about:
        raise SystemExit(f'About metadata missing {token}')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ["'8.1'", "'8.2'", "'8.3'", "'8.4'", 'contents: read', 'contents: write', 'codex/**']:
    if token not in workflow:
        raise SystemExit(f'CI hardening missing {token}')
if workflow.count('contents: write') != 1:
    raise SystemExit('CI write permission must be limited to the release publishing job')

print('LoginGuard v0.2.24 validation completed successfully')
PY

php tests/mfa_template_compatibility.php
