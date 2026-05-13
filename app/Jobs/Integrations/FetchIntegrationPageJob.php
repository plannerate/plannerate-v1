<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
use App\Models\TenantIntegration;
use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\IntegrationPayloadBuilder;
use App\Services\Integrations\RecordMapper;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\TenantUpsertRecordPreparer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Busca uma página específica da API e salva a resposta bruta em disco
 * para processamento assíncrono pelo ProcessPageResponseJob.
 */
class FetchIntegrationPageJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly int $page,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateEnd = null,
        public readonly ?string $storeId = null,
        public readonly ?string $storeDocument = null,
    ) {
        $this->onQueue('imports-fetch');
    }

    public function handle(): void
    {
        $integration = $this->loadIntegration();

        if ($integration === null) {
            return;
        }

        $api = $integration->api;
        $pathConfig = $this->resolvePathConfig($api);

        if ($pathConfig === null) {
            return;
        }

        $config = $integration->config ?? [];
        $requests = $api->requests ?? [];

        $url = $this->buildUrl($config, $pathConfig);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($this->dateStart, $this->dateEnd, $this->storeDocument, page: $this->page);

        Log::info('FetchIntegrationPageJob: requisição', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'page' => $this->page,
            'store_id' => $this->storeId,
            'url' => $url,
            'payload' => $payload,
        ]);

        $response = (new IntegrationHttpClient($config))
            ->call($method, $url, $payload);

        if (! $response->successful()) {
            Log::error('FetchIntegrationPageJob: falha na chamada HTTP', [
                'payload' => $payload,
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
                'status' => $response->status(),
                'url' => $url,
            ]);

            $this->fail(sprintf('HTTP %d ao acessar %s (página %d)', $response->status(), $url, $this->page));

            return;
        }

        $records = $this->mapResponse(
            $response->body(),
            $api->response ?? [],
            $pathConfig,
            (string) $integration->tenant_id,
            (string) $integration->id,
        );

        if ($records === []) {
            Log::info('FetchIntegrationPageJob: nenhum registro mapeado', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        Log::info('FetchIntegrationPageJob: registros mapeados', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'page' => $this->page,
            'store_id' => $this->storeId,
            'count' => count($records),
        ]);

        $filePath = $this->saveRecords($records);

        ProcessPageResponseJob::dispatch(
            $this->integrationId,
            $this->pathKey,
            $this->storeId,
            $filePath,
        );
    }

    // ─── Mapping e persistência em arquivo ───────────────────────────────────

    /**
     * Extrai itens da resposta e aplica o field_map, retornando registros prontos para upsert.
     *
     * @param  array<string, mixed>  $responseMeta
     * @param  array<string, mixed>  $pathConfig
     * @return array<int, array<string, mixed>>
     */
    private function mapResponse(
        string $body,
        array $responseMeta,
        array $pathConfig,
        string $tenantId,
        string $integrationId,
    ): array {
        $data = json_decode($body, true);

        if (! is_array($data)) {
            return [];
        }

        $itemsPath = (string) data_get($responseMeta, 'items_path', '');
        $raw = $itemsPath !== '' ? data_get($data, $itemsPath) : $data;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        if (array_keys($raw) !== range(0, count($raw) - 1)) {
            $raw = array_values($raw);
        }

        $items = array_filter($raw, fn (mixed $item): bool => is_array($item));

        if ($items === []) {
            return [];
        }

        $fieldMap = (array) data_get($pathConfig, 'field_map', []);
        $mapper = new RecordMapper(new FieldValueResolver);
        $idGenerator = new DeterministicIdGenerator;
        $now = Carbon::now()->toDateTimeString();

        $skippedRequired = 0;
        /** @var array<string, int> $skippedByField */
        $skippedByField = [];
        $mappedRecords = [];

        foreach ($items as $item) {
            [$record, $rejectedField] = $mapper->mapWithRejectionReason($item, $fieldMap, $this->storeId);

            if ($record === null) {
                $skippedRequired++;

                if ($rejectedField !== null) {
                    $skippedByField[$rejectedField] = ($skippedByField[$rejectedField] ?? 0) + 1;
                }

                continue;
            }

            $record['id'] = $idGenerator->fromRecord($tenantId, $integrationId, $record, $pathConfig, $this->storeId);
            $record['tenant_id'] = $tenantId;
            $record['created_at'] = $now;
            $record['updated_at'] = $now;

            $mappedRecords[] = $record;
        }

        if ($skippedRequired > 0) {
            Log::warning('FetchIntegrationPageJob: registros descartados por not_null', [
                'integration_id' => $integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
                'skipped' => $skippedRequired,
                'skipped_by_field' => $skippedByField,
            ]);
        }

        $deduplicatedRecords = TenantUpsertRecordPreparer::deduplicateById($mappedRecords);

        return array_values($deduplicatedRecords);
    }

    /** @param array<int, array<string, mixed>> $records */
    private function saveRecords(array $records): string
    {
        $path = 'imports/'.Str::ulid().'.json';
        Storage::disk('local')->put($path, json_encode($records));

        return $path;
    }

    // ─── Carregamento ────────────────────────────────────────────────────────

    private function loadIntegration(): ?TenantIntegration
    {
        $integration = TenantIntegration::query()
            ->with('api')
            ->whereKey($this->integrationId)
            ->first();

        if ($integration === null || $integration->api === null) {
            Log::warning('FetchIntegrationPageJob: integração ou API não encontrada', [
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
            Log::warning('FetchIntegrationPageJob: path não encontrado na API', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
            ]);

            return null;
        }

        return $pathConfig;
    }

    private function buildUrl(array $config, array $pathConfig): string
    {
        $baseUrl = (string) data_get($config, 'connection.base_url', '');
        $fallbackPath = (string) data_get($pathConfig, 'fallback_path', '');

        return rtrim($baseUrl, '/').$fallbackPath;
    }

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, string> */
    public function tags(): array
    {
        return [
            'integration',
            'fetch',
            "integration:{$this->integrationId}",
            "path:{$this->pathKey}",
            "page:{$this->page}",
        ];
    }
}
