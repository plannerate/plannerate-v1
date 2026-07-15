<?php

return [
    'navigation' => 'Referências EAN',
    'title' => 'Referências EAN',
    'description' => 'Gerencie o catálogo global de referências por EAN.',
    'actions' => [
        'new' => 'Nova referência',
        'edit' => 'Editar referência',
    ],
    'fields' => [
        'ean' => 'EAN',
        'image_front_url' => 'Imagem',
        'reference_description' => 'Descrição de referência',
        'brand' => 'Marca',
        'subbrand' => 'Submarca',
        'packaging_type' => 'Tipo de embalagem',
        'packaging_size' => 'Tamanho da embalagem',
        'measurement_unit' => 'Unidade de medida',
    ],
    'messages' => [
        'created' => 'Referência EAN criada com sucesso.',
        'updated' => 'Referência EAN atualizada com sucesso.',
        'deleted' => 'Referência EAN removida com sucesso.',
        'force_deleted' => 'Referência EAN excluída permanentemente com sucesso.',
        'restored' => 'Referência EAN restaurada com sucesso.',
    ],
];
