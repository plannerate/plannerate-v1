<?php

/**
 * Pipeline diário pós-importação.
 *
 * Busca as TenantIntegrations ativas (mesma lógica do integration:run) e,
 * para cada tenant com integração ativa, executa em sequência:
 *   1. sync:link-sales        — vincula vendas aos produtos via codigo_erp
 *   2. sync:cleanup           — limpa vendas órfãs/antigas e produtos inativos
 *   3. sync:products-from-ean-references — padroniza produtos pela tabela ean_references
 *
 * Deve ser agendado com gap suficiente após integration:run para garantir que
 * os jobs da fila `imports` já foram processados.
 *
 * Created by Claudio Campos.
 * User: callcocam@gmail.com, contato@sigasmart.com.br
 * https://www.sigasmart.com.br
 */

namespace App\Console\Commands\Integrations;

use App\Models\TenantIntegration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('sync:post-import {--tenant= : ID do tenant específico}')]
#[Description('Pipeline pós-importação: vincula vendas, limpa dados e sincroniza por EAN')]
class RunDailyPostImportCommand extends Command
{
    public function handle(): int
    {
        $integrations = $this->getActiveIntegrations();

        if ($integrations->isEmpty()) {
            $this->warn('Nenhuma TenantIntegration ativa encontrada. Pipeline encerrado.');

            return self::SUCCESS;
        }

        // Extrai os IDs únicos de tenant com integração ativa
        $tenantIds = $integrations
            ->pluck('tenant_id')
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║       Pipeline Diário Pós-Importação             ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->info(sprintf('Tenants com integração ativa: %d', count($tenantIds)));
        $this->info('');

        $failed = 0;

        foreach ($tenantIds as $tenantId) {
            $this->info('───────────────────────────────────────────────────');
            $this->info("🏢 Tenant: {$tenantId}");

            $exitCode = $this->runPipelineForTenant($tenantId);

            if ($exitCode !== self::SUCCESS) {
                $this->error("❌ Pipeline falhou para o tenant {$tenantId}.");
                $failed++;
            }
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════════════');

        if ($failed > 0) {
            $this->error("Pipeline concluído com {$failed} falha(s).");

            return self::FAILURE;
        }

        $this->info('✅ Pipeline pós-importação concluído com sucesso.');

        return self::SUCCESS;
    }

    /**
     * Retorna as TenantIntegrations ativas, opcionalmente filtradas por tenant.
     *
     * @return Collection<int, TenantIntegration>
     */
    protected function getActiveIntegrations(): Collection
    {
        $tenantId = $this->option('tenant');

        return TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->where('is_active', true)
            ->whereHas('api', fn ($q) => $q->where('is_active', true))
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->when(
                is_string($tenantId) && $tenantId !== '',
                fn ($q) => $q->where('tenant_id', $tenantId),
            )
            ->get();
    }

    /**
     * Executa as 3 etapas do pipeline para um tenant específico.
     * Falha rápida: interrompe se qualquer etapa retornar erro.
     */
    protected function runPipelineForTenant(string $tenantId): int
    {
        $args = ['--tenant' => $tenantId];

        // 1. Vincula vendas aos produtos usando codigo_erp
        $this->line('  [ 1/3 ] sync:link-sales');
        $exitCode = $this->call('sync:link-sales', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:link-sales falhou (código {$exitCode}).");

            return $exitCode;
        }

        // 2. Limpeza: vendas órfãs/antigas, produtos inativos, restauração
        $this->line('  [ 2/3 ] sync:cleanup');
        $exitCode = $this->call('sync:cleanup', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:cleanup falhou (código {$exitCode}).");

            return $exitCode;
        }

        // 3. Padroniza produtos usando ean_references (nome, EAN, dimensões)
        $this->line('  [ 3/3 ] sync:products-from-ean-references');
        $exitCode = $this->call('sync:products-from-ean-references', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:products-from-ean-references falhou (código {$exitCode}).");

            return $exitCode;
        }

        return self::SUCCESS;
    }
}
