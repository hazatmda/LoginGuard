#!/usr/bin/env bash
set -euo pipefail

find plugins administrator -name "*.php" -exec php -l {} \;

python3 - <<'PY'
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

xml_files = [
    *Path('plugins').rglob('*.xml'),
    *Path('administrator').rglob('*.xml'),
    *Path('pkg_loginguard').rglob('*.xml'),
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

expected_package_name = f"pkg_loginguard_v{versions['VERSION']}.zip"
readme = Path('README.md').read_text(encoding='utf-8')
if expected_package_name not in readme:
    print(f'Missing synchronized package filename in README: {expected_package_name}', file=sys.stderr)
    sys.exit(1)

scriptfile = plugin_root.findtext('scriptfile')
if scriptfile != 'script.php' or not (plugin_manifest.parent / scriptfile).is_file():
    print('Plugin installer scriptfile is not registered or missing', file=sys.stderr)
    sys.exit(1)


component_model = Path('administrator/components/com_loginguard/src/Model/AttemptsModel.php').read_text(encoding='utf-8')
component_view = Path('administrator/components/com_loginguard/src/View/Attempts/HtmlView.php').read_text(encoding='utf-8')
component_template = Path('administrator/components/com_loginguard/tmpl/attempts/default.php').read_text(encoding='utf-8')
filter_xml_path = Path('administrator/components/com_loginguard/forms/filter_attempts.xml')
filter_root = ET.parse(filter_xml_path).getroot()

required_template_snippets = [
    "LayoutHelper::render('joomla.searchtools.default'",
    "HTMLHelper::_('searchtools.form', '#adminForm')",
    "HTMLHelper::_('grid.sort', 'COM_LOGINGUARD_HEADING_WHERE', 'where_at'",
    "$this->pagination->getListFooter()",
    'name="filter_order"',
    'name="filter_order_Dir"',
]
for snippet in required_template_snippets:
    if snippet not in component_template:
        print(f'Administrator attempts template missing Joomla ListView/SearchTools contract: {snippet}', file=sys.stderr)
        sys.exit(1)

for invalid_snippet in ["HTMLHelper::_('searchtools.default'", "searchtools::default"]:
    if invalid_snippet in component_template:
        print(f'Administrator attempts template still uses invalid SearchTools rendering: {invalid_snippet}', file=sys.stderr)
        sys.exit(1)

required_view_snippets = ["$this->get('FilterForm')", "$this->get('ActiveFilters')", "$this->get('Pagination')"]
for snippet in required_view_snippets:
    if snippet not in component_view:
        print(f'Administrator HtmlView missing ListView wiring: {snippet}', file=sys.stderr)
        sys.exit(1)

required_model_snippets = [
    "extends ListModel",
    "'where_at'",
    "filter.where_at",
    "list.ordering",
    "list.direction",
    "#__loginguard_attempts",
]
for snippet in required_model_snippets:
    if snippet not in component_model:
        print(f'Attempts ListModel missing Joomla state/query contract: {snippet}', file=sys.stderr)
        sys.exit(1)

filter_fields = filter_root.find("fields[@name='filter']")
list_fields = filter_root.find("fields[@name='list']")
if filter_fields is None or list_fields is None:
    print('Filter XML missing filter/list fieldsets required by Joomla SearchTools', file=sys.stderr)
    sys.exit(1)

filter_names = {field.attrib.get('name') for field in filter_fields.findall('field')}
if {'search', 'status', 'where_at'} - filter_names:
    print(f'Filter XML missing SearchTools filters: {sorted({"search", "status", "where_at"} - filter_names)}', file=sys.stderr)
    sys.exit(1)

fullordering = list_fields.find("field[@name='fullordering']")
limit = list_fields.find("field[@name='limit']")
if fullordering is None or limit is None:
    print('Filter XML missing fullordering or limit fields for sorting/pagination', file=sys.stderr)
    sys.exit(1)

ordering_options = {option.attrib.get('value') for option in fullordering.findall('option')}
required_ordering = {'id ASC', 'id DESC', 'ip_address ASC', 'ip_address DESC', 'name ASC', 'name DESC', 'username ASC', 'username DESC', 'status ASC', 'status DESC', 'created ASC', 'created DESC', 'country ASC', 'country DESC', 'browser ASC', 'browser DESC', 'operating_system ASC', 'operating_system DESC', 'where_at ASC', 'where_at DESC'}
if required_ordering - ordering_options:
    print(f'Filter XML missing sorting options: {sorted(required_ordering - ordering_options)}', file=sys.stderr)
    sys.exit(1)

install_sql = (plugin_manifest.parent / 'sql/install.mysql.utf8.sql').read_text(encoding='utf-8')
required_columns = [
    'id', 'user_id', 'name', 'username', 'email', 'ip_address', 'status',
    'browser', 'operating_system', 'country', 'where_at', 'user_agent',
    'attempt_type', 'created',
]
missing_columns = [column for column in required_columns if f'`{column}`' not in install_sql]
if missing_columns:
    print(f'Install SQL missing required columns: {", ".join(missing_columns)}', file=sys.stderr)
    sys.exit(1)
PY

echo "Validation completed successfully"
