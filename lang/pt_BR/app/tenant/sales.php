<?php

return [
    'navigation' => 'Vendas',
    'title' => 'Vendas',
    'description' => 'Gerencie as vendas.',
    'actions' => [
        'new' => 'Nova venda',
        'edit' => 'Editar venda',
    ],
    'fields' => [
        'store' => 'Loja',
        'ean' => 'EAN',
        'codigo_erp' => 'Código ERP',
        'acquisition_cost' => 'Custo de aquisição',
        'sale_price' => 'Preço de venda',
        'total_profit_margin' => 'Margem de lucro unitária',
        'sale_date' => 'Data da venda',
        'promotion' => 'Promoção',
        'total_sale_quantity' => 'Quantidade total vendida',
        'total_sale_value' => 'Valor total da venda',
        'margem_contribuicao' => 'Margem de contribuição',
        'extra_data' => 'Dados extras',
    ],
    'messages' => [
        'created' => 'Venda criada com sucesso.',
        'updated' => 'Venda atualizada com sucesso.',
        'deleted' => 'Venda removida com sucesso.',
    ],
];
