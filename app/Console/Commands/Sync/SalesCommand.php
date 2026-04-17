<?php

/**
 * Comando para sincronização de vendas da API externa com análise de lacunas.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Sync;

use App\Console\Commands\Sync\Concerns\IntegrationConfigTrait;
use App\Jobs\Sync\Sysmo\DiscoverIntegrationSaleJob as SysmoDiscoverJob;
use App\Jobs\Sync\Visao\DiscoverIntegrationSaleJob as VisaoDiscoverJob;
use App\Models\Client;
use App\Models\ClientIntegration;
use App\Models\Store;
use App\Models\User;
use App\Notifications\SalesSyncStartedNotification;
use App\Services\Sync\IntegrationCircuitBreaker;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SalesCommand extends Command
{
    use \App\Concerns\BelongsToConnection;
    use IntegrationConfigTrait;

    protected $signature = 'sync:sales 
                            {--client= : ID do cliente específico para sincronizar}
                            {--from= : Data inicial (Y-m-d) ou número de dias atrás}
                            {--truncate : Limpar vendas antes de sincronizar}
                            {--skip-complete : Pula clientes com histórico 100% completo}
                            {--preview : Exibe resumo do que seria processado sem executar}
                            {--debug-config : Exibe a configuração de integração sem executar}';

    protected $description = 'Sincroniza vendas da API externa com análise de lacunas';

    protected array $historyCache = [];

    public function handle(): int
    {
        if (! $this->validateOptions()) {
            return self::FAILURE;
        }

        if ($this->option('debug-config')) {
            return $this->handleDebugConfig();
        }

        if ($this->option('truncate')) {
            $this->handleTruncate();
        }

        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->warn('⚠️  Nenhum cliente encontrado.');

            return self::SUCCESS;
        }

        $this->displaySummary($clients);

        if ($this->option('preview')) {
            $this->displayPreview($clients);

            return self::SUCCESS;
        }

        $this->sendSalesSyncStartedNotification($clients);
        $this->processClients($clients);
        $this->info('✅ Sincronização concluída.');

        return self::SUCCESS;
    }

    /**
     * Envia notificação (database + broadcast) de início do sync:sales.
     *
     * @param  Collection<int, Client>  $clients
     */
    protected function sendSalesSyncStartedNotification(Collection $clients): void
    {
        try {
            $users = User::all();
            if ($users->isEmpty()) {
                return;
            }
            $clientNames = $clients->pluck('name')->values()->all();
            $notification = new SalesSyncStartedNotification($clients->count(), $clientNames);
            foreach ($users as $user) {
                $user->notify($notification);
            }
            Log::info('Notificação de início do sync:sales enviada', [
                'clients_count' => $clients->count(),
                'users_count' => $users->count(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar notificação de início do sync:sales', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Valida as opções do comando
     */
    protected function validateOptions(): bool
    {
        if ($from = $this->option('from')) {
            if (is_numeric($from)) {
                $days = (int) $from;
                if ($days < 1 || $days > 3650) {
                    $this->error('❌ Dias deve estar entre 1 e 3650');

                    return false;
                }
            } else {
                try {
                    \Carbon\Carbon::parse($from);
                } catch (\Exception $e) {
                    $this->error("❌ Data inválida: {$from}");

                    return false;
                }
            }
        }

        if ($clientId = $this->option('client')) {
            if (! Client::find($clientId)) {
                $this->error("❌ Cliente {$clientId} não encontrado.");

                return false;
            }
        }

        return true;
    }

    /**
     * Exibe configuração de integração para debug
     */
    protected function handleDebugConfig(): int
    {
        $clients = $this->getClients();

        if ($clients->isEmpty()) {
            $this->error('❌ Nenhum cliente encontrado.');

            return self::FAILURE;
        }

        foreach ($clients as $client) {
            $this->debugIntegrationConfig($client);

            // Exibir configuração preparada para cada loja
            $integration = $client->client_integration;
            $stores = $client->storesDocument;

            if ($integration && $stores && $stores->isNotEmpty()) {
                foreach ($stores as $store) {
                    $this->displayPreparedConfig($client, $store, $integration);
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * Exibe a configuração preparada que seria enviada para o job
     */
    protected function displayPreparedConfig(Client $client, Store $store, ClientIntegration $integration): void
    {
        $this->newLine();
        $document = preg_replace('/\D/', '', $store->document);
        $this->info("   🔧 Config para {$store->name} ({$document}):");

        $config = $this->normalizeArray($integration->config ?? []);
        $dataInicialName = data_get($config, 'data_inicial_name', 'data_inicial');
        $dataFinalName = data_get($config, 'data_final_name', 'data_final');

        $extraBody = [
            $dataInicialName => '2026-01-01',
            $dataFinalName => '2026-01-01',
        ];

        $prepared = $this->prepareBaseIntegrationConfig($client, $store, $integration, $extraBody);

        $this->line("      base_url: {$prepared['base_url']}");
        $this->line('      auth: '.($prepared['headers']['authorization']['username'] ?? '<null>'));
        $this->line('      body: '.json_encode($prepared['body'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Exibe resumo da execução
     */
    protected function displaySummary(Collection $clients): void
    {
        $this->newLine();
        $this->info('📊 RESUMO');
        $this->info("   Clientes: {$clients->count()}");

        if ($from = $this->option('from')) {
            $this->info("   Desde: {$this->getFromDate()}");
        }

        if ($this->option('skip-complete')) {
            $this->info('   Modo: Pulando completos');
        }
        $this->newLine();
    }

    /**
     * Exibe preview detalhado
     */
    protected function displayPreview(Collection $clients): void
    {
        $this->info('👁️  PREVIEW - Nenhuma ação será executada');
        $this->newLine();

        $totalJobs = 0;

        foreach ($clients as $client) {
            $integration = $client->client_integration;
            $stores = $client->storesDocument;

            if (! $integration || ! $stores || $stores->isEmpty()) {
                continue;
            }

            $this->info("🏢 {$client->name} ({$integration->integration_type})");

            foreach ($stores as $store) {
                $this->configureTenantContext($client);

                if (! $this->getClientConnection()) {
                    continue;
                }

                $strategy = $this->determineSyncStrategy($client, $store, $integration);

                if ($this->shouldSkipStore($strategy)) {
                    continue;
                }

                $datesCount = count($strategy['dates_to_process']);
                $totalJobs += $datesCount;

                $document = preg_replace('/\D/', '', $store->document);
                $this->line("   📍 {$store->name} ({$document})");
                $this->line("      Modo: {$strategy['sync_mode']} | Datas: {$datesCount}");
                $this->line("      Completude: {$strategy['history_analysis']['completeness_percentage']}%");
            }
        }

        $this->newLine();
        $this->info("📦 Total de jobs: {$totalJobs}");
    }

    /**
     * Processa todos os clientes
     */
    protected function processClients(Collection $clients): void
    {
        $jobs = collect();

        foreach ($clients as $client) {
            $clientJobs = $this->processClient($client);
            $jobs = $jobs->concat($clientJobs);
        }

        if ($jobs->isEmpty()) {
            $this->warn('⚠️  Nenhum job preparado.');

            return;
        }

        $this->info("📋 Jobs: {$jobs->count()}");
        Bus::chain($jobs->toArray())->dispatch();
        $this->info('✓ Jobs despachados');
    }

    /**
     * Processa um cliente específico
     */
    protected function processClient(Client $client): Collection
    {
        $jobs = collect();

        $this->info("🔍 {$client->name}");

        $this->configureTenantContext($client);

        if (! $this->getClientConnection()) {
            $this->error('   ❌ Falha na conexão');

            return $jobs;
        }

        $integration = $client->client_integration;

        if (! $integration) {
            $this->warn('   ⚠️  Sem integração');

            return $jobs;
        }

        $stores = $client->storesDocument;

        if (! $stores || $stores->isEmpty()) {
            $this->warn('   ⚠️  Sem lojas');

            return $jobs;
        }

        $this->info("   ✓ {$integration->integration_type} | Lojas: {$stores->count()}");

        foreach ($stores as $store) {
            $storeJobs = $this->processStore($client, $store, $integration);
            $jobs = $jobs->concat($storeJobs);
        }

        return $jobs;
    }

    /**
     * Processa uma loja específica
     */
    protected function processStore(Client $client, Store $store, ClientIntegration $integration): Collection
    {
        $jobs = collect();
        $document = preg_replace('/\D/', '', $store->document);

        // Verificar Circuit Breaker
        if (IntegrationCircuitBreaker::isOpen($client->id, $store->id, $integration->integration_type)) {
            $this->warn("   📍 {$store->name} - 🔴 Circuit Breaker aberto");

            return $jobs;
        }

        $strategy = $this->determineSyncStrategy($client, $store, $integration);

        if ($this->shouldSkipStore($strategy)) {
            $this->line("   📍 {$store->name} - ⏭️  Completo");

            return $jobs;
        }

        if (empty($strategy['dates_to_process'])) {
            $this->line("   📍 {$store->name} - ⏭️  Sem datas");

            return $jobs;
        }

        $baseConfig = $this->prepareSalesConfig($client, $store, $integration, $strategy);

        foreach ($strategy['dates_to_process'] as $date) {
            $dateConfig = array_merge($baseConfig, [
                'single_date' => $date,
                'dates_to_process' => [$date],
            ]);

            if ($job = $this->createSyncJob($client, $store, $integration, $dateConfig)) {
                $jobs->push($job);
            }
        }

        $datesCount = count($strategy['dates_to_process']);
        $this->line("   📍 {$store->name} ({$document}) - ✓ {$datesCount} jobs");

        return $jobs;
    }

    /**
     * Prepara configuração específica para vendas
     */
    protected function prepareSalesConfig(
        Client $client,
        Store $store,
        ClientIntegration $integration,
        array $strategy
    ): array {
        $config = $this->normalizeArray($integration->config ?? []);

        $dataInicialName = data_get($config, 'data_inicial_name', 'data_inicial');
        $dataFinalName = data_get($config, 'data_final_name', 'data_final');
        $dataDaVendaName = data_get($config, 'data_da_venda_name');

        $extraBody = [
            $dataInicialName => $strategy['start_date'],
            $dataFinalName => $strategy['end_date'],
        ];

        if ($dataDaVendaName) {
            $extraBody[$dataDaVendaName] = $strategy['start_date'];
        }

        $prepared = $this->prepareBaseIntegrationConfig($client, $store, $integration, $extraBody);

        return array_merge($prepared, [
            'dates_to_process' => $strategy['dates_to_process'],
            'sync_mode' => $strategy['sync_mode'],
            'history_analysis' => $strategy['history_analysis'],
        ]);
    }

    /**
     * Determina a estratégia de sincronização
     */
    protected function determineSyncStrategy(Client $client, Store $store, ClientIntegration $integration): array
    {
        $config = $this->normalizeArray($integration->config ?? []);
        $periodoConfig = data_get($config, 'periodo', 365);

        $periodInfo = $this->calculatePeriod($periodoConfig);
        $fromDate = $this->getFromDate();

        $connection = $this->getClientConnection();

        if (! $connection) {
            return $this->defaultStrategy($periodInfo);
        }

        $salesQuery = DB::connection($connection)
            ->table('sales')
            ->where('client_id', $client->id);

        $existingSalesCount = $salesQuery->count();

        // Sempre usa o período completo para análise de lacunas
        $periodStartDate = $periodInfo['start_date'];
        $endDate = now()->format('Y-m-d');

        // Determinar modo e período de análise
        if ($fromDate) {
            // Modo customizado: usa data específica
            $startDate = $fromDate;
            $syncMode = 'custom_period';
        } elseif ($existingSalesCount === 0) {
            // Setup inicial: sem vendas, busca período completo
            $startDate = $periodStartDate;
            $syncMode = 'initial_setup';
        } else {
            // Modo incremental com verificação de lacunas
            // Analisa o período completo para encontrar lacunas
            $startDate = $periodStartDate;
            $syncMode = 'gap_fill';
        }

        // Analisar histórico do período completo
        $historyAnalysis = $this->analyzeSalesHistory($client, $store, $startDate, $endDate);

        // Determinar datas a processar
        $datesToProcess = $historyAnalysis['missing_dates'];

        // Se não há lacunas e não é setup inicial, pode ser incremental puro
        if (empty($datesToProcess) && $syncMode === 'gap_fill') {
            $syncMode = 'incremental';
            // Buscar apenas a data de hoje se não houver lacunas
            $today = now()->format('Y-m-d');
            if (! in_array($today, $historyAnalysis['existing_dates']->toArray())) {
                $datesToProcess = [$today];
            }
        }

        return [
            'sync_mode' => $syncMode,
            'dates_to_process' => $datesToProcess,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'history_analysis' => $historyAnalysis,
            'period_days' => $periodInfo['days'],
        ];
    }

    /**
     * Estratégia padrão quando não há conexão
     */
    protected function defaultStrategy(array $periodInfo): array
    {
        $dates = $this->generateDateRange($periodInfo['start_date'], now()->format('Y-m-d'));

        return [
            'sync_mode' => 'initial_setup',
            'dates_to_process' => $dates,
            'start_date' => $periodInfo['start_date'],
            'end_date' => now()->format('Y-m-d'),
            'history_analysis' => [
                'existing_dates' => collect(),
                'missing_dates' => $dates,
                'first_sale_date' => null,
                'last_sale_date' => null,
                'total_days_with_sales' => 0,
                'expected_days' => count($dates),
                'completeness_percentage' => 0,
                'missing_count' => count($dates),
            ],
            'period_days' => $periodInfo['days'],
        ];
    }

    /**
     * Calcula período baseado na configuração
     */
    protected function calculatePeriod($periodoConfig): array
    {
        $periodoEmDias = null;
        $dataInicialCustom = null;

        if (is_string($periodoConfig) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodoConfig)) {
            try {
                $dataInicio = \Carbon\Carbon::parse($periodoConfig);
                if ($dataInicio->lte(now())) {
                    $dataInicialCustom = $dataInicio->format('Y-m-d');
                    $periodoEmDias = (int) $dataInicio->diffInDays(now());
                }
            } catch (\Exception $e) {
                $periodoEmDias = 365;
            }
        } else {
            $periodoEmDias = (int) $periodoConfig;
        }

        return [
            'days' => $periodoEmDias,
            'start_date' => $dataInicialCustom ?: now()->subDays($periodoEmDias)->format('Y-m-d'),
            'custom_date' => $dataInicialCustom,
        ];
    }

    /**
     * Determina quais datas processar
     */
    protected function determineDatesToProcess(string $syncMode, string $startDate, string $endDate, array $historyAnalysis): array
    {
        if (in_array($syncMode, ['initial_setup', 'custom_period'])) {
            return $this->generateDateRange($startDate, $endDate);
        }

        if ($syncMode === 'gap_fill') {
            return $historyAnalysis['missing_dates'];
        }

        if ($syncMode === 'incremental') {
            $dateRange = $this->generateDateRange($startDate, $endDate);
            $thirtyDaysAgo = now()->subDays(30);

            return array_filter($dateRange, function ($date) use ($thirtyDaysAgo) {
                return \Carbon\Carbon::parse($date)->gte($thirtyDaysAgo);
            });
        }

        return $this->generateDateRange($startDate, $endDate);
    }

    /**
     * Analisa histórico de vendas
     */
    protected function analyzeSalesHistory(Client $client, Store $store, string $startDate, string $endDate): array
    {
        $cacheKey = "{$client->id}_{$store->id}_{$startDate}_{$endDate}";

        if (isset($this->historyCache[$cacheKey])) {
            return $this->historyCache[$cacheKey];
        }

        $connection = $this->getClientConnection();

        if (! $connection) {
            $dates = $this->generateDateRange($startDate, $endDate);

            return [
                'existing_dates' => collect(),
                'missing_dates' => $dates,
                'first_sale_date' => null,
                'last_sale_date' => null,
                'total_days_with_sales' => 0,
                'expected_days' => count($dates),
                'completeness_percentage' => 0,
                'missing_count' => count($dates),
            ];
        }

        $salesQuery = DB::connection($connection)
            ->table('sales')
            ->where('client_id', $client->id);

        $existingDates = $salesQuery
            ->selectRaw('DATE(sale_date) as date')
            ->distinct()
            ->orderBy('date')
            ->pluck('date');

        $firstSaleDate = $salesQuery->min('sale_date');
        $lastSaleDate = $salesQuery->max('sale_date');
        $totalDaysWithSales = $existingDates->count();

        $expectedDates = $this->generateDateRange($startDate, $endDate);
        $expectedDays = count($expectedDates);

        $existingDatesArray = $existingDates
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
            ->toArray();

        $missingDates = array_values(array_diff($expectedDates, $existingDatesArray));

        $completenessPercentage = $expectedDays > 0
            ? round(($totalDaysWithSales / $expectedDays) * 100, 2)
            : 0;

        $result = [
            'existing_dates' => $existingDates,
            'missing_dates' => $missingDates,
            'first_sale_date' => $firstSaleDate ? \Carbon\Carbon::parse($firstSaleDate)->format('Y-m-d') : null,
            'last_sale_date' => $lastSaleDate ? \Carbon\Carbon::parse($lastSaleDate)->format('Y-m-d') : null,
            'total_days_with_sales' => $totalDaysWithSales,
            'expected_days' => $expectedDays,
            'completeness_percentage' => $completenessPercentage,
            'missing_count' => count($missingDates),
        ];

        $this->historyCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Verifica se deve pular esta loja
     */
    protected function shouldSkipStore(array $strategy): bool
    {
        if (! $this->option('skip-complete')) {
            return false;
        }

        return $strategy['history_analysis']['completeness_percentage'] >= 100
            && $strategy['sync_mode'] === 'incremental';
    }

    /**
     * Cria job de sincronização
     */
    protected function createSyncJob(Client $client, Store $store, ClientIntegration $integration, array $config): ?object
    {
        return match ($integration->integration_type) {
            'sysmo' => new SysmoDiscoverJob(
                client: $client,
                store: $store,
                integration: $config,
                sequential: true
            ),
            'visao' => new VisaoDiscoverJob(
                client: $client,
                store: $store,
                integration: $config,
                sequential: true
            ),
            default => null
        };
    }

    /**
     * Gera array com datas entre duas datas
     */
    protected function generateDateRange(string $startDate, string $endDate): array
    {
        $dates = [];
        $current = \Carbon\Carbon::parse($startDate);
        $end = \Carbon\Carbon::parse($endDate);

        while ($current->lte($end)) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        return $dates;
    }

    /**
     * Calcula data inicial baseada em --from
     */
    protected function getFromDate(): ?string
    {
        $from = $this->option('from');

        if (! $from) {
            return null;
        }

        if (is_numeric($from)) {
            return now()->subDays((int) $from)->format('Y-m-d');
        }

        try {
            return \Carbon\Carbon::parse($from)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Trunca vendas
     */
    protected function handleTruncate(): void
    {
        $clientId = $this->option('client');

        if ($clientId) {
            $this->truncateClientSales($clientId);
        } else {
            $this->truncateAllSales();
        }
    }

    /**
     * Trunca vendas de um cliente específico
     */
    protected function truncateClientSales(string $clientId): void
    {
        $client = Client::find($clientId);

        if (! $client) {
            $this->error("❌ Cliente {$clientId} não encontrado.");

            return;
        }

        $this->configureTenantContext($client);

        $count = DB::connection($this->getClientConnection())
            ->table('sales')
            ->count();

        if ($count === 0) {
            $this->info("Nenhuma venda para '{$client->name}'.");

            return;
        }

        if (! $this->confirm("⚠️  Excluir {$count} vendas de '{$client->name}'?")) {
            $this->warn('Operação cancelada.');

            return;
        }

        DB::connection($this->getClientConnection())->table('sales')->delete();
        $this->info("✅ {$count} vendas excluídas de '{$client->name}'.");

        Log::info('Vendas truncadas', [
            'client_id' => $clientId,
            'count' => $count,
        ]);
    }

    /**
     * Trunca vendas de todos os clientes
     */
    protected function truncateAllSales(): void
    {
        $clients = $this->getClients();

        foreach ($clients as $client) {
            $this->truncateClientSales($client->id);
        }
    }
}
