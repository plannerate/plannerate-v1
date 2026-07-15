<?php

namespace App\Services\Integrations\Lookup;

/**
 * Monta o payload de uma busca pontual (lookup) de UM produto ou suas vendas.
 *
 * Diferente do IntegrationPayloadBuilder (usado no import em massa, paginado),
 * este builder injeta o código do produto num campo específico e não depende
 * do fluxo de descoberta de páginas. Combina:
 *   1. Params/body base do config da integração (connection.body/params habilitados)
 *   2. extra_params fixos do lookup (ex.: tipo_consulta, somente_precos)
 *   3. O código do produto no campo lookup_field
 *   4. O valor da loja no campo store_field (quando configurado)
 *   5. Campos de data (start/end ou changed_since) do lookup
 *   6. Paginação (page_field=1, page_size_field=max_page_size)
 */
class LookupPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $config  Config da TenantIntegration (encrypted:array)
     * @param  array<string, mixed>  $requests  requests da IntegrationApi (top-level)
     * @param  array<string, mixed>  $lookup  Config do lookup (requests.lookups.product|sales)
     * @return array<string, mixed>
     */
    public function build(
        array $config,
        array $requests,
        array $lookup,
        string $code,
        ?string $storeValue = null,
        ?string $dateStart = null,
        ?string $dateEnd = null,
    ): array {
        $method = $this->method($requests, $lookup);

        $payload = $this->baseParams($config, $method);
        $payload = array_merge($payload, $this->extraParams($lookup));
        $payload = $this->applyLookupCode($payload, $lookup, $code);
        $payload = $this->applyStore($payload, $lookup, $storeValue);
        $payload = $this->applyDateFields($payload, $lookup, $dateStart, $dateEnd);

        return $this->applyPagination($payload, $requests, $lookup);
    }

    /** Método HTTP efetivo: lookup.method sobrepõe requests.method. */
    public function method(array $requests, array $lookup): string
    {
        return strtolower((string) (data_get($lookup, 'method') ?? data_get($requests, 'method', 'get')));
    }

    /**
     * Params base do tenant conforme o método:
     * - GET  → connection.params (query string)
     * - POST → connection.body   (request body)
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function baseParams(array $config, string $method): array
    {
        $key = match ($method) {
            'post', 'put', 'patch' => 'body',
            default => 'params',
        };

        return collect(data_get($config, "connection.$key", []))
            ->filter(fn (mixed $item): bool => is_array($item) && (bool) ($item['enabled'] ?? false))
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function extraParams(array $lookup): array
    {
        $extra = data_get($lookup, 'extra_params', []);

        return is_array($extra) ? $extra : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function applyLookupCode(array $payload, array $lookup, string $code): array
    {
        $field = (string) data_get($lookup, 'lookup_field', '');

        if ($field !== '') {
            $payload[$field] = $code;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function applyStore(array $payload, array $lookup, ?string $storeValue): array
    {
        $field = (string) data_get($lookup, 'store_field', '');

        if ($field !== '' && $storeValue !== null && $storeValue !== '') {
            $payload[$field] = $storeValue;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function applyDateFields(array $payload, array $lookup, ?string $dateStart, ?string $dateEnd): array
    {
        $dateFields = data_get($lookup, 'date_fields', []);

        if (! is_array($dateFields)) {
            return $payload;
        }

        if ($dateStart !== null) {
            if (isset($dateFields['start'])) {
                $payload[$dateFields['start']] = $dateStart;
            } elseif (isset($dateFields['changed_since'])) {
                $payload[$dateFields['changed_since']] = $dateStart;
            }
        }

        if ($dateEnd !== null && isset($dateFields['end'])) {
            $payload[$dateFields['end']] = $dateEnd;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $requests
     * @param  array<string, mixed>  $lookup
     * @return array<string, mixed>
     */
    private function applyPagination(array $payload, array $requests, array $lookup): array
    {
        $pageField = (string) data_get($requests, 'page_field', '');
        $pageSizeField = (string) data_get($requests, 'page_size_field', '');

        if ($pageField !== '') {
            $payload[$pageField] = 1;
        }

        if ($pageSizeField !== '') {
            $payload[$pageSizeField] = (int) (data_get($lookup, 'max_page_size') ?? data_get($requests, 'max_page_size', 1000));
        }

        return $payload;
    }
}
