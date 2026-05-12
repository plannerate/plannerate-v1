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
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function items(ResolvedIntegrationConfig|TenantIntegration $config, string $resource, array $payload): array
    {
        $config = $this->resolveConfig($config);

        foreach ($this->itemsPaths($config, $resource) as $path) {
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
    public function totalPages(ResolvedIntegrationConfig|TenantIntegration $config, string $resource, array $payload, int $currentPage): int
    {
        $config = $this->resolveConfig($config);

        foreach ($this->lastPagePaths($config, $resource) as $path) {
            $candidate = data_get($payload, $path);

            if (is_numeric($candidate)) {
                return max($currentPage, (int) $candidate);
            }
        }

        return $currentPage;
    }

    /** @return list<string> */
    private function itemsPaths(ResolvedIntegrationConfig $config, string $resource): array
    {
        return $this->paths($config, $resource, 'items_path', [
            'data',
            'dados',
            'items',
            'results',
        ]);
    }

    /** @return list<string> */
    private function lastPagePaths(ResolvedIntegrationConfig $config, string $resource): array
    {
        return $this->paths($config, $resource, 'last_page_path', [
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
    private function paths(ResolvedIntegrationConfig $config, string $resource, string $key, array $fallbacks): array
    {
        $providerResponse = $config->response();
        $responseConfigs = $providerResponse !== [] ? [$providerResponse] : [];
        $configured = [];

        foreach ($responseConfigs as $response) {
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

    private function resolveConfig(ResolvedIntegrationConfig|TenantIntegration $config): ResolvedIntegrationConfig
    {
        if ($config instanceof ResolvedIntegrationConfig) {
            return $config;
        }

        return ($this->configResolver ?? app(ResolvedIntegrationConfigResolver::class))->resolve($config);
    }
}
