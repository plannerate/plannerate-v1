<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;
use App\Services\Integrations\ResolvedIntegrationConfigResolver;

class IntegrationResponseReader
{
    public function __construct(
        private readonly ?ResolvedIntegrationConfigResolver $configResolver = null,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function items(TenantIntegration $integration, string $resource, array $payload): array
    {
        foreach ($this->itemsPaths($integration, $resource) as $path) {
            $items = data_get($payload, $path);

            if (is_array($items)) {
                return array_values(array_filter($items, fn (mixed $item): bool => is_array($item)));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function totalPages(TenantIntegration $integration, string $resource, array $payload, int $currentPage): int
    {
        foreach ($this->lastPagePaths($integration, $resource) as $path) {
            $candidate = data_get($payload, $path);

            if (is_numeric($candidate)) {
                return max($currentPage, (int) $candidate);
            }
        }

        return $currentPage;
    }

    /**
     * @return list<string>
     */
    private function itemsPaths(TenantIntegration $integration, string $resource): array
    {
        return $this->paths($integration, $resource, 'items_path', [
            'data',
            'dados',
            'items',
            'results',
        ]);
    }

    /**
     * @return list<string>
     */
    private function lastPagePaths(TenantIntegration $integration, string $resource): array
    {
        return $this->paths($integration, $resource, 'last_page_path', [
            'pagination.last_page',
            'pagination.total_pages',
            'meta.last_page',
            'total_paginas',
            'totalPaginas',
            'total_pages',
            'last_page',
        ]);
    }

    /**
     * @param  list<string>  $fallbacks
     * @return list<string>
     */
    private function paths(TenantIntegration $integration, string $resource, string $key, array $fallbacks): array
    {
        $configured = [];

        foreach ($this->responseConfigs($integration) as $response) {
            $resourceResponse = is_array($response[$resource] ?? null) ? $response[$resource] : [];
            $responsePagination = is_array($response['pagination'] ?? null) ? $response['pagination'] : [];
            $resourcePagination = is_array($resourceResponse['pagination'] ?? null) ? $resourceResponse['pagination'] : [];

            $configured[] = $resourceResponse[$key] ?? null;
            $configured[] = $resourcePagination[$key] ?? null;
            $configured[] = $response[$key] ?? null;
            $configured[] = $responsePagination[$key] ?? null;
        }

        return array_values(array_unique(array_filter([
            ...array_map(fn (mixed $path): string => trim((string) $path), $configured),
            ...$fallbacks,
        ], fn (string $path): bool => $path !== '')));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function responseConfigs(TenantIntegration $integration): array
    {
        $providerResponse = ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))
            ->resolve($integration)
            ->response();

        return array_values(array_filter([
            $providerResponse,
        ], fn (array $response): bool => $response !== []));
    }
}
