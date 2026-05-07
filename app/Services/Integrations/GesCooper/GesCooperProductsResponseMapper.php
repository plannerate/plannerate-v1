<?php

namespace App\Services\Integrations\GesCooper;

use App\Services\Integrations\GesCooper\Concerns\NormalizesGesCooperValues;
use App\Services\Integrations\Mappers\ProductsResponseMapper;

class GesCooperProductsResponseMapper implements ProductsResponseMapper
{
    use NormalizesGesCooperValues;

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function mapMany(array $items): array
    {
        return array_map(
            fn (array $item): array => $this->mapItem($item),
            $items,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function mapItem(array $item): array
    {
        return [
            'external_id' => $this->normalizeCodigoErp($this->normalizeString($item['id_produto'] ?? null)),
            'ean' => $this->normalizeString($item['ean'] ?? null),
            'name' => $this->normalizeString($item['descricao_completa'] ?? null),
            'brand' => $this->normalizeString($item['marca'] ?? null),
            'subbrand' => $this->normalizeString($item['submarca'] ?? null),
            'status' => $this->normalizeString($item['status_produto'] ?? null),
            'last_purchase_date' => $this->normalizeDate($item['data_ultima_compra'] ?? null),
            'height' => $this->normalizeFloat($item['altura'] ?? null),
            'width' => $this->normalizeFloat($item['largura'] ?? null),
            'depth' => $this->normalizeFloat($item['profundidade'] ?? null),
            'packaging_type' => $this->normalizeString($item['tipo_embalagem'] ?? null),
            'packaging_size' => $this->normalizeString($item['tamanho_embalagem'] ?? null),
            'unit' => $this->normalizeString($item['unidade_medida'] ?? null),
            'fragrance' => $this->normalizeString($item['fragrancia'] ?? null),
            'flavor' => $this->normalizeString($item['sabor'] ?? null),
            'color' => $this->normalizeString($item['cor'] ?? null),
            'reference' => $this->normalizeString($item['referencia'] ?? null),
            'auxiliary_description' => $this->normalizeString($item['descricao_auxiliar'] ?? null),
            'additional_information' => $this->normalizeString($item['informacao_adicional'] ?? null),
            'current_stock' => $this->normalizeFloat($item['estoque_atual'] ?? null),
            'sortiment_attribute' => $this->normalizeString($item['segmento_varejista'] ?? null),
            'raw' => $item,
        ];
    }
}
