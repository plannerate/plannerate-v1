<?php

return [
    'navigation' => 'Referencias EAN',
    'title' => 'Referencias EAN',
    'description' => 'Gerencie as referencias por EAN para apoio a padronizacao de produtos.',
    'actions' => [
        'new' => 'Nova referencia EAN',
        'edit' => 'Editar referencia EAN',
    ],
    'fields' => [
        'ean' => 'EAN',
        'reference_description' => 'Descricao de referencia',
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
        'created' => 'Referencia EAN criada com sucesso.',
        'updated' => 'Referencia EAN atualizada com sucesso.',
        'deleted' => 'Referencia EAN removida com sucesso.',
    ],
];
