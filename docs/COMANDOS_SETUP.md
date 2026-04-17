# Comandos de Setup e Importação - Plannerate

Este documento descreve os comandos essenciais para configurar um novo ambiente ou migrar dados do sistema legado.

## Índice

1. [Migrações Multi-Tenant](#1-migrações-multi-tenant)
2. [Importação de Dados Legados - Base](#2-importação-de-dados-legados---base)
3. [Importação de Dados Legados - Clientes](#3-importação-de-dados-legados---clientes)
4. [Seed de Workflow (Flow)](#4-seed-de-workflow-flow)
5. [Fluxo Completo de Setup](#5-fluxo-completo-de-setup)

---

## 1. Migrações Multi-Tenant

### Comando: `tenant:migrate`

Executa migrações no banco principal e nos bancos de todos os tenants (clientes).

```bash
# Executar todas as migrações (principal + clientes)
sail artisan tenant:migrate

# Apenas migrações do banco principal
sail artisan tenant:migrate --only-main

# Apenas migrações dos clientes
sail artisan tenant:migrate --only-clients

# Cliente específico
sail artisan tenant:migrate --client=franciosi

# Criar bancos de dados dos clientes se não existirem
sail artisan tenant:migrate --create-db

# Fresh: dropar e recriar tudo (CUIDADO!)
sail artisan tenant:migrate --fresh

# Fresh apenas no banco principal
sail artisan tenant:migrate --fresh --only-main
```

### Opções Disponíveis

| Opção | Descrição |
|-------|-----------|
| `--only-main` | Executa apenas migrações do banco principal |
| `--only-clients` | Executa apenas migrações dos bancos de clientes |
| `--client=SLUG` | Executa apenas para o cliente especificado |
| `--create-db` | Cria os bancos de dados dos clientes se não existirem |
| `--fresh` | Dropa todas as tabelas antes de migrar |
| `--seed` | Executa seeders após as migrações |

### Estrutura de Migrações

- **Banco Principal**: `database/migrations/`
- **Bancos de Clientes**: `database/migrations/clients/`

---

## 2. Importação de Dados Legados - Base

### Comando: `import:legacy-main`

Importa dados base do banco MySQL legado para o PostgreSQL principal.

```bash
# Importar todas as tabelas base
sail artisan import:legacy-main

# Fresh: limpar tabelas antes de importar
sail artisan import:legacy-main --fresh
```

### Tabelas Importadas

| Ordem | Tabela | Descrição |
|-------|--------|-----------|
| 1 | `tenants` | Tenants do sistema |
| 2 | `users` | Usuários |
| 3 | `clients` | Clientes (empresas) |
| 4 | `stores` | Lojas |
| 5 | `roles` | Perfis de acesso |
| 6 | `permissions` | Permissões |
| 7 | `role_has_permissions` | Relação perfil-permissão |
| 8 | `model_has_roles` | Relação usuário-perfil |
| 9 | `model_has_permissions` | Relação usuário-permissão |

### Opções

| Opção | Descrição |
|-------|-----------|
| `--fresh` | Limpa as tabelas de destino antes de importar |

### Requisitos

- Conexão `mysql_legacy` configurada no `.env`:

```env
LEGACY_DB_HOST=mysql_legacy
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=plannerate_legacy
LEGACY_DB_USERNAME=root
LEGACY_DB_PASSWORD=secret
```

---

## 3. Importação de Dados Legados - Clientes

### Comando: `import:legacy-client`

Importa dados específicos de cada cliente (produtos, planogramas, gôndolas, etc.) para seus respectivos bancos tenant.

```bash
# Importar todos os clientes (interativo - selecione quais)
sail artisan import:legacy-client

# Cliente específico pelo slug
sail artisan import:legacy-client --client=franciosi

# Fresh: limpar tabelas antes de importar
sail artisan import:legacy-client --fresh

# Criar bancos de dados se não existirem
sail artisan import:legacy-client --create-db

# Combinado: fresh + criar banco
sail artisan import:legacy-client --client=franciosi --fresh --create-db
```

### Tabelas Importadas (por cliente)

| Ordem | Tabela | Descrição |
|-------|--------|-----------|
| 1 | `categories` | Categorias mercadológicas |
| 2 | `products` | Produtos |
| 3 | `planograms` | Planogramas |
| 4 | `gondolas` | Gôndolas |
| 5 | `sections` | Seções/Módulos |
| 6 | `shelves` | Prateleiras |
| 7 | `segments` | Segmentos |
| 8 | `layers` | Camadas de produtos |

### Opções

| Opção | Descrição |
|-------|-----------|
| `--client=SLUG` | Importa apenas o cliente especificado |
| `--fresh` | Limpa tabelas de destino antes de importar |
| `--create-db` | Cria banco de dados do cliente se não existir |

### Mapeamento de IDs

O comando mantém um cache de mapeamento entre IDs antigos (MySQL) e novos (PostgreSQL ULID) para manter a integridade referencial:

- `category_id` → nova categoria
- `planogram_id` → novo planograma
- `gondola_id` → nova gôndola
- `section_id` → nova seção
- `shelf_id` → nova prateleira
- `segment_id` → novo segmento

---

## 4. Seed de Workflow (Flow)

O workflow de planogramas/gôndolas usa o pacote **laravel-raptor-flow** e as tabelas `flow_*` no banco de cada cliente.

### Comando: `flow:seed`

Cria do zero (se não existir): **FlowStepTemplate**, **FlowConfig** + **FlowConfigStep** por planograma e **FlowExecution** por gôndola. Se não houver nenhum `FlowStepTemplate` no cliente, os templates são criados a partir dos `WorkflowStepTemplate` do landlord.

```bash
# Processar todos os clientes (interativo)
sail artisan flow:seed

# Cliente específico
sail artisan flow:seed --client=franciosi

# Recriar configs/executions mesmo se já existirem
sail artisan flow:seed --force

# Apenas criar configs (sem executions)
sail artisan flow:seed --skip-executions

# Apenas criar executions (sem configs)
sail artisan flow:seed --skip-configs
```

### O que é criado

1. **FlowStepTemplate** (por cliente): se a tabela estiver vazia, copia de `WorkflowStepTemplate` (landlord).
2. **FlowConfig** + **FlowConfigStep**: uma config por planograma, com uma etapa por template (ordem).
3. **FlowExecution**: uma execução pendente (primeira etapa) por gôndola.

### Comando: `workflow:verify`

Lista por cliente: quantidade de planogramas, FlowConfigs, gôndolas, FlowExecutions e FlowStepTemplates (tabelas `flow_*`).

```bash
sail artisan workflow:verify
sail artisan workflow:verify --client=franciosi
```

### Pré-requisitos

- Migrações do pacote flow aplicadas nos bancos dos clientes (tabelas `flow_*`).
- Para ter templates no flow sem usar o legado: criar `WorkflowStepTemplate` no landlord ou rodar `flow:seed` (que cria templates a partir do legado quando vazio).

---

## 5. Fluxo Completo de Setup

### Novo Ambiente (do zero)

```bash
# 1. Copiar .env e configurar
cp .env.example .env
# Editar .env com configurações de banco

# 2. Instalar dependências
composer install
npm install

# 3. Gerar chave da aplicação
sail artisan key:generate

# 4. Executar migrações (com criação de bancos)
sail artisan tenant:migrate --create-db --seed

# 5. (Opcional) Seed de workflow (flow)
sail artisan flow:seed

# 6. Build do frontend
sail npm run build
```

### Migração de Dados Legados

```bash
# 1. Garantir que o banco legado está acessível
# Configurar LEGACY_DB_* no .env

# 2. Executar migrações fresh (limpa tudo)
sail artisan tenant:migrate --fresh --create-db

# 3. Importar dados base (tenants, users, clients, stores, roles)
sail artisan import:legacy-main --fresh

# 4. Importar dados de cada cliente (products, planograms, gondolas...)
sail artisan import:legacy-client --fresh --create-db

# 5. Criar workflow (flow)
sail artisan flow:seed

# 6. Verificar dados
sail artisan workflow:verify
```

### Atualização de Dados (sem perder dados existentes)

```bash
# Apenas migrações (adicionar novas tabelas/colunas)
sail artisan tenant:migrate

# Importar novos dados do legado (sem --fresh)
sail artisan import:legacy-main
sail artisan import:legacy-client

# Criar workflow para novos planogramas/gôndolas
sail artisan flow:seed
```

---

## Troubleshooting

### Erro: "Conexão recusada ao banco legado"

Verifique as configurações no `.env`:
```env
LEGACY_DB_HOST=mysql_legacy  # ou IP do servidor
LEGACY_DB_PORT=3306
```

### Erro: "Banco de dados não existe"

Use a flag `--create-db`:
```bash
sail artisan tenant:migrate --create-db
sail artisan import:legacy-client --create-db
```

### Erro: "Nenhum template disponível" (flow)

Crie `WorkflowStepTemplate` no landlord ou garanta que existam registros em `workflow_step_templates`; o `flow:seed` cria `FlowStepTemplate` a partir deles quando a tabela flow estiver vazia.

### Erro: "Foreign key constraint fails"

Execute na ordem correta:
1. `import:legacy-main` (base)
2. `import:legacy-client` (dados por cliente)
3. `flow:seed` (workflow flow)

### Resetar tudo e começar do zero

```bash
# CUIDADO: Isso apaga TODOS os dados!
sail artisan tenant:migrate --fresh --create-db --seed
sail artisan import:legacy-main --fresh
sail artisan import:legacy-client --fresh --create-db
sail artisan flow:seed --force
```

---

## Resumo dos Comandos

| Comando | Descrição |
|---------|-----------|
| `tenant:migrate` | Migrações multi-tenant |
| `import:legacy-main` | Importa dados base do legado |
| `import:legacy-client` | Importa dados de clientes do legado |
| `flow:seed` | Cria FlowStepTemplate, FlowConfig/FlowConfigStep e FlowExecutions (workflow) |
| `workflow:verify` | Lista por cliente: planogramas, FlowConfigs, gôndolas, FlowExecutions |

---

*Documentação criada em 30/01/2026*
