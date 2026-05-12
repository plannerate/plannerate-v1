<?php

namespace App\Console\Commands\Integrations;

use App\Models\TenantIntegration;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class RunIntegrationImportCommand extends Command
{
    protected $signature = 'integration:run
                            {--tenant= : ID do tenant específico}
                            {--type= : Slug da integração (ex: sysmo)}
                            {--list : Apenas lista as integrações ativas sem executar}';

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
        $this->info('Processamento concluído.');

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
        $apiName = $integration->api?->name ?? $integration->integration_type;

        $this->info('───────────────────────────────────────────────────────');
        $this->info("Integração: {$apiName} | Tenant: {$integration->tenant_id}");
        $this->info('───────────────────────────────────────────────────────');

        if ($integration->api === null) {
            $this->error('   API não encontrada para esta integração.');

            return;
        }

        $this->line(sprintf('   API: %s (%s)', $integration->api->name, $integration->api->slug));
        $this->line(sprintf('   Tenant ID: %s', $integration->tenant_id));
        $this->line(sprintf('   Último sync: %s', $integration->last_sync?->toDateTimeString() ?? 'Nunca'));
    }
}
