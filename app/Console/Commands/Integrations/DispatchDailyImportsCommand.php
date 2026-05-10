<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Imports\ImportProductsJob;
use App\Jobs\Integrations\Imports\ImportSalesJob;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('integrations:daily-imports {--type=all : Tipo de importação: all, sales ou products} {--clear : Limpa tabelas antes do dispatch respeitando o type}')]
#[Description('Inicia a busca diária de produtos e vendas para integrações ativas')]
class DispatchDailyImportsCommand extends Command
{
    public function handle(): int
    {
        $types = $this->resolveTypes();
        if ($types === null) {
            return self::FAILURE;
        }

        $integrations = TenantIntegration::query()
            ->with(['tenant:id,name,slug,status,database'])
            ->where('is_active', true)
            ->orderBy('tenant_id')
            ->get(['id', 'tenant_id', 'integration_type', 'identifier', 'is_active', 'last_sync']);

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma integração ativa encontrada para a busca diária.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('clear')) {
            $this->clearTablesForActiveIntegrations($integrations, $types);
        }

        $this->info(sprintf(
            'Integrações ativas encontradas para importação diária: %d',
            $integrations->count(),
        ));

        $dispatches = [];

        foreach ($integrations as $integration) {
            if (in_array('sales', $types, true)) {
                ImportSalesJob::dispatch((string) $integration->id);

                $dispatches[] = [
                    (string) $integration->id,
                    $this->tenantLabel($integration),
                    'sales',
                    'provider_adapter',
                ];
            }

            if (in_array('products', $types, true)) {
                ImportProductsJob::dispatch((string) $integration->id);

                $dispatches[] = [
                    (string) $integration->id,
                    $this->tenantLabel($integration),
                    'products',
                    'provider_adapter',
                ];
            }
        }

        $this->table(
            ['Integração', 'Tenant', 'Status tenant', 'Tipo', 'Identificador', 'Última sync'],
            $integrations->map(fn (TenantIntegration $integration): array => [
                (string) $integration->id,
                $this->tenantLabel($integration),
                (string) ($integration->tenant?->status ?? '-'),
                (string) $integration->integration_type,
                (string) ($integration->identifier ?: '-'),
                $integration->last_sync?->toDateTimeString() ?? '-',
            ])->all(),
        );

        $this->table(
            ['Integração', 'Tenant', 'Job', 'Responsável'],
            $dispatches,
        );

        return self::SUCCESS;
    }

    /**
     * @return list<'sales'|'products'>|null
     */
    private function resolveTypes(): ?array
    {
        $type = strtolower((string) $this->option('type'));

        return match ($type) {
            'all', '' => ['sales', 'products'],
            'sales' => ['sales'],
            'products' => ['products'],
            default => tap(null, fn () => $this->error('Tipo inválido. Use: all, sales ou products.')),
        };
    }

    private function tenantLabel(TenantIntegration $integration): string
    {
        if ($integration->tenant === null) {
            return sprintf('Tenant não encontrado (%s)', $integration->tenant_id);
        }

        return sprintf('%s (%s)', $integration->tenant->name, $integration->tenant->slug);
    }

    /**
     * @param  list<'sales'|'products'>  $types
     */
    private function clearTablesForActiveIntegrations($integrations, array $types): void
    {
        $tenants = $integrations
            ->pluck('tenant')
            ->filter(fn (mixed $tenant): bool => $tenant instanceof Tenant)
            ->unique(fn (Tenant $tenant): string => (string) $tenant->id)
            ->values();

        if ($tenants->isEmpty()) {
            $this->warn('Limpeza ignorada: nenhum tenant válido encontrado nas integrações ativas.');

            return;
        }

        $this->warn('Iniciando limpeza de tabelas para integrações ativas...');

        foreach ($tenants as $tenant) {
            $tenant->execute(function () use ($tenant, $types): void {
                $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));

                if (in_array('products', $types, true)) {
                    DB::connection($connection)->table('product_store')->where('tenant_id', (string) $tenant->id)->delete();
                    DB::connection($connection)->table('products')->where('tenant_id', (string) $tenant->id)->delete();
                }

                if (in_array('sales', $types, true)) {
                    DB::connection($connection)->table('sales')->where('tenant_id', (string) $tenant->id)->delete();
                }
            });
        }

        $this->info(sprintf(
            'Limpeza concluída para %d tenant(s) ativo(s). Tipos: %s',
            $tenants->count(),
            implode(', ', $types),
        ));
    }
}
