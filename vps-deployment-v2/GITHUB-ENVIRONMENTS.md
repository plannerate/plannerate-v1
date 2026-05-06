# GitHub Environments — Referência de Variáveis e Secrets

> O script `setup.sh` configura o environment `staging` automaticamente via GitHub CLI.
> Este documento é uma referência do que foi provisionado e o que precisa ser feito para production.

---

## O que o `setup.sh` cria automaticamente

Ao rodar `./setup.sh`, o script usa `gh` (GitHub CLI) para criar:

### Repository Variables (nível repositório — todos os workflows)

| Nome | Valor configurado | Workflow que usa |
|------|------------------|------------------|
| `DOMAIN_LANDLORD` | domínio do landlord (ex: `plannerate.dev.br`) | `vps-v2-build-push` (baked na imagem Docker) |
| `GHCR_REPO` | `plannerate/plannerate-v1` | `vps-v2-build-push` (push da imagem) |

### Environment `staging` — Secrets

| Nome | Origem | Descrição |
|------|--------|-----------|
| `APP_HOST` | IP do VPS informado no setup | Endereço do servidor |
| `APP_USER` | usuário SSH informado no setup | Usuário de deploy |
| `SSH_PRIVATE_KEY` | chave gerada/informada no setup | Acesso SSH ao VPS |
| `SSH_KNOWN_HOSTS` | fingerprint escaneado no setup | Previne TOFU no SSH |
| `DOMAIN` | domínio landlord | Domínio do ambiente (não usado nos workflows atuais) |

### Environment `staging` — Variables

| Nome | Valor | Descrição |
|------|-------|-----------|
| `DEPLOY_PATH` | `/opt/plannerate/staging` | Diretório no VPS |
| `COMPOSE_FILE` | `docker-compose.staging.yml` | Arquivo compose do ambiente |

---

## O que falta: environment `production`

O `setup.sh` provisiona **apenas staging**. O environment `production` no GitHub precisa ser criado separadamente.

Como o projeto usa o mesmo VPS para ambos os ambientes (separados por diretório), os secrets são idênticos — só muda o `APP_SLUG` e o compose file.

### Crie o environment `production` via GitHub CLI:

```bash
REPO="plannerate/plannerate-v1"
VPS_HOST="148.230.78.184"
VPS_USER="root"

# Cria o environment
gh api --method PUT "repos/${REPO}/environments/production"

# Mesmos secrets do staging (mesmo VPS)
gh secret set APP_HOST        --repo "${REPO}" --env production --body "${VPS_HOST}"
gh secret set APP_USER        --repo "${REPO}" --env production --body "${VPS_USER}"
gh secret set SSH_PRIVATE_KEY --repo "${REPO}" --env production < ~/.ssh/id_ed25519
gh secret set SSH_KNOWN_HOSTS --repo "${REPO}" --env production --body "$(ssh-keyscan -H ${VPS_HOST} 2>/dev/null)"

# Variables específicas de production
gh variable set DEPLOY_PATH  --repo "${REPO}" --env production --body "/opt/plannerate/production"
gh variable set COMPOSE_FILE --repo "${REPO}" --env production --body "docker-compose.production.yml"
```

---

## Mapa completo: qual workflow usa o quê

```
vps-v2-build-push          (sem environment)
  vars.GHCR_REPO            ← repository variable
  vars.DOMAIN_LANDLORD      ← repository variable

vps-v2-deploy-staging      (environment: staging)
  secrets.APP_HOST
  secrets.APP_USER
  secrets.SSH_PRIVATE_KEY
  → deploya em /opt/plannerate/staging/

vps-v2-deploy-production   (environment: production)
  secrets.APP_HOST
  secrets.APP_USER
  secrets.SSH_PRIVATE_KEY
  → deploya em /opt/plannerate/production/

vps-v2-rollback            (environment: staging — fixo)
  secrets.APP_HOST
  secrets.APP_USER
  secrets.SSH_PRIVATE_KEY

vps-v2-tenant-migrate      (environment: staging — fixo)
  secrets.APP_HOST
  secrets.APP_USER
  secrets.SSH_PRIVATE_KEY
```

> `vps-v2-rollback` e `vps-v2-tenant-migrate` usam `environment: staging` fixo no código.
> Para rodá-los em production, é necessário passar o `app_slug=production` no `workflow_dispatch`
> (os comandos SSH ainda chegam no VPS correto, só as protection rules do environment serão as do staging).
