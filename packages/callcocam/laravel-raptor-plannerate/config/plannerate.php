<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Plannerate Features
    |--------------------------------------------------------------------------
    |
    | Habilita/desabilita features opcionais do sistema de planogramas.
    | Cada feature pode ser controlada individualmente via .env
    |
    */
    'features' => [
        /**
         * Geração Automática de Planogramas
         * 
         * Habilita o sistema de geração automática de planogramas baseado em:
         * - Análise ABC
         * - Dados de vendas
         * - Regras de merchandising
         * - Target stock
         * 
         * Para habilitar: PLANNERATE_AUTO_GENERATE_ENABLED=true no .env
         */
        'auto_generate' => env('PLANNERATE_AUTO_GENERATE_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto Generate - Configurações Padrão
    |--------------------------------------------------------------------------
    |
    | Valores padrão para o sistema de geração automática de planogramas
    |
    */
    'auto_generate' => [
        /**
         * Estratégia de otimização padrão
         * Opções: 'abc', 'sales', 'margin', 'mix'
         */
        'default_strategy' => 'abc',

        /**
         * Facings (frentes) mínimo e máximo
         * Controla quantos produtos lado a lado por padrão
         */
        'min_facings' => 1,
        'max_facings' => 10,

        /**
         * Tipo de tabela de vendas padrão
         * Opções: 'sales' (diária), 'monthly_summaries' (mensal agregada)
         */
        'default_table_type' => 'monthly_summaries',

        /**
         * Agrupar por subcategoria por padrão
         */
        'group_by_subcategory' => true,

        /**
         * Incluir produtos sem vendas por padrão
         */
        'include_products_without_sales' => false,

        /**
         * Usar análise ABC existente por padrão
         */
        'use_existing_analysis' => true,
    ],
    'package' => [
        'migrations' => [
            // Caminho usado por plannerate:migrations:sync
            'client_path' => env('PLANNERATE_PACKAGE_CLIENT_MIGRATIONS_PATH', 'database/migrations/clients'),
        ],
    ],
    'defaults' => [
        'gondola' => [
            "gondolaName" => "GND-2602-1841",
            "location" => "Corredor  03",
            "side" => "A",
            "scaleFactor" => 3,
            "flow" => "left_to_right",
            "height" => 200,
            "width" => 100,
            "numModules" => 4,
            "baseHeight" => 20,
            "baseWidth" => 100,
            "baseDepth" => 50,
            "rackWidth" => 4,
            "holeHeight" => 3,
            "holeWidth" => 2,
            "holeSpacing" => 2,
            "shelfHeight" => 4,
            "shelfWidth" => 100,
            "shelfDepth" => 40,
            "numShelves" => 4,
            "productType" => "normal",
            "notes" => null
        ],
    ],
];
