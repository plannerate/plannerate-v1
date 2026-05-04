#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../provisioning/common.sh"

MANIFEST_PATH="${1:-}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    log_error "Usage: ./bootstrap-github.sh /path/to/manifest.env"
    exit 1
fi

load_manifest "${MANIFEST_PATH}"
require_commands gh ssh-keygen ssh-keyscan

DOMAIN_LANDLORD="${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-${DOMAIN_PRODUCTION:-}}}"
APP_SLUG="${APP_SLUG:-staging}"
VPS_DEPLOY_USER="${VPS_DEPLOY_USER:-${DEPLOY_USER:-}}"

required_vars=(
    GITHUB_OWNER
    GITHUB_REPO_NAME
    VPS_HOST
    VPS_DEPLOY_USER
    GHCR_REPO
)

for var_name in "${required_vars[@]}"; do
    if [[ -z "${!var_name:-}" ]]; then
        log_error "Missing required variable in manifest: ${var_name}"
        exit 1
    fi
done

if [[ -z "${DOMAIN_LANDLORD}" ]]; then
    log_error "Missing required domain variable: DOMAIN_LANDLORD (or DOMAIN_STAGING fallback)."
    exit 1
fi

repo="${GITHUB_OWNER}/${GITHUB_REPO_NAME}"
key_dir="${HOME}/.ssh"
priv_key_path="${key_dir}/id_ed25519_${GITHUB_REPO_NAME}_deploy"
pub_key_path="${priv_key_path}.pub"

log_info "Verificando autenticação no GitHub CLI (gh) — precisa estar logado"
gh auth status >/dev/null

mkdir -p "${key_dir}"
chmod 700 "${key_dir}"

if [[ ! -f "${priv_key_path}" ]]; then
    log_info "Gerando chave de deploy ed25519 para o repositório ${repo}"
    ssh-keygen -t ed25519 -f "${priv_key_path}" -N "" -C "${repo}-deploy"
else
    log_info "Chave de deploy já existe em ${priv_key_path} — reutilizando"
fi

log_info "Fazendo upload da chave pública para o repositório no GitHub"
gh repo deploy-key add "${pub_key_path}" --repo "${repo}" --title "deploy-key-vps-v2" --allow-write >/dev/null 2>&1 || true

log_info "Criando ambiente 'staging' no GitHub (se já existir, sem problema)"
gh api --method PUT "repos/${repo}/environments/staging" >/dev/null

log_info "Coletando known_hosts da VPS ${VPS_HOST} — necessário pro GitHub Actions não reclamar de host desconhecido"
known_hosts=$(ssh-keyscan -H "${VPS_HOST}" 2>/dev/null)

set_secret() {
    local secret_name="$1"
    local secret_value="$2"
    local env_name="${3:-}"

    if [[ -n "${env_name}" ]]; then
        gh secret set "${secret_name}" --repo "${repo}" --env "${env_name}" --body "${secret_value}"
    else
        gh secret set "${secret_name}" --repo "${repo}" --body "${secret_value}"
    fi
}

set_var() {
    local var_name="$1"
    local var_value="$2"
    local env_name="${3:-}"

    if [[ -n "${env_name}" ]]; then
        gh variable set "${var_name}" --repo "${repo}" --env "${env_name}" --body "${var_value}"
    else
        gh variable set "${var_name}" --repo "${repo}" --body "${var_value}"
    fi
}

log_info "Definindo variável compartilhada: GHCR_REPO (repositório de imagens Docker)"
set_var "GHCR_REPO" "${GHCR_REPO}"

log_info "Configurando secrets do ambiente 'staging' no GitHub — IP, usuário, chave SSH e domínio"
set_secret "APP_HOST" "${VPS_HOST}" "staging"
set_secret "APP_USER" "${VPS_DEPLOY_USER}" "staging"
set_secret "SSH_PRIVATE_KEY" "$(cat "${priv_key_path}")" "staging"
set_secret "SSH_KNOWN_HOSTS" "${known_hosts}" "staging"
set_secret "DOMAIN" "${DOMAIN_LANDLORD}" "staging"

log_info "Definindo variáveis do ambiente 'staging' — caminho de deploy e arquivo compose"
set_var "DEPLOY_PATH" "/opt/plannerate/${APP_SLUG}" "staging"
set_var "COMPOSE_FILE" "docker-compose.staging.yml" "staging"

log_success "GitHub configurado! O Actions já consegue fazer deploy via SSH."
log_info "Chave privada local: ${priv_key_path}"
