<?php

return [
    'timeout' => env('INTEGRATION_TIMEOUT', 60),

    'fetch_delay' => env('INTEGRATION_FETCH_DELAY', 3),

    /*
     * Janela (em dias) sempre re-buscada no sync incremental, mesmo quando os
     * dias já têm registros: um dia com fetch interrompido no meio (só parte
     * das páginas persistida) contaria como "completo" e nunca seria
     * completado. A re-busca é idempotente (upsert por id determinístico).
     */
    'recheck_days' => env('INTEGRATION_RECHECK_DAYS', 3),

    'import_clear_tables' => [
        'products' => ['product_store', 'products'],
        'sales' => ['sales'],
    ],

    'field_map_tables' => [
        'products' => [
            'label' => 'Produtos',
            'columns' => [
                'tenant_id',
                'user_id',
                'image_id',
                'category_id',
                'client_id',
                'name',
                'slug',
                'ean',
                'codigo_erp',
                'stackable',
                'perishable',
                'flammable',
                'hangable',
                'no_sales',
                'no_purchases',
                'description',
                'sales_status',
                'sales_purchases',
                'status',
                'sync_source',
                'resolution_status',
                'resolution_details',
                'sync_at',
                'url',
                'type',
                'reference',
                'fragrance',
                'flavor',
                'color',
                'brand',
                'subbrand',
                'packaging_type',
                'packaging_size',
                'measurement_unit',
                'packaging_content',
                'unit_measure',
                'auxiliary_description',
                'additional_information',
                'sortiment_attribute',
                'width',
                'height',
                'depth',
                'weight',
                'unit',
                'has_dimensions',
                'dimension_status',
                'current_stock',
                'last_purchase_date',
            ],
        ],
        'sales' => [
            'label' => 'Vendas',
            'columns' => [
                'tenant_id',
                'store_id',
                'product_id',
                'ean',
                'codigo_erp',
                'acquisition_cost',
                'sale_price',
                'total_profit_margin',
                'sale_date',
                'promotion',
                'total_sale_quantity',
                'total_sale_value',
                'margem_contribuicao',
                'extra_data',
            ],
        ],
        'stores' => [
            'label' => 'Lojas',
            'columns' => [
                'tenant_id',
                'user_id',
                'name',
                'document',
                'slug',
                'code',
                'phone',
                'email',
                'status',
                'description',
                'map_image_path',
                'map_regions',
            ],
        ],
    ],
];
