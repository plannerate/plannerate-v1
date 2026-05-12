<?php

namespace App\Services\Integrations;

/**
 * Monta o array de parâmetros para uma chamada de integração.
 *
 * Combina três fontes:
 *   1. Params/body base do config da integração
 *   2. Campos de paginação (page=1, page_size=max)
 *   3. Campos de data do path (start/end ou changed_since)
 */
class IntegrationPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $config  Config da TenantIntegration
     * @param  array<string, mixed>  $requests  Requests da IntegrationApi
     * @param  array<string, mixed>  $pathConfig  Config do path (products/sales/...)
     */
    public function __construct(
        private readonly array $config,
        private readonly array $requests,
        private readonly array $pathConfig,
    ) {}

    /**
     * Monta o payload final para a página 1.
     *
     * @param  bool  $useMinPageSize  Usa min_page_size em vez de max (para chamadas de descoberta)
     * @return array<string, mixed>
     */
    public function build(?string $dateStart, ?string $dateEnd, ?string $storeDocument = null, bool $useMinPageSize = false): array
    {
        $method = strtolower((string) data_get($this->requests, 'method', 'get'));

        $payload = $this->baseParams($method);
        $payload = $this->applyPagination($payload, $useMinPageSize);
        $payload = $this->applyDateFields($payload, $dateStart, $dateEnd);
        $payload = $this->applyStoreDocument($payload, $storeDocument);

        return $payload;
    }

    // ─── Base params ─────────────────────────────────────────────────────────

    /**
     * Lê os parâmetros base da integração conforme o método HTTP:
     * - GET  → connection.params (query string)
     * - POST → connection.body   (request body)
     *
     * @return array<string, mixed>
     */
    private function baseParams(string $method): array
    {
        $key = match ($method) {
            'post', 'put', 'patch' => 'body',
            default => 'params',
        };

        return collect(data_get($this->config, "connection.$key", []))
            ->filter(fn (array $item): bool => (bool) ($item['enabled'] ?? false))
            ->pluck('value', 'key')
            ->toArray();
    }

    // ─── Paginação ───────────────────────────────────────────────────────────

    /**
     * Força page=1 e page_size.
     *
     * Para descoberta ($useMinPageSize=true) usa min_page_size para evitar carregar
     * registros desnecessários — só precisamos do total de páginas.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyPagination(array $payload, bool $useMinPageSize = false): array
    {
        $pageSize = $useMinPageSize
            ? (int) data_get($this->requests, 'min_page_size', 1)
            : (int) data_get($this->requests, 'max_page_size', 100);

        $payload[(string) data_get($this->requests, 'page_field', 'page')] = 1;
        $payload[(string) data_get($this->requests, 'page_size_field', 'per_page')] = $pageSize;

        return $payload;
    }

    // ─── Datas ───────────────────────────────────────────────────────────────

    /**
     * Injeta os campos de data conforme o tipo do path:
     *
     * - start + end       → datas do chunk (ex: vendas)
     * - changed_since     → data pré-calculada pelo command e passada via dateStart
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDateFields(array $payload, ?string $dateStart, ?string $dateEnd): array
    {
        $dateFields = data_get($this->pathConfig, 'date_fields', []);

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

    // ─── Loja ────────────────────────────────────────────────────────────────

    /**
     * Injeta o documento da loja (CNPJ sem formatação) no campo
     * definido em requests.store_document_field.
     *
     * Só aplica quando a API exige filtro por loja e um documento foi fornecido.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyStoreDocument(array $payload, ?string $storeDocument): array
    {
        $field = (string) data_get($this->requests, 'store_document_field', '');

        if ($field !== '' && $storeDocument !== null) {
            $payload[$field] = $storeDocument;
        }

        return $payload;
    }
}
