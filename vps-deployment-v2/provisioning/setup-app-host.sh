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
DOMAIN_LANDLORD="${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-${DOMAIN_PRODUCTION:-}}}"
DB_ENGINE="${DB_ENGINE:-${DB_ENGINE_STAGING:-${DB_ENGINE_PRODUCTION:-mysql}}}"
DB_HOST="${DB_HOST:-${DB_HOST_STAGING:-${DB_HOST_PRODUCTION:-}}}"
DB_PORT="${DB_PORT:-${DB_PORT_STAGING:-${DB_PORT_PRODUCTION:-}}}"
DB_NAME="${DB_NAME:-${DB_NAME_STAGING:-${DB_NAME_PRODUCTION:-plannerate_${APP_SLUG}}}}"
DB_USER="${DB_USER:-${DB_USER_STAGING:-${DB_USER_PRODUCTION:-plannerate_${APP_SLUG}_user}}}"
DB_PASSWORD="${DB_PASSWORD:-${DB_PASSWORD_STAGING:-${DB_PASSWORD_PRODUCTION:-$(random_secret)}}}"
REDIS_PASSWORD="${REDIS_PASSWORD:-${REDIS_PASSWORD_STAGING:-${REDIS_PASSWORD_PRODUCTION:-$(random_secret)}}}"
REVERB_DOMAIN="${REVERB_DOMAIN:-reverb.${DOMAIN_LANDLORD}}"

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
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg lsb-release ufw fail2ban jq unzip mysql-client postgresql-client"

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

log_info "Preparing filesystem layout"
run_cmd "mkdir -p ${APP_DIR} /opt/traefik/letsencrypt /opt/backups /opt/monitoring/${APP_SLUG}"
run_cmd "chown -R ${DEPLOY_USER}:${DEPLOY_USER} ${APP_DIR}"
run_cmd "chmod 750 ${APP_DIR}"
run_cmd "touch /opt/traefik/letsencrypt/acme.json"
run_cmd "chmod 600 /opt/traefik/letsencrypt/acme.json"
run_cmd "docker network create traefik-global >/dev/null 2>&1 || true"

if [[ "${DRY_RUN}" != "true" ]]; then
    write_file_secure "${APP_DIR}/.env" "${DEPLOY_USER}:${DEPLOY_USER}" "600" "APP_ENV=staging
APP_DEBUG=false
APP_URL=https://${DOMAIN_LANDLORD}
DOMAIN=${DOMAIN_LANDLORD}
DOMAIN_LANDLORD=${DOMAIN_LANDLORD}
APP_SLUG=${APP_SLUG}
GHCR_REPO=${GHCR_REPO}
DB_CONNECTION=${DB_ENGINE}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD}
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb
REVERB_HOST=${REVERB_DOMAIN}
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_DOMAIN=${REVERB_DOMAIN}
VITE_REVERB_HOST=${REVERB_DOMAIN}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
"
fi

log_success "App host provisioning completed for ${APP_SLUG}"
log_info "App directory: ${APP_DIR}"
