<?php

namespace App\Console\Commands\Integrations;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncLayerProductIdsFromLegacyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('sync:layers-product-ids-from-legacy {--tenant= : ID do tenant específico} {--preview : Apenas mostra o que seria atualizado} {--only-invalid-count : Apenas conta layers inválidas por tenant}')]
#[Description('Corrige layers.product_id inválido usando EAN da base legada')]
class SyncLayerProductIdsFromLegacyCommand extends Command
{
    public function handle(SyncLayerProductIdsFromLegacyService $service): int
    {
        $onlyInvalidCount = (bool) $this->option('only-invalid-count');

        if (! $onlyInvalidCount && ! $this->legacyConnectionIsAvailable()) {
            $this->error('Conexão [mysql_legacy] indisponível.');

            return self::FAILURE;
        }

        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $preview = (bool) $this->option('preview');
        if ($preview) {
            $this->info('MODO PREVIEW - Nenhuma atualização será persistida.');
        }
        if ($onlyInvalidCount) {
            $this->info('MODO CONTAGEM - Apenas layers com product_id inválido.');
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $preview, $onlyInvalidCount, $service);
        }

        $this->info('Sincronização de layers.product_id concluída.');

        return self::SUCCESS;
    }

    private function legacyConnectionIsAvailable(): bool
    {
        try {
            DB::connection('mysql_legacy')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function getTenants(): Collection
    {
        $query = Tenant::query()->where('status', 'active');

        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        return $query->get(['id', 'name', 'database']);
    }

    private function processTenant(Tenant $tenant, bool $preview, bool $onlyInvalidCount, SyncLayerProductIdsFromLegacyService $service): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $tenantConnection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database'))
            ? trim((string) $tenant->getAttribute('database'))
            : '';

        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->warn(sprintf('Tenant %s sem database configurado; sincronização ignorada.', $tenant->id));

            return;
        }

        $run = function () use ($service, $tenantConnection, $tenant, $preview, $onlyInvalidCount): void {
            if ($onlyInvalidCount) {
                $invalidLayers = $service->countInvalidLayers(
                    tenantConnectionName: $tenantConnection,
                    tenantId: (string) $tenant->id,
                );

                $this->line(sprintf('%s: inválidas=%d', $tenant->name, $invalidLayers));

                return;
            }

            $summary = $service->sync(
                tenantConnectionName: $tenantConnection,
                legacyConnectionName: 'mysql_legacy',
                tenantId: (string) $tenant->id,
                preview: $preview,
            );

            $this->line(sprintf(
                '%s: inválidas=%d, restaurados=%d, legacy=%d, tenant=%d, atualizadas=%d, sem_legacy=%d, sem_tenant=%d',
                $tenant->name,
                $summary['invalid_layers'],
                $summary['restored_products'],
                $summary['legacy_matched'],
                $summary['tenant_matched'],
                $summary['updated'],
                $summary['unresolved_legacy'],
                $summary['unresolved_tenant'],
            ));
        };

        if ($shouldSwitchTenantContext) {
            $tenant->execute($run);

            return;
        }

        $run();
    }
}
