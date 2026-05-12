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
     * @return array<string, mixed>
     */
    public function build(?string $dateStart, ?string $dateEnd): array
    {
        $method = strtolower((string) data_get($this->requests, 'method', 'get'));

        $payload = $this->baseParams($method);
        $payload = $this->applyPagination($payload);
        $payload = $this->applyDateFields($payload, $dateStart, $dateEnd);

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
     * Força page=1 e page_size=max_page_size da API,
     * independente do valor configurado na integração.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyPagination(array $payload): array
    {
        $payload[(string) data_get($this->requests, 'page_field', 'page')] = 1;
        $payload[(string) data_get($this->requests, 'page_size_field', 'per_page')] = (int) data_get($this->requests, 'max_page_size', 100);

        return $payload;
    }

    // ─── Datas ───────────────────────────────────────────────────────────────

    /**
     * Injeta os campos de data conforme o tipo do path:
     *
     * - start + end    → datas do chunk fornecido (ex: vendas)
     * - changed_since  → calculado a partir de initial_days (ex: produtos)
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyDateFields(array $payload, ?string $dateStart, ?string $dateEnd): array
    {
        $dateFields = data_get($this->pathConfig, 'date_fields', []);

        if ($dateStart !== null && isset($dateFields['start'])) {
            $payload[$dateFields['start']] = $dateStart;
        }

        if ($dateEnd !== null && isset($dateFields['end'])) {
            $payload[$dateFields['end']] = $dateEnd;
        }

        if ($dateStart === null && isset($dateFields['changed_since'])) {
            $initialDays = (int) data_get($this->pathConfig, 'initial_days', 2);
            $payload[$dateFields['changed_since']] = now()->subDays($initialDays)->toDateString();
        }

        return $payload;
    }
}
