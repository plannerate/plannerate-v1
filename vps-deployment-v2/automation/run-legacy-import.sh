#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
DEFAULT_MANIFEST_PATH="${ROOT_DIR}/manifest.env"
MANIFEST_PATH="${DEFAULT_MANIFEST_PATH}"
APP_SLUG="staging"
APP_DIR="/opt/plannerate/${APP_SLUG}"
APP_ENV_PATH="${APP_DIR}/.env"
SSH_HOST=""
SSH_USER=""
SSH_PORT="22"
SSH_KEY_PATH=""

usage() {
  cat <<'USAGE'
Uso:
  run-legacy-import.sh [manifest_path] [app_slug]
  run-legacy-import.sh --manifest /caminho/manifest.env --app-slug staging
  run-legacy-import.sh --ssh-host HOST --ssh-user USER [--ssh-key /caminho/chave] [--ssh-port 22] [--manifest ...] [--app-slug ...]

Exemplo (rodando local e executando na VPS):
  ./run-legacy-import.sh \
    --ssh-host 203.0.113.10 \
    --ssh-user deploy \
    --ssh-key ~/.ssh/id_rsa \
    --app-slug staging \
    --manifest ./vps-deployment-v2/manifest.env
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --manifest)
      MANIFEST_PATH="$2"
      shift 2
      ;;
    --app-slug)
      APP_SLUG="$2"
      shift 2
      ;;
    --ssh-host)
      SSH_HOST="$2"
      shift 2
      ;;
    --ssh-user)
      SSH_USER="$2"
      shift 2
      ;;
    --ssh-port)
      SSH_PORT="$2"
      shift 2
      ;;
    --ssh-key)
      SSH_KEY_PATH="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      if [[ "${MANIFEST_PATH}" == "${DEFAULT_MANIFEST_PATH}" ]]; then
        MANIFEST_PATH="$1"
      elif [[ "${APP_SLUG}" == "staging" ]]; then
        APP_SLUG="$1"
      else
        echo "Argumento desconhecido: $1"
        usage
        exit 1
      fi
      shift
      ;;
  esac
done

APP_DIR="/opt/plannerate/${APP_SLUG}"
APP_ENV_PATH="${APP_DIR}/.env"

if [[ -n "${SSH_HOST}" ]]; then
  if [[ -z "${SSH_USER}" ]]; then
    echo "Quando usar --ssh-host, informe também --ssh-user."
    exit 1
  fi

  if [[ ! -f "${MANIFEST_PATH}" ]]; then
    echo "Manifest local não encontrado: ${MANIFEST_PATH}"
    exit 1
  fi

  if ! command -v ssh >/dev/null 2>&1; then
    echo "ssh não encontrado na máquina local."
    exit 1
  fi

  if ! command -v scp >/dev/null 2>&1; then
    echo "scp não encontrado na máquina local."
    exit 1
  fi

  ssh_opts=(-p "${SSH_PORT}" -o StrictHostKeyChecking=accept-new)
  scp_opts=(-P "${SSH_PORT}" -o StrictHostKeyChecking=accept-new)
  if [[ -n "${SSH_KEY_PATH}" ]]; then
    ssh_opts+=(-i "${SSH_KEY_PATH}")
    scp_opts+=(-i "${SSH_KEY_PATH}")
  fi

  remote_tmp_dir="/tmp/plannerate-legacy-import-$$"
  remote_manifest="${remote_tmp_dir}/manifest.env"

  ssh "${ssh_opts[@]}" "${SSH_USER}@${SSH_HOST}" "mkdir -p '${remote_tmp_dir}'"
  scp "${scp_opts[@]}" "${MANIFEST_PATH}" "${SSH_USER}@${SSH_HOST}:${remote_manifest}"

  ssh "${ssh_opts[@]}" "${SSH_USER}@${SSH_HOST}" "bash -s -- '${remote_manifest}' '${APP_SLUG}'" < "$0"

  scp "${scp_opts[@]}" "${SSH_USER}@${SSH_HOST}:${remote_manifest}" "${MANIFEST_PATH}"
  ssh "${ssh_opts[@]}" "${SSH_USER}@${SSH_HOST}" "rm -rf '${remote_tmp_dir}'"

  echo "Execução remota finalizada e manifest local atualizado."
  exit 0
fi

if [[ ! -f "${MANIFEST_PATH}" ]]; then
  echo "Manifest não encontrado: ${MANIFEST_PATH}"
  usage
  exit 1
fi

if [[ ! -f "${APP_ENV_PATH}" ]]; then
  echo "Arquivo .env da app não encontrado: ${APP_ENV_PATH}"
  echo "Confirme APP_SLUG e provisionamento."
  exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker não encontrado no host."
  exit 1
fi

if ! command -v docker compose >/dev/null 2>&1; then
  echo "docker compose não encontrado no host."
  exit 1
fi

if [[ ! -t 0 ]]; then
  echo "Este script precisa de terminal interativo (TTY)."
  exit 1
fi

ask() {
  local var_name="$1"
  local prompt="$2"
  local default_value="${3:-}"
  local input

  if [[ -n "${default_value}" ]]; then
    read -r -p "${prompt} [${default_value}]: " input
    input="${input:-${default_value}}"
  else
    read -r -p "${prompt}: " input
  fi

  while [[ -z "${input}" ]]; do
    read -r -p "${prompt}: " input
  done

  printf -v "${var_name}" '%s' "${input}"
}

ask_secret() {
  local var_name="$1"
  local prompt="$2"
  local default_value="${3:-}"
  local input

  if [[ -n "${default_value}" ]]; then
    read -r -s -p "${prompt} [ENTER mantém atual]: " input
    echo
    input="${input:-${default_value}}"
  else
    read -r -s -p "${prompt}: " input
    echo
  fi

  while [[ -z "${input}" ]]; do
    read -r -s -p "${prompt}: " input
    echo
  done

  printf -v "${var_name}" '%s' "${input}"
}

ask_optional() {
  local var_name="$1"
  local prompt="$2"
  local default_value="${3:-}"
  local input

  if [[ -n "${default_value}" ]]; then
    read -r -p "${prompt} [${default_value}]: " input
    input="${input:-${default_value}}"
  else
    read -r -p "${prompt}: " input
  fi

  printf -v "${var_name}" '%s' "${input}"
}

get_env_value() {
  local file_path="$1"
  local key="$2"
  local value

  value="$(grep -E "^${key}=" "${file_path}" | tail -n1 | cut -d'=' -f2- || true)"
  printf '%s' "${value}"
}

escape_sed_replacement() {
  printf '%s' "$1" | sed -e 's/[\\&|]/\\&/g'
}

upsert_env_value() {
  local file_path="$1"
  local key="$2"
  local raw_value="$3"
  local safe_value

  safe_value="$(escape_sed_replacement "${raw_value}")"

  if grep -qE "^${key}=" "${file_path}"; then
    sed -i "s|^${key}=.*|${key}=${safe_value}|" "${file_path}"
  else
    printf '%s=%s\n' "${key}" "${raw_value}" >> "${file_path}"
  fi
}

legacy_host_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_HOST")"
legacy_port_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_PORT")"
legacy_db_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_DATABASE")"
legacy_user_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_USERNAME")"
legacy_password_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_PASSWORD")"
legacy_socket_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_SOCKET")"
legacy_charset_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_CHARSET")"
legacy_collation_default="$(get_env_value "${APP_ENV_PATH}" "LEGACY_DB_COLLATION")"

legacy_host_default="${legacy_host_default:-127.0.0.1}"
legacy_port_default="${legacy_port_default:-3306}"
legacy_db_default="${legacy_db_default:-legacy}"
legacy_user_default="${legacy_user_default:-legacy}"
legacy_charset_default="${legacy_charset_default:-utf8mb4}"
legacy_collation_default="${legacy_collation_default:-utf8mb4_unicode_ci}"

ask LEGACY_DB_HOST "LEGACY_DB_HOST" "${legacy_host_default}"
ask LEGACY_DB_PORT "LEGACY_DB_PORT" "${legacy_port_default}"
ask LEGACY_DB_DATABASE "LEGACY_DB_DATABASE" "${legacy_db_default}"
ask LEGACY_DB_USERNAME "LEGACY_DB_USERNAME" "${legacy_user_default}"
ask_secret LEGACY_DB_PASSWORD "LEGACY_DB_PASSWORD" "${legacy_password_default}"
ask_optional LEGACY_DB_SOCKET "LEGACY_DB_SOCKET (pode ser vazio)" "${legacy_socket_default}"
ask LEGACY_DB_CHARSET "LEGACY_DB_CHARSET" "${legacy_charset_default}"
ask LEGACY_DB_COLLATION "LEGACY_DB_COLLATION" "${legacy_collation_default}"

cp "${MANIFEST_PATH}" "${MANIFEST_PATH}.bak.$(date +%Y%m%d%H%M%S)"

for file in "${MANIFEST_PATH}" "${APP_ENV_PATH}"; do
  upsert_env_value "${file}" "LEGACY_DB_HOST" "${LEGACY_DB_HOST}"
  upsert_env_value "${file}" "LEGACY_DB_PORT" "${LEGACY_DB_PORT}"
  upsert_env_value "${file}" "LEGACY_DB_DATABASE" "${LEGACY_DB_DATABASE}"
  upsert_env_value "${file}" "LEGACY_DB_USERNAME" "${LEGACY_DB_USERNAME}"
  upsert_env_value "${file}" "LEGACY_DB_PASSWORD" "${LEGACY_DB_PASSWORD}"
  upsert_env_value "${file}" "LEGACY_DB_SOCKET" "${LEGACY_DB_SOCKET}"
  upsert_env_value "${file}" "LEGACY_DB_CHARSET" "${LEGACY_DB_CHARSET}"
  upsert_env_value "${file}" "LEGACY_DB_COLLATION" "${LEGACY_DB_COLLATION}"
done

chmod 600 "${MANIFEST_PATH}" || true
chmod 600 "${APP_ENV_PATH}" || true

echo "Credenciais LEGACY_* salvas no manifest e no .env da instância."

echo "Recriando containers para aplicar env..."
(
  cd "${APP_DIR}"
  docker compose -p "plannerate-${APP_SLUG}" up -d --force-recreate
)

echo "Testando conexão mysql_legacy..."
(
  cd "${APP_DIR}"
  docker compose -p "plannerate-${APP_SLUG}" exec app php artisan tinker --execute 'DB::connection("mysql_legacy")->getPdo(); echo "OK";'
)

echo
echo "Qual comando você quer rodar?"
echo "  1) import:legacy-tenants"
echo "  2) import:source-client"
echo "  3) ambos (primeiro tenants, depois source-client)"
read -r -p "Escolha [1/2/3]: " run_choice

run_tenants() {
  local dry_run_flag="$1"
  local all_flag="$2"
  local skip_users_flag="$3"
  local fresh_users_flag="$4"
  local skip_rbac_flag="$5"

  local cmd=(php artisan import:legacy-tenants)
  [[ "${dry_run_flag}" == "y" ]] && cmd+=(--dry-run)
  [[ "${all_flag}" == "y" ]] && cmd+=(--all)
  [[ "${skip_users_flag}" == "y" ]] && cmd+=(--skip-users)
  [[ "${fresh_users_flag}" == "y" ]] && cmd+=(--fresh-users)
  [[ "${skip_rbac_flag}" == "y" ]] && cmd+=(--skip-rbac)

  (cd "${APP_DIR}" && docker compose -p "plannerate-${APP_SLUG}" exec app "${cmd[@]}")
}

run_source_client() {
  local tenant_arg="$1"
  local table_arg="$2"
  local dry_run_flag="$3"
  local fresh_flag="$4"

  local cmd=(php artisan import:source-client)
  [[ -n "${tenant_arg}" ]] && cmd+=("${tenant_arg}")
  [[ -n "${table_arg}" ]] && cmd+=("--table=${table_arg}")
  [[ "${dry_run_flag}" == "y" ]] && cmd+=(--dry-run)
  [[ "${fresh_flag}" == "y" ]] && cmd+=(--fresh)

  (cd "${APP_DIR}" && docker compose -p "plannerate-${APP_SLUG}" exec app "${cmd[@]}")
}

ask_yes_no() {
  local prompt="$1"
  local default_value="${2:-n}"
  local input
  local suffix="[y/N]"

  [[ "${default_value}" == "y" ]] && suffix="[Y/n]"

  read -r -p "${prompt} ${suffix}: " input
  input="${input:-${default_value}}"

  if [[ "${input}" =~ ^[Yy]$ ]]; then
    printf '%s' "y"
  else
    printf '%s' "n"
  fi
}

case "${run_choice}" in
  1)
    tenants_dry_run="$(ask_yes_no "Rodar import:legacy-tenants em dry-run?" "n")"
    tenants_all="$(ask_yes_no "Importar todos sem seleção interativa (--all)?" "n")"
    tenants_skip_users="$(ask_yes_no "Pular importação de usuários (--skip-users)?" "n")"
    tenants_fresh_users="$(ask_yes_no "Recriar usuários existentes (--fresh-users)?" "n")"
    tenants_skip_rbac="$(ask_yes_no "Pular seeder RBAC (--skip-rbac)?" "n")"
    run_tenants "${tenants_dry_run}" "${tenants_all}" "${tenants_skip_users}" "${tenants_fresh_users}" "${tenants_skip_rbac}"
    ;;
  2)
    ask_optional TENANT_ARG "Tenant (ULID ou slug; vazio = interativo)" ""
    ask_optional TABLE_ARG "Tabela específica (--table, vazio = todas)" ""
    source_dry_run="$(ask_yes_no "Rodar import:source-client em dry-run?" "n")"
    source_fresh="$(ask_yes_no "Limpar tabela destino antes (--fresh)?" "n")"
    run_source_client "${TENANT_ARG}" "${TABLE_ARG}" "${source_dry_run}" "${source_fresh}"
    ;;
  3)
    tenants_dry_run="$(ask_yes_no "Rodar import:legacy-tenants em dry-run?" "n")"
    tenants_all="$(ask_yes_no "Importar todos sem seleção interativa (--all)?" "n")"
    tenants_skip_users="$(ask_yes_no "Pular importação de usuários (--skip-users)?" "n")"
    tenants_fresh_users="$(ask_yes_no "Recriar usuários existentes (--fresh-users)?" "n")"
    tenants_skip_rbac="$(ask_yes_no "Pular seeder RBAC (--skip-rbac)?" "n")"
    run_tenants "${tenants_dry_run}" "${tenants_all}" "${tenants_skip_users}" "${tenants_fresh_users}" "${tenants_skip_rbac}"

    ask_optional TENANT_ARG "Tenant (ULID ou slug; vazio = interativo)" ""
    ask_optional TABLE_ARG "Tabela específica (--table, vazio = todas)" ""
    source_dry_run="$(ask_yes_no "Rodar import:source-client em dry-run?" "n")"
    source_fresh="$(ask_yes_no "Limpar tabela destino antes (--fresh)?" "n")"
    run_source_client "${TENANT_ARG}" "${TABLE_ARG}" "${source_dry_run}" "${source_fresh}"
    ;;
  *)
    echo "Opção inválida: ${run_choice}"
    exit 1
    ;;
esac

echo "Concluído."
