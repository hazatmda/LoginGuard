#!/usr/bin/env bash
set -e

VERSION=$(cat VERSION)
PACKAGE_NAME="plg_user_loginguard_v${VERSION}.zip"

mkdir -p packages

zip -r "packages/${PACKAGE_NAME}" plugins >/dev/null

echo "Generated packages/${PACKAGE_NAME}"
