<?php

namespace App\Services\Integrations\Importers;

use App\Models\TenantIntegration;

interface ClientApiImporter
{
    public function importSales(TenantIntegration $integration): void;

    public function importProducts(TenantIntegration $integration): void;
}
