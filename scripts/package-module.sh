#!/usr/bin/env bash
set -euo pipefail

MODULE_NAME="bookstack-offline-web-export"
DIST_DIR="dist"
STAGING_DIR="${DIST_DIR}/${MODULE_NAME}"
ZIP_PATH="${DIST_DIR}/${MODULE_NAME}.zip"

rm -rf "${STAGING_DIR}" "${ZIP_PATH}"
mkdir -p "${STAGING_DIR}"

cp bookstack-module.json "${STAGING_DIR}/"
cp functions.php "${STAGING_DIR}/"
cp README.md "${STAGING_DIR}/"
cp -R src "${STAGING_DIR}/"
cp -R views "${STAGING_DIR}/"

(
  cd "${DIST_DIR}"
  zip -r "${MODULE_NAME}.zip" "${MODULE_NAME}" >/dev/null
)

echo "Created ${ZIP_PATH}"
