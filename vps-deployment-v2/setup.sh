#!/usr/bin/env bash
# setup.sh — Wizard interativo (multi-app por APP_SLUG)
# Execute: bash vps-deployment-v2/setup.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MANIFEST_OUT="${SCRIPT_DIR}/manifest.env"
REMOTE_WORKDIR="/tmp/vps-deployment-v2"

if [[ -f "${MANIFEST_OUT}" ]]; then
    # shellcheck disable=SC1090
    set -a; source "${MANIFEST_OUT}"; set +a || true
fi

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; DIM='\033[2m'; RESET='\033[0m'
step() { echo -e "\n${BOLD}${CYAN}▶ $*${RESET}"; }
info() { echo -e "  ${DIM}$*${RESET}"; }
ok() { echo -e "  ${GREEN}✔ $*${RESET}"; }
warn() { echo -e "  ${YELLOW}⚠ $*${RESET}"; }

ask() {
    local var_name="$1" prompt="$2" default="${3:-}"
    local display_default=""
    [[ -n "$default" ]] && display_default=" ${DIM}[${default}]${RESET}"
    echo -ne "  ${BOLD}${prompt}${RESET}${display_default}: "
    read -r input
    input="${input:-$default}"
    while [[ -z "$input" ]]; do
        echo -ne "  ${RED}Obrigatório.${RESET} ${BOLD}${prompt}${RESET}: "
        read -r input
    done
    printf -v "$var_name" '%s' "$input"
}

ask_secret_default() {
    local var_name="$1" prompt="$2" default="${3:-}"
    echo -ne "  ${BOLD}${prompt}${RESET} ${DIM}[ENTER para manter]${RESET}: "
    read -rs input
    echo ""
    input="${input:-$default}"
    while [[ -z "$input" ]]; do
        echo -ne "  ${RED}Obrigatório.${RESET} ${BOLD}${prompt}${RESET}: "
        read -rs input
        echo ""
    done
    printf -v "$var_name" '%s' "$input"
}

ask_yn() {
    local prompt="$1" default="${2:-s}" opts="S/n"
    [[ "$default" == "n" ]] && opts="s/N"
    echo -ne "  ${BOLD}${prompt}${RESET} ${DIM}[${opts}]${RESET}: "
    read -r yn
    yn="${yn:-$default}"
    [[ "${yn,,}" == "s" || "${yn,,}" == "y" ]]
}

ask_choice() {
    local var_name="$1" prompt="$2" default="$3"
    shift 3
    local options=("$@")
    local options_label
    options_label="$(IFS='/'; echo "${options[*]}")"
    local input

    while true; do
        echo -ne "  ${BOLD}${prompt}${RESET} ${DIM}[${options_label}] (padrão: ${default})${RESET}: "
        read -r input
        input="${input:-$default}"
        for option in "${options[@]}"; do
            if [[ "${input}" == "${option}" ]]; then
                printf -v "${var_name}" '%s' "${input}"
                return 0
            fi
        done
        warn "Opção inválida: ${input}"
    done
}

refresh_known_host() {
    local host="$1"

    if [[ -z "${host}" ]]; then
        return 0
    fi

    ssh-keygen -R "${host}" -f "${HOME}/.ssh/known_hosts" >/dev/null 2>&1 || true
    ssh-keygen -R "[${host}]:22" -f "${HOME}/.ssh/known_hosts" >/dev/null 2>&1 || true
}

random_secret() { openssl rand -base64 48 | tr -d '=+/' | cut -c1-40; }
emit_manifest_var() { printf '%s=%q\n' "$1" "${2:-}"; }

clear
step "VPS Deployment v2 — Multi-app por APP_SLUG"
info "Fluxo ativo padrão: dev -> staging"
info "Você pode criar novas apps trocando APP_SLUG + domínio"

step "Projeto e domínio"
ask PROJECT_NAME "Nome do projeto" "${PROJECT_NAME:-plannerate}"
ask APP_SLUG "Nome da app/pasta (slug)" "${APP_SLUG:-staging}"
ask GITHUB_OWNER "GitHub org/usuário" "${GITHUB_OWNER:-}"
ask GITHUB_REPO "Nome do repositório" "${GITHUB_REPO_NAME:-${PROJECT_NAME}}"
ask GHCR_REPO "Imagem GHCR" "${GHCR_REPO:-${GITHUB_OWNER}/${GITHUB_REPO}}"
ask DOMAIN_LANDLORD "Domínio landlord (raiz)" "${DOMAIN_LANDLORD:-${DOMAIN_STAGING:-}}"
ask ACME_EMAIL "E-mail para Let's Encrypt" "${ACME_EMAIL:-}"

step "VPS e banco"
ask VPS_HOST "IP do App VPS" "${VPS_HOST:-}"
ask VPS_USER "Usuário SSH root no VPS" "${VPS_USER:-root}"
ask DEPLOY_USER "Usuário de deploy" "${DEPLOY_USER:-deploy}"
ask_choice DB_MODE "Banco é local na mesma VPS ou externo?" "${DB_MODE:-local}" local externo
ask DB_ENGINE "Engine (pgsql|mysql)" "${DB_ENGINE:-${DB_ENGINE_STAGING:-pgsql}}"

if [[ "${DB_MODE}" == "local" ]]; then
    DB_HOST="${DB_HOST:-host.docker.internal}"
    if [[ "${DB_ENGINE}" == "pgsql" ]]; then
        DB_PORT="${DB_PORT:-5432}"
    else
        DB_PORT="${DB_PORT:-3306}"
    fi
    DB_ROOT_USER="${DB_ROOT_USER:-root}"
    DB_ROOT_PASS="${DB_ROOT_PASS:-}"
else
    ask DB_HOST "IP/host do banco externo" "${DB_HOST:-${DB_HOST_STAGING:-}}"
    if [[ "${DB_ENGINE}" == "pgsql" ]]; then
        ask DB_PORT "Porta DB externa" "${DB_PORT:-5432}"
    else
        ask DB_PORT "Porta DB externa" "${DB_PORT:-3306}"
    fi
    ask DB_ROOT_USER "Usuário admin do banco externo (referência)" "${DB_ROOT_USER:-root}"
    ask_secret_default DB_ROOT_PASS "Senha admin do banco externo (referência)" "${DB_ROOT_PASS:-}"
fi
ask DB_NAME "Nome do banco (${APP_SLUG})" "${DB_NAME:-${DB_NAME_STAGING:-${PROJECT_NAME}_${APP_SLUG}}}"
if [[ "${DB_ENGINE}" == "pgsql" ]]; then
    DB_USER_DEFAULT="${DB_USER:-${DB_USER_STAGING:-${DB_NAME}}}"
else
    DB_USER_DEFAULT="${DB_USER:-${DB_USER_STAGING:-${PROJECT_NAME}_${APP_SLUG}_user}}"
fi
ask DB_USER "Usuário DB (${APP_SLUG})" "${DB_USER_DEFAULT}"
ask_secret_default DB_PASSWORD "Senha DB (${APP_SLUG})" "${DB_PASSWORD:-${DB_PASSWORD_STAGING:-}}"

DB_TENANT_DATABASE="${DB_TENANT_DATABASE:-${DB_NAME}}"

if [[ "${DB_MODE}" == "externo" ]]; then
    step "Configuração manual do banco externo"
    echo "  Configure no banco externo antes de continuar:"
    echo "  - Engine: ${DB_ENGINE}"
    echo "  - Host: ${DB_HOST}"
    echo "  - Port: ${DB_PORT}"
    echo "  - Database: ${DB_NAME}"
    echo "  - Username: ${DB_USER}"
    echo "  - Password: ${DB_PASSWORD}"
    read -r -p "Pressione ENTER após concluir a configuração no banco externo..."
fi

step "Chave SSH deploy"
KEY_DIR="${HOME}/.ssh"
KEY_PATH="${KEY_DIR}/id_ed25519_${GITHUB_REPO}_deploy"
mkdir -p "${KEY_DIR}" && chmod 700 "${KEY_DIR}"
if [[ ! -f "$KEY_PATH" ]]; then
    ssh-keygen -t ed25519 -f "$KEY_PATH" -N "" -C "${GITHUB_OWNER}/${GITHUB_REPO}-deploy" -q
fi
refresh_known_host "${VPS_HOST}"
DEPLOY_PUBLIC_KEY="$(cat "${KEY_PATH}.pub")"
DEPLOY_PRIVATE_KEY="$(cat "${KEY_PATH}")"
VPS_KNOWN_HOSTS="$(ssh-keyscan -H "${VPS_HOST}" 2>/dev/null || true)"
ok "Chave pronta: ${KEY_PATH}"

step "Adicione a deploy key no GitHub"
echo "https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/settings/keys/new"
echo "${DEPLOY_PUBLIC_KEY}"
read -r -p "Pressione ENTER após salvar a chave..."

step "Configurar GitHub Secrets (staging)"
if command -v gh &>/dev/null && gh auth status &>/dev/null 2>&1; then
    repo="${GITHUB_OWNER}/${GITHUB_REPO}"
    gh api --method PUT "repos/${repo}/environments/staging" --silent >/dev/null 2>&1 || true
    gh secret set APP_HOST --repo "$repo" --env staging --body "$VPS_HOST" >/dev/null
    gh secret set APP_USER --repo "$repo" --env staging --body "$DEPLOY_USER" >/dev/null
    gh secret set SSH_PRIVATE_KEY --repo "$repo" --env staging --body "$DEPLOY_PRIVATE_KEY" >/dev/null
    gh secret set SSH_KNOWN_HOSTS --repo "$repo" --env staging --body "$VPS_KNOWN_HOSTS" >/dev/null
    gh secret set DOMAIN --repo "$repo" --env staging --body "$DOMAIN_LANDLORD" >/dev/null
    gh variable set GHCR_REPO --repo "$repo" --body "$GHCR_REPO" >/dev/null
    gh variable set DEPLOY_PATH --repo "$repo" --env staging --body "/opt/plannerate/${APP_SLUG}" >/dev/null
    gh variable set COMPOSE_FILE --repo "$repo" --env staging --body "docker-compose.staging.yml" >/dev/null
    ok "Secrets/variables de staging configurados"
else
    warn "gh CLI não autenticado; configure secrets manualmente depois."
fi

step "Salvar manifest.env"
REDIS_PASSWORD="${REDIS_PASSWORD:-${REDIS_PASSWORD_STAGING:-$(random_secret)}}"
{
    emit_manifest_var PROJECT_NAME "$PROJECT_NAME"
    emit_manifest_var APP_SLUG "$APP_SLUG"
    emit_manifest_var GHCR_REPO "$GHCR_REPO"
    emit_manifest_var GITHUB_OWNER "$GITHUB_OWNER"
    emit_manifest_var GITHUB_REPO_NAME "$GITHUB_REPO"
    emit_manifest_var DOMAIN_LANDLORD "$DOMAIN_LANDLORD"
    emit_manifest_var ACME_EMAIL "$ACME_EMAIL"
    emit_manifest_var VPS_HOST "$VPS_HOST"
    emit_manifest_var VPS_USER "$VPS_USER"
    emit_manifest_var DEPLOY_USER "$DEPLOY_USER"
    emit_manifest_var VPS_DEPLOY_USER "$DEPLOY_USER"
    emit_manifest_var DB_MODE "$DB_MODE"
    emit_manifest_var GITHUB_DEPLOY_PUBLIC_KEY "$DEPLOY_PUBLIC_KEY"
    emit_manifest_var DB_ENGINE "$DB_ENGINE"
    emit_manifest_var DB_HOST "$DB_HOST"
    emit_manifest_var DB_PORT "$DB_PORT"
    emit_manifest_var DB_ROOT_USER "$DB_ROOT_USER"
    emit_manifest_var DB_ROOT_PASS "$DB_ROOT_PASS"
    emit_manifest_var DB_ALLOWED_HOST "%"
    emit_manifest_var DB_ALLOWED_CIDR "10.10.0.0/24"
    emit_manifest_var DB_NAME "$DB_NAME"
    emit_manifest_var DB_USER "$DB_USER"
    emit_manifest_var DB_PASSWORD "$DB_PASSWORD"
    emit_manifest_var DB_TENANT_DATABASE "$DB_TENANT_DATABASE"
    emit_manifest_var DB_LANDLORD_HOST "$DB_HOST"
    emit_manifest_var DB_LANDLORD_PORT "$DB_PORT"
    emit_manifest_var DB_LANDLORD_DATABASE "$DB_NAME"
    emit_manifest_var DB_LANDLORD_USERNAME "$DB_USER"
    emit_manifest_var DB_LANDLORD_PASSWORD "$DB_PASSWORD"
    emit_manifest_var REDIS_PASSWORD "$REDIS_PASSWORD"
} > "$MANIFEST_OUT"
chmod 600 "$MANIFEST_OUT"
ok "manifest salvo em ${MANIFEST_OUT}"

if ask_yn "Provisionar App VPS agora?"; then
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "mkdir -p /tmp/vps-provisioning"
    scp -o StrictHostKeyChecking=accept-new -r "${SCRIPT_DIR}/provisioning/." "${VPS_USER}@${VPS_HOST}:/tmp/vps-provisioning/"
    scp -o StrictHostKeyChecking=accept-new "${MANIFEST_OUT}" "${VPS_USER}@${VPS_HOST}:/tmp/vps-provisioning/manifest.env"
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "bash /tmp/vps-provisioning/validate-prereqs.sh /tmp/vps-provisioning/manifest.env"
    if [[ "${DB_MODE}" == "local" ]]; then
        ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "DB_ENGINE='${DB_ENGINE}' bash /tmp/vps-provisioning/setup-db-host.sh /tmp/vps-provisioning/manifest.env"
        ok "Banco local provisionado (${DB_ENGINE})"
    fi
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "APP_SLUG='${APP_SLUG}' bash /tmp/vps-provisioning/setup-app-host.sh /tmp/vps-provisioning/manifest.env"
    ok "App VPS provisionado"
fi

if ask_yn "Instalar compose files no VPS agora?"; then
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "mkdir -p '${REMOTE_WORKDIR}'"
    scp -o StrictHostKeyChecking=accept-new -r "${SCRIPT_DIR}/." "${VPS_USER}@${VPS_HOST}:${REMOTE_WORKDIR}/"
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "APP_SLUG='${APP_SLUG}' START_SERVICES='true' bash ${REMOTE_WORKDIR}/automation/install-compose-on-host.sh"
    ok "Compose files instalados e serviços iniciais (Traefik/app) iniciados"
fi

if ask_yn "Instalar monitoramento dessa app agora?"; then
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "APP_SLUG='${APP_SLUG}' bash ${REMOTE_WORKDIR}/automation/install-monitoring-on-host.sh ${REMOTE_WORKDIR}/manifest.env '${APP_SLUG}'"
    ok "Monitoramento instalado para ${APP_SLUG}"
fi

echo ""
ok "Setup concluído. Fluxo ativo: dev -> staging"
info "Produção futura: /opt/plannerate/production"
