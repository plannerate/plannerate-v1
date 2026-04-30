<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\TenantIntegration;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use App\Services\Integrations\Sysmo\Concerns\BuildsSysmoRequestBodies;
use App\Services\Integrations\Sysmo\Concerns\ExtractsSysmoPayloadItems;
use RuntimeException;

class SysmoSingleProductIntegrationService
{
    use BuildsSysmoRequestBodies;
    use ExtractsSysmoPayloadItems;

    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoProductsResponseMapper $responseMapper,
        private readonly SysmoProductsIntegrationService $sysmoProductsIntegrationService,
        private readonly TenantIntegrationConfigNormalizer $configNormalizer,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fetchAndPersist(TenantIntegration $integration, string $produto, array $filters = []): array
    {
        $produtoValue = trim($produto);
        if ($produtoValue === '') {
            throw new RuntimeException('Parâmetro "produto" é obrigatório.');
        }

        $normalized = $this->configNormalizer->normalize($integration);
        $processing = is_array($normalized['processing'] ?? null) ? $normalized['processing'] : [];

        $requestBody = $this->buildSingleProductRequestBody(
            produto: $produtoValue,
            filters: $filters,
            defaults: [
                'partner_key' => $processing['partner_key'] ?? '',
                'empresa' => $processing['empresa'] ?? '',
            ],
        );

        $response = $this->externalApiBaseService->request(
            integration: $integration,
            method: strtoupper((string) $integration->http_method),
            endpoint: $this->sysmoEndpoints->get('product'),
            body: $requestBody,
        );

        $payload = is_array($response->json()) ? $response->json() : [];
        $item = $this->extractItem($payload);

        if ($item === null) {
            return [
                'found' => false,
                'produto' => $produtoValue,
                'request' => $requestBody,
            ];
        }

        $mapped = $this->responseMapper->mapMany([$item]);

        $this->sysmoProductsIntegrationService->persistMappedProducts(
            tenantId: (string) $integration->tenant_id,
            source: (string) ($integration->integration_type ?: 'sysmo'),
            mappedItems: $mapped,
        );

        $this->sysmoProductsIntegrationService->finalizePersistedProductsSync((string) $integration->tenant_id);

        return [
            'found' => true,
            'produto' => $produtoValue,
            'request' => $requestBody,
            'mapped_item' => $mapped[0] ?? null,
            'raw_item' => $item,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function extractItem(array $payload): ?array
    {
        if (is_array($payload['data'] ?? null) && ! array_is_list($payload['data'])) {
            return $payload['data'];
        }

        if (is_array($payload['dados'] ?? null) && ! array_is_list($payload['dados'])) {
            return $payload['dados'];
        }

        $items = $this->extractItemsFromPayload($payload);
        if ($items !== []) {
            return $items[0] ?? null;
        }

        return $payload !== [] ? $payload : null;
    }
}
