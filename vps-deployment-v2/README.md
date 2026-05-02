# VPS Deployment v2

Provisionamento e deploy multi-instância por `APP_SLUG` no mesmo VPS, com foco em staging estável (`dev -> staging`) e produção preparada para ativação futura.

## Resumo Operacional
- App principal de uma instância: `DOMAIN_LANDLORD` (ex.: `siga.dev.br`).
- Caminho da instância: `/opt/plannerate/<APP_SLUG>`.
- Monitoring da instância: `/opt/monitoring/<APP_SLUG>`.
- Traefik compartilhado: `/opt/traefik`.
- Deploy automático: branch `dev` -> workflow de staging.
- Health check do CI: interno no container (`http://127.0.0.1/up`).

## Fluxo Recomendado
1. Preparar DNS do domínio raiz da instância (`DOMAIN_LANDLORD`) para o IP do VPS.
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
- `DOMAIN_LANDLORD`: domínio raiz da app da instância.
- `GHCR_REPO`: imagem no GHCR.
- `DB_*`, `REDIS_PASSWORD`, `REVERB_*`: runtime da instância.

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
Ordem obrigatória no deploy:
1. Landlord:
```bash
php artisan migrate --force --database=landlord --path=database/migrations/landlord
```
2. Tenants:
```bash
php artisan tenants:artisan "migrate --force --database=tenant"
```

Se ainda não existir tenant cadastrado, o deploy continua sem falhar e apenas registra `No tenants found yet; skipping tenant migrations.`.

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
Prevenção: compose já publica `host.docker.internal:host-gateway` e `setup-db-host.sh` libera `172.16.0.0/12` para porta do banco em `DB_MODE=local`.

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

## Comandos Úteis
```bash
# bootstrap de secrets/vars no GitHub
automation/bootstrap-github.sh vps-deployment-v2/manifest.env

# instalar compose no host
APP_SLUG=staging automation/install-compose-on-host.sh

# instalar monitoring (com validação DNS)
APP_SLUG=staging automation/install-monitoring-on-host.sh vps-deployment-v2/manifest.env staging

# health check completo
automation/vps-health-check.sh vps-deployment-v2/manifest.env staging
```
