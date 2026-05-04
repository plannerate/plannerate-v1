#!/usr/bin/env bash
# provision-external-db.sh — provisiona banco em VPS externa via SSH
#
# Roda LOCALMENTE. Conecta na VPS de banco, instala PostgreSQL/MySQL,
# configura UFW/fail2ban, e opcionalmente instala backup para DO Spaces.
#
# Uso:
#   ./automation/provision-external-db.sh \
#     --host <IP>                    # IP da VPS de banco (obrigatório)
#     [--manifest <path>]            # padrão: ../manifest.env
#     [--db-user root]               # usuário SSH da VPS de banco (padrão: root)
#     [--db-key ~/.ssh/id_ed25519]   # chave SSH (padrão: configurado no ~/.ssh/config)
#     [--new-passwords]              # gera novas senhas DB e atualiza manifest + app .env
#     [--no-backup]                  # pula instalação do cron de backup DO Spaces
#     [--dry-run]                    # mostra os comandos sem executar

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
PROVISIONING_DIR="${SCRIPT_DIR}/../provisioning"
# shellcheck disable=SC1091
source "${PROVISIONING_DIR}/common.sh"

# ── Parse args ────────────────────────────────────────────────────────────────
DB_HOST_ARG=""
MANIFEST_PATH="${SCRIPT_DIR}/../manifest.env"
DB_SSH_USER="root"
DB_SSH_KEY=""
NEW_PASSWORDS=false
SETUP_BACKUP=true
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --host)       DB_HOST_ARG="$2"; shift 2 ;;
        --manifest)   MANIFEST_PATH="$2"; shift 2 ;;
        --db-user)    DB_SSH_USER="$2"; shift 2 ;;
        --db-key)     DB_SSH_KEY="$2"; shift 2 ;;
        --new-passwords) NEW_PASSWORDS=true; shift ;;
        --no-backup)  SETUP_BACKUP=false; shift ;;
        --dry-run)    DRY_RUN=true; shift ;;
        *) log_error "Argumento desconhecido: $1"; exit 1 ;;
    esac
done

if [[ -z "${DB_HOST_ARG}" ]]; then
    log_error "Uso: $0 --host <IP> [--manifest path] [--new-passwords] [--no-backup] [--dry-run]"
    exit 1
fi

MANIFEST_PATH="$(realpath "${MANIFEST_PATH}")"
if [[ ! -f "${MANIFEST_PATH}" ]]; then
    log_error "Manifest não encontrado: ${MANIFEST_PATH}"
    exit 1
fi

load_manifest "${MANIFEST_PATH}"

# ── SSH helper ────────────────────────────────────────────────────────────────
SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)
if [[ -n "${DB_SSH_KEY}" ]]; then
    SSH_OPTS+=(-i "${DB_SSH_KEY}" -o IdentitiesOnly=yes)
fi
SSH_TARGET="${DB_SSH_USER}@${DB_HOST_ARG}"

run_remote() {
    if [[ "${DRY_RUN}" == "true" ]]; then
        printf '[DRY_RUN remote] %s\n' "$*"
        return
    fi
    ssh "${SSH_OPTS[@]}" "${SSH_TARGET}" "$@"
}

run_scp() {
    if [[ "${DRY_RUN}" == "true" ]]; then
        printf '[DRY_RUN scp] %s\n' "$*"
        return
    fi
    scp "${SSH_OPTS[@]}" "$@"
}

# ── Geração de novas senhas ───────────────────────────────────────────────────
if [[ "${NEW_PASSWORDS}" == "true" ]]; then
    log_info "Gerando novas senhas para o banco"
    NEW_DB_PASSWORD="$(openssl rand -base64 48 | tr -d '=+/' | cut -c1-28)"
    NEW_DB_ROOT_PASS="$(openssl rand -base64 48 | tr -d '=+/' | cut -c1-28)"

    # Atualiza manifest local (substitui ou adiciona as variáveis)
    _update_manifest_var() {
        local key="$1" val="$2" file="$3"
        if grep -q "^${key}=" "${file}"; then
            sed -i "s|^${key}=.*|${key}=${val}|" "${file}"
        else
            printf '%s=%s\n' "${key}" "${val}" >> "${file}"
        fi
    }

    _update_manifest_var "DB_PASSWORD"         "${NEW_DB_PASSWORD}"  "${MANIFEST_PATH}"
    _update_manifest_var "DB_ROOT_PASS"        "${NEW_DB_ROOT_PASS}" "${MANIFEST_PATH}"
    _update_manifest_var "DB_LANDLORD_PASSWORD" "${NEW_DB_PASSWORD}" "${MANIFEST_PATH}"

    # Recarrega manifest com as novas senhas
    load_manifest "${MANIFEST_PATH}"
    log_success "Novas senhas salvas no manifest"
fi

# ── Atualiza manifest com host e modo externo ─────────────────────────────────
_update_manifest_var() {
    local key="$1" val="$2" file="$3"
    if grep -q "^${key}=" "${file}"; then
        sed -i "s|^${key}=.*|${key}=${val}|" "${file}"
    else
        printf '%s=%s\n' "${key}" "${val}" >> "${file}"
    fi
}

_update_manifest_var "DB_MODE"          "externo"       "${MANIFEST_PATH}"
_update_manifest_var "DB_HOST"          "${DB_HOST_ARG}" "${MANIFEST_PATH}"
_update_manifest_var "DB_LANDLORD_HOST" "${DB_HOST_ARG}" "${MANIFEST_PATH}"

load_manifest "${MANIFEST_PATH}"

APP_SLUG="${APP_SLUG:-staging}"
DB_ENGINE="${DB_ENGINE:-pgsql}"
DB_ALLOWED_CIDR="${DB_ALLOWED_CIDR:-10.0.0.0/8}"

log_info "Provisionando banco em ${DB_HOST_ARG} (engine: ${DB_ENGINE}, modo: externo)"

# ── Testa conectividade SSH ───────────────────────────────────────────────────
if [[ "${DRY_RUN}" != "true" ]]; then
    log_info "Testando conexão SSH com ${SSH_TARGET}"
    if ! ssh "${SSH_OPTS[@]}" "${SSH_TARGET}" echo "ssh-ok" >/dev/null 2>&1; then
        log_error "Não foi possível conectar via SSH em ${SSH_TARGET}"
        log_error "Verifique o IP, usuário e chave SSH."
        exit 1
    fi
    log_success "Conexão SSH OK"
fi

# ── Copia scripts para a VPS remota ──────────────────────────────────────────
REMOTE_TMP="/tmp/vps-v2-db-$$"
log_info "Copiando scripts para ${SSH_TARGET}:${REMOTE_TMP}"

run_remote "mkdir -p ${REMOTE_TMP}"
run_scp \
    "${PROVISIONING_DIR}/common.sh" \
    "${PROVISIONING_DIR}/setup-db-host.sh" \
    "${MANIFEST_PATH}" \
    "${SSH_TARGET}:${REMOTE_TMP}/"

# Garante permissões de execução remotas
run_remote "chmod +x ${REMOTE_TMP}/setup-db-host.sh"

# ── Roda setup-db-host.sh na VPS ─────────────────────────────────────────────
MANIFEST_BASENAME="$(basename "${MANIFEST_PATH}")"
log_info "Rodando setup-db-host.sh na VPS remota"
run_remote "DB_MODE=externo DB_ENGINE=${DB_ENGINE} bash ${REMOTE_TMP}/setup-db-host.sh ${REMOTE_TMP}/${MANIFEST_BASENAME}"

# ── Backup DO Spaces ──────────────────────────────────────────────────────────
if [[ "${SETUP_BACKUP}" == "true" ]]; then
    BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-}"
    BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-}"
    BACKUP_S3_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY_ID:-}"
    BACKUP_S3_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_ACCESS_KEY:-}"

    if [[ -z "${BACKUP_S3_BUCKET}" || -z "${BACKUP_S3_ENDPOINT}" || -z "${BACKUP_S3_ACCESS_KEY_ID}" || -z "${BACKUP_S3_SECRET_ACCESS_KEY}" ]]; then
        log_warn "Variáveis BACKUP_S3_* não configuradas no manifest — pulando setup de backup."
        log_warn "Preencha o bloco '# DO Spaces backup' no manifest.env e re-execute com --no-backup removido."
    else
        log_info "Instalando backup DO Spaces na VPS de banco"

        # Copia scripts de backup
        run_scp \
            "${SCRIPT_DIR}/backup-db.sh" \
            "${SCRIPT_DIR}/install-backup-cron.sh" \
            "${SCRIPT_DIR}/run-backup-all.sh" \
            "${SSH_TARGET}:${REMOTE_TMP}/"
        run_remote "chmod +x ${REMOTE_TMP}/backup-db.sh ${REMOTE_TMP}/install-backup-cron.sh ${REMOTE_TMP}/run-backup-all.sh"

        # Instala awscli se necessário
        run_remote "command -v aws >/dev/null 2>&1 || (apt-get install -y awscli 2>/dev/null || pip3 install --quiet awscli)"

        # Instala cron de backup
        run_remote "CRON_USER=root bash ${REMOTE_TMP}/install-backup-cron.sh ${REMOTE_TMP}/${MANIFEST_BASENAME}"

        log_success "Backup cron instalado na VPS de banco"
        log_info "Agendamento: diário às 02:10 → DO Spaces bucket: ${BACKUP_S3_BUCKET}"
    fi
fi

# ── Atualiza .env do app se senhas mudaram ────────────────────────────────────
APP_VPS_HOST="${VPS_HOST:-}"
if [[ "${NEW_PASSWORDS}" == "true" && -n "${APP_VPS_HOST}" ]]; then
    APP_SSH_USER="${DEPLOY_USER:-root}"
    APP_DIR="/opt/plannerate/${APP_SLUG}"
    APP_ENV_FILE="${APP_DIR}/.env"

    log_info "Atualizando senhas no .env do app em ${APP_VPS_HOST}"

    _sed_env() {
        local key="$1" val="$2"
        run_remote -H "${APP_SSH_USER}@${APP_VPS_HOST}" \
            "sed -i 's|^${key}=.*|${key}=${val}|' ${APP_ENV_FILE} 2>/dev/null || true"
    }

    if [[ "${DRY_RUN}" != "true" ]]; then
        APP_SSH_OPTS=(-o StrictHostKeyChecking=accept-new -o ConnectTimeout=15)
        [[ -n "${DB_SSH_KEY}" ]] && APP_SSH_OPTS+=(-i "${DB_SSH_KEY}" -o IdentitiesOnly=yes)

        ssh "${APP_SSH_OPTS[@]}" "${APP_SSH_USER}@${APP_VPS_HOST}" \
            "sed -i 's|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|;
                     s|^DB_LANDLORD_PASSWORD=.*|DB_LANDLORD_PASSWORD=${DB_PASSWORD}|;
                     s|^DB_HOST=.*|DB_HOST=${DB_HOST_ARG}|;
                     s|^DB_LANDLORD_HOST=.*|DB_LANDLORD_HOST=${DB_HOST_ARG}|' \
             ${APP_ENV_FILE}"

        log_info "Reiniciando containers do app"
        ssh "${APP_SSH_OPTS[@]}" "${APP_SSH_USER}@${APP_VPS_HOST}" \
            "cd ${APP_DIR} && docker compose up -d"
    else
        printf '[DRY_RUN] Atualizaria .env em %s e reiniciaria containers\n' "${APP_VPS_HOST}"
    fi

    log_success "App .env atualizado e containers reiniciados"
fi

# ── Limpeza ───────────────────────────────────────────────────────────────────
log_info "Removendo arquivos temporários da VPS remota"
run_remote "rm -rf ${REMOTE_TMP}"

log_success "Provisionamento do banco externo concluído!"
log_info ""
log_info "Próximos passos:"
log_info "  1. Confirme no manifest.env: DB_MODE=externo, DB_HOST=${DB_HOST_ARG}"
if [[ "${NEW_PASSWORDS}" == "true" ]]; then
    log_info "  2. Novas senhas gravadas no manifest.env — faça commit se estiver em repositório privado"
fi
log_info "  3. Para mudar o app de local → externo, atualize o .env na VPS do app:"
log_info "     ssh plannerate-v2-vps \"sed -i 's|^DB_HOST=.*|DB_HOST=${DB_HOST_ARG}|' /opt/plannerate/${APP_SLUG}/.env\""
log_info "     ssh plannerate-v2-vps \"cd /opt/plannerate/${APP_SLUG} && docker compose up -d\""
