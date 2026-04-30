<?php

namespace App\Services\Integrations\Sysmo\Concerns;

trait BuildsSysmoRequestBodies
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function buildProductsRequestBody(array $filters, array $defaults = []): array
    {
        $requestBody = [
            'pagina' => (int) ($filters['page'] ?? 1),
            'tamanho_pagina' => (int) ($filters['page_size'] ?? 1000),
            'partner_key' => (string) ($filters['partner_key'] ?? $defaults['partner_key'] ?? ''),
        ];

        if (is_string($filters['date'] ?? null) && $filters['date'] !== '') {
            $requestBody['data_ultima_alteracao'] = $filters['date'];
        }

        if (is_string($filters['empresa'] ?? null) && $filters['empresa'] !== '') {
            $requestBody['empresa'] = $filters['empresa'];
        } elseif (is_string($defaults['empresa'] ?? null) && $defaults['empresa'] !== '') {
            $requestBody['empresa'] = $defaults['empresa'];
        }

        return $requestBody;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function buildSingleProductRequestBody(string $produto, array $filters, array $defaults = []): array
    {
        $requestBody = [
            'partner_key' => (string) ($filters['partner_key'] ?? $defaults['partner_key'] ?? ''),
            'empresa' => (string) ($filters['empresa'] ?? $defaults['empresa'] ?? ''),
            'produto' => $produto,
        ];

        if (is_string($filters['somente_precos'] ?? null) && $filters['somente_precos'] !== '') {
            $requestBody['somente_precos'] = $filters['somente_precos'];
        }

        return $requestBody;
    }
}
