#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
START_SERVICES="${START_SERVICES:-false}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root"
    exit 1
fi

install -d -m 750 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" /opt/production /opt/staging
install -d -m 755 /opt/traefik
install -d -m 700 /opt/traefik/letsencrypt
touch /opt/traefik/letsencrypt/acme.json
chmod 600 /opt/traefik/letsencrypt/acme.json

cp "${ROOT_DIR}/deployments/docker-compose.production.yml" /opt/production/docker-compose.yml
cp "${ROOT_DIR}/deployments/docker-compose.staging.yml" /opt/staging/docker-compose.yml
cp "${ROOT_DIR}/deployments/traefik/docker-compose.yml" /opt/traefik/docker-compose.yml

chown "${DEPLOY_USER}:${DEPLOY_USER}" /opt/production/docker-compose.yml /opt/staging/docker-compose.yml
chmod 640 /opt/production/docker-compose.yml /opt/staging/docker-compose.yml

if [[ "${START_SERVICES}" == "true" ]]; then
    docker network create traefik-global >/dev/null 2>&1 || true
    (cd /opt/traefik && docker compose up -d)
    (cd /opt/staging && docker compose pull && docker compose up -d)
    (cd /opt/production && docker compose pull && docker compose up -d)
fi

echo "Compose files installed to /opt"
