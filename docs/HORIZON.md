# Laravel Horizon - Monitoramento de Filas

## O que é o Horizon?

Laravel Horizon fornece uma interface visual bonita para monitorar suas filas Redis. Permite ver:

- ✅ **Throughput de jobs** em tempo real
- ✅ **Jobs falhados** com detalhes do erro
- ✅ **Tempo de execução** de cada job
- ✅ **Memória utilizada** pelos workers
- ✅ **Métricas de performance** com gráficos
- ✅ **Tags personalizadas** para organizar jobs

## Acesso ao Dashboard

### Staging
```
https://plannerate.dev.br/horizon
```

### Production
```
https://plannerate.com.br/horizon
```

**Autenticação**: Apenas usuários autenticados têm acesso ao dashboard.

## Comandos Úteis

### Ver status do Horizon
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:status
```

### Pausar processamento de jobs
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:pause
```

### Continuar processamento
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:continue
```

### Terminar Horizon (graceful shutdown)
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:terminate
```

### Limpar jobs falhados
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:clear
```

### Ver métricas
```bash
docker compose -f docker-compose.staging.yml exec app php artisan horizon:snapshot
```

## Configuração

O arquivo de configuração está em `config/horizon.php` e define:

- **Filas monitoradas**: default, high, low
- **Workers por fila**: Quantidade de processos simultâneos
- **Tentativas**: Número de retries antes de falhar
- **Timeout**: Tempo máximo de execução de um job
- **Memória**: Limite de memória por worker

### Exemplo de configuração:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'high', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

## Tags para Organização

Você pode adicionar tags aos seus jobs para melhor organização no dashboard:

```php
class ProcessProductImport implements ShouldQueue
{
    public function tags()
    {
        return ['products', 'import', "client:{$this->client->id}"];
    }
}
```

No Horizon, você poderá filtrar jobs por essas tags.

## Notificações

Configure notificações para ser alertado quando:
- Filas ficam muito longas
- Jobs estão falhando muito
- Workers estão usando muita memória

Edite `app/Providers/HorizonServiceProvider.php`:

```php
public function boot(): void
{
    parent::boot();

    // Notificar via SMS
    Horizon::routeSmsNotificationsTo('15556667777');
    
    // Notificar via Email
    Horizon::routeMailNotificationsTo('admin@plannerate.com.br');
    
    // Notificar via Slack
    Horizon::routeSlackNotificationsTo('slack-webhook-url', '#ops-channel');
}
```

## Troubleshooting

### Jobs não estão sendo processados
```bash
# Ver logs do container
docker compose -f docker-compose.staging.yml logs -f queue

# Verificar status do Horizon
docker compose -f docker-compose.staging.yml exec app php artisan horizon:status

# Reiniciar workers
docker compose -f docker-compose.staging.yml restart queue
```

### Jobs falhando constantemente
1. Acesse o dashboard do Horizon
2. Vá para a aba "Failed Jobs"
3. Clique no job para ver detalhes do erro
4. Após corrigir o bug, clique em "Retry" ou use:
```bash
docker compose -f docker-compose.staging.yml exec app php artisan queue:retry all
```

### Performance lenta
1. Aumente o número de `processes` em `config/horizon.php`
2. Divida jobs grandes em jobs menores
3. Use `batch()` para processar em paralelo
4. Considere usar filas diferentes para jobs prioritários

## Monitoramento de Performance

O Horizon rastreia automaticamente:
- **Jobs/min**: Taxa de processamento
- **Failed Jobs/min**: Taxa de falhas
- **Min/Max/Avg Runtime**: Tempos de execução
- **Memory**: Uso de memória

Todas essas métricas ficam disponíveis no dashboard com gráficos em tempo real.

## Backup de Dados do Horizon

O Horizon usa Redis para armazenar dados. Para fazer backup:

```bash
# Criar snapshot do Redis
docker compose -f docker-compose.staging.yml exec redis redis-cli BGSAVE

# Copiar arquivo RDB
docker compose -f docker-compose.staging.yml exec redis cat /data/dump.rdb > horizon-backup-$(date +%Y%m%d).rdb
```

## Diferença: Horizon vs Queue:Work

| Característica | queue:work | Horizon |
|----------------|------------|---------|
| Interface Visual | ❌ | ✅ |
| Métricas | ❌ | ✅ |
| Auto-scaling | ❌ | ✅ |
| Tags | ❌ | ✅ |
| Balanceamento | Manual | Automático |
| Notificações | ❌ | ✅ |
| Dashboard | ❌ | ✅ |

## Links Úteis

- [Documentação Oficial](https://laravel.com/docs/11.x/horizon)
- [Customização de Interface](https://laravel.com/docs/11.x/horizon#dashboard-customization)
- [Deployment](https://laravel.com/docs/11.x/horizon#deploying-horizon)
