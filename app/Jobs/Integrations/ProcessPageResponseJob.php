<?php

namespace App\Jobs\Integrations;

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

/**
 * Lê o arquivo de registros já mapeados pelo FetchIntegrationPageJob
 * e persiste no banco do tenant via upsert.
 */
class ProcessPageResponseJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly ?string $storeId,
        public readonly string $filePath,
    ) {
        $this->onQueue('imports');
    }

    public function handle(): void
    {
        $integration = $this->loadIntegration();

        if ($integration === null) {
            $this->deleteFile();

            return;
        }

        $pathConfig = $this->resolvePathConfig($integration);

        if ($pathConfig === null) {
            $this->deleteFile();

            return;
        }

        $targetTable = (string) data_get($pathConfig, 'target_table', '');

        if ($targetTable === '') {
            $this->deleteFile();

            return;
        }

        $records = $this->readRecords();

        if ($records === null) {
            return;
        }

        if ($records === []) {
            Log::info('ProcessPageResponseJob: arquivo sem registros', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'file' => $this->filePath,
            ]);

            $this->deleteFile();

            return;
        }

        Log::info('ProcessPageResponseJob: persistindo registros', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $this->storeId,
            'count' => count($records),
            'file' => $this->filePath,
        ]);

        $pivotConfigs = $this->normalizePivotConfigs((array) data_get($pathConfig, 'pivot_tables', []));

        TenantRecordPersister::persist($integration, $targetTable, $records, $pivotConfigs);

        $this->deleteFile();
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

            $this->deleteFile();

            return null;
        }

        return array_values(array_filter($data, fn (mixed $r): bool => is_array($r)));
    }

    private function deleteFile(): void
    {
        Storage::disk('local')->delete($this->filePath);
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
        $adjusted = 0;

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
                $adjusted++;
            }

            if ($uniqueBy !== []) {
                $pivotConfig['unique_by'] = array_values(array_unique($uniqueBy));
            }

            $normalized[] = $pivotConfig;
        }

        if ($adjusted > 0) {
            Log::info('ProcessPageResponseJob: pivot unique_by normalizado com tenant_id', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'adjusted_configs' => $adjusted,
            ]);
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
