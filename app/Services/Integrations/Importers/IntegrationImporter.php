<?php

namespace App\Services\Integrations\Importers;

use App\Models\TenantIntegration;
use InvalidArgumentException;

class IntegrationImporter
{
    public function __construct(
        private readonly SysmoImporter $sysmoImporter,
        private readonly GescooperImporter $gescooperImporter,
    ) {}

    public function importSales(TenantIntegration $integration): void
    {
        $this->resolve($integration)->importSales($integration);
    }

    public function importProducts(TenantIntegration $integration): void
    {
        $this->resolve($integration)->importProducts($integration);
    }

    private function resolve(TenantIntegration $integration): ClientApiImporter
    {
        return match ((string) $integration->integration_type) {
            'sysmo' => $this->sysmoImporter,
            'gescooper' => $this->gescooperImporter,
            default => throw new InvalidArgumentException(sprintf(
                'Importador não configurado para integração [%s].',
                (string) $integration->integration_type,
            )),
        };
    }
}
