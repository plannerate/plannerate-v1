<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class TenantIntegrationController extends Controller
{
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;
        $config = is_array($integration?->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $paths = is_array($config['paths'] ?? null) ? $config['paths'] : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($integration?->integration_type ?? 'sysmo');

        $authType = (string) ($auth['type'] ?? 'basic');

        $integrationData = $integration ? [
            'id' => $integration->id,
            'integration_type' => $type,
            'is_active' => (bool) $integration->is_active,
            'last_sync' => $integration->last_sync?->toDateTimeString(),
            // Connection
            'api_url' => (string) ($connection['base_url'] ?? ''),
            'connection_headers' => $this->formatHeadersForFrontend($connection['headers'] ?? []),
            'connection_params' => is_array($connection['params'] ?? null) ? array_values((array) $connection['params']) : [],
            'connection_body' => is_array($connection['body'] ?? null) ? array_values((array) $connection['body']) : [],
            // Auth
            'auth_type' => $authType,
            'auth_username' => (string) ($credentials['username'] ?? ''),
            'auth_password' => '',
            'auth_token' => '',
            // Processing
            'sales_initial_days' => (int) ($processing['sales_initial_days'] ?? 120),
            'products_initial_days' => (int) ($processing['products_initial_days'] ?? 120),
            'processing_time' => (string) ($processing['processing_time'] ?? '02:00'),
            'separate_by_store' => (bool) ($processing['separate_by_store'] ?? false),
            // Paths
            'products_path' => (string) ($paths['products'] ?? ''),
            'sales_path' => (string) ($paths['sales'] ?? ''),
        ] : null;

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

        Storage::disk('local')->put(
            $tenant->id.'/last_payload.json',
            json_encode($request->integrationPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

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

        $payload = $this->mockConnectionTestPayload($request);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => $payload['message'],
        ]);
        Inertia::flash('tenant_integration_test', $payload);

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

    /** @return array{ok: bool, message: string, meta: array{status: int, method: string, path: string}, data: array<string, mixed>} */
    private function mockConnectionTestPayload(Request $request): array
    {
        return [
            'ok' => true,
            'message' => 'Teste de conexão mockado enquanto o novo sistema de importação é construído.',
            'meta' => [
                'status' => 200,
                'method' => strtoupper((string) ($request->string('test_method') ?: 'GET')),
                'path' => (string) ($request->string('test_path') ?: '/'),
            ],
            'data' => [
                'mocked' => true,
            ],
        ];
    }
}
