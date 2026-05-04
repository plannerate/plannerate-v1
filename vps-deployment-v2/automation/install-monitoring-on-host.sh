#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${ROOT_DIR}/provisioning/common.sh"

MANIFEST_PATH="${1:-}"
START_MONITORING="${START_MONITORING:-true}"
APP_SLUG="${APP_SLUG:-${2:-staging}}"
ENABLE_PGADMIN="${ENABLE_PGADMIN:-false}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./install-monitoring-on-host.sh /path/to/manifest.env [app-slug]"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"
require_commands install cp sed docker getent

DOMAIN_LANDLORD="${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-${DOMAIN_PRODUCTION:-}}}"
if [[ -z "${DOMAIN_LANDLORD}" ]]; then
    log_error "Missing DOMAIN_LANDLORD in manifest"
    exit 1
fi

monitoring_dir="/opt/monitoring/${APP_SLUG}"
mkdir -p "${monitoring_dir}"
cp "${ROOT_DIR}/deployments/monitoring/docker-compose.yml" "${monitoring_dir}/docker-compose.yml"
cp "${ROOT_DIR}/deployments/monitoring/prometheus.yml" "${monitoring_dir}/prometheus.yml"
cp "${ROOT_DIR}/deployments/monitoring/alerts.yml" "${monitoring_dir}/alerts.yml"
cp "${ROOT_DIR}/deployments/monitoring/blackbox.yml" "${monitoring_dir}/blackbox.yml"
cp "${ROOT_DIR}/deployments/monitoring/alertmanager.yml" "${monitoring_dir}/alertmanager.yml"
cp "${ROOT_DIR}/deployments/monitoring/docker-compose.pgadmin.yml" "${monitoring_dir}/docker-compose.pgadmin.yml"

app_url="https://${DOMAIN_LANDLORD}/up"
sed -i "s|https://app.example.com/up|${app_url}|g" "${monitoring_dir}/prometheus.yml"
sed -i '/https:\/\/stg.example.com\/up/d' "${monitoring_dir}/prometheus.yml"

alert_webhook_default_url="${ALERT_WEBHOOK_DEFAULT_URL:-http://127.0.0.1:65535}"
alert_webhook_warning_url="${ALERT_WEBHOOK_WARNING_URL:-${alert_webhook_default_url}}"
alert_webhook_critical_url="${ALERT_WEBHOOK_CRITICAL_URL:-${alert_webhook_default_url}}"

safe_alert_webhook_default_url="$(printf '%s' "${alert_webhook_default_url}" | sed 's/[&/]/\\&/g')"
safe_alert_webhook_warning_url="$(printf '%s' "${alert_webhook_warning_url}" | sed 's/[&/]/\\&/g')"
safe_alert_webhook_critical_url="$(printf '%s' "${alert_webhook_critical_url}" | sed 's/[&/]/\\&/g')"

sed -i "s|\${ALERT_WEBHOOK_DEFAULT_URL}|${safe_alert_webhook_default_url}|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${ALERT_WEBHOOK_WARNING_URL}|${safe_alert_webhook_warning_url}|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${ALERT_WEBHOOK_CRITICAL_URL}|${safe_alert_webhook_critical_url}|g" "${monitoring_dir}/alertmanager.yml"

grafana_user="${GRAFANA_ADMIN_USER:-admin}"
grafana_pass="${GRAFANA_ADMIN_PASSWORD:-$(random_secret)}"
grafana_domain="${GRAFANA_DOMAIN:-grafana.${DOMAIN_LANDLORD}}"
prometheus_domain="${PROMETHEUS_DOMAIN:-prometheus.${DOMAIN_LANDLORD}}"
alertmanager_domain="${ALERTMANAGER_DOMAIN:-alerts.${DOMAIN_LANDLORD}}"
prometheus_retention="${PROMETHEUS_RETENTION:-15d}"
pgadmin_enabled="false"
pgadmin_domain="${PGADMIN_DOMAIN:-pgadmin.${DOMAIN_LANDLORD}}"
pgadmin_default_email="${PGADMIN_DEFAULT_EMAIL:-admin@${DOMAIN_LANDLORD}}"
pgadmin_default_password="${PGADMIN_DEFAULT_PASSWORD:-$(random_secret)}"

if [[ "${ENABLE_PGADMIN}" == "true" && "${APP_SLUG}" != "staging" && "${APP_SLUG}" != "dev" ]]; then
    log_warn "ENABLE_PGADMIN=true ignorado para APP_SLUG='${APP_SLUG}'. Permitido apenas em dev/staging."
    ENABLE_PGADMIN="false"
fi

if [[ "${ENABLE_PGADMIN}" == "true" ]]; then
    pgadmin_enabled="true"
fi

ensure_domain_resolves() {
    local host="$1"
    if getent ahosts "${host}" >/dev/null 2>&1; then
        log_success "DNS OK: ${host}"
        return
    fi
    log_error "DNS missing for ${host}. Create A/AAAA record before enabling monitoring to avoid ACME rate-limit."
    exit 1
}

ensure_domain_resolves "${grafana_domain}"
ensure_domain_resolves "${prometheus_domain}"
ensure_domain_resolves "${alertmanager_domain}"
if [[ "${pgadmin_enabled}" == "true" ]]; then
    ensure_domain_resolves "${pgadmin_domain}"
fi

write_file_secure "${monitoring_dir}/.env" "root:root" "600" "GRAFANA_DOMAIN=${grafana_domain}
PROMETHEUS_DOMAIN=${prometheus_domain}
ALERTMANAGER_DOMAIN=${alertmanager_domain}
APP_SLUG=${APP_SLUG}
GRAFANA_ADMIN_USER=${grafana_user}
GRAFANA_ADMIN_PASSWORD=${grafana_pass}
PROMETHEUS_RETENTION=${prometheus_retention}
ALERT_WEBHOOK_DEFAULT_URL=${alert_webhook_default_url}
ALERT_WEBHOOK_WARNING_URL=${alert_webhook_warning_url}
ALERT_WEBHOOK_CRITICAL_URL=${alert_webhook_critical_url}
ENABLE_PGADMIN=${pgadmin_enabled}
PGADMIN_DOMAIN=${pgadmin_domain}
PGADMIN_DEFAULT_EMAIL=${pgadmin_default_email}
PGADMIN_DEFAULT_PASSWORD=${pgadmin_default_password}
"

docker network create traefik-global >/dev/null 2>&1 || true

if [[ "${START_MONITORING}" == "true" ]]; then
    if [[ "${pgadmin_enabled}" == "true" ]]; then
        (cd "${monitoring_dir}" && docker compose -p "plannerate-monitoring-${APP_SLUG}" -f docker-compose.yml -f docker-compose.pgadmin.yml up -d)
    else
        (cd "${monitoring_dir}" && docker compose -p "plannerate-monitoring-${APP_SLUG}" up -d)
    fi
fi

log_success "Monitoring stack installed at ${monitoring_dir}"
log_info "Grafana URL: https://${grafana_domain}"
log_info "Prometheus URL: https://${prometheus_domain}"
log_info "Alertmanager URL: https://${alertmanager_domain}"
if [[ "${pgadmin_enabled}" == "true" ]]; then
    log_info "pgAdmin URL: https://${pgadmin_domain}"
fi
