#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

MANIFEST_PATH="${1:-}"
DB_ENGINE="${DB_ENGINE:-mysql}"
DRY_RUN="${DRY_RUN:-false}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: DB_ENGINE=mysql|pgsql ./setup-db-host.sh /path/to/manifest.env"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"

DB_NAME="${DB_NAME:-${DB_NAME_STAGING:-${DB_NAME_PRODUCTION:-}}}"
DB_USER="${DB_USER:-${DB_USER_STAGING:-${DB_USER_PRODUCTION:-}}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD_STAGING:-${DB_PASSWORD_PRODUCTION:-}}}"

required_vars=(
    DB_ALLOWED_CIDR
)

for var_name in "${required_vars[@]}"; do
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required variable in manifest: ${var_name}"
        exit 1
    fi
done

if [[ -z "${DB_NAME}" || -z "${DB_USER}" || -z "${DB_PASSWORD}" ]]; then
    log_error "Missing DB_NAME/DB_USER/DB_PASSWORD (or legacy staging/production DB vars)."
    exit 1
fi

run_cmd() {
    if [[ "${DRY_RUN}" == "true" ]]; then
        printf '[DRY_RUN] %s\n' "$*"
    else
        eval "$@"
    fi
}

DB_ALLOWED_HOST="${DB_ALLOWED_HOST:-%}"

run_cmd "apt-get update -qq"
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ufw fail2ban rsync"

if [[ "${DB_ENGINE}" == "mysql" ]]; then
    log_info "Installing MySQL"
    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(random_secret)}"
    run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server"

    if [[ "${DRY_RUN}" != "true" ]]; then
        cat > /etc/mysql/mysql.conf.d/zz-vps-v2.cnf <<CFG
[mysqld]
bind-address = 0.0.0.0
max_connections = 300
innodb_buffer_pool_size = 1G
CFG
    fi

    run_cmd "systemctl restart mysql"

    if [[ "${DRY_RUN}" != "true" ]]; then
        mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_ALLOWED_HOST}' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'${DB_ALLOWED_HOST}';
FLUSH PRIVILEGES;
SQL

        write_file_secure "/root/.db-credentials-v2" "root:root" "600" "DB_ENGINE=mysql
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
"
    fi
elif [[ "${DB_ENGINE}" == "pgsql" ]]; then
    log_info "Installing PostgreSQL"
    run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq postgresql postgresql-contrib"

    if [[ "${DRY_RUN}" != "true" ]]; then
        PG_VERSION=$(ls /etc/postgresql | sort -V | tail -n1)
        cat > "/etc/postgresql/${PG_VERSION}/main/conf.d/vps-v2.conf" <<CFG
listen_addresses = '*'
max_connections = 300
CFG

        echo "host all all ${DB_ALLOWED_CIDR} scram-sha-256" >> "/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
    fi

    run_cmd "systemctl restart postgresql"

    if [[ "${DRY_RUN}" != "true" ]]; then
        sudo -u postgres psql <<SQL
CREATE DATABASE ${DB_NAME};
CREATE USER ${DB_USER} WITH ENCRYPTED PASSWORD '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};
SQL

        write_file_secure "/root/.db-credentials-v2" "root:root" "600" "DB_ENGINE=pgsql
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
"
    fi
else
    log_error "Invalid DB_ENGINE: ${DB_ENGINE}. Use mysql or pgsql."
    exit 1
fi

log_info "Configuring firewall for database traffic"
run_cmd "ufw --force default deny incoming"
run_cmd "ufw --force default allow outgoing"
run_cmd "ufw --force allow 22/tcp"
if [[ "${DB_ENGINE}" == "mysql" ]]; then
    run_cmd "ufw --force allow from ${DB_ALLOWED_CIDR} to any port 3306 proto tcp"
else
    run_cmd "ufw --force allow from ${DB_ALLOWED_CIDR} to any port 5432 proto tcp"
fi
run_cmd "ufw --force enable"

log_success "DB host provisioning completed"
