<?php

return [
    'package' => [
        'migrations' => [
            // Caminho usado por plannerate:migrations:sync
            'client_path' => env('PLANNERATE_PACKAGE_CLIENT_MIGRATIONS_PATH', 'database/migrations/clients'),
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
