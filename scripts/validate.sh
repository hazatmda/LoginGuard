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
for xml_file in xml_files:
    try:
        root = ET.parse(xml_file).getroot()
    except ET.ParseError as exc:
        print(f'Invalid XML in {xml_file}: {exc}', file=sys.stderr)
        sys.exit(1)

    version = root.findtext('version')
    if version is not None:
        versions[str(xml_file)] = version.strip()

mismatched = {path: version for path, version in versions.items() if version != versions['VERSION']}
if mismatched:
    details = ', '.join(f'{path}={version}' for path, version in versions.items())
    print(f'Version mismatch: {details}', file=sys.stderr)
    sys.exit(1)
PY

echo "Validation completed successfully"
