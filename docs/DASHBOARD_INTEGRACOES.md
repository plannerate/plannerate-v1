# Dashboard de Integrações

## Visão Geral

Dashboard para monitorar o status das sincronizações de integrações (Vendas, Produtos, Compras) com APIs externas (Visão e Sysmo).

## Acesso

- **URL**: `/settings/integrations`
- **Rota**: `integrations.dashboard`
- **Menu**: Sidebar → Sistema → Integrações (ícone Activity)

## Funcionalidades

### 1. Cards de Resumo (Topo)
- **Taxa de Sucesso**: Percentual de syncs bem-sucedidos
- **Falhas**: Total de syncs falhados + quantos precisam retry
- **Dias Pulados**: Dias que atingiram máximo de 5 tentativas
- **Itens Sincronizados**: Total de registros processados

### 2. Estatísticas por Tipo
Três cards mostrando métricas individuais para:
- **Vendas**: Stats de day-by-day sync
- **Produtos**: Stats de full sync
- **Compras**: Stats (preparado para futura implementação)

Cada card exibe:
- Total de dias sincronizados
- Sucessos (verde)
- Falhas (vermelho)
- Pulados (amarelo)
- Total de itens processados
- Falhas consecutivas (se houver)

### 3. Dias com Falhas
Tabela com até 30 dias que falharam, mostrando:
- Data do sync
- Tipo (Vendas/Produtos/Compras)
- Loja
- Status (badge colorido)
- Tentativas (x/5) - destaca em vermelho quando >= 5
- Mensagem de erro

### 4. Últimas Sincronizações
Tabela com logs das últimas 24 horas (50 registros), mostrando:
- Data/Hora da execução
- Tipo de sync
- Data sendo sincronizada
- Loja
- Status com ícone
- Quantidade de itens processados
- Número de tentativas

## Dados Técnicos

### Backend
- **Controller**: `App\Http\Controllers\Settings\IntegrationSyncDashboardController`
- **Service**: `App\Services\Sync\IntegrationSyncRetryService`
- **Model**: `App\Models\IntegrationSyncLog`

### Dados Retornados
```php
[
    'stats' => [
        'sales' => [
            'total_days' => int,
            'success' => int,
            'failed' => int,
            'skipped' => int,
            'pending' => int,
            'total_items' => int,
            'needs_retry' => int,
            'consecutive_failures' => int,
        ],
        'products' => [...],
        'purchases' => [...],
    ],
    'recentLogs' => [ // últimos 50 da últimas 24h
        [
            'id' => string,
            'sync_type' => 'sales|products|purchases',
            'sync_date' => 'Y-m-d',
            'status' => 'success|failed|skipped|pending',
            'retry_count' => int,
            'total_items' => int|null,
            'error_message' => string|null,
            'store_name' => string,
            'created_at' => datetime,
        ],
        ...
    ],
    'failedDays' => [ // 30 mais recentes
        [...],
        'can_retry' => bool,
        'should_skip' => bool,
    ],
    'timeline' => [ // 7 dias agrupados
        [
            'date' => 'Y-m-d',
            'sales' => ['total' => int, 'success_count' => int, 'failed_count' => int, 'skipped_count' => int],
            'products' => [...],
            'purchases' => [...],
        ],
    ],
    'client' => ['id' => string, 'name' => string],
]
```

### Frontend
- **Página**: `resources/js/pages/settings/IntegrationSyncDashboard.vue`
- **Layout**: `AppLayout`
- **Componentes**: shadcn-vue (Card, Table, Badge)
- **Ícones**: lucide-vue-next (Activity, CheckCircle2, XCircle, AlertCircle, Clock)

## Status e Cores

| Status | Cor | Significado |
|--------|-----|-------------|
| success | Verde | Sync concluído com sucesso |
| failed | Vermelho | Sync falhou, pode tentar novamente |
| skipped | Amarelo | Máximo de tentativas atingido (5) |
| pending | Azul | Aguardando execução |

## Próximos Passos (Opcional)

1. **Ações de Retry**: Adicionar botões para retry manual de dias específicos
2. **Filtros**: Filtro por data, tipo, loja, status
3. **Export**: Exportar logs para CSV
4. **Gráficos**: Timeline visual dos últimos 7 dias
5. **Real-time**: Atualização automática via polling/websockets
6. **Notificações**: Preferências de notificações por email

## Manutenção

### Limpar Cache
```bash
docker compose exec app php artisan optimize:clear
```

### Ver Logs
```bash
docker compose exec app tail -f storage/logs/laravel.log
```

### Retry Manual via Command
```bash
# Retry vendas de um cliente
docker compose exec app php artisan sync:retry --type=sales --client=ULID

# Retry produtos
docker compose exec app php artisan sync:retry --type=products --client=ULID

# Force retry (ignora should_skip)
docker compose exec app php artisan sync:retry --type=sales --force
```
