#!/usr/bin/env bash
# Caminho rápido pra rodar testes localmente sem remigrar tudo em todo processo.
#
# Usa arquivos SQLite persistentes em storage/testing/ (git-ignorado) em vez
# do :memory: de phpunit.xml/.env.testing — nunca toca o banco de
# desenvolvimento real (Postgres/MySQL) nem CI, que continuam intocados.
#
# Uso: ./scripts/test-fast.sh --filter=NomeDoTeste
set -euo pipefail

docker compose exec -e APP_ENV=testing php php artisan test:fast-db:prepare

docker compose exec \
  -e APP_ENV=testing \
  -e PLANNERATE_FAST_TESTS=true \
  -e DB_CONNECTION=sqlite \
  -e DB_DATABASE=storage/testing/work-default.sqlite \
  -e DB_TENANT_CONNECTION=sqlite \
  -e DB_TENANT_DATABASE=storage/testing/work-tenant.sqlite \
  -e DB_LANDLORD_CONNECTION=sqlite \
  -e DB_LANDLORD_DATABASE=storage/testing/work-landlord.sqlite \
  php php artisan test --compact "$@"
