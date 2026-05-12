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

        $pivotConfigs = (array) data_get($pathConfig, 'pivot_tables', []);

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
