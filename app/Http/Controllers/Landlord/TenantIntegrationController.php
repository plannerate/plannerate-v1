<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Requests\Landlord\UpdateTenantIntegrationRequest;
use App\Models\Tenant;
use App\Models\TenantIntegration;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class TenantIntegrationController extends Controller
{
    public function edit(Tenant $tenant): Response
    {
        $this->authorize('update', $tenant);

        $integration = $tenant->integration;
        $headers = is_array($integration?->authentication_headers) ? $integration->authentication_headers : [];
        $body = is_array($integration?->authentication_body) ? $integration->authentication_body : [];
        $config = is_array($integration?->config) ? $integration->config : [];

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
                'days_to_maintain' => (int) ($config['days_to_maintain'] ?? 120),
                'auto_processing_enabled' => (bool) ($config['auto_processing_enabled'] ?? true),
                'processing_time' => (string) ($config['processing_time'] ?? '02:00'),
                'initial_setup_date' => $config['initial_setup_date'] ?? null,
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
}
