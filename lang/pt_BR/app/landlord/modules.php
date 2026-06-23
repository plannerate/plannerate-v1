<?php

return [
    'navigation' => 'Módulos',
    'title' => 'Módulos',
    'description' => 'Gerencie os módulos disponíveis para os tenants.',
    'actions' => [
        'new' => 'Novo módulo',
        'edit' => 'Editar módulo',
    ],
    'fields' => [
        'name' => 'Nome',
        'description' => 'Descrição',
        'is_active' => 'Ativo',
        'tenants_count' => 'Tenants',
    ],
    'messages' => [
        'created' => 'Módulo criado com sucesso.',
        'updated' => 'Módulo atualizado com sucesso.',
        'deleted' => 'Módulo removido com sucesso.',
        'in_use' => 'Não é possível excluir um módulo que possui tenants vinculados.',
    ],
];
