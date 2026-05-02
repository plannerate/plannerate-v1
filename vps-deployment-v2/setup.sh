#!/usr/bin/env bash
# setup.sh — Wizard interativo de implantação vps-deployment-v2
# Execute: bash vps-deployment-v2/setup.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MANIFEST_OUT="${SCRIPT_DIR}/manifest.env"

# Reuse previous answers when rerunning the wizard.
if [[ -f "${MANIFEST_OUT}" ]]; then
    # shellcheck disable=SC1090
    if ! (set -a; source "${MANIFEST_OUT}"; set +a); then
        echo "[WARN] Existing manifest.env is invalid and will be ignored for defaults."
        echo "[WARN] The wizard will continue with built-in defaults and rewrite the manifest."
    else
        set -a; source "${MANIFEST_OUT}"; set +a
    fi
fi

# ─── Cores ────────────────────────────────────────────────────────────────────
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; DIM='\033[2m'; RESET='\033[0m'

step()    { echo -e "\n${BOLD}${CYAN}▶ $*${RESET}"; }
info()    { echo -e "  ${DIM}$*${RESET}"; }
ok()      { echo -e "  ${GREEN}✔ $*${RESET}"; }
warn()    { echo -e "  ${YELLOW}⚠ $*${RESET}"; }
err()     { echo -e "  ${RED}✖ $*${RESET}" >&2; }
pause()   { echo -e "\n  ${BOLD}${YELLOW}$*${RESET}"; echo -e "  ${DIM}Pressione ENTER para continuar...${RESET}"; read -r; }

# ─── Helpers de leitura ───────────────────────────────────────────────────────
ask() {
    # ask VAR_NAME "Pergunta" ["default"]
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

ask_secret() {
    local var_name="$1" prompt="$2"
    echo -ne "  ${BOLD}${prompt}${RESET} ${DIM}(oculto)${RESET}: "
    read -rs input
    echo ""
    while [[ -z "$input" ]]; do
        echo -ne "  ${RED}Obrigatório.${RESET} ${BOLD}${prompt}${RESET}: "
        read -rs input
        echo ""
    done
    printf -v "$var_name" '%s' "$input"
}

ask_secret_default() {
    # ask_secret_default VAR_NAME "Pergunta" ["default"]
    local var_name="$1" prompt="$2" default="${3:-}"
    local hint=""
    [[ -n "$default" ]] && hint=" ${DIM}[ENTER para manter valor atual]${RESET}"
    echo -ne "  ${BOLD}${prompt}${RESET}${hint}: "
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
    # ask_yn "Pergunta?" → retorna 0 (sim) ou 1 (não)
    local prompt="$1" default="${2:-s}"
    local opts="S/n"; [[ "$default" == "n" ]] && opts="s/N"
    echo -ne "  ${BOLD}${prompt}${RESET} ${DIM}[${opts}]${RESET}: "
    read -r yn
    yn="${yn:-$default}"
    [[ "${yn,,}" == "s" || "${yn,,}" == "y" ]]
}

random_secret() { openssl rand -base64 48 | tr -d '=+/' | cut -c1-40; }

emit_manifest_var() {
    local key="$1" value="${2:-}"
    printf '%s=%q\n' "$key" "$value"
}

# ─── Banner ───────────────────────────────────────────────────────────────────
clear
echo -e "${BOLD}${CYAN}"
cat <<'BANNER'
  ╔══════════════════════════════════════════════════════╗
  ║        VPS Deployment v2 — Setup Wizard              ║
  ║        Plannerate                                     ║
  ╚══════════════════════════════════════════════════════╝
BANNER
echo -e "${RESET}"
echo -e "  Este wizard vai:"
echo -e "  ${DIM}1. Coletar as informações do projeto${RESET}"
echo -e "  ${DIM}2. Gerar chaves SSH para deploy${RESET}"
echo -e "  ${DIM}3. Aguardar você adicionar a chave pública no GitHub${RESET}"
echo -e "  ${DIM}4. Salvar o manifest.env${RESET}"
echo -e "  ${DIM}5. Executar o provisionamento no VPS (opcional)${RESET}"
echo ""
pause "Pronto para começar?"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 1 — Informações básicas
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 1 — Projeto e domínios"

ask PROJECT_NAME   "Nome do projeto (ex: plannerate)"         "${PROJECT_NAME:-plannerate}"
ask GITHUB_OWNER   "GitHub org/usuário (ex: minha-org)"       "${GITHUB_OWNER:-}"
ask GITHUB_REPO    "Nome do repositório no GitHub"            "${GITHUB_REPO_NAME:-${PROJECT_NAME}}"
ask GHCR_REPO      "Imagem GHCR (ex: minha-org/plannerate)"   "${GHCR_REPO:-${GITHUB_OWNER}/${GITHUB_REPO}}"

echo ""
ask DOMAIN_PROD    "Domínio produção (ex: app.seudominio.com)" "${DOMAIN_PRODUCTION:-}"
ask DOMAIN_STG     "Domínio staging   (ex: stg.seudominio.com)" "${DOMAIN_STAGING:-}"
ask ACME_EMAIL     "E-mail para Let's Encrypt"                  "${ACME_EMAIL:-}"

DEFAULT_GRAFANA_DOMAIN="${DOMAIN_GRAFANA:-grafana.${DOMAIN_PROD}}"
DEFAULT_PROMETHEUS_DOMAIN="${DOMAIN_PROMETHEUS:-prometheus.${DOMAIN_PROD}}"

echo ""
ask DOMAIN_GRAFANA    "Domínio Grafana     (ex: grafana.seudominio.com)" "${DEFAULT_GRAFANA_DOMAIN}"
ask DOMAIN_PROMETHEUS "Domínio Prometheus  (ex: prometheus.seudominio.com)" "${DEFAULT_PROMETHEUS_DOMAIN}"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 2 — App VPS
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 2 — App VPS"

ask VPS_HOST      "IP do App VPS" "${VPS_HOST:-}"
ask VPS_USER      "Usuário SSH root no VPS" "${VPS_USER:-root}"
ask DEPLOY_USER   "Usuário de deploy (será criado)"  "${DEPLOY_USER:-deploy}"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 3 — Banco de dados
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 3 — Banco de dados (servidor separado)"

ask DB_ENGINE  "Engine: mysql ou postgres" "${DB_ENGINE:-mysql}"
ask DB_HOST    "IP do DB VPS" "${DB_HOST:-${DB_HOST_PRODUCTION:-}}"
ask DB_PORT    "Porta" "${DB_PORT:-${DB_PORT_PRODUCTION:-$([ "$DB_ENGINE" = "postgres" ] && echo 5432 || echo 3306)}}"
ask DB_ROOT_USER "Usuário root do banco" "${DB_ROOT_USER:-root}"
ask_secret_default DB_ROOT_PASS "Senha root do banco" "${DB_ROOT_PASS:-}"

ask DB_NAME_PROD "Nome do banco (produção)" "${DB_NAME_PRODUCTION:-${PROJECT_NAME}_production}"
ask DB_USER_PROD "Usuário DB (produção)"    "${DB_USER_PRODUCTION:-${PROJECT_NAME}_prod}"
ask_secret_default DB_PASS_PROD "Senha DB (produção)" "${DB_PASSWORD_PRODUCTION:-${DB_PASS_PRODUCTION:-}}"

ask DB_NAME_STG  "Nome do banco (staging)"  "${DB_NAME_STAGING:-${PROJECT_NAME}_staging}"
ask DB_USER_STG  "Usuário DB (staging)"     "${DB_USER_STAGING:-${PROJECT_NAME}_stg}"
ask_secret_default DB_PASS_STG "Senha DB (staging)" "${DB_PASSWORD_STAGING:-${DB_PASS_STAGING:-}}"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 4 — Backups (DO Spaces)
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 4 — Backup (DigitalOcean Spaces)"

if ask_yn "Configurar backup automático para DO Spaces?"; then
    BACKUP_ENABLED=true
    ask DO_SPACES_KEY    "DO Spaces Access Key" "${DO_SPACES_KEY:-${BACKUP_S3_ACCESS_KEY_ID:-}}"
    ask_secret_default DO_SPACES_SECRET "DO Spaces Secret Key" "${DO_SPACES_SECRET:-${BACKUP_S3_SECRET_ACCESS_KEY:-}}"
    ask DO_SPACES_BUCKET "Nome do bucket" "${DO_SPACES_BUCKET:-${BACKUP_S3_BUCKET:-}}"
    ask DO_SPACES_REGION "Região" "${DO_SPACES_REGION:-nyc3}"
    ask DO_SPACES_PREFIX "Prefixo no bucket" "${DO_SPACES_PREFIX:-${BACKUP_S3_PREFIX:-backups/${PROJECT_NAME}}}"
    ask BACKUP_RETENTION "Retenção em dias" "${BACKUP_RETENTION:-14}"
    ask BACKUP_SCHEDULE  "Cron de backup" "${BACKUP_SCHEDULE:-0 2 * * *}"
else
    BACKUP_ENABLED=false
    DO_SPACES_KEY="" DO_SPACES_SECRET="" DO_SPACES_BUCKET=""
    DO_SPACES_REGION="nyc3" DO_SPACES_PREFIX="" BACKUP_RETENTION="14"
    BACKUP_SCHEDULE="0 2 * * *"
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 5 — Webhooks de alerta
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 5 — Webhooks de alerta (Discord / Slack)"
info "Deixe em branco para pular."

ask ALERT_WEBHOOK_DEFAULT_URL "Webhook padrão (default)" "${ALERT_WEBHOOK_DEFAULT_URL:-}"
ask ALERT_WEBHOOK_WARNING_URL "Webhook warning"          "${ALERT_WEBHOOK_WARNING_URL:-}"
ask ALERT_WEBHOOK_CRITICAL_URL "Webhook critical"        "${ALERT_WEBHOOK_CRITICAL_URL:-}"
ask BACKUP_ALERT_WEBHOOK_URL "Webhook falha de backup"   "${BACKUP_ALERT_WEBHOOK_URL:-}"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 6 — Monitoramento
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 6 — Grafana e Traefik dashboard"

ask GRAFANA_ADMIN_USER "Usuário admin Grafana" "${GRAFANA_ADMIN_USER:-admin}"
ask_secret_default GRAFANA_ADMIN_PASS "Senha admin Grafana" "${GRAFANA_ADMIN_PASSWORD:-}"

echo ""
info "Traefik dashboard: acesso protegido por usuário/senha."
info "Deixe a senha em branco para gerar automaticamente durante o provisionamento."
ask TRAEFIK_DASHBOARD_USER "Usuário Traefik dashboard" "${TRAEFIK_DASHBOARD_USER:-admin}"
ask_secret_default TRAEFIK_DASHBOARD_PASS "Senha Traefik dashboard (branco = auto-gerar)" "${TRAEFIK_DASHBOARD_PASS:-}"

# ══════════════════════════════════════════════════════════════════════════════
# FASE 7 — Gerar chave SSH de deploy
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 7 — Chave SSH de deploy"

KEY_DIR="${HOME}/.ssh"
KEY_PATH="${KEY_DIR}/id_ed25519_${GITHUB_REPO}_deploy"
mkdir -p "$KEY_DIR" && chmod 700 "$KEY_DIR"

REGENERATE_DEPLOY_KEY="${REGENERATE_DEPLOY_KEY:-false}"

if [[ -f "$KEY_PATH" && "$REGENERATE_DEPLOY_KEY" != "true" ]]; then
    ok "Reutilizando chave existente: ${KEY_PATH}"
    info "Para forçar nova chave, rode com REGENERATE_DEPLOY_KEY=true"
else
    if [[ -f "$KEY_PATH" ]]; then
        rm -f "$KEY_PATH" "${KEY_PATH}.pub"
    fi
    ssh-keygen -t ed25519 -f "$KEY_PATH" -N "" -C "${GITHUB_OWNER}/${GITHUB_REPO}-deploy" -q
    ok "Chave gerada: ${KEY_PATH}"
fi

DEPLOY_PUBLIC_KEY="$(cat "${KEY_PATH}.pub")"
DEPLOY_PRIVATE_KEY="$(cat "${KEY_PATH}")"

# ── Capturar known_hosts do VPS ───────────────────────────────────────────────
info "Coletando fingerprint do VPS (${VPS_HOST})..."
VPS_KNOWN_HOSTS="$(ssh-keyscan -H "${VPS_HOST}" 2>/dev/null)" || {
    warn "Não foi possível coletar fingerprint. Verifique se o VPS está acessível."
    VPS_KNOWN_HOSTS=""
}
[[ -n "$VPS_KNOWN_HOSTS" ]] && ok "Fingerprint coletado."

# ══════════════════════════════════════════════════════════════════════════════
# FASE 8 — Adicionar chave pública ao GitHub
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 8 — Adicione a chave pública como Deploy Key no GitHub"

echo ""
echo -e "  ${BOLD}${YELLOW}Abra a URL abaixo no navegador:${RESET}"
echo -e "  ${CYAN}https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}/settings/keys/new${RESET}"
echo ""
echo -e "  ${BOLD}Cole esta chave pública no campo 'Key':${RESET}"
echo ""
echo -e "${BOLD}${GREEN}"
echo "─────────────────────────────────────────────────────────────────"
echo "$DEPLOY_PUBLIC_KEY"
echo "─────────────────────────────────────────────────────────────────"
echo -e "${RESET}"
echo -e "  ${DIM}Título sugerido: deploy-key-vps-v2${RESET}"
echo -e "  ${DIM}Marque 'Allow write access' se o workflow precisar fazer push.${RESET}"
echo ""
pause "Quando tiver colado e salvo a chave no GitHub, pressione ENTER."

# ── Verificar via gh CLI (opcional) ───────────────────────────────────────────
if command -v gh &>/dev/null; then
    if gh auth status &>/dev/null 2>&1; then
        info "Verificando deploy key via gh CLI..."
        if gh repo deploy-key list --repo "${GITHUB_OWNER}/${GITHUB_REPO}" 2>/dev/null | grep -q "deploy-key-vps-v2"; then
            ok "Deploy key encontrada no repositório."
        else
            warn "Deploy key não encontrada via gh CLI. Verifique manualmente se foi salva."
        fi
    fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 9 — Secrets e Variables no GitHub
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 9 — Configurar Secrets e Variables no GitHub"

if command -v gh &>/dev/null && gh auth status &>/dev/null 2>&1; then
    info "gh CLI detectado. Configurando secrets e environments automaticamente..."

    repo="${GITHUB_OWNER}/${GITHUB_REPO}"

    # Environments
    gh api --method PUT "repos/${repo}/environments/staging"    --silent >/dev/null 2>&1 || true
    gh api --method PUT "repos/${repo}/environments/production" --silent >/dev/null 2>&1 || true
    ok "Environments criados: staging, production"

    # Secrets globais
    gh secret set GHCR_REPO    --repo "$repo" --body "$GHCR_REPO"    >/dev/null
    gh secret set DEPLOY_USER  --repo "$repo" --body "$DEPLOY_USER"  >/dev/null
    ok "Secrets globais configurados."

    # Secrets por environment
    for env in staging production; do
        [[ "$env" == "staging" ]]    && host_domain="$DOMAIN_STG"
        [[ "$env" == "production" ]] && host_domain="$DOMAIN_PROD"

        gh secret set APP_HOST         --repo "$repo" --env "$env" --body "$VPS_HOST"          >/dev/null
        gh secret set APP_USER         --repo "$repo" --env "$env" --body "$DEPLOY_USER"       >/dev/null
        gh secret set SSH_PRIVATE_KEY  --repo "$repo" --env "$env" --body "$DEPLOY_PRIVATE_KEY" >/dev/null
        gh secret set SSH_KNOWN_HOSTS  --repo "$repo" --env "$env" --body "$VPS_KNOWN_HOSTS"  >/dev/null
        gh secret set DOMAIN           --repo "$repo" --env "$env" --body "$host_domain"       >/dev/null
        gh variable set DOMAIN         --repo "$repo" --env "$env" --body "$host_domain"       >/dev/null
        ok "  [${env}] secrets configurados."
    done
else
    warn "gh CLI não encontrado ou não autenticado. Configure os secrets manualmente:"
    echo ""
    echo -e "  ${BOLD}Secrets globais:${RESET}"
    echo "    GHCR_REPO = ${GHCR_REPO}"
    echo "    DEPLOY_USER = ${DEPLOY_USER}"
    echo ""
    echo -e "  ${BOLD}Secrets por environment (staging e production):${RESET}"
    echo "    APP_HOST = ${VPS_HOST}"
    echo "    APP_USER = ${DEPLOY_USER}"
    echo "    SSH_PRIVATE_KEY = (conteúdo de ${KEY_PATH})"
    echo "    SSH_KNOWN_HOSTS = (conteúdo abaixo)"
    echo ""
    echo "$VPS_KNOWN_HOSTS"
    echo ""
    echo -e "  ${BOLD}Secret/Variable (staging):${RESET}  DOMAIN = ${DOMAIN_STG}"
    echo -e "  ${BOLD}Secret/Variable (production):${RESET} DOMAIN = ${DOMAIN_PROD}"
    echo ""
    pause "Configure os secrets acima e pressione ENTER para continuar."
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 10 — Salvar manifest.env
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 10 — Salvando manifest.env"

# Gerar Redis passwords apenas se ainda não existem
REDIS_PASSWORD_PROD="${REDIS_PASSWORD_PRODUCTION:-$(random_secret)}"
REDIS_PASSWORD_STG="${REDIS_PASSWORD_STAGING:-$(random_secret)}"

{
cat <<MANIFEST
# ──────────────────────────────────────────────────────────────────────────────
# manifest.env — gerado pelo setup.sh em $(date '+%Y-%m-%d %H:%M:%S')
# NÃO commite este arquivo. Adicione-o ao .gitignore.
# ──────────────────────────────────────────────────────────────────────────────

MANIFEST

emit_manifest_var PROJECT_NAME "$PROJECT_NAME"
emit_manifest_var GHCR_REPO "$GHCR_REPO"

echo ""
echo "# GitHub"
emit_manifest_var GITHUB_OWNER "$GITHUB_OWNER"
emit_manifest_var GITHUB_REPO_NAME "$GITHUB_REPO"

echo ""
echo "# Domínios"
emit_manifest_var DOMAIN_PRODUCTION "$DOMAIN_PROD"
emit_manifest_var DOMAIN_STAGING "$DOMAIN_STG"
emit_manifest_var ACME_EMAIL "$ACME_EMAIL"

echo ""
echo "# App VPS"
emit_manifest_var VPS_HOST "$VPS_HOST"
emit_manifest_var VPS_USER "$VPS_USER"
emit_manifest_var DEPLOY_USER "$DEPLOY_USER"
emit_manifest_var GITHUB_DEPLOY_PUBLIC_KEY "$DEPLOY_PUBLIC_KEY"

echo ""
echo "# Banco de dados"
emit_manifest_var DB_ENGINE_PRODUCTION "$DB_ENGINE"
emit_manifest_var DB_ENGINE_STAGING "$DB_ENGINE"
emit_manifest_var DB_HOST_PRODUCTION "$DB_HOST"
emit_manifest_var DB_HOST_STAGING "$DB_HOST"
emit_manifest_var DB_PORT_PRODUCTION "$DB_PORT"
emit_manifest_var DB_PORT_STAGING "$DB_PORT"
emit_manifest_var DB_ROOT_USER "$DB_ROOT_USER"
emit_manifest_var DB_ROOT_PASS "$DB_ROOT_PASS"
emit_manifest_var DB_ALLOWED_HOST "%"

echo ""
echo "# Produção"
emit_manifest_var DB_NAME_PRODUCTION "$DB_NAME_PROD"
emit_manifest_var DB_USER_PRODUCTION "$DB_USER_PROD"
emit_manifest_var DB_PASSWORD_PRODUCTION "$DB_PASS_PROD"

echo ""
echo "# Staging"
emit_manifest_var DB_NAME_STAGING "$DB_NAME_STG"
emit_manifest_var DB_USER_STAGING "$DB_USER_STG"
emit_manifest_var DB_PASSWORD_STAGING "$DB_PASS_STG"

echo ""
echo "# Redis"
emit_manifest_var REDIS_PASSWORD_PRODUCTION "$REDIS_PASSWORD_PROD"
emit_manifest_var REDIS_PASSWORD_STAGING "$REDIS_PASSWORD_STG"

echo ""
echo "# DO Spaces (backup)"
emit_manifest_var BACKUP_ENABLED "$BACKUP_ENABLED"
emit_manifest_var DO_SPACES_KEY "$DO_SPACES_KEY"
emit_manifest_var DO_SPACES_SECRET "$DO_SPACES_SECRET"
emit_manifest_var DO_SPACES_BUCKET "$DO_SPACES_BUCKET"
emit_manifest_var DO_SPACES_REGION "$DO_SPACES_REGION"
emit_manifest_var DO_SPACES_PREFIX "$DO_SPACES_PREFIX"
emit_manifest_var BACKUP_S3_ENDPOINT "https://${DO_SPACES_REGION}.digitaloceanspaces.com"
emit_manifest_var BACKUP_S3_REGION "us-east-1"
emit_manifest_var BACKUP_S3_BUCKET "$DO_SPACES_BUCKET"
emit_manifest_var BACKUP_S3_PREFIX "$DO_SPACES_PREFIX"
emit_manifest_var BACKUP_S3_ACCESS_KEY_ID "$DO_SPACES_KEY"
emit_manifest_var BACKUP_S3_SECRET_ACCESS_KEY "$DO_SPACES_SECRET"
emit_manifest_var BACKUP_RETENTION "$BACKUP_RETENTION"
emit_manifest_var BACKUP_SCHEDULE "$BACKUP_SCHEDULE"

echo ""
echo "# Webhooks de alerta"
emit_manifest_var ALERT_WEBHOOK_DEFAULT_URL "${ALERT_WEBHOOK_DEFAULT_URL:-}"
emit_manifest_var ALERT_WEBHOOK_WARNING_URL "${ALERT_WEBHOOK_WARNING_URL:-}"
emit_manifest_var ALERT_WEBHOOK_CRITICAL_URL "${ALERT_WEBHOOK_CRITICAL_URL:-}"
emit_manifest_var BACKUP_ALERT_WEBHOOK_URL "${BACKUP_ALERT_WEBHOOK_URL:-}"

echo ""
echo "# Monitoramento"
emit_manifest_var DOMAIN_GRAFANA "$DOMAIN_GRAFANA"
emit_manifest_var DOMAIN_PROMETHEUS "$DOMAIN_PROMETHEUS"
emit_manifest_var GRAFANA_ADMIN_USER "$GRAFANA_ADMIN_USER"
emit_manifest_var GRAFANA_ADMIN_PASSWORD "$GRAFANA_ADMIN_PASS"

echo ""
echo "# Traefik dashboard"
emit_manifest_var TRAEFIK_DASHBOARD_USER "${TRAEFIK_DASHBOARD_USER:-admin}"
emit_manifest_var TRAEFIK_DASHBOARD_PASS "${TRAEFIK_DASHBOARD_PASS:-}"
} > "$MANIFEST_OUT"

chmod 600 "$MANIFEST_OUT"
ok "manifest.env salvo em: ${MANIFEST_OUT}"

# Adicionar ao .gitignore se necessário
GITIGNORE_ROOT="$(git -C "$SCRIPT_DIR" rev-parse --show-toplevel 2>/dev/null || echo "")"
if [[ -n "$GITIGNORE_ROOT" ]]; then
    GITIGNORE_FILE="${GITIGNORE_ROOT}/.gitignore"
    if ! grep -q "vps-deployment-v2/manifest.env" "$GITIGNORE_FILE" 2>/dev/null; then
        echo "vps-deployment-v2/manifest.env" >> "$GITIGNORE_FILE"
        ok "manifest.env adicionado ao .gitignore"
    fi
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 11 — Provisionar VPS (opcional)
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 11 — Provisionar o App VPS"

echo ""
echo -e "  ${DIM}Isso vai conectar via SSH no VPS (${VPS_HOST}) como root${RESET}"
echo -e "  ${DIM}e executar: validate-prereqs.sh + setup-app-host.sh${RESET}"
echo ""

if ask_yn "Provisionar o App VPS agora?"; then
    info "Copiando scripts de provisionamento para o VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "mkdir -p /tmp/vps-provisioning"
    scp -o StrictHostKeyChecking=accept-new -r \
        "${SCRIPT_DIR}/provisioning/." \
        "${VPS_USER}@${VPS_HOST}:/tmp/vps-provisioning/"
    scp -o StrictHostKeyChecking=accept-new \
        "${MANIFEST_OUT}" \
        "${VPS_USER}@${VPS_HOST}:/tmp/vps-provisioning/manifest.env"

    info "Executando validate-prereqs.sh no VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" \
        "bash /tmp/vps-provisioning/validate-prereqs.sh /tmp/vps-provisioning/manifest.env" && ok "Pré-requisitos OK." || {
        err "validate-prereqs.sh falhou. Corrija os problemas antes de continuar."
        exit 1
    }

    info "Executando setup-app-host.sh no VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" \
        "bash /tmp/vps-provisioning/setup-app-host.sh /tmp/vps-provisioning/manifest.env" \
        2>&1 | while IFS= read -r line; do echo "  ${line}"; done

    ok "App VPS provisionado."
else
    info "Pulando. Execute manualmente:"
    echo "  ssh ${VPS_USER}@${VPS_HOST} 'bash /root/vps-deployment-v2/provisioning/setup-app-host.sh /root/vps-deployment-v2/manifest.env'"
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 12 — Instalar compose files no VPS
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 12 — Instalar compose files no VPS"

echo ""
echo -e "  ${DIM}Copia os docker-compose.yml de production, staging e traefik para /opt/ no VPS.${RESET}"
echo ""

if ask_yn "Instalar compose files no VPS agora?"; then
    info "Copiando automation + deployments para o VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" "mkdir -p /root/vps-deployment-v2"
    scp -o StrictHostKeyChecking=accept-new -r \
        "${SCRIPT_DIR}/." \
        "${VPS_USER}@${VPS_HOST}:/root/vps-deployment-v2/"

    info "Executando install-compose-on-host.sh no VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" \
        "bash /root/vps-deployment-v2/automation/install-compose-on-host.sh" \
        2>&1 | while IFS= read -r line; do echo "  ${line}"; done

    ok "Compose files instalados."
else
    info "Pulando. Execute manualmente:"
    echo "  ssh ${VPS_USER}@${VPS_HOST} 'bash /root/vps-deployment-v2/automation/install-compose-on-host.sh'"
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 13 — Instalar stack de monitoramento
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 13 — Instalar monitoramento (Prometheus + Grafana)"

echo ""
echo -e "  ${DIM}Instala Prometheus, Grafana, Alertmanager e Blackbox Exporter em /opt/monitoring/.${RESET}"
echo ""

if ask_yn "Instalar monitoramento no VPS agora?"; then
    info "Executando install-monitoring-on-host.sh no VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" \
        "bash /root/vps-deployment-v2/automation/install-monitoring-on-host.sh /root/vps-deployment-v2/manifest.env" \
        2>&1 | while IFS= read -r line; do echo "  ${line}"; done

    ok "Stack de monitoramento instalada."
else
    info "Pulando. Execute manualmente:"
    echo "  ssh ${VPS_USER}@${VPS_HOST} 'bash /root/vps-deployment-v2/automation/install-monitoring-on-host.sh /root/vps-deployment-v2/manifest.env'"
fi

# ══════════════════════════════════════════════════════════════════════════════
# FASE 14 — Configurar cron de backup
# ══════════════════════════════════════════════════════════════════════════════
step "FASE 14 — Configurar cron de backup"

echo ""
echo -e "  ${DIM}Instala cron job para backup automático do banco de dados (padrão: 02:10 diário).${RESET}"
echo ""

if ask_yn "Configurar cron de backup no VPS agora?"; then
    info "Executando install-backup-cron.sh no VPS..."
    ssh -o StrictHostKeyChecking=accept-new "${VPS_USER}@${VPS_HOST}" \
        "bash /root/vps-deployment-v2/automation/install-backup-cron.sh /root/vps-deployment-v2/manifest.env" \
        2>&1 | while IFS= read -r line; do echo "  ${line}"; done

    ok "Cron de backup configurado."
else
    info "Pulando. Execute manualmente:"
    echo "  ssh ${VPS_USER}@${VPS_HOST} 'bash /root/vps-deployment-v2/automation/install-backup-cron.sh /root/vps-deployment-v2/manifest.env'"
fi

# ══════════════════════════════════════════════════════════════════════════════
# FIM
# ══════════════════════════════════════════════════════════════════════════════
echo ""
echo -e "${BOLD}${GREEN}"
cat <<'DONE'
  ╔══════════════════════════════════════════════════════╗
  ║   Setup concluído!                                   ║
  ╚══════════════════════════════════════════════════════╝
DONE
echo -e "${RESET}"
echo -e "  ${BOLD}Próximos passos:${RESET}"
echo ""
echo -e "  ${DIM}1. Fazer o primeiro deploy: push na main ou workflow manual no GitHub.${RESET}"
echo ""
