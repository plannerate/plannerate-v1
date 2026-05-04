#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"
ROOT_DIR="$(cd -- "${SCRIPT_DIR}/.." >/dev/null 2>&1 && pwd)"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
APP_SLUG="${APP_SLUG:-staging}"
START_SERVICES="${START_SERVICES:-false}"
APP_DIR="/opt/plannerate/${APP_SLUG}"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root"
    exit 1
fi

install -d -m 750 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}"
install -d -m 755 /opt/traefik
install -d -m 700 /opt/traefik/letsencrypt
touch /opt/traefik/letsencrypt/acme.json
chmod 600 /opt/traefik/letsencrypt/acme.json

cp "${ROOT_DIR}/deployments/docker-compose.staging.yml" "${APP_DIR}/docker-compose.yml"
cp "${ROOT_DIR}/deployments/traefik/docker-compose.yml" /opt/traefik/docker-compose.yml

chown "${DEPLOY_USER}:${DEPLOY_USER}" "${APP_DIR}/docker-compose.yml"
chmod 640 "${APP_DIR}/docker-compose.yml"

if [[ "${START_SERVICES}" == "true" ]]; then
    docker network create traefik-global >/dev/null 2>&1 || true

    echo "[traefik] Starting..."
    (cd /opt/traefik && docker compose up -d)
    # Wait for Traefik to be healthy before continuing
    _traefik_up=false
    for _i in $(seq 1 15); do
        if docker inspect --format '{{.State.Running}}' traefik-global 2>/dev/null | grep -q true; then
            _traefik_up=true
            break
        fi
        sleep 2
    done
    if [[ "${_traefik_up}" == "true" ]]; then
        echo "[traefik] Running"
    else
        echo "[traefik] WARNING: container not running after 30s — check: docker logs traefik-global"
    fi

    # App image may not exist yet on first provisioning — don't fail Traefik over it
    echo "[app] Pulling image for plannerate-${APP_SLUG}..."
    if (cd "${APP_DIR}" && docker compose -p "plannerate-${APP_SLUG}" pull 2>&1); then
        (cd "${APP_DIR}" && docker compose -p "plannerate-${APP_SLUG}" up -d)
        echo "[app] Started plannerate-${APP_SLUG}"
    else
        echo "[app] WARNING: image pull failed (not yet published?). Traefik is running. Deploy via GitHub Actions to start the app."
    fi
fi

echo "Compose files installed to ${APP_DIR} and /opt/traefik"
