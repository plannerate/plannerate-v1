<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\TenantIntegration;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\TenantIntegrationConfigNormalizer;
use RuntimeException;

class SysmoSingleProductIntegrationService
{
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

        $requestBody = [
            'partner_key' => (string) ($filters['partner_key'] ?? $processing['partner_key'] ?? ''),
            'empresa' => (string) ($filters['empresa'] ?? $processing['empresa'] ?? ''),
            'produto' => $produtoValue,
        ];

        if (is_string($filters['somente_precos'] ?? null) && $filters['somente_precos'] !== '') {
            $requestBody['somente_precos'] = $filters['somente_precos'];
        }

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
        if (is_array($payload['data'] ?? null)) {
            $data = $payload['data'];
            if (array_is_list($data)) {
                return isset($data[0]) && is_array($data[0]) ? $data[0] : null;
            }

            return $data;
        }

        if (is_array($payload['dados'] ?? null)) {
            $dados = $payload['dados'];
            if (array_is_list($dados)) {
                return isset($dados[0]) && is_array($dados[0]) ? $dados[0] : null;
            }

            return $dados;
        }

        if (is_array($payload)) {
            return $payload;
        }

        return null;
    }
}
