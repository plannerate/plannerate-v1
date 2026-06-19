<?php

return [
    'navigation' => 'Gondolas',
    'title' => 'Gondolas',
    'description' => 'Gerencie as gondolas vinculadas ao planograma.',
    'planogram_prefix' => 'Planograma',
    'actions' => [
        'new' => 'Nova gondola',
        'edit' => 'Editar gondola',
    ],
    'fields' => [
        'name' => 'Nome',
        'modules' => 'Modulos',
        'location' => 'Localizacao',
        'side' => 'Lado',
        'flow' => 'Fluxo',
        'alignment' => 'Alinhamento',
        'scale_factor' => 'Escala',
        'status' => 'Status',
    ],
    'status_draft' => 'Rascunho',
    'status_published' => 'Publicado',
    'messages' => [
        'created' => 'Gondola criada com sucesso.',
        'updated' => 'Gondola atualizada com sucesso.',
        'deleted' => 'Gondola removida com sucesso.',
    ],
];
