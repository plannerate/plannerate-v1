# VPS Deployment v2 — Guia de Implantação

Deploy completo para staging + produção no mesmo VPS, com banco externo, Traefik, monitoramento e backups automáticos.

---

## Visão Geral da Arquitetura

```
Internet
   │
   ▼
Traefik (80/443 + Let's Encrypt)
   ├── plannerate.com.br         → Production stack
   ├── plannerate.xyz            → Staging stack
   ├── grafana.plannerate.com.br
   └── prometheus.plannerate.com.br

App VPS (Ubuntu 24.04)
   ├── /opt/production/   (app, queue, scheduler, reverb, redis)
   ├── /opt/staging/      (app, queue, scheduler, reverb, redis)
   ├── /opt/traefik/
   └── /opt/monitoring/   (Prometheus, Grafana, Alertmanager, Node Exporter)

DB VPS (separado)
   ├── plannerate_production
   └── plannerate_staging
```

---

## Pré-requisitos

| Item | Detalhe |
|------|---------|
| App VPS | Ubuntu 24.04, mín. 20 GB disco, 2 GB RAM |
| DB VPS | Ubuntu 24.04 com MySQL 8+ ou PostgreSQL 15+ |
| Domínio | DNS apontando para o IP do App VPS |
| GitHub | Repositório com Actions habilitado |
| `ssh-keygen`, `ssh-keyscan` | Disponíveis na máquina local |
| `gh` CLI (opcional) | Para configurar secrets automaticamente |

---

## Setup em um comando

```bash
bash vps-deployment-v2/setup.sh
```

O wizard interativo vai guiar cada etapa:

### O que ele faz

1. **Coleta as informações** — pergunta projeto, domínios, VPS, banco, DO Spaces, webhooks, Grafana
2. **Gera a chave SSH de deploy** automaticamente
3. **Exibe a chave pública** e pede para você colar no GitHub → aguarda ENTER
4. **Configura secrets no GitHub** automaticamente (se `gh` CLI estiver autenticado)
5. **Salva o `manifest.env`** com todas as variáveis (permissão 600, adicionado ao `.gitignore`)
6. **Provisiona o App VPS** via SSH (opcional, pergunta antes)

### Exemplo de execução

```
▶ FASE 1 — Projeto e domínios
  Nome do projeto [plannerate]: plannerate
  GitHub org/usuário: callcocam
  Nome do repositório: plannerate-v1
  Domínio produção: plannerate.com.br
  Domínio staging:   plannerate.xyz
  Domínio Grafana:   grafana.plannerate.com.br
  Domínio Prometheus: prometheus.plannerate.com.br
  ...

▶ FASE 7 — Chave SSH de deploy
  ✔ Chave gerada: ~/.ssh/id_ed25519_plannerate-v1_deploy

▶ FASE 8 — Adicione a chave pública como Deploy Key no GitHub
  Abra: https://github.com/callcocam/plannerate-v1/settings/keys/new
  ─────────────────────────────────────────────────
  ssh-ed25519 AAAA... callcocam/plannerate-v1-deploy
  ─────────────────────────────────────────────────
  Pressione ENTER para continuar...     ← aguarda você colar e salvar

▶ FASE 9 — Configurar Secrets e Variables no GitHub
  ✔ [staging] secrets configurados.
  ✔ [production] secrets configurados.

▶ FASE 11 — Provisionar o App VPS
  Provisionar o App VPS agora? [S/n]: s
  ...
```

---

## Após o setup.sh — Passos finais

### Provisionar o DB VPS

```bash
bash vps-deployment-v2/provisioning/setup-db-host.sh --manifest vps-deployment-v2/manifest.env
```

### Instalar os Compose Files no VPS

```bash
START_SERVICES=true bash vps-deployment-v2/automation/install-compose-on-host.sh --manifest vps-deployment-v2/manifest.env
```

Serviços iniciados:
- **Traefik** → reverse proxy + TLS automático
- **Production** → app, queue, scheduler, reverb, redis
- **Staging** → idem, com configurações mais leves

### Instalar o Monitoramento

```bash
bash vps-deployment-v2/automation/install-monitoring-on-host.sh --manifest vps-deployment-v2/manifest.env
```

Sobe: Prometheus, Grafana, Alertmanager, Node Exporter, cAdvisor, Blackbox Exporter.

Acesse em `https://grafana.plannerate.com.br` após a instalação.

### Configurar Backups

```bash
bash vps-deployment-v2/automation/install-backup-cron.sh --manifest vps-deployment-v2/manifest.env
```

Backups enviados para DO Spaces em `.sql.gz`, retenção de 14 dias por padrão.

### Configurar Health Check Periódico

```bash
bash vps-deployment-v2/automation/install-health-cron.sh --manifest vps-deployment-v2/manifest.env
```

Verifica: Docker daemon, containers, endpoints HTTP, cron, disco e memória.

---

## Deploy via GitHub Actions

Após o setup inicial, os deploys acontecem automaticamente:

| Evento | Comportamento |
|--------|--------------|
| Push na `main` | Build da imagem + deploy automático em staging |
| Workflow manual | Deploy em produção (requer aprovação do environment) |
| Rollback | Workflow manual — escolha o environment e a tag da imagem |

---

## Operações do Dia a Dia

### Verificar saúde do VPS

```bash
bash vps-deployment-v2/automation/vps-health-check.sh --manifest manifest.env
```

### Testar webhooks de alerta

```bash
# Envia mensagem de teste para todos os webhooks configurados
bash vps-deployment-v2/automation/test-webhooks.sh --manifest manifest.env

# Só exibe os comandos sem enviar
bash vps-deployment-v2/automation/test-webhooks.sh --manifest manifest.env --dry-run
```

### Restaurar um backup

```bash
bash vps-deployment-v2/automation/restore-db.sh \
  --manifest manifest.env \
  --file backups/production/plannerate_production_2026-05-01.sql.gz
```

---

## Estrutura de Arquivos

```
vps-deployment-v2/
├── provisioning/
│   ├── common.sh               # Funções compartilhadas
│   ├── validate-prereqs.sh     # Checagem de pré-requisitos
│   ├── setup-app-host.sh       # Provisiona App VPS
│   ├── setup-db-host.sh        # Provisiona DB VPS
│   └── bootstrap-all.sh        # Executa tudo em sequência
│
├── deployments/
│   ├── docker-compose.production.yml
│   ├── docker-compose.staging.yml
│   ├── traefik/
│   │   └── docker-compose.yml
│   └── monitoring/
│       ├── docker-compose.yml
│       ├── prometheus.yml
│       ├── alerts.yml
│       ├── alertmanager.yml
│       └── blackbox.yml
│
├── automation/
│   ├── bootstrap-github.sh         # Configura GitHub
│   ├── install-compose-on-host.sh  # Instala compose files no VPS
│   ├── install-monitoring-on-host.sh
│   ├── backup-db.sh                # Backup para DO Spaces
│   ├── restore-db.sh               # Restauração de backup
│   ├── run-backup-all.sh           # Backup staging + production
│   ├── install-backup-cron.sh      # Agenda cron de backup
│   ├── vps-health-check.sh         # Health check completo
│   ├── install-health-cron.sh      # Agenda cron de health check
│   └── test-webhooks.sh            # Testa webhooks de alerta
│
└── templates/
    ├── manifest.example.env        # Template de configuração
    ├── .env.production.example
    └── .env.staging.example
```

---

## Alertas

Os alertas são roteados por severidade para webhooks distintos:

| Severidade | Webhook | Quando |
|-----------|---------|--------|
| `critical` | `ALERT_WEBHOOK_CRITICAL_URL` | CPU >85%, memória >90%, app fora do ar |
| `warning` | `ALERT_WEBHOOK_WARNING_URL` | Disco <15%, serviços degradados |
| `default` | `ALERT_WEBHOOK_DEFAULT_URL` | Demais alertas do Alertmanager |
| backup | `BACKUP_ALERT_WEBHOOK_URL` | Falha no backup |

Compatível com Discord e Slack.
