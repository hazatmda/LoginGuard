#!/usr/bin/env bash
set -euo pipefail

VERSION=$(cat VERSION)
PACKAGE_NAME="pkg_loginguard_v${VERSION}.zip"
PLUGIN_ZIP="plg_user_loginguard.zip"
COMPONENT_ZIP="com_loginguard.zip"
BUILD_DIR="build/pkg_loginguard"
COMPONENT_BUILD_DIR="build/com_loginguard"
PACKAGE_DIR="packages"

rm -rf build
mkdir -p "${PACKAGE_DIR}"
rm -f "${PACKAGE_DIR}"/pkg_loginguard_v*.zip
mkdir -p "${BUILD_DIR}" "${COMPONENT_BUILD_DIR}/admin" "${PACKAGE_DIR}"

(
    cd plugins/user/loginguard
    zip -r "../../../${BUILD_DIR}/${PLUGIN_ZIP}" . >/dev/null
)

cp administrator/components/com_loginguard/loginguard.xml "${COMPONENT_BUILD_DIR}/loginguard.xml"
cp -R administrator/components/com_loginguard/services "${COMPONENT_BUILD_DIR}/admin/services"
cp -R administrator/components/com_loginguard/src "${COMPONENT_BUILD_DIR}/admin/src"
cp -R administrator/components/com_loginguard/language "${COMPONENT_BUILD_DIR}/admin/language"

(
    cd "${COMPONENT_BUILD_DIR}"
    zip -r "../pkg_loginguard/${COMPONENT_ZIP}" . >/dev/null
)

cp pkg_loginguard/pkg_loginguard.xml "${BUILD_DIR}/pkg_loginguard.xml"

(
    cd "${BUILD_DIR}"
    zip -r "../../${PACKAGE_DIR}/${PACKAGE_NAME}" . >/dev/null
)

echo "Generated ${PACKAGE_DIR}/${PACKAGE_NAME}"
