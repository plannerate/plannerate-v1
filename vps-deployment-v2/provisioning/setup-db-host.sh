#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

MANIFEST_PATH="${1:-}"
DB_ENGINE="${DB_ENGINE:-pgsql}"
DB_MODE="${DB_MODE:-local}"
DRY_RUN="${DRY_RUN:-false}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: DB_ENGINE=pgsql|mysql ./setup-db-host.sh /path/to/manifest.env"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"

DB_NAME="${DB_NAME:-${DB_NAME_STAGING:-${DB_NAME_PRODUCTION:-}}}"
DB_USER="${DB_USER:-${DB_USER_STAGING:-${DB_USER_PRODUCTION:-}}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD_STAGING:-${DB_PASSWORD_PRODUCTION:-}}}"

required_vars=()

if [[ "${DB_MODE}" == "externo" ]]; then
    required_vars+=(DB_ALLOWED_CIDR)
fi

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

if [[ "${DB_MODE}" == "local" ]]; then
    DB_ALLOWED_HOST="${DB_ALLOWED_HOST:-localhost}"
else
    DB_ALLOWED_HOST="${DB_ALLOWED_HOST:-%}"
fi

log_info "Waiting for apt lock (unattended-upgrades may be running)"
run_cmd "systemctl stop unattended-upgrades 2>/dev/null || true"
run_cmd "while fuser /var/lib/dpkg/lock-frontend >/dev/null 2>&1; do sleep 2; done"

run_cmd "apt-get update -qq"
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ufw fail2ban rsync"

if [[ "${DB_ENGINE}" == "mysql" ]]; then
    log_info "Installing MySQL"
    MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-$(random_secret)}"
    run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mysql-server"

    if [[ "${DRY_RUN}" != "true" ]]; then
        MYSQL_BIND_ADDRESS="0.0.0.0"
        cat > /etc/mysql/mysql.conf.d/zz-vps-v2.cnf <<CFG
[mysqld]
bind-address = ${MYSQL_BIND_ADDRESS}
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
        if [[ "${DB_MODE}" == "local" ]]; then
            mysql -uroot <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'172.%' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'172.%';
FLUSH PRIVILEGES;
SQL
        fi

        write_file_secure "/root/.db-credentials-v2" "root:root" "600" "DB_ENGINE=pgsql
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
        PG_LISTEN_ADDRESSES="'*'"
        cat > "/etc/postgresql/${PG_VERSION}/main/conf.d/vps-v2.conf" <<CFG
listen_addresses = ${PG_LISTEN_ADDRESSES}
max_connections = 300
CFG

        if [[ "${DB_MODE}" == "externo" ]]; then
            echo "host all all ${DB_ALLOWED_CIDR} scram-sha-256" >> "/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
        else
            echo "host all all 172.16.0.0/12 scram-sha-256" >> "/etc/postgresql/${PG_VERSION}/main/pg_hba.conf"
        fi
    fi

    run_cmd "systemctl restart postgresql"

    if [[ "${DRY_RUN}" != "true" ]]; then
        sudo -u postgres psql <<SQL
DO \$\$
BEGIN
    IF NOT EXISTS (SELECT FROM pg_catalog.pg_roles WHERE rolname = '${DB_USER}') THEN
        CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASSWORD}';
    ELSE
        ALTER ROLE ${DB_USER} WITH LOGIN PASSWORD '${DB_PASSWORD}';
    END IF;
END
\$\$;
SQL
        sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1 || sudo -u postgres createdb --owner="${DB_USER}" "${DB_NAME}"
        sudo -u postgres psql -d postgres -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"
        sudo -u postgres psql -d "${DB_NAME}" <<SQL
GRANT USAGE, CREATE ON SCHEMA public TO ${DB_USER};
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO ${DB_USER};
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO ${DB_USER};
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO ${DB_USER};
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

log_info "Configuring firewall rules"
run_cmd "ufw default deny incoming"
run_cmd "ufw default allow outgoing"
run_cmd "ufw allow 22/tcp"

if [[ "${DB_MODE}" == "externo" ]]; then
    if [[ "${DB_ENGINE}" == "mysql" ]]; then
        run_cmd "ufw allow from ${DB_ALLOWED_CIDR} to any port 3306 proto tcp"
    else
        run_cmd "ufw allow from ${DB_ALLOWED_CIDR} to any port 5432 proto tcp"
    fi
else
    if [[ "${DB_ENGINE}" == "mysql" ]]; then
        run_cmd "ufw allow from 172.16.0.0/12 to any port 3306 proto tcp"
    else
        run_cmd "ufw allow from 172.16.0.0/12 to any port 5432 proto tcp"
    fi
fi
run_cmd "ufw --force enable"

log_info "Configuring fail2ban (SSH jail)"
if [[ "${DRY_RUN}" != "true" ]]; then
    OPERATOR_IP="${OPERATOR_IP:-}"
    if [[ -z "${OPERATOR_IP}" ]]; then
        OPERATOR_IP="$(echo "${SSH_CLIENT:-}" | awk '{print $1}')"
    fi

    mkdir -p /etc/fail2ban/jail.d
    cat > /etc/fail2ban/jail.d/vps-v2-ssh.local << CFG
[sshd]
enabled  = true
port     = ssh
filter   = sshd
maxretry = 5
bantime  = 3600
findtime = 600
ignoreip = 127.0.0.1/8 ::1${OPERATOR_IP:+ ${OPERATOR_IP}}
CFG
    systemctl enable fail2ban >/dev/null 2>&1
    systemctl restart fail2ban
    if [[ -n "${OPERATOR_IP}" ]]; then
        log_info "fail2ban: IP do operador ${OPERATOR_IP} está na whitelist"
    fi
fi

log_success "DB host provisioning completed"
