<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Models\IntegrationApi;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use App\Services\Integrations\Http\IntegrationHttpClient;
use App\Services\Integrations\IntegrationApiConfigResolver;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
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

        $integration = $tenant->integration;
        $config = is_array($integration?->config) ? $integration->config : [];
        $processing = is_array($config['processing'] ?? null) ? $config['processing'] : [];
        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $connection = is_array($config['connection'] ?? null) ? $config['connection'] : [];
        $credentials = is_array($auth['credentials'] ?? null) ? $auth['credentials'] : [];
        $type = (string) ($integration?->integration_type ?? '');

        $authType = (string) ($auth['type'] ?? 'basic');

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
            // Processing
            'sales_initial_days' => (int) ($processing['sales_initial_days'] ?? 120),
            'products_initial_days' => (int) ($processing['products_initial_days'] ?? 120),
            'processing_time' => (string) ($processing['processing_time'] ?? '02:00'),
            'separate_by_store' => (bool) ($processing['separate_by_store'] ?? false),
        ] : null;
 
        return Inertia::render('landlord/tenants/Integration', [
            'tenant' => ['id' => $tenant->id, 'name' => $tenant->name],
            'integration' => $integrationData,
            'integration_types' => IntegrationApi::query()
                ->where('is_active', true)
                ->get()
                ->map(fn(IntegrationApi $api) => [
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

        $payload['api'] = IntegrationApi::query()->where('id', $payload['integration_type'])->first()?->toArray();

        Storage::disk('local')->put(
            $tenant->id.'/last_payload.json',
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('app.landlord.tenant_integrations.messages.updated'),
        ]);

        return to_route('landlord.tenants.integration.edit', $tenant);
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

    public function testConnection(Request $request, Tenant $tenant, IntegrationHttpClient $httpClient): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $tenant);

      

        return to_route('landlord.tenants.integration.edit', $tenant);
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
    private function connectionTestPayload(Request $request ,IntegrationHttpClient $httpClient): array
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
