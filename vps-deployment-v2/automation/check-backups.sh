#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
# shellcheck disable=SC1091
source "${SCRIPT_DIR}/../provisioning/common.sh"

MANIFEST_PATH="${1:-}"

if [[ -z "${MANIFEST_PATH}" ]]; then
    if ! MANIFEST_PATH="$(find_manifest "${SCRIPT_DIR}/..")"; then
        log_error "Nenhum manifest encontrado. Passe: ./check-backups.sh /path/to/manifest.env"
        exit 1
    fi
    log_info "Usando manifest: ${MANIFEST_PATH}"
fi

load_manifest "${MANIFEST_PATH}"
require_commands aws date sort awk mktemp gunzip

BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-}"
BACKUP_S3_REGION="${BACKUP_S3_REGION:-us-east-1}"
BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-}"
BACKUP_S3_PREFIX="${BACKUP_S3_PREFIX:-db-backups}"
BACKUP_S3_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY_ID:-}"
BACKUP_S3_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_ACCESS_KEY:-}"

if [[ -z "${BACKUP_S3_ENDPOINT}" || -z "${BACKUP_S3_BUCKET}" || -z "${BACKUP_S3_ACCESS_KEY_ID}" || -z "${BACKUP_S3_SECRET_ACCESS_KEY}" ]]; then
    log_error "Variáveis de backup DO Spaces ausentes no manifest (endpoint, bucket, chave e segredo)."
    exit 1
fi

# O cron roda 1x/dia (install-backup-cron.sh) — dá folga sobre 24h antes de avisar.
STALE_WARN_HOURS="${STALE_WARN_HOURS:-26}"
STALE_FAIL_HOURS="${STALE_FAIL_HOURS:-48}"
# Baixa o backup mais recente de cada ambiente e roda gunzip -t nele (custa tempo/banda).
VERIFY_INTEGRITY="${VERIFY_INTEGRITY:-false}"

export AWS_ACCESS_KEY_ID="${BACKUP_S3_ACCESS_KEY_ID}"
export AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET_ACCESS_KEY}"
export AWS_DEFAULT_REGION="${BACKUP_S3_REGION}"

fail_count=0
warn_count=0
ok() { echo "[OK] $*"; }
warn() { echo "[WARN] $*"; warn_count=$((warn_count + 1)); }
fail() { echo "[FAIL] $*"; fail_count=$((fail_count + 1)); }

human_bytes() {
    numfmt --to=iec-i --suffix=B "$1" 2>/dev/null || echo "$1 bytes"
}

echo "=== Estado dos backups (DO Spaces) ==="
echo "Horário: $(date -Iseconds)"
echo "Bucket: s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/"
echo ""

listing="$(aws --endpoint-url "${BACKUP_S3_ENDPOINT}" s3 ls "s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/" --recursive || true)"

if [[ -z "${listing}" ]]; then
    fail "Nenhum backup encontrado em s3://${BACKUP_S3_BUCKET}/${BACKUP_S3_PREFIX}/"
    echo ""
    echo "--- Resumo ---"
    echo "Status: FALHOU"
    exit 2
fi

# Ambiente = primeiro segmento de path após o prefix (backup-db.sh grava em <prefix>/<TARGET_ENV>/arquivo)
mapfile -t environments < <(echo "${listing}" | awk '{print $4}' | sed -E "s#^${BACKUP_S3_PREFIX}/([^/]+)/.*#\1#" | sort -u)

now_epoch="$(date +%s)"

for env in "${environments[@]}"; do
    echo "--- Ambiente: ${env} ---"

    env_listing="$(echo "${listing}" | grep -F "${BACKUP_S3_PREFIX}/${env}/" || true)"
    file_count="$(echo "${env_listing}" | grep -c . || true)"
    total_bytes="$(echo "${env_listing}" | awk '{sum+=$3} END {print sum+0}')"

    # "aws s3 ls" imprime "YYYY-MM-DD HH:MM:SS  bytes  key" — as duas primeiras colunas
    # ordenam corretamente por data/hora como texto (formato ISO).
    latest_line="$(echo "${env_listing}" | sort -k1,2 | tail -1)"
    latest_date="$(echo "${latest_line}" | awk '{print $1, $2}')"
    latest_key="$(echo "${latest_line}" | awk '{print $4}')"
    latest_bytes="$(echo "${latest_line}" | awk '{print $3}')"

    latest_epoch="$(date -d "${latest_date}" +%s 2>/dev/null || echo 0)"
    age_hours=$(( (now_epoch - latest_epoch) / 3600 ))

    echo "Arquivos: ${file_count} — total $(human_bytes "${total_bytes}")"
    echo "Mais recente: ${latest_key} (${latest_date}, $(human_bytes "${latest_bytes}"))"

    if [[ "${latest_epoch}" -eq 0 ]]; then
        fail "${env}: não foi possível interpretar a data do backup mais recente"
    elif [[ "${age_hours}" -ge "${STALE_FAIL_HOURS}" ]]; then
        fail "${env}: backup mais recente tem ${age_hours}h — acima do limite crítico de ${STALE_FAIL_HOURS}h"
    elif [[ "${age_hours}" -ge "${STALE_WARN_HOURS}" ]]; then
        warn "${env}: backup mais recente tem ${age_hours}h — acima do esperado de ${STALE_WARN_HOURS}h"
    else
        ok "${env}: backup recente (${age_hours}h atrás)"
    fi

    if [[ "${latest_bytes}" -lt 1024 ]]; then
        warn "${env}: backup mais recente é suspeito de pequeno (${latest_bytes} bytes) — pode estar vazio/corrompido"
    fi

    if [[ "${VERIFY_INTEGRITY}" == "true" ]]; then
        tmp_file="$(mktemp)"
        if aws --endpoint-url "${BACKUP_S3_ENDPOINT}" s3 cp "s3://${BACKUP_S3_BUCKET}/${latest_key}" "${tmp_file}" --only-show-errors \
            && gunzip -t "${tmp_file}" 2>/dev/null; then
            ok "${env}: integridade do gzip verificada (download + gunzip -t)"
        else
            fail "${env}: falha ao baixar ou validar integridade do backup mais recente"
        fi
        rm -f "${tmp_file}"
    fi

    echo ""
done

echo "--- Resumo ---"
echo "Avisos: ${warn_count}"
echo "Falhas: ${fail_count}"

if [[ "${fail_count}" -gt 0 ]]; then
    echo "Status: FALHOU — verifique os itens marcados com [FAIL] acima"
    exit 2
fi
if [[ "${warn_count}" -gt 0 ]]; then
    echo "Status: OK COM AVISOS"
    exit 1
fi
echo "Status: TUDO CERTO"
exit 0
