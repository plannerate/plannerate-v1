<?php

namespace App\Console\Commands\Integrations;

use App\Jobs\Integrations\Imports\ImportProductsJob;
use App\Jobs\Integrations\Imports\ImportSalesJob;
use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:daily-imports {--type=all : Tipo de importação: all, sales ou products}')]
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
}
