#!/usr/bin/env bash
set -euo pipefail

VERSION=$(cat VERSION)
PACKAGE_NAME="pkg_loginguard_v${VERSION}.zip"
BUILD_DIR="build/pkg_loginguard"
OUTPUT_DIR="packages"

rm -rf build
mkdir -p "${BUILD_DIR}" "${OUTPUT_DIR}"

(
    cd plugins/user/loginguard
    zip -r "../../../${BUILD_DIR}/plg_user_loginguard.zip" . >/dev/null
)

(
    cd plugins/task/loginguardcleanup
    zip -r "../../../${BUILD_DIR}/plg_task_loginguardcleanup.zip" . >/dev/null
)

COMPONENT_BUILD_DIR="build/com_loginguard"
mkdir -p "${COMPONENT_BUILD_DIR}/administrator/components/com_loginguard"
cp -R administrator/components/com_loginguard/. "${COMPONENT_BUILD_DIR}/administrator/components/com_loginguard/"
cp administrator/components/com_loginguard/loginguard.xml "${COMPONENT_BUILD_DIR}/loginguard.xml"
(
    cd "${COMPONENT_BUILD_DIR}"
    zip -r "../pkg_loginguard/com_loginguard.zip" . >/dev/null
)

cp pkg_loginguard/pkg_loginguard.xml "${BUILD_DIR}/pkg_loginguard.xml"
cp pkg_loginguard/script.php "${BUILD_DIR}/script.php"
(
    cd "${BUILD_DIR}"
    zip -r "../../${OUTPUT_DIR}/${PACKAGE_NAME}" . >/dev/null
)

rm -rf build

echo "Generated ${OUTPUT_DIR}/${PACKAGE_NAME}"
