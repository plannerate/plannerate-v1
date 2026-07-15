<?php

return [
    'navigation' => 'Usuários',
    'title' => 'Usuários',
    'description' => 'Gerencie os usuários globais do landlord e seus perfis de acesso.',
    'actions' => [
        'new' => 'Novo usuário',
        'edit' => 'Editar usuário',
    ],
    'fields' => [
        'information' => 'Informações',
        'name' => 'Nome',
        'email' => 'E-mail',
        'password' => 'Senha',
        'password_confirmation' => 'Confirmação de senha',
        'password_hint' => 'Preencha apenas para alterar a senha.',
        'roles' => 'Perfis de acesso',
        'is_active' => 'Ativo',
    ],
    'messages' => [
        'created' => 'Usuário criado com sucesso.',
        'updated' => 'Usuário atualizado com sucesso.',
        'deleted' => 'Usuário removido com sucesso.',
        'force_deleted' => 'Usuário excluído permanentemente com sucesso.',
        'restored' => 'Usuário restaurado com sucesso.',
    ],
];
