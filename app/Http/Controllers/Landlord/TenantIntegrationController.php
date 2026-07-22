<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Models\IntegrationApi;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\IntegrationHttpClient;
use App\Services\Integrations\IntegrationPayloadBuilder;
use App\Services\Integrations\Support\IntegrationPaginationMode;
use App\Services\Integrations\Support\IntegrationUrlBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TenantIntegrationController extends Controller
{
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration?->load('api');
        $config = is_array($integration?->config) ? $integration->config : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($integration?->integration_type ?? '');
        $authType = (string) ($auth['type'] ?? 'basic');

        $api = $integration?->api;
        $apiPaths = collect(data_get($api?->requests, 'paths', []))
            ->map(fn (mixed $pathConfig, string $pathKey) => [
                'key' => $pathKey,
                'path' => (string) data_get($pathConfig, 'fallback_path', '/'.$pathKey),
            ])->values()->all();

        $integrationData = $integration ? [
            'id' => $integration->id,
            'integration_type' => $type,
            'is_active' => (bool) $integration->is_active,
            'last_sync' => $integration->last_sync?->toDateTimeString(),
            // Connection
            'api_url' => (string) ($connection['base_url'] ?? ''),
            'connection_headers' => $this->formatKeyValueRowsForFrontend($connection['headers'] ?? []),
            'connection_params' => $this->formatKeyValueRowsForFrontend($connection['params'] ?? []),
            'connection_body' => $this->formatKeyValueRowsForFrontend($connection['body'] ?? []),
            // Auth
            'auth_type' => $authType,
            'auth_bearer_mode' => (string) ($auth['token_mode'] ?? 'manual'),
            'auth_token_header' => (string) ($auth['token_header'] ?? ''),
            'auth_username' => (string) ($credentials['username'] ?? ''),
            'auth_password' => '',
            'auth_token' => '',
            'auth_token_username' => (string) ($credentials['username'] ?? ''),
            'auth_token_password' => '',
            'auth_token_method' => (string) ($auth['token_request']['method'] ?? 'POST'),
            'auth_token_path' => (string) ($auth['token_request']['path'] ?? ''),
            'auth_token_response_path' => (string) ($auth['token_request']['response_path'] ?? 'token'),
            'auth_token_username_field' => (string) ($auth['token_request']['username_field'] ?? 'username'),
            'auth_token_password_field' => (string) ($auth['token_request']['password_field'] ?? 'password'),
            'auth_token_headers' => $this->formatKeyValueRowsForFrontend($auth['token_request']['headers'] ?? []),
            'auth_token_params' => $this->formatKeyValueRowsForFrontend($auth['token_request']['params'] ?? []),
            'auth_token_body' => $this->formatKeyValueRowsForFrontend($auth['token_request']['body'] ?? []),
            // API paths para seleção de teste
            'api_paths' => $apiPaths,
            'api_method' => strtolower((string) data_get($api?->requests, 'method', 'post')),
        ] : null;

        return Inertia::render('landlord/tenants/Integration', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'integration' => $integrationData,
            'integration_types' => IntegrationApi::query()
                ->where('is_active', true)
                ->get()
                ->map(fn (IntegrationApi $api) => [
                    'value' => $api->id,
                    'label' => $api->name,
                    'slug' => $api->slug,
                ])->values(),
        ]);
    }

    public function update(UpdateTenantIntegrationRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $payload = $request->integrationPayload();
        // $resolvedPayload = $this->resolvedIntegrationPayload($payload, $configResolver);
        TenantIntegration::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $payload,
        );

        $data['tenant_integration'] = [
            'model' => TenantIntegration::class,
            'data' => $payload,
        ];
        $data['integration_api'] = [
            'model' => IntegrationApi::class,
            'data' => IntegrationApi::query()->where('id', $payload['integration_type'])->first()?->toArray(),
        ];

        Storage::disk('local')->put(
            '/last_payload/'.$tenant->id.'.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_integrations.messages.updated'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolvedIntegrationPayload(array $payload, object $configResolver): array
    {
        return $payload;
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->integration?->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_integrations.messages.deleted'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
    }

    public function toggleStatus(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;

        if (! $integration instanceof TenantIntegration) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.tenant_integrations.messages.missing_configuration'),
            ]);

            return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
        }

        $integration->update(['is_active' => ! $integration->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $integration->is_active
                ? __('app.landlord.tenant_integrations.messages.activated')
                : __('app.landlord.tenant_integrations.messages.deactivated'),
        ]);

        return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
    }

    public function testConnection(Request $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;

        if (! $integration instanceof TenantIntegration) {
            Inertia::flash('tenant_integration_test', [
                'ok' => false,
                'message' => __('app.landlord.tenant_integrations.messages.missing_configuration'),
                'meta' => [],
            ]);

            return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
        }

        $config = is_array($integration->config) ? $integration->config : [];
        $requests = is_array($integration->api?->requests) ? $integration->api->requests : [];
        $pathConfig = (array) (data_get($requests, 'paths.'.(string) $request->input('test_path_key', '')) ?? []);
        $method = strtolower((string) $request->input('test_method', 'post'));

        [$url, $payload] = $pathConfig === []
            ? $this->legacyTestRequest($request, $config)
            : $this->pathTestRequest($request, $tenant, $config, $requests, $pathConfig);

        $body = array_merge($payload, $this->testBody($request));

        try {
            $client = new IntegrationHttpClient($config);
            $response = $client->call($method, $url, $body);

            Inertia::flash('tenant_integration_test', [
                'ok' => $response->successful(),
                'message' => sprintf('HTTP %d', $response->status()),
                'meta' => [
                    'status' => $response->status(),
                    'url' => $url,
                    'method' => strtoupper($method),
                ],
                'data' => $response->json() ?? $response->body(),
            ]);
        } catch (Throwable $e) {
            Inertia::flash('tenant_integration_test', [
                'ok' => false,
                'message' => $e->getMessage(),
                'meta' => [
                    'url' => $url,
                    'method' => strtoupper($method),
                ],
            ]);
        }

        return $this->toLandlordRoute('landlord.tenants.integration.edit', ['tenant' => $tenant]);
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function formatKeyValueRowsForFrontend(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        $result = [];

        foreach ($headers as $key => $value) {
            if (is_string($key) && (is_string($value) || is_numeric($value))) {
                $result[] = ['key' => $key, 'value' => (string) $value, 'enabled' => true];
            } elseif (is_array($value) && isset($value['key'])) {
                $result[] = [
                    'key' => (string) $value['key'],
                    'value' => (string) ($value['value'] ?? ''),
                    'enabled' => (bool) ($value['enabled'] ?? true),
                ];
            }
        }

        return $result;
    }

    /** @return array{ok: bool, message: string, meta: array<string, mixed>, data?: mixed} */
    private function connectionTestPayload(Request $request): array
    {
        return [
            'ok' => false,
            'message' => '',
            'meta' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Monta a chamada de teste do jeito que o import faria: resolve os
     * placeholders do `fallback_path` ({cursor}, {store_document}) e usa o
     * mesmo IntegrationPayloadBuilder do FetchIntegrationPageJob.
     *
     * Sem isso o teste enviava o path cru — a URL saía com "{cursor}" literal
     * e a resposta não dizia nada sobre a integração de verdade.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $pathConfig
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function pathTestRequest(Request $request, Tenant $tenant, array $config, array $requests, array $pathConfig): array
    {
        $storeDocument = $this->firstStoreDocument($tenant);

        $cursor = IntegrationPaginationMode::isCursor($requests, $pathConfig)
            ? IntegrationPaginationMode::initialCursor($pathConfig)
            : null;

        // A janela de datas é obrigatória em alguns endpoints (vendas): sem ela
        // a API erra. Hoje/hoje é o teste mais barato e representativo.
        $dateFields = (array) data_get($pathConfig, 'date_fields', []);
        $today = now()->toDateString();
        $dateStart = $dateFields === [] ? null : $today;
        $dateEnd = isset($dateFields['end']) ? $today : null;

        $payload = (new IntegrationPayloadBuilder($config, $requests, $pathConfig))
            ->build($dateStart, $dateEnd, $storeDocument, useMinPageSize: true);

        return [
            IntegrationUrlBuilder::build($config, $pathConfig, $cursor, $storeDocument),
            $payload,
        ];
    }

    /**
     * Blueprint sem path selecionado (ou legado): concatena o caminho informado
     * e manda o body fixo da conexão, como antes.
     *
     * @param  array<string, mixed>  $config
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function legacyTestRequest(Request $request, array $config): array
    {
        $baseUrl = rtrim((string) data_get($config, 'connection.base_url', ''), '/');
        $testPath = '/'.ltrim((string) $request->input('test_path', '/'), '/');

        $payload = [];

        foreach (data_get($config, 'connection.body', []) as $param) {
            if ($param['enabled'] ?? false) {
                $payload[(string) $param['key']] = $param['value'];
            }
        }

        return [$baseUrl.$testPath, $payload];
    }

    /**
     * Documento (só dígitos) da primeira loja publicada — mesma fonte do import.
     *
     * Defensivo de propósito: este é um painel de diagnóstico. Tenant sem banco
     * provisionado ou sem loja não pode estourar 500 e esconder o resultado do
     * teste de conexão, que é justamente o que se quer ver.
     */
    private function firstStoreDocument(Tenant $tenant): ?string
    {
        try {
            $document = $tenant->execute(fn (): mixed => Store::published()
                ->whereNotNull('document')
                ->value('document'));
        } catch (Throwable) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $document) ?? '';

        return $digits !== '' ? $digits : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function testBody(Request $request): array
    {
        $rawBody = trim((string) $request->string('test_body'));
        if ($rawBody === '') {
            return [];
        }

        $decoded = json_decode($rawBody, true);

        return is_array($decoded) ? $decoded : [];
    }
}
