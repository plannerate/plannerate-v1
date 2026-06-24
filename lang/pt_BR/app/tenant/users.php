<?php

return [
    'navigation' => 'Usuários',
    'title' => 'Usuários',
    'description' => 'Gerencie os usuários.',
    'actions' => [
        'new' => 'Novo usuário',
        'edit' => 'Editar usuário',
    ],
    'fields' => [
        'name' => 'Nome',
        'email' => 'E-mail',
        'password' => 'Senha',
        'password_confirmation' => 'Confirmação de senha',
        'password_hint' => 'Preencha apenas se quiser alterar a senha.',
        'roles' => 'Perfis',
        'is_active' => 'Ativo',
        'is_active_hint' => 'Usuários inativos não conseguem fazer login.',
    ],
    'messages' => [
        'created' => 'Usuário criado com sucesso.',
        'updated' => 'Usuário atualizado com sucesso.',
        'deleted' => 'Usuário removido com sucesso.',
    ],
    'limit' => [
        'title' => 'Limite de usuários',
    ],
];
