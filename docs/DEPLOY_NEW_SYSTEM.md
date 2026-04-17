# 🚀 Novo Sistema de Deploy - Plannerate

## 📋 Visão Geral

Sistema de deploy profissional usando **GitHub Container Registry** + **Docker Compose** + **GitHub Actions**. Zero conflitos de permissão, deployments rápidos, fácil rollback.

## 🏗️ Arquitetura

```
GitHub Actions (CI/CD)
    ↓
Build & Push → GitHub Container Registry (GHCR)
    ↓
Deploy → VPS (Pull images prontas)
```

### Componentes:

- **Build Workflow** (`.github/workflows/build-and-push.yml`)
  - Faz build das imagens Docker
  - Publica no GHCR
  - Tags: `dev`, `main`, SHA

- **Deploy Staging** (`.github/workflows/deploy-staging.yml`)
  - Automático após build da branch `dev`
  - Pull de imagens do GHCR
  - Backup de banco
  - Migrations e cache

- **Deploy Production** (`.github/workflows/deploy-production.yml`)
  - Após build da branch `main`
  - Requer confirmação manual
  - Backup obrigatório (mantém 30 dias)
  - Rollback automático em falha

## 🎯 Fluxo de Deploy

### Staging (Automático):
```bash
git push origin dev
↓
Build & Push (automático)
↓
Deploy Staging (automático)
```

### Production (Com aprovação):
```bash
git push origin main
↓
Build & Push (automático)
↓
Deploy Production (requer aprovação no GitHub)
```

## 🛠️ Setup Inicial da VPS

### 1. Prepare a VPS manualmente:

```bash
ssh root@148.230.78.184


# Dependências básicas
apt-get update && apt-get install -y docker.io docker-compose-plugin git ufw

# Usuário de deploy
useradd -m -s /bin/bash plannerate || true
usermod -aG docker plannerate

# Estrutura de diretórios
mkdir -p /opt/plannerate/staging /opt/plannerate/production /opt/traefik/letsencrypt
chown -R plannerate:plannerate /opt/plannerate

# Rede global do Traefik
docker network create traefik-global || true
```

### 2. Suba o Traefik global:

Use os arquivos do repositório já existentes para publicar o proxy reverso e certificados.

```bash
cd /opt/traefik
docker compose -f /caminho/do/repositorio/traefik-docker-compose.yml up -d
```

### 3. Copie os arquivos docker-compose para a VPS:

```bash
# Na sua máquina local
scp docker-compose.staging.yml root@148.230.78.184:/opt/plannerate/staging/docker-compose.staging.yml
scp docker-compose.production.yml root@148.230.78.184:/opt/plannerate/production/docker-compose.production.yml
```

### 4. Configure os arquivos .env:

```bash
# Conectar como usuário plannerate
ssh root@148.230.78.184

# Staging
cd /opt/plannerate/staging
cp .env.example .env.staging
vim .env.staging  # Preencher valores reais

# Production
cd /opt/plannerate/production
cp .env.example .env.production
vim .env.production  # Preencher valores reais
```

**Valores importantes para preencher:**
- `APP_KEY` - Gerar com: `docker run --rm ghcr.io/seu-usuario/plannerate:dev php artisan key:generate --show`
- `GITHUB_REPO` - Seu repositório no formato `usuario/repo`
- `DB_PASSWORD` - Senha forte para PostgreSQL
- `REDIS_PASSWORD` - Senha forte para Redis
- `REVERB_APP_KEY`, `REVERB_APP_SECRET` - Chaves aleatórias
- `DO_SPACES_KEY`, `DO_SPACES_SECRET` - Credenciais DigitalOcean Spaces
- `MAIL_*` - Configurações de email

### 4. Configure DNS:

Adicione os seguintes registros A no seu provedor de DNS:

**Staging:**
- `staging.plannerate.dev.br` → IP da VPS
- `*.plannerate.dev.br` → IP da VPS

**Production:**
- `plannerate.com.br` → IP da VPS
- `www.plannerate.com.br` → IP da VPS
- `*.plannerate.com.br` → IP da VPS

### 5. Configure GitHub Secrets:

No repositório GitHub, vá em **Settings → Secrets and variables → Actions** e adicione:

```
VPS_HOST=seu-ip-da-vps
VPS_USER=plannerate
SSH_PRIVATE_KEY=<conteúdo da chave privada SSH>
STAGING_DOMAIN=staging.plannerate.dev.br
PRODUCTION_DOMAIN=plannerate.com.br
```

**Para gerar par de chaves SSH:**
```bash
# Na sua máquina local
ssh-keygen -t ed25519 -C "github-actions-plannerate" -f ~/.ssh/plannerate-deploy

# Chave pública (adicionar na VPS)
cat ~/.ssh/plannerate-deploy.pub

# Chave privada (adicionar no GitHub Secret)
cat ~/.ssh/plannerate-deploy
```

**Adicionar chave pública na VPS:**
```bash
# Na VPS como root
echo "ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOp74hdfOGKM4KoaJBQJYnIHByV+A4YGQMr0Vrod/qPr github-actions-plannerate" >> /home/plannerate/.ssh/authorized_keys
chown plannerate:plannerate /home/plannerate/.ssh/authorized_keys
chmod 600 /home/plannerate/.ssh/authorized_keys
```

## 🚀 Fazendo Deploys

### Deploy Staging (Automático):
```bash
# Qualquer push na branch dev
git add .
git commit -m "feat: nova feature"
git push origin dev

# GitHub Actions irá:
# 1. Build da imagem
# 2. Push para GHCR
# 3. Deploy automático no staging
```

### Deploy Production (Manual):
```bash
# Push ou merge na branch main
git checkout main
git merge dev
git push origin main

# GitHub Actions irá:
# 1. Build da imagem
# 2. Push para GHCR
# 3. Aguardar aprovação manual
# 4. Deploy em produção após aprovação
```

**Ou via workflow_dispatch:**
```bash
gh workflow run deploy-production.yml -f confirm=PRODUCTION
```

## 🔄 Rollback

### Usando tags antigas:

```bash
# Na VPS
ssh root@148.230.78.184
cd /opt/plannerate/production

# Editar docker-compose.production.yml
vim docker-compose.production.yml
# Mudar tag de :main para :SHA-ANTIGO

# Restart
docker compose -f docker-compose.production.yml pull
docker compose -f docker-compose.production.yml up -d
```

### Restaurar backup de banco:

```bash
# Listar backups
ls -lh /opt/plannerate/production/backups/

# Restaurar backup
gunzip -c /opt/plannerate/production/backups/prod-backup-TIMESTAMP.sql.gz | \
  docker compose -f docker-compose.production.yml exec -T postgres \
  psql -U postgres -d plannerate
```

## 🔍 Monitoramento

### Verificar status dos containers:

```bash
# Staging
cd /opt/plannerate/staging
docker compose -f docker-compose.staging.yml ps
docker compose -f docker-compose.staging.yml logs -f app

# Production
cd /opt/plannerate/production
docker compose -f docker-compose.production.yml ps
docker compose -f docker-compose.production.yml logs -f app
```

### Verificar Traefik:

```bash
cd /opt/traefik
docker compose ps
docker compose logs -f

# Dashboard (se configurado): https://traefik.plannerate.com.br
```

### Health checks:

```bash
# Staging
curl -I https://staging.plannerate.dev.br/up

# Production
curl -I https://plannerate.com.br/up
```

## 🐛 Troubleshooting

### Containers não iniciam:

```bash
# Verificar logs
docker compose -f docker-compose.staging.yml logs

# Verificar variáveis de ambiente
docker compose -f docker-compose.staging.yml config

# Restart completo
docker compose -f docker-compose.staging.yml down
docker compose -f docker-compose.staging.yml up -d
```

### Erro de autenticação no GHCR:

```bash
# Na VPS, fazer login manual
echo "$GITHUB_TOKEN" | docker login ghcr.io -u seu-usuario --password-stdin

# Ou criar Personal Access Token com permissão packages:read
```

### SSL/TLS não funciona:

```bash
# Verificar Traefik
cd /opt/traefik
docker compose logs traefik | grep -i acme

# Verificar DNS
dig staging.plannerate.dev.br +short

# Certificados Let's Encrypt
ls -lh /opt/traefik/letsencrypt/acme.json
```

### Permissões de storage:

```bash
# Containers já rodam como www-data, mas se necessário:
docker compose -f docker-compose.production.yml exec app \
  chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
```

## 📊 Diferenças vs Sistema Antigo

| Aspecto | Sistema Antigo | Sistema Novo |
|---------|----------------|--------------|
| Build | Na VPS (lento) | GitHub Actions (rápido) |
| Deploy | Git pull + build | Docker pull (segundos) |
| Permissões | Conflitos constantes | Zero conflitos |
| Rollback | Difícil | Simples (mudar tag) |
| Ambientes | Um único | Staging + Production |
| Backups | Manual | Automático |
| Logs | Dispersos | Centralizados |

## 🎉 Vantagens

- ✅ **Zero conflitos de permissão** - Não usa git na VPS
- ✅ **Deploy ultra-rápido** - Apenas pull de imagem pronta (< 30s)
- ✅ **Rollback fácil** - Voltar para qualquer versão anterior
- ✅ **Múltiplos ambientes** - Staging e Production isolados
- ✅ **Backups automáticos** - Banco sempre protegido
- ✅ **CI/CD profissional** - Build uma vez, deploy em qualquer lugar
- ✅ **Segurança** - Production requer aprovação manual
- ✅ **Observabilidade** - Logs centralizados, health checks

## 📝 Checklist de Migração

- [ ] VPS preparada manualmente (Docker, usuário `plannerate`, diretórios, rede `traefik-global`)
- [ ] Usuário `plannerate` criado
- [ ] Docker e Traefik instalados
- [ ] Arquivos docker-compose copiados para VPS
- [ ] Arquivos `.env.staging` e `.env.production` configurados
- [ ] DNS configurado (A records apontando para VPS)
- [ ] GitHub Secrets configurados (VPS_HOST, VPS_USER, SSH_PRIVATE_KEY)
- [ ] SSH key adicionada ao `authorized_keys` na VPS
- [ ] Primeiro deploy testado (push na branch dev)
- [ ] Staging funcionando corretamente
- [ ] Production deployment testado
- [ ] Backups funcionando
- [ ] Monitoramento configurado

## 📚 Arquivos Relacionados

- `.github/workflows/build-and-push.yml` - Build de imagens
- `.github/workflows/deploy-staging.yml` - Deploy staging
- `.github/workflows/deploy-production.yml` - Deploy production
- `docker-compose.staging.yml` - Compose staging
- `docker-compose.production.yml` - Compose production
- `Dockerfile.prod` - Imagem Docker da aplicação

## 🆘 Suporte

Em caso de problemas:

1. Verificar logs: `docker compose logs`
2. Verificar GitHub Actions: aba "Actions" no repositório
3. Verificar DNS: `dig seu-dominio.com +short`
4. Verificar Traefik: `docker compose -f /opt/traefik/docker-compose.yml logs`
5. Consultar documentação: [SAVE_SYSTEM.md](SAVE_SYSTEM.md), [DASHBOARD_INTEGRACOES.md](DASHBOARD_INTEGRACOES.md)