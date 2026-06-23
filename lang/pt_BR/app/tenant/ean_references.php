<?php

return [
    'navigation' => 'Referências EAN',
    'title' => 'Referências EAN',
    'description' => 'Gerencie as referências por EAN para apoio a padronização de produtos.',
    'actions' => [
        'new' => 'Nova referência EAN',
        'edit' => 'Editar referência EAN',
    ],
    'fields' => [
        'ean' => 'EAN',
        'reference_description' => 'Descrição de referência',
        'brand' => 'Marca',
        'subbrand' => 'Submarca',
        'packaging_type' => 'Tipo de embalagem',
        'packaging_size' => 'Tamanho da embalagem',
        'measurement_unit' => 'Unidade de medida',
        'dimensions_section' => 'Dimensões',
        'width' => 'Largura',
        'height' => 'Altura',
        'depth' => 'Profundidade',
        'weight' => 'Peso',
        'unit' => 'Unidade',
        'dimensions_status' => 'Status da dimensão',
        'dimensions_column' => 'Medidas',
    ],
    'messages' => [
        'created' => 'Referência EAN criada com sucesso.',
        'updated' => 'Referência EAN atualizada com sucesso.',
        'deleted' => 'Referência EAN removida com sucesso.',
    ],
];
