#!/usr/bin/env bash
# switch-to-deploy-user.sh — optional post-provisioning step
#
# Migrates from root-only access to a dedicated deploy user.
# Run this on the VPS AFTER the app is confirmed working as root.
#
# Usage (from your local machine):
#   ssh plannerate-v2-vps 'bash -s' < automation/switch-to-deploy-user.sh /root/manifest.env
#
# Or upload manifest first:
#   scp vps-deployment-v2/manifest.env plannerate-v2-vps:/root/manifest.env
#   ssh plannerate-v2-vps bash /root/switch-to-deploy-user.sh /root/manifest.env

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
source "${SCRIPT_DIR}/../provisioning/common.sh"

MANIFEST_PATH="${1:-}"
if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./switch-to-deploy-user.sh /path/to/manifest.env"
    exit 1
fi

require_root
load_manifest "${MANIFEST_PATH}"

DEPLOY_USER="${DEPLOY_USER:-deploy}"
if [[ "${DEPLOY_USER}" == "root" ]]; then
    log_error "DEPLOY_USER=root in manifest — update it to the desired username before running this script."
    exit 1
fi

DEPLOY_HOME="/home/${DEPLOY_USER}"
GITHUB_DEPLOY_PUBLIC_KEY="${GITHUB_DEPLOY_PUBLIC_KEY:-}"
APP_SLUG="${APP_SLUG:-staging}"
APP_DIR="/opt/plannerate/${APP_SLUG}"

log_info "Creating user: ${DEPLOY_USER}"
if ! id "${DEPLOY_USER}" >/dev/null 2>&1; then
    useradd -m -s /bin/bash "${DEPLOY_USER}"
    log_info "User ${DEPLOY_USER} created"
else
    log_info "User ${DEPLOY_USER} already exists — skipping creation"
fi

if [[ -n "${DEPLOY_USER_PASS:-}" ]]; then
    printf '%s:%s\n' "${DEPLOY_USER}" "${DEPLOY_USER_PASS}" | chpasswd
    log_info "Password set for ${DEPLOY_USER} (console access only)"
fi

log_info "Adding ${DEPLOY_USER} to docker group"
usermod -aG docker "${DEPLOY_USER}"

log_info "Setting up SSH authorized_keys for ${DEPLOY_USER}"
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${DEPLOY_HOME}/.ssh"
touch "${DEPLOY_HOME}/.ssh/authorized_keys"
chown "${DEPLOY_USER}:${DEPLOY_USER}" "${DEPLOY_HOME}/.ssh/authorized_keys"
chmod 600 "${DEPLOY_HOME}/.ssh/authorized_keys"

if [[ -n "${GITHUB_DEPLOY_PUBLIC_KEY}" ]]; then
    if ! grep -Fq "${GITHUB_DEPLOY_PUBLIC_KEY}" "${DEPLOY_HOME}/.ssh/authorized_keys"; then
        printf '%s\n' "${GITHUB_DEPLOY_PUBLIC_KEY}" >> "${DEPLOY_HOME}/.ssh/authorized_keys"
        log_info "Deploy key added to ${DEPLOY_USER} authorized_keys"
    else
        log_info "Deploy key already present in ${DEPLOY_USER} authorized_keys"
    fi
else
    log_warn "GITHUB_DEPLOY_PUBLIC_KEY not set — authorized_keys left empty"
fi

log_info "Granting passwordless sudo to ${DEPLOY_USER}"
printf '%s ALL=(ALL) NOPASSWD:ALL\n' "${DEPLOY_USER}" > /etc/sudoers.d/vps-v2-deploy
chmod 440 /etc/sudoers.d/vps-v2-deploy
visudo -cf /etc/sudoers.d/vps-v2-deploy || {
    rm -f /etc/sudoers.d/vps-v2-deploy
    log_error "sudoers inválido — revertido"
    exit 1
}

log_info "Transferring ownership of ${APP_DIR} to ${DEPLOY_USER}"
if [[ -d "${APP_DIR}" ]]; then
    chown -R "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}"
    log_info "Ownership transferred"
else
    log_warn "${APP_DIR} not found — skipping chown"
fi

log_info "Hardening SSH — disabling root login and password auth"
SSHD_CFG="/etc/ssh/sshd_config"
SSHD_BACKUP="/etc/ssh/sshd_config.bak-before-switch"
cp "${SSHD_CFG}" "${SSHD_BACKUP}"
grep -Ev '^#?\s*(PermitRootLogin|PasswordAuthentication|MaxAuthTries)\b' "${SSHD_BACKUP}" > "${SSHD_CFG}"
printf '\n# Added by switch-to-deploy-user.sh\nPermitRootLogin no\nPasswordAuthentication no\nMaxAuthTries 3\n' >> "${SSHD_CFG}"
if ! sshd -t -f "${SSHD_CFG}"; then
    log_error "sshd_config inválido — restaurando backup"
    cp "${SSHD_BACKUP}" "${SSHD_CFG}"
    exit 1
fi
systemctl restart ssh 2>/dev/null || systemctl restart sshd
log_warn "Root SSH login is now DISABLED. Use '${DEPLOY_USER}' for future connections."

log_success "Migration to deploy user '${DEPLOY_USER}' complete!"
log_info ""
log_info "Next steps on your LOCAL machine:"
log_info "  1. Update ~/.ssh/config — change 'User root' to 'User ${DEPLOY_USER}' for ${VPS_HOST:-<VPS_HOST>}"
log_info "  2. Test: ssh ${DEPLOY_USER}@${VPS_HOST:-<VPS_HOST>} echo ok"
log_info "  3. Update manifest.env: DEPLOY_USER=${DEPLOY_USER}, VPS_DEPLOY_USER=${DEPLOY_USER}"
log_info "  4. Update GitHub secret APP_USER=${DEPLOY_USER} for your environment"
