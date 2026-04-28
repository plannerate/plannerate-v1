<?php

namespace App\Services\Integrations\Contracts;

use App\Models\TenantIntegration;

interface SalesIntegrationService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchSales(TenantIntegration $integration, array $filters = []): array;
}
