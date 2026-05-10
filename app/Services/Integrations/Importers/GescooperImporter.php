<?php

namespace App\Services\Integrations\Importers;

use App\Jobs\Integrations\Imports\ProcessImportedProductsBatchJob;
use App\Jobs\Integrations\Imports\ProcessImportedSalesBatchJob;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\Support\ImportBatchPayloadStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GescooperImporter implements ClientApiImporter
{
    public function __construct(
        private readonly IntegrationHttpClient $httpClient,
        private readonly ImportBatchPayloadStore $importBatchPayloadStore,
    ) {}

    public function importSales(TenantIntegration $integration, ?Store $store = null): void
    {
        $path = $this->path($integration, 'sales', '');

        if ($path !== '') {
            $query = $this->storeQuery($store);
            $token = $this->accessToken($integration);

            $this->logRequestPayload('sales', $integration, $store, $path, $query);

            try {
                $response = $this->httpClient->request(
                    integration: $integration,
                    method: 'GET',
                    endpoint: $path,
                    query: $query,
                    bearerToken: $token,
                );
            } catch (RequestException $exception) {
                if ($exception->response?->status() === 404) {
                    Log::info('GesCooper sales import skipped: endpoint retornou 404.', [
                        'integration_id' => (string) $integration->id,
                        'tenant_id' => (string) $integration->tenant_id,
                        'store_id' => $store?->id,
                        'endpoint' => $path,
                    ]);

                    return;
                }

                throw $exception;
            }

            $payload = $response->json();
            $items = $this->resolveItems(is_array($payload) ? $payload : []);

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, 'sales', $items);
            ProcessImportedSalesBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: 'gescooper',
                payloadKey: $payloadKey,
                storeId: $store?->id,
                storeDocument: $store?->document,
            );

            Log::info('GesCooper sales import request completed.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'items' => count($items),
                'status' => $response->status(),
            ]);

            return;
        }

        Log::info('GesCooper sales import skipped: endpoint ainda não definido.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
        ]);
    }

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void
    {
        $endpoint = $this->path($integration, 'products', '/Produtos/Produtos');
        $token = $this->accessToken($integration);
        $currentPage = 1;
        $totalPages = 1;

        do {
            $query = [
                ...$this->storeQuery($store),
                ...$this->productsDateQuery($integration, $store),
                'pagina' => $currentPage,
                'registros_por_pagina' => 1000,
                'api-version' => '1.0',
            ];

            $this->logRequestPayload('products', $integration, $store, $endpoint, $query);

            $response = $this->httpClient->request(
                integration: $integration,
                method: 'GET',
                endpoint: $endpoint,
                query: $query,
                bearerToken: $token,
            );

            $payload = $response->json();
            $totalPages = $this->resolveTotalPages(is_array($payload) ? $payload : [], $currentPage);
            $items = $this->resolveItems(is_array($payload) ? $payload : []);

            $payloadKey = $this->importBatchPayloadStore->put((string) $integration->id, 'products', $items);
            ProcessImportedProductsBatchJob::dispatch(
                integrationId: (string) $integration->id,
                provider: 'gescooper',
                payloadKey: $payloadKey,
                storeId: $store?->id,
            );

            Log::info('GesCooper products import page fetched.', [
                'integration_id' => (string) $integration->id,
                'tenant_id' => (string) $integration->tenant_id,
                'store_id' => $store?->id,
                'page' => $currentPage,
                'total_pages' => $totalPages,
                'items' => count($items),
                'status' => $response->status(),
            ]);

            $currentPage++;
        } while ($currentPage <= $totalPages);
    }

    /**
     * @return array{empresa: string}|array{}
     */
    private function storeQuery(?Store $store): array
    {
        $document = $this->storeDocument($store);

        return $document !== '' ? ['empresa' => $document] : [];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function logRequestPayload(
        string $resource,
        TenantIntegration $integration,
        ?Store $store,
        string $endpoint,
        array $query,
    ): void {
        Log::info('GesCooper import request params.', [
            'resource' => $resource,
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'store_id' => $store?->id,
            'store_document' => $store?->document,
            'store_document_normalized' => $this->storeDocument($store),
            'method' => 'GET',
            'endpoint' => $endpoint,
            'params' => $query,
        ]);
    }

    private function path(TenantIntegration $integration, string $key, string $fallback): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];
        $path = trim((string) ($paths[$key] ?? ''));

        return $path !== '' ? $path : $fallback;
    }

    private function storeDocument(?Store $store): string
    {
        return preg_replace('/\D+/', '', (string) $store?->document) ?? '';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveTotalPages(array $payload, int $currentPage): int
    {
        $candidates = [
            $payload['total_paginas'] ?? null,
            $payload['totalPaginas'] ?? null,
            $payload['total_pages'] ?? null,
            $payload['last_page'] ?? null,
            is_array($payload['pagination'] ?? null) ? ($payload['pagination']['last_page'] ?? null) : null,
            is_array($payload['pagination'] ?? null) ? ($payload['pagination']['total_pages'] ?? null) : null,
            is_array($payload['meta'] ?? null) ? ($payload['meta']['last_page'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_numeric($candidate)) {
                return max($currentPage, (int) $candidate);
            }
        }

        return $currentPage;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveItemCount(array $payload): int
    {
        $items = $payload['data'] ?? null;

        return is_array($items) ? count($items) : 0;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function resolveItems(array $payload): array
    {
        $items = $payload['data'] ?? $payload['dados'] ?? null;
        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
    }

    private function accessToken(TenantIntegration $integration): string
    {
        $cacheKey = $this->tokenCacheKey($integration);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->requestToken($integration);
        Cache::put($cacheKey, $token, $this->tokenExpiration($token));

        return $token;
    }

    private function tokenCacheKey(TenantIntegration $integration): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');

        return sprintf(
            'integrations:gescooper:token:%s:%s',
            (string) $integration->id,
            sha1($username.'|'.$password),
        );
    }

    private function requestToken(TenantIntegration $integration): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $username = (string) ($credentials['username'] ?? '');
        $password = (string) ($credentials['password'] ?? '');
        $deviceUid = (string) ($credentials['dispositivo_uid'] ?? 'plannerate');

        if ($username === '' || $password === '') {
            throw new RuntimeException('Credenciais da GesCooper não configuradas para gerar token.');
        }

        $response = Http::timeout(60)
            ->connectTimeout(15)
            ->acceptJson()
            ->withHeaders(['Content-Type' => 'application/json-patch+json'])
            ->post($this->authUrl($integration), [
                'usuario' => $username,
                'senha' => $password,
                'dispositivoUID' => $deviceUid,
            ])
            ->throw();

        $payload = $response->json();
        $token = $this->extractToken(is_array($payload) ? $payload : []);
        if ($token === '') {
            throw new RuntimeException('Token da GesCooper não encontrado na resposta de autenticação.');
        }

        Log::info('GesCooper token refreshed.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'expires_at' => $this->tokenExpiration($token)->toDateTimeString(),
        ]);

        return $token;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractToken(array $payload): string
    {
        $candidates = [
            $payload['token'] ?? null,
            $payload['access_token'] ?? null,
            $payload['jwt'] ?? null,
            is_array($payload['data'] ?? null) ? ($payload['data']['token'] ?? null) : null,
            is_array($payload['data'] ?? null) ? ($payload['data']['access_token'] ?? null) : null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function tokenExpiration(string $token): CarbonImmutable
    {
        $parts = explode('.', $token);
        if (count($parts) === 3) {
            $decoded = json_decode(base64_decode(strtr($parts[1], '-_', '+/')) ?: '', true);
            $exp = is_array($decoded) ? ($decoded['exp'] ?? null) : null;
            if (is_numeric($exp)) {
                $expiration = CarbonImmutable::createFromTimestamp((int) $exp)->subMinutes(1);

                return $expiration->isFuture()
                    ? $expiration
                    : CarbonImmutable::now()->addMinutes(30);
            }
        }

        return CarbonImmutable::now()->addMinutes(55);
    }

    private function authUrl(TenantIntegration $integration): string
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $baseUrl = rtrim((string) ($connection['base_url'] ?? ''), '/');
        $path = $this->path($integration, 'auth', '/v1/Token');

        if ($baseUrl === '') {
            throw new RuntimeException('Base URL da GesCooper não configurada para autenticação.');
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $baseUrl.'/'.ltrim($path, '/');
    }

    /**
     * @return array{data_cadastro_de: string, data_cadastro_ate: string}|array{}
     */
    private function productsDateQuery(TenantIntegration $integration, ?Store $store = null): array
    {
        if (! $this->tenantHasProducts($integration, $store)) {
            return [];
        }

        return [
            'data_cadastro_de' => Carbon::yesterday()->startOfDay()->toIso8601String(),
            'data_cadastro_ate' => Carbon::now()->toIso8601String(),
        ];
    }

    private function tenantHasProducts(TenantIntegration $integration, ?Store $store = null): bool
    {
        $tenant = $integration->tenant;
        if (! $tenant instanceof Tenant) {
            return false;
        }

        return $tenant->execute(function () use ($integration, $store, $tenant): bool {
            $connection = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
            $isSeparateByStore = $this->separateByStore($integration);

            if ($isSeparateByStore && $store instanceof Store && is_string($store->id) && $store->id !== '') {
                return DB::connection($connection)
                    ->table('product_store')
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('store_id', (string) $store->id)
                    ->exists();
            }

            return DB::connection($connection)
                ->table('products')
                ->where('tenant_id', (string) $tenant->id)
                ->whereNull('deleted_at')
                ->exists();
        });
    }

    private function separateByStore(TenantIntegration $integration): bool
    {
        $config = is_array($integration->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];

        return (bool) ($processing['separate_by_store'] ?? false);
    }
}
