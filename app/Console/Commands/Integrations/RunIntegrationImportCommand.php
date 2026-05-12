<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\TenantIntegration;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RunIntegrationImportCommand extends Command
{
    protected $signature = 'integration:run
                            {--tenant= : ID do tenant específico}
                            {--type= : Slug da integração (ex: sysmo)}
                            {--list : Apenas lista as integrações ativas sem executar}
                            {--chunk-days=7 : Tamanho do chunk de dias para paths com date range}';

    protected $description = 'Busca e executa importação de dados via integrações ativas';

    public function handle(): int
    {
        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada.');

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            $this->listIntegrations($integrations);

            return self::SUCCESS;
        }

        $this->info(sprintf('Encontradas %d integração(ões) ativa(s).', $integrations->count()));
        $this->newLine();

        foreach ($integrations as $integration) {
            $this->processIntegration($integration);
        }

        $this->newLine();
        $this->info('Jobs despachados na queue [imports]. Acompanhe o progresso no Horizon.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, TenantIntegration>
     */
    protected function getActiveIntegrations(): Collection
    {
        $query = TenantIntegration::query()
            ->with('api')
            ->where('is_active', true)
            ->whereHas('api', fn ($q) => $q->where('is_active', true));

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        $type = $this->option('type');
        if (is_string($type) && $type !== '') {
            $query->whereHas('api', fn ($q) => $q->where('slug', $type));
        }

        return $query->get();
    }

    /**
     * @param  Collection<int, TenantIntegration>  $integrations
     */
    protected function listIntegrations(Collection $integrations): void
    {
        $rows = $integrations->map(fn (TenantIntegration $integration): array => [
            $integration->id,
            $integration->tenant?->name ?? '-',
            $integration->api?->name ?? '-',
            $integration->api?->slug ?? '-',
            $integration->last_sync?->toDateTimeString() ?? 'Nunca',
        ])->all();

        $this->table(
            ['ID', 'Tenant Name', 'Integração', 'Slug', 'Último Sync'],
            $rows,
        );
    }

    protected function processIntegration(TenantIntegration $integration): void
    {
        $api = $integration->api;

        if ($api === null) {
            $this->error("   API não encontrada para integração {$integration->id}");

            return;
        }

        $this->info("Integração: {$api->name} | Tenant: {$integration->tenant_id}");

        $paths = data_get($api->requests ?? [], 'paths', []);
        $chunkDays = max(1, (int) $this->option('chunk-days'));

        foreach ($paths as $pathKey => $pathConfig) {
            $this->dispatchPathJobs($integration, (string) $pathKey, (array) $pathConfig, $chunkDays);
        }

        $this->newLine();
    }

    /**
     * @param  array<string, mixed>  $pathConfig
     */
    private function dispatchPathJobs(
        TenantIntegration $integration,
        string $pathKey,
        array $pathConfig,
        int $chunkDays,
    ): void {
        $dateFields = data_get($pathConfig, 'date_fields', []);
        $initialDays = (int) data_get($pathConfig, 'initial_days', 5);

        $hasDateRange = isset($dateFields['start']) && isset($dateFields['end']);

        if ($hasDateRange) {
            $chunks = $this->buildDateChunks(
                start: now()->subDays($initialDays),
                end: now(),
                chunkDays: $chunkDays,
            );

            foreach ($chunks as $chunk) {
                DiscoverIntegrationPagesJob::dispatch(
                    $integration->id,
                    $pathKey,
                    $chunk['start'],
                    $chunk['end'],
                );
            }

            $this->line(sprintf(
                '   [%s] %d job(s) de descoberta (%d dias em chunks de %d)',
                $pathKey,
                count($chunks),
                $initialDays,
                $chunkDays,
            ));

            return;
        }

        DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey);

        $this->line(sprintf('   [%s] 1 job de descoberta', $pathKey));
    }

    /**
     * @return array<int, array{start: string, end: string}>
     */
    private function buildDateChunks(CarbonInterface $start, CarbonInterface $end, int $chunkDays): array
    {
        $chunks = [];
        $current = $start->copy();

        while ($current->lessThan($end)) {
            $chunkEnd = $current->copy()->addDays($chunkDays - 1)->min($end);

            $chunks[] = [
                'start' => $current->toDateString(),
                'end' => $chunkEnd->toDateString(),
            ];

            $current = $current->addDays($chunkDays);
        }

        return $chunks;
    }
}
