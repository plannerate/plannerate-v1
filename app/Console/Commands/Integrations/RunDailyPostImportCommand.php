<?php

/**
 * Pipeline diário pós-importação.
 *
 * Busca as TenantIntegrations ativas (mesma lógica do integration:run) e,
 * para cada tenant com integração ativa, executa em sequência:
 *   1. sync:link-sales        — vincula vendas aos produtos via codigo_erp
 *   2. sync:cleanup           — limpa vendas órfãs/antigas e produtos inativos
 *   3. sync:products-from-ean-references — padroniza produtos pela tabela ean_references
 *   4. monthly-sales:recalculate — reagrega monthly_sales_summaries e re-vincula
 *      product_id pelo codigo_erp (mantém o scoring e o ABC consistentes após
 *      reimportações que geram novos ULIDs de produto)
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
use App\Services\Integrations\Support\ImportQueueMonitor;
use App\Services\Integrations\Support\ImportRunReconciler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

#[Signature('sync:post-import
    {--tenant= : ID do tenant específico}
    {--wait-minutes=60 : Tempo máximo (minutos) de espera pelas filas de importação esvaziarem}
    {--skip-queue-check : Não espera as filas de importação (risco de agir sobre dados parciais)}')]
#[Description('Pipeline pós-importação: vincula vendas, limpa dados e sincroniza por EAN')]
class RunDailyPostImportCommand extends Command
{
    /** Intervalo entre verificações do backlog das filas de importação. */
    private const QUEUE_POLL_SECONDS = 30;

    public function handle(): int
    {
        if (! $this->option('skip-queue-check') && ! $this->waitForImportQueuesToDrain()) {
            return self::FAILURE;
        }

        // Filas vazias = não há mais trabalho → reconcilia os runs de hoje
        // (marca complete/partial, loga parciais) antes de agir sobre os dados.
        $this->reconcileImportRuns();

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
     * Reconcilia os runs de importação de hoje (cobertura real vs. plano).
     * Defensivo: falha na reconciliação não impede o pipeline pós-import.
     */
    protected function reconcileImportRuns(): void
    {
        try {
            $summary = ImportRunReconciler::reconcileForDate(now()->toDateString());

            if ($summary['reconciled'] > 0) {
                $this->line(sprintf(
                    '  Runs de importação reconciliados: %d (complete=%d, partial=%d)',
                    $summary['reconciled'],
                    $summary['complete'],
                    $summary['partial'],
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('sync:post-import: falha ao reconciliar import runs', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Barreira import → pós-import: aguarda as filas de importação esvaziarem
     * antes de vincular/limpar, para não agir sobre um snapshot parcial.
     * O agendamento (07:30, após o integration:run das 06:00) é só um chute de
     * relógio — esta verificação é a garantia real.
     */
    protected function waitForImportQueuesToDrain(): bool
    {
        $waitMinutes = max(0, (int) $this->option('wait-minutes'));
        $deadline = now()->addMinutes($waitMinutes);

        while (true) {
            $pendingByQueue = ImportQueueMonitor::pendingJobsByQueue();

            if (array_sum($pendingByQueue) === 0) {
                return true;
            }

            $pendingLabel = collect($pendingByQueue)
                ->map(fn (int $size, string $queue): string => "{$queue}={$size}")
                ->implode(', ');

            if (now()->greaterThanOrEqualTo($deadline)) {
                $this->error("Filas de importação ainda com backlog ({$pendingLabel}); pipeline abortado para não processar dados parciais.");

                Log::error('sync:post-import abortado: backlog nas filas de importação após o tempo de espera', [
                    'pending' => $pendingByQueue,
                    'wait_minutes' => $waitMinutes,
                ]);

                return false;
            }

            $this->warn("Aguardando filas de importação esvaziarem ({$pendingLabel})...");
            sleep(self::QUEUE_POLL_SECONDS);
        }
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
        $this->line('  [ 1/4 ] sync:link-sales');
        $exitCode = $this->call('sync:link-sales', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:link-sales falhou (código {$exitCode}).");

            return $exitCode;
        }

        // 2. Limpeza: vendas órfãs/antigas, produtos inativos, restauração
        $this->line('  [ 2/4 ] sync:cleanup');
        $exitCode = $this->call('sync:cleanup', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:cleanup falhou (código {$exitCode}).");

            return $exitCode;
        }

        // 3. Padroniza produtos usando ean_references (nome, EAN, dimensões)
        $this->line('  [ 3/4 ] sync:products-from-ean-references');
        $exitCode = $this->call('sync:products-from-ean-references', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ sync:products-from-ean-references falhou (código {$exitCode}).");

            return $exitCode;
        }

        // 4. Reagrega monthly_sales_summaries e re-vincula product_id pelo codigo_erp.
        // Roda por último (após produtos padronizados) e ASSÍNCRONO: o job vai para a
        // fila maintenance (1 worker, FIFO), então executa depois da corrente de
        // cleanup — a ordem é preservada sem prender o processo do scheduler num
        // recálculo pesado.
        $this->line('  [ 4/4 ] monthly-sales:recalculate (assíncrono, fila maintenance)');
        $exitCode = $this->call('monthly-sales:recalculate', $args);

        if ($exitCode !== self::SUCCESS) {
            $this->error("         ❌ monthly-sales:recalculate falhou (código {$exitCode}).");

            return $exitCode;
        }

        return self::SUCCESS;
    }
}
