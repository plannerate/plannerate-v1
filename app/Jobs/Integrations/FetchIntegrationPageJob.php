<?php

namespace App\Jobs\Integrations;

use App\Models\IntegrationApi;
use App\Models\IntegrationImportRun;
use App\Models\TenantIntegration;
use App\Services\Integrations\FieldValueResolver;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\IntegrationPayloadBuilder;
use App\Services\Integrations\RecordMapper;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Support\ImportDiscardMetrics;
use App\Services\Integrations\Support\IntegrationPaginationMode;
use App\Services\Integrations\Support\IntegrationResponseGuard;
use App\Services\Integrations\Support\IntegrationUrlBuilder;
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
use Throwable;

/**
 * Busca uma página específica da API e salva a resposta bruta em disco
 * para processamento assíncrono pelo ProcessPageResponseJob.
 */
class FetchIntegrationPageJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Status HTTP que indicam erro permanente (config/permissão) — retry não
     * resolve. 401 NÃO entra: com token_mode fetch pode ser só token expirado
     * em cache (o IntegrationHttpClient invalida o cache ao ver 401, então o
     * retry busca um token novo).
     */
    private const NON_RETRYABLE_STATUSES = [403, 404];

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
        public readonly ?string $runId = null,
        /**
         * Posição na paginação por cursor (`pagination_mode: cursor`). Null nos
         * modos página/diário. Default explícito para que jobs enfileirados
         * antes do deploy desserializem sem a propriedade — sempre lido com `?? null`.
         */
        public readonly ?string $cursor = null,
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

        $url = IntegrationUrlBuilder::build($config, $pathConfig, $this->cursor ?? null, $this->storeDocument);
        $method = strtolower((string) data_get($requests, 'method', 'get'));

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($this->dateStart, $this->dateEnd, $this->storeDocument, page: $this->page);

        Log::info('FetchIntegrationPageJob: requisição', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'page' => $this->page,
            'cursor' => $this->cursor ?? null,
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

        $responseData = (array) $response->json();
        $responseMeta = $api->response ?? [];

        // Erro lógico com HTTP 200: sem isso o job leria zero itens, trataria
        // como página vazia e marcaria o dia como coberto — buraco invisível.
        $this->failOnLogicalError($responseData, $responseMeta, $url);

        $rawItems = $this->extractItems($responseData, $responseMeta, $pathConfig);

        $records = $this->mapItems(
            $rawItems,
            $pathConfig,
            (string) $integration->tenant_id,
            (string) $integration->id,
        );

        // Cobertura do run: o fetch concluiu com sucesso (mesmo com zero
        // registros — dia de feriado). Conta 1 unidade por dia (daily: só a
        // página 1) ou por página (page mode: cada página). Antes de qualquer
        // early-return, para o dia vazio também contar. Sempre embrulhado.
        $this->recordRunCoverage();

        // Antes do early-return de página vazia: a checagem de extensão precisa
        // rodar mesmo quando a última página planejada não mapeou registros.
        if (! $this->autoPage && $this->knownLastPage !== null && $this->page >= $this->knownLastPage) {
            $this->dispatchPagesBeyondKnownLast($responseData, $responseMeta, $pathConfig);
        }

        if ($records === []) {
            Log::info('FetchIntegrationPageJob: nenhum registro mapeado', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
                'raw_items' => count($rawItems),
            ]);

            // Sem `return`: uma página inteira rejeitada pelas validações (lote
            // de cancelados) ainda precisa encadear a próxima — senão o import
            // para no meio do catálogo.
            $this->dispatchContinuation($rawItems, $responseData, $responseMeta, $requests, $pathConfig);

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
            // ?? null: jobs enfileirados antes do deploy que adicionou $runId
            // desserializam sem a propriedade (typed prop não-inicializada).
            $this->runId ?? null,
        );

        $this->dispatchContinuation($rawItems, $responseData, $responseMeta, $requests, $pathConfig);
    }

    /**
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     */
    private function failOnLogicalError(array $responseData, array $responseMeta, string $url): void
    {
        $error = IntegrationResponseGuard::logicalErrorMessage($responseData, $responseMeta);

        if ($error === null) {
            return;
        }

        Log::error('FetchIntegrationPageJob: erro lógico na resposta (HTTP 2xx)', [
            'integration_id' => $this->integrationId,
            'path_key' => $this->pathKey,
            'page' => $this->page,
            'cursor' => $this->cursor ?? null,
            'store_id' => $this->storeId,
            'url' => $url,
            'error' => $error,
        ]);

        throw new RuntimeException(sprintf('Erro na resposta de %s: %s', $url, $error));
    }

    /**
     * Encadeia a próxima busca conforme o modo de paginação do path.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     */
    private function dispatchContinuation(
        array $rawItems,
        array $responseData,
        array $responseMeta,
        array $requests,
        array $pathConfig,
    ): void {
        if (IntegrationPaginationMode::isCursor($requests, $pathConfig)) {
            $this->dispatchNextCursorIfNeeded($rawItems, $pathConfig);

            return;
        }

        if ($this->autoPage) {
            $this->dispatchNextPageIfNeeded($responseData, $responseMeta, $pathConfig);
        }
    }

    /**
     * Marca cobertura do run: 1 unidade por dia (daily: só página 1) ou por
     * página (page mode: cada página). Defensivo — falha no tracking nunca
     * quebra o fetch; `?? null` tolera jobs enfileirados antes do deploy.
     */
    private function recordRunCoverage(): void
    {
        $runId = $this->runId ?? null;

        if ($runId === null) {
            return;
        }

        // Daily (autoPage): páginas 2+ do mesmo dia não contam nova unidade.
        if ($this->page !== 1 && $this->autoPage) {
            return;
        }

        try {
            IntegrationImportRun::recordCovered($runId);
        } catch (Throwable $e) {
            Log::warning('FetchIntegrationPageJob: falha ao registrar cobertura do run', [
                'run_id' => $runId,
                'error' => $e->getMessage(),
            ]);
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
                runId: $this->runId ?? null,
            )->delay(now()->addSeconds($index * $delaySeconds));
        }
    }

    /**
     * Paginação por cursor: a resposta não diz quantas páginas existem, então a
     * cadeia é sequencial — cada job lê o id do último item bruto e despacha o
     * seguinte. Para em página vazia (fim) ou cursor repetido (guarda contra
     * loop infinito se a API ignorar o cursor).
     *
     * `$page` continua incrementando como contador da cadeia: alimenta os logs,
     * a tag do Horizon e a chave de overlap.
     *
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, mixed>  $pathConfig
     */
    private function dispatchNextCursorIfNeeded(array $rawItems, array $pathConfig): void
    {
        if ($rawItems === []) {
            Log::info('FetchIntegrationPageJob: fim da cadeia de cursor (página vazia)', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'cursor' => $this->cursor ?? null,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $nextCursor = IntegrationPaginationMode::nextCursor($rawItems, $pathConfig);
        $currentCursor = $this->cursor ?? null;

        if ($nextCursor === null || $nextCursor === $currentCursor) {
            Log::warning('FetchIntegrationPageJob: cursor não avançou; interrompendo a cadeia', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'cursor' => $currentCursor,
                'next_cursor' => $nextCursor,
                'cursor_item_path' => data_get($pathConfig, 'cursor_item_path'),
                'store_id' => $this->storeId,
            ]);

            return;
        }

        $maxPage = (int) data_get($pathConfig, 'max_page', 0);

        if ($maxPage > 0 && $this->page >= $maxPage) {
            Log::info('FetchIntegrationPageJob: max_page atingido na cadeia de cursor', [
                'integration_id' => $this->integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'max_page' => $maxPage,
                'store_id' => $this->storeId,
            ]);

            return;
        }

        self::dispatch(
            $this->integrationId, $this->pathKey, $this->page + 1,
            $this->dateStart, $this->dateEnd, $this->storeId, $this->storeDocument,
            autoPage: true,
            runId: $this->runId ?? null,
            cursor: $nextCursor,
        );
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
            runId: $this->runId ?? null,
        );
    }

    // ─── Mapping e persistência em arquivo ───────────────────────────────────

    /**
     * Localiza a lista de itens dentro da resposta.
     *
     * `items_path` é lido do path config primeiro e só depois do `response`
     * global: APIs com um endpoint por recurso usam caminhos diferentes para
     * cada um (RP Info: `response.produtos` × `response.movimentos`).
     *
     * @param  array<string, mixed>  $responseData
     * @param  array<string, mixed>  $responseMeta
     * @param  array<string, mixed>  $pathConfig
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $responseData, array $responseMeta, array $pathConfig): array
    {
        $itemsPath = (string) (data_get($pathConfig, 'items_path') ?? data_get($responseMeta, 'items_path', ''));
        $raw = $itemsPath !== '' ? data_get($responseData, $itemsPath) : $responseData;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        return array_values(array_filter($raw, fn (mixed $item): bool => is_array($item)));
    }

    /**
     * Aplica o field_map aos itens brutos, retornando registros prontos para upsert.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $pathConfig
     * @return array<int, array<string, mixed>>
     */
    private function mapItems(
        array $items,
        array $pathConfig,
        string $tenantId,
        string $integrationId,
    ): array {
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

                // Rejeição por validação de grupo não tem campo culpado — sem o
                // campo sintético ela ficava invisível no detalhamento.
                $field = $rejectedField ?? ImportDiscardMetrics::GROUP_VALIDATION_FIELD;
                $skippedByField[$field] = ($skippedByField[$field] ?? 0) + 1;

                continue;
            }

            $record['id'] = $idGenerator->fromRecord($tenantId, $integrationId, $record, $pathConfig, $this->storeId);
            $record['tenant_id'] = $tenantId;
            $record['created_at'] = $now;
            $record['updated_at'] = $now;

            $mappedRecords[] = $record;
        }

        if ($skippedRequired > 0) {
            Log::warning('FetchIntegrationPageJob: registros descartados no mapping', [
                'integration_id' => $integrationId,
                'path_key' => $this->pathKey,
                'page' => $this->page,
                'store_id' => $this->storeId,
                'skipped' => $skippedRequired,
                'skipped_by_field' => $skippedByField,
            ]);

            ImportDiscardMetrics::record(
                $integrationId,
                $this->pathKey,
                $this->storeId,
                count($mappedRecords),
                $skippedRequired,
                $skippedByField,
            );
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

    // ─── Horizon tags ────────────────────────────────────────────────────────

    /** @return array<int, mixed> */
    public function middleware(): array
    {
        return [
            // expireAfter pouco acima do timeout (120s): um job morto por timeout
            // não segura o lock por mais tempo que o necessário.
            (new WithoutOverlapping($this->overlapKey()))
                ->releaseAfter(20)
                ->expireAfter(130),
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
            'cursor',
            $this->cursor ?? 'none',
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
