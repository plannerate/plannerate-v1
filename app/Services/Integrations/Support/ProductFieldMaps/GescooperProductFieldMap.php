<?php

namespace App\Services\Integrations\Support\ProductFieldMaps;

class GescooperProductFieldMap implements ProductFieldMap
{
    public function provider(): string
    {
        return 'gescooper';
    }

    public function fields(): array
    {
        return [
            'codigo_erp' => ['paths' => ['id_produto'], 'transforms' => ['string', 'alnum']],
            'ean' => ['paths' => ['ean'], 'transforms' => ['ean']],
            'name' => ['paths' => ['descricao_completa'], 'transforms' => ['string']],
            'brand' => ['paths' => ['marca'], 'transforms' => ['string']],
            'subbrand' => ['paths' => ['submarca'], 'transforms' => ['string']],
            'description' => ['paths' => ['descricao_completa'], 'transforms' => ['string']],
            'auxiliary_description' => ['paths' => ['descricao_auxiliar'], 'transforms' => ['string']],
            'additional_information' => ['paths' => ['informacao_adicional'], 'transforms' => ['string']],
            'reference' => ['paths' => ['referencia'], 'transforms' => ['string']],
            'color' => ['paths' => ['cor'], 'transforms' => ['string']],
            'fragrance' => ['paths' => ['fragrancia'], 'transforms' => ['string']],
            'flavor' => ['paths' => ['sabor'], 'transforms' => ['string']],
            'packaging_type' => ['paths' => ['tipo_embalagem'], 'transforms' => ['string']],
            'packaging_size' => ['paths' => ['tamanho_embalagem'], 'transforms' => ['string']],
            'measurement_unit' => ['paths' => ['unidade_medida'], 'transforms' => ['string']],
            'unit_measure' => ['paths' => ['unidade_medida'], 'transforms' => ['string']],
            'sortiment_attribute' => ['paths' => ['segmento_varejista'], 'transforms' => ['string']],
            'current_stock' => ['paths' => ['estoque_atual'], 'transforms' => ['float']],
            'last_purchase_date' => ['paths' => ['data_ultima_compra'], 'transforms' => ['date']],
            'sales_status' => ['paths' => ['status_produto'], 'transforms' => ['string']],
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        return is_string($mapped['name'] ?? null) && trim((string) $mapped['name']) !== '';
    }
}
