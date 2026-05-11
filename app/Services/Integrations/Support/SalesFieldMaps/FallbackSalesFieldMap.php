<?php

namespace App\Services\Integrations\Support\SalesFieldMaps;

class FallbackSalesFieldMap implements SalesFieldMap
{
    public function provider(): string
    {
        return 'default';
    }

    public function fields(): array
    {
        return [
            'codigo_erp' => ['paths' => ['produto'], 'transforms' => ['string', 'alnum']],
            'ean' => ['paths' => ['ean'], 'transforms' => ['ean']],
            'sale_date' => ['paths' => ['data_venda'], 'transforms' => ['date']],
            'promotion' => ['paths' => ['promocao'], 'transforms' => ['string']],
            'total_sale_quantity' => ['paths' => ['total_sale_quantity', 'quantidade'], 'transforms' => ['float']],
            'total_sale_value' => ['paths' => ['total_sale_value', 'valor_total', 'valor_liquido'], 'transforms' => ['float']],
            'sale_price' => ['paths' => ['sale_price', 'valor_unitario'], 'transforms' => ['float']],
            'acquisition_cost' => ['paths' => ['acquisition_cost', 'custo_aquisicao'], 'transforms' => ['float']],
            'total_profit_margin' => ['paths' => ['total_profit_margin', 'custo_comercial'], 'transforms' => ['float']],
            'valor_impostos' => ['paths' => ['valor_impostos'], 'transforms' => ['float']],
            'custo_medio_loja' => ['paths' => ['custo_medio_loja'], 'transforms' => ['float']],
            'margem_contribuicao' => ['expression' => 'total_sale_value - valor_impostos - custo_medio_loja', 'transforms' => ['round2']],
            'store_document' => ['paths' => ['store_document', 'cnpj', 'loja'], 'transforms' => ['string']],
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        return true;
    }
}
