<?php

return [
    'title' => 'Acessos do tenant',
    'description' => 'Gerencie os usuários e perfis de acesso deste tenant.',
    'users_count' => 'Usuários',
    'users_limit' => 'Limite do plano',
    'create_user' => 'Novo usuário',
    'edit_user' => 'Editar usuário',
    'no_user' => 'Nenhum usuário encontrado para este tenant.',
    'empty' => 'Nenhum usuário encontrado',
    'empty_hint' => 'Tente ajustar os filtros ou adicione um novo usuário.',
    'slots_remaining' => 'Sua conta permite mais :count usuário(s)',
    'label' => 'usuário',
    'roles' => 'Perfis de acesso',
    'none' => 'Nenhum perfil',
    'search' => 'Buscar por nome ou email...',
    'statuses' => [
        'all' => 'Todos',
        'deleted' => 'Excluídos',
    ],
    'force_delete' => [
        'title' => 'Excluir usuário definitivamente?',
        'description' => 'Esta ação é irreversível. O usuário :name e seus perfis de acesso serão removidos permanentemente.',
        'confirm' => 'Excluir definitivamente',
    ],
    'messages' => [
        'created' => 'Usuário criado com sucesso.',
        'updated' => 'Acesso do usuário atualizado com sucesso.',
        'status_updated' => 'Status do usuário atualizado com sucesso.',
        'deleted' => 'Usuário removido com sucesso.',
        'restored' => 'Usuário restaurado com sucesso.',
        'force_deleted' => 'Usuário excluído definitivamente.',
        'no_plan_limit' => 'Não é possível cadastrar usuários sem limite definido no plano.',
        'limit_reached' => 'Você atingiu o limite de usuários administradores do seu plano.',
    ],
];
