<?php

namespace App\Services\Integrations\Importers;

use App\Models\Store;
use App\Models\TenantIntegration;

interface ClientApiImporter
{
    public function importSales(TenantIntegration $integration, ?Store $store = null): void;

    public function importProducts(TenantIntegration $integration, ?Store $store = null): void;
}
