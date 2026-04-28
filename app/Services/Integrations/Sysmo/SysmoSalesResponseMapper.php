<?php

namespace App\Services\Integrations\Sysmo;

use App\Services\Integrations\Mappers\SalesResponseMapper;

class SysmoSalesResponseMapper implements SalesResponseMapper
{
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
            'external_id' => $this->pickString($item, ['id', 'codigo_venda', 'venda_id']),
            'product_code' => $this->pickString($item, ['produto']),
            'codigo_erp' => $this->pickString($item, ['produto']),
            'product_ean' => $this->pickString($item, ['ean', 'codigo_barras', 'produto_ean']),
            'promocao' => $this->pickString($item, ['promocao']),
            'empresa' => $this->pickString($item, ['empresa']),
            'sold_at' => $this->pickString($item, ['data_venda', 'data', 'data_movimento']),
            'quantity' => $this->pickFloat($item, ['quantidade', 'qtd', 'qtde']),
            'valor_liquido' => $this->pickFloat($item, ['valor_liquido']),
            'valor_impostos' => $this->pickFloat($item, ['valor_impostos']),
            'unit_price' => $this->pickFloat($item, ['valor_unitario', 'preco_unitario', 'preco_efetivo']),
            'total_price' => $this->pickFloat($item, ['valor_total', 'total', 'valor_liquido']),
            'custo_aquisicao' => $this->pickFloat($item, ['custo_aquisicao']),
            'custo_medio_loja' => $this->pickFloat($item, ['custo_medio_loja']),
            'custo_medio_geral' => $this->pickFloat($item, ['custo_medio_geral']),
            'custo_comercial' => $this->pickFloat($item, ['custo_comercial']),
            'departamento' => $this->pickString($item, ['departamento']),
            'departamento_descricao' => $this->pickString($item, ['departamento_descricao']),
            'categoria' => $this->pickString($item, ['categoria']),
            'categoria_descricao' => $this->pickString($item, ['categoria_descricao']),
            'store_identifier' => $this->pickString($item, ['cnpj', 'loja', 'filial']),
            'raw' => $item,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function pickFloat(array $item, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_numeric($value)) {
                return (float) $value;
            }

            if (is_string($value) && is_numeric(str_replace(',', '.', $value))) {
                return (float) str_replace(',', '.', $value);
            }
        }

        return null;
    }
}
