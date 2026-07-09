<?php

return [
    'navigation' => 'Perfis de acesso',
    'title' => 'Perfis de acesso',
    'description' => 'Gerencie os perfis globais e suas permissões.',
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
        'system_name_hint' => 'O identificador do sistema não pode ser alterado após a criação.',
        'type' => 'Tipo',
        'is_administrative' => 'Perfil administrativo',
        'is_administrative_hint' => 'Usuários com este perfil contam no limite do plano (definido por plano na tela de Planos).',
        'permissions' => 'Permissões',
        'permissions_count' => 'Permissões',
    ],
    'protected' => 'Este perfil é protegido: apenas o nome de exibição pode ser alterado.',
    'permissions_ui' => [
        'filter_placeholder' => 'Filtrar permissões...',
        'select_all' => 'Selecionar todas',
        'deselect_all' => 'Desmarcar todas',
        'empty' => 'Nenhuma permissão disponível para este tipo.',
        'selected_count' => ':count selecionada(s)',
    ],
    'messages' => [
        'created' => 'Perfil criado com sucesso.',
        'updated' => 'Perfil atualizado com sucesso.',
        'deleted' => 'Perfil removido com sucesso.',
        'in_use' => 'Não é possível excluir um perfil com usuários vinculados.',
        'protected' => 'Este perfil é protegido e não pode ser alterado.',
    ],
];
