#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../provisioning/common.sh"

MANIFEST_PATH="${1:-}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./vps-health-check.sh /path/to/manifest.env"
    exit 1
fi

load_manifest "${MANIFEST_PATH}"
require_commands docker curl df free awk grep date

fail_count=0
warn_count=0

ok() {
    echo "[OK] $*"
}

warn() {
    echo "[WARN] $*"
    warn_count=$((warn_count + 1))
}

fail() {
    echo "[FAIL] $*"
    fail_count=$((fail_count + 1))
}

check_compose_service_running() {
    local compose_dir="$1"
    local service_name="$2"

    if [[ ! -f "${compose_dir}/docker-compose.yml" ]]; then
        fail "Missing compose file in ${compose_dir}"
        return
    fi

    if docker compose -f "${compose_dir}/docker-compose.yml" ps --status running --services | grep -q "^${service_name}$"; then
        ok "${compose_dir} service ${service_name} is running"
    else
        fail "${compose_dir} service ${service_name} is not running"
    fi
}

check_http() {
    local url="$1"
    local label="$2"

    if curl -fsS --max-time 8 "${url}" >/dev/null; then
        ok "${label} reachable (${url})"
    else
        fail "${label} unreachable (${url})"
    fi
}

echo "=== VPS Health Check ==="
echo "Time: $(date -Iseconds)"

echo "--- Docker daemon ---"
if docker info >/dev/null 2>&1; then
    ok "Docker daemon reachable"
else
    fail "Docker daemon not reachable"
fi

echo "--- Core stacks ---"
check_compose_service_running "/opt/traefik" "traefik"
check_compose_service_running "/opt/production" "app"
check_compose_service_running "/opt/production" "queue"
check_compose_service_running "/opt/production" "scheduler"
check_compose_service_running "/opt/production" "reverb"
check_compose_service_running "/opt/staging" "app"
check_compose_service_running "/opt/staging" "queue"
check_compose_service_running "/opt/staging" "scheduler"
check_compose_service_running "/opt/staging" "reverb"

if [[ -f "/opt/monitoring/docker-compose.yml" ]]; then
    check_compose_service_running "/opt/monitoring" "prometheus"
    check_compose_service_running "/opt/monitoring" "grafana"
    check_compose_service_running "/opt/monitoring" "alertmanager"
    check_compose_service_running "/opt/monitoring" "node-exporter"
else
    warn "Monitoring stack not installed in /opt/monitoring"
fi

echo "--- HTTP health endpoints ---"
check_http "https://${DOMAIN_PRODUCTION}/up" "Production app"
check_http "https://${DOMAIN_STAGING}/up" "Staging app"

if [[ -n "${GRAFANA_DOMAIN:-}" ]]; then
    check_http "https://${GRAFANA_DOMAIN}" "Grafana"
fi

if [[ -n "${PROMETHEUS_DOMAIN:-}" ]]; then
    check_http "https://${PROMETHEUS_DOMAIN}/-/ready" "Prometheus"
fi

if [[ -n "${ALERTMANAGER_DOMAIN:-}" ]]; then
    check_http "https://${ALERTMANAGER_DOMAIN}/-/ready" "Alertmanager"
fi

echo "--- Backup and cron ---"
if crontab -l 2>/dev/null | grep -q "run-backup-all.sh"; then
    ok "Backup cron installed"
else
    fail "Backup cron not installed"
fi

latest_backup=$(find /opt/backups/db -type f -name '*.sql.gz' -printf '%T@ %p\n' 2>/dev/null | sort -n | tail -1 | awk '{print $2}')
if [[ -n "${latest_backup:-}" ]]; then
    ok "Latest local backup: ${latest_backup}"
else
    warn "No local backup file found in /opt/backups/db"
fi

echo "--- Host resources ---"
disk_used_pct=$(df -P / | awk 'NR==2{gsub(/%/,"",$5); print $5}')
if [[ -n "${disk_used_pct}" && "${disk_used_pct}" -ge 90 ]]; then
    fail "Disk usage critical on / (${disk_used_pct}%)"
elif [[ -n "${disk_used_pct}" && "${disk_used_pct}" -ge 80 ]]; then
    warn "Disk usage high on / (${disk_used_pct}%)"
else
    ok "Disk usage healthy on / (${disk_used_pct}%)"
fi

mem_used_pct=$(free | awk '/Mem:/ {printf("%d", ($3/$2)*100)}')
if [[ -n "${mem_used_pct}" && "${mem_used_pct}" -ge 95 ]]; then
    fail "Memory usage critical (${mem_used_pct}%)"
elif [[ -n "${mem_used_pct}" && "${mem_used_pct}" -ge 85 ]]; then
    warn "Memory usage high (${mem_used_pct}%)"
else
    ok "Memory usage healthy (${mem_used_pct}%)"
fi

echo "--- Summary ---"
echo "Warnings: ${warn_count}"
echo "Failures: ${fail_count}"

if [[ "${fail_count}" -gt 0 ]]; then
    exit 2
fi

if [[ "${warn_count}" -gt 0 ]]; then
    exit 1
fi

exit 0
