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
        $headers = is_array($integration?->authentication_headers) ? $integration->authentication_headers : [];
        $body = is_array($integration?->authentication_body) ? $integration->authentication_body : [];
        $config = is_array($integration?->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : $config;

        return Inertia::render('landlord/tenants/Integration', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'integration' => $integration ? [
                'id' => $integration->id,
                'integration_type' => $integration->integration_type,
                'identifier' => $integration->identifier,
                'external_name' => $integration->external_name,
                'external_name_ean' => $integration->external_name_ean,
                'external_name_status' => $integration->external_name_status,
                'external_name_sale_date' => $integration->external_name_sale_date,
                'http_method' => $integration->http_method,
                'api_url' => $integration->api_url,
                'auth_username' => (string) ($headers['auth_username'] ?? ''),
                'auth_password' => '',
                'partner_key' => (string) ($body['partner_key'] ?? ''),
                'empresa' => (string) ($body['empresa'] ?? ''),
                'days_to_maintain' => (int) ($processing['days_to_maintain'] ?? 120),
                'sales_initial_days' => (int) ($processing['sales_initial_days'] ?? $processing['days_to_maintain'] ?? 120),
                'products_initial_days' => (int) ($processing['products_initial_days'] ?? $processing['days_to_maintain'] ?? 120),
                'daily_lookback_days' => (int) ($processing['daily_lookback_days'] ?? 7),
                'sales_page_size' => (int) ($processing['sales_page_size'] ?? 20000),
                'products_page_size' => (int) ($processing['products_page_size'] ?? 1000),
                'sales_tipo_consulta' => (string) ($processing['sales_tipo_consulta'] ?? 'produto'),
                'auto_processing_enabled' => (bool) ($processing['auto_processing_enabled'] ?? true),
                'processing_time' => (string) ($processing['processing_time'] ?? '02:00'),
                'initial_setup_date' => $processing['initial_setup_date'] ?? null,
                'is_active' => (bool) $integration->is_active,
                'last_sync' => $integration->last_sync?->toDateTimeString(),
            ] : null,
            'integration_types' => [
                ['value' => 'sysmo', 'label' => __('app.landlord.tenant_integrations.types.sysmo')],
            ],
            'http_methods' => ['GET', 'POST', 'PUT', 'PATCH'],
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
        $authenticationBody = is_array($integration->authentication_body) ? $integration->authentication_body : [];
        $endpoint = (string) ($request->string('test_path') ?: $connection['ping_path'] ?: '/');
        $method = strtoupper((string) ($request->string('test_method') ?: $connection['ping_method'] ?: 'GET'));
        $query = $request->query();
        $defaultPageSize = str_contains($endpoint, 'hubvendas')
            ? (int) ($processing['sales_page_size'] ?? 20000)
            : (int) ($processing['products_page_size'] ?? 1000);
        $body = array_merge(
            [
                'partner_key' => (string) ($authenticationBody['partner_key'] ?? ''),
                'empresa' => (string) ($authenticationBody['empresa'] ?? ''),
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
