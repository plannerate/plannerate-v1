<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
use App\Models\TenantIntegration;
use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\RecordMapper;
use App\Services\Integrations\Support\DeterministicIdGenerator;
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
 * Lê o arquivo de resposta salvo pelo FetchIntegrationPageJob,
 * extrai os itens e os passa para o serviço de persistência.
 */
class ProcessPageResponseJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

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

        $api = $integration->api;
        $pathConfig = $this->resolvePathConfig($api);

        if ($pathConfig === null) {
            $this->deleteFile();

            return;
        }

        $responseData = $this->readFile();

        if ($responseData === null) {
            return;
        }

        $items = $this->extractItems($responseData, $api->response ?? []);

        if ($items === []) {
            Log::info('ProcessPageResponseJob: nenhum item na resposta', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'store_id' => $this->storeId,
                'file' => $this->filePath,
            ]);

            $this->deleteFile();

            return;
        }

        Log::info('ProcessPageResponseJob: itens extraídos', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'store_id' => $this->storeId,
            'count' => count($items),
            'file' => $this->filePath,
        ]);

        $persister = new TenantRecordPersister(
            new RecordMapper(new FieldValueResolver),
            new DeterministicIdGenerator,
        );
        $persister->handle($integration, $pathConfig, $this->storeId, $items);

        $this->deleteFile();
    }

    // ─── Leitura do arquivo ───────────────────────────────────────────────────

    /** @return array<string, mixed>|null */
    private function readFile(): ?array
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

        return $data;
    }

    private function deleteFile(): void
    {
        Storage::disk('local')->delete($this->filePath);
    }

    // ─── Extração de itens ────────────────────────────────────────────────────

    /**
     * Extrai o array de itens da resposta usando o items_path configurado.
     * Normaliza objeto associativo para array indexado quando necessário.
     *
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $responseData, array $responseMeta): array
    {
        $itemsPath = (string) data_get($responseMeta, 'items_path', '');
        $raw = $itemsPath !== '' ? data_get($responseData, $itemsPath) : $responseData;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        // Normalize associative object {"0": {...}, "1": {...}} to indexed array
        if (array_keys($raw) !== range(0, count($raw) - 1)) {
            $raw = array_values($raw);
        }

        return array_filter($raw, fn (mixed $item): bool => is_array($item));
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

    private function resolvePathConfig(IntegrationApi $api): ?array
    {
        $pathConfig = data_get($api->requests ?? [], "paths.{$this->pathKey}");

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
