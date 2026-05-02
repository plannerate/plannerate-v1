#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${ROOT_DIR}/provisioning/common.sh"

MANIFEST_PATH="${1:-}"
START_MONITORING="${START_MONITORING:-true}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./install-monitoring-on-host.sh /path/to/manifest.env"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"
require_commands install cp sed docker

mkdir -p /opt/monitoring
cp "${ROOT_DIR}/deployments/monitoring/docker-compose.yml" /opt/monitoring/docker-compose.yml
cp "${ROOT_DIR}/deployments/monitoring/prometheus.yml" /opt/monitoring/prometheus.yml
cp "${ROOT_DIR}/deployments/monitoring/alerts.yml" /opt/monitoring/alerts.yml
cp "${ROOT_DIR}/deployments/monitoring/blackbox.yml" /opt/monitoring/blackbox.yml
cp "${ROOT_DIR}/deployments/monitoring/alertmanager.yml" /opt/monitoring/alertmanager.yml

prod_url="https://${DOMAIN_PRODUCTION}/up"
stg_url="https://${DOMAIN_STAGING}/up"

# Render healthcheck targets for current environment domains.
sed -i "s|https://app.example.com/up|${prod_url}|g" /opt/monitoring/prometheus.yml
sed -i "s|https://stg.example.com/up|${stg_url}|g" /opt/monitoring/prometheus.yml

alert_webhook_default_url="${ALERT_WEBHOOK_DEFAULT_URL:-http://127.0.0.1:65535}"
alert_webhook_warning_url="${ALERT_WEBHOOK_WARNING_URL:-${alert_webhook_default_url}}"
alert_webhook_critical_url="${ALERT_WEBHOOK_CRITICAL_URL:-${alert_webhook_default_url}}"

safe_alert_webhook_default_url="$(printf '%s' "${alert_webhook_default_url}" | sed 's/[&/]/\\&/g')"
safe_alert_webhook_warning_url="$(printf '%s' "${alert_webhook_warning_url}" | sed 's/[&/]/\\&/g')"
safe_alert_webhook_critical_url="$(printf '%s' "${alert_webhook_critical_url}" | sed 's/[&/]/\\&/g')"

sed -i "s|\${ALERT_WEBHOOK_DEFAULT_URL}|${safe_alert_webhook_default_url}|g" /opt/monitoring/alertmanager.yml
sed -i "s|\${ALERT_WEBHOOK_WARNING_URL}|${safe_alert_webhook_warning_url}|g" /opt/monitoring/alertmanager.yml
sed -i "s|\${ALERT_WEBHOOK_CRITICAL_URL}|${safe_alert_webhook_critical_url}|g" /opt/monitoring/alertmanager.yml

if [[ ! -f /opt/monitoring/.env ]]; then
    grafana_user="${GRAFANA_ADMIN_USER:-admin}"
    grafana_pass="${GRAFANA_ADMIN_PASSWORD:-$(random_secret)}"
    grafana_domain="${GRAFANA_DOMAIN:-grafana.${DOMAIN_PRODUCTION}}"
    prometheus_retention="${PROMETHEUS_RETENTION:-15d}"
    prometheus_domain="${PROMETHEUS_DOMAIN:-prometheus.${DOMAIN_PRODUCTION}}"
    alertmanager_domain="${ALERTMANAGER_DOMAIN:-alerts.${DOMAIN_PRODUCTION}}"
    alert_webhook_default_url="${ALERT_WEBHOOK_DEFAULT_URL:-}"
    alert_webhook_warning_url="${ALERT_WEBHOOK_WARNING_URL:-${alert_webhook_default_url}}"
    alert_webhook_critical_url="${ALERT_WEBHOOK_CRITICAL_URL:-${alert_webhook_default_url}}"

    write_file_secure "/opt/monitoring/.env" "root:root" "600" "GRAFANA_DOMAIN=${grafana_domain}
PROMETHEUS_DOMAIN=${prometheus_domain}
ALERTMANAGER_DOMAIN=${alertmanager_domain}
GRAFANA_ADMIN_USER=${grafana_user}
GRAFANA_ADMIN_PASSWORD=${grafana_pass}
PROMETHEUS_RETENTION=${prometheus_retention}
ALERT_WEBHOOK_DEFAULT_URL=${alert_webhook_default_url}
ALERT_WEBHOOK_WARNING_URL=${alert_webhook_warning_url}
ALERT_WEBHOOK_CRITICAL_URL=${alert_webhook_critical_url}
"
fi

docker network create traefik-global >/dev/null 2>&1 || true

if [[ "${START_MONITORING}" == "true" ]]; then
    (cd /opt/monitoring && docker compose up -d)
fi

log_success "Monitoring stack installed at /opt/monitoring"
log_info "Grafana URL: https://${GRAFANA_DOMAIN:-grafana.${DOMAIN_PRODUCTION}}"
