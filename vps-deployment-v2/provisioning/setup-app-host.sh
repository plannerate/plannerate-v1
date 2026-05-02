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

required_vars=(
    PROJECT_NAME
    DEPLOY_USER
    DOMAIN_PRODUCTION
    DOMAIN_STAGING
    ACME_EMAIL
    GHCR_REPO
    GITHUB_DEPLOY_PUBLIC_KEY
)

for var_name in "${required_vars[@]}"; do
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required variable in manifest: ${var_name}"
        exit 1
    fi
done

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
run_cmd "DEBIAN_FRONTEND=noninteractive apt-get install -y -qq ca-certificates curl gnupg lsb-release ufw fail2ban jq awscli mysql-client postgresql-client"

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

log_info "Preparing SSH access for deploy user"
run_cmd "install -d -m 700 -o ${DEPLOY_USER} -g ${DEPLOY_USER} /home/${DEPLOY_USER}/.ssh"
run_cmd "touch /home/${DEPLOY_USER}/.ssh/authorized_keys"
run_cmd "chown ${DEPLOY_USER}:${DEPLOY_USER} /home/${DEPLOY_USER}/.ssh/authorized_keys"
run_cmd "chmod 600 /home/${DEPLOY_USER}/.ssh/authorized_keys"

if [[ "${DRY_RUN}" != "true" ]]; then
    if ! grep -Fq "${GITHUB_DEPLOY_PUBLIC_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
        printf '%s\n' "${GITHUB_DEPLOY_PUBLIC_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
    fi
fi

log_info "Applying SSH hardening"
run_cmd "sed -i 's/^#\?PasswordAuthentication .*/PasswordAuthentication no/' /etc/ssh/sshd_config"
run_cmd "sed -i 's/^#\?PermitRootLogin .*/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config"
run_cmd "systemctl restart ssh || systemctl restart sshd"

log_info "Configuring firewall"
run_cmd "ufw --force default deny incoming"
run_cmd "ufw --force default allow outgoing"
run_cmd "ufw --force allow 22/tcp"
run_cmd "ufw --force allow 80/tcp"
run_cmd "ufw --force allow 443/tcp"
run_cmd "ufw --force enable"

log_info "Preparing filesystem layout"
run_cmd "mkdir -p /opt/production /opt/staging /opt/traefik/letsencrypt /opt/backups /opt/monitoring"
run_cmd "chown -R ${DEPLOY_USER}:${DEPLOY_USER} /opt/production /opt/staging"
run_cmd "chmod 750 /opt/production /opt/staging"
run_cmd "touch /opt/traefik/letsencrypt/acme.json"
run_cmd "chmod 600 /opt/traefik/letsencrypt/acme.json"

log_info "Creating Docker network for Traefik"
run_cmd "docker network create traefik-global >/dev/null 2>&1 || true"

log_info "Generating runtime secrets"
REDIS_PASSWORD_PRODUCTION="${REDIS_PASSWORD_PRODUCTION:-$(random_secret)}"
REDIS_PASSWORD_STAGING="${REDIS_PASSWORD_STAGING:-$(random_secret)}"
REVERB_DOMAIN_PRODUCTION="${REVERB_DOMAIN_PRODUCTION:-reverb.${DOMAIN_PRODUCTION}}"
REVERB_DOMAIN_STAGING="${REVERB_DOMAIN_STAGING:-reverb.${DOMAIN_STAGING}}"
GRAFANA_ADMIN_USER="${GRAFANA_ADMIN_USER:-admin}"
GRAFANA_ADMIN_PASSWORD="${GRAFANA_ADMIN_PASSWORD:-$(random_secret)}"
GRAFANA_DOMAIN="${GRAFANA_DOMAIN:-grafana.${DOMAIN_PRODUCTION}}"
PROMETHEUS_DOMAIN="${PROMETHEUS_DOMAIN:-prometheus.${DOMAIN_PRODUCTION}}"
ALERTMANAGER_DOMAIN="${ALERTMANAGER_DOMAIN:-alerts.${DOMAIN_PRODUCTION}}"
PROMETHEUS_RETENTION="${PROMETHEUS_RETENTION:-15d}"
ALERT_WEBHOOK_DEFAULT_URL="${ALERT_WEBHOOK_DEFAULT_URL:-http://127.0.0.1:65535}"
ALERT_WEBHOOK_WARNING_URL="${ALERT_WEBHOOK_WARNING_URL:-${ALERT_WEBHOOK_DEFAULT_URL}}"
ALERT_WEBHOOK_CRITICAL_URL="${ALERT_WEBHOOK_CRITICAL_URL:-${ALERT_WEBHOOK_DEFAULT_URL}}"

if [[ "${DRY_RUN}" != "true" ]]; then
    write_file_secure "/opt/production/.env" "${DEPLOY_USER}:${DEPLOY_USER}" "600" "APP_ENV=production
APP_DEBUG=false
APP_URL=https://${DOMAIN_PRODUCTION}
DOMAIN=${DOMAIN_PRODUCTION}
GHCR_REPO=${GHCR_REPO}
DB_CONNECTION=${DB_ENGINE_PRODUCTION:-mysql}
DB_HOST=${DB_HOST_PRODUCTION}
DB_PORT=${DB_PORT_PRODUCTION}
DB_DATABASE=${DB_NAME_PRODUCTION}
DB_USERNAME=${DB_USER_PRODUCTION}
DB_PASSWORD=${DB_PASSWORD_PRODUCTION}
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD_PRODUCTION}
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb
REVERB_HOST=${REVERB_DOMAIN_PRODUCTION}
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_DOMAIN=${REVERB_DOMAIN_PRODUCTION}
VITE_REVERB_HOST=${REVERB_DOMAIN_PRODUCTION}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
"

    write_file_secure "/opt/staging/.env" "${DEPLOY_USER}:${DEPLOY_USER}" "600" "APP_ENV=staging
APP_DEBUG=false
APP_URL=https://${DOMAIN_STAGING}
DOMAIN=${DOMAIN_STAGING}
GHCR_REPO=${GHCR_REPO}
DB_CONNECTION=${DB_ENGINE_STAGING:-mysql}
DB_HOST=${DB_HOST_STAGING}
DB_PORT=${DB_PORT_STAGING}
DB_DATABASE=${DB_NAME_STAGING}
DB_USERNAME=${DB_USER_STAGING}
DB_PASSWORD=${DB_PASSWORD_STAGING}
REDIS_HOST=redis
REDIS_PASSWORD=${REDIS_PASSWORD_STAGING}
REDIS_PORT=6379
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
BROADCAST_CONNECTION=reverb
REVERB_HOST=${REVERB_DOMAIN_STAGING}
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_DOMAIN=${REVERB_DOMAIN_STAGING}
VITE_REVERB_HOST=${REVERB_DOMAIN_STAGING}
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
"

    write_file_secure "/opt/traefik/.env" "root:root" "600" "ACME_EMAIL=${ACME_EMAIL}
DOMAIN_PRODUCTION=${DOMAIN_PRODUCTION}
DOMAIN_STAGING=${DOMAIN_STAGING}
TRAEFIK_DASHBOARD_HOST=${TRAEFIK_DASHBOARD_HOST:-traefik.${DOMAIN_PRODUCTION}}
TRAEFIK_DASHBOARD_BASICAUTH=${TRAEFIK_DASHBOARD_BASICAUTH}
"

    write_file_secure "/opt/monitoring/.env" "root:root" "600" "GRAFANA_DOMAIN=${GRAFANA_DOMAIN}
PROMETHEUS_DOMAIN=${PROMETHEUS_DOMAIN}
ALERTMANAGER_DOMAIN=${ALERTMANAGER_DOMAIN}
GRAFANA_ADMIN_USER=${GRAFANA_ADMIN_USER}
GRAFANA_ADMIN_PASSWORD=${GRAFANA_ADMIN_PASSWORD}
PROMETHEUS_RETENTION=${PROMETHEUS_RETENTION}
ALERT_WEBHOOK_DEFAULT_URL=${ALERT_WEBHOOK_DEFAULT_URL}
ALERT_WEBHOOK_WARNING_URL=${ALERT_WEBHOOK_WARNING_URL}
ALERT_WEBHOOK_CRITICAL_URL=${ALERT_WEBHOOK_CRITICAL_URL}
"
fi

log_success "App host provisioning completed"
log_info "Next step: copy compose files into /opt/production and /opt/staging, then start stacks."
