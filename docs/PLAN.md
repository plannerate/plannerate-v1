# 📚 Documentação Completa - Plannerate Multi-Tenant

## 🎯 Visão Geral da Arquitetura

Sistema de planogramas multi-tenant com **3 ambientes isolados**, **CI/CD automatizado**, e **infraestrutura híbrida Cloudflare + VPS**.

### Ambientes

| Ambiente | Localização | Domínio | Propósito |
|----------|-------------|---------|-----------|
| **Local** | Seu PC | `*.plannerate.local` | Desenvolvimento ativo |
| **Staging** | VPS | `*.plannerate.dev.br` | Testes pré-produção |
| **Production** | VPS | `*.plannerate.com.br` | Clientes reais |

---

## 🏗️ Arquitetura de Rede

### Fluxo de Requisições HTTP/HTTPS

```
Cliente (Browser/App)
    ↓
CLOUDFLARE (Edge Network)
    ├── SSL/TLS Termination
    ├── DDoS Protection  
    ├── CDN (cache de assets)
    ├── Firewall (WAF)
    └── DNS Management
    ↓
VPS (148.230.78.184) - São Paulo
    ↓
TRAEFIK (Reverse Proxy)
    ├── Routing multi-tenant (por subdomínio)
    ├── SSL Origin Certificate
    ├── Headers de segurança
    └── Health checks
    ↓
┌─────────┬─────────┬─────────┐
│ Laravel │ Reverb  │  Redis  │
│  App    │  (HTTP) │         │
└─────────┴─────────┴─────────┘
    ↓           ↓
PostgreSQL   Queue Workers
```

### Fluxo WebSockets (Bypass Cloudflare)

```
Cliente (Browser/App)
    ↓
DNS Lookup: ws.plannerate.com.br
    ↓
DIRETO → VPS (sem proxy Cloudflare)
    ↓
TRAEFIK (porta 8080)
    ↓
REVERB (WebSocket Server)
```

**Por que bypass?**
- Cloudflare Free tem timeout de 100s em WebSockets
- Reverb precisa de conexões persistentes
- Ping/pong heartbeat evita timeouts, mas conexão direta é mais confiável

---

## 🌐 Configuração Cloudflare

### DNS Records

#### Produção (plannerate.com.br)

| Tipo | Nome | Destino | Proxy | Explicação |
|------|------|---------|-------|------------|
| A | `plannerate.com.br` | `148.230.78.184` | ✅ Proxied | Domínio raiz via Cloudflare |
| A | `*.plannerate.com.br` | `148.230.78.184` | ✅ Proxied | Wildcard para todos subdomínios (tenants) |
| A | `ws.plannerate.com.br` | `148.230.78.184` | ❌ DNS Only | WebSockets direto (sem timeout) |
| CNAME | `www` | `plannerate.com.br` | ✅ Proxied | Redirect www → raiz |

#### Staging (plannerate.dev.br)

| Tipo | Nome | Destino | Proxy |
|------|------|---------|-------|
| A | `plannerate.dev.br` | `148.230.78.184` | ✅ Proxied |
| A | `*.plannerate.dev.br` | `148.230.78.184` | ✅ Proxied |
| A | `ws.plannerate.dev.br` | `148.230.78.184` | ❌ DNS Only |

**Legenda:**
- **☁️ Proxied (laranja):** Traffic passa pelo Cloudflare (SSL, cache, proteção)
- **☁️ DNS Only (cinza):** Apenas resolução DNS, conexão direta à VPS

---

### SSL/TLS Configuration

**Modo de Criptografia:** Full (Strict)
- Cloudflare ↔ Cliente: SSL gerenciado pelo Cloudflare (wildcard automático)
- Cloudflare ↔ VPS: SSL via Origin Certificate (gerado no Cloudflare)
- Requer certificado válido na VPS (não pode ser self-signed)

**Settings:**
- **Always Use HTTPS:** ON (redireciona HTTP → HTTPS)
- **Automatic HTTPS Rewrites:** ON
- **Minimum TLS Version:** 1.2 (recomendo 1.3)
- **Opportunistic Encryption:** ON
- **TLS 1.3:** ON

**Origin Certificate:**
- Gerar no Dashboard Cloudflare: SSL/TLS → Origin Server
- Hostnames: `*.plannerate.com.br, plannerate.com.br, *.plannerate.dev.br, plannerate.dev.br`
- Validade: 15 anos
- Salvar na VPS: `/etc/ssl/cloudflare/` (Traefik irá usar)

---

### Page Rules (Plano Free = 3 regras)

**Estratégia de uso inteligente:**

**Regra #1 - Cache Agressivo para Assets**
- **URL Pattern:** `*plannerate.com.br/build/*`
- **Settings:**
  - Cache Level: Standard
  - Edge Cache TTL: 1 month
  - Browser Cache TTL: 1 year
- **Objetivo:** Assets do Vite (JS/CSS compilados) raramente mudam

**Regra #2 - Bypass Cache para APIs e Broadcasting**
- **URL Pattern:** `*plannerate.com.br/(api|broadcasting)/*`
- **Settings:**
  - Cache Level: Bypass
  - Disable Performance
- **Objetivo:** Conteúdo dinâmico nunca deve ser cacheado

**Regra #3 - Cache para Imagens de Produtos**
- **URL Pattern:** `*plannerate.com.br/storage/images/*`
- **Settings:**
  - Cache Level: Standard
  - Edge Cache TTL: 7 days
  - Browser Cache TTL: 1 month
- **Objetivo:** Imagens de produtos mudam ocasionalmente

**Alternativa:** Se precisar de mais controle, pode combinar regras 2 e 3, liberando uma slot.

---

### Firewall Rules (Plano Free = 5 regras)

**Estratégia de defesa em camadas:**

**Regra #1 - Rate Limit Login/Register**
- **Expressão:** `(http.request.uri.path contains "/login" or http.request.uri.path contains "/register") and (rate(10m) > 10)`
- **Ação:** Block
- **Objetivo:** Prevenir brute force (máx 10 tentativas/10min)

**Regra #2 - Proteção Admin**
- **Expressão:** `http.request.uri.path contains "/admin"`
- **Ação:** JS Challenge
- **Objetivo:** CAPTCHA invisível para área administrativa

**Regra #3 - Block Known Bad Bots**
- **Expressão:** `(cf.client.bot and cf.threat_score > 30)`
- **Ação:** Block
- **Objetivo:** Bots maliciosos identificados pelo Cloudflare

**Regra #4 - Rate Limiting Global**
- **Expressão:** `(rate(1m) > 100)`
- **Ação:** Challenge
- **Objetivo:** Máx 100 requests/min por IP (ajustar conforme necessidade)

**Regra #5 - Geo-Blocking (Opcional)**
- **Expressão:** `ip.geoip.country in {"CN" "RU" "KP"}`
- **Ação:** Block
- **Objetivo:** Se 99% dos clientes são BR, pode bloquear países irrelevantes
- **Atenção:** Ajuste conforme necessidade real (não bloquear sem motivo)

---

### Cache Configuration

**Caching Level:** Standard
- Respeita headers Cache-Control do Laravel
- Edge cache conforme Page Rules

**Browser Cache TTL:** Respect Existing Headers
- Laravel define `Cache-Control` apropriado
- Assets com hash (Vite) podem ter cache longo

**Always Online™:** ON
- Se VPS cair, Cloudflare serve versão cached
- Funciona para páginas públicas (não para dashboard autenticado)

**Development Mode:**
- OFF normalmente
- Ativar temporariamente durante deploy para purgar cache
- Auto-desativa após 3 horas

**Purge Cache:**
- **Manual:** Dashboard → Caching → Purge Everything
- **Seletivo:** Purge by URL (para updates específicos)
- **Automático:** API call no GitHub Actions após deploy

---

### Network Settings

**HTTP/2:** ON
- Performance melhorada (multiplexing)
- Suportado por todos browsers modernos

**HTTP/3 (QUIC):** ON
- Protocolo mais moderno (sobre UDP)
- Reduz latência

**WebSockets:** ON
- **CRÍTICO** para Reverb funcionar
- Mesmo com bypass (DNS Only), útil para fallback

**IP Geolocation:** ON
- Headers `CF-IPCountry`, `CF-IPCity` disponíveis
- Laravel pode usar para analytics/personalização

**Onion Routing:** OFF (ou ON se quiser acessibilidade via Tor)

**Pseudo IPv4:** Add Header
- Compatibilidade com apps que esperam IPv4

---

### Security Settings

**Security Level:** Medium
- Balanço entre proteção e false positives
- Pode aumentar para High se sofrer ataques

**Challenge Passage:** 30 minutes
- Usuário que passa CAPTCHA não precisa resolver novamente por 30min

**Browser Integrity Check:** ON
- Bloqueia browsers conhecidamente maliciosos

**Privacy Pass Support:** ON
- Usuários com Privacy Pass tokens evitam CAPTCHAs repetidos

**Hotlink Protection:** OFF (ou ON se quiser proteger imagens)

---

### Speed Settings

**Auto Minify:**
- HTML: OFF (Laravel já minifica via Blade)
- CSS: OFF (Vite já otimiza)
- JavaScript: OFF (Vite já otimiza)
- **Recomendo tudo OFF** - evita conflitos com build moderno

**Brotli Compression:** ON
- Compressão melhor que gzip
- Suportado por browsers modernos

**Early Hints:** ON
- Envia headers de recursos estáticos antes do HTML
- Melhora carregamento inicial

**Rocket Loader™:** OFF
- Pode quebrar JS moderno (modules)
- Vite já otimiza loading

**Mirage:** OFF (recurso descontinuado)

---

## 🔧 Ajustes no Laravel (Conceitual)

### 1. Trust Proxies Middleware

**Objetivo:** Laravel precisa confiar nos headers do Cloudflare para obter IP real do cliente.

**Headers importantes:**
- `X-Forwarded-For` → IP real do cliente
- `X-Forwarded-Proto` → https (mesmo se Cloudflare→VPS for http interno)
- `X-Forwarded-Port` → Porta original (443)
- `CF-Connecting-IP` → IP do cliente (específico Cloudflare)
- `CF-RAY` → Request ID único (útil para debug)

**Proxies confiáveis:**
- Lista de IPs Cloudflare (IPv4 e IPv6) - atualizar periodicamente
- IPs da rede Docker interna (Traefik)

**Manter atualizado:** Cloudflare publica lista oficial de IPs: `https://www.cloudflare.com/ips/`

---

### 2. Configuração Reverb (WebSockets)

**Ambiente de Produção:**
- Host: `ws.plannerate.com.br` (subdomínio DNS Only)
- Porta: 443 (HTTPS)
- Scheme: `https`
- Ping Interval: 60 segundos (heartbeat antes de qualquer timeout)

**Ambiente de Staging:**
- Host: `ws.plannerate.dev.br`
- Porta: 443
- Scheme: `https`

**Ambiente Local:**
- Host: `localhost`
- Porta: 8080
- Scheme: `http`

**Frontend (Vite):**
- Variáveis `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`
- Cliente JS conecta no endpoint correto por ambiente

**Allowed Origins:**
- Produção: `*.plannerate.com.br`
- Staging: `*.plannerate.dev.br`
- Local: `localhost:*`

---

### 3. CORS Configuration

**Paths protegidos:** `/api/*`, `/broadcasting/*`, `/reverb/*`

**Allowed Origins:**
- Wildcards: `https://*.plannerate.com.br`, `https://*.plannerate.dev.br`
- Padrões regex para validação dinâmica de subdomínios

**Credentials:** `true` (necessário para cookies/sessions multi-tenant)

**Allowed Methods:** GET, POST, PUT, PATCH, DELETE, OPTIONS

**Exposed Headers:** Permitir que frontend acesse headers customizados

---

### 4. Session e Cookies (Multi-Tenant)

**Session Domain:** `.plannerate.com.br` (com ponto inicial)
- Cookie funciona em todos subdomínios
- `burda.plannerate.com.br` e `extra.plannerate.com.br` compartilham session domain

**Secure Cookies:**
- Produção/Staging: `true` (HTTPS obrigatório)
- Local: `false` (HTTP permitido)

**SameSite:** `lax` ou `none` (depende da arquitetura de autenticação)

**Session Lifetime:** Ajustar conforme necessidade (padrão 120 minutos)

---

### 5. Logging com Contexto Cloudflare

**Informações adicionais nos logs:**
- IP real do cliente (`CF-Connecting-IP`)
- País de origem (`CF-IPCountry`)
- Ray ID para correlação (`CF-RAY`)
- User Agent original

**Processadores de log:**
- Adicionar contexto automático em cada log entry
- Facilita troubleshooting: "qual IP causou erro 500?"

**Formato estruturado:**
- JSON para fácil parsing (ferramentas como Elasticsearch, Grafana Loki)
- Timestamp, level, message, context (IP, país, tenant)

---

### 6. Cache e Performance

**Cache Tags por Tenant:**
- Redis keys prefixados: `tenant:burda:products`
- Flush cache de um tenant específico sem afetar outros

**Session Store:**
- Redis (performance)
- Database (fallback se Redis cair)

**Query Cache:**
- Produtos, categorias raramente mudam → cache longo
- Vendas, planogramas ativos → cache curto ou sem cache

---

## 🐳 Estratégia Docker Compose

### Estrutura de Arquivos

```
project/
├── docker-compose.yml              → Local (development)
├── docker-compose.staging.yml      → Staging  
├── docker-compose.production.yml   → Production
├── docker/
│   ├── nginx/
│   │   ├── nginx.conf
│   │   └── ssl/                    → Cloudflare Origin Certificates
│   ├── traefik/
│   │   ├── traefik.yml            → Static configuration
│   │   └── dynamic/               → Dynamic config (SSL, middlewares)
│   ├── php/
│   │   └── Dockerfile
│   └── scripts/
│       ├── backup.sh              → Postgres backup automatizado
│       └── deploy.sh              → Helper para deploy
├── .env.local
├── .env.staging
└── .env.production
```

---

### Serviços Principais

#### 1. Traefik (Reverse Proxy)

**Responsabilidades:**
- Routing multi-tenant por subdomínio
- SSL termination (Cloudflare Origin Certificate)
- Health checks
- Rate limiting
- Headers de segurança
- Logs estruturados

**Portas expostas:**
- 80 (HTTP → redirect 443)
- 443 (HTTPS)
- 8080 (WebSockets/Reverb)
- 8081 (Dashboard Traefik - opcional, protegido)

**Volumes:**
- Socket Docker (routing dinâmico)
- Certificados SSL Cloudflare
- Configurações estáticas/dinâmicas
- Logs

**Labels Docker:**
- Define routing rules
- Middlewares (segurança, rate limit)
- Service discovery automático

---

#### 2. PostgreSQL

**Versão:** 16 (Alpine para imagem menor)

**Configuração otimizada para 16GB RAM:**
- `max_connections`: 200
- `shared_buffers`: 2GB (25% da RAM disponível para Postgres)
- `effective_cache_size`: 6GB
- `work_mem`: 10MB
- `maintenance_work_mem`: 512MB
- `checkpoint_completion_target`: 0.9

**Volumes persistentes:**
- Data: `/var/lib/postgresql/data`
- Backups: `/backups` (montado do host)

**Health Check:**
- Comando: `pg_isready -U ${DB_USERNAME}`
- Interval: 10s
- Timeout: 5s
- Retries: 5

**Bancos separados por ambiente:**
- Local: `plannerate_local`
- Staging: `plannerate_staging`
- Production: `plannerate_production`

**Backup Strategy:**
- Staging: Diário, retenção 7 dias
- Production: A cada 6 horas, retenção 30 dias
- Teste de restore: Semanal (automatizado)

---

#### 3. Redis

**Versão:** 7 (Alpine)

**Uso:**
- Cache de aplicação
- Sessions
- Queue backend
- Reverb pub/sub

**Configuração:**
- Persistência: AOF (Append Only File)
- Max Memory: 2GB
- Eviction Policy: `allkeys-lru`

**Health Check:**
- Comando: `redis-cli ping`
- Interval: 10s

**Volumes persistentes:**
- Data: `/data`

---

#### 4. Laravel App (Nginx + PHP-FPM)

**Imagem:** Build customizada (Dockerfile multi-stage)
- Base: `php:8.3-fpm-alpine`
- Extensões: pdo_pgsql, redis, gd, zip, opcache
- Composer dependencies
- Node/Vite assets compilados

**Volumes:**
- `storage/` → Persistente (logs, uploads, cache)
- `bootstrap/cache/` → Persistente (route/config cache)

**Environment Variables:**
- Injetadas via `.env` do ambiente específico
- Secrets via GitHub Secrets (produção)

**Health Check:**
- Endpoint: `GET /health` (retorna 200 OK)
- Interval: 30s
- Start period: 40s (primeiro boot demora mais)

**Depends On:**
- Postgres (healthy)
- Redis (healthy)

**Labels Traefik:**
- Routing: `HostRegexp({subdomain:[a-z0-9-]+}.plannerate.com.br)`
- TLS: Certificado Cloudflare
- Middlewares: Security headers, rate limit

**Replicas:**
- Local: 1
- Staging: 1
- Production: 2-3 (load balancing)

---

#### 5. Laravel Reverb (WebSockets)

**Comando:** `php artisan reverb:start --host=0.0.0.0 --port=8080`

**Mesma imagem do app** (reutiliza build)

**Configuração específica:**
- Host: `0.0.0.0` (escuta todas interfaces)
- Port: 8080
- Scheme: `https` (produção/staging)

**Depends On:**
- Redis (pub/sub backend)
- App (inicializa depois da app principal)

**Labels Traefik:**
- Routing: `Host(ws.plannerate.com.br)`
- Porta exposta: 8080
- Middlewares: Headers WebSocket (Upgrade, Connection)

**Health Check:**
- Comando: `nc -z localhost 8080` (netcat verifica porta aberta)
- Interval: 30s

**Observação:** Este serviço recebe tráfego direto (DNS Only), mas Traefik ainda gerencia roteamento interno.

---

#### 6. Queue Workers

**Filas separadas por prioridade:**

**a) Queue High (alta prioridade)**
- Filas: `high`
- Sleep: 1s (verifica fila rapidamente)
- Tries: 3
- Timeout: 300s (5min)
- Max Time: 1800s (30min de vida do worker)
- Replicas: 2

**Uso:** Notificações críticas, processamento imediato

**b) Queue Default (prioridade média)**
- Filas: `default`
- Sleep: 3s
- Tries: 3
- Timeout: 600s (10min)
- Max Time: 3600s (1h)
- Replicas: 3-4

**Uso:** Processamento padrão (sync de dados, emails)

**c) Queue Low (baixa prioridade)**
- Filas: `low`
- Sleep: 5s
- Tries: 3
- Timeout: 300s (5min por job)
- Max Time: 7200s (2h de vida do worker)
- Memory Limit: 512MB
- Replicas: 1

**Uso:** Importações massivas (milhares de produtos/vendas)

**Strategy para Setup Inicial:**
- Jobs em chunks de 500-1000 registros
- Progress tracking no Redis
- Notificação via Reverb ao completar
- Retry com exponential backoff

**Volumes compartilhados:**
- `storage/` para acesso a arquivos

---

#### 7. Laravel Scheduler

**Comando:** Loop infinito executando `php artisan schedule:run` a cada 60s

**Tarefas agendadas (configuradas no Kernel):**
- Sincronização diária de produtos
- Backup incremental
- Limpeza de cache/sessions antigas
- Relatórios agendados
- Verificação de saúde do sistema

**Depends On:** App, Postgres, Redis

**Volumes:** Mesmo do app

---

#### 8. Backup Service

**Imagem:** `postgres:16-alpine` (reusa para pg_dump)

**Script:** `docker/scripts/backup.sh`

**Funcionalidade:**
- Dump completo do banco
- Compressão (gzip)
- Nomenclatura: `plannerate_YYYY-MM-DD_HH-MM-SS.sql.gz`
- Upload para storage externo (opcional: S3, Backblaze)
- Rotação automática (delete backups > 30 dias)

**Execução:**
- Staging: Diário (00:00)
- Production: A cada 6 horas

**Volumes:**
- `/backups` → Diretório no host

---

### Networks

**Bridge Network:** `plannerate_network`
- Todos containers na mesma network
- Comunicação interna por nome do serviço
- Isolamento de outros projetos Docker

---

### Volumes

**Nomeados (gerenciados pelo Docker):**
- `postgres_data` → Persistência do banco
- `redis_data` → Persistência do Redis
- `letsencrypt` → Certificados (fallback, Cloudflare é primário)

**Bind Mounts (host → container):**
- `./storage` → Uploads, logs do Laravel
- `./bootstrap/cache` → Cache de rotas/config
- `./backups` → Backups do Postgres
- `./docker/nginx/ssl` → Certificados Cloudflare

---

### Environment-Specific Overrides

**docker-compose.yml (Local):**
- Sem Traefik (Nginx simples ou `php artisan serve`)
- Portas expostas direto (8000, 5432, 6379)
- Build local do código (não pull de GHCR)
- Hot reload (volumes montando código fonte)
- Debug habilitado

**docker-compose.staging.yml:**
- Extends base compose
- Usa imagens GHCR (`tag: staging`)
- Traefik com SSL Cloudflare
- Debug logs habilitados
- Menos replicas (economia de recursos)

**docker-compose.production.yml:**
- Imagens GHCR (`tag: latest`)
- Traefik otimizado
- Debug OFF
- Logs estruturados (JSON)
- Health checks rigorosos
- Mais replicas (redundância)
- Resource limits (evitar um serviço consumir tudo)

---

## 🚀 CI/CD Pipeline (GitHub Actions)

### Fluxo de Deploy

```
Developer → git push origin develop
                ↓
    GitHub Actions (Staging)
                ↓
    ┌─────────────────────────┐
    │ 1. Checkout código      │
    │ 2. Setup Node + Composer│
    │ 3. Run tests (PHPUnit)  │
    │ 4. Build Vite assets    │
    │ 5. Build Docker image   │
    │ 6. Push GHCR (staging)  │
    └─────────────────────────┘
                ↓
    SSH para VPS → Diretório staging
                ↓
    ┌─────────────────────────┐
    │ 1. Pull nova imagem     │
    │ 2. docker compose down  │
    │ 3. docker compose pull  │
    │ 4. docker compose up -d │
    │ 5. Migrations           │
    │ 6. Health check         │
    │ 7. Purge Cloudflare     │
    └─────────────────────────┘
                ↓
    Notificação Slack/Discord (sucesso/falha)
```

**Produção:** Mesmo fluxo, mas trigger em `main` branch.

---

### Workflows

#### 1. Deploy Staging (`.github/workflows/deploy-staging.yml`)

**Trigger:**
- Push para branch `develop`
- Workflow dispatch manual

**Steps:**
1. Checkout código
2. Setup ambiente (PHP, Node, Composer)
3. Install dependencies
4. Run tests (PHPUnit, Pest)
5. Build frontend (Vite)
6. Build Docker image
7. Tag: `ghcr.io/usuario/plannerate-app:staging`
8. Push para GHCR
9. SSH na VPS
10. Deploy staging environment
11. Run migrations
12. Health check
13. Purge cache Cloudflare (via API)
14. Notificação

**Secrets necessários:**
- `GHCR_TOKEN` → GitHub Container Registry
- `VPS_SSH_KEY` → Chave privada SSH
- `VPS_HOST` → IP da VPS
- `CLOUDFLARE_API_TOKEN` → Para purge cache
- `SLACK_WEBHOOK` → Notificações

---

#### 2. Deploy Production (`.github/workflows/deploy-production.yml`)

**Trigger:**
- Push/merge para branch `main`
- Workflow dispatch manual (com confirmação)

**Diferenças do staging:**
- Tag: `latest` + SHA commit (ex: `latest`, `sha-abc123`)
- Approval step (manual ou automático)
- Zero downtime deploy:
  - Inicia novos containers
  - Health check
  - Troca tráfego (Traefik)
  - Derruba containers antigos
- Rollback automático se health check falhar
- Notificações mais detalhadas

---

#### 3. Tests (`.github/workflows/tests.yml`)

**Trigger:**
- Pull Request para `develop` ou `main`
- Push em qualquer branch

**Steps:**
1. Checkout
2. Setup ambiente
3. Install dependencies
4. Run PHPUnit
5. Run Pest (se aplicável)
6. Run PHP CS Fixer (code style)
7. Run PHPStan/Larastan (static analysis)
8. Frontend tests (Vitest, se aplicável)
9. Report coverage

**Não faz deploy** - apenas valida código

---

#### 4. Build e Cache (`.github/workflows/build.yml`)

**Trigger:**
- Nightly (diariamente)
- Workflow dispatch manual

**Objetivo:** Rebuild imagens base, atualizar dependencies, cache

**Steps:**
1. Build imagem base (sem código da app)
2. Cache Composer dependencies
3. Cache Node dependencies
4. Push imagem base para GHCR

**Benefício:** Deploys subsequentes são mais rápidos (reusa camadas cacheadas)

---

### GitHub Container Registry (GHCR)

**Imagens publicadas:**
- `ghcr.io/seu-usuario/plannerate-app:latest` → Produção
- `ghcr.io/seu-usuario/plannerate-app:staging` → Staging
- `ghcr.io/seu-usuario/plannerate-app:sha-abc123` → Specific commit (rollback)
- `ghcr.io/seu-usuario/plannerate-app:base` → Base image (cached)

**Visibilidade:** Private (requer autenticação)

**Autenticação na VPS:**
- Token do GitHub com permissão `read:packages`
- Login: `echo $GHCR_TOKEN | docker login ghcr.io -u USERNAME --password-stdin`

---

### Deploy Strategy

**Staging (Fast):**
- Downtime aceitável (~10s)
- `docker compose down && docker compose up -d`
- Simples e rápido

**Production (Zero Downtime):**
- Rolling update:
  1. Iniciar novos containers
  2. Health check nos novos
  3. Adicionar ao load balancer (Traefik)
  4. Remover antigos do load balancer
  5. Graceful shutdown dos antigos (aguarda requests finalizarem)
  6. Remover containers antigos

**Rollback:**
- Manter tag `stable` sempre apontando para última versão funcional
- Se deploy falhar: `docker compose pull plannerate-app:stable && docker compose up -d`
- Automático se health check falhar após deploy

---

### Health Checks

**Durante Deploy:**
1. Aguardar containers subirem (30s)
2. Check endpoint: `curl https://plannerate.com.br/health`
3. Validar response: status 200, payload esperado
4. Check Reverb: WebSocket connection
5. Check Queue: Redis queue size
6. Check Database: Connection pool

**Se qualquer check falhar:**
- Abort deploy
- Rollback para versão anterior
- Notificação urgente

---

### Post-Deploy Tasks

**Automáticos:**
1. Run migrations: `php artisan migrate --force`
2. Clear caches: `php artisan optimize:clear && php artisan optimize`
3. Restart queues: `php artisan queue:restart`
4. Purge Cloudflare cache (seletivo: `/build/*`)
5. Warm up cache (opcional): Visitar páginas principais

**Manuais (quando necessário):**
- Seed data: `php artisan db:seed`
- Reindex search: `php artisan scout:import`
- Generate sitemaps: `php artisan sitemap:generate`

---

## 📊 Monitoramento e Observabilidade

### Logs

**Centralização:**
- Todos containers → stdout/stderr
- Docker log driver → arquivo ou serviço externo
- Formato: JSON estruturado

**Níveis:**
- **ERROR:** Erros críticos (quebra funcionalidade)
- **WARNING:** Problemas não críticos (degradação)
- **INFO:** Eventos importantes (deploy, migration)
- **DEBUG:** Detalhes técnicos (apenas staging/local)

**Contexto obrigatório:**
- Timestamp (ISO 8601)
- Tenant ID
- User ID (se aplicável)
- IP real (Cloudflare headers)
- Request ID (`CF-RAY`)
- Environment (local/staging/production)

**Retenção:**
- Local: 7 dias
- Staging: 30 dias
- Production: 90 dias

**Ferramentas (futuro):**
- Grafana Loki (query logs)
- Elasticsearch + Kibana
- Papertrail/Logtail (SaaS)

---

### Métricas

**Essenciais (monitorar desde o início):**

**Infraestrutura:**
- CPU usage por container
- RAM usage por container
- Disk usage (alerta > 80%)
- Network I/O

**Aplicação:**
- Request latency (p50, p95, p99)
- Throughput (requests/min)
- Error rate (5xx responses)
- Queue size (por fila)
- Queue processing time
- Active WebSocket connections

**Database:**
- Connection pool usage
- Query time (slow queries > 1s)
- Deadlocks
- Cache hit rate

**Redis:**
- Memory usage
- Evicted keys
- Commands/sec

**Coleta:**
- Prometheus exporter em cada serviço
- Scrape interval: 15s
- Retenção: 30 dias

**Visualização:**
- Grafana dashboards
- Alertas via Prometheus Alertmanager

---

### Alertas

**Críticos (notificação imediata - Slack/PagerDuty):**
- Site down (health check failed)
- Disk > 90%
- Database connection errors
- Error rate > 5% (últimos 5min)
- Queue backlog > 10.000 jobs

**Warnings (notificação em horário comercial):**
- CPU > 80% (sustentado 10min)
- RAM > 85%
- Slow queries > 100/min
- Failed jobs > 10% (última hora)

**Info (log apenas, revisar periodicamente):**
- Deploy succeeded
- Backup completed
- Tenant created/deleted

---

### Uptime Monitoring

**Externo (fora da VPS):**
- Serviço: UptimeRobot, Pingdom, ou Betteruptime
- Endpoints monitorados:
  - `https://plannerate.com.br/health` (a cada 1min)
  - `https://burda.plannerate.com.br` (tenant exemplo)
  - `wss://ws.plannerate.com.br` (WebSocket)

**Notificações:**
- Email/SMS se down > 2min
- Slack/Discord para avisos

---

### Performance Monitoring (APM)

**Futuro (quando escalar):**
- New Relic, DataDog, ou Sentry Performance
- Distributed tracing (OpenTelemetry)
- Real User Monitoring (RUM)
- Identificar gargalos (N+1 queries, slow API calls)

---

## 🔐 Segurança

### Secrets Management

**Ambientes:**

**Local:**
- `.env.local` commitado (sem secrets reais)
- Secrets de teste

**Staging:**
- `.env.staging` na VPS (não commitado)
- Secrets menos sensíveis (pode resetar fácil)

**Production:**
- `.env.production` na VPS (permissões 600)
- GitHub Secrets (CI/CD)
- Secrets rotacionados regularmente

**Nunca:**
- Commitar .env com secrets reais
- Logar secrets (passwords, tokens)
- Enviar secrets via Slack/email

---

### SSL/TLS

**Cloudflare → Cliente:**
- Certificado gerenciado pelo Cloudflare
- Wildcard automático
- Auto-renova

**Cloudflare → VPS:**
- Origin Certificate (15 anos)
- Full (Strict) mode
- Mutual TLS (opcional, futuro)

**Dentro da VPS:**
- Traefik → App: pode ser HTTP (rede Docker isolada)
- Reverb: HTTPS (certificado Cloudflare)

---

### Firewall (VPS)

**UFW (Uncomplicated Firewall):**

**Portas abertas:**
- 22 (SSH) - apenas IPs específicos (seu IP + GitHub Actions runners)
- 80 (HTTP) - Cloudflare IPs apenas
- 443 (HTTPS) - Cloudflare IPs apenas
- 8080 (Reverb WebSocket) - Público (mas rate-limited)

**Bloqueadas:**
- Tudo que não está explicitamente permitido
- Brute force SSH (fail2ban)

**Cloudflare IPs:**
- Whitelist completa em `/etc/ufw/applications.d/cloudflare`
- Update automático mensal

---

### Database Security

**Credenciais:**
- Password forte (32+ caracteres alfanuméricos)
- User específico por ambiente
- Sem usuário `postgres` com acesso externo

**Network:**
- Postgres escuta apenas rede Docker interna
- Não expor porta 5432 publicamente

**Backup Encryption:**
- Backups encriptados (GPG)
- Chave privada em local seguro (fora da VPS)

---

### Rate Limiting

**Camadas:**

**Cloudflare (primeira linha):**
- 100 req/min por IP (global)
- 10 req/10min para `/login` (brute force)

**Traefik (segunda linha):**
- 50 req/min por IP (bypass se Cloudflare falhar)
- Diferentes limites por rota

**Laravel (aplicação):**
- Throttle middleware: 60 req/min por user autenticado
- API: 1000 req/hour por tenant

---

### Updates e Patches

**Estratégia:**
- Atualizar dependências mensalmente (composer, npm)
- Security patches imediatos (Laravel, PHP)
- SO (Ubuntu): `unattended-upgrades` para security patches

**Testing:**
- Staging recebe updates primeiro
- Mínimo 48h em staging antes de prod
- Rollback plan sempre pronto

---

## 📦 Recursos da VPS (16GB RAM)

### Distribuição de Memória (Aproximada)

| Serviço | RAM Alocada | Justificativa |
|---------|-------------|---------------|
| PostgreSQL | 4-5 GB | Shared buffers (2GB) + connections + cache |
| Redis | 2 GB | Cache + sessions + queues |
| Laravel App (2 replicas) | 2 GB | 1GB por replica (PHP-FPM pools) |
| Reverb | 1 GB | WebSocket connections (1000-2000 concurrent) |
| Queue Workers (6 total) | 3 GB | 500MB cada |
| Traefik | 512 MB | Proxy + logs |
| Sistema/Overhead | 2-3 GB | Ubuntu, Docker daemon, buffers |
| **TOTAL** | **~15 GB** | Margem apertada - monitorar! |

**Atenção:** Se atingir limite de RAM:
1. Swap (4GB) ativa - performance degrada
2. OOM Killer pode matar processos
3. Reduzir replicas ou otimizar queries

---

### Distribuição de Disco (200GB)

| Conteúdo | Espaço | Crescimento |
|----------|--------|-------------|
| Sistema (Ubuntu) | 10 GB | Lento |
| Docker images | 5-10 GB | Lento (pull updates) |
| PostgreSQL data | 20-100 GB | **Rápido** (vendas diárias) |
| Backups | 20-50 GB | Médio (rotação automática) |
| Logs | 5-10 GB | Médio (rotação semanal) |
| Storage Laravel | 10-30 GB | Médio (uploads, cache) |
| **Livre** | **>50 GB** | Buffer de segurança |

**Monitoramento crítico:**
- Alerta em 80% (160GB usados)
- Limpeza automática de logs antigos
- Compressão de backups
- Migração para storage externo (S3) se necessário

---

## 🔄 Operações do Dia-a-Dia

### Deploy Manual (Emergency)

**Staging:**
```bash
# SSH na VPS
ssh user@148.230.78.184

# Navegar para staging
cd /var/www/plannerate-staging

# Pull última imagem
docker compose -f docker-compose.staging.yml pull

# Restart serviços
docker compose -f docker-compose.staging.yml up -d

# Check health
curl https://plannerate.dev.br/health
```

**Production:**
Mesmo processo, mas com confirmação dupla e backup antes.

---

### Rollback

**Via Git (re-deploy versão anterior):**
- GitHub Actions → Re-run workflow de commit específico

**Via Docker (troca de tag):**
```bash
# Setar tag estável
docker tag ghcr.io/.../plannerate-app:sha-abc123 ghcr.io/.../plannerate-app:stable

# Pull e restart
docker compose pull
docker compose up -d
```

---

### Adicionar Novo Tenant

**Cloudflare:**
- DNS já configurado (wildcard)
- Nada a fazer!

**Laravel:**
1. Criar registro na tabela `tenants`
2. Migrar schema do tenant: `php artisan tenants:migrate --tenant=burda`
3. Seed data inicial (se necessário)
4. Notificar cliente: credenciais de acesso

---

### Backup e Restore

**Backup manual:**
```bash
# Dentro da VPS
docker exec plannerate_postgres pg_dump -U user -d plannerate_production > backup.sql
gzip backup.sql
```

**Restore:**
```bash
# Descompactar
gunzip backup.sql.gz

# Restore (CUIDADO!)
docker exec -i plannerate_postgres psql -U user -d plannerate_production < backup.sql
```

---

### Ver Logs em Tempo Real

**App:**
```bash
docker logs -f plannerate_app
```

**Postgres:**
```bash
docker logs -f plannerate_postgres
```

**Queues:**
```bash
docker logs -f plannerate_queue_default
```

**Traefik (access logs):**
```bash
docker exec plannerate_traefik tail -f /var/log/traefik/access.log
```

---

### Purge Cache Cloudflare

**Via Dashboard:**
- Caching → Purge Everything (ou seletivo)

**Via API (no deploy):**
```bash
curl -X POST "https://api.cloudflare.com/client/v4/zones/ZONE_ID/purge_cache" \
  -H "Authorization: Bearer $CF_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{"files":["https://plannerate.com.br/build/app.js"]}'
```

---

## 🎯 Próximos Passos

### Documentação a Criar

1. ✅ Arquitetura geral (este documento)
2. ⏳ Arquivos de configuração:
   - docker-compose (local/staging/production)
   - Traefik (static + dynamic config)
   - Nginx
   - GitHub Actions workflows
   - Scripts (backup, deploy, helpers)
3. ⏳ Guias operacionais:
   - Runbook de deploy
   - Troubleshooting comum
   - Disaster recovery
   - Onboarding de novos desenvolvedores

### Melhorias Futuras

**Curto prazo (1-3 meses):**
- Monitoramento básico (Prometheus + Grafana)
- Alertas críticos (Slack/Discord)
- CI/CD 100% automatizado
- Testes automatizados (coverage > 70%)

**Médio prazo (3-6 meses):**
- APM (Application Performance Monitoring)
- Distributed tracing
- Horizontal scaling (se necessário)
- CDN para uploads (Cloudflare R2 ou S3)

**Longo prazo (6-12 meses):**
- Multi-region (se clientes fora do BR)
- Kubernetes (se escalar para muitos servidores)
- Auto-scaling baseado em métricas
- Disaster recovery completo (multi-datacenter)

---

## 📞 Suporte e Troubleshooting

### Cloudflare Issues

**Site lento:**
- Check cache hit rate (Dashboard Analytics)
- Verificar Page Rules (cache configurado corretamente?)
- Development Mode ON? (cache desabilitado)

**SSL errors:**
- Origin Certificate expirado?
- Modo Full (Strict) configurado?
- Certificado na VPS corresponde ao domínio?

**WebSocket não conecta:**
- `ws.plannerate.com.br` está DNS Only? (não Proxied)
- Porta 8080 aberta na VPS?
- Reverb rodando? (`docker ps`)

---

### Database Issues

**Conexões esgotadas:**
- Check `max_connections` no Postgres
- Verificar connection leaks no Laravel
- Aumentar pool size ou reduzir timeout

**Queries lentas:**
- Enable slow query log
- Identificar queries sem índices
- Otimizar N+1 queries

**Disk full:**
- Purgar logs antigos
- Vacuum do Postgres
- Mover backups para storage externo

---

### Queue Issues

**Jobs não processam:**
- Workers rodando? (`docker ps | grep queue`)
- Redis conectado?
- Fila correta? (high/default/low)

**Jobs falhando:**
- Check logs: `docker logs plannerate_queue_default`
- Verificar timeout (job demora mais que limite?)
- Retry limit atingido?

---

**Pronto!** Essa é a documentação completa da arquitetura. Quando você quiser, podemos partir para criação dos arquivos específicos (docker-compose, Traefik configs, GitHub Actions, etc). Me avisa qual parte você quer implementar primeiro! 🚀