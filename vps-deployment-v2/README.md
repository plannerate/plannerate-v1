# VPS Deployment v2

Provisionamento e deploy multi-instância por `APP_SLUG` no mesmo VPS, com foco em staging estável (`dev -> staging`) e produção preparada para ativação futura.

## Resumo Operacional
- App principal de uma instância: `DOMAIN_LANDLORD` (ex.: `siga.dev.br`).
- Caminho da instância: `/opt/plannerate/<APP_SLUG>`.
- Monitoring da instância: `/opt/monitoring/<APP_SLUG>`.
- Traefik compartilhado: `/opt/traefik`.
- Deploy automático: branch `dev` -> workflow de staging (branch `main` -> futuro workflow de produção).
- Health check do CI: interno no container (`http://127.0.0.1/up`).
- Tags de imagem: `staging-<sha>` + `branch-staging-latest` (build de `dev`); `production-<sha>` + `branch-production-latest` (build de `main`).
- **Wayfinder**: URLs são geradas em build-time a partir de `DOMAIN_LANDLORD` do `manifest.env`. Alterações no domínio requerem rebuild da imagem.

## Fluxo Recomendado
1. Preparar DNS do domínio raiz da instância (`DOMAIN_LANDLORD`) para o IP do VPS.
   - Em Cloudflare, iniciar com `DNS only` (nuvem cinza) para evitar atrito de ACME/WebSocket.
   - Criar `A` para raiz e `A` curinga (`*.<DOMAIN_LANDLORD>`) para tenants por subdomínio.
2. Rodar setup:
```bash
bash vps-deployment-v2/setup.sh
```
3. Durante o setup:
- informar `APP_SLUG` (ex.: `staging`)
- informar `DOMAIN_LANDLORD`
- escolher `DB_MODE`:
  - `local`: provisiona MySQL/PostgreSQL automaticamente na VPS e cria database/user
  - `externo`: exibe credenciais esperadas e aguarda ENTER após configuração manual
- permitir provisionamento e instalação de compose
4. Push em `dev` para validar build+deploy.

## Regras Importantes
- `APP_SLUG` é obrigatório como identificador lógico de instância.
- `queue` usa `php artisan queue:work` (não depende de Horizon).
- Não subir monitoring antes de DNS dos subdomínios estar pronto.
- Dashboard auth do Traefik com `$` precisa de escape (`$$`).
- Migrações seguem Spatie Multitenancy: landlord primeiro, tenants depois.

## Variáveis-Chave
- `APP_SLUG`: nome da instância (pasta/projeto docker/routers).
- `DOMAIN_LANDLORD`: domínio raiz da app da instância. Usado em runtime **e** em build-time (via `WAYFINDER_LANDLORD_DOMAIN`) para gerar as URLs do Wayfinder no JS bundle. Deve estar correto no `manifest.env` antes do build.
- `GHCR_REPO`: imagem no GHCR.
- `DB_*`, `REDIS_PASSWORD`, `REVERB_*`: runtime da instância.

### Pipeline CI/CD
| Branch | DEPLOY_CHANNEL | Formato de tag |
|--------|---------------|----------------|
| `dev`  | `staging`     | `staging-<sha>`, `branch-staging-latest` |
| `main` | `production`  | `production-<sha>`, `branch-production-latest` |

- `vps-v2-build-push` dispara apenas em push para `dev` (por ora).
- `vps-v2-deploy-staging` só executa se `head_branch == 'dev'` (filtro no trigger + condição do job).
- O workflow lê `DOMAIN_LANDLORD` de `vps-deployment-v2/manifest.env` e passa como `WAYFINDER_LANDLORD_DOMAIN` build-arg ao Docker. Se esse valor estiver ausente ou errado, o JS bundle conterá URLs `//localhost/...`.

## Validação Pós-Provisionamento
### Local (máquina de operação)
```bash
ssh -i ~/.ssh/id_ed25519_<repo>_deploy deploy@<VPS_IP>
```

### No VPS
```bash
cd /opt/plannerate/<APP_SLUG>
docker compose -p plannerate-<APP_SLUG> ps
docker compose -p plannerate-<APP_SLUG> exec -T app sh -lc 'curl -fsS http://127.0.0.1/up >/dev/null && echo OK'
```

### Traefik
```bash
cd /opt/traefik
docker compose ps
ss -tulpen | grep -E ':80|:443'
```

## Deploy Diário
1. Commit/push em `dev`.
2. Confirmar `vps-v2-build-push` OK.
3. Confirmar `vps-v2-deploy-staging` OK.
4. Verificar stack no VPS e logs se necessário.

## Migrações Multi-Tenancy (Spatie)
No workflow de staging atual, o deploy executa apenas migração landlord.
Ordem recomendada para execução manual:
1. Landlord:
```bash
php artisan migrate --force --database=landlord --path=database/migrations/landlord
```
2. Tenants:
```bash
php artisan tenants:artisan "migrate --force --database=tenant"
```

## Incidentes Reais e Prevenção
### 1) `ssh: unable to authenticate`
Sintoma: GitHub Action falha no `appleboy/ssh-action`.
Causa: `deploy` sem `authorized_keys` ou chave divergente.
Correção:
```bash
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
cat >> /home/deploy/.ssh/authorized_keys  # colar chave pública
chmod 600 /home/deploy/.ssh/authorized_keys
chown -R deploy:deploy /home/deploy/.ssh
```
Prevenção: usar `setup-app-host.sh` + `bootstrap-github.sh`.

### 2) `WARNING: REMOTE HOST IDENTIFICATION HAS CHANGED`
Causa: host key do servidor alterada.
Correção local:
```bash
ssh-keygen -f '/home/<user>/.ssh/known_hosts' -R '<VPS_IP>'
ssh-keyscan -H <VPS_IP> >> /home/<user>/.ssh/known_hosts
```
Prevenção: atualizar também `SSH_KNOWN_HOSTS` no environment `staging`.

### 3) `No APP_KEY variable was found` / `.env` read-only
Causa: tentar `key:generate` dentro de container com bind read-only.
Correção: gerar `APP_KEY` no host e re-subir stack.
Prevenção: `setup-app-host.sh` já escreve `APP_KEY`.

### 4) Reverb/Pusher null key (`auth_key null`)
Causa: `REVERB_APP_KEY/SECRET/ID` ausentes.
Correção: definir vars no `.env` da instância e recriar containers.
Prevenção: `setup-app-host.sh` já escreve `REVERB_APP_*`.

### 5) `404` no `/up` público durante deploy
Causa frequente: dependência de DNS/CDN no health check do workflow.
Correção: health check interno do container no CI.
Prevenção: manter workflow com check interno (`127.0.0.1/up`).

### 6) `Command "horizon" is not defined`
Causa: imagem sem Horizon instalado.
Correção: usar `queue:work`.
Prevenção: compose padrão já usa `queue:work --sleep=3 --tries=3 --max-time=3600`.

### 7) ACME `NXDOMAIN` + `429 rateLimited`
Causa: Traefik tentou emitir cert sem DNS pronto.
Correção: criar registros DNS, aguardar janela de retry e reiniciar Traefik.
Prevenção: `install-monitoring-on-host.sh` valida DNS antes de subir monitoring.

### 8) `SQLSTATE[HY000] [2002] Connection timed out` no migrate
Causa: app em container sem rota/permit para MySQL local no host.
Correção:
```bash
cd /opt/plannerate/<APP_SLUG>
grep -E '^(DB_HOST|DB_LANDLORD_HOST|DB_CONNECTION)=' .env
# esperado para local:
# DB_HOST=host.docker.internal
# DB_LANDLORD_HOST=host.docker.internal
# DB_CONNECTION=landlord
```
Se `host.docker.internal` não resolver na VPS, use fallback `172.17.0.1` (ou ajuste `DB_LOCAL_HOST_FALLBACK` no manifest).
Prevenção: compose já publica `host.docker.internal:host-gateway` e `setup-db-host.sh` libera `172.16.0.0/12` para porta do banco em `DB_MODE=local`.

### 8.1) PostgreSQL tentando conectar no banco com nome do usuário (`plannerate_stg`)
Causa raiz: a doc do Spatie para múltiplos bancos recomenda deixar a conexão `tenant` com `database = null`. Isso funciona quando a conexão `tenant` só é usada depois que existe um tenant atual e o `SwitchTenantDatabaseTask` já trocou o database dinamicamente. Neste projeto, porém, há código e migrations tenant-first que podem tocar a conexão `tenant` antes desse contexto existir.

No PostgreSQL, quando a conexão é aberta com `database = null`, o driver tenta usar o nome do usuário como `dbname`. Se o usuário for `plannerate_stg`, o erro vira:
```text
SQLSTATE[08006] [7] FATAL: database "plannerate_stg" does not exist
```

Pontos de código relevantes no projeto:
- `config/database.php`: a conexão `tenant` lê `DB_TENANT_DATABASE`; se vier `null`, a configuração final fica sem database definido.
- `config/multitenancy.php`: `tenant_database_connection_name = 'tenant'` e `SwitchTenantDatabaseTask::class` só corrigem a conexão quando há tenant current.
- `database/migrations/2026_04_22_200000_create_categories_table.php`: a migration tenant já nasce com `protected $connection = 'tenant';`.
- `app/Models/Traits/UsesTenantConnection.php`: modelos tenant retornam sempre a conexão configurada como `tenant`.
- `app/Models/Category.php`: exemplo concreto de model tenant que usa `UsesTenantConnection`.

Diagnóstico rápido no container:
```bash
docker compose -p plannerate-<APP_SLUG> exec -T app php artisan tinker --execute '
dump(config("database.connections.tenant"));
'
```

Se o retorno mostrar `"database" => null`, o ambiente ainda está vulnerável a esse problema.

**Solução mais simples (recomendada):** usar o mesmo nome para o banco e o usuário PostgreSQL.
Se `DB_LANDLORD_DATABASE=plannerate_stg` e `DB_LANDLORD_USERNAME=plannerate_stg`, o fallback do PostgreSQL (`dbname = username`) passa a funcionar corretamente — a conexão `tenant` com `database = null` conecta no banco correto sem precisar de workaround. Isso elimina o problema na raiz, sem necessidade de tratar `null` no workflow.

Estratégia aplicada no `vps-deployment-v2` (para ambientes com nomes diferentes):
- o provisionamento não deve mais gravar `DB_TENANT_DATABASE=null` no bootstrap inicial;
- na ausência de tenant current, o bootstrap usa temporariamente o banco landlord da instância para evitar DSN inválido no PostgreSQL.

Se precisar conferir a origem do problema no código da app:
```bash
sed -n '58,74p' config/database.php
sed -n '43,45p' config/multitenancy.php
sed -n '1,40p' database/migrations/2026_04_22_200000_create_categories_table.php
sed -n '1,40p' app/Models/Traits/UsesTenantConnection.php
```

### 9) `Please provide a valid cache path`
Causa: diretórios de cache/views não existentes ou sem permissão no container.
Correção:
```bash
docker compose -p plannerate-<APP_SLUG> exec -T app sh -lc '
  mkdir -p storage/framework/views storage/framework/cache storage/framework/sessions bootstrap/cache
  chmod -R ug+rwX storage bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
'
```
Prevenção: workflows de deploy/rollback já executam essa preparação antes das migrações.

### 10) `Redis connection [landlord] not configured`
Causa: `SESSION_DRIVER=redis` com `SESSION_CONNECTION=landlord` (valor default do projeto), mas Redis só tem conexões `default` e `cache`.
Correção:
```bash
sed -i 's/^SESSION_CONNECTION=.*/SESSION_CONNECTION=default/' /opt/plannerate/<APP_SLUG>/.env
grep -q '^REDIS_CACHE_CONNECTION=' /opt/plannerate/<APP_SLUG>/.env || echo 'REDIS_CACHE_CONNECTION=cache' >> /opt/plannerate/<APP_SLUG>/.env
docker compose -p plannerate-<APP_SLUG> up -d --force-recreate
```
Prevenção: `setup-app-host.sh` já escreve `SESSION_CONNECTION=default` e `REDIS_CACHE_CONNECTION=cache`.

### 12) URLs `//localhost/...` ou `//siga.dev.br/...` erradas nos assets JS (Wayfinder)
Sintoma: requests do frontend vão para `//localhost/api/...` ou para o domínio errado; visível no Network tab ou `browser-logs`.
Causa: `php artisan wayfinder:generate` é executado durante o Docker build (estágio `wayfinder`). As funções TypeScript geradas contêm a URL base derivada de `config('app.landlord_domain')`, que por sua vez lê `LANDLORD_DOMAIN` no `.env`. Se essa variável não for injetada na build, o fallback é `localhost` ou o default hardcoded em `config/app.php`.
Correção: garantir que `DOMAIN_LANDLORD` esteja definido em `vps-deployment-v2/manifest.env` e fazer novo push em `dev` para forçar rebuild da imagem:
```bash
# verificar manifest
grep DOMAIN_LANDLORD vps-deployment-v2/manifest.env
# forçar rebuild
git commit --allow-empty -m "chore: rebuild wayfinder URLs" && git push origin dev
```
Prevenção: o workflow `vps-v2-build-push` lê `DOMAIN_LANDLORD` do `manifest.env` e passa como `WAYFINDER_LANDLORD_DOMAIN` build-arg. O `Dockerfile.prod` falha explicitamente se esse valor estiver vazio.

### 11) `GET /dashboard 404` no domínio principal
Causa: `LANDLORD_DOMAIN` ausente/incorreto no `.env`, então as rotas com `Route::domain(config('app.landlord_domain'))` não casam no host real.
Correção:
```bash
sed -i 's/^LANDLORD_DOMAIN=.*/LANDLORD_DOMAIN=siga.dev.br/' /opt/plannerate/<APP_SLUG>/.env || echo 'LANDLORD_DOMAIN=siga.dev.br' >> /opt/plannerate/<APP_SLUG>/.env
sed -i 's|^APP_URL=.*|APP_URL=https://siga.dev.br|' /opt/plannerate/<APP_SLUG>/.env
sed -i 's|^ASSET_URL=.*|ASSET_URL=https://siga.dev.br|' /opt/plannerate/<APP_SLUG>/.env
docker compose -p plannerate-<APP_SLUG> exec -T app php artisan optimize:clear
docker compose -p plannerate-<APP_SLUG> up -d --force-recreate
```
Prevenção: `setup-app-host.sh` já escreve `LANDLORD_DOMAIN=${DOMAIN_LANDLORD}`.

## DNS/ACME Guardrails
Antes de monitoring/reverb público:
- criar `A/AAAA` para:
  - `grafana.<DOMAIN_LANDLORD>`
  - `prometheus.<DOMAIN_LANDLORD>`
  - `alerts.<DOMAIN_LANDLORD>`
  - `reverb.<DOMAIN_LANDLORD>` (se ativo)
  - `traefik.<DOMAIN_LANDLORD>` (se dashboard público)
  - `pgadmin.<DOMAIN_LANDLORD>` (se `ENABLE_PGADMIN=true` em `dev/staging`)

## pgAdmin Opcional (dev/staging)
- Habilitar no manifest:
```bash
ENABLE_PGADMIN=true
PGADMIN_DOMAIN=pgadmin.<DOMAIN_LANDLORD>
PGADMIN_DEFAULT_EMAIL=admin@<DOMAIN_LANDLORD>
PGADMIN_DEFAULT_PASSWORD=<senha-forte>
```
- O instalador de monitoring só habilita pgAdmin para `APP_SLUG=dev` ou `APP_SLUG=staging`.
- Em produção (`APP_SLUG=production`), `ENABLE_PGADMIN=true` é ignorado por segurança.

## Comandos Úteis
```bash
# bootstrap de secrets/vars no GitHub
automation/bootstrap-github.sh vps-deployment-v2/manifest.env

# instalar compose no host
APP_SLUG=staging START_SERVICES=true automation/install-compose-on-host.sh

# instalar monitoring (com validação DNS)
APP_SLUG=staging automation/install-monitoring-on-host.sh vps-deployment-v2/manifest.env staging

# health check completo
automation/vps-health-check.sh vps-deployment-v2/manifest.env staging
```
