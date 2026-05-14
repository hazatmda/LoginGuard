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

scriptfile = plugin_root.findtext('scriptfile')
if scriptfile != 'script.php' or not (plugin_manifest.parent / scriptfile).is_file():
    print('Plugin installer scriptfile is not registered or missing', file=sys.stderr)
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
