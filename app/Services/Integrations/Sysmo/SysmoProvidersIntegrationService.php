<?php

namespace App\Services\Integrations\Sysmo;

use App\Models\Provider;
use App\Models\TenantIntegration;
use App\Services\Integrations\Contracts\ProvidersIntegrationService;
use App\Services\Integrations\ExternalApiBaseService;
use App\Services\Integrations\Support\DeterministicIdGenerator;
use App\Services\Integrations\Sysmo\Concerns\ExtractsSysmoPayloadItems;
use App\Services\Integrations\Sysmo\Concerns\NormalizesSysmoValues;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SysmoProvidersIntegrationService implements ProvidersIntegrationService
{
    use ExtractsSysmoPayloadItems;
    use NormalizesSysmoValues;

    private const PROVIDERS_UPSERT_CHUNK_SIZE = 500;

    public function __construct(
        private readonly ExternalApiBaseService $externalApiBaseService,
        private readonly SysmoEndpoints $sysmoEndpoints,
        private readonly SysmoProvidersResponseMapper $responseMapper,
        private readonly DeterministicIdGenerator $deterministicIdGenerator,
    ) {}

    public function fetchProviders(TenantIntegration $integration, array $filters = []): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 500),
            'partner_key' => (string) ($filters['partner_key'] ?? ''),
        ];

        $response = $this->externalApiBaseService->request(
            integration: $integration,
            method: strtoupper((string) $integration->http_method),
            endpoint: $this->sysmoEndpoints->get('providers'),
            body: $requestBody,
        );

        $responsePayload = $response->json();

        Log::info('Sysmo providers API response received.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'page' => $requestBody['pagina'],
            'http_status' => $response->status(),
            'total_paginas' => $responsePayload['total_paginas'] ?? null,
            'dados_count' => is_array($responsePayload['dados'] ?? null) ? count($responsePayload['dados']) : null,
            'raw_keys' => is_array($responsePayload) ? array_keys($responsePayload) : null,
        ]);

        $mappedItems = $this->responseMapper->mapMany($this->extractItemsFromPayload($responsePayload));

        $this->persistMappedProviders(
            tenantId: (string) $integration->tenant_id,
            mappedItems: $mappedItems,
        );

        Log::info('Sysmo providers sync persisted.', [
            'integration_id' => (string) $integration->id,
            'tenant_id' => (string) $integration->tenant_id,
            'page' => $requestBody['pagina'],
            'mapped_count' => count($mappedItems),
        ]);

        return $mappedItems;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappedItems
     */
    public function persistMappedProviders(
        string $tenantId,
        array $mappedItems,
    ): void {
        if ($tenantId === '' || $mappedItems === []) {
            return;
        }

        $tenantConnectionName = (string) (config('multitenancy.tenant_database_connection_name') ?: config('database.default'));
        $connection = DB::connection($tenantConnectionName);
        $now = Carbon::now();

        $providerRows = [];
        $addressRows = [];
        $skipped = 0;

        foreach ($mappedItems as $item) {
            $code = $this->normalizeString($item['code'] ?? null);

            if ($code === null) {
                $skipped++;

                continue;
            }

            $providerId = $this->deterministicIdGenerator->providerId($tenantId, $code);

            $providerRows[] = [
                'id' => $providerId,
                'tenant_id' => $tenantId,
                'code' => $code,
                'name' => $this->normalizeString($item['name'] ?? null),
                'cnpj' => $this->normalizeString($item['cnpj'] ?? null),
                'description' => $this->normalizeString($item['description'] ?? null),
                'is_default' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $street = $this->normalizeString($item['address_street'] ?? null);
            $city = $this->normalizeString($item['address_city'] ?? null);
            $state = $this->normalizeString($item['address_state'] ?? null);

            if ($street !== null || $city !== null || $state !== null) {
                $rawZip = $this->normalizeString($item['address_zip_code'] ?? null);
                $zipCode = $rawZip !== null ? preg_replace('/\D/', '', $rawZip) : null;

                $addressRows[] = [
                    'id' => $this->deterministicIdGenerator->providerAddressId($tenantId, $code),
                    'tenant_id' => $tenantId,
                    'addressable_type' => Provider::class,
                    'addressable_id' => $providerId,
                    'street' => $street,
                    'district' => $this->normalizeString($item['address_district'] ?? null),
                    'city' => $city,
                    'state' => $state,
                    'zip_code' => $zipCode,
                    'complement' => $this->normalizeString($item['address_complement'] ?? null),
                    'country' => 'Brasil',
                    'is_default' => true,
                    'status' => 'published',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($skipped > 0) {
            Log::warning('Integrations providers sync skipped items without code.', [
                'tenant_id' => $tenantId,
                'skipped_count' => $skipped,
            ]);
        }

        foreach (array_chunk($providerRows, self::PROVIDERS_UPSERT_CHUNK_SIZE) as $chunk) {
            $connection->table('providers')->upsert(
                $chunk,
                ['tenant_id', 'code'],
                ['name', 'cnpj', 'description', 'updated_at'],
            );
        }

        foreach ($addressRows as $addressRow) {
            $connection->table('addresses')->updateOrInsert(
                [
                    'addressable_type' => $addressRow['addressable_type'],
                    'addressable_id' => $addressRow['addressable_id'],
                ],
                $addressRow,
            );
        }
    }
}
