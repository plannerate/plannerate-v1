#!/usr/bin/env bash

set -euo pipefail

usage() {
  cat <<'EOF'
Uso:
  ./scripts/configure-github-pat.sh [--token TOKEN] [--update-lock] [--yes]

Opcoes:
  --token TOKEN   Usa token informado via argumento (evita prompt interativo)
  --update-lock   Roda composer update --lock --no-interaction --no-scripts
  --yes           Nao pede confirmacao para o --update-lock
  --help          Exibe esta ajuda

Exemplos:
  ./scripts/configure-github-pat.sh
  ./scripts/configure-github-pat.sh --update-lock
  ./scripts/configure-github-pat.sh --token "$GHPAT" --update-lock --yes
EOF
}

token=""
update_lock="0"
assume_yes="0"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --token)
      shift
      token="${1:-}"
      ;;
    --update-lock)
      update_lock="1"
      ;;
    --yes)
      assume_yes="1"
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Opcao invalida: $1" >&2
      usage >&2
      exit 1
      ;;
  esac
  shift
done

if [[ -z "$token" ]]; then
  read -rsp "GitHub PAT: " token
  echo
fi

token="$(printf '%s' "$token" | tr -d '\r\n')"

if [[ -z "$token" ]]; then
  echo "Erro: token vazio." >&2
  exit 1
fi

if ! command -v ./vendor/bin/sail >/dev/null 2>&1; then
  echo "Erro: execute no diretorio raiz do projeto (sail nao encontrado)." >&2
  exit 1
fi

if ! command -v curl >/dev/null 2>&1; then
  echo "Erro: curl nao encontrado no host." >&2
  exit 1
fi

echo "Validando token no GitHub..."
response="$(curl -sS -H "Authorization: Bearer $token" https://api.github.com/user)"

if grep -q '"message"[[:space:]]*:[[:space:]]*"Bad credentials"' <<<"$response"; then
  echo "Erro: token invalido (Bad credentials)." >&2
  exit 1
fi

if ! grep -q '"login"' <<<"$response"; then
  echo "Erro: nao foi possivel validar o token. Resposta recebida:" >&2
  echo "$response" >&2
  exit 1
fi

echo "Token valido. Gravando no Composer auth global dentro do Sail..."
./vendor/bin/sail bash -lc 'php -r '\''
$files = [getenv("HOME") . "/.composer/auth.json", getenv("HOME") . "/.config/composer/auth.json"];
foreach ($files as $file) {
  if (! is_file($file)) {
    continue;
  }

  $content = file_get_contents($file);
  if ($content === false) {
    continue;
  }

  $data = json_decode($content, true);
  if (! is_array($data)) {
    continue;
  }

  $githubOauth = $data["github-oauth"] ?? null;
  if (is_array($githubOauth) && array_key_exists("github.com", $githubOauth) && trim((string) $githubOauth["github.com"]) === "") {
    unset($data["github-oauth"]["github.com"]);
    if (($data["github-oauth"] ?? null) === []) {
      $data["github-oauth"] = new stdClass();
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  }
}
'\'''

./vendor/bin/sail composer config --global --auth --unset github-oauth.github.com || true
./vendor/bin/sail composer config --global --auth github-oauth.github.com "$token"

echo "Limpando cache do Composer..."
./vendor/bin/sail composer clear-cache

if [[ "$update_lock" == "1" ]]; then
  if [[ "$assume_yes" != "1" ]]; then
    read -rp "Rodar composer update --lock agora? [y/N] " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
      echo "Update do lock cancelado."
      exit 0
    fi
  fi

  echo "Atualizando composer.lock..."
  ./vendor/bin/sail composer update --lock --no-interaction --no-scripts
fi

echo "Concluido com sucesso."