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
    if ! MANIFEST_PATH="$(find_manifest "${ROOT_DIR}")"; then
        log_error "Nenhum manifest encontrado. Passe: ./install-monitoring-on-host.sh /path/to/manifest.env [app-slug]"
        exit 1
    fi
    log_info "Usando manifest: ${MANIFEST_PATH}"
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

# Provisionamento do Grafana (datasource + dashboards). Sem isto, o Grafana sobe
# sem datasource nem dashboards por arquivo (bug histórico: a pasta não era copiada).
rm -rf "${monitoring_dir}/grafana"
cp -r "${ROOT_DIR}/deployments/monitoring/grafana" "${monitoring_dir}/grafana"

app_url="https://${DOMAIN_LANDLORD}/up"
sed -i "s|https://app.example.com/up|${app_url}|g" "${monitoring_dir}/prometheus.yml"
sed -i '/https:\/\/stg.example.com\/up/d' "${monitoring_dir}/prometheus.yml"

# Alertas por e-mail (Alertmanager email_configs).
smtp_host="${SMTP_HOST:-}"
smtp_port="${SMTP_PORT:-587}"
smtp_from="${SMTP_FROM:-alerts@${DOMAIN_LANDLORD}}"
smtp_user="${SMTP_USER:-${smtp_from}}"
smtp_pass="${SMTP_PASS:-}"
smtp_require_tls="${SMTP_REQUIRE_TLS:-true}"
alert_email_to="${ALERT_EMAIL_TO:-}"
alert_email_critical_to="${ALERT_EMAIL_CRITICAL_TO:-${alert_email_to}}"

if [[ -z "${smtp_host}" || -z "${alert_email_to}" ]]; then
    log_warn "SMTP_HOST/ALERT_EMAIL_TO ausentes no manifest — Alertmanager subirá, mas os alertas NÃO serão entregues por e-mail até você preencher essas variáveis e reinstalar."
fi

# Escapa string para uso seguro como replacement do sed (delimitador '|').
escape_sed_repl() { printf '%s' "$1" | sed -e 's/[\\&|]/\\&/g'; }

sed -i "s|\${SMTP_HOST}|$(escape_sed_repl "${smtp_host}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${SMTP_PORT}|$(escape_sed_repl "${smtp_port}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${SMTP_FROM}|$(escape_sed_repl "${smtp_from}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${SMTP_USER}|$(escape_sed_repl "${smtp_user}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${SMTP_PASS}|$(escape_sed_repl "${smtp_pass}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${SMTP_REQUIRE_TLS}|$(escape_sed_repl "${smtp_require_tls}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${ALERT_EMAIL_TO}|$(escape_sed_repl "${alert_email_to}")|g" "${monitoring_dir}/alertmanager.yml"
sed -i "s|\${ALERT_EMAIL_CRITICAL_TO}|$(escape_sed_repl "${alert_email_critical_to}")|g" "${monitoring_dir}/alertmanager.yml"

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
    log_warn "DNS missing for ${host}. Create an A record pointing to this VPS before accessing monitoring — Traefik will retry ACME automatically once DNS propagates."
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
SMTP_HOST=${smtp_host}
SMTP_PORT=${smtp_port}
SMTP_FROM=${smtp_from}
SMTP_USER=${smtp_user}
SMTP_PASS=${smtp_pass}
SMTP_REQUIRE_TLS=${smtp_require_tls}
ALERT_EMAIL_TO=${alert_email_to}
ALERT_EMAIL_CRITICAL_TO=${alert_email_critical_to}
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
