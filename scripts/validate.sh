#!/usr/bin/env bash
set -euo pipefail

find plugins administrator -name "*.php" -exec php -l {} \;


php <<'PHP'
<?php
define('_JEXEC', 1);
require 'plugins/user/loginguard/src/Service/IpResolver.php';

use Joomla\Plugin\User\LoginGuard\Service\IpResolver;

$cases = [
    'cloudflare_ipv4_priority' => [
        ['HTTP_CF_CONNECTING_IP' => '1.1.1.1', 'HTTP_X_FORWARDED_FOR' => '8.8.8.8', 'REMOTE_ADDR' => '127.0.0.1'],
        '1.1.1.1',
    ],
    'proxy_x_forwarded_for_first_public' => [
        ['HTTP_X_FORWARDED_FOR' => 'malformed, 10.0.0.5, 8.8.8.8, 1.1.1.1', 'REMOTE_ADDR' => '127.0.0.1'],
        '8.8.8.8',
    ],
    'x_real_ip_fallback' => [
        ['HTTP_CF_CONNECTING_IP' => 'bad', 'HTTP_X_FORWARDED_FOR' => '10.0.0.1, nope', 'HTTP_X_REAL_IP' => '9.9.9.9', 'REMOTE_ADDR' => '127.0.0.1'],
        '9.9.9.9',
    ],
    'localhost_remote_fallback' => [
        ['HTTP_CF_CONNECTING_IP' => 'bad', 'REMOTE_ADDR' => '127.0.0.1'],
        '127.0.0.1',
    ],
    'docker_remote_fallback' => [
        ['HTTP_X_FORWARDED_FOR' => '172.17.0.2', 'REMOTE_ADDR' => '172.17.0.1'],
        '172.17.0.1',
    ],
    'ipv6_public_proxy' => [
        ['HTTP_X_FORWARDED_FOR' => 'fd00::1, 2606:4700:4700::1111', 'REMOTE_ADDR' => '::1'],
        '2606:4700:4700::1111',
    ],
    'ipv6_localhost_fallback' => [
        ['HTTP_X_REAL_IP' => 'fd00::1', 'REMOTE_ADDR' => '::1'],
        '::1',
    ],
    'invalid_values_unknown' => [
        ['HTTP_CF_CONNECTING_IP' => '1.2.3.4.5', 'HTTP_X_FORWARDED_FOR' => 'bad', 'HTTP_X_REAL_IP' => 'also-bad', 'REMOTE_ADDR' => 'nope'],
        'unknown',
    ],
];

foreach ($cases as $name => [$server, $expected]) {
    $actual = IpResolver::resolve($server);
    if ($actual !== $expected) {
        fwrite(STDERR, "$name expected $expected got $actual\n");
        exit(1);
    }
}

echo "IpResolver validation completed successfully\n";
PHP

python3 - <<'PY'
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

xml_files = [
    *Path('plugins').rglob('*.xml'),
    *Path('administrator').rglob('*.xml'),
    *Path('pkg_loginguard').rglob('*.xml'),
    *Path('updates').rglob('*.xml'),
]

versions = {'VERSION': Path('VERSION').read_text(encoding='utf-8').strip()}
roots = {}
for xml_file in xml_files:
    try:
        root = ET.parse(xml_file).getroot()
    except ET.ParseError as exc:
        print(f'Invalid XML in {xml_file}: {exc}', file=sys.stderr)
        sys.exit(1)

    roots[xml_file] = root
    version = root.findtext('version')
    if version is not None:
        versions[str(xml_file)] = version.strip()

mismatched = {path: version for path, version in versions.items() if version != versions['VERSION']}
if mismatched:
    details = ', '.join(f'{path}={version}' for path, version in versions.items())
    print(f'Version mismatch: {details}', file=sys.stderr)
    sys.exit(1)

plugin_manifest = Path('plugins/user/loginguard/loginguard.xml')
plugin_root = roots.get(plugin_manifest)
if plugin_root is None:
    print(f'Missing plugin manifest: {plugin_manifest}', file=sys.stderr)
    sys.exit(1)

expected_sql = {
    './install/sql/file': 'sql/install.mysql.utf8.sql',
    './uninstall/sql/file': 'sql/uninstall.mysql.utf8.sql',
}

for xpath, relative_path in expected_sql.items():
    node = plugin_root.find(xpath)
    if node is None or (node.text or '').strip() != relative_path:
        print(f'Plugin manifest missing SQL mapping {xpath} -> {relative_path}', file=sys.stderr)
        sys.exit(1)
    if not (plugin_manifest.parent / relative_path).is_file():
        print(f'Plugin SQL file missing: {plugin_manifest.parent / relative_path}', file=sys.stderr)
        sys.exit(1)

schema_path = plugin_root.findtext('./update/schemas/schemapath')
if schema_path != 'sql/updates/mysql':
    print('Plugin manifest missing update schema path sql/updates/mysql', file=sys.stderr)
    sys.exit(1)

if not (plugin_manifest.parent / schema_path / f"{versions['VERSION']}.sql").is_file():
    print(f"Missing update migration for {versions['VERSION']}", file=sys.stderr)
    sys.exit(1)

ip_resolver = Path('plugins/user/loginguard/src/Service/IpResolver.php')
login_guard = Path('plugins/user/loginguard/src/Extension/LoginGuard.php')
if not ip_resolver.is_file():
    print('Missing centralized IpResolver service', file=sys.stderr)
    sys.exit(1)

ip_resolver_text = ip_resolver.read_text(encoding='utf-8')
for required_text in ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR', 'FILTER_VALIDATE_IP', 'FILTER_FLAG_NO_PRIV_RANGE', 'FILTER_FLAG_NO_RES_RANGE']:
    if required_text not in ip_resolver_text:
        print(f'IpResolver missing proxy-aware validation token: {required_text}', file=sys.stderr)
        sys.exit(1)

login_guard_text = login_guard.read_text(encoding='utf-8')
if 'IpResolver::resolve()' not in login_guard_text:
    print('Login logging must use IpResolver::resolve()', file=sys.stderr)
    sys.exit(1)
for required_text in ["ComponentHelper::getParams('com_loginguard')", 'Factory::getMailer()', 'audit_alerts_enabled', 'audit_alert_success', 'audit_alert_failed', 'audit_alert_recipients', 'audit_alert_success_subject', 'audit_alert_success_body', 'audit_alert_failed_subject', 'audit_alert_failed_body', 'isFailedAlertThrottled', 'normaliseAlertRecipients']:
    if required_text not in login_guard_text:
        print(f'LoginGuard extension missing audit alert support: {required_text}', file=sys.stderr)
        sys.exit(1)

for forbidden_text in ['enqueueMessage', 'audit_alert_clients']:
    if forbidden_text in login_guard_text:
        print(f'LoginGuard extension must send mail alerts instead of onscreen alert support: {forbidden_text}', file=sys.stderr)
        sys.exit(1)
for template_variable in ['{username}', '{ip}', '{status}', '{failure_reason}', '{where}', '{browser}', '{os}', '{datetime}', '{site_name}']:
    if template_variable not in login_guard_text:
        print(f'LoginGuard extension missing alert template variable: {template_variable}', file=sys.stderr)
        sys.exit(1)
if "$_SERVER['REMOTE_ADDR']" in login_guard_text or '$_SERVER["REMOTE_ADDR"]' in login_guard_text:
    print('LoginGuard extension must not read REMOTE_ADDR directly', file=sys.stderr)
    sys.exit(1)

component_view = Path('administrator/components/com_loginguard/src/View/Attempts/HtmlView.php')
component_model = Path('administrator/components/com_loginguard/src/Model/AttemptsModel.php')
component_template = Path('administrator/components/com_loginguard/tmpl/attempts/default.php')
component_filter = Path('administrator/components/com_loginguard/forms/filter_attempts.xml')

for required_file in [component_view, component_model, component_template, component_filter]:
    if not required_file.is_file():
        print(f'Missing administrator MVC/ListView file: {required_file}', file=sys.stderr)
        sys.exit(1)

template_text = component_template.read_text(encoding='utf-8')
if "LayoutHelper::render('joomla.searchtools.default'" not in template_text:
    print('Attempts template must render Joomla SearchTools through LayoutHelper', file=sys.stderr)
    sys.exit(1)
if "HTMLHelper::_('searchtools.default'" in template_text:
    print('Attempts template must not call the non-layout searchtools.default HTML helper', file=sys.stderr)
    sys.exit(1)
if "HTMLHelper::_('searchtools.sort'" not in template_text:
    print('Attempts template must use Joomla SearchTools sorting helpers', file=sys.stderr)
    sys.exit(1)
if "getListFooter()" not in template_text:
    print('Attempts template missing pagination footer rendering', file=sys.stderr)
    sys.exit(1)

view_text = component_view.read_text(encoding='utf-8')
for required_state in ['FilterForm', 'ActiveFilters', 'Pagination', 'Items', 'ToolbarHelper::title']:
    if required_state not in view_text:
        print(f'Attempts HtmlView missing {required_state} wiring', file=sys.stderr)
        sys.exit(1)

model_text = component_model.read_text(encoding='utf-8')
for required_state in ["'filter.search'", "'filter.status'", "'filter.where_at'", "'list.ordering'", "'list.direction'"]:
    if required_state not in model_text:
        print(f'Attempts ListModel missing state handling for {required_state}', file=sys.stderr)
        sys.exit(1)


attempts_controller_text = Path('administrator/components/com_loginguard/src/Controller/AttemptsController.php').read_text(encoding='utf-8')
for required_text in ["requirePermission('loginguard.export')", 'checkToken()', "['ignore_request' => false]", 'getExportRows', 'Content-Type', 'charset=UTF-8', 'Content-Disposition', 'Content-Transfer-Encoding', 'fputcsv', 'fwrite($output']:
    if required_text not in attempts_controller_text:
        print(f'Attempts export missing required routing/header/encoding token: {required_text}', file=sys.stderr)
        sys.exit(1)
for required_text in ['getExportRows', 'getListQuery()', 'whereIn', 'loadAssocList']:
    if required_text not in model_text:
        print(f'Attempts model missing export filter/selected-row support: {required_text}', file=sys.stderr)
        sys.exit(1)
if "ToolbarHelper::custom('attempts.export'" not in view_text:
    print('Attempts toolbar must route export through Joomla toolbar form task submission', file=sys.stderr)
    sys.exit(1)

filter_root = roots.get(component_filter)
if filter_root is None:
    print(f'Missing attempts filter XML: {component_filter}', file=sys.stderr)
    sys.exit(1)
filter_fields = {field.attrib.get('name') for field in filter_root.findall("./fields[@name='filter']/field")}
list_fields = {field.attrib.get('name') for field in filter_root.findall("./fields[@name='list']/field")}
if not {'search', 'status', 'where_at'}.issubset(filter_fields):
    print('Attempts filter XML missing search/status/where_at filters', file=sys.stderr)
    sys.exit(1)
if not {'fullordering', 'limit'}.issubset(list_fields):
    print('Attempts filter XML missing SearchTools ordering or limit fields', file=sys.stderr)
    sys.exit(1)


component_manifest = Path('administrator/components/com_loginguard/loginguard.xml')
component_root = roots.get(component_manifest)
component_manifest_text = component_manifest.read_text(encoding='utf-8')
for required_file in ['access.xml', 'config.xml']:
    if f'<filename>{required_file}</filename>' not in component_manifest_text or not (component_manifest.parent / required_file).is_file():
        print(f'Component manifest missing Joomla-native {required_file}', file=sys.stderr)
        sys.exit(1)
component_menu = component_root.find('./administration/menu') if component_root is not None else None
if component_menu is None or component_menu.attrib.get('view') == 'attempts':
    print('Component root menu must be present and must not route to attempts', file=sys.stderr)
    sys.exit(1)
if component_menu.attrib.get('link') != 'option=com_loginguard&view=dashboard':
    print('Component root menu must route to the dashboard view for stable active menu behavior', file=sys.stderr)
    sys.exit(1)
submenu_root = component_root.find('./administration/submenu') if component_root is not None else None
if submenu_root is None:
    print('Component manifest missing Joomla-native administrator submenu registration', file=sys.stderr)
    sys.exit(1)
expected_submenus = [
    ('COM_LOGINGUARD_SUBMENU_DASHBOARD', {'view': 'dashboard'}),
    ('COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION', {'view': 'attempts'}),
    ('COM_LOGINGUARD_SUBMENU_CONFIGURATION', {'link': 'option=com_config&view=component&component=com_loginguard'}),
    ('COM_LOGINGUARD_SUBMENU_TOOLS', {'view': 'tools'}),
    ('COM_LOGINGUARD_SUBMENU_ABOUT', {'view': 'about'}),
]
submenu_items = [((item.text or '').strip(), item.attrib) for item in submenu_root.findall('menu')]
for label, required_attrs in expected_submenus:
    if not any(item_label == label and all(attrs.get(name) == value for name, value in required_attrs.items()) for item_label, attrs in submenu_items):
        print(f'Component manifest missing administrator submenu item: {label} {required_attrs}', file=sys.stderr)
        sys.exit(1)

access_xml = Path('administrator/components/com_loginguard/access.xml')
access_root = roots.get(access_xml)
if access_root is None:
    print('Missing access.xml for ACL validation', file=sys.stderr)
    sys.exit(1)
actions = {action.attrib.get('name') for action in access_root.findall("./section[@name='component']/action")}
required_actions = {'core.manage', 'loginguard.view', 'core.admin', 'loginguard.delete', 'loginguard.export'}
if not required_actions.issubset(actions):
    print(f'access.xml missing ACL actions: {sorted(required_actions - actions)}', file=sys.stderr)
    sys.exit(1)

config_xml = Path('administrator/components/com_loginguard/config.xml')
config_root = roots.get(config_xml)
if config_root is None:
    print('Missing config.xml for com_config integration', file=sys.stderr)
    sys.exit(1)
config_fields = {field.attrib.get('name') for field in config_root.findall('.//field')}
required_config_fields = {'trusted_proxies', 'retention_days', 'logging_level', 'geoip_enabled', 'export_requires_permission', 'audit_alerts_enabled', 'audit_alert_success', 'audit_alert_failed', 'audit_alert_recipients', 'audit_alert_success_subject', 'audit_alert_success_body', 'audit_alert_failed_subject', 'audit_alert_failed_body', 'rules'}
forbidden_config_fields = {'lockout_duration', 'failed_attempt_threshold'}
if not required_config_fields.issubset(config_fields):
    print(f'config.xml missing fields: {sorted(required_config_fields - config_fields)}', file=sys.stderr)
    sys.exit(1)
if forbidden_config_fields & config_fields:
    print(f'config.xml keeps non-functional enforcement fields: {sorted(forbidden_config_fields & config_fields)}', file=sys.stderr)
    sys.exit(1)

for view in ['Dashboard', 'Attempts', 'Tools', 'About']:
    view_file = Path(f'administrator/components/com_loginguard/src/View/{view}/HtmlView.php')
    if not view_file.is_file():
        print(f'Missing submenu view {view}', file=sys.stderr)
        sys.exit(1)

dashboard_view = Path('administrator/components/com_loginguard/src/View/Dashboard/HtmlView.php')
dashboard_model = Path('administrator/components/com_loginguard/src/Model/DashboardModel.php')
dashboard_template = Path('administrator/components/com_loginguard/tmpl/dashboard/default.php')
for required_file in [dashboard_view, dashboard_model, dashboard_template]:
    if not required_file.is_file():
        print(f'Missing dashboard MVC file: {required_file}', file=sys.stderr)
        sys.exit(1)

dashboard_view_text = dashboard_view.read_text(encoding='utf-8')
for required_text in ["requirePermission('core.manage')", "requirePermission('loginguard.view')", 'TelemetryCounts', 'RecentActivity', 'TopFailureReasons', 'TopFailedIps']:
    if required_text not in dashboard_view_text:
        print(f'Dashboard HtmlView missing required telemetry/ACL wiring: {required_text}', file=sys.stderr)
        sys.exit(1)

dashboard_model_text = dashboard_model.read_text(encoding='utf-8')
for required_text in ['SUCCESS_LOGIN', 'FAILED_LOGIN', 'frontend', 'backend', 'PASSWORD_INCORRECT', 'USERNAME_NOT_FOUND', 'INVALID_CREDENTIALS', 'ACCOUNT_BLOCKED', 'ACCOUNT_DISABLED', '#__loginguard_attempts']:
    if required_text not in dashboard_model_text:
        print(f'Dashboard model missing required telemetry token: {required_text}', file=sys.stderr)
        sys.exit(1)

dashboard_template_text = dashboard_template.read_text(encoding='utf-8')
for required_text in ['COM_LOGINGUARD_DASHBOARD_FRONTEND_SUCCESS', 'COM_LOGINGUARD_DASHBOARD_BACKEND_SUCCESS', 'COM_LOGINGUARD_DASHBOARD_FRONTEND_FAILED', 'COM_LOGINGUARD_DASHBOARD_BACKEND_FAILED', 'COM_LOGINGUARD_DASHBOARD_RECENT_ACTIVITY', 'COM_LOGINGUARD_DASHBOARD_TOP_FAILURE_REASONS', 'COM_LOGINGUARD_DASHBOARD_TOP_IPS', 'COM_LOGINGUARD_SUBMENU_LOGIN_INFORMATION']:
    if required_text not in dashboard_template_text:
        print(f'Dashboard template missing required widget rendering: {required_text}', file=sys.stderr)
        sys.exit(1)
if any(forbidden in dashboard_template_text.lower() for forbidden in ['chart.js', 'analytics', 'leaflet', 'react', 'vue']):
    print('Dashboard template must remain lightweight without charts/maps/SPA/analytics libraries', file=sys.stderr)
    sys.exit(1)

if any(origin in dashboard_template_text for origin in ["'api' =>", "'cli' =>"]):
    print('Dashboard origin metrics must only render frontend and backend origins', file=sys.stderr)
    sys.exit(1)

helper_text = Path('administrator/components/com_loginguard/src/Helper/LoginGuardHelper.php').read_text(encoding='utf-8')
internal_sidebar_paths = [
    Path('administrator/components/com_loginguard/src/Helper/LoginGuardHelper.php'),
    *(Path(f'administrator/components/com_loginguard/src/View/{view}/HtmlView.php') for view in ['Dashboard', 'Attempts', 'Tools', 'About']),
    *(Path(f'administrator/components/com_loginguard/tmpl/{view}/default.php') for view in ['dashboard', 'attempts', 'tools', 'about']),
]
for internal_sidebar_path in internal_sidebar_paths:
    internal_sidebar_text = internal_sidebar_path.read_text(encoding='utf-8')
    for forbidden_sidebar_token in ['Sidebar::addEntry', 'Sidebar::render()', 'j-sidebar-container', '$this->sidebar']:
        if forbidden_sidebar_token in internal_sidebar_text:
            print(f'Internal component sidebar token remains in {internal_sidebar_path}: {forbidden_sidebar_token}', file=sys.stderr)
            sys.exit(1)

for permission in required_actions:
    if permission not in helper_text + view_text + login_guard_text + Path('administrator/components/com_loginguard/src/Controller/DisplayController.php').read_text(encoding='utf-8') + Path('administrator/components/com_loginguard/src/Controller/AttemptsController.php').read_text(encoding='utf-8'):
        print(f'ACL permission not enforced or referenced: {permission}', file=sys.stderr)
        sys.exit(1)

install_sql = (plugin_manifest.parent / 'sql/install.mysql.utf8.sql').read_text(encoding='utf-8')

for telemetry in ['SUCCESS_LOGIN', 'FAILED_LOGIN', 'USERNAME_NOT_FOUND', 'PASSWORD_INCORRECT', 'INVALID_CREDENTIALS', 'ACCOUNT_BLOCKED', 'ACCOUNT_DISABLED', 'frontend', 'backend', 'api', 'cli']:
    if telemetry not in login_guard_text and telemetry not in install_sql:
        print(f'Missing authentication telemetry token: {telemetry}', file=sys.stderr)
        sys.exit(1)
if 'password' in login_guard_text.lower() and "'password'" not in login_guard_text.lower() and 'PASSWORD_INCORRECT' not in login_guard_text:
    print('Unexpected password handling check failed', file=sys.stderr)
    sys.exit(1)
if 'raw password' in login_guard_text.lower() or 'plaintext password' in install_sql.lower():
    print('Potential plaintext password storage detected', file=sys.stderr)
    sys.exit(1)

attempts_template = component_template.read_text(encoding='utf-8')
for heading in ['COM_LOGINGUARD_HEADING_FAILURE_REASON', 'COM_LOGINGUARD_HEADING_USER_AGENT', 'COM_LOGINGUARD_HEADING_WHERE', 'COM_LOGINGUARD_HEADING_DATETIME']:
    if heading not in attempts_template:
        print(f'Attempts table missing required heading {heading}', file=sys.stderr)
        sys.exit(1)

package_manifest = Path('pkg_loginguard/pkg_loginguard.xml')
package_name = f"pkg_loginguard_v{versions['VERSION']}.zip"

package_manifest_text = package_manifest.read_text(encoding='utf-8')
update_manifest = Path('updates/loginguard.xml')
if '<updateservers>' not in package_manifest_text or 'updates/loginguard.xml' not in package_manifest_text:
    print('Package manifest missing Joomla update server metadata', file=sys.stderr)
    sys.exit(1)
if not update_manifest.is_file():
    print('Missing Joomla update stream metadata: updates/loginguard.xml', file=sys.stderr)
    sys.exit(1)
update_text = update_manifest.read_text(encoding='utf-8')
update_server_text = package_manifest_text + update_text
wrong_repo = 'hazim' + '/LoginGuard'
if wrong_repo in update_server_text:
    print('Update metadata contains the incorrect repository URL', file=sys.stderr)
    sys.exit(1)
for required_url in ['https://raw.githubusercontent.com/hazatmda/LoginGuard/main/updates/loginguard.xml', 'https://github.com/hazatmda/LoginGuard/releases/tag/v0.2.4-alpha', 'https://github.com/hazatmda/LoginGuard/releases/download/v0.2.4-alpha/pkg_loginguard_v0.2.4-alpha.zip', 'https://github.com/hazatmda/LoginGuard']:
    if required_url not in update_server_text:
        print(f'Update metadata missing corrected repository URL: {required_url}', file=sys.stderr)
        sys.exit(1)
for required_text in [f'<version>{versions["VERSION"]}</version>', package_name, '<type>package</type>', '<element>pkg_loginguard</element>']:
    if required_text not in update_text:
        print(f'Update stream missing required metadata: {required_text}', file=sys.stderr)
        sys.exit(1)
if package_name not in Path('README.md').read_text(encoding='utf-8'):
    print(f'Readme missing expected package name {package_name}', file=sys.stderr)
    sys.exit(1)

scriptfile = plugin_root.findtext('scriptfile')
if scriptfile != 'script.php' or not (plugin_manifest.parent / scriptfile).is_file():
    print('Plugin installer scriptfile is not registered or missing', file=sys.stderr)
    sys.exit(1)

required_columns = [
    'id', 'user_id', 'name', 'username', 'email', 'ip_address', 'status',
    'browser', 'operating_system', 'country', 'where_at', 'user_agent',
    'attempt_type', 'client', 'reason', 'created',
]
missing_columns = [column for column in required_columns if f'`{column}`' not in install_sql]
if missing_columns:
    print(f'Install SQL missing required columns: {", ".join(missing_columns)}', file=sys.stderr)
    sys.exit(1)
PY

echo "Validation completed successfully"
