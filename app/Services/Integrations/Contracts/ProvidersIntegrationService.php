<?php

namespace App\Services\Integrations\Contracts;

use App\Models\TenantIntegration;

interface ProvidersIntegrationService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchProviders(TenantIntegration $integration, array $filters = []): array;
}
