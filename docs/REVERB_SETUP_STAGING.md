# Reverb (WebSocket) em Staging – O que foi feito

Documentação do que foi implementado para o Reverb rodar **dentro do container do app** (Supervisor), em **mesma origem** (`wss://proplanner.plannerate.dev.br/app/KEY`), sem container separado e sem roteamento de path no Traefik.

---

## 1. Arquitetura

| Antes | Depois |
|-------|--------|
| Container `app` (porta 80) + container `reverb` (porta 8080) | Apenas container `app` |
| Traefik: router para `reverb.${DOMAIN}` e path `/app` | Traefik: só app na porta 80 |
| WebSocket em subdomínio ou path via Traefik | WebSocket em **mesma origem** (`/app` via Nginx dentro do app) |

- **Reverb** roda dentro do container do app via **Supervisor** (porta 8080 interna).
- **Nginx** no mesmo container faz proxy de **`/app`** para `http://127.0.0.1:8080` (Reverb), com suporte a WebSocket.
- **Traefik** envia todo o tráfego do host do app para o container na **porta 80**; o Nginx trata `/app` e encaminha para o Reverb.

---

## 2. O que foi implementado

### 2.1. Dockerfile (e Dockerfile.prod)

- **Arquivo de map do Nginx** (`/etc/nginx/http.d/00-reverb-map.conf`):
  - `$connection_upgrade`: WebSocket (Upgrade/Connection).
  - `$forwarded_proto`: repassa `X-Forwarded-Proto` do Traefik; fallback `$scheme` (ex.: dev local).

- **`location /app`** no Nginx:
  - `proxy_pass http://127.0.0.1:8080`
  - Headers WebSocket: `Upgrade`, `Connection` (`$connection_upgrade`)
  - `X-Forwarded-Proto $forwarded_proto`
  - `proxy_buffering off`
  - `proxy_read_timeout` e `proxy_send_timeout` 86400

**Importante:** o deploy de staging usa o **Dockerfile** na raiz (não o Dockerfile.prod). O proxy `/app` e os maps estão nos dois arquivos para manter consistência.

### 2.2. docker-compose.staging.yml

- **Removido** o serviço **reverb** (container separado).
- **Removidas** todas as labels do Traefik relacionadas ao Reverb (`staging-reverb`, `staging-reverb-path`).
- O app segue com `image: ghcr.io/${GITHUB_REPO}:dev` e env `VITE_REVERB_HOST` default `proplanner.${DOMAIN}`.

### 2.3. deploy-staging.yml (build-args)

- `VITE_REVERB_HOST=proplanner.plannerate.dev.br`
- `VITE_REVERB_SCHEME=wss`
- `VITE_PUSHER_HOST` e `VITE_PUSHER_SCHEME` iguais (Echo usa VITE_PUSHER_*).

### 2.4. config/reverb.php e app.blade.php

- Chave **`config('reverb.vite')`** para expor host/port/scheme (funciona após `config:cache`).
- **app.blade.php** injeta `window.VITE_REVERB_*` e `window.VITE_PUSHER_*` a partir de `config('reverb.vite')` (fallback runtime).

---

## 3. Como verificar se está ok

### 3.1. Supervisor e Reverb dentro do container

```bash
docker exec plannerate-app-staging supervisorctl status
```

Saída esperada (exemplo):

```
nginx                            RUNNING   pid 12, uptime 0:00:00
php-fpm                          RUNNING   pid 11, uptime 0:00:00
reverb                           RUNNING   pid 13, uptime 0:00:00
```

Só o Reverb:

```bash
docker exec plannerate-app-staging supervisorctl status reverb
```

### 3.2. WebSocket no navegador

1. Abra `https://proplanner.plannerate.dev.br`
2. DevTools (F12) → aba **Network** → filtro **WS**
3. Confira a URL da conexão WebSocket: deve ser `wss://proplanner.plannerate.dev.br/app/...` (sem porta, mesmo host do app).

### 3.3. Checklist completo

Para variáveis, build-args e demais pontos de configuração, use o checklist em **[REVERB_WEBSOCKET_VERIFICACAO.md](./REVERB_WEBSOCKET_VERIFICACAO.md)**.

---

## 4. Resumo

| Item | Status |
|------|--------|
| Reverb no mesmo container (Supervisor) | ✅ |
| Nginx proxy `/app` → Reverb 8080 | ✅ |
| Mesma origem (`wss://HOST/app/KEY`) | ✅ |
| Sem container reverb no compose | ✅ |
| Sem roteamento path no Traefik | ✅ |
| Build staging usa Dockerfile (raiz) | ✅ |
| Verificação: `supervisorctl status` | ✅ |
