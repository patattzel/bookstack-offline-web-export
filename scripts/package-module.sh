#!/usr/bin/env bash
set -euo pipefail

MODULE_NAME="bookstack-offline-web-export"
DIST_DIR="dist"
ZIP_PATH="${DIST_DIR}/${MODULE_NAME}.zip"

rm -rf "${ZIP_PATH}"
mkdir -p "${DIST_DIR}"

(
  cd .
  zip -r "${ZIP_PATH}" \
    bookstack-module.json \
    functions.php \
    README.md \
    src \
    views >/dev/null
)

echo "Created ${ZIP_PATH}"
