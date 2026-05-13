<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Integrations\Support\SyncLayerProductsByEanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

use function Laravel\Prompts\search;

#[Signature('sync:layers-product-ids-by-ean {--tenant= : ID do tenant específico} {--preview : Apenas mostra o que seria feito}')]
#[Description('Atualiza product_id das layers via EAN, incluindo layers soft deleted')]
class SyncLayerProductIdsByEanCommand extends Command
{
    public function handle(SyncLayerProductsByEanService $service): int
    {
        $tenants = $this->getTenants();

        if ($tenants->isEmpty()) {
            $this->warn('Nenhum tenant ativo encontrado.');

            return self::SUCCESS;
        }

        $preview = (bool) $this->option('preview');
        if ($preview) {
            $this->info('MODO PREVIEW - Nenhuma atualização será executada');
        }

        $totals = [
            'matched_layers' => 0,
            'layers_updated' => 0,
            'products_restored' => 0,
        ];

        foreach ($tenants as $tenant) {
            $summary = $this->processTenant($tenant, $service, $preview);

            $totals['matched_layers'] += $summary['matched_layers'];
            $totals['layers_updated'] += $summary['layers_updated'];
            $totals['products_restored'] += $summary['products_restored'];

            $this->line(sprintf(
                '%s: %d layer(s) com EAN válido, %d layer(s) atualizada(s), %d produto(s) reativado(s).',
                $tenant->name,
                $summary['matched_layers'],
                $summary['layers_updated'],
                $summary['products_restored'],
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '✅ Total geral: %d layer(s) com match, %d layer(s) atualizada(s), %d produto(s) reativado(s).',
            $totals['matched_layers'],
            $totals['layers_updated'],
            $totals['products_restored'],
        ));

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

        $selectedTenantId = search(
            label: 'Selecione o tenant para sincronizar',
            options: fn (string $value) => Tenant::query()
                ->where('status', 'active')
                ->when($value !== '', function ($query) use ($value): void {
                    $query->where(function ($subQuery) use ($value): void {
                        $subQuery
                            ->where('name', 'like', "%{$value}%")
                            ->orWhere('slug', 'like', "%{$value}%");
                    });
                })
                ->orderBy('name')
                ->limit(50)
                ->pluck('name', 'id')
                ->toArray(),
            placeholder: 'Digite nome ou slug do tenant...',
        );

        if (! is_string($selectedTenantId) || $selectedTenantId === '') {
            return Tenant::query()
                ->whereRaw('1 = 0')
                ->get(['id', 'name', 'database']);
        }

        return Tenant::query()
            ->where('status', 'active')
            ->whereKey($selectedTenantId)
            ->get(['id', 'name', 'database']);
    }

    /**
     * @return array{matched_layers: int, layers_updated: int, products_restored: int}
     */
    private function processTenant(Tenant $tenant, SyncLayerProductsByEanService $service, bool $preview): array
    {
        $configuredTenantConnection = config('multitenancy.tenant_database_connection_name');
        $tenantConnection = (string) ($configuredTenantConnection ?: config('database.default'));
        $shouldSwitchTenantContext = is_string($configuredTenantConnection) && $configuredTenantConnection !== '';

        $tenantDatabase = is_string($tenant->getAttribute('database'))
            ? trim((string) $tenant->getAttribute('database'))
            : '';

        if ($shouldSwitchTenantContext && $tenantDatabase === '') {
            $this->warn(sprintf('Tenant %s sem database configurado; sincronização ignorada.', $tenant->id));

            return [
                'matched_layers' => 0,
                'layers_updated' => 0,
                'products_restored' => 0,
            ];
        }

        $run = fn (): array => $service->sync(
            tenantConnectionName: $tenantConnection,
            tenantId: (string) $tenant->id,
            preview: $preview,
        );

        if ($shouldSwitchTenantContext) {
            return $tenant->execute($run);
        }

        return $run();
    }
}
