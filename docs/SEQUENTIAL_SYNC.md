# Sincronização Sequencial de Vendas

## Problema Identificado

Os jobs de sincronização de vendas estão sendo executados em **paralelo** por múltiplos workers do Horizon. Isso causa:

- **Logs misturados** entre diferentes clientes/lojas
- **Difícil rastreamento** de qual job pertence a qual cliente
- **Jobs excedendo tentativas máximas** devido a condições de corrida
- **Processamento simultâneo** pode causar locks no banco de dados

## Solução Implementada

Foi adicionado um modo **sequencial** usando Laravel's **Job Chaining**. Com ele, cada job aguarda o anterior terminar antes de iniciar.

## Como Usar

### Modo Paralelo (Padrão)
```bash
# Processa todos os clientes/lojas simultaneamente (comportamento original)
docker compose exec app php artisan sync:sales --from=365
```

### Modo Sequencial (Novo)
```bash
# Processa um cliente/loja por vez em ordem
docker compose exec app php artisan sync:sales --from=365 --sequential
```

## Comparação

| Aspecto | Paralelo | Sequencial |
|---------|----------|------------|
| **Velocidade** | ⚡ Mais rápido | 🐢 Mais lento |
| **Logs** | 🔀 Misturados | ✅ Organizados |
| **Rastreamento** | ❌ Difícil | ✅ Fácil |
| **Erros** | ⚠️ Condições de corrida | ✅ Mais estável |
| **Uso de recursos** | 🔥 Alto | 💚 Moderado |

## Quando Usar Cada Modo

### Use Paralelo quando:
- Precisa de velocidade máxima
- Tem infraestrutura robusta (CPU/RAM/DB)
- Não precisa rastrear logs facilmente
- Sincronização incremental (poucos dias)

### Use Sequencial quando:
- Precisa rastrear cada cliente/loja
- Está debugando problemas
- Sincronização histórica (muitos dias)
- Recursos limitados
- Quer evitar deadlocks/timeouts

## Configuração do Horizon

Para reduzir o paralelismo no modo padrão, você pode ajustar o Horizon:

```php
// config/horizon.php

'environments' => [
    'local' => [
        'supervisor-1' => [
            'maxProcesses' => 1, // Reduzir de 3 para 1
        ],
    ],
],
```

## Exemplos de Uso

```bash
# Sincronizar último mês em modo sequencial
docker compose exec app php artisan sync:sales --from=30 --sequential

# Sincronizar cliente específico em modo sequencial
docker compose exec app php artisan sync:sales --client=01ABC123 --sequential

# Limpar vendas e reprocessar tudo sequencialmente
docker compose exec app php artisan sync:sales --truncate --from=365 --sequential
```

## Vantagens do Modo Sequencial

1. **Logs Claros**: Cada cliente/loja tem seus logs agrupados
2. **Melhor Debugging**: Fácil identificar onde ocorreu um erro
3. **Menos Falhas**: Reduz condições de corrida e timeouts
4. **Previsibilidade**: Sabe exatamente qual job está rodando
5. **Menos Memória**: Não processa múltiplos datasets simultaneamente

## Implementação Técnica

O modo sequencial usa `Bus::chain()` para encadear jobs:

```php
Bus::chain([
    new SysmoDiscoverJob($client1, $store1, $config1),
    new SysmoDiscoverJob($client1, $store2, $config2),
    new VisaoDiscoverJob($client2, $store1, $config3),
    // ...
])->dispatch();
```

Cada job espera o anterior completar antes de executar. Se um job falhar, os próximos não são executados (por padrão).

## Monitoramento

Você pode acompanhar o progresso em tempo real:

```bash
# Ver logs em tempo real
docker compose exec app php artisan pail

# Ver fila no Horizon
# Acesse: http://localhost/horizon
```

## Notas Importantes

- Jobs individuais (`DiscoverIntegrationSaleJob`) continuam processando dias em paralelo internamente
- O sequencial se aplica apenas ao nível de cliente/loja
- `ProcessIntegrationDataJob` ainda roda em paralelo para diferentes páginas/dias
- Se um job na cadeia falhar, os próximos não executam (falha rápida)
