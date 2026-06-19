<?php

return [
    'navigation' => 'Referencias EAN',
    'title' => 'Referencias EAN',
    'description' => 'Gerencie o catalogo global de referencias por EAN.',
    'actions' => [
        'new' => 'Nova referencia',
        'edit' => 'Editar referencia',
    ],
    'fields' => [
        'ean' => 'EAN',
        'image_front_url' => 'Imagem',
        'reference_description' => 'Descricao de referencia',
        'brand' => 'Marca',
        'subbrand' => 'Submarca',
        'packaging_type' => 'Tipo de embalagem',
        'packaging_size' => 'Tamanho da embalagem',
        'measurement_unit' => 'Unidade de medida',
    ],
    'messages' => [
        'created' => 'Referencia EAN criada com sucesso.',
        'updated' => 'Referencia EAN atualizada com sucesso.',
        'deleted' => 'Referencia EAN removida com sucesso.',
    ],
];
