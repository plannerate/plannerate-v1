# Verificação do build Reverb (WebSocket)

Use este checklist para garantir que o frontend está usando a URL correta do Reverb.

---

## 1. URL esperada no navegador

O WebSocket deve conectar em:

```
wss://proplanner.plannerate.dev.br/app/KEY
```

- **Sem** porta (`:8080` ou `:443` no final da URL = errado).
- **Host** = `proplanner.plannerate.dev.br` (mesmo do app).
- **Path** = `/app/` + chave do app.

---

## 2. Configuração no servidor

| Arquivo | Variável | Valor correto |
|---------|----------|---------------|
| `.env` | VITE_REVERB_HOST | proplanner.plannerate.dev.br |
| `.env` | VITE_REVERB_PORT | 443 |
| `.env` | VITE_REVERB_SCHEME | wss |
| `.env` | REVERB_HOST | proplanner.plannerate.dev.br |
| `.env` | REVERB_SCHEME | wss |
| `docker-compose.staging.yml` | default VITE_REVERB_HOST (app) | proplanner.${DOMAIN} |
| `.github/workflows/deploy-staging.yml` (build-args) | VITE_REVERB_HOST | proplanner.plannerate.dev.br |
| `.github/workflows/deploy-staging.yml` (build-args) | VITE_REVERB_SCHEME | wss |
| `.env.staging.example` | VITE_REVERB_* / REVERB_* | conforme tabela acima |
| `config/reverb.php` → `vite` | Valores lidos de env e expostos via config | Usados em `app.blade.php` com `config()` (funciona após `config:cache`) |
| `resources/views/app.blade.php` | Injeta `window.VITE_REVERB_*` e `window.VITE_PUSHER_*` | Fallback runtime; Echo usa `import.meta.env` primeiro, depois `window` |

---

## 3. Como verificar se o build está certo

O frontend (Vite) **embute** as variáveis `VITE_*` no momento do **build**. O que está no `.env` do servidor só vale para o container; o JS que o navegador baixa foi gerado na **imagem Docker** (GitHub Actions).

### Opção A: No navegador (DevTools)

1. Abra a aplicação: `https://proplanner.plannerate.dev.br`
2. Abra o DevTools (F12) → aba **Console**
3. Digite e execute:
   ```js
   console.log('REVERB_HOST', import.meta.env?.VITE_REVERB_HOST);
   console.log('REVERB_PORT', import.meta.env?.VITE_REVERB_PORT);
   console.log('REVERB_SCHEME', import.meta.env?.VITE_REVERB_SCHEME);
   ```
4. Ou na aba **Network** → filtro **WS** → ao conectar, clique na requisição WebSocket e veja a **URL**:
   - **Certo:** `wss://proplanner.plannerate.dev.br/app/...`
   - **Errado:** `wss://reverb.plannerate.dev.br/...` ou `...:8080/...`

### Opção B: Conferir a imagem que está rodando

No servidor de staging:

```bash
cd /opt/plannerate/staging

# Ver data da imagem atual do app
docker inspect plannerate-app-staging --format '{{.Created}}'

# Variáveis de ambiente do container (runtime; não alteram o JS já buildado)
docker exec plannerate-app-staging env | grep VITE_REVERB
```

Se `VITE_REVERB_HOST` no container estiver `proplanner.plannerate.dev.br`, o **runtime** está ok. O JS só muda quando uma **nova imagem** for buildada com o workflow atual (build-args com `proplanner.plannerate.dev.br` e `wss`).

### Opção C: Garantir que o último build usou o deploy correto

- O `.github/workflows/deploy-staging.yml` no repositório deve ter:
  - `VITE_REVERB_HOST=proplanner.plannerate.dev.br`
  - `VITE_REVERB_SCHEME=wss`
- Após push na branch `dev`, o GitHub Actions gera a imagem com esses build-args.
- Depois do deploy: `docker compose -f docker-compose.staging.yml pull` e `up -d` (o script de deploy já faz isso).

---

## 4. Resumo: o que faz o build “certo”

1. **Build da imagem** (GitHub Actions) usa os **build-args** do `deploy-staging.yml`:
   - `VITE_REVERB_HOST=proplanner.plannerate.dev.br`
   - `VITE_REVERB_PORT=443`
   - `VITE_REVERB_SCHEME=wss`
2. O **Dockerfile.prod** do app passa esses build-args para o `npm run build` (`ARG VITE_REVERB_*` e uso no `npm run build`).
3. Depois do deploy, o navegador recebe JS que monta a URL: `wss://proplanner.plannerate.dev.br/app/KEY`.

---

## 5. Reverb no mesmo container (Supervisor + Nginx)

- **Reverb** roda dentro do container do app via **Supervisor** (porta 8080 interna).
- **Mesma origem:** não há container separado nem roteamento de path no Traefik.
- O **Traefik** envia todo o tráfego do host do app (ex.: proplanner.plannerate.dev.br) para o container na **porta 80**.
- O **Nginx** dentro do container faz proxy de **`/app`** para `http://127.0.0.1:8080` (Reverb), com suporte a WebSocket (Upgrade, Connection, X-Forwarded-Proto).
- **Staging usa o Dockerfile padrão** (não o Dockerfile.prod): o workflow de deploy não especifica `file:`, então a imagem é buildada com o **Dockerfile** na raiz. O proxy `/app` e os maps de WebSocket precisam estar nesse Dockerfile.
- URL final: `wss://proplanner.plannerate.dev.br/app/KEY` (porta 443 no Traefik; sem porta na URL para o usuário).

---

## 6. app.blade.php (runtime)

A view injeta variáveis no `window` como **fallback** para o frontend (Echo usa `import.meta.env` primeiro, depois `window`). Os valores vêm de **config** (`config('reverb.vite')`), não de `env()` direto, para funcionar após `php artisan config:cache`. Assim, não há conflito com o build: o build define a URL na imagem; o Blade só entra se o JS precisar de fallback (ex.: dev ou mesma imagem em vários ambientes).

Se algo ainda falhar, confira no navegador a URL exata da requisição WebSocket (aba Network → WS) e compare com a tabela e o resumo acima.
