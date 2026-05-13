<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncProductsFromEanReferencesService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\multiselect;

#[Signature('sync:products-from-ean-references {--tenant= : ID do tenant específico} {--preview : Apenas mostra o que seria feito}')]
#[Description('Padroniza produtos usando a tabela ean_references')]
class SyncProductsFromEanReferencesCommand extends Command
{
    public function handle(SyncProductsFromEanReferencesService $syncProductsFromEanReferencesService): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $preview = (bool) $this->option('preview');

        if ($preview) {
            $this->info('MODO PREVIEW - Nenhuma ação será executada');
        }

        foreach ($tenants as $tenant) {
            $this->processTenant($tenant, $preview, $syncProductsFromEanReferencesService);
        }

        $this->info('Padronização de produtos por EAN concluída.');

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function getTenants(): Collection
    {
        $tenantId = $this->option('tenant');

        if (is_string($tenantId) && $tenantId !== '') {
            return Tenant::query()
                ->where('status', 'active')
                ->whereKey($tenantId)
                ->get(['id', 'name', 'database']);
        }

        $allTenants = Tenant::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name', 'database']);

        if ($allTenants->isEmpty()) {
            return $allTenants;
        }

        $options = $allTenants->mapWithKeys(
            fn (Tenant $tenant): array => [(string) $tenant->id => $tenant->name]
        )->all();

        $selectedIds = multiselect(
            label: 'Selecione os tenants para processar:',
            options: $options,
            default: array_keys($options),
            hint: 'Pressione espaço para selecionar/deselecionar. Enter para confirmar.',
        );

        return $allTenants->whereIn('id', $selectedIds)->values();
    }

    private function processTenant(Tenant $tenant, bool $preview, SyncProductsFromEanReferencesService $syncProductsFromEanReferencesService): void
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $connection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database'))
            ? trim((string) $tenant->getAttribute('database'))
            : '';

        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->warn(sprintf('Tenant %s sem database configurado; padronização ignorada.', $tenant->id));

            return;
        }

        $process = function () use ($connection, $preview, $syncProductsFromEanReferencesService, $tenant): void {
            $summary = $syncProductsFromEanReferencesService->sync(
                tenantConnectionName: $connection,
                tenantId: (string) $tenant->id,
                preview: $preview,
            );

            $this->line(sprintf(
                '%s: %d produto(s) com referência, %d atualizado(s), %d pendente(s).',
                $tenant->name,
                $summary['matched'],
                $summary['updated'],
                $summary['remaining'],
            ));
        };

        if ($shouldSwitchTenantContext) {
            $tenant->execute($process);

            return;
        }

        $process();
    }
}
