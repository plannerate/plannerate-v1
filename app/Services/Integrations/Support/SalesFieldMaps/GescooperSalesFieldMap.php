<?php

namespace App\Services\Integrations\Support\SalesFieldMaps;

class GescooperSalesFieldMap implements SalesFieldMap
{
    public function provider(): string
    {
        return 'gescooper';
    }

    public function fields(): array
    {
        return [
            'codigo_erp' => ['paths' => ['id_produto', 'codigo_erp', 'produto'], 'transforms' => ['string', 'alnum']],
            'ean' => ['paths' => ['ean'], 'transforms' => ['ean']],
            'sale_date' => ['paths' => ['data_venda', 'sale_date', 'data'], 'transforms' => ['date']],
            'promotion' => ['paths' => ['promocao', 'promotion'], 'transforms' => ['string']],
            'total_sale_quantity' => ['paths' => ['quantidade', 'qtd', 'qtde'], 'transforms' => ['float']],
            'total_sale_value' => ['paths' => ['valor_total', 'total', 'valor_liquido'], 'transforms' => ['float']],
            'sale_price' => ['paths' => ['valor_unitario', 'preco_unitario', 'preco_efetivo'], 'transforms' => ['float']],
            'acquisition_cost' => ['paths' => ['custo_aquisicao'], 'transforms' => ['float']],
            'total_profit_margin' => ['paths' => ['custo_comercial'], 'transforms' => ['float']],
            'valor_impostos' => ['paths' => ['valor_impostos'], 'transforms' => ['float']],
            'custo_medio_loja' => ['paths' => ['custo_medio_loja'], 'transforms' => ['float']],
            'store_document' => ['paths' => ['cnpj', 'loja', 'filial', 'store_identifier'], 'transforms' => ['string']],
        ];
    }

    public function passesValidation(array $mapped, array $raw): bool
    {
        return ($mapped['codigo_erp'] ?? null) !== null
            && ($mapped['sale_date'] ?? null) !== null;
    }
}
