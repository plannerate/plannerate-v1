#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
MANIFEST_PATH="${1:-}"
DB_ENGINE="${DB_ENGINE:-pgsql}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    echo "Usage: ./bootstrap-all.sh /path/to/manifest.env"
    exit 1
fi

"${SCRIPT_DIR}/validate-prereqs.sh" "${MANIFEST_PATH}"
"${SCRIPT_DIR}/setup-app-host.sh" "${MANIFEST_PATH}"
DB_ENGINE="${DB_ENGINE}" "${SCRIPT_DIR}/setup-db-host.sh" "${MANIFEST_PATH}"

echo "Bootstrap completed."
