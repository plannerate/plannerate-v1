<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationImportRun;
use App\Models\TenantIntegration;
use App\Services\Integrations\TenantRecordPersister;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Throwable;

/**
 * Lê o arquivo de registros já mapeados pelo FetchIntegrationPageJob
 * e persiste no banco do tenant via upsert.
 */
class ProcessPageResponseJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Quarentena de páginas não persistidas — dados preservados p/ diagnóstico/reprocesso (limpo pelo imports:prune). */
    private const QUARANTINE_DIR = 'imports/failed';

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly ?string $storeId,
        public readonly string $filePath,
        public readonly ?string $runId = null,
    ) {
        $this->onQueue('imports-process');
    }

    public function handle(): void
    {
        $integration = $this->loadIntegration();

        if ($integration === null) {
            $this->quarantineFile();

            return;
        }

        $pathConfig = $this->resolvePathConfig($integration);

        if ($pathConfig === null) {
            $this->quarantineFile();

            return;
        }

        $targetTable = (string) data_get($pathConfig, 'target_table', '');

        if ($targetTable === '') {
            $this->quarantineFile();

            return;
        }

        $records = $this->readRecords();

        if ($records === null) {
            return;
        }

        if ($records === []) {
            $this->deleteFile();

            return;
        }

        $pivotConfigs = $this->normalizePivotConfigs((array) data_get($pathConfig, 'pivot_tables', []));

        $pivotOnlyTargets = array_values(array_filter(
            (array) data_get($pathConfig, 'pivot_only_targets', []),
            static fn (mixed $target): bool => is_string($target) && $target !== '',
        ));

        $persisted = TenantRecordPersister::persist($integration, $targetTable, $records, $pivotConfigs, $pivotOnlyTargets);

        // Progresso do run — nunca deixa o tracking quebrar a persistência.
        // ?? null: jobs enfileirados antes do deploy que adicionou $runId
        // desserializam sem a propriedade (typed prop não-inicializada).
        try {
            IntegrationImportRun::recordPersisted($this->runId ?? null, $persisted);
        } catch (Throwable $e) {
            Log::warning('ProcessPageResponseJob: falha ao registrar progresso do import run', [
                'run_id' => $this->runId,
                'error' => $e->getMessage(),
            ]);
        }

        $this->deleteFile();
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('ProcessPageResponseJob: job falhou, movendo arquivo para quarentena', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'file' => $this->filePath,
            'error' => $exception->getMessage(),
        ]);

        $this->quarantineFile();
    }

    // ─── Leitura ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>>|null */
    private function readRecords(): ?array
    {
        if (! Storage::disk('local')->exists($this->filePath)) {
            Log::warning('ProcessPageResponseJob: arquivo não encontrado', [
                'integration_id' => $this->integrationId,
                'file' => $this->filePath,
            ]);

            return null;
        }

        $contents = Storage::disk('local')->get($this->filePath);
        $data = json_decode((string) $contents, true);

        if (! is_array($data)) {
            Log::error('ProcessPageResponseJob: JSON inválido no arquivo', [
                'integration_id' => $this->integrationId,
                'file' => $this->filePath,
            ]);

            $this->quarantineFile();

            return null;
        }

        return array_values(array_filter($data, fn (mixed $r): bool => is_array($r)));
    }

    /**
     * Move o arquivo para a quarentena em vez de apagar: a página não foi
     * persistida e o JSON é a única cópia dos dados mapeados.
     */
    private function quarantineFile(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->filePath)) {
            return;
        }

        $target = self::QUARANTINE_DIR.'/'.basename($this->filePath);

        if ($disk->move($this->filePath, $target)) {
            Log::warning('ProcessPageResponseJob: arquivo movido para quarentena', [
                'integration_id' => $this->integrationId,
                'file' => $this->filePath,
                'quarantine' => $target,
            ]);

            return;
        }

        Log::error('ProcessPageResponseJob: falha ao mover arquivo para quarentena', [
            'integration_id' => $this->integrationId,
            'file' => $this->filePath,
        ]);
    }

    private function deleteFile(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->filePath)) {
            return;
        }

        if (! $disk->delete($this->filePath)) {
            Log::warning('ProcessPageResponseJob: falha ao remover arquivo temporário', [
                'integration_id' => $this->integrationId,
                'file' => $this->filePath,
            ]);
        }
    }

    // ─── Carregamento ────────────────────────────────────────────────────────

    private function loadIntegration(): ?TenantIntegration
    {
        $integration = TenantIntegration::query()
            ->with('api')
            ->whereKey($this->integrationId)
            ->first();

        if ($integration === null || $integration->api === null) {
            Log::warning('ProcessPageResponseJob: integração ou API não encontrada', [
                'integration_id' => $this->integrationId,
            ]);

            return null;
        }

        return $integration;
    }

    /** @return array<string, mixed>|null */
    private function resolvePathConfig(TenantIntegration $integration): ?array
    {
        $pathConfig = data_get($integration->api->requests ?? [], "paths.{$this->pathKey}");

        if (! is_array($pathConfig)) {
            Log::warning('ProcessPageResponseJob: path não encontrado na API', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
            ]);

            return null;
        }

        return $pathConfig;
    }

    /**
     * @param  array<int, mixed>  $pivotConfigs
     * @return array<int, array<string, mixed>>
     */
    private function normalizePivotConfigs(array $pivotConfigs): array
    {
        $normalized = [];

        foreach ($pivotConfigs as $pivotConfig) {
            if (! is_array($pivotConfig)) {
                continue;
            }

            $table = (string) ($pivotConfig['table'] ?? '');
            $foreignKey = (string) ($pivotConfig['foreign_key'] ?? '');
            $relatedKey = (string) ($pivotConfig['related_key'] ?? '');

            $uniqueBy = collect(is_array($pivotConfig['unique_by'] ?? null) ? $pivotConfig['unique_by'] : [])
                ->filter(fn (mixed $column): bool => is_string($column) && $column !== '')
                ->values()
                ->all();

            if ($uniqueBy === []) {
                $uniqueBy = collect([$foreignKey, $relatedKey])
                    ->filter(fn (string $column): bool => $column !== '')
                    ->values()
                    ->all();
            }

            if ($table === 'product_store' && ! in_array('tenant_id', $uniqueBy, true)) {
                $uniqueBy = ['tenant_id', ...$uniqueBy];
            }

            if ($uniqueBy !== []) {
                $pivotConfig['unique_by'] = array_values(array_unique($uniqueBy));
            }

            $normalized[] = $pivotConfig;
        }

        return $normalized;
    }

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'process',
            "integration:{$this->integrationId}",
            "path:{$this->pathKey}",
        ];
    }
}
