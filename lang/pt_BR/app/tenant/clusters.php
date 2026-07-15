<?php

return [
    'navigation' => 'Clusters',
    'title' => 'Clusters',
    'description' => 'Gerencie os clusters.',
    'actions' => [
        'new' => 'Novo cluster',
        'edit' => 'Editar cluster',
    ],
    'fields' => [
        'name' => 'Nome',
        'store' => 'Loja',
        'specification_1' => 'Especificação 1',
        'specification_2' => 'Especificação 2',
        'specification_3' => 'Especificação 3',
        'status' => 'Status',
        'description' => 'Descrição',
    ],
    'status_draft' => 'Rascunho',
    'status_published' => 'Publicado',
    'messages' => [
        'created' => 'Cluster criado com sucesso.',
        'updated' => 'Cluster atualizado com sucesso.',
        'deleted' => 'Cluster removido com sucesso.',
        'force_deleted' => 'Cluster excluído definitivamente.',
        'restored' => 'Cluster restaurado com sucesso.',
    ],
];
