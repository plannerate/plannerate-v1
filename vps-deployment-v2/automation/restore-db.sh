#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../provisioning/common.sh"

MANIFEST_PATH="${1:-}"
BACKUP_FILE="${2:-}"
TARGET_ENV="staging"

if [[ -z "${MANIFEST_PATH}" || -z "${BACKUP_FILE}" ]]; then
    log_error "Usage: ./restore-db.sh /path/to/manifest.env /path/to/backup.sql.gz"
    exit 1
fi

if [[ ! -f "${BACKUP_FILE}" ]]; then
    log_error "Backup file not found: ${BACKUP_FILE}"
    exit 1
fi

load_manifest "${MANIFEST_PATH}"
require_commands gunzip

DB_ENGINE="${DB_ENGINE:-${DB_ENGINE_STAGING:-${DB_ENGINE_PRODUCTION:-mysql}}}"
DB_HOST="${DB_HOST:-${DB_HOST_STAGING:-${DB_HOST_PRODUCTION:-}}}"
DB_PORT="${DB_PORT:-${DB_PORT_STAGING:-${DB_PORT_PRODUCTION:-}}}"
DB_NAME="${DB_NAME:-${DB_NAME_STAGING:-${DB_NAME_PRODUCTION:-}}}"
DB_USER="${DB_USER:-${DB_USER_STAGING:-${DB_USER_PRODUCTION:-}}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD_STAGING:-${DB_PASSWORD_PRODUCTION:-}}}"

read -r -p "This will overwrite data in ${TARGET_ENV}. Type YES to continue: " confirmation
if [[ "${confirmation}" != "YES" ]]; then
    log_warn "Restore cancelled"
    exit 1
fi

if [[ "${DB_ENGINE}" == "mysql" ]]; then
    require_commands mysql
    gunzip -c "${BACKUP_FILE}" | MYSQL_PWD="${DB_PASSWORD}" mysql --host="${DB_HOST}" --port="${DB_PORT}" --user="${DB_USER}" "${DB_NAME}"
elif [[ "${DB_ENGINE}" == "pgsql" ]]; then
    require_commands psql
    gunzip -c "${BACKUP_FILE}" | PGPASSWORD="${DB_PASSWORD}" psql --host="${DB_HOST}" --port="${DB_PORT}" --username="${DB_USER}" --dbname="${DB_NAME}"
else
    log_error "Unsupported DB engine: ${DB_ENGINE}"
    exit 1
fi

log_success "Restore finished for ${TARGET_ENV}"
