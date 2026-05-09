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
            'codigo_erp' => ['id_produto'],
            'ean' => ['ean'],
            'name' => ['descricao_completa'],
            'brand' => ['marca'],
            'subbrand' => ['submarca'],
            'description' => ['descricao_completa'],
            'auxiliary_description' => ['descricao_auxiliar'],
            'additional_information' => ['informacao_adicional'],
            'reference' => ['referencia'],
            'color' => ['cor'],
            'fragrance' => ['fragrancia'],
            'flavor' => ['sabor'],
            'packaging_type' => ['tipo_embalagem'],
            'packaging_size' => ['tamanho_embalagem'],
            'measurement_unit' => ['unidade_medida'],
            'unit_measure' => ['unidade_medida'],
            'sortiment_attribute' => ['segmento_varejista'],
            'current_stock' => ['estoque_atual'],
            'last_purchase_date' => ['data_ultima_compra'],
            'sales_status' => ['status_produto'],
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        return is_string($mapped['name'] ?? null) && trim((string) $mapped['name']) !== '';
    }
}
