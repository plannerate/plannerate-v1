<?php

namespace App\Console\Commands\Integrations;

use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('integrations:daily-imports')]
#[Description('Inicia a busca diária de produtos e vendas para integrações ativas')]
class DispatchDailyImportsCommand extends Command
{
    public function handle(): int
    {
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
            'Integrações ativas encontradas para busca diária: %d',
            $integrations->count(),
        ));

        $this->table(
            ['Integração', 'Tenant', 'Status tenant', 'Tipo', 'Identificador', 'Última sync'],
            $integrations->map(fn (TenantIntegration $integration): array => [
                (string) $integration->id,
                $integration->tenant
                    ? sprintf('%s (%s)', $integration->tenant->name, $integration->tenant->slug)
                    : sprintf('Tenant não encontrado (%s)', $integration->tenant_id),
                (string) ($integration->tenant?->status ?? '-'),
                (string) $integration->integration_type,
                (string) ($integration->identifier ?: '-'),
                $integration->last_sync?->toDateTimeString() ?? '-',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
