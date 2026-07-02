<?php

return [
    'navigation' => 'Permissões',
    'title' => 'Permissões',
    'description' => 'Gerencie o catálogo global de permissões.',
    'actions' => [
        'new' => 'Nova permissão',
        'edit' => 'Editar permissão',
    ],
    'fields' => [
        'name' => 'Nome',
        'name_hint' => 'O identificador (slug) não pode ser alterado após a criação.',
        'short_name' => 'Nome curto',
        'description' => 'Descrição',
        'type' => 'Tipo',
    ],
    'protected' => 'Esta permissão é protegida e não pode ser alterada.',
    'messages' => [
        'created' => 'Permissão criada com sucesso.',
        'updated' => 'Permissão atualizada com sucesso.',
        'deleted' => 'Permissão removida com sucesso.',
        'protected' => 'Esta permissão é protegida e não pode ser alterada.',
    ],
];
