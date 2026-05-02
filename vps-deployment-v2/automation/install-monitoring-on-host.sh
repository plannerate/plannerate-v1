#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${ROOT_DIR}/provisioning/common.sh"

MANIFEST_PATH="${1:-}"
START_MONITORING="${START_MONITORING:-true}"
APP_SLUG="${APP_SLUG:-${2:-staging}}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./install-monitoring-on-host.sh /path/to/manifest.env [app-slug]"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"
require_commands install cp sed docker

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
grafana_domain="grafana.${DOMAIN_LANDLORD}"
prometheus_domain="prometheus.${DOMAIN_LANDLORD}"
alertmanager_domain="alerts.${DOMAIN_LANDLORD}"
prometheus_retention="${PROMETHEUS_RETENTION:-15d}"

write_file_secure "${monitoring_dir}/.env" "root:root" "600" "GRAFANA_DOMAIN=${grafana_domain}
PROMETHEUS_DOMAIN=${prometheus_domain}
ALERTMANAGER_DOMAIN=${alertmanager_domain}
GRAFANA_ADMIN_USER=${grafana_user}
GRAFANA_ADMIN_PASSWORD=${grafana_pass}
PROMETHEUS_RETENTION=${prometheus_retention}
ALERT_WEBHOOK_DEFAULT_URL=${alert_webhook_default_url}
ALERT_WEBHOOK_WARNING_URL=${alert_webhook_warning_url}
ALERT_WEBHOOK_CRITICAL_URL=${alert_webhook_critical_url}
"

docker network create traefik-global >/dev/null 2>&1 || true

if [[ "${START_MONITORING}" == "true" ]]; then
    (cd "${monitoring_dir}" && docker compose -p "plannerate-monitoring-${APP_SLUG}" up -d)
fi

log_success "Monitoring stack installed at ${monitoring_dir}"
log_info "Grafana URL: https://${grafana_domain}"
log_info "Prometheus URL: https://${prometheus_domain}"
log_info "Alertmanager URL: https://${alertmanager_domain}"
