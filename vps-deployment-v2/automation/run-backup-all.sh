#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
MANIFEST_PATH="${1:-}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    echo "Uso: ./run-backup-all.sh /path/to/manifest.env"
    exit 1
fi

RETENTION_DAYS="${RETENTION_DAYS:-14}"
echo "[INFO] Rodando backup do banco (retenção: ${RETENTION_DAYS} dias)"
"${SCRIPT_DIR}/backup-db.sh" "${MANIFEST_PATH}"

echo "[OK] Backup de staging concluído"
