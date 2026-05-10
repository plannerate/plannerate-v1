<?php

namespace App\Services\Integrations\Support;

use App\Models\TenantIntegration;

class IntegrationResponseReader
{
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
        $config = is_array($integration->config) ? $integration->config : [];
        $response = is_array($config['response'] ?? null) ? $config['response'] : [];
        $resourceResponse = is_array($response[$resource] ?? null) ? $response[$resource] : [];
        $pagination = is_array($resourceResponse['pagination'] ?? null) ? $resourceResponse['pagination'] : [];

        $configured = [
            $resourceResponse[$key] ?? null,
            $pagination[$key] ?? null,
            $response[$key] ?? null,
        ];

        return array_values(array_unique(array_filter([
            ...array_map(fn (mixed $path): string => trim((string) $path), $configured),
            ...$fallbacks,
        ], fn (string $path): bool => $path !== '')));
    }
}
