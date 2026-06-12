<?php

return [
    'package' => [
        'migrations' => [
            // Caminho usado por plannerate:migrations:sync
            'client_path' => env('PLANNERATE_PACKAGE_CLIENT_MIGRATIONS_PATH', 'database/migrations/clients'),
        ],
    ],
    'auto_planogram' => [
        /*
         * Parâmetros do cálculo de Estoque Alvo por classe ABC.
         *
         * service_levels: nível de serviço usado no z-score do estoque de segurança.
         * coverage_days : dias de cobertura do estoque mínimo (média diária × dias).
         *
         * Defaults preservam a regra da planilha VBA original (docs/ESTOQUE-ALVO.md):
         * classe A repõe com mais frequência (menos dias, NS menor). Tenants que
         * preferem o padrão de mercado (A com NS mais alto) podem sobrescrever aqui.
         */
        'target_stock' => [
            'service_levels' => [
                'A' => 0.7,
                'B' => 0.8,
                'C' => 0.9,
            ],
            'coverage_days' => [
                'A' => 2,
                'B' => 5,
                'C' => 7,
            ],
        ],
    ],
    'defaults' => [
        'gondola' => [
            'gondolaName' => 'GND-2602-1841',
            'location' => 'Corredor  03',
            'side' => 'A',
            'scaleFactor' => 3,
            'flow' => 'left_to_right',
            'height' => 200,
            'width' => 100,
            'numModules' => 4,
            'baseHeight' => 20,
            'baseWidth' => 100,
            'baseDepth' => 50,
            'rackWidth' => 4,
            'holeHeight' => 3,
            'holeWidth' => 2,
            'holeSpacing' => 2,
            'shelfHeight' => 4,
            'shelfWidth' => 100,
            'shelfDepth' => 40,
            'numShelves' => 4,
            'productType' => 'normal',
            'notes' => null,
        ],
    ],
];
