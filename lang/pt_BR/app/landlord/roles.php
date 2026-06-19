<?php

return [
    'navigation' => 'Perfis de acesso',
    'title' => 'Perfis de acesso',
    'description' => 'Gerencie os perfis globais e suas permissoes.',
    'types' => [
        'landlord' => 'Landlord',
        'tenant' => 'Tenant',
    ],
    'actions' => [
        'new' => 'Novo perfil',
        'edit' => 'Editar perfil',
    ],
    'fields' => [
        'name' => 'Nome',
        'system_name' => 'Nome do sistema',
        'type' => 'Tipo',
        'permissions' => 'Permissoes',
        'permissions_count' => 'Permissoes',
    ],
    'messages' => [
        'created' => 'Perfil criado com sucesso.',
        'updated' => 'Perfil atualizado com sucesso.',
        'deleted' => 'Perfil removido com sucesso.',
        'in_use' => 'Nao e possivel excluir um perfil com usuarios vinculados.',
        'protected' => 'Este perfil e protegido e nao pode ser alterado.',
    ],
];
