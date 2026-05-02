#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/common.sh"

MANIFEST_PATH="${1:-}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./validate-prereqs.sh /path/to/manifest.env"
    exit 1
fi

load_manifest "${MANIFEST_PATH}"
require_commands awk grep sed ss df curl openssl
ensure_linux_ubuntu

MIN_DISK_GB="${MIN_DISK_GB:-20}"
SSH_PORT="${SSH_PORT:-22}"
DOMAIN_LANDLORD="${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-${DOMAIN_PRODUCTION:-}}}"

required_manifest_vars=(
    PROJECT_NAME
    DEPLOY_USER
    GHCR_REPO
)

for var_name in "${required_manifest_vars[@]}"; do
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required variable in manifest: ${var_name}"
        exit 1
    fi
done

if [[ -z "${DOMAIN_LANDLORD}" ]]; then
    log_error "Missing domain variable: DOMAIN_LANDLORD (or legacy DOMAIN_STAGING/DOMAIN_PRODUCTION)."
    exit 1
fi

available_kb=$(df --output=avail / | tail -n1 | awk '{print $1}')
required_kb=$((MIN_DISK_GB * 1024 * 1024))
if (( available_kb < required_kb )); then
    log_error "Not enough disk space. Required ${MIN_DISK_GB}GB on root volume."
    exit 1
fi
log_success "Disk check passed"

if ss -tuln | grep -q ':80 '; then
    log_warn "Port 80 is in use. Ensure this is expected before provisioning Traefik."
fi

if ss -tuln | grep -q ':443 '; then
    log_warn "Port 443 is in use. Ensure this is expected before provisioning Traefik."
fi

if ! ss -tuln | grep -q ":${SSH_PORT} "; then
    log_warn "SSH port ${SSH_PORT} is not currently listening."
fi

if ! curl -fsSL https://ghcr.io >/dev/null 2>&1; then
    log_warn "Cannot reach ghcr.io. Image pulls may fail later."
else
    log_success "Outbound access to GHCR confirmed"
fi

log_success "Validation completed"
