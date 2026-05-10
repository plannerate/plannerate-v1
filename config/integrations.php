<?php

return [
    'providers' => [
        'sysmo' => [
            'response' => [
                'items_path' => 'dados',
                'pagination' => [
                    'current_page_path' => 'pagina',
                    'last_page_path' => 'total_paginas',
                ],
            ],
        ],

        'gescooper' => [
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
