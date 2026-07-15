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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;

/**
 * Busca uma página específica da API e salva a resposta bruta em disco
 * para processamento assíncrono pelo ProcessPageResponseJob.
 */
class FetchIntegrationPageJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Status HTTP que indicam erro permanente (config/permissão) — retry não resolve. */
    private const NON_RETRYABLE_STATUSES = [401, 403, 404];

    public int $tries = 5;

    public int $maxExceptions = 3;

    public int $timeout = 120;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 60, 120, 300];
    }

    public function __construct(
        public readonly string $integrationId,
        public readonly string $pathKey,
        public readonly int $page,
        public readonly ?string $dateStart = null,
        public readonly ?string $dateEnd = null,
        public readonly ?string $storeId = null,
        public readonly ?string $storeDocument = null,
        public readonly bool $autoPage = false,
        public readonly ?int $knownLastPage = null,
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

            $message = sprintf('HTTP %d ao acessar %s (página %d)', $response->status(), $url, $this->page);

            if (in_array($response->status(), self::NON_RETRYABLE_STATUSES, true)) {
                $this->fail($message);

                return;
            }

            throw new RuntimeException($message);
        }

        $responseData = $response->json();
        $responseMeta = $api->response ?? [];

        $records = $this->mapResponse(
            $response->body(),
            $responseMeta,
            $pathConfig,
            (string) $integration->tenant_id,
            (string) $integration->id,
        );

        // Antes do early-return de página vazia: a checagem de extensão precisa
        // rodar mesmo quando a última página planejada não mapeou registros.
        if (! $this->autoPage && $this->knownLastPage !== null && $this->page >= $this->knownLastPage) {
            $this->dispatchPagesBeyondKnownLast((array) $responseData, $responseMeta, $pathConfig);
        }

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

        if ($this->autoPage) {
            $this->dispatchNextPageIfNeeded($responseData, $responseMeta, $pathConfig);
        }
    }

    // ─── Auto-paginação ──────────────────────────────────────────────────────

    /**
     * No modo página, o total de páginas é congelado na sondagem da descoberta:
     * páginas que surgirem na API depois disso nunca seriam buscadas. O job da
     * última página planejada relê o last_page real da resposta e despacha as
     * excedentes (que por sua vez podem estender de novo).
     *
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     * @param  array<string, mixed>  $pathConfig
     */
    private function dispatchPagesBeyondKnownLast(array $responseData, array $responseMeta, array $pathConfig): void
    {
        $lastPagePath = (string) data_get($responseMeta, 'pagination.last_page_path', '');

        if ($lastPagePath === '') {
            return;
        }

        $actualLastPage = (int) data_get($responseData, $lastPagePath, $this->page);
        $maxPage = (int) data_get($pathConfig, 'max_page', 0);

        if ($maxPage > 0) {
            $actualLastPage = min($actualLastPage, $maxPage);
        }

        if ($actualLastPage <= $this->knownLastPage) {
            return;
        }

        Log::info('FetchIntegrationPageJob: páginas novas após a sondagem, despachando excedentes', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'known_last_page' => $this->knownLastPage,
            'actual_last_page' => $actualLastPage,
            'store_id' => $this->storeId,
        ]);

        $delaySeconds = (int) config('integrations.fetch_delay', 3);

        foreach (range($this->knownLastPage + 1, $actualLastPage) as $index => $page) {
            self::dispatch(
                $this->integrationId, $this->pathKey, $page,
                $this->dateStart, $this->dateEnd, $this->storeId, $this->storeDocument,
                autoPage: false,
                knownLastPage: $actualLastPage,
            )->delay(now()->addSeconds($index * $delaySeconds));
        }
    }

    /**
     * Lê o last_page da resposta e despacha o próximo FetchIntegrationPageJob
     * quando ainda há páginas a buscar. Usado somente no modo diário.
     *
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     * @param  array<string, mixed>  $pathConfig
     */
    private function dispatchNextPageIfNeeded(array $responseData, array $responseMeta, array $pathConfig): void
    {
        $lastPagePath = (string) data_get($responseMeta, 'pagination.last_page_path', '');

        if ($lastPagePath === '') {
            return;
        }

        $lastPage = (int) data_get($responseData, $lastPagePath, 1);
        $maxPage = (int) data_get($pathConfig, 'max_page', 0);

        if ($maxPage > 0) {
            $lastPage = min($lastPage, $maxPage);
        }

        if ($this->page >= $lastPage) {
            return;
        }

        $nextPage = $this->page + 1;

        Log::info('FetchIntegrationPageJob: despachando próxima página', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'next_page' => $nextPage,
            'last_page' => $lastPage,
            'store_id' => $this->storeId,
            'date_start' => $this->dateStart,
        ]);

        self::dispatch(
            $this->integrationId, $this->pathKey, $nextPage,
            $this->dateStart, $this->dateEnd, $this->storeId, $this->storeDocument,
            autoPage: true,
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
        $validations = (array) data_get($pathConfig, 'validations', []);
        $mapper = new RecordMapper(new FieldValueResolver);
        $idGenerator = new DeterministicIdGenerator;
        $now = Carbon::now()->toDateTimeString();

        $skippedRequired = 0;
        /** @var array<string, int> $skippedByField */
        $skippedByField = [];
        $mappedRecords = [];

        foreach ($items as $item) {
            [$record, $rejectedField] = $mapper->mapWithRejectionReason($item, $fieldMap, $this->storeId, $validations);

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

    /** @return array<int, mixed> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->overlapKey()))
                ->releaseAfter(20)
                ->expireAfter(180),
        ];
    }

    private function overlapKey(): string
    {
        return implode(':', [
            'integration',
            $this->integrationId,
            'path',
            $this->pathKey,
            'page',
            (string) $this->page,
            'store',
            $this->storeId ?? $this->storeDocument ?? 'all',
            'date',
            $this->dateStart ?? 'all',
            $this->dateEnd ?? 'all',
        ]);
    }

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
