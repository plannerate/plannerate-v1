# Comandos de Sincronização

Documentação dos comandos de sincronização de dados com APIs externas (Sysmo, Visão).

## Índice

- [sync:sales](#syncsales)
- [sync:products](#syncproducts)
- [sync:cleanup](#synccleanup)
- [Agendamentos](#agendamentos)
- [Configuração de Integração](#configuração-de-integração)

---

## sync:sales

Sincroniza vendas da API externa com análise de lacunas.

### Uso

```bash
# Sincronizar todos os clientes (preenche lacunas automaticamente)
./vendor/bin/sail artisan sync:sales

# Sincronizar cliente específico
./vendor/bin/sail artisan sync:sales --client=ID_DO_CLIENTE

# Sincronizar a partir de uma data específica
./vendor/bin/sail artisan sync:sales --from=2026-01-01

# Sincronizar últimos N dias
./vendor/bin/sail artisan sync:sales --from=30

# Preview (mostra o que seria feito sem executar)
./vendor/bin/sail artisan sync:sales --preview

# Debug da configuração de integração
./vendor/bin/sail artisan sync:sales --debug-config

# Pular clientes com histórico completo
./vendor/bin/sail artisan sync:sales --skip-complete

# Limpar vendas antes de sincronizar
./vendor/bin/sail artisan sync:sales --truncate --client=ID_DO_CLIENTE
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--client=` | ID do cliente específico para sincronizar |
| `--from=` | Data inicial (Y-m-d) ou número de dias atrás |
| `--truncate` | Limpa vendas antes de sincronizar |
| `--skip-complete` | Pula clientes com histórico 100% completo |
| `--preview` | Exibe resumo sem executar |
| `--debug-config` | Exibe configuração de integração |

### Modos de Sincronização

| Modo | Descrição |
|------|-----------|
| `initial_setup` | Primeira sincronização, busca período completo |
| `gap_fill` | Preenche lacunas encontradas no histórico |
| `incremental` | Busca apenas datas novas (sem lacunas) |
| `custom_period` | Período customizado via `--from` |

### Comportamento

1. **Analisa o período completo** configurado na integração (ex: 365 dias)
2. **Identifica lacunas** - datas sem vendas no banco
3. **Cria um job por data** faltante
4. **Processa sequencialmente** via Horizon

---

## sync:products

Sincroniza produtos da API externa.

### Uso

```bash
# Sincronizar todos os clientes
./vendor/bin/sail artisan sync:products

# Sincronizar cliente específico
./vendor/bin/sail artisan sync:products --client=ID_DO_CLIENTE

# Debug da configuração
./vendor/bin/sail artisan sync:products --debug-config

# Limpar produtos antes de sincronizar
./vendor/bin/sail artisan sync:products --truncate --client=ID_DO_CLIENTE
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--client=` | ID do cliente específico |
| `--truncate` | Limpa produtos antes de sincronizar |
| `--debug-config` | Exibe configuração de integração |

---

## sync:cleanup

Verifica e limpa vendas/produtos órfãos.

### Uso

```bash
# Preview de todas as verificações
./vendor/bin/sail artisan sync:cleanup --all --preview

# Executar todas as verificações
./vendor/bin/sail artisan sync:cleanup --all

# Cliente específico
./vendor/bin/sail artisan sync:cleanup --client=ID_DO_CLIENTE --all

# Apenas vendas órfãs (sem produto correspondente)
./vendor/bin/sail artisan sync:cleanup --orphan-sales

# Apenas vendas antigas (anteriores ao período da integração)
./vendor/bin/sail artisan sync:cleanup --old-sales

# Apenas produtos inativos (sem vendas no período)
./vendor/bin/sail artisan sync:cleanup --inactive-products --days=180

# Restaurar produtos deletados que tiveram vendas
./vendor/bin/sail artisan sync:cleanup --restore-sold --days=30
```

### Opções

| Opção | Descrição |
|-------|-----------|
| `--client=` | ID do cliente específico |
| `--orphan-sales` | Deleta vendas sem produto correspondente |
| `--old-sales` | Deleta vendas anteriores ao período da integração |
| `--inactive-products` | Soft delete em produtos sem vendas |
| `--restore-sold` | Restaura produtos deletados com vendas recentes |
| `--days=` | Período em dias (padrão: 90) |
| `--all` | Executa todas as verificações |
| `--preview` | Apenas mostra o que seria feito |

### Verificações Disponíveis

| Verificação | Ação | Tipo |
|-------------|------|------|
| `--orphan-sales` | Deleta vendas sem produto | Delete permanente |
| `--old-sales` | Deleta vendas fora do período | Delete permanente |
| `--inactive-products` | Desativa produtos sem vendas | Soft delete |
| `--restore-sold` | Restaura produtos com vendas | Restore |

### Período da Integração

A opção `--old-sales` usa o campo `config.periodo` da integração do cliente:

```json
{
  "config": {
    "document_name": "empresa",
    "periodo": "365"
  }
}
```

Se `periodo = 365`, vendas anteriores a 365 dias serão deletadas.

---

## Agendamentos

Os comandos são executados automaticamente via Laravel Scheduler.

### Configuração Atual

| Comando | Frequência | Horário |
|---------|------------|---------|
| `sync:sales` | Diário | 07:00 |
| `sync:cleanup --old-sales` | Domingo | 02:00 |
| `sync:cleanup --orphan-sales` | Domingo | 03:00 |
| `sync:cleanup --restore-sold --days=30` | Segunda | 06:00 |
| `sync:cleanup --inactive-products --days=180` | Dia 1/mês | 04:00 |

### Verificar Agendamentos

```bash
./vendor/bin/sail artisan schedule:list
```

### Executar Manualmente

```bash
./vendor/bin/sail artisan schedule:run
```

---

## Configuração de Integração

A configuração de integração é armazenada na tabela `client_integrations`.

### Estrutura

```json
{
  "integration_type": "sysmo",
  "api_url": "https://api.exemplo.com",
  "authentication_headers": {
    "auth_username": "usuario",
    "auth_password": "senha"
  },
  "authentication_body": {
    "partner_key": "Proplanner",
    "pagina": "1",
    "tamanho_pagina": "1000"
  },
  "config": {
    "document_name": "empresa",
    "periodo": "365"
  }
}
```

### Campos Importantes

| Campo | Descrição |
|-------|-----------|
| `integration_type` | Tipo da integração (`sysmo`, `visao`) |
| `api_url` | URL base da API |
| `authentication_headers` | Credenciais de autenticação |
| `authentication_body` | Parâmetros do body da requisição |
| `config.document_name` | Nome do campo de documento (CNPJ) |
| `config.periodo` | Período em dias para buscar vendas |

### Debug da Configuração

```bash
# Ver como a configuração está sendo montada
./vendor/bin/sail artisan sync:sales --client=ID --debug-config
./vendor/bin/sail artisan sync:products --client=ID --debug-config
```

---

## Arquitetura

### Trait Compartilhado

Os comandos usam o trait `IntegrationConfigTrait` para código comum:

- `configureTenantContext()` - Configura conexão do cliente
- `getClients()` - Busca clientes ativos
- `prepareBaseIntegrationConfig()` - Prepara configuração de integração
- `normalizeArray()` - Normaliza arrays (formato antigo/novo)
- `debugIntegrationConfig()` - Debug da configuração

### Jobs de Sincronização

| Job | Descrição |
|-----|-----------|
| `Sysmo\DiscoverIntegrationSaleJob` | Busca vendas na API Sysmo |
| `Sysmo\DiscoverIntegrationProductJob` | Busca produtos na API Sysmo |
| `Visao\DiscoverIntegrationSaleJob` | Busca vendas na API Visão |
| `Visao\DiscoverIntegrationProductJob` | Busca produtos na API Visão |

### Jobs de Limpeza

| Job | Descrição |
|-----|-----------|
| `Cleanup\CleanupOrphanSalesJob` | Deleta vendas órfãs |
| `Cleanup\CleanupOldSalesJob` | Deleta vendas antigas |
| `Cleanup\DeactivateInactiveProductsJob` | Desativa produtos inativos |
| `Cleanup\RestoreSoldProductsJob` | Restaura produtos com vendas |

---

## Monitoramento

### Horizon

Os jobs são processados pelo Laravel Horizon:

```bash
# Iniciar Horizon
./vendor/bin/sail artisan horizon

# Verificar status
./vendor/bin/sail artisan horizon:status

# Dashboard
https://plannerate.test/horizon
```

### Logs

```bash
# Acompanhar em tempo real
tail -f storage/logs/laravel.log

# Filtrar por cliente
grep "01k7ez88kzrywyejk3xgvskyhj" storage/logs/laravel.log
```

---

## Exemplos Práticos

### Sincronizar vendas de um cliente novo

```bash
# 1. Verificar configuração
./vendor/bin/sail artisan sync:sales --client=ID --debug-config

# 2. Preview
./vendor/bin/sail artisan sync:sales --client=ID --preview

# 3. Executar
./vendor/bin/sail artisan sync:sales --client=ID
```

### Limpar dados antigos

```bash
# 1. Preview
./vendor/bin/sail artisan sync:cleanup --client=ID --all --preview

# 2. Executar apenas vendas órfãs
./vendor/bin/sail artisan sync:cleanup --client=ID --orphan-sales

# 3. Executar apenas vendas antigas
./vendor/bin/sail artisan sync:cleanup --client=ID --old-sales
```

### Re-sincronizar período específico

```bash
# Buscar últimos 30 dias
./vendor/bin/sail artisan sync:sales --client=ID --from=30

# Buscar a partir de data específica
./vendor/bin/sail artisan sync:sales --client=ID --from=2026-01-01
```

---

## Troubleshooting

### Vendas não estão sendo importadas

1. Verificar configuração:
   ```bash
   ./vendor/bin/sail artisan sync:sales --client=ID --debug-config
   ```

2. Verificar se há lacunas:
   ```bash
   ./vendor/bin/sail artisan sync:sales --client=ID --preview
   ```

3. Verificar logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Circuit Breaker aberto

Se muitas falhas consecutivas, o Circuit Breaker bloqueia temporariamente:

```
🔴 Circuit Breaker aberto - Integração bloqueada temporariamente
```

Aguarde o timeout ou verifique a API externa.

### Completude maior que 100%

Isso indica vendas duplicadas. Execute:

```bash
./vendor/bin/sail artisan sync:cleanup --client=ID --orphan-sales --preview
```
