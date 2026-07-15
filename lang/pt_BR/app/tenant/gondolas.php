<?php

return [
    'navigation' => 'Gôndolas',
    'title' => 'Gôndolas',
    'description' => 'Gerencie as gôndolas vinculadas ao planograma.',
    'planogram_prefix' => 'Planograma',
    'actions' => [
        'new' => 'Nova gôndola',
        'edit' => 'Editar gôndola',
    ],
    'fields' => [
        'name' => 'Nome',
        'modules' => 'Módulos',
        'location' => 'Localização',
        'side' => 'Lado',
        'flow' => 'Fluxo',
        'alignment' => 'Alinhamento',
        'scale_factor' => 'Escala',
        'status' => 'Status',
    ],
    'status_draft' => 'Rascunho',
    'status_published' => 'Publicado',
    'messages' => [
        'created' => 'Gôndola criada com sucesso.',
        'updated' => 'Gôndola atualizada com sucesso.',
        'deleted' => 'Gôndola removida com sucesso.',
        'force_deleted' => 'Gôndola excluída definitivamente.',
        'restored' => 'Gôndola restaurada com sucesso.',
    ],
];
