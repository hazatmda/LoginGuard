#!/usr/bin/env bash
set -euo pipefail

VERSION=$(cat VERSION)
EXPECTED_PACKAGE="pkg_loginguard_v${VERSION}.zip"

find plugins administrator -name "*.php" -print0 | xargs -0 -n 1 php -l

python3 - <<'PY'
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

version = Path('VERSION').read_text().strip()
manifest_paths = [
    Path('plugins/user/loginguard/loginguard.xml'),
    Path('administrator/components/com_loginguard/loginguard.xml'),
    Path('pkg_loginguard/pkg_loginguard.xml'),
]

for path in manifest_paths:
    root = ET.parse(path).getroot()
    found = root.findtext('version')
    if found != version:
        raise SystemExit(f'{path}: manifest version {found!r} does not match VERSION {version!r}')

package_root = ET.parse('pkg_loginguard/pkg_loginguard.xml').getroot()
files = {(node.attrib.get('type'), node.attrib.get('id'), node.attrib.get('group'), (node.text or '').strip()) for node in package_root.findall('./files/file')}
required = {
    ('plugin', 'loginguard', 'user', 'plg_user_loginguard.zip'),
    ('component', 'com_loginguard', None, 'com_loginguard.zip'),
}
if files != required:
    raise SystemExit(f'pkg_loginguard/pkg_loginguard.xml: unexpected package files {files!r}')

component_root = ET.parse('administrator/components/com_loginguard/loginguard.xml').getroot()
menu = component_root.find('./administration/menu')
if menu is None or menu.attrib.get('link') != 'option=com_loginguard':
    raise SystemExit('administrator component manifest must define the com_loginguard admin menu link')

workflow = Path('.github/workflows/build.yml').read_text()
if 'packages/pkg_loginguard_v*.zip' not in workflow or 'softprops/action-gh-release@v2' not in workflow:
    raise SystemExit('build workflow must upload package ZIP artifacts and release assets')

print('Manifest, package structure, workflow, and version metadata validation passed')
PY

echo "Expected release asset: ${EXPECTED_PACKAGE}"
echo "Validation completed successfully"
