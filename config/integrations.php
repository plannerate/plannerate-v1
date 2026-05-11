<?php

return [
    'providers' => [
        'sysmo' => [
            'requests' => [
                'method' => 'POST',
                'payload' => 'body',
                'page_field' => 'pagina',
                'page_value_type' => 'string',
                'page_size_field' => 'tamanho_pagina',
                'page_size_payload' => 'body',
                'min_page_size' => 100,
                'max_page_size' => 5000,
                'store_document_field' => 'empresa',
                'products' => [
                    'fallback_path' => '/sysmo-integrador-api/api/integradorService/hubprodutos.listar_produtos',
                    'default_page_size' => 500,
                    'date_fields' => [
                        'changed_since' => 'data_ultima_alteracao',
                    ],
                ],
                'sales' => [
                    'fallback_path' => '/sysmo-integrador-api/api/integradorService/hubvendas.vendas_produtos',
                    'date_fields' => [
                        'start' => 'data_inicial',
                        'end' => 'data_final',
                    ],
                    'dispatch_by_day' => true,
                ],
            ],
            'response' => [
                'items_path' => 'dados',
                'pagination' => [
                    'current_page_path' => 'pagina',
                    'last_page_path' => 'total_paginas',
                ],
            ],
        ],

        'gescooper' => [
            'requests' => [
                'method' => 'GET',
                'payload' => 'query',
                'store_document_field' => 'empresa',
                'page_field' => 'pagina',
                'page_value_type' => 'integer',
                'page_size_field' => 'registros_por_pagina',
                'page_size_payload' => 'query',
                'default_page_size' => 200,
                'min_page_size' => 50,
                'max_page_size' => 500,
                'fixed_query' => [
                    'api-version' => '1.0',
                ],
                'products' => [
                    'fallback_path' => '/Produtos/Produtos',
                    'date_fields' => [
                        'created_from' => 'data_cadastro_de',
                        'created_to' => 'data_cadastro_ate',
                    ],
                ],
                'sales' => [
                    'fallback_path' => '',
                    'skip_statuses' => [404],
                ],
            ],
            'response' => [
                'items_path' => 'data',
                'pagination' => [
                    'current_page_path' => 'pagination.current_page',
                    'per_page_path' => 'pagination.per_page',
                    'total_path' => 'pagination.total',
                    'last_page_path' => 'pagination.last_page',
                ],
            ],
        ],
    ],
];
