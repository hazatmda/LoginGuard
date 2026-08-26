#!/usr/bin/env bash
set -euo pipefail

find plugins administrator pkg_loginguard -name "*.php" -exec php -l {} \;

php tests/joomla_login_event_aggregation.php
php tests/user_login_transparency.php
php tests/user_login_failure_event.php
php tests/no_mfa_integration.php
php tests/legacy_mfa_cleanup.php
php tests/ip_blocked_reason.php

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
if VERSION != '0.2.26':
    raise SystemExit(f'Expected VERSION 0.2.26, got {VERSION}')
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
for token in ['idx_loginguard_ip_status_created', 'active_key', '#__loginguard_admin_audit', '#__loginguard_health']:
    if token not in schema_text:
        raise SystemExit(f'Fresh install schema missing {token}')
if 'mfa_method' in schema_text:
    raise SystemExit('Fresh install schema retains retired MFA field')

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
    "'IP_BLOCKED'",
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

# Active MFA integration and legacy cleanup are covered by dedicated regressions above.

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
for token in ['AuditAlertService', 'buildAlertHtmlBody', 'formatConfiguredDateTime']:
    if token not in audit_service:
        raise SystemExit(f'Shared audit alert pipeline missing: {token}')
if 'bootComponent(' in login_guard:
    raise SystemExit('Authentication events must not boot a component or mutate routing state')
for forbidden in ['mfa_method', 'mfa_status', 'mfa_reason', 'MFA_PENDING']:
    if forbidden in audit_service or forbidden in config_text:
        raise SystemExit(f'Retired MFA alert surface remains: {forbidden}')
for update in Path('plugins/user/loginguard/sql/updates/mysql').glob('*.sql'):
    if 'audit_alert_success_body' in update.read_text(encoding='utf-8') or 'audit_alert_failed_body' in update.read_text(encoding='utf-8'):
        raise SystemExit('Upgrades must preserve administrator-saved alert templates')

# Critical retained UI references must always have MFA-free translations.
definitions = {line.split('=', 1)[0] for line in language_text.splitlines() if '=' in line}
critical_keys = {
    'COM_LOGINGUARD_CONFIG_WHITELISTED_IPS_DESC',
    'COM_LOGINGUARD_CONFIG_AUDIT_ALERT_TEMPLATE_DESC',
    'COM_LOGINGUARD_XML_DESCRIPTION',
    'COM_LOGINGUARD_FILTER_SEARCH_DESC',
    'COM_LOGINGUARD_STATUS_BANNER_PROTECTION_ACTIVE_DESC',
    'COM_LOGINGUARD_ABOUT_OPERATIONAL_GUIDANCE_DESC',
    'COM_LOGINGUARD_BLOCKEDIPS_POLICY_NOTE_DESC',
}
missing = critical_keys - definitions
if missing:
    raise SystemExit(f'Critical language definitions missing: {sorted(missing)}')
for line in language_text.splitlines():
    if line.split('=', 1)[0] in critical_keys and 'mfa' in line.lower():
        raise SystemExit(f'Critical non-MFA language definition mentions MFA: {line}')

for obsolete in ['name="trusted_proxies"', 'name="logging_level"', 'name="export_requires_permission"']:
    if obsolete in config_text:
        raise SystemExit(f'Unused/misleading configuration remains: {obsolete}')

attempts_controller = Path('administrator/components/com_loginguard/src/Controller/AttemptsController.php').read_text(encoding='utf-8')
for token in ["requirePermission('loginguard.export')", 'checkToken()', 'sanitiseCsvCell', "['=', '+', '-', '@']", 'AUDIT_EXPORTED']:
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
for token in ['loadHealthStatus']:
    if token not in dashboard_view:
        raise SystemExit(f'Dashboard view missing {token}')
for token in ['COM_LOGINGUARD_DASHBOARD_SYSTEM_HEALTH']:
    if token not in dashboard_template:
        raise SystemExit(f'Dashboard template missing {token}')

package_text = Path('pkg_loginguard/pkg_loginguard.xml').read_text(encoding='utf-8')
package_script = Path('pkg_loginguard/script.php').read_text(encoding='utf-8')
build_text = Path('scripts/build.sh').read_text(encoding='utf-8')
for token in ['plg_user_loginguard.zip', 'plg_task_loginguardcleanup.zip', 'com_loginguard.zip']:
    if token not in build_text:
        raise SystemExit(f'Build script missing {token}')
if 'plg_system_loginguardmfa.zip' in package_text or 'plg_system_loginguardmfa.zip' in build_text:
    raise SystemExit('Retired System MFA plugin remains in package/build inputs')
for token in ["'loginguardmfa', 'system'", 'removeLegacyMfaPlugin', "uninstall('plugin',"]:
    if token not in package_script:
        raise SystemExit(f'Legacy MFA cleanup contract missing: {token}')

readme = Path('README.md').read_text(encoding='utf-8')
for token in ['Joomla 5.2+', 'PHP 8.1+', f'pkg_loginguard_v{VERSION}.zip', 'REMOTE_ADDR']:
    if token not in readme:
        raise SystemExit(f'README missing {token}')

about = Path('administrator/components/com_loginguard/tmpl/about/default.php').read_text(encoding='utf-8')
for token in ["'0.2.26'", "'Joomla 5.2+'", "'PHP 8.1+'"]:
    if token not in about:
        raise SystemExit(f'About metadata missing {token}')

workflow = Path('.github/workflows/build.yml').read_text(encoding='utf-8')
for token in ["'8.1'", "'8.2'", "'8.3'", "'8.4'", 'contents: read', 'contents: write', 'codex/**']:
    if token not in workflow:
        raise SystemExit(f'CI hardening missing {token}')
if workflow.count('contents: write') != 1:
    raise SystemExit('CI write permission must be limited to the release publishing job')

print('LoginGuard v0.2.26 validation completed successfully')
PY
