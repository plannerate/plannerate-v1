<?php

namespace App\Console\Commands\Integrations;

use App\Events\Tenant\IntegrationProcessFinished;
use App\Events\Tenant\IntegrationProcessStarted;
use App\Jobs\Integrations\DiscoverIntegrationPagesJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class RunIntegrationImportCommand extends Command
{
    /**
     * Lock compartilhado com integration:backfill: impede que os dois disparem
     * descobertas sobre os mesmos paths ao mesmo tempo (fan-out duplicado).
     */
    public const DISPATCH_LOCK_KEY = 'integrations:discovery-dispatch';

    /** TTL do lock em segundos — cobre com folga a janela de despacho. */
    public const DISPATCH_LOCK_SECONDS = 600;

    protected $signature = 'integration:run
        {--tenant= : Roda apenas a integração deste tenant (id). Sem isso, roda todas as ativas}';

    protected $description = 'Executa importação de dados via integrações ativas';

    public function handle(): int
    {
        $lock = self::acquireDispatchLock();

        if ($lock === null) {
            $this->error('Outro despacho de descoberta (integration:run ou integration:backfill) está em andamento; abortado.');

            return self::FAILURE;
        }

        try {
            return $this->dispatchDiscoveries();
        } finally {
            $lock->release();
        }
    }

    /** Tenta adquirir o lock de despacho; null quando já está em uso. */
    public static function acquireDispatchLock(): ?Lock
    {
        $lock = Cache::lock(self::DISPATCH_LOCK_KEY, self::DISPATCH_LOCK_SECONDS);

        return $lock->get() ? $lock : null;
    }

    private function dispatchDiscoveries(): int
    {
        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada.');

            return self::SUCCESS;
        }

        $this->info(sprintf('Integrações ativas: %d', $integrations->count()));

        $totalJobs = 0;
        $referenceDate = now()->toDateString();

        foreach ($integrations as $integration) {
            $tenantId = (string) $integration->tenant_id;
            $integrationId = (string) $integration->id;

            event(new IntegrationProcessStarted(
                tenantId: $tenantId,
                integrationId: $integrationId,
                resource: 'integration_import',
                referenceDate: $referenceDate,
            ));

            try {
                $totalJobs += $this->processIntegration($integration);

                event(new IntegrationProcessFinished(
                    tenantId: $tenantId,
                    integrationId: $integrationId,
                    resource: 'integration_import',
                    referenceDate: $referenceDate,
                    status: 'success',
                ));
            } catch (Throwable $exception) {
                event(new IntegrationProcessFinished(
                    tenantId: $tenantId,
                    integrationId: $integrationId,
                    resource: 'integration_import',
                    referenceDate: $referenceDate,
                    status: 'failed',
                    errorMessage: $exception->getMessage(),
                ));

                Log::error('RunIntegrationImportCommand: falha ao processar integração', [
                    'integration_id' => $integrationId,
                    'tenant_id' => $tenantId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $this->info(sprintf('Jobs de descoberta despachados: %d (queue: imports-fetch).', $totalJobs));

        return self::SUCCESS;
    }

    /** @return Collection<int, TenantIntegration> */
    private function getActiveIntegrations(): Collection
    {
        $tenantId = trim((string) $this->option('tenant'));

        return TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->where('is_active', true)
            ->when($tenantId !== '', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('api', fn ($q) => $q->where('is_active', true))
            ->get();
    }

    private function processIntegration(TenantIntegration $integration): int
    {
        $api = $integration->api;
        $paths = data_get($api->requests ?? [], 'paths', []);
        $dispatched = 0;

        foreach ($paths as $pathKey => $pathConfig) {
            $dispatched += $this->dispatchPathJobs($integration, (string) $pathKey, (array) $pathConfig);
        }

        $this->line(sprintf(' - %s (%s): %d job(s)', $api->name, (string) $integration->tenant_id, $dispatched));

        return $dispatched;
    }

    /** @param array<string, mixed> $pathConfig */
    private function dispatchPathJobs(TenantIntegration $integration, string $pathKey, array $pathConfig): int
    {
        $dateFields = data_get($pathConfig, 'date_fields', []);
        $hasDateRange = isset($dateFields['start']) && isset($dateFields['end']);

        // Resolução de datas é feita por loja no DiscoverIntegrationPagesJob
        if ($hasDateRange) {
            DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, null, now()->toDateString());

            return 1;
        }

        DiscoverIntegrationPagesJob::dispatch($integration->id, $pathKey, null, null);

        return 1;
    }
}
