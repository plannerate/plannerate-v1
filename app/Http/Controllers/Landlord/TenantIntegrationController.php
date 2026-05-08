<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TenantIntegrationController extends Controller
{
    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;
        $config = is_array($integration?->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($integration?->integration_type ?? 'sysmo');

        $authType = (string) ($auth['type'] ?? 'basic');

        $integrationData = $integration ? [
            'id' => $integration->id,
            'integration_type' => $type,
            'identifier' => $integration->identifier,
            'is_active' => (bool) $integration->is_active,
            'last_sync' => $integration->last_sync?->toDateTimeString(),
            'auto_processing_enabled' => (bool) ($processing['auto_processing_enabled'] ?? true),
            'processing_time' => (string) ($processing['processing_time'] ?? '02:00'),
            'initial_setup_date' => $processing['initial_setup_date'] ?? null,
            'products_page_size' => (int) ($processing['products_page_size'] ?? ($type === 'gescooper' ? 200 : 1000)),
            // Connection
            'api_url' => (string) ($connection['base_url'] ?? ''),
            'connection_headers' => $this->formatHeadersForFrontend($connection['headers'] ?? []),
            'connection_params' => is_array($connection['params'] ?? null) ? array_values((array) $connection['params']) : [],
            'default_body' => (string) ($connection['default_body'] ?? ''),
            // Auth — common to all types
            'auth_type' => $authType,
            'auth_username' => (string) ($credentials['username'] ?? ''),
            'auth_password' => '',
            'auth_token' => '',
            'auth_api_key' => '',
            'auth_api_key_name' => (string) ($credentials['name'] ?? ''),
            // GesCooper credentials
            'usuario' => (string) ($credentials['usuario'] ?? ''),
            'senha' => '',
            'dispositivo_uid' => (string) ($credentials['dispositivo_uid'] ?? ''),
        ] : null;

        if ($integrationData !== null && $type === 'sysmo') {
            $integrationData = array_merge($integrationData, [
                'partner_key' => (string) ($processing['partner_key'] ?? ''),
                'empresa' => (string) ($processing['empresa'] ?? ''),
                'external_name' => (string) ($processing['external_name'] ?? 'produto'),
                'external_name_ean' => (string) ($processing['external_name_ean'] ?? ''),
                'external_name_status' => (string) ($processing['external_name_status'] ?? ''),
                'external_name_sale_date' => (string) ($processing['external_name_sale_date'] ?? ''),
                'days_to_maintain' => (int) ($processing['days_to_maintain'] ?? 120),
                'sales_initial_days' => (int) ($processing['sales_initial_days'] ?? 120),
                'products_initial_days' => (int) ($processing['products_initial_days'] ?? 120),
                'daily_lookback_days' => (int) ($processing['daily_lookback_days'] ?? 7),
                'sales_page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                'sales_tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
            ]);
        }

        return Inertia::render('landlord/tenants/Integration', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'integration' => $integrationData,
            'integration_types' => [
                ['value' => 'sysmo',     'label' => __('app.landlord.tenant_integrations.types.sysmo')],
                ['value' => 'gescooper', 'label' => __('app.landlord.tenant_integrations.types.gescooper')],
            ],
        ]);
    }

    public function update(UpdateTenantIntegrationRequest $request, Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        TenantIntegration::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            $request->integrationPayload(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_integrations.messages.updated'),
        ]);

        return to_route('landlord.tenants.integration.edit', $tenant);
    }

    public function destroy(Tenant $tenant): RedirectResponse
    {
        $this->authorize('update', $tenant);

        $tenant->integration?->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_integrations.messages.deleted'),
        ]);

        return to_route('landlord.tenants.integration.edit', $tenant);
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

            return to_route('landlord.tenants.integration.edit', $tenant);
        }

        $integration->update(['is_active' => ! $integration->is_active]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $integration->is_active
                ? __('app.landlord.tenant_integrations.messages.activated')
                : __('app.landlord.tenant_integrations.messages.deactivated'),
        ]);

        return to_route('landlord.tenants.integration.edit', $tenant);
    }

    public function testConnection(Request $request, Tenant $tenant): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;

        if (! $integration instanceof TenantIntegration) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => __('app.landlord.tenant_integrations.messages.missing_configuration'),
                ], 422);
            }

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.tenant_integrations.messages.missing_configuration'),
            ]);
            Inertia::flash('tenant_integration_test', [
                'ok' => false,
                'message' => __('app.landlord.tenant_integrations.messages.missing_configuration'),
            ]);

            return to_route('landlord.tenants.integration.edit', $tenant);
        }

        $normalized = $this->configNormalizer->normalize($integration);
        $connection = $normalized['connection'];
        $processing = $normalized['processing'];
        $endpoint = (string) ($request->string('test_path') ?: $connection['ping_path'] ?: '/');
        $method = strtoupper((string) ($request->string('test_method') ?: $connection['ping_method'] ?: 'GET'));
        $query = $request->query();
        $defaultPageSize = str_contains($endpoint, 'hubvendas')
            ? (int) ($processing['sales_page_size'] ?? 20000)
            : (int) ($processing['products_page_size'] ?? 1000);
        $body = array_merge(
            [
                'partner_key' => (string) ($processing['partner_key'] ?? ''),
                'empresa' => (string) ($processing['empresa'] ?? ''),
                'pagina' => 1,
                'tamanho_pagina' => max(1, $defaultPageSize),
            ],
            $this->decodeJsonBody((string) $request->input('test_body', ''))
        );

        try {
            Log::info('Tenant integration test request', [
                'tenant_id' => $tenant->id,
                'integration_id' => $integration->id,
                'method' => $method,
                'endpoint' => $endpoint,
                'query' => $query,
                'body' => $body,
            ]);

            $response = $this->externalApiBaseService->request(
                integration: $integration,
                method: $method,
                endpoint: $endpoint,
                query: $query,
                body: $body,
            );

            $integration->update([
                'last_sync' => now(),
            ]);

            $payload = $response->json();
            $responseBody = is_array($payload) ? $payload : ['raw' => $response->body()];

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => __('app.landlord.tenant_integrations.messages.connection_success'),
                    'meta' => [
                        'status' => $response->status(),
                        'method' => $method,
                        'path' => $endpoint,
                    ],
                    'data' => $responseBody,
                ]);
            }

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('app.landlord.tenant_integrations.messages.connection_success'),
            ]);
            Inertia::flash('tenant_integration_test', [
                'ok' => true,
                'message' => __('app.landlord.tenant_integrations.messages.connection_success'),
                'meta' => [
                    'status' => $response->status(),
                    'method' => $method,
                    'path' => $endpoint,
                ],
                'data' => $responseBody,
            ]);
        } catch (RuntimeException $exception) {
            Log::error('Tenant integration test failed', [
                'tenant_id' => $tenant->id,
                'integration_id' => $integration->id,
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => __('app.landlord.tenant_integrations.messages.connection_failed', [
                        'error' => $exception->getMessage(),
                    ]),
                    'meta' => [
                        'method' => $method,
                        'path' => $endpoint,
                    ],
                ], 422);
            }

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('app.landlord.tenant_integrations.messages.connection_failed', [
                    'error' => $exception->getMessage(),
                ]),
            ]);
            Inertia::flash('tenant_integration_test', [
                'ok' => false,
                'message' => __('app.landlord.tenant_integrations.messages.connection_failed', [
                    'error' => $exception->getMessage(),
                ]),
                'meta' => [
                    'method' => $method,
                    'path' => $endpoint,
                ],
            ]);
        }

        return to_route('landlord.tenants.integration.edit', $tenant);
    }

    /**
     * @return array<int, array{key: string, value: string, enabled: bool}>
     */
    private function formatHeadersForFrontend(mixed $headers): array
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

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonBody(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
