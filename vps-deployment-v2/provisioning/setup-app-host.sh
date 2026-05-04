#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

MANIFEST_PATH="${1:-}"
DRY_RUN="${DRY_RUN:-false}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./setup-app-host.sh /path/to/manifest.env"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"

APP_SLUG="${APP_SLUG:-${APP_NAME:-staging}}"
APP_DIR="/opt/plannerate/${APP_SLUG}"
APP_ENV="${APP_ENV:-}"
if [[ -z "${APP_ENV}" ]]; then
    if [[ "${APP_SLUG}" == "production" ]]; then
        APP_ENV="production"
    else
        APP_ENV="staging"
    fi
fi
DOMAIN_LANDLORD="${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-${DOMAIN_PRODUCTION:-}}}"
DB_MODE="${DB_MODE:-local}"
DB_ENGINE="${DB_ENGINE:-${DB_ENGINE_STAGING:-${DB_ENGINE_PRODUCTION:-pgsql}}}"
DB_CONNECTION_DEFAULT="${DB_CONNECTION_DEFAULT:-landlord}"
DB_HOST="${DB_HOST:-${DB_HOST_STAGING:-${DB_HOST_PRODUCTION:-}}}"
DB_PORT="${DB_PORT:-${DB_PORT_STAGING:-${DB_PORT_PRODUCTION:-}}}"
DB_NAME="${DB_NAME:-${DB_NAME_STAGING:-${DB_NAME_PRODUCTION:-plannerate_${APP_SLUG}}}}"
DB_USER="${DB_USER:-${DB_USER_STAGING:-${DB_USER_PRODUCTION:-plannerate_${APP_SLUG}_user}}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD_STAGING:-${DB_PASSWORD_PRODUCTION:-$(random_secret)}}}"
DB_LANDLORD_HOST="${DB_LANDLORD_HOST:-${DB_HOST}}"
DB_LANDLORD_PORT="${DB_LANDLORD_PORT:-${DB_PORT}}"
DB_LANDLORD_DATABASE="${DB_LANDLORD_DATABASE:-${DB_NAME}}"
DB_LANDLORD_USERNAME="${DB_LANDLORD_USERNAME:-${DB_USER}}"
DB_LANDLORD_PASSWORD="${DB_LANDLORD_PASSWORD:-${DB_PASSWORD}}"
DB_TENANT_DATABASE="${DB_TENANT_DATABASE:-}"
if [[ -z "${DB_TENANT_DATABASE}" || "${DB_TENANT_DATABASE}" == "null" ]]; then
    DB_TENANT_DATABASE="${DB_NAME}"
fi
if [[ "${DB_ENGINE}" == "pgsql" ]]; then
    DB_CHARSET_VALUE="${DB_CHARSET_VALUE:-utf8}"
else
    DB_CHARSET_VALUE="${DB_CHARSET_VALUE:-utf8mb4}"
fi
REDIS_PASSWORD="${REDIS_PASSWORD:-${REDIS_PASSWORD_STAGING:-${REDIS_PASSWORD_PRODUCTION:-$(random_secret)}}}"
REVERB_DOMAIN="${REVERB_DOMAIN:-reverb.${DOMAIN_LANDLORD}}"
REVERB_APP_ID="${REVERB_APP_ID:-${APP_SLUG}}"
REVERB_APP_KEY="${REVERB_APP_KEY:-$(random_secret)}"
REVERB_APP_SECRET="${REVERB_APP_SECRET:-$(random_secret)}"
APP_KEY="${APP_KEY:-base64:$(openssl rand -base64 32)}"

if [[ "${DB_MODE}" == "local" ]]; then
    DB_HOST="${DB_HOST:-host.docker.internal}"
    DB_LANDLORD_HOST="${DB_LANDLORD_HOST:-host.docker.internal}"
    if [[ -z "${DB_PORT}" ]]; then
        if [[ "${DB_ENGINE}" == "pgsql" ]]; then
            DB_PORT="5432"
        else
            DB_PORT="3306"
        fi
    fi
fi

required_vars=(PROJECT_NAME DEPLOY_USER ACME_EMAIL GHCR_REPO GITHUB_DEPLOY_PUBLIC_KEY)
for var_name in "${required_vars[@]}"; do
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required variable in manifest: ${var_name}"
        exit 1
    fi
done

if [[ -z "${DOMAIN_LANDLORD}" || -z "${DB_HOST}" || -z "${DB_NAME}" || -z "${DB_USER}" || -z "${DB_PASSWORD}" ]]; then
    log_error "Missing DOMAIN_LANDLORD/DB settings in manifest."
    exit 1
fi

if [[ "${DRY_RUN}" == "true" ]]; then
    log_info "DRY_RUN=true; command execution is disabled."
fi

run_cmd() {
    if [[ "${DRY_RUN}" == "true" ]]; then
        printf '[DRY_RUN] %s\n' "$*"
    else
        eval "$@"
    fi
}

log_info "Installing base packages"
run_cmd "apt-get update -qq"
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq"
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg lsb-release ufw fail2ban jq unzip openssl mysql-client postgresql-client"

log_info "Installing Docker engine"
run_cmd "install -m 0755 -d /etc/apt/keyrings"
run_cmd "rm -f /etc/apt/keyrings/docker.gpg"
run_cmd "curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg"
run_cmd "chmod a+r /etc/apt/keyrings/docker.gpg"
run_cmd "echo 'deb [arch='\"$(dpkg --print-architecture)\"' signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu '$(. /etc/os-release && echo "$VERSION_CODENAME")' stable' > /etc/apt/sources.list.d/docker.list"
run_cmd "apt-get update -qq"
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin"

if ! id "${DEPLOY_USER}" >/dev/null 2>&1; then
    run_cmd "useradd -m -s /bin/bash ${DEPLOY_USER}"
fi
run_cmd "usermod -aG docker ${DEPLOY_USER}"
if [[ "${DRY_RUN}" != "true" && -n "${DEPLOY_USER_PASS:-}" ]]; then
    printf '%s:%s\n' "${DEPLOY_USER}" "${DEPLOY_USER_PASS}" | chpasswd
    log_info "Senha definida para ${DEPLOY_USER} (acesso via console)"
fi

log_info "Preparing SSH access for deploy user"
run_cmd "install -d -m 700 -o ${DEPLOY_USER} -g ${DEPLOY_USER} /home/${DEPLOY_USER}/.ssh"
run_cmd "touch /home/${DEPLOY_USER}/.ssh/authorized_keys"
run_cmd "chown ${DEPLOY_USER}:${DEPLOY_USER} /home/${DEPLOY_USER}/.ssh/authorized_keys"
run_cmd "chmod 600 /home/${DEPLOY_USER}/.ssh/authorized_keys"

if [[ "${DRY_RUN}" != "true" ]]; then
    if ! grep -Fq "${GITHUB_DEPLOY_PUBLIC_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
        printf '%s\n' "${GITHUB_DEPLOY_PUBLIC_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    fi
    ADMIN_PUBLIC_KEY="${ADMIN_PUBLIC_KEY:-}"
    if [[ -n "${ADMIN_PUBLIC_KEY}" ]] && ! grep -Fq "${ADMIN_PUBLIC_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
        printf '%s\n' "${ADMIN_PUBLIC_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
        log_info "Chave admin adicionada ao authorized_keys do ${DEPLOY_USER}"
    fi
fi

log_info "Granting passwordless sudo to ${DEPLOY_USER}"
if [[ "${DRY_RUN}" != "true" ]]; then
    printf '%s ALL=(ALL) NOPASSWD:ALL\n' "${DEPLOY_USER}" > /etc/sudoers.d/vps-v2-deploy
    chmod 440 /etc/sudoers.d/vps-v2-deploy
    visudo -cf /etc/sudoers.d/vps-v2-deploy || { rm -f /etc/sudoers.d/vps-v2-deploy; log_error "sudoers inválido"; exit 1; }
fi

log_info "Hardening SSH — disabling root login and password auth"
if [[ "${DRY_RUN}" != "true" ]]; then
    SSHD_CFG="/etc/ssh/sshd_config"
    SSHD_BACKUP="/etc/ssh/sshd_config.bak-vps-v2"
    cp "${SSHD_CFG}" "${SSHD_BACKUP}"

    # Remove existing directives (both commented and active)
    grep -Ev '^#?\s*(PermitRootLogin|PasswordAuthentication|MaxAuthTries)\b' "${SSHD_BACKUP}" > "${SSHD_CFG}"

    printf '\n# Added by vps-deployment-v2 setup\nPermitRootLogin no\nPasswordAuthentication no\nMaxAuthTries 3\n' >> "${SSHD_CFG}"

    if ! sshd -t -f "${SSHD_CFG}"; then
        log_error "sshd_config inválido — restaurando backup e abortando hardening"
        cp "${SSHD_BACKUP}" "${SSHD_CFG}"
    else
        systemctl restart ssh 2>/dev/null || systemctl restart sshd
        log_warn "Root SSH login is now DISABLED. Use '${DEPLOY_USER}' for future connections."
        log_info "Backup da config original em ${SSHD_BACKUP}"
    fi
fi

log_info "Configuring firewall (UFW)"
run_cmd "ufw default deny incoming"
run_cmd "ufw default allow outgoing"
run_cmd "ufw allow 22/tcp"
run_cmd "ufw allow 80/tcp"
run_cmd "ufw allow 443/tcp"
run_cmd "ufw --force enable"

log_info "Configuring fail2ban (SSH jail)"
if [[ "${DRY_RUN}" != "true" ]]; then
    OPERATOR_IP="${OPERATOR_IP:-}"
    if [[ -z "${OPERATOR_IP}" ]]; then
        # Detect from current SSH connection
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

log_info "Preparing filesystem layout"
run_cmd "mkdir -p ${APP_DIR} /opt/traefik/letsencrypt /opt/backups /opt/monitoring/${APP_SLUG}"
run_cmd "mkdir -p ${APP_DIR}/storage/framework/views ${APP_DIR}/storage/framework/cache ${APP_DIR}/storage/framework/sessions ${APP_DIR}/bootstrap/cache"
run_cmd "chown -R ${DEPLOY_USER}:${DEPLOY_USER} ${APP_DIR}"
run_cmd "chmod 750 ${APP_DIR}"
run_cmd "touch /opt/traefik/letsencrypt/acme.json"
run_cmd "chmod 600 /opt/traefik/letsencrypt/acme.json"
run_cmd "docker network create traefik-global >/dev/null 2>&1 || true"

if [[ "${DRY_RUN}" != "true" ]]; then
    if [[ -z "${TRAEFIK_DASHBOARD_BASICAUTH:-}" ]]; then
        TRAEFIK_DASHBOARD_USER="${TRAEFIK_DASHBOARD_USER:-admin}"
        TRAEFIK_DASHBOARD_PASS="${TRAEFIK_DASHBOARD_PASS:-$(random_secret)}"
        _salt="$(openssl rand -base64 6)"
        _hash="$(openssl passwd -apr1 -salt "${_salt}" "${TRAEFIK_DASHBOARD_PASS}")"
        TRAEFIK_DASHBOARD_BASICAUTH="${TRAEFIK_DASHBOARD_USER}:${_hash//\$/\$\$}"
    fi

    write_file_secure "${APP_DIR}/.env" "${DEPLOY_USER}:${DEPLOY_USER}" "600" "APP_ENV=${APP_ENV}
APP_DEBUG=false
APP_KEY=${APP_KEY}
APP_URL=https://${DOMAIN_LANDLORD}
LANDLORD_DOMAIN=${DOMAIN_LANDLORD}
DOMAIN=${DOMAIN_LANDLORD}
DOMAIN_LANDLORD=${DOMAIN_LANDLORD}
APP_SLUG=${APP_SLUG}
GHCR_REPO=${GHCR_REPO}
DB_CONNECTION=${DB_CONNECTION_DEFAULT}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
DB_CHARSET=${DB_CHARSET_VALUE}
DB_TENANT_CONNECTION=${DB_ENGINE}
DB_TENANT_DATABASE=${DB_TENANT_DATABASE}
DB_TENANT_CHARSET=${DB_CHARSET_VALUE}
DB_LANDLORD_CONNECTION=${DB_ENGINE}
DB_LANDLORD_HOST=${DB_LANDLORD_HOST}
DB_LANDLORD_PORT=${DB_LANDLORD_PORT}
DB_LANDLORD_DATABASE=${DB_LANDLORD_DATABASE}
DB_LANDLORD_USERNAME=${DB_LANDLORD_USERNAME}
DB_LANDLORD_PASSWORD=${DB_LANDLORD_PASSWORD}
DB_LANDLORD_CHARSET=${DB_CHARSET_VALUE}
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=default
REDIS_CACHE_CONNECTION=cache
REDIS_CACHE_LOCK_CONNECTION=default
BROADCAST_CONNECTION=reverb
REVERB_HOST=${REVERB_DOMAIN}
REVERB_APP_ID=${REVERB_APP_ID}
REVERB_APP_KEY=${REVERB_APP_KEY}
REVERB_APP_SECRET=${REVERB_APP_SECRET}
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_DOMAIN=${REVERB_DOMAIN}
VITE_REVERB_HOST=${REVERB_DOMAIN}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
IMAGE_TAG=latest
"

    write_file_secure "/opt/traefik/.env" "root:root" "600" "ACME_EMAIL=${ACME_EMAIL}
TRAEFIK_DASHBOARD_HOST=${TRAEFIK_DASHBOARD_HOST:-traefik.${DOMAIN_LANDLORD}}
TRAEFIK_DASHBOARD_BASICAUTH=${TRAEFIK_DASHBOARD_BASICAUTH}
"
fi

log_success "App host provisioning completed for ${APP_SLUG}"
log_info "App directory: ${APP_DIR}"
