<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProductsIntegrationService;
use App\Services\Integrations\Contracts\SalesIntegrationService;
use App\Services\Integrations\Sysmo\SysmoProductsIntegrationService;
use App\Services\Integrations\Sysmo\SysmoSalesIntegrationService;
use RuntimeException;

class IntegrationServiceResolver
{
    public function __construct(
        private readonly SysmoProductsIntegrationService $sysmoProductsIntegrationService,
        private readonly SysmoSalesIntegrationService $sysmoSalesIntegrationService,
    ) {}

    public function resolveProductsService(TenantIntegration $integration): ProductsIntegrationService
    {
        return match ($this->normalizeIntegrationType($integration->integration_type)) {
            'sysmo' => $this->sysmoProductsIntegrationService,
            default => throw new RuntimeException('Servico de produtos nao mapeado para este tipo de integracao: '.(string) $integration->integration_type),
        };
    }

    public function resolveSalesService(TenantIntegration $integration): SalesIntegrationService
    {
        return match ($this->normalizeIntegrationType($integration->integration_type)) {
            'sysmo' => $this->sysmoSalesIntegrationService,
            default => throw new RuntimeException('Servico de vendas nao mapeado para este tipo de integracao: '.(string) $integration->integration_type),
        };
    }

    private function normalizeIntegrationType(mixed $integrationType): string
    {
        if (! is_string($integrationType) && ! is_numeric($integrationType)) {
            return '';
        }

        return strtolower(trim((string) $integrationType));
    }
}
