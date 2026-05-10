<?php

namespace App\Services\Integrations\Support\ProductFieldMaps;

class SysmoProductFieldMap implements ProductFieldMap
{
    public function provider(): string
    {
        return 'sysmo';
    }

    public function fields(): array
    {
        return [
            'codigo_erp' => ['paths' => ['produto', 'id', 'codigo'], 'transforms' => ['string', 'alnum']],
            'ean' => ['paths' => fn (array $item): ?string => $this->primaryGtin($item) ?? data_get($item, 'ean'), 'transforms' => ['ean']],
            'name' => ['paths' => ['descricao', 'nome'], 'transforms' => ['string']],
            'brand' => ['paths' => ['marca.descricao', 'marca'], 'transforms' => ['string']],
            'description' => ['paths' => ['descricao_comercial', 'descricao'], 'transforms' => ['string']],
            'unit_measure' => ['paths' => ['unidade_venda.codigo'], 'transforms' => ['string']],
            'measurement_unit' => ['paths' => ['unidade_venda.descricao'], 'transforms' => ['string']],
            'current_stock' => ['paths' => ['estoque.disponivel'], 'transforms' => ['float']],
            'last_purchase_date' => ['paths' => fn (array $item): ?string => $this->lastPurchaseDate($item), 'transforms' => ['date']],
            'sales_status' => ['paths' => ['cadastro_ativo', 'status'], 'transforms' => ['string']],
            'subbrand' => fn () => null,
            'packaging_type' => fn () => null,
            'packaging_size' => fn () => null,
            'auxiliary_description' => fn () => null,
            'additional_information' => fn () => null,
            'reference' => fn () => null,
            'color' => fn () => null,
            'fragrance' => fn () => null,
            'flavor' => fn () => null,
            'sortiment_attribute' => fn () => null,
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        foreach (['cadastro_ativo', 'ativo_na_empresa', 'pertence_ao_mix'] as $flag) {
            if (! array_key_exists($flag, $raw)) {
                continue;
            }

            if (strtoupper((string) ($raw[$flag] ?? '')) === 'N') {
                return false;
            }
        }

        return is_string($mapped['name'] ?? null) && trim((string) $mapped['name']) !== '';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function primaryGtin(array $item): ?string
    {
        $entries = data_get($item, 'gtins.completo');
        if (! is_array($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            if (($entry['principal'] ?? null) === 'S') {
                return is_string($entry['gtin'] ?? null) ? $entry['gtin'] : null;
            }
        }

        foreach ($entries as $entry) {
            if (is_array($entry) && is_string($entry['gtin'] ?? null)) {
                return $entry['gtin'];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function lastPurchaseDate(array $item): ?string
    {
        $suppliers = $item['fornecedores'] ?? null;
        if (! is_array($suppliers)) {
            return null;
        }

        $latest = null;
        foreach ($suppliers as $supplier) {
            if (! is_array($supplier)) {
                continue;
            }

            $date = $supplier['data_ultima_compra'] ?? null;
            if (! is_string($date) || trim($date) === '') {
                continue;
            }

            if ($latest === null || $date > $latest) {
                $latest = $date;
            }
        }

        return $latest;
    }
}
