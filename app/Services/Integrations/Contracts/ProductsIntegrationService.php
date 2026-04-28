<?php

namespace App\Services\Integrations\Contracts;

use App\Models\TenantIntegration;

interface ProductsIntegrationService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function fetchProducts(TenantIntegration $integration, array $filters = []): array;
}
