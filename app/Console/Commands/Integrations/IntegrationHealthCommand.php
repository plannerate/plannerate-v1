<?php

namespace App\Console\Commands\Integrations;

use App\Models\IntegrationImportRun;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ImportDiscardMetrics;
use App\Services\Integrations\Support\ImportQueueMonitor;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Relatório de saúde do pipeline de importação — não-interativo e scriptável.
 *
 * Consolida os sinais que a Fase 4 passou a coletar (descartes, quarentena,
 * backlog de fila) + o frescor da última importação por integração/path, num
 * único lugar acionável. Read-only: não toca no caminho de escrita do import.
 *
 * `--json` para alertas/monitoramento; exit code 1 quando há sinal de alerta
 * (import atrasado, backlog, quarentena ou descarte alto) para uso em cron.
 */
class IntegrationHealthCommand extends Command
{
    /** Import considerado atrasado quando a venda mais recente é anterior a hoje − N dias. */
    private const SALES_STALE_DAYS = 2;

    protected $signature = 'integration:health
        {--tenant= : Limita a um tenant específico}
        {--json : Saída em JSON (para monitoramento/alertas)}';

    protected $description = 'Relatório de saúde do pipeline de importação por integração';

    public function handle(): int
    {
        $global = $this->collectGlobal();
        $integrations = $this->collectPerIntegration();

        $alert = $this->hasAlert($global, $integrations);

        if ($alert) {
            // Deixa rastro para a execução agendada (monitoramento passivo).
            Log::warning('integration:health: sinais de alerta no pipeline de importação', [
                'queue_total' => $global['queue_total'],
                'quarantine_files' => $global['quarantine_files'],
                'orphan_files' => $global['orphan_files'],
                'stale_paths' => array_values(array_filter(
                    array_map(fn (array $r): ?string => ($r['stale'] ?? false) ? "{$r['tenant']}/{$r['path']}" : null, $integrations),
                )),
            ]);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'alert' => $alert,
                'global' => $global,
                'integrations' => $integrations,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $alert ? self::FAILURE : self::SUCCESS;
        }

        $this->renderHuman($global, $integrations, $alert);

        return $alert ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sinais globais do ambiente (não por tenant): filas e arquivos temporários.
     *
     * @return array{queues: array<string, int>, queue_total: int, orphan_files: int, quarantine_files: int}
     */
    private function collectGlobal(): array
    {
        $disk = Storage::disk('local');
        $orphans = count($disk->files('imports'));           // não recursivo: só imports/, não imports/failed/
        $quarantine = count($disk->files('imports/failed'));

        $queues = ImportQueueMonitor::pendingJobsByQueue();

        return [
            'queues' => $queues,
            'queue_total' => array_sum($queues),
            'orphan_files' => $orphans,
            'quarantine_files' => $quarantine,
        ];
    }

    /**
     * Saúde por integração ativa × path (products/sales).
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectPerIntegration(): array
    {
        $rows = [];

        foreach ($this->activeIntegrations() as $integration) {
            $tenant = $integration->tenant;

            if (! $tenant instanceof Tenant) {
                continue;
            }

            $paths = data_get($integration->api?->requests ?? [], 'paths', []);

            if (! is_array($paths)) {
                continue;
            }

            foreach ($paths as $pathKey => $pathConfig) {
                if (! is_string($pathKey) || ! is_array($pathConfig)) {
                    continue;
                }

                $rows[] = $this->pathHealth($tenant, $integration, $pathKey, $pathConfig);
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $pathConfig
     * @return array<string, mixed>
     */
    private function pathHealth(Tenant $tenant, TenantIntegration $integration, string $pathKey, array $pathConfig): array
    {
        $targetTable = (string) data_get($pathConfig, 'target_table', $pathKey);
        $integrationId = (string) $integration->id;

        $initialDays = max(1, (int) data_get($pathConfig, 'initial_days', 200));

        $data = $tenant->execute(function () use ($targetTable, $initialDays): array {
            if (! Schema::connection('tenant')->hasTable($targetTable)) {
                return ['rows' => null, 'last_date' => null, 'rounded_margin' => null];
            }

            $query = DB::connection('tenant')->table($targetTable);
            $rows = (clone $query)->count();

            // sales → sale_date (frescor do DADO: dia de venda mais recente).
            // Demais → updated_at (frescor do IMPORT: último upsert). Evita sync_at,
            // que nem sempre é populado.
            $dateColumn = match (true) {
                Schema::connection('tenant')->hasColumn($targetTable, 'sale_date') => 'sale_date',
                Schema::connection('tenant')->hasColumn($targetTable, 'updated_at') => 'updated_at',
                default => null,
            };

            $lastDate = $dateColumn !== null ? (clone $query)->max($dateColumn) : null;

            // Progresso do backfill de precisão da margem (só onde há
            // margem_contribuicao + sale_date): linhas NA JANELA do backfill
            // ainda com margem em ≤2 casas. Cai conforme o backfill reimporta.
            // Não zera (margens legítimas de 2 casas), então é indicador, não alerta.
            $roundedMargin = null;
            if (Schema::connection('tenant')->hasColumn($targetTable, 'margem_contribuicao')
                && Schema::connection('tenant')->hasColumn($targetTable, 'sale_date')) {
                $cutoff = now()->subDays($initialDays)->toDateString();
                $roundedMargin = (clone $query)
                    ->where('sale_date', '>=', $cutoff)
                    ->whereNotNull('margem_contribuicao')
                    ->whereRaw('margem_contribuicao = round(margem_contribuicao, 2)')
                    ->count();
            }

            return ['rows' => $rows, 'last_date' => $lastDate, 'rounded_margin' => $roundedMargin];
        });

        $lastDate = $data['last_date'] !== null ? Carbon::parse((string) $data['last_date']) : null;
        $ageDays = $lastDate !== null ? $lastDate->startOfDay()->diffInDays(now()->startOfDay()) : null;
        $stale = $targetTable === 'sales' && ($lastDate === null || $ageDays > self::SALES_STALE_DAYS);

        return [
            'tenant' => $tenant->name,
            'integration_id' => $integrationId,
            'path' => $pathKey,
            'table' => $targetTable,
            'rows' => $data['rows'],
            'last_import' => $lastDate?->toDateString(),
            'age_days' => $ageDays,
            'stale' => $stale,
            'discards_today' => ImportDiscardMetrics::totalForToday($integrationId, $pathKey),
            'rounded_margin_in_window' => $data['rounded_margin'],
            'last_run' => $this->lastRun($integrationId, $pathKey),
        ];
    }

    /**
     * Último run de importação da integração/path (proveniência real:
     * status + cobertura + persistido, do integration_import_runs).
     *
     * @return array{status: string, reference_date: string, covered: ?int, expected: int, persisted: int}|null
     */
    private function lastRun(string $integrationId, string $pathKey): ?array
    {
        try {
            $run = IntegrationImportRun::query()
                ->where('integration_id', $integrationId)
                ->where('path_key', $pathKey)
                ->latest('discovered_at')
                ->first();
        } catch (\Throwable) {
            // Tabela ainda não migrada (ex.: entre etapas de deploy) → sem run.
            return null;
        }

        if ($run === null) {
            return null;
        }

        return [
            'status' => $run->status,
            'reference_date' => (string) $run->reference_date,
            'covered' => $run->covered_units,
            'expected' => $run->expected_units,
            'persisted' => $run->persisted_records,
        ];
    }

    /** @return Collection<int, TenantIntegration> */
    private function activeIntegrations(): Collection
    {
        $tenantId = $this->option('tenant');

        return TenantIntegration::query()
            ->with(['api', 'tenant'])
            ->where('is_active', true)
            ->whereHas('api', fn ($q) => $q->where('is_active', true))
            ->whereHas('tenant', fn ($q) => $q->where('status', 'active'))
            ->when(is_string($tenantId) && $tenantId !== '', fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();
    }

    /**
     * @param  array{queue_total: int, quarantine_files: int, orphan_files: int}  $global
     * @param  array<int, array<string, mixed>>  $integrations
     */
    private function hasAlert(array $global, array $integrations): bool
    {
        if ($global['queue_total'] > 0 || $global['quarantine_files'] > 0) {
            return true;
        }

        foreach ($integrations as $row) {
            if (($row['stale'] ?? false) || ($row['discards_today'] ?? 0) > 0) {
                return true;
            }

            if (($row['last_run']['status'] ?? null) === 'partial') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{queues: array<string, int>, queue_total: int, orphan_files: int, quarantine_files: int}  $global
     * @param  array<int, array<string, mixed>>  $integrations
     */
    private function renderHuman(array $global, array $integrations, bool $alert): void
    {
        $this->newLine();
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║          Saúde do Pipeline de Importação         ║');
        $this->info('╚══════════════════════════════════════════════════╝');

        $this->line(sprintf(
            'Filas: imports-fetch=%d, imports-process=%d  |  Órfãos: %d  |  Quarentena: %d',
            $global['queues']['imports-fetch'] ?? 0,
            $global['queues']['imports-process'] ?? 0,
            $global['orphan_files'],
            $global['quarantine_files'],
        ));
        $this->newLine();

        if ($integrations === []) {
            $this->warn('Nenhuma integração ativa encontrada.');

            return;
        }

        $this->table(
            ['Tenant', 'Path', 'Linhas', 'Última import.', 'Idade', 'Descartes', 'Margem ≤2c', 'Último run', 'Estado'],
            array_map(fn (array $r): array => [
                $r['tenant'],
                $r['path'],
                $r['rows'] === null ? '—' : number_format((int) $r['rows']),
                $r['last_import'] ?? '—',
                $r['age_days'] === null ? '—' : "{$r['age_days']}d",
                $r['discards_today'] > 0 ? "⚠ {$r['discards_today']}" : '0',
                $r['rounded_margin_in_window'] === null ? '—' : number_format((int) $r['rounded_margin_in_window']),
                self::formatRun($r['last_run']),
                $r['stale'] ? '🔴 atrasado' : '🟢 ok',
            ], $integrations),
        );

        $this->newLine();
        $this->line('<fg=gray>Margem ≤2c: vendas na janela do backfill com margem em ≤2 casas (progresso do backfill de precisão; não zera — margens legítimas de 2 casas sempre restam).</>');
        $this->line('<fg=gray>Último run: ✓ concluído / ⚠ parcial (dias esperados vs. cobertos) / … em andamento, do integration_import_runs.</>');
        $this->line($alert ? '🔴 Há sinais de alerta (exit 1).' : '🟢 Tudo saudável.');
    }

    /**
     * @param  array{status: string, covered: ?int, expected: int}|null  $run
     */
    private static function formatRun(?array $run): string
    {
        if ($run === null) {
            return '—';
        }

        $ratio = ($run['covered'] ?? '?').'/'.$run['expected'];

        return match ($run['status']) {
            'complete' => "✓ {$ratio}",
            'partial' => "⚠ {$ratio}",
            'failed' => '✗ falhou',
            default => "… {$run['expected']}",
        };
    }
}
