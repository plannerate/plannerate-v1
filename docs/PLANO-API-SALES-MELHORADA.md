# Documentação: Sistema de Sincronização de Vendas com Análise de Gaps

## ✅ Status: IMPLEMENTADO

Sistema completo de sincronização inteligente de vendas com detecção automática de lacunas temporais, jobs granulares por data e modo preview para planejamento.

## 🎯 Resumo Executivo

O sistema de sincronização de vendas foi completamente redesenhado para garantir:
- **Integridade Temporal**: Detecção automática de datas faltantes no histórico
- **Granularidade**: Um job por data para controle preciso e retry inteligente
- **Visibilidade**: Modo preview para planejamento antes da execução
- **Resiliência**: Retry com exponential backoff e tratamento de erros robusto

### Arquivos Principais
- **`app/Console/Commands/Sync/SalesCommand.php`**: Command principal com análise de gaps
- **`app/Jobs/Sync/*/DiscoverIntegrationSaleJob.php`**: Jobs otimizados para data única
- **`app/Services/Api/BaseApiService.php`**: Retry inteligente com backoff exponencial
- **`app/Jobs/Sync/ProcessIntegrationDataJob.php`**: Processamento em lotes (chunking)

---

## 🚀 Funcionalidades Implementadas

### 1. Análise Inteligente de Histórico
- ✅ Consulta automática de vendas existentes por cliente/loja
- ✅ Identificação de datas com vendas vs datas esperadas
- ✅ Cálculo de completude do histórico (percentual)
- ✅ Detecção de gaps (lacunas temporais)
- ✅ Cache de análise para performance

### 2. Estratégia de Jobs por Data
- ✅ **Um job por data** em vez de um job com múltiplas datas
- ✅ Detecção automática de `single_date` nos jobs
- ✅ Modo otimizado sem loops desnecessários
- ✅ Fila sequencial com `Bus::chain()` para ordem cronológica

### 3. Modo Preview
- ✅ Flag `--preview` para visualização sem execução
- ✅ Exibe análise detalhada por cliente/loja
- ✅ Mostra total de jobs que seriam criados
- ✅ Lista exemplos de datas a processar

### 4. API Resilience
- ✅ Timeout aumentado (120s)
- ✅ Retry com exponential backoff (5 tentativas: 3s, 9s, 27s, 81s, 243s)
- ✅ Detecção de erros retriáveis (5xx, timeouts, connection issues)
- ✅ Logs estruturados de cada tentativa

### 5. Batch Processing
- ✅ Persistência em lotes de 1000 registros
- ✅ Evita erro de limite de parâmetros do PostgreSQL (65535)
- ✅ Transações por lote para integridade

---

## 💎 Vantagens da Estratégia "Um Job Por Data"

### 🎯 1. Controle Granular
**Antes:** Um job processava 30 dias → se falhar dia 15, perde tudo
**Agora:** 30 jobs (um por dia) → se falhar dia 15, só refaz aquele dia

### 🔄 2. Retry Inteligente
- Cada data tem seu próprio retry independente
- Não precisa reprocessar datas que já funcionaram
- Fácil identificar qual data específica está problemática
- Retry automático via Laravel Horizon

### 📊 3. Visibilidade no Horizon
```
Queue: sync-sales
- [Processing] 2025-12-28: Supermercado Bruda
- [Pending] 2025-12-29: Supermercado Bruda
- [Pending] 2025-12-30: Supermercado Bruda
- [Failed] 2025-12-25: Supermercado Michelon (retrying...)
```
Vê exatamente qual data está processando, qual falhou, quantas faltam.

### ⏸️ 4. Controle de Execução
- Pode pausar a fila entre datas
- Pode cancelar jobs de datas específicas
- Pode ajustar prioridades no Horizon
- Não trava por longos períodos

### 🐛 5. Debugging Facilitado
- Logs específicos por data
- Rastreamento de qual data causou erro
- Testes isolados por data
- Reprodução de problemas simplificada

### 📈 6. Performance Escalável
- Jobs menores = menos memória por job
- Distribuição natural entre workers
- Sem timeouts por processar muitas datas
- Processamento paralelo (se múltiplos workers)

### 🔍 7. Auditoria Precisa
```sql
-- Ver quais datas falharam
SELECT * FROM integration_sync_retries 
WHERE status = 'failed' 
ORDER BY sync_date;

-- Ver progresso de um período
SELECT sync_date, status, attempts 
FROM integration_sync_retries
WHERE sync_date BETWEEN '2025-12-01' AND '2025-12-31';
```

### ⚡ 8. Otimização de API
- Rate limiting mais eficiente (distribui chamadas)
- Backoff entre datas automático (fila sequencial)
- Menos chance de sobrecarregar API externa
- Retry só das datas que falharam

---

## 📋 Como Usar

### Comandos Disponíveis

```bash
# Preview (não executa, só mostra o plano)
docker compose exec app php artisan sync:sales --preview

# Últimos 3 dias (preview)
docker compose exec app php artisan sync:sales --from=3 --preview

# Executar últimos 7 dias
docker compose exec app php artisan sync:sales --from=7

# Executar cliente específico
docker compose exec app php artisan sync:sales --client=01JGHK2M8RZFP3DVDP6WJBZ5ZG

# Limpar dados antes de sincronizar
docker compose exec app php artisan sync:sales --from=30 --truncate

# Data customizada
docker compose exec app php artisan sync:sales --from=2025-01-01
```

### Exemplo de Output (Preview)

```
📊 RESUMO DA EXECUÇÃO
─────────────────────────────────────────
  Clientes a processar: 4
  Período customizado: desde 2025-12-28
─────────────────────────────────────────

👁️  MODO PREVIEW - Exibindo apenas o que seria processado
    📊 Analisando histórico de vendas...
       Período: 2025-12-28 até 2025-12-31
       Esperados: 4 dias
       Com vendas: 0 dias
       Faltantes: 4 dias
       Completude: 0%
       Datas faltantes: 2025-12-28, 2025-12-29, 2025-12-30, 2025-12-31
    🎯 Período Customizado - 4 datas a processar

📦 Total de jobs que seriam criados: 12 (um job por data)
📅 Total de datas a processar: 12

💡 Para executar de verdade, remova a opção --preview
```

---

## 🎓 Conceitos Implementados

### Modo de Operação Detectado Automaticamente

| Modo | Condição | Comportamento |
|------|----------|---------------|
| **initial_setup** | Sem vendas no banco | Sincroniza período completo desde `--from` |
| **incremental** | Com vendas, sem gaps | Sincroniza do último dia + 1 até hoje |
| **gap_fill** | Com vendas, com gaps | Sincroniza APENAS as datas faltantes |
| **custom_period** | Usuário passou `--from` | Sincroniza período específico + gaps |

### Gap Analysis (Análise de Lacunas)

```php
// O que o sistema faz automaticamente:
1. Busca MIN(sale_date) e MAX(sale_date) do banco
2. Gera array com TODAS as datas esperadas nesse período
3. Busca quais datas REALMENTE têm vendas (distinct sale_date)
4. Compara esperado vs real = identifica gaps
5. Adiciona gaps ao array de datas_to_process
```

**Resultado:** Nunca mais terá "buracos" no histórico de vendas!

---

## Objetivo Original (Mantido para Referência)

Implementar um mecanismo robusto que identifique, registre e sincronize datas faltantes no histórico de vendas, garantindo que não existam lacunas temporais nos dados importados.

---

## 📚 Documentação Técnica Detalhada

## 1. Análise Prévia do Histórico de Vendas ✅ IMPLEMENTADO

### 1.1 Consulta Inicial
Implementado em `analyzeSalesHistory()` no SalesCommand:

```php
// Consulta automática executada antes de qualquer sincronização
$historyAnalysis = $this->analyzeSalesHistory($client, $store, $startDate, $endDate);

// Retorna:
[
    'total_days_expected' => 30,        // dias no período
    'total_days_with_sales' => 28,      // dias que têm vendas
    'missing_dates' => ['2025-12-15', '2025-12-20'],  // gaps
    'completeness_percentage' => 93.33,  // 28/30 * 100
    'first_sale_date' => '2025-01-01',
    'last_sale_date' => '2025-12-31',
]
```

**Implementação real:**
- ✅ Consulta `sales` filtrando por `client_id` e `store_id`
- ✅ Identifica MIN e MAX de `sale_date`
- ✅ Mapeia datas distintas com vendas
- ✅ Cache de 5 minutos para performance

### 1.2 Dados Coletados
Implementado e expandido:
- ✅ Total de dias com vendas registradas
- ✅ Data inicial do histórico (primeira venda)
- ✅ Data final do histórico (última venda)
- ✅ Período total em dias
- ✅ Lista completa de datas com vendas (via `distinct sale_date`)
- ✅ **Percentual de completude** (novo)
- ✅ **Lista de datas faltantes** (novo)

---

## 2. Identificação de Lacunas Temporais ✅ IMPLEMENTADO

### 2.1 Construção da Linha do Tempo Esperada

Implementado em `determineSyncStrategy()` e `analyzeHistoryAndFindGaps()`:

```php
// Gera array completo de datas esperadas
$expectedDates = $this->generateDateRange($startDate, $endDate);
// ['2025-12-01', '2025-12-02', ..., '2025-12-31']

// Compara com datas reais que têm vendas
$missingDates = array_diff($expectedDates, $datesWithSales);
```

**Para Setup Inicial (sem vendas existentes):**
- ✅ Cria array sequencial desde `--from` até hoje
- ✅ Marca modo como `initial_setup`
- ✅ Não precisa verificar gaps (tudo é novo)

**Para Sincronização Incremental (com vendas existentes):**
- ✅ Cria array completo do período
- ✅ Compara com `SELECT DISTINCT sale_date`
- ✅ Identifica gaps automaticamente
- ✅ Muda modo para `gap_fill` se encontrar lacunas

### 2.2 Detecção de Dias Faltantes ✅ IMPLEMENTADO

Método `analyzeHistoryAndFindGaps()` executa:
- ✅ Itera sobre cada dia do período esperado
- ✅ Verifica presença via `in_array($date, $datesWithSales)`
- ✅ Marca ausentes como "missing_dates"
- ✅ Logs detalhados no modo preview

### 2.3 Categorização das Lacunas ⚠️ PARCIALMENTE IMPLEMENTADO

**Implementado:**
- ✅ Detecção de dias não sincronizados (via diff de arrays)
- ✅ Tracking via `integration_sync_retries` table

**Pendente (roadmap):**
- ⏳ Dias com falha de API (registrado mas precisa classificação)
- ⏳ Dias sem movimento legítimo (API retornou vazio)
- ⏳ Dias não processados (dados buscados mas não salvos)

---

## 3. Construção do Array de Datas ✅ IMPLEMENTADO

### 3.1 Montagem da Lista de Datas

Implementado em `determineDatesToProcess()`:

```php
protected function determineDatesToProcess(
    string $syncMode, 
    string $startDate, 
    string $endDate, 
    array $historyAnalysis
): array {
    return match($syncMode) {
        'initial_setup' => $this->generateDateRange($startDate, $endDate),
        'incremental' => $this->generateDateRange($startDate, $endDate),
        'gap_fill' => $historyAnalysis['missing_dates'],
        'custom_period' => array_unique(array_merge(
            $this->generateDateRange($startDate, $endDate),
            $historyAnalysis['missing_dates']
        )),
    };
}
```

**Para Setup Inicial:**
- ✅ Array sequencial de todas as datas
- ✅ Ordem cronológica ascendente
- ✅ Formato YYYY-MM-DD

**Para Sincronização Incremental:**
- ✅ Array contém APENAS datas faltantes (modo gap_fill)
- ✅ Priorização: mais antigas primeiro
- ✅ Metadados no objeto de estratégia

### 3.2 Parâmetros de Controle ✅ IMPLEMENTADO

Array `$strategy` contém:
```php
[
    'sync_mode' => 'gap_fill',              // ✅
    'dates_to_process' => ['2025-12-15'],   // ✅
    'start_date' => '2025-12-01',           // ✅
    'end_date' => '2025-12-31',             // ✅
    'history_analysis' => [...],            // ✅
    'period_days' => 31,                    // ✅
]
```

### 3.3 Otimizações ✅ IMPLEMENTADO (DE FORMA DIFERENTE)

**Estratégia Adotada: Jobs Granulares**
- ✅ **Um job por data** (não lotes de datas)
- ✅ Fila sequencial com `Bus::chain()` mantém ordem
- ✅ Sem limite máximo (cada job é pequeno)
- ✅ Priorização via ordem da fila
- ✅ Processamento paralelo se múltiplos workers

**Decisão de Design:**
Optamos por jobs menores e granulares em vez de agrupamento, pelos benefícios de retry e controle descritos na seção "Vantagens".

---

## 4. Sistema de Rastreamento e Logs ✅ IMPLEMENTADO

### 4.1 Estrutura Atual

**Tabela `integration_sync_retries`:**
```sql
- id (ULID)
- client_id
- store_id  
- integration_id
- sync_date (data sendo sincronizada)
- status (pending, processing, completed, failed)
- attempts (número de tentativas)
- last_attempt_at
- last_error
- metadata (JSON com detalhes)
- created_at, updated_at
```

**Logs via Laravel Log:**
- ✅ `Log::info()` para sucesso e progresso
- ✅ `Log::error()` para falhas com contexto completo
- ✅ Horizon registra todos os jobs (status, payload, exceções)

### 4.2 Níveis de Log ✅ IMPLEMENTADO

**INFO** - Implementado:
- ✅ Início do comando (resumo de clientes/período)
- ✅ Análise de histórico (completude, gaps encontrados)
- ✅ Preview detalhado por cliente/loja
- ✅ Total de jobs criados
- ✅ Jobs individuais: "Job de data única - modo otimizado"

**WARNING** - Implementado:
- ✅ APIs com retry (via BaseApiService)
- ✅ Datas sem vendas registradas no log

**ERROR** - Implementado:
- ✅ Falhas de API com stack trace
- ✅ Erros de processamento (chunking)
- ✅ Exception handling em jobs
- ✅ Retry automático via Laravel Queue

### 4.3 Monitoramento ✅ DISPONÍVEL

**Laravel Horizon:**
- ✅ Dashboard visual em `/horizon`
- ✅ Jobs em tempo real (pending, processing, completed, failed)
- ✅ Métricas de throughput e tempo de processamento
- ✅ Stack trace de falhas
- ✅ Retry manual de jobs falhados

**Queries úteis:**
```sql
-- Completude por cliente
SELECT 
    c.name,
    COUNT(DISTINCT s.sale_date) as dias_com_vendas,
    DATEDIFF(MAX(s.sale_date), MIN(s.sale_date)) + 1 as dias_esperados
FROM clients c
LEFT JOIN sales s ON s.client_id = c.id
GROUP BY c.id;

-- Jobs falhados por data
SELECT * FROM failed_jobs 
WHERE payload LIKE '%DiscoverIntegrationSaleJob%'
ORDER BY failed_at DESC;

-- Histórico de tentativas
SELECT * FROM integration_sync_retries
WHERE status = 'failed'
ORDER BY sync_date, attempts DESC;
```

---

## 5. Fluxo de Execução ✅ IMPLEMENTADO

### Implementação Real (SalesCommand::handle)

```php
1. ✅ Validar/processar parâmetros (--from, --client, --preview, --truncate)
2. ✅ Buscar clientes (getClients)
3. ✅ Para cada cliente:
   3.1. ✅ Buscar lojas com integrações ativas
   3.2. ✅ Para cada loja:
        - ✅ determineSyncStrategy() [pré-análise + gap detection]
        - ✅ analyzeSalesHistory() [consulta banco]
        - ✅ analyzeHistoryAndFindGaps() [compara esperado vs real]
        - ✅ determineDatesToProcess() [monta array final]
        - ✅ Se preview: displayDetailedPreview() [mostra, não executa]
        - ✅ Se não preview: prepareSyncJobs() [cria jobs]
4. ✅ Despachar jobs via Bus::chain() [fila sequencial]
5. ✅ Exibir resumo final
```

**Diferenças do plano original:**
- ✅ Modo preview adicional (não estava no plano)
- ✅ Jobs granulares por data (decisão de design)
- ✅ Análise de completude % (métrica adicional)

---

## 6. Tratamento de Cenários Especiais ✅ IMPLEMENTADO

### 6.1 Dias Sem Movimento Legítimo ✅
```php
// DiscoverIntegrationSaleJob
if (empty($data)) {
    Log::info('API retornou vazio para data', [
        'date' => $currentDate,
        'client' => $this->client->name,
        'store' => $this->store->name,
    ]);
    // Não marca como erro, simplesmente pula
    continue; // próxima data
}
```

### 6.2 Falhas Temporárias de API ✅
```php
// BaseApiService::makeRequest()
$attempt = 0;
$backoffDelays = [3, 9, 27, 81, 243]; // exponential backoff (segundos)

while ($attempt < $maxRetries) {
    try {
        $response = Http::timeout($timeout)->get($url);
        if ($response->successful()) return $response;
        
        if ($this->isRetryableError($response)) {
            sleep($backoffDelays[$attempt]);
            $attempt++;
            continue;
        }
        throw new Exception("Non-retryable error");
    } catch (ConnectionException $e) {
        sleep($backoffDelays[$attempt]);
        $attempt++;
    }
}
```
- ✅ Retry automático com backoff exponencial
- ✅ Detecção de erros retriáveis (5xx, timeouts)
- ✅ Limite de 5 tentativas
- ✅ Logs de cada tentativa

### 6.3 Dados Parciais ⚠️ PARCIALMENTE IMPLEMENTADO
```php
// ProcessIntegrationDataJob
foreach (array_chunk($sales, 1000) as $batch) {
    DB::transaction(function () use ($batch) {
        // Salva em lote dentro de transação
    });
}
```
- ✅ Processamento em lotes (evita parameter limit)
- ✅ Transações garantem atomicidade por lote
- ⏳ Falta: Tracking de quantos registros foram salvos vs recebidos

### 6.4 Mudanças de Configuração ⏳ PENDENTE
- ⏳ Detecção de mudança em `client_integrations`
- ⏳ Re-avaliação de datas com config antiga
- ⏳ Histórico de configurações usadas

---

## 7. Benefícios Alcançados ✅

### ✅ Integridade dos Dados
- Histórico completo via gap analysis
- Datas faltantes identificadas automaticamente
- Auditoria temporal via `sale_date` indexado

### ✅ Rastreabilidade
- Logs detalhados em `storage/logs/laravel.log`
- Horizon registra payload completo de cada job
- Stack traces de todas as exceções

### ✅ Confiabilidade  
- Retry automático com exponential backoff
- Recuperação de falhas via Horizon (manual ou automático)
- Alertas visuais no Horizon dashboard

### ✅ Manutenibilidade
- Logs estruturados com contexto (cliente, loja, data)
- Métricas claras (completude %, total jobs, datas faltantes)
- Preview mode para testar sem executar

---

## 8. Performance e Limites ✅ IMPLEMENTADO

### Otimizações Implementadas
- ✅ Cache de análise de histórico (5 minutos)
- ✅ Batch processing (1000 registros por lote)
- ✅ Jobs pequenos (uma data por job)
- ✅ Fila sequencial (evita sobrecarga na API)
- ✅ Índices em `sale_date`, `client_id`, `store_id`

### Controles de Limite
- ✅ Sem limite de datas (cada job é pequeno)
- ✅ Timeout de 120s por request de API
- ✅ 5 retries máximo por request
- ✅ Exponential backoff (rate limiting natural)
- ✅ Workers configuráveis via Horizon

### Escalabilidade
- ✅ Múltiplos workers processam jobs em paralelo
- ✅ Redis para fila distribuída
- ✅ PostgreSQL com índices otimizados
- ✅ Processamento assíncrono (não bloqueia interface)

---

## 🎯 Roadmap Futuro

### Melhorias Planejadas
- ⏳ Dashboard de completude por cliente (widget no admin)
- ⏳ Notificações automáticas (Slack/email) para falhas recorrentes  
- ⏳ Classificação inteligente de gaps (legítimo vs falha)
- ⏳ Histórico de configurações de integração
- ⏳ Modo de sincronização "smart" (prioriza datas recentes)
- ⏳ API endpoint para consultar status de sincronização

---

## 📖 Referências

- **Código fonte**: `app/Console/Commands/Sync/SalesCommand.php`
- **Jobs**: `app/Jobs/Sync/{Sysmo,Visao}/DiscoverIntegrationSaleJob.php`
- **API Service**: `app/Services/Api/BaseApiService.php`
- **Processing**: `app/Jobs/Sync/ProcessIntegrationDataJob.php`
- **Horizon**: http://localhost/horizon
- **Logs**: `storage/logs/laravel.log`