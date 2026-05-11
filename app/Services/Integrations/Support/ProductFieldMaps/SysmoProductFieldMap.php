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
            'ean' => ['paths' => ['gtins.completo[principal=S].gtin', 'gtins.resumido.*', 'ean'], 'transforms' => ['first', 'ean']],
            'name' => ['paths' => ['descricao', 'nome'], 'transforms' => ['string']],
            'brand' => ['paths' => ['marca.descricao', 'marca'], 'transforms' => ['string']],
            'description' => ['paths' => ['descricao_comercial', 'descricao'], 'transforms' => ['string']],
            'unit_measure' => ['paths' => ['unidade_venda.codigo'], 'transforms' => ['string']],
            'measurement_unit' => ['paths' => ['unidade_venda.descricao'], 'transforms' => ['string']],
            'current_stock' => ['paths' => ['estoque.disponivel'], 'transforms' => ['float']],
            'last_purchase_date' => ['paths' => ['fornecedores.*.data_ultima_compra'], 'transforms' => ['filter_filled', 'max_date']],
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
}
