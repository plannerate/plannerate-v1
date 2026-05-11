<?php

namespace App\Services\Integrations;

use App\Models\TenantIntegration;
use App\Services\Integrations\Support\ResolvedIntegrationConfig;

class ResolvedIntegrationConfigResolver
{
    public function __construct(
        private readonly IntegrationApiConfigResolver $apiConfigResolver,
    ) {}

    public function resolve(TenantIntegration $integration): ResolvedIntegrationConfig
    {
        $apiConfig = $this->apiConfigResolver->provider((string) $integration->integration_type);
        $tenantConfig = is_array($integration->config) ? $integration->config : [];

        return new ResolvedIntegrationConfig(
            integration: $integration,
            apiConfig: $apiConfig,
            tenantConfig: $tenantConfig,
        );
    }

    /**
     * @param  array<string, mixed>  $tenantPayload
     * @return array<string, mixed>
     */
    public function resolvedPayload(array $tenantPayload): array
    {
        $apiConfig = $this->apiConfigResolver->provider((string) ($tenantPayload['integration_type'] ?? ''));
        $tenantConfig = is_array($tenantPayload['config'] ?? null) ? $tenantPayload['config'] : [];

        // Se não houver requests na API config, tenta usar do tenantConfig
        if (empty($apiConfig['requests']) && isset($tenantConfig['requests'])) {
            $apiConfig['requests'] = $tenantConfig['requests'];
        }
        // Se não houver response na API config, tenta usar do tenantConfig
        if (empty($apiConfig['response']) && isset($tenantConfig['response'])) {
            $apiConfig['response'] = $tenantConfig['response'];
        }

        return [
            ...$tenantPayload,
            'config' => array_replace_recursive($apiConfig, $tenantConfig),
        ];
    }
}
