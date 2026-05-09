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
            'codigo_erp' => ['produto', 'id', 'codigo'],
            'ean' => fn (array $item): ?string => $this->primaryGtin($item) ?? data_get($item, 'ean'),
            'name' => ['descricao', 'nome'],
            'brand' => ['marca.descricao', 'marca'],
            'description' => ['descricao_comercial', 'descricao'],
            'unit_measure' => ['unidade_venda.codigo'],
            'measurement_unit' => ['unidade_venda.descricao'],
            'current_stock' => ['estoque.disponivel'],
            'last_purchase_date' => fn (array $item): ?string => $this->lastPurchaseDate($item),
            'sales_status' => ['cadastro_ativo', 'status'],
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
